<?php

namespace App\Jobs;

use App\Models\Connection;
use App\Models\ImportProgress;
use App\Models\User;
use App\Services\PlaqueResidenceConnectionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CreatePlaqueResidenceConnectionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const IMPORT_TYPE = 'plaque_residence_connections';

    public int $timeout = 0;

    public int $tries = 1;

    public function __construct(
        private readonly string $userId,
        private readonly int $batchSize = 25
    ) {
    }

    public function handle(PlaqueResidenceConnectionService $service): void
    {
        Connection::$skipCacheClearingDuringImport = true;

        try {
            $progress = $this->getOrCreateProgress();

            $user = User::find($this->userId);
            if (! $user) {
                $this->updateProgress(['status' => 'failed', 'error_message' => 'User not found']);

                return;
            }

            $this->updateProgress(['status' => 'running', 'started_at' => now()]);

            $offset = 0;
            $totalCreated = 0;
            $totalSkipped = 0;
            $totalIneligible = 0;
            $totalErrors = 0;
            $createdKeys = [];

            set_time_limit(0);

            do {
                $progress->refresh();
                if ($progress->metadata['cancel_requested'] ?? false) {
                    $this->updateProgress([
                        'status' => 'cancelled',
                        'processed_items' => $offset,
                        'created_items' => $totalCreated,
                        'skipped_items' => $totalSkipped,
                        'error_count' => $totalErrors,
                        'created_keys' => $createdKeys,
                        'cancelled_at' => now()->toIso8601String(),
                    ]);

                    return;
                }

                $batch = $service->processCreatableScanBatch($this->batchSize, $offset, $user);

                if ($batch['plaques_scanned'] === 0) {
                    break;
                }

                $offset += $batch['plaques_scanned'];
                $totalCreated += $batch['created'];
                $totalSkipped += $batch['skipped'];
                $totalIneligible += $batch['ineligible'];
                $totalErrors += $batch['errors'];
                $createdKeys = array_values(array_unique(array_merge($createdKeys, $batch['created_keys'])));

                $totalPlaques = $batch['total_plaques'];
                $percentage = $totalPlaques > 0
                    ? min(100, round(($offset / $totalPlaques) * 100, 1))
                    : 100;

                $this->updateProgress([
                    'status' => 'running',
                    'total_items' => $totalPlaques,
                    'processed_items' => $offset,
                    'created_items' => $totalCreated,
                    'skipped_items' => $totalSkipped + $totalIneligible,
                    'error_count' => $totalErrors,
                    'progress_percentage' => $percentage,
                    'current_item' => $batch['current_item'],
                    'created_keys' => $createdKeys,
                    'last_activity' => now()->toIso8601String(),
                    'batch_size' => $this->batchSize,
                ]);

                $hasMore = $batch['has_more'];
            } while ($hasMore);

            $this->updateProgress([
                'status' => 'completed',
                'processed_items' => $offset,
                'created_items' => $totalCreated,
                'skipped_items' => $totalSkipped + $totalIneligible,
                'error_count' => $totalErrors,
                'progress_percentage' => 100,
                'created_keys' => $createdKeys,
                'completed_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('CreatePlaqueResidenceConnectionsJob failed', [
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
