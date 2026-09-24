<?php

namespace App\Jobs;

use App\Models\Connection;
use App\Models\ImportProgress;
use App\Models\Span;
use App\Models\User;
use App\Services\PrivateIndividualConnectionService;
use App\Services\PublicSpanCache;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class FixPrivateIndividualConnectionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const IMPORT_TYPE = 'private_individual_connections';

    public int $timeout = 0;

    public int $tries = 1;

    /**
     * @param  array<int, string>|null  $individualIds
     */
    public function __construct(
        private readonly string $userId,
        private readonly int $batchSize = 25,
        private readonly ?array $individualIds = null
    ) {
    }

    public function handle(
        PrivateIndividualConnectionService $service,
        PublicSpanCache $publicSpanCache
    ): void {
        $spanDispatcher = Span::getEventDispatcher();
        $connectionDispatcher = Connection::getEventDispatcher();

        Span::unsetEventDispatcher();
        Connection::unsetEventDispatcher();
        Connection::$skipCacheClearingDuringImport = true;

        try {
            $progress = $this->getOrCreateProgress();

            $user = User::find($this->userId);
            if (! $user) {
                $this->updateProgress(['status' => 'failed', 'error_message' => 'User not found']);

                return;
            }

            $this->updateProgress(['status' => 'running', 'started_at' => now()]);

            $ids = $this->individualIds ?? $service->idsNeedingFix();
            $total = count($ids);
            $offset = 0;
            $totalFixedConnections = 0;
            $totalFixedIndividuals = 0;
            $totalSkipped = 0;
            $totalErrors = 0;
            $fixedIds = [];

            set_time_limit(0);

            $this->updateProgress([
                'status' => 'running',
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

            while ($offset < $total) {
                $progress->refresh();
                if ($progress->metadata['cancel_requested'] ?? false) {
                    $this->updateProgress([
                        'status' => 'cancelled',
                        'processed_items' => $offset,
                        'created_items' => $totalFixedConnections,
                        'skipped_items' => $totalSkipped,
                        'error_count' => $totalErrors,
                        'progress_percentage' => min(100, round(($offset / $total) * 100, 1)),
                        'fixed_ids' => $fixedIds,
                        'cancelled_at' => now()->toIso8601String(),
                    ]);

                    return;
                }

                $batchIds = array_slice($ids, $offset, $this->batchSize);
                $batch = $service->fixIds($batchIds, $publicSpanCache);

                $offset += count($batchIds);
                $totalFixedConnections += $batch['fixed_connections'];
                $totalFixedIndividuals += $batch['fixed_individuals'];
                $totalSkipped += $batch['skipped'];
                $totalErrors += count($batch['errors']);
                $fixedIds = array_values(array_unique(array_merge($fixedIds, $batch['fixed_ids'])));

                $percentage = min(100, round(($offset / $total) * 100, 1));

                $this->updateProgress([
                    'status' => 'running',
                    'total_items' => $total,
                    'processed_items' => $offset,
                    'created_items' => $totalFixedConnections,
                    'skipped_items' => $totalSkipped,
                    'error_count' => $totalErrors,
                    'progress_percentage' => $percentage,
                    'current_item' => $batch['current_item'],
                    'fixed_ids' => $fixedIds,
                    'fixed_individuals' => $totalFixedIndividuals,
                    'last_activity' => now()->toIso8601String(),
                    'batch_size' => $this->batchSize,
                ]);
            }

            $this->updateProgress([
                'status' => 'completed',
                'processed_items' => $offset,
                'created_items' => $totalFixedConnections,
                'skipped_items' => $totalSkipped,
                'error_count' => $totalErrors,
                'progress_percentage' => 100,
                'fixed_ids' => $fixedIds,
                'fixed_individuals' => $totalFixedIndividuals,
                'completed_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('FixPrivateIndividualConnectionsJob failed', [
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
            if ($spanDispatcher) {
                Span::setEventDispatcher($spanDispatcher);
            }
            if ($connectionDispatcher) {
                Connection::setEventDispatcher($connectionDispatcher);
            }
        }
    }

    private ?ImportProgress $progressRow = null;

    private function getOrCreateProgress(): ImportProgress
    {
        if ($this->progressRow) {
            return $this->progressRow;
        }

        $this->progressRow = ImportProgress::updateOrCreate(
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
        $this->updateProgress([
            'status' => 'failed',
            'error_message' => $exception->getMessage(),
            'failed_at' => now()->toIso8601String(),
        ]);
    }
}
