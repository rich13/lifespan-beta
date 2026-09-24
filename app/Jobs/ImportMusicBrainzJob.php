<?php

namespace App\Jobs;

use App\Models\Connection;
use App\Models\ImportProgress;
use App\Models\Span;
use App\Models\User;
use App\Services\MusicBrainzImportService;
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

class ImportMusicBrainzJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const IMPORT_TYPE = 'musicbrainz';

    public int $timeout = 0;

    public int $tries = 1;

    public int $uniqueFor = 86400;

    public function __construct(
        private readonly string $userId,
        private readonly ?string $spanId = null
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

    public function handle(MusicBrainzImportService $service): void
    {
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

            $artists = $this->spanId
                ? collect([Span::find($this->spanId)])->filter()
                : $service->catalogueArtists();

            $total = $artists->count();
            $created = 0;
            $skipped = 0;
            $errors = 0;
            $recentArtists = [];

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
                'recent_artists' => [],
            ]);

            if ($total === 0) {
                $this->updateProgress([
                    'status' => 'completed',
                    'progress_percentage' => 100,
                    'completed_at' => now(),
                ]);

                return;
            }

            foreach ($artists->values() as $index => $artist) {
                $progress->refresh();
                if ($progress->metadata['cancel_requested'] ?? false) {
                    $this->updateProgress([
                        'status' => 'cancelled',
                        'total_items' => $total,
                        'processed_items' => $index,
                        'created_items' => $created,
                        'skipped_items' => $skipped,
                        'error_count' => $errors,
                        'progress_percentage' => $total > 0 ? min(100, round(($index / $total) * 100, 1)) : 0,
                        'cancelled_at' => now()->toIso8601String(),
                        'recent_artists' => $recentArtists,
                    ]);

                    return;
                }

                $this->updateProgress([
                    'current_item' => $artist->name,
                    'last_activity' => now()->toIso8601String(),
                    'recent_artists' => array_slice(array_merge($recentArtists, [[
                        'name' => $artist->name,
                        'status' => 'working',
                        'message' => 'Looking up MusicBrainz…',
                    ]]), -20),
                ]);

                try {
                    $result = $service->importForSpan($artist, $user);
                    $status = $result['status'] ?? ($result['skipped'] ? 'skipped' : 'imported');
                    if (!empty($result['skipped'])) {
                        $skipped++;
                    } elseif (!empty($result['success'])) {
                        $created++;
                    } else {
                        $skipped++;
                        $status = $result['status'] ?? 'skipped';
                    }

                    $recentArtists[] = [
                        'name' => $artist->name,
                        'status' => $status,
                        'message' => $result['message'] ?? '',
                    ];
                } catch (\Throwable $e) {
                    $errors++;
                    $recentArtists[] = [
                        'name' => $artist->name,
                        'status' => 'error',
                        'message' => $e->getMessage(),
                    ];
                    Log::warning('MusicBrainz import failed for artist', [
                        'span_id' => $artist->id,
                        'span_name' => $artist->name,
                        'error' => $e->getMessage(),
                    ]);
                }

                $recentArtists = array_slice($recentArtists, -20);
                $processed = $index + 1;
                $this->updateProgress([
                    'status' => 'running',
                    'total_items' => $total,
                    'processed_items' => $processed,
                    'created_items' => $created,
                    'skipped_items' => $skipped,
                    'error_count' => $errors,
                    'progress_percentage' => $total > 0 ? min(100, round(($processed / $total) * 100, 1)) : 100,
                    'recent_artists' => $recentArtists,
                    'current_item' => $artist->name,
                    'last_activity' => now()->toIso8601String(),
                ]);
            }

            $this->updateProgress([
                'status' => 'completed',
                'total_items' => $total,
                'processed_items' => $total,
                'created_items' => $created,
                'skipped_items' => $skipped,
                'error_count' => $errors,
                'progress_percentage' => 100,
                'completed_at' => now(),
                'recent_artists' => $recentArtists,
            ]);
        } catch (\Throwable $e) {
            Log::error('ImportMusicBrainzJob failed', [
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
