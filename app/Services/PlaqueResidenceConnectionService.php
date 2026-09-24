<?php

namespace App\Services;

use App\Models\Connection;
use App\Models\Span;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PlaqueResidenceConnectionService
{
    public const RESIDENCE_TYPE = 'residence';

    public const RESIDENCE_PREDICATE = 'lived-in';

    /**
     * Plaques that already have a featured person and a location.
     */
    public function plaquesQuery(): Builder
    {
        return Span::query()
            ->where('type_id', 'thing')
            ->whereJsonContains('metadata->subtype', 'plaque');
    }

    public function countPlaques(): int
    {
        return $this->plaquesQuery()->count();
    }

    /**
     * Scan a page of plaques and return person/place rows.
     *
     * @return array{
     *     rows: array<int, array<string, mixed>>,
     *     plaques_scanned: int,
     *     plaques_with_person_and_place: int,
     *     offset: int,
     *     limit: int,
     *     total_plaques: int,
     *     has_more: bool
     * }
     */
    public function scanBatch(int $limit = 25, int $offset = 0): array
    {
        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);

        $totalPlaques = $this->countPlaques();

        $plaques = $this->plaquesQuery()
            ->with([
                'connectionsAsSubject.child' => function ($query) {
                    $query->select('id', 'name', 'slug', 'type_id', 'short_id');
                },
                'connectionsAsSubject.connectionSpan',
            ])
            ->orderBy('name')
            ->orderBy('id')
            ->offset($offset)
            ->limit($limit)
            ->get();

        $personPlacePairs = [];
        $personIds = [];
        $placeIds = [];

        foreach ($plaques as $plaque) {
            $people = $this->featuredPeople($plaque);
            $places = $this->locatedPlaces($plaque);

            foreach ($people as $person) {
                foreach ($places as $place) {
                    $personIds[$person->id] = true;
                    $placeIds[$place->id] = true;
                    $personPlacePairs[] = [
                        'plaque' => $plaque,
                        'person' => $person,
                        'place' => $place,
                    ];
                }
            }
        }

        $residences = $this->residencesByPersonAndPlace(
            array_keys($personIds),
            array_keys($placeIds)
        );

        $rows = [];
        $plaquesWithPersonAndPlace = [];

        foreach ($personPlacePairs as $pair) {
            $plaquesWithPersonAndPlace[$pair['plaque']->id] = true;
            $rows[] = $this->buildRow(
                $pair['plaque'],
                $pair['person'],
                $pair['place'],
                $residences[$pair['person']->id.'|'.$pair['place']->id] ?? null
            );
        }

        $scanned = $plaques->count();

        return [
            'rows' => $rows,
            'plaques_scanned' => $scanned,
            'plaques_with_person_and_place' => count($plaquesWithPersonAndPlace),
            'offset' => $offset,
            'limit' => $limit,
            'total_plaques' => $totalPlaques,
            'has_more' => ($offset + $scanned) < $totalPlaques,
        ];
    }

    /**
     * Create a residence connection if the plaque is eligible.
     *
     * @return array{status: string, message: string, row?: array<string, mixed>}
     */
    public function createResidence(string $plaqueId, string $personId, string $placeId, User $user): array
    {
        $plaque = $this->plaquesQuery()->where('id', $plaqueId)->first();
        if ($plaque === null) {
            return ['status' => 'error', 'message' => 'Plaque not found.'];
        }

        $person = Span::query()->where('id', $personId)->where('type_id', 'person')->first();
        $place = Span::query()->where('id', $placeId)->where('type_id', 'place')->first();

        if ($person === null || $place === null) {
            return ['status' => 'error', 'message' => 'Person or place not found.'];
        }

        if (! $this->plaqueFeaturesPerson($plaque, $person) || ! $this->plaqueLocatedAt($plaque, $place)) {
            return ['status' => 'ineligible', 'message' => 'This plaque does not feature that person at that place.'];
        }

        $existing = $this->findResidence($person->id, $place->id);
        $row = $this->buildRow($plaque, $person, $place, $existing);

        if ($existing !== null) {
            return [
                'status' => 'skipped',
                'message' => 'Residence connection already exists.',
                'row' => $row,
            ];
        }

        if (! $row['can_create']) {
            return [
                'status' => 'ineligible',
                'message' => $this->ineligibleMessage($row),
                'row' => $row,
            ];
        }

        $connection = null;

        DB::transaction(function () use ($person, $place, $row, $user, &$connection) {
            $connectionSpan = Span::create([
                'name' => $person->name.' lived in '.$place->name,
                'type_id' => 'connection',
                'owner_id' => $user->id,
                'updater_id' => $user->id,
                'access_level' => 'public',
                'state' => 'complete',
                'start_year' => $row['start_year'],
                'end_year' => $row['end_year'],
                'start_precision' => 'year',
                'end_precision' => 'year',
            ]);

            $connection = Connection::create([
                'type_id' => self::RESIDENCE_TYPE,
                'parent_id' => $person->id,
                'child_id' => $place->id,
                'connection_span_id' => $connectionSpan->id,
            ]);
        });

        $row = $this->buildRow($plaque, $person, $place, $connection);

        return [
            'status' => 'created',
            'message' => sprintf(
                'Created %s lived in %s [%d - %d].',
                $person->name,
                $place->name,
                $row['start_year'],
                $row['end_year']
            ),
            'row' => $row,
        ];
    }

    /**
     * Scan one page of plaques and create any eligible missing residences.
     *
     * @return array{
     *     plaques_scanned: int,
     *     total_plaques: int,
     *     has_more: bool,
     *     offset: int,
     *     created: int,
     *     skipped: int,
     *     ineligible: int,
     *     errors: int,
     *     created_keys: array<int, string>,
     *     current_item: ?string
     * }
     */
    public function processCreatableScanBatch(int $limit, int $offset, User $user): array
    {
        $scan = $this->scanBatch($limit, $offset);
        $created = 0;
        $skipped = 0;
        $ineligible = 0;
        $errors = 0;
        $createdKeys = [];
        $currentItem = null;

        foreach ($scan['rows'] as $row) {
            if (! ($row['can_create'] ?? false)) {
                continue;
            }

            $currentItem = sprintf(
                '%s — %s lived in %s',
                $row['plaque_name'],
                $row['person_name'],
                $row['place_name']
            );

            $result = $this->createResidence(
                $row['plaque_id'],
                $row['person_id'],
                $row['place_id'],
                $user
            );

            match ($result['status']) {
                'created' => $created++,
                'skipped' => $skipped++,
                'ineligible' => $ineligible++,
                default => $errors++,
            };

            if (($result['status'] ?? null) === 'created' && ! empty($result['row']['key'])) {
                $createdKeys[] = $result['row']['key'];
            }
        }

        return [
            'plaques_scanned' => $scan['plaques_scanned'],
            'total_plaques' => $scan['total_plaques'],
            'has_more' => $scan['has_more'],
            'offset' => $scan['offset'],
            'created' => $created,
            'skipped' => $skipped,
            'ineligible' => $ineligible,
            'errors' => $errors,
            'created_keys' => $createdKeys,
            'current_item' => $currentItem,
        ];
    }

    /**
     * @param  array<int, array{plaque_id: string, person_id: string, place_id: string}>  $items
     * @return array<int, array{status: string, message: string, row?: array<string, mixed>}>
     */
    public function createResidences(array $items, User $user): array
    {
        $results = [];

        Connection::$skipCacheClearingDuringImport = true;

        try {
            foreach ($items as $item) {
                $results[] = $this->createResidence(
                    $item['plaque_id'],
                    $item['person_id'],
                    $item['place_id'],
                    $user
                );
            }
        } finally {
            Connection::$skipCacheClearingDuringImport = false;
        }

        return $results;
    }

    public function hasLivedPhrase(?string $description): bool
    {
        if ($description === null || $description === '') {
            return false;
        }

        return (bool) preg_match('/\blived\s+(?:here|in|at)\b/i', $description);
    }

    /**
     * @return array{start_year: int, end_year: int}|null
     */
    public function extractLivedDatesFromDescription(?string $description): ?array
    {
        if ($description === null || $description === '') {
            return null;
        }

        $pos = stripos($description, 'lived');
        if ($pos === false) {
            return null;
        }

        $afterLived = substr($description, $pos);

        if (preg_match('/\b(\d{4})\s*(?:-|to)\s*(\d{4})\b/i', $afterLived, $matches)) {
            $startYear = (int) $matches[1];
            $endYear = (int) $matches[2];
            if ($startYear >= 1 && $startYear <= 9999 && $endYear >= 1 && $endYear <= 9999) {
                return [
                    'start_year' => $startYear,
                    'end_year' => $endYear,
                ];
            }
        }

        return null;
    }

    public function extractLivedSnippet(?string $description): string
    {
        if ($description === null || $description === '') {
            return '';
        }

        $pos = stripos($description, 'lived');
        if ($pos === false) {
            return '';
        }

        $before = 25;
        $after = 100;
        $start = max(0, $pos - $before);
        $len = min(mb_strlen($description) - $start, $pos - $start + $after);
        $snippet = mb_substr($description, $start, $len);

        if ($start > 0) {
            $snippet = '...'.ltrim($snippet, " \t\n\r\0\x0B.,;:!?");
        }
        if ($start + $len < mb_strlen($description)) {
            $snippet = rtrim($snippet, " \t\n\r\0\x0B");
            $snippet .= '...';
        }

        return $snippet;
    }

    /**
     * @return \Illuminate\Support\Collection<int, Span>
     */
    public function featuredPeople(Span $plaque)
    {
        return $plaque->connectionsAsSubject
            ->filter(function (Connection $connection) {
                return $connection->type_id === 'features'
                    && $connection->child
                    && $connection->child->type_id === 'person';
            })
            ->map(fn (Connection $connection) => $connection->child)
            ->unique('id')
            ->values();
    }

    /**
     * @return \Illuminate\Support\Collection<int, Span>
     */
    public function locatedPlaces(Span $plaque)
    {
        return $plaque->connectionsAsSubject
            ->filter(function (Connection $connection) {
                return $connection->type_id === 'located'
                    && $connection->child
                    && $connection->child->type_id === 'place';
            })
            ->map(fn (Connection $connection) => $connection->child)
            ->unique('id')
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    public function buildRow(Span $plaque, Span $person, Span $place, ?Connection $residence): array
    {
        $description = $plaque->description ?? '';
        $hasLivedPhrase = $this->hasLivedPhrase($description);
        $extractedDates = $this->extractLivedDatesFromDescription($description);
        $hasResidence = $residence !== null;
        $canCreate = ! $hasResidence && $hasLivedPhrase && $extractedDates !== null;

        $blockedReason = null;
        if ($hasResidence) {
            $blockedReason = 'already_has_residence';
        } elseif (! $hasLivedPhrase) {
            $blockedReason = 'no_lived_phrase';
        } elseif ($extractedDates === null) {
            $blockedReason = 'no_dates';
        }

        return [
            'key' => $plaque->id.'|'.$person->id.'|'.$place->id,
            'plaque_id' => $plaque->id,
            'plaque_name' => $plaque->name,
            'plaque_slug' => $plaque->slug,
            'plaque_url' => route('spans.show', ['subject' => $plaque->slug ?? $plaque->id]),
            'person_id' => $person->id,
            'person_name' => $person->name,
            'person_slug' => $person->slug,
            'person_url' => route('spans.show', ['subject' => $person->slug ?? $person->id]),
            'place_id' => $place->id,
            'place_name' => $place->name,
            'place_slug' => $place->slug,
            'place_url' => route('spans.show', ['subject' => $place->slug ?? $place->id]),
            'has_lived_phrase' => $hasLivedPhrase,
            'inscription' => trim($description),
            'lived_snippet' => $this->extractLivedSnippet($description),
            'start_year' => $extractedDates['start_year'] ?? null,
            'end_year' => $extractedDates['end_year'] ?? null,
            'has_residence' => $hasResidence,
            'residence_url' => $hasResidence ? $this->residenceUrl($person, $place, $residence) : null,
            'residence_dates' => $hasResidence ? $this->formatConnectionDateRange($residence) : null,
            'can_create' => $canCreate,
            'create_blocked_reason' => $blockedReason,
        ];
    }

    private function plaqueFeaturesPerson(Span $plaque, Span $person): bool
    {
        $plaque->loadMissing(['connectionsAsSubject.child']);

        return $this->featuredPeople($plaque)->contains(fn (Span $featured) => $featured->id === $person->id);
    }

    private function plaqueLocatedAt(Span $plaque, Span $place): bool
    {
        $plaque->loadMissing(['connectionsAsSubject.child']);

        return $this->locatedPlaces($plaque)->contains(fn (Span $located) => $located->id === $place->id);
    }

    /**
     * @param  array<int, string>  $personIds
     * @param  array<int, string>  $placeIds
     * @return array<string, Connection>
     */
    private function residencesByPersonAndPlace(array $personIds, array $placeIds): array
    {
        if ($personIds === [] || $placeIds === []) {
            return [];
        }

        $connections = Connection::query()
            ->where('type_id', self::RESIDENCE_TYPE)
            ->whereIn('parent_id', $personIds)
            ->whereIn('child_id', $placeIds)
            ->with('connectionSpan')
            ->get();

        $keyed = [];
        foreach ($connections as $connection) {
            $keyed[$connection->parent_id.'|'.$connection->child_id] = $connection;
        }

        return $keyed;
    }

    private function findResidence(string $personId, string $placeId): ?Connection
    {
        return Connection::query()
            ->where('type_id', self::RESIDENCE_TYPE)
            ->where('parent_id', $personId)
            ->where('child_id', $placeId)
            ->with('connectionSpan')
            ->first();
    }

    private function residenceUrl(Span $person, Span $place, Connection $residence): ?string
    {
        $connectionSpan = $residence->connectionSpan;
        if ($connectionSpan === null) {
            return null;
        }

        $subject = $person->slug ?? $person->id;
        $object = $place->slug ?? $place->id;

        if (! empty($connectionSpan->short_id) && Str::length($connectionSpan->short_id) === 8) {
            return route('spans.connection.by-id', [
                'subject' => $subject,
                'predicate' => self::RESIDENCE_PREDICATE,
                'object' => $object,
                'shortId' => $connectionSpan->short_id,
            ]);
        }

        return route('spans.connection', [
            'subject' => $subject,
            'predicate' => self::RESIDENCE_PREDICATE,
            'object' => $object,
        ]);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function ineligibleMessage(array $row): string
    {
        return match ($row['create_blocked_reason'] ?? null) {
            'already_has_residence' => 'Residence connection already exists.',
            'no_lived_phrase' => 'Plaque text does not mention living here, in, or at this place.',
            'no_dates' => 'Could not extract residence dates from the plaque text.',
            default => 'This plaque is not eligible for a residence connection.',
        };
    }

    private function formatConnectionDateRange(Connection $connection): string
    {
        $start = $connection->formatted_start_date;
        $end = $connection->formatted_end_date;

        if ($start === null && $end === null) {
            return '';
        }

        return trim(($start ?? '?').' – '.($end ?? ''));
    }
}
