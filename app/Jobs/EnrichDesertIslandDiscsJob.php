<?php

namespace App\Jobs;

use App\Models\Connection;
use App\Models\ImportProgress;
use App\Models\Span;
use App\Services\DesertIslandDiscsImportService;
use App\Services\MusicBrainzImportService;
use App\Services\WikipediaBookService;
use App\Services\WikipediaImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class EnrichDesertIslandDiscsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const IMPORT_TYPE = 'desert_island_discs_enrich';

    public int $timeout = 0;

    public int $tries = 1;

    public function __construct(
        private readonly string $userId
    ) {
    }

    public static function dispatchIfNotRunning(string $userId): void
    {
        $existing = ImportProgress::forDesertIslandDiscsEnrich($userId);
        if ($existing && $existing->status === 'running') {
            return;
        }

        self::dispatch($userId);
    }

    public function handle(
        WikipediaImportService $wikipediaPeople,
        WikipediaBookService $wikipediaBooks,
        MusicBrainzImportService $musicBrainz
    ): void {
        Span::unsetEventDispatcher();
        Connection::unsetEventDispatcher();
        Connection::$skipCacheClearingDuringImport = true;

        try {
            $progress = $this->getOrCreateProgress();
            $this->updateProgress(['status' => 'running', 'started_at' => now()]);

            $people = $this->peopleNeedingWikipedia();
            $books = $this->booksNeedingWikipedia();
            $artists = $this->artistsNeedingMusicBrainz();
            $tracks = $this->tracksNeedingMusicBrainz();

            $items = [];
            foreach ($people as $span) {
                $items[] = ['kind' => 'person', 'span' => $span];
            }
            foreach ($books as $span) {
                $items[] = ['kind' => 'book', 'span' => $span];
            }
            foreach ($artists as $span) {
                $items[] = ['kind' => 'artist', 'span' => $span];
            }
            foreach ($tracks as $span) {
                $items[] = ['kind' => 'track', 'span' => $span];
            }

            $total = count($items);
            $this->updateProgress(['total_items' => $total]);

            $processed = 0;
            $created = 0;
            $skipped = 0;
            $errors = 0;

            set_time_limit(0);

            foreach ($items as $item) {
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

                /** @var Span $span */
                $span = $item['span'];
                $kind = $item['kind'];

                $this->updateProgress([
                    'current_item' => "{$kind}: {$span->name}",
                    'last_activity' => now()->toIso8601String(),
                ]);

                try {
                    $result = match ($kind) {
                        'person' => $wikipediaPeople->processSpan($span),
                        'book' => [
                            'success' => $wikipediaBooks->updateBookSpanWithWikipediaInfo($span),
                            'skipped' => false,
                        ],
                        'artist' => $musicBrainz->enrichExistingArtist($span),
                        'track' => $musicBrainz->enrichExistingTrack($span, $this->artistNameForTrack($span)),
                        default => ['success' => false, 'message' => 'Unknown enrichment kind'],
                    };

                    if (!empty($result['skipped'])) {
                        $skipped++;
                    } elseif (!empty($result['success'])) {
                        $created++;
                    } else {
                        $skipped++;
                    }
                } catch (\Throwable $e) {
                    $errors++;
                    Log::warning('Desert Island Discs enrichment failed', [
                        'span_id' => $span->id,
                        'span_name' => $span->name,
                        'kind' => $kind,
                        'error' => $e->getMessage(),
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
                    'progress_percentage' => $total > 0 ? min(100, round(($processed / $total) * 100, 1)) : 100,
                ]);
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
            Log::error('EnrichDesertIslandDiscsJob failed', [
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

    /**
     * @return \Illuminate\Support\Collection<int,Span>
     */
    private function peopleNeedingWikipedia()
    {
        return Span::query()
            ->where('type_id', 'person')
            ->whereRaw("metadata->>'data_source' = ?", [DesertIslandDiscsImportService::DATA_SOURCE])
            ->whereRaw("metadata->>'subtype' = ?", ['public_figure'])
            ->get()
            ->filter(fn (Span $span) => !$this->hasWikipedia($span))
            ->values();
    }

    /**
     * @return \Illuminate\Support\Collection<int,Span>
     */
    private function booksNeedingWikipedia()
    {
        return Span::query()
            ->where('type_id', 'thing')
            ->whereRaw("metadata->>'data_source' = ?", [DesertIslandDiscsImportService::DATA_SOURCE])
            ->whereRaw("metadata->>'subtype' = ?", ['book'])
            ->get()
            ->filter(fn (Span $span) => !$this->hasWikipedia($span))
            ->values();
    }

    /**
     * @return \Illuminate\Support\Collection<int,Span>
     */
    private function artistsNeedingMusicBrainz()
    {
        return Span::query()
            ->whereIn('type_id', ['person', 'band'])
            ->whereRaw("metadata->>'data_source' = ?", [DesertIslandDiscsImportService::DATA_SOURCE])
            ->whereRaw("metadata->>'did_role' = ?", ['artist'])
            ->get()
            ->filter(fn (Span $span) => !$this->hasMusicBrainz($span))
            ->values();
    }

    /**
     * @return \Illuminate\Support\Collection<int,Span>
     */
    private function tracksNeedingMusicBrainz()
    {
        return Span::query()
            ->where('type_id', 'thing')
            ->whereRaw("metadata->>'data_source' = ?", [DesertIslandDiscsImportService::DATA_SOURCE])
            ->whereRaw("metadata->>'subtype' = ?", ['track'])
            ->get()
            ->filter(fn (Span $span) => !$this->hasMusicBrainz($span))
            ->values();
    }

    private function hasWikipedia(Span $span): bool
    {
        if (!empty($span->metadata['wikipedia'])) {
            return true;
        }

        foreach ($span->sources ?? [] as $source) {
            $url = is_array($source) ? (string) ($source['url'] ?? '') : (string) $source;
            if (str_contains($url, 'wikipedia.org')) {
                return true;
            }
        }

        return false;
    }

    private function hasMusicBrainz(Span $span): bool
    {
        return !empty($span->metadata['musicbrainz']['id']);
    }

    private function artistNameForTrack(Span $span): ?string
    {
        $connection = Connection::where('child_id', $span->id)
            ->where('type_id', 'created')
            ->first();

        if (!$connection) {
            return null;
        }

        return $connection->parent?->name;
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
