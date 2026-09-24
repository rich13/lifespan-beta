<?php

namespace App\Jobs;

use App\Models\ImportProgress;
use App\Models\ImprovementAttempt;
use App\Models\Span;
use App\Models\User;
use App\Services\ImprovementCreationPolicy;
use App\Services\SpanImprovementCoordinator;
use Illuminate\Bus\Queueable;
use Illuminate\Bus\UniqueLock;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\MaxAttemptsExceededException;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ImproveSpansJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const IMPORT_TYPE = 'span_improvement';

    public int $timeout = 0;

    public int $tries = 1;

    public int $uniqueFor = 86400;

    private const DELAY_MS = [
        SpanImprovementCoordinator::IMPROVER_WIKIPEDIA => 2000,
        SpanImprovementCoordinator::IMPROVER_MUSICBRAINZ => 1100,
        SpanImprovementCoordinator::IMPROVER_GEOCODE => 1100,
    ];

    public function __construct(
        private readonly string $userId
    ) {}

    public function uniqueId(): string
    {
        return $this->userId;
    }

    public static function releaseUniquenessFor(string $userId): void
    {
        (new UniqueLock(Cache::driver()))->release(new self($userId));
    }

    public function handle(SpanImprovementCoordinator $coordinator): void
    {
        $progress = $this->getOrCreateProgress();
        if ($this->stopRequested($progress)) {
            return;
        }

        $user = User::find($this->userId);
        if (! $user) {
            $this->updateProgress(['status' => 'failed', 'error_message' => 'User not found']);

            return;
        }

        set_time_limit(0);

        $work = $coordinator->pendingWork();
        $progress->refresh();
        if ($this->stopRequested($progress)) {
            return;
        }

        $total = count($work);
            $budget = $coordinator->aiBudget();

            $this->updateProgress([
                'status' => 'running',
                'started_at' => now(),
                'total_items' => $total,
                'processed_items' => 0,
                'created_items' => 0,
                'skipped_items' => 0,
                'error_count' => 0,
                'progress_percentage' => $total > 0 ? 0 : 100,
                'queue_counts' => $coordinator->queueCounts(),
                'ai_token_budget' => $budget['limit'],
                'ai_tokens_used' => $budget['used'],
                'activity_log' => [],
                'current_item' => null,
                'current_improver' => null,
            ]);

            if ($total === 0) {
                $this->updateProgress([
                    'status' => 'completed',
                    'progress_percentage' => 100,
                    'completed_at' => now(),
                ]);

                return;
            }

            $processed = 0;
            $improved = 0;
            $skipped = 0;
            $errors = 0;
            $activity = [];
            $creation = app(ImprovementCreationPolicy::class);
            $creation->beginRun();

            try {
            foreach ($work as $index => $item) {
                $progress->refresh();
                if ($this->stopRequested($progress)) {
                    $this->finishCancelled($processed, $improved, $skipped, $errors, $total);

                    return;
                }

                if ($index > 0 && ! app()->environment('testing')) {
                    usleep((self::DELAY_MS[$item['improver']] ?? 1000) * 1000);
                }

                $progress->refresh();
                if ($this->stopRequested($progress)) {
                    $this->finishCancelled($processed, $improved, $skipped, $errors, $total);

                    return;
                }

                $span = Span::find($item['span_id']);
                $this->updateProgress([
                    'current_item' => $span?->name ?? $item['span_id'],
                    'current_improver' => $item['improver'],
                    'last_activity' => now()->toIso8601String(),
                ]);

                try {
                    $result = $coordinator->improve($item);
                } catch (\Throwable $e) {
                    Log::error('Span improvement failed', [
                        'span_id' => $item['span_id'],
                        'improver' => $item['improver'],
                        'error' => $e->getMessage(),
                    ]);
                    ImprovementAttempt::create([
                        'span_id' => $item['span_id'],
                        'improver' => $item['improver'],
                        'outcome' => 'error',
                        'detail' => $e->getMessage(),
                        'created_at' => now(),
                    ]);
                    $result = [
                        'outcome' => 'error',
                        'detail' => $e->getMessage(),
                        'name' => $span?->name ?? $item['span_id'],
                    ];
                }

                $processed++;
                if ($result['outcome'] === 'improved') {
                    $improved++;
                } elseif ($result['outcome'] === 'error') {
                    $errors++;
                } else {
                    $skipped++;
                }

                array_unshift($activity, [
                    'name' => $result['name'],
                    'improver' => $item['improver'],
                    'outcome' => $result['outcome'],
                    'detail' => $result['detail'],
                    'at' => now()->toIso8601String(),
                ]);
                $activity = array_slice($activity, 0, 25);

                $this->updateProgress([
                    'status' => 'running',
                    'processed_items' => $processed,
                    'created_items' => $improved,
                    'skipped_items' => $skipped,
                    'error_count' => $errors,
                    'progress_percentage' => min(100, round(($processed / $total) * 100, 1)),
                    'current_item' => $result['name'],
                    'current_improver' => $item['improver'],
                    'activity_log' => $activity,
                    'last_activity' => now()->toIso8601String(),
                ]);
            }

            $progress->refresh();
            if ($this->stopRequested($progress)) {
                $this->finishCancelled($processed, $improved, $skipped, $errors, $total);

                return;
            }

            $this->updateProgress([
                'status' => 'completed',
                'processed_items' => $processed,
                'created_items' => $improved,
                'skipped_items' => $skipped,
                'error_count' => $errors,
                'progress_percentage' => 100,
                'current_item' => null,
                'current_improver' => null,
                'completed_at' => now(),
            ]);
            } finally {
                $creation->endRun();
            }
    }

    public function failed(\Throwable $exception): void
    {
        app(ImprovementCreationPolicy::class)->endRun();

        $message = $exception instanceof MaxAttemptsExceededException
            ? 'The coordinator was interrupted. Start it again to continue.'
            : $exception->getMessage();

        $this->updateProgress([
            'status' => 'failed',
            'error_message' => $message,
            'failed_at' => now()->toIso8601String(),
        ]);
    }

    private function stopRequested(ImportProgress $progress): bool
    {
        return (bool) ($progress->metadata['cancel_requested'] ?? false)
            || $progress->status === 'cancelled';
    }

    private function finishCancelled(int $processed, int $improved, int $skipped, int $errors, int $total): void
    {
        $this->updateProgress([
            'status' => 'cancelled',
            'processed_items' => $processed,
            'created_items' => $improved,
            'skipped_items' => $skipped,
            'error_count' => $errors,
            'progress_percentage' => $total > 0 ? min(100, round(($processed / $total) * 100, 1)) : 0,
            'cancel_requested' => true,
            'current_item' => null,
            'current_improver' => null,
            'cancelled_at' => now()->toIso8601String(),
        ]);
    }

    private ?ImportProgress $progressRow = null;

    private function getOrCreateProgress(): ImportProgress
    {
        if ($this->progressRow) {
            return $this->progressRow;
        }

        $this->progressRow = ImportProgress::firstOrCreate(
            [
                'import_type' => self::IMPORT_TYPE,
                'plaque_type' => null,
                'user_id' => $this->userId,
            ],
            [
                'total_items' => 0,
                'processed_items' => 0,
                'created_items' => 0,
                'skipped_items' => 0,
                'error_count' => 0,
                'status' => 'running',
                'started_at' => now(),
                'metadata' => [],
            ]
        );

        return $this->progressRow;
    }

    private function updateProgress(array $data): void
    {
        $progress = $this->getOrCreateProgress();
        $progress->refresh();
        if ($this->stopRequested($progress) && (($data['status'] ?? null) === 'running')) {
            return;
        }

        $progress->mergeProgress($data);
    }
}
