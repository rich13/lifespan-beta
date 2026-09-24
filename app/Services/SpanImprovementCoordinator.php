<?php

namespace App\Services;

use App\Models\Connection;
use App\Models\ImprovementAttempt;
use App\Models\Span;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Chooses the next spans that can be improved, and runs one improver at a time.
 * Wikipedia, MusicBrainz, and geocoding are the free steps. The AI improver stays
 * closed while the daily token budget is zero.
 */
class SpanImprovementCoordinator
{
    public const IMPROVER_WIKIPEDIA = 'wikipedia';

    public const IMPROVER_MUSICBRAINZ = 'musicbrainz';

    public const IMPROVER_GEOCODE = 'geocode';

    public function __construct(
        private readonly WikipediaImportService $wikipedia,
        private readonly WikipediaBookService $books,
        private readonly MusicBrainzImportService $musicBrainz,
        private readonly PlaceGeocodingWorkflowService $geocoding,
    ) {}

    /**
     * @return list<array{span_id: string, improver: string, kind: string}>
     */
    public function pendingWork(): array
    {
        return $this->interleave([
            $this->workItems($this->wikipediaPeopleQuery(), self::IMPROVER_WIKIPEDIA, 'person'),
            $this->workItems($this->wikipediaBooksQuery(), self::IMPROVER_WIKIPEDIA, 'book'),
            $this->workItems($this->musicBrainzArtistsQuery(), self::IMPROVER_MUSICBRAINZ, 'artist'),
            $this->workItems($this->musicBrainzTracksQuery(), self::IMPROVER_MUSICBRAINZ, 'track'),
            $this->workItems($this->placesQuery(), self::IMPROVER_GEOCODE, 'place'),
        ]);
    }

    /**
     * @return array{wikipedia: int, musicbrainz: int, geocode: int}
     */
    public function queueCounts(): array
    {
        return [
            'wikipedia' => $this->wikipediaPeopleQuery()->count() + $this->wikipediaBooksQuery()->count(),
            'musicbrainz' => $this->musicBrainzArtistsQuery()->count() + $this->musicBrainzTracksQuery()->count(),
            'geocode' => $this->placesQuery()->count(),
        ];
    }

    /**
     * @return array{limit: int, used: int, remaining: int, enabled: bool}
     */
    public function aiBudget(): array
    {
        $limit = max(0, (int) config('services.improvement.ai_daily_token_budget', 0));

        return [
            'limit' => $limit,
            'used' => 0,
            'remaining' => $limit,
            'enabled' => $limit > 0,
        ];
    }

    /**
     * @param  array{span_id: string, improver: string, kind: string}  $item
     * @return array{outcome: string, detail: string, name: string}
     */
    public function improve(array $item): array
    {
        $span = Span::find($item['span_id']);
        if (! $span) {
            return $this->record($item, 'error', 'Span no longer exists', $item['span_id']);
        }

        $result = match ($item['improver']) {
            self::IMPROVER_WIKIPEDIA => $item['kind'] === 'book'
                ? $this->improveBook($span)
                : $this->improvePerson($span),
            self::IMPROVER_MUSICBRAINZ => $item['kind'] === 'track'
                ? $this->improveTrack($span)
                : $this->improveArtist($span),
            self::IMPROVER_GEOCODE => $this->improvePlace($span),
            default => ['outcome' => 'error', 'detail' => 'Unknown improver'],
        };

        return $this->record($item, $result['outcome'], $result['detail'], $span->name);
    }

    /**
     * @return array{outcome: string, detail: string}
     */
    private function improvePerson(Span $span): array
    {
        $result = $this->wikipedia->processSpan($span);
        if ($result['success'] ?? false) {
            $detail = ($result['data']['type_corrected'] ?? false)
                ? 'Corrected type and imported Wikipedia'
                : 'Imported Wikipedia';

            return ['outcome' => 'improved', 'detail' => $detail];
        }

        if (str_contains($result['message'] ?? '', 'No suitable description')) {
            $this->wikipedia->skipSpan($span);

            return ['outcome' => 'skipped', 'detail' => 'No suitable Wikipedia page'];
        }

        return ['outcome' => 'error', 'detail' => $result['message'] ?? 'Wikipedia import failed'];
    }

