<?php

namespace App\Jobs;

use App\Models\ImportProgress;
use App\Models\User;
use App\Services\DesertIslandDiscsImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Bus\UniqueLock;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ImportDesertIslandDiscsJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 0;

    public int $tries = 1;

    public int $uniqueFor = 86400;

    public function __construct(
        private readonly string $userId,
        private readonly int $batchSize = 25
    ) {
    }

    public function uniqueId(): string
    {
        return $this->userId;
    }

    public static function releaseUniquenessFor(string $userId): void
    {
        (new UniqueLock(Cache::driver()))->release(new self($userId));
    }

    public function handle(): void
    {
        \App\Models\Span::unsetEventDispatcher();
        \App\Models\Connection::unsetEventDispatcher();
        \App\Models\Connection::$skipCacheClearingDuringImport = true;

        try {
            $progress = $this->getOrCreateProgress();

            $user = User::find($this->userId);
            if (!$user) {
                $this->updateProgress(['status' => 'failed', 'error_message' => 'User not found']);

                return;
            }

            $this->updateProgress(['status' => 'running', 'started_at' => now()]);

            $service = new DesertIslandDiscsImportService();
            $episodes = $service->getParsedEpisodes();

            $totalEpisodes = count($episodes);
            $this->updateProgress(['total_items' => $totalEpisodes]);

            $offset = 0;
            $totalCreated = 0;
            $totalSkipped = 0;
            $totalErrors = 0;
            $recentPeople = [];

            set_time_limit(0);

            while ($offset < $totalEpisodes) {
                $progress->refresh();
                if ($progress->metadata['cancel_requested'] ?? false) {
                    $this->updateProgress([
                        'status' => 'cancelled',
                        'total_items' => $totalEpisodes,
                        'processed_items' => $offset,
                        'created_items' => $totalCreated,
                        'skipped_items' => $totalSkipped,
                        'error_count' => $totalErrors,
                        'progress_percentage' => $totalEpisodes > 0 ? min(100, round(($offset / $totalEpisodes) * 100, 1)) : 0,
                        'cancelled_at' => now()->toIso8601String(),
                    ]);

                    return;
                }

                $batchEpisodes = array_slice($episodes, $offset, $this->batchSize);

                $onProgress = function (array $data) use ($totalCreated, $totalSkipped, $totalErrors, &$recentPeople) {
                    if (!empty($data['last_person']) && is_array($data['last_person'])) {
                        $recentPeople[] = $data['last_person'];
                        $recentPeople = array_slice($recentPeople, -20);
                    }

                    $this->updateProgress([
                        'status' => 'running',
                        'total_items' => $data['total'],
                        'processed_items' => $data['processed'],
                        'created_items' => $totalCreated + $data['created'],
                        'skipped_items' => $totalSkipped + $data['skipped'],
                        'error_count' => $totalErrors + $data['errors_count'],
                        'progress_percentage' => $data['progress_percentage'],
                        'current_item' => $data['current_item'] ?? null,
                        'recent_people' => $recentPeople,
                        'last_activity' => now()->toIso8601String(),
                        'batch_progress' => $data['batch_progress'] ?? null,
                        'batch_size' => $data['batch_size'] ?? null,
                    ]);
                };

                $progressHeartbeat = function () {
                    $this->updateProgress(['last_activity' => now()->toIso8601String()]);
                };

                $shouldCancel = function () use ($progress) {
                    $progress->refresh();

                    return (bool) ($progress->metadata['cancel_requested'] ?? false);
                };

                $results = $service->processBatch(
                    $batchEpisodes,
                    $user,
                    $totalEpisodes,
                    $offset,
                    $onProgress,
                    $progressHeartbeat,
                    $shouldCancel
                );

                $totalCreated += $results['created'] ?? 0;
                $totalSkipped += $results['skipped'] ?? 0;
                $totalErrors += count($results['errors'] ?? []);

                if (!empty($results['cancelled'])) {
                    $this->updateProgress([
                        'status' => 'cancelled',
                        'total_items' => $totalEpisodes,
                        'processed_items' => $offset + ($results['processed'] ?? 0),
                        'created_items' => $totalCreated,
                        'skipped_items' => $totalSkipped,
                        'error_count' => $totalErrors,
                        'progress_percentage' => $totalEpisodes > 0
                            ? min(100, round((($offset + ($results['processed'] ?? 0)) / $totalEpisodes) * 100, 1))
                            : 0,
                        'cancelled_at' => now()->toIso8601String(),
                    ]);

                    return;
                }

                $offset += count($batchEpisodes);
                $percentage = $totalEpisodes > 0 ? min(100, round(($offset / $totalEpisodes) * 100, 1)) : 100;

                $this->updateProgress([
                    'status' => 'running',
                    'total_items' => $totalEpisodes,
                    'processed_items' => $offset,
                    'created_items' => $totalCreated,
                    'skipped_items' => $totalSkipped,
                    'error_count' => $totalErrors,
                    'progress_percentage' => $percentage,
                ]);
            }

            $this->updateProgress([
                'status' => 'completed',
                'total_items' => $totalEpisodes,
                'processed_items' => $totalEpisodes,
                'created_items' => $totalCreated,
                'skipped_items' => $totalSkipped,
                'error_count' => $totalErrors,
                'progress_percentage' => 100,
                'completed_at' => now(),
            ]);

            EnrichDesertIslandDiscsJob::dispatchIfNotRunning($this->userId);
        } catch (\Throwable $e) {
            Log::error('ImportDesertIslandDiscsJob failed', [
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
            \App\Models\Connection::$skipCacheClearingDuringImport = false;
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
                'import_type' => 'desert_island_discs',
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
        $progress->mergeProgress($data);
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
