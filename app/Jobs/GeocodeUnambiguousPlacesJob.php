<?php

namespace App\Jobs;

use App\Models\ImportProgress;
use App\Models\Span;
use App\Models\User;
use App\Services\PlaceGeocodingWorkflowService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GeocodeUnambiguousPlacesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const IMPORT_TYPE = 'unambiguous_place_geocode';

    public int $timeout = 0;

    public int $tries = 1;

    /**
     * @param  list<string>|null  $spanIds
     */
    public function __construct(
        private readonly string $userId,
        private readonly ?array $spanIds = null
    ) {
    }

    public function handle(PlaceGeocodingWorkflowService $workflow): void
    {
        try {
            $progress = $this->getOrCreateProgress();
            if ($this->stopRequested($progress)) {
                return;
            }

            $user = User::find($this->userId);
            if (! $user) {
                $this->updateProgress(['status' => 'failed', 'error_message' => 'User not found']);

                return;
            }

            $ids = $this->spanIds ?? $workflow->idsNeedingGeocoding();
            $total = count($ids);
            $geocoded = 0;
            $needsDisambiguation = 0;
            $noMatch = 0;
            $skipped = 0;
            $errors = 0;

            set_time_limit(0);

            $this->updateProgress([
                'status' => 'running',
                'started_at' => now(),
                'total_items' => $total,
                'processed_items' => 0,
                'created_items' => 0,
                'skipped_items' => 0,
                'error_count' => 0,
                'needs_disambiguation' => 0,
                'no_match' => 0,
                'progress_percentage' => $total > 0 ? 0 : 100,
            ]);

            if ($total === 0) {
                $this->updateProgress([
                    'status' => 'completed',
                    'progress_percentage' => 100,
                    'completed_at' => now(),
                ]);

                return;
            }

            foreach (array_values($ids) as $index => $spanId) {
                if ($this->stopRequested($progress)) {
                    $this->finishCancelled($index, $geocoded, $needsDisambiguation, $noMatch, $skipped, $errors, $total);

                    return;
                }

                if ($index > 0 && ! app()->environment('testing')) {
                    usleep(1100000);
                }

                $span = Span::find($spanId);
                $currentName = $span?->name ?? $spanId;
                $this->updateProgress([
                    'current_item' => $currentName,
                    'last_activity' => now()->toIso8601String(),
                ]);

                $batch = $workflow->batchProcess([$spanId]);
                if ($this->stopRequested($progress)) {
                    $this->finishCancelled($index, $geocoded, $needsDisambiguation, $noMatch, $skipped, $errors, $total);

                    return;
                }
                $geocoded += $batch['geocoded'];
                $needsDisambiguation += $batch['needs_disambiguation'];
                $noMatch += $batch['no_match'];
                $skipped += $batch['skipped'];
                $errors += $batch['errors'];

                $processed = $index + 1;
                $this->updateProgress([
                    'status' => 'running',
                    'total_items' => $total,
                    'processed_items' => $processed,
                    'created_items' => $geocoded,
                    'skipped_items' => $needsDisambiguation + $noMatch + $skipped,
                    'error_count' => $errors,
                    'needs_disambiguation' => $needsDisambiguation,
                    'no_match' => $noMatch,
                    'progress_percentage' => min(100, round(($processed / $total) * 100, 1)),
                    'current_item' => $currentName,
                    'last_activity' => now()->toIso8601String(),
                ]);
            }

            $this->updateProgress([
                'status' => 'completed',
                'processed_items' => $total,
                'created_items' => $geocoded,
                'skipped_items' => $needsDisambiguation + $noMatch + $skipped,
                'error_count' => $errors,
                'needs_disambiguation' => $needsDisambiguation,
                'no_match' => $noMatch,
                'progress_percentage' => 100,
                'completed_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('GeocodeUnambiguousPlacesJob failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->updateProgress([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'failed_at' => now()->toIso8601String(),
            ]);

            throw $e;
        }
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

    private function stopRequested(ImportProgress $progress): bool
    {
        $progress->refresh();

        return (bool) ($progress->metadata['cancel_requested'] ?? false)
            || $progress->status === 'cancelled';
    }

    private function finishCancelled(int $processed, int $geocoded, int $needsDisambiguation, int $noMatch, int $skipped, int $errors, int $total): void
    {
        $this->updateProgress([
            'status' => 'cancelled',
            'processed_items' => $processed,
            'created_items' => $geocoded,
            'skipped_items' => $needsDisambiguation + $noMatch + $skipped,
            'error_count' => $errors,
            'needs_disambiguation' => $needsDisambiguation,
            'no_match' => $noMatch,
            'progress_percentage' => $total > 0 ? min(100, round(($processed / $total) * 100, 1)) : 0,
            'cancel_requested' => true,
            'cancelled_at' => now()->toIso8601String(),
            'current_item' => null,
        ]);
    }

    private function updateProgress(array $data): void
    {
        $progress = $this->getOrCreateProgress();
        $progress->refresh();
        if ($this->alreadyCancelled($progress) && ($data['status'] ?? null) === 'running') {
            return;
        }

        $progress->mergeProgress($data);
    }

    private function alreadyCancelled(ImportProgress $progress): bool
    {
        return (bool) ($progress->metadata['cancel_requested'] ?? false)
            || $progress->status === 'cancelled';
    }

    public function failed(\Throwable $exception): void
    {
        $this->updateProgress([
            'status' => 'failed',
            'error_message' => $exception->getMessage(),
            'failed_at' => now()->toIso8601String(),
        ]);
    }
}