    /**
     * @return array{outcome: string, detail: string}
     */
    private function improveBook(Span $span): array
    {
        if ($this->books->updateBookSpanWithWikipediaInfo($span)) {
            return ['outcome' => 'improved', 'detail' => 'Imported book from Wikipedia'];
        }

        return ['outcome' => 'skipped', 'detail' => 'No Wikipedia book match'];
    }

    /**
     * @return array{outcome: string, detail: string}
     */
    private function improveArtist(Span $span): array
    {
        $result = $this->musicBrainz->enrichExistingArtist($span);
        if (($result['success'] ?? false) && empty($result['skipped'])) {
            return ['outcome' => 'improved', 'detail' => 'Matched MusicBrainz artist'];
        }

        if ($result['skipped'] ?? false) {
            return ['outcome' => 'skipped', 'detail' => $result['message'] ?? 'No unambiguous MusicBrainz artist'];
        }

        return ['outcome' => 'error', 'detail' => $result['message'] ?? 'MusicBrainz artist match failed'];
    }

    /**
     * @return array{outcome: string, detail: string}
     */
    private function improveTrack(Span $span): array
    {
        $artistName = $this->artistNameForTrack($span);
        $result = $this->musicBrainz->enrichExistingTrack($span, $artistName);
        if (($result['success'] ?? false) && empty($result['skipped'])) {
            return ['outcome' => 'improved', 'detail' => 'Matched MusicBrainz recording'];
        }

        if ($result['skipped'] ?? false) {
            return ['outcome' => 'skipped', 'detail' => $result['message'] ?? 'No MusicBrainz recording'];
        }

        return ['outcome' => 'skipped', 'detail' => $result['message'] ?? 'No unambiguous MusicBrainz recording'];
    }

    /**
     * @return array{outcome: string, detail: string}
     */
    private function improvePlace(Span $span): array
    {
        $batch = $this->geocoding->batchProcess([$span->id]);
        if ($batch['geocoded'] > 0) {
            return ['outcome' => 'improved', 'detail' => 'Geocoded from an unambiguous match'];
        }
        if ($batch['errors'] > 0) {
            $detail = $batch['error_details'][0]['error'] ?? 'Geocoding failed';

            return ['outcome' => 'error', 'detail' => $detail];
        }
        if ($batch['needs_disambiguation'] > 0) {
            return ['outcome' => 'skipped', 'detail' => 'More than one possible place'];
        }

        return ['outcome' => 'skipped', 'detail' => 'No geocoding match'];
    }

    private function artistNameForTrack(Span $span): ?string
    {
        $connection = Connection::where('child_id', $span->id)
            ->where('type_id', 'created')
            ->first();

        return $connection?->parent?->name;
    }

    /**
     * @param  array{span_id: string, improver: string, kind: string}  $item
     * @return array{outcome: string, detail: string, name: string}
     */
    private function record(array $item, string $outcome, string $detail, string $name): array
    {
        ImprovementAttempt::create([
            'span_id' => $item['span_id'],
            'improver' => $item['improver'],
            'outcome' => $outcome,
            'detail' => $detail,
            'created_at' => now(),
        ]);

        return [
            'outcome' => $outcome,
            'detail' => $detail,
            'name' => $name,
        ];
    }

    private function wikipediaPeopleQuery(): Builder
    {
        return Span::query()
            ->where('type_id', 'person')
            ->where('metadata->subtype', 'public_figure')
            ->where(function (Builder $query) {
                $query->whereNull('description')
                    ->orWhereRaw("sources IS NULL OR sources::text NOT ILIKE '%wikipedia.org%'")
                    ->orWhereNull('start_year')
                    ->orWhereRaw("(metadata->>'gender') IS NULL")
                    ->orWhere(function (Builder $dates) {
                        $dates->where('start_month', 1)->where('start_day', 1);
                    });
            })
            ->where(function (Builder $query) {
                $query->whereNull('notes')
                    ->orWhereRaw("notes NOT LIKE '%[Skipped Wikipedia import%'");
            })
            ->tap(fn (Builder $query) => $this->excludeRecentAttempts($query, self::IMPROVER_WIKIPEDIA));
    }

