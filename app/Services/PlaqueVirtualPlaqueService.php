<?php

namespace App\Services;

use App\Models\Connection;
use App\Models\ConnectionType;
use App\Models\Span;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PlaqueVirtualPlaqueService
{
    /**
     * Connection types where person is parent and place is child.
     *
     * @return Collection<int, ConnectionType>
     */
    public function personPlaceConnectionTypes(): Collection
    {
        return ConnectionType::query()
            ->orderBy('type')
            ->get()
            ->filter(function (ConnectionType $type) {
                return in_array('person', $type->getAllowedSubjectTypes(), true)
                    && in_array('place', $type->getAllowedObjectTypes(), true);
            })
            ->values();
    }

    /**
     * Build status payload for the explore plaques virtual-plaque admin card.
     */
    public function statusForPlaque(Span $plaque): array
    {
        if (!$this->isLondonPlaqueSpan($plaque)) {
            return [
                'available' => false,
                'message' => 'This span is not a London blue plaque.',
            ];
        }

        $people = $this->featuredPeople($plaque);
        $places = $this->plaquePlaces($plaque);

        if ($people->isEmpty() || $places->isEmpty()) {
            return [
                'available' => false,
                'message' => 'A virtual plaque needs both a featured person and a located place.',
                'people' => $this->spanSummaries($people),
                'places' => $this->spanSummaries($places),
            ];
        }

        $description = (string) ($plaque->description ?? '');
        $hasLivedPhrase = $this->descriptionContainsLivedPhrase($description);
        $extractedDates = $this->extractLivedDatesFromDescription($description);
        $personPlaceTypes = $this->personPlaceConnectionTypes();
        $suggestedType = $hasLivedPhrase ? 'residence' : ($personPlaceTypes->first()?->type ?? 'residence');

        if (!$personPlaceTypes->contains('type', $suggestedType)) {
            $suggestedType = $personPlaceTypes->first()?->type ?? 'residence';
        }

        $pairs = [];
        $firstVirtualUrl = null;

        foreach ($people as $person) {
            foreach ($places as $place) {
                $existing = $this->personPlaceConnections($person, $place);
                $virtualUrls = $existing->map(fn (Connection $conn) => $this->virtualPlaqueUrl($conn))->filter()->values();

                if ($firstVirtualUrl === null && $virtualUrls->isNotEmpty()) {
                    $firstVirtualUrl = $virtualUrls->first();
                }

                $firstConnection = $existing->first();
                $preview = $this->buildVirtualPlaquePreview(
                    $person,
                    $firstConnection?->type_id ?? $suggestedType,
                    $firstConnection?->connectionSpan?->start_year ?? $extractedDates['start_year'] ?? null,
                    $firstConnection?->connectionSpan?->end_year ?? $extractedDates['end_year'] ?? null,
                    $firstConnection
                );

                $pairs[] = [
                    'person' => $this->spanSummary($person),
                    'place' => $this->spanSummary($place),
                    'preview' => $preview,
                    'existing_connections' => $existing->map(function (Connection $conn) {
                        $type = $conn->type;
                        $isForward = $conn->parent_id === $conn->subject->id;

                        return [
                            'type_id' => $conn->type_id,
                            'predicate' => $isForward ? $type->forward_predicate : $type->inverse_predicate,
                            'virtual_plaque_url' => $this->virtualPlaqueUrl($conn),
                            'connection_span_url' => $conn->connectionSpan
                                ? route('spans.show', $conn->connectionSpan)
                                : null,
                        ];
                    })->values()->all(),
                    'has_connection' => $existing->isNotEmpty(),
                ];
            }
        }

        $suggestedReason = $hasLivedPhrase
            ? 'Plaque description mentions "lived".'
            : 'Default connection type for person and place.';

        return [
            'available' => true,
            'plaque' => [
                'id' => $plaque->id,
                'name' => $plaque->name,
                'description_snippet' => $this->extractLivedSnippet($description),
            ],
            'people' => $this->spanSummaries($people),
            'places' => $this->spanSummaries($places),
            'pairs' => $pairs,
            'ready' => $firstVirtualUrl !== null,
            'virtual_plaque_url' => $firstVirtualUrl,
            'suggested' => [
                'connection_type' => $suggestedType,
                'connection_type_label' => $personPlaceTypes->firstWhere('type', $suggestedType)?->forward_predicate
                    ?? 'lived in',
                'start_year' => $extractedDates['start_year'] ?? null,
                'end_year' => $extractedDates['end_year'] ?? null,
                'has_lived_phrase' => $hasLivedPhrase,
                'reason' => $suggestedReason,
                'will_use_placeholder' => $extractedDates === null,
            ],
            'connection_types' => $personPlaceTypes->map(fn (ConnectionType $type) => [
                'type' => $type->type,
                'label' => $type->forward_predicate,
            ])->values()->all(),
        ];
    }

    /**
     * Create a person→place connection for virtual plaque rendering.
     */
    public function createPersonPlaceConnection(
        Span $person,
        Span $place,
        string $connectionType,
        User $user,
        ?int $startYear = null,
        ?int $endYear = null,
    ): Connection {
        if ($person->type_id !== 'person') {
            throw new \InvalidArgumentException('Subject must be a person span.');
        }

        if ($place->type_id !== 'place') {
            throw new \InvalidArgumentException('Object must be a place span.');
        }

        $type = ConnectionType::find($connectionType);
        if (!$type || !in_array('person', $type->getAllowedSubjectTypes(), true)
            || !in_array('place', $type->getAllowedObjectTypes(), true)) {
            throw new \InvalidArgumentException('Invalid connection type for person and place.');
        }

        $existing = Connection::query()
            ->where('type_id', $connectionType)
            ->where('parent_id', $person->id)
            ->where('child_id', $place->id)
            ->with(['type', 'subject', 'object', 'connectionSpan'])
            ->first();

        if ($existing && $existing->connectionSpan?->short_id) {
            return $existing;
        }

        $predicateLabel = $type->forward_predicate;

        return DB::transaction(function () use (
            $person,
            $place,
            $connectionType,
            $user,
            $startYear,
            $endYear,
            $predicateLabel
        ) {
            $hasDates = $startYear !== null || $endYear !== null;

            $connectionSpanData = [
                'name' => $person->name . ' ' . $predicateLabel . ' ' . $place->name,
                'type_id' => 'connection',
                'owner_id' => $user->id,
                'updater_id' => $user->id,
                'access_level' => 'public',
                'state' => $hasDates ? 'complete' : 'placeholder',
            ];

            if ($startYear !== null) {
                $connectionSpanData['start_year'] = $startYear;
                $connectionSpanData['start_precision'] = 'year';
            }
            if ($endYear !== null) {
                $connectionSpanData['end_year'] = $endYear;
                $connectionSpanData['end_precision'] = 'year';
            }

            $connectionSpan = Span::create($connectionSpanData);

            $connection = Connection::create([
                'type_id' => $connectionType,
                'parent_id' => $person->id,
                'child_id' => $place->id,
                'connection_span_id' => $connectionSpan->id,
            ]);

            return $connection->load(['type', 'subject', 'object', 'connectionSpan']);
        });
    }

    public function isLondonPlaqueSpan(Span $span): bool
    {
        if ($span->type_id !== 'thing') {
            return false;
        }

        $subtype = $span->metadata['subtype'] ?? null;

        return $subtype === 'plaque';
    }

    /**
     * @return Collection<int, Span>
     */
    public function featuredPeople(Span $plaque): Collection
    {
        return Connection::query()
            ->where('type_id', 'features')
            ->where('parent_id', $plaque->id)
            ->whereHas('child', fn ($q) => $q->where('type_id', 'person'))
            ->with('child')
            ->get()
            ->pluck('child')
            ->filter()
            ->unique('id')
            ->values();
    }

    /**
     * @return Collection<int, Span>
     */
    public function plaquePlaces(Span $plaque): Collection
    {
        return Connection::query()
            ->where('type_id', 'located')
            ->where('parent_id', $plaque->id)
            ->whereHas('child', fn ($q) => $q->where('type_id', 'place'))
            ->with('child')
            ->get()
            ->pluck('child')
            ->filter()
            ->unique('id')
            ->values();
    }

    public function descriptionContainsLivedPhrase(string $description): bool
    {
        if ($description === '') {
            return false;
        }

        if (preg_match('/\blived\s+(?:here|in|at)\b/i', $description)) {
            return true;
        }

        return stripos($description, 'lived') !== false;
    }

    /**
     * @return array{start_year: int, end_year: int}|null
     */
    public function extractLivedDatesFromDescription(string $description): ?array
    {
        if ($description === '') {
            return null;
        }

        $pos = stripos($description, 'lived');
        if ($pos === false) {
            return null;
        }

        $afterLived = substr($description, $pos);

        if (preg_match('/\b(\d{4})\s*(?:-|to)\s*(\d{4})\b/i', $afterLived, $m)) {
            $startYear = (int) $m[1];
            $endYear = (int) $m[2];
            if ($startYear >= 1 && $startYear <= 9999 && $endYear >= 1 && $endYear <= 9999) {
                return [
                    'start_year' => $startYear,
                    'end_year' => $endYear,
                ];
            }
        }

        return null;
    }

    public function extractLivedSnippet(string $description): string
    {
        if ($description === '') {
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
            $snippet = '...' . ltrim($snippet, " \t\n\r\0\x0B.,;:!?");
        }
        if ($start + $len < mb_strlen($description)) {
            $snippet = rtrim($snippet, " \t\n\r\0\x0B");
            $snippet .= '...';
        }

        return $snippet;
    }

    public function virtualPlaqueUrl(Connection $connection): ?string
    {
        $connection->loadMissing(['type', 'subject', 'object', 'connectionSpan']);

        if (!$connection->connectionSpan?->short_id) {
            return null;
        }

        $type = $connection->type;
        $isForward = $connection->parent_id === $connection->subject->id;
        $predicate = str_replace(
            ' ',
            '-',
            $isForward ? $type->forward_predicate : $type->inverse_predicate
        );

        return route('plaques.connection', [
            'subject' => $connection->subject,
            'predicate' => $predicate,
            'object' => $connection->object,
            'shortId' => $connection->connectionSpan->short_id,
        ]);
    }

    /**
     * @return Collection<int, Connection>
     */
    private function personPlaceConnections(Span $person, Span $place): Collection
    {
        return Connection::query()
            ->where('parent_id', $person->id)
            ->where('child_id', $place->id)
            ->whereIn('type_id', $this->personPlaceConnectionTypes()->pluck('type'))
            ->with(['type', 'subject', 'object', 'connectionSpan'])
            ->get();
    }

    /**
     * @param  Collection<int, Span>  $spans
     * @return array<int, array<string, mixed>>
     */
    private function spanSummaries(Collection $spans): array
    {
        return $spans->map(fn (Span $span) => $this->spanSummary($span))->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function spanSummary(Span $span): array
    {
        $summary = [
            'id' => $span->id,
            'name' => $span->name,
            'url' => route('spans.show', $span),
        ];

        if ($span->type_id === 'place') {
            $coordinates = $span->getCoordinates();
            if ($coordinates && isset($coordinates['latitude'], $coordinates['longitude'])) {
                $summary['latitude'] = (float) $coordinates['latitude'];
                $summary['longitude'] = (float) $coordinates['longitude'];
            }
        }

        if ($span->type_id === 'person') {
            $summary['start_year'] = $span->start_year;
            $summary['end_year'] = $span->end_year;
            $summary['is_ongoing'] = (bool) $span->is_ongoing;
        }

        return $summary;
    }

    /**
     * Build display data for rendering a virtual plaque (SVG) on the map preview.
     *
     * @return array{name_lines: array<int, string>, subject_dates: ?string, predicate: string, connection_dates: ?string}
     */
    public function buildVirtualPlaquePreview(
        Span $person,
        string $connectionTypeId,
        ?int $startYear = null,
        ?int $endYear = null,
        ?Connection $existing = null,
    ): array {
        if ($existing?->connectionSpan) {
            $connectionSpan = $existing->connectionSpan;
            $startYear = $connectionSpan->start_year;
            $endYear = $connectionSpan->end_year;
            $connectionTypeId = $existing->type_id;
        }

        $type = ConnectionType::find($connectionTypeId);
        $forwardPredicate = $type?->forward_predicate ?? 'lived in';
        $predicateKey = str_replace(' ', '-', $forwardPredicate);
        $predicate = config("plaques.predicate_mappings.{$predicateKey}")
            ?? ucwords($forwardPredicate);

        return [
            'name_lines' => $this->buildNameLines($person->getDisplayTitle()),
            'subject_dates' => $this->formatSpanDateRange($person),
            'predicate' => $predicate,
            'connection_dates' => $this->formatYearRange($startYear, $endYear),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function buildNameLines(string $displayTitle): array
    {
        $nameText = strtoupper($displayTitle);
        $nameWords = explode(' ', $nameText);

        if (count($nameWords) === 2) {
            return [$nameWords[0], $nameWords[1]];
        }

        if (count($nameWords) === 3) {
            return [$nameWords[0], $nameWords[1], $nameWords[2]];
        }

        $nameLines = [];
        $nameLine = '';
        foreach ($nameWords as $word) {
            if (strlen($nameLine) + strlen($word) + 1 <= 36) {
                $nameLine .= ($nameLine !== '' ? ' ' : '') . $word;
            } else {
                if ($nameLine !== '') {
                    $nameLines[] = $nameLine;
                }
                $nameLine = $word;
            }
        }

        if ($nameLine !== '') {
            $nameLines[] = $nameLine;
        }

        return $nameLines !== [] ? $nameLines : [$nameText];
    }

    private function formatSpanDateRange(Span $span): ?string
    {
        if (!$span->start_year && !$span->end_year) {
            return null;
        }

        $text = $span->start_year ? (string) $span->start_year : (string) $span->end_year;

        if ($span->end_year && $span->start_year !== $span->end_year) {
            $text .= ' – ' . $span->end_year;
        } elseif ($span->start_year && $span->is_ongoing) {
            $text .= ' –';
        }

        return $text;
    }

    private function formatYearRange(?int $startYear, ?int $endYear): ?string
    {
        if ($startYear === null && $endYear === null) {
            return null;
        }

        if ($startYear !== null && $endYear !== null && $startYear !== $endYear) {
            return $startYear . ' – ' . $endYear;
        }

        if ($startYear !== null) {
            return (string) $startYear;
        }

        return (string) $endYear;
    }
}
