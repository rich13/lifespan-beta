<?php

namespace App\Jobs;

use App\Models\Connection;
use App\Models\ImportProgress;
use App\Models\Span;
use App\Models\User;
use App\Services\WikipediaImportService;
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

class ImportWikipediaPublicFiguresJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const IMPORT_TYPE = 'wikipedia_public_figures';

    public int $timeout = 0;

    public int $tries = 1;

    public int $uniqueFor = 86400;

    private const DELAY_BETWEEN_ITEMS_MS = 2000;

    public function __construct(
        private readonly string $userId,
        private readonly bool $retrySkipped = false
    ) {}

    public function uniqueId(): string
    {
        return $this->userId;
    }

    public static function releaseUniquenessFor(string $userId): void
    {
        (new UniqueLock(Cache::driver()))->release(new self($userId));
    }

    public function handle(WikipediaImportService $service): void
    {
        $spanDispatcher = Span::getEventDispatcher();
        $connectionDispatcher = Connection::getEventDispatcher();
        Span::unsetEventDispatcher();
        Connection::unsetEventDispatcher();
        Connection::$skipCacheClearingDuringImport = true;

        try {
            $progress = $this->getOrCreateProgress();

            $user = User::find($this->userId);
            if (!$user) {
                $this->updateProgress(['status' => 'failed', 'error_message' => 'User not found']);

                return;
            }

            $spanIds = $this->getPublicFiguresNeedingImport();
            $total = count($spanIds);

            set_time_limit(0);

            $this->updateProgress([
                'status' => 'running',
                'started_at' => now(),
                'total_items' => $total,
                'processed_items' => 0,
                'created_items' => 0,
                'skipped_items' => 0,
                'error_count' => 0,
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

            $processed = 0;
            $created = 0;
            $skipped = 0;
            $errors = 0;

            foreach ($spanIds as $index => $spanId) {
                $progress->refresh();
                if ($progress->metadata['cancel_requested'] ?? false) {
                    $this->updateProgress([
                        'status' => 'cancelled',
                        'total_items' => $total,
                        'processed_items' => $processed,
                        'created_items' => $created,
                        'skipped_items' => $skipped,
                        'error_count' => $errors,
                        'progress_percentage' => $total > 0 ? min(100, round(($processed / $total) * 100, 1)) : 0,
                        'cancelled_at' => now()->toIso8601String(),
                    ]);

                    return;
                }

                $span = Span::find($spanId);
                if (!$span) {
                    $errors++;
                    $processed++;
                    $this->updateProgress([
                        'status' => 'running',
                        'total_items' => $total,
                        'processed_items' => $processed,
                        'created_items' => $created,
                        'skipped_items' => $skipped,
                        'error_count' => $errors,
                        'progress_percentage' => min(100, round(($processed / $total) * 100, 1)),
                        'current_item' => $spanId,
                        'last_activity' => now()->toIso8601String(),
                    ]);
                    continue;
                }

                $this->updateProgress([
                    'current_item' => $span->name,
                    'last_activity' => now()->toIso8601String(),
                ]);

                $result = $service->processSpan($span);

                if ($result['success']) {
                    $created++;
                } elseif (str_contains($result['message'] ?? '', 'No suitable description')) {
                    $service->skipSpan($span);
                    $skipped++;
                } else {
                    $errors++;
                    Log::warning('Wikipedia import failed for span', [
                        'span_id' => $span->id,
                        'span_name' => $span->name,
                        'message' => $result['message'],
                    ]);
                }

                $processed++;
                $this->updateProgress([
                    'status' => 'running',
                    'total_items' => $total,
                    'processed_items' => $processed,
                    'created_items' => $created,
                    'skipped_items' => $skipped,
                    'error_count' => $errors,
                    'progress_percentage' => min(100, round(($processed / $total) * 100, 1)),
                    'current_item' => $span->name,
                    'last_activity' => now()->toIso8601String(),
                ]);

                if ($index < count($spanIds) - 1 && ! app()->environment('testing')) {
                    usleep(self::DELAY_BETWEEN_ITEMS_MS * 1000);
                }
            }

            $this->updateProgress([
                'status' => 'completed',
                'total_items' => $total,
                'processed_items' => $processed,
                'created_items' => $created,
                'skipped_items' => $skipped,
                'error_count' => $errors,
                'progress_percentage' => 100,
                'completed_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('ImportWikipediaPublicFiguresJob failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->updateProgress([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'failed_at' => now()->toIso8601String(),
            ]);

            throw $e;
        } finally {
            Connection::$skipCacheClearingDuringImport = false;
            Span::setEventDispatcher($spanDispatcher);
            Connection::setEventDispatcher($connectionDispatcher);
        }
    }

    private function getPublicFiguresNeedingImport(): array
    {
        $excludeSkipped = !$this->retrySkipped;

        return Span::where('type_id', 'person')
            ->whereJsonContains('metadata->subtype', 'public_figure')
            ->where(function ($query) use ($excludeSkipped) {
                $query->whereNull('description')
                    ->orWhere(function ($subQuery) use ($excludeSkipped) {
                        $subQuery->whereRaw("sources IS NULL OR sources::text NOT ILIKE '%wikipedia.org%'");
                        if ($excludeSkipped) {
                            $subQuery->whereRaw("notes NOT LIKE '%[Skipped Wikipedia import%'");
                        }
                    })
                    ->orWhere(function ($subQuery) use ($excludeSkipped) {
                        $subQuery->whereNull('start_year');
                        if ($excludeSkipped) {
                            $subQuery->whereRaw("notes NOT LIKE '%[Skipped Wikipedia import%'");
                        }
                    })
                    ->orWhere(function ($subQuery) use ($excludeSkipped) {
                        $subQuery->where('start_month', 1)->where('start_day', 1);
                        if ($excludeSkipped) {
                            $subQuery->whereRaw("notes NOT LIKE '%[Skipped Wikipedia import%'");
                        }
                    })
                    ->orWhere(function ($subQuery) use ($excludeSkipped) {
                        $subQuery->whereNotNull('end_year')->where('end_month', 1)->where('end_day', 1);
                        if ($excludeSkipped) {
                            $subQuery->whereRaw("notes NOT LIKE '%[Skipped Wikipedia import%'");
                        }
                    });
            })
            ->where(function ($query) use ($excludeSkipped) {
                $query->whereNull('notes')
                    ->orWhere(function ($subQuery) use ($excludeSkipped) {
                        if ($excludeSkipped) {
                            $subQuery->whereRaw("notes NOT LIKE '%[Skipped Wikipedia import%'");
                        }
                        $subQuery->whereRaw("notes NOT LIKE '%[Wikipedia import complete%'");
                    });
            })
            ->orderBy('name')
            ->pluck('id')
            ->all();
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
        $this->getOrCreateProgress()->mergeProgress($data);
    }

    public function failed(\Throwable $exception): void
    {
        $message = $exception instanceof MaxAttemptsExceededException
            ? 'The import was interrupted. Start it again to continue.'
            : $exception->getMessage();

        $this->updateProgress([
            'status' => 'failed',
            'error_message' => $message,
            'failed_at' => now()->toIso8601String(),
        ]);
    }
}