    private function wikipediaBooksQuery(): Builder
    {
        return Span::query()
            ->where('type_id', 'thing')
            ->where('metadata->subtype', 'book')
            ->where(function (Builder $query) {
                $query->whereNull('sources')
                    ->orWhereRaw("sources::text NOT ILIKE '%wikipedia.org%'");
            })
            ->tap(fn (Builder $query) => $this->excludeRecentAttempts($query, self::IMPROVER_WIKIPEDIA));
    }

    private function musicBrainzArtistsQuery(): Builder
    {
        return Span::query()
            ->where(function (Builder $query) {
                $query->where('type_id', 'band')
                    ->orWhere(function (Builder $people) {
                        $people->where('type_id', 'person')
                            ->where('metadata->did_role', 'artist');
                    });
            })
            ->whereRaw("(metadata->'musicbrainz'->>'id') IS NULL")
            ->tap(fn (Builder $query) => $this->excludeRecentAttempts($query, self::IMPROVER_MUSICBRAINZ));
    }

    private function musicBrainzTracksQuery(): Builder
    {
        return Span::query()
            ->where('type_id', 'thing')
            ->where('metadata->subtype', 'track')
            ->whereRaw("(metadata->'musicbrainz'->>'id') IS NULL")
            ->tap(fn (Builder $query) => $this->excludeRecentAttempts($query, self::IMPROVER_MUSICBRAINZ));
    }

    private function placesQuery(): Builder
    {
        return Span::query()
            ->where('type_id', 'place')
            ->where(function (Builder $query) {
                $query->whereRaw("metadata->>'coordinates' IS NULL")
                    ->orWhereRaw("metadata->>'osm_data' IS NULL");
            })
            ->tap(fn (Builder $query) => $this->excludeRecentAttempts($query, self::IMPROVER_GEOCODE));
    }

    private function excludeRecentAttempts(Builder $query, string $improver): void
    {
        $query->where('improvement_generation', '<=', ImprovementCreationPolicy::MAX_GENERATION)
            ->whereNotExists(function ($attempt) use ($improver) {
                $attempt->select(DB::raw('1'))
                    ->from('improvement_attempts')
                    ->whereColumn('improvement_attempts.span_id', 'spans.id')
                    ->where('improver', $improver)
                    ->where(function ($recent) {
                        $recent->where(function ($errors) {
                            $errors->where('outcome', 'error')
                                ->where('improvement_attempts.created_at', '>=', now()->subDay());
                        })->orWhere(function ($settled) {
                            $settled->whereIn('outcome', ['improved', 'skipped'])
                                ->where(function ($window) {
                                    $window->whereRaw('spans.improvement_generation >= 1')
                                        ->orWhere('improvement_attempts.created_at', '>=', now()->subDays(30));
                                });
                        });
                    });
            });
    }

    /**
     * @return list<array{span_id: string, improver: string, kind: string}>
     */
    private function workItems(Builder $query, string $improver, string $kind): array
    {
        return $query
            ->orderByDesc('updated_at')
            ->pluck('id')
            ->map(fn ($id) => [
                'span_id' => (string) $id,
                'improver' => $improver,
                'kind' => $kind,
            ])
            ->all();
    }

    /**
     * @param  list<list<array{span_id: string, improver: string, kind: string}>>  $lists
     * @return list<array{span_id: string, improver: string, kind: string}>
     */
    private function interleave(array $lists): array
    {
        $longest = 0;
        foreach ($lists as $list) {
            $longest = max($longest, count($list));
        }

        $work = [];
        for ($index = 0; $index < $longest; $index++) {
            foreach ($lists as $list) {
                if (isset($list[$index])) {
                    $work[] = $list[$index];
                }
            }
        }

        return $work;
    }
}
