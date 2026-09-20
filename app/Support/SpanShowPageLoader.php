<?php

namespace App\Support;

use App\Models\Connection;
use App\Models\Span;
use App\Services\ConfigurableStoryGeneratorService;
use App\Services\PlaqueVirtualPlaqueService;
use App\Services\SpanTimelineSeedService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * One load path for span-show facts. The main page, connection URLs, and the
 * experimental lab all use this so cards keep slicing the same dump.
 *
 * Redis still wraps the span row and family-tree walk in SpanController;
 * connections stay request-scoped because they depend on the viewer.
 */
final class SpanShowPageLoader
{
    /**
     * @param  array<string, mixed>|null  $familyData  Cached family tree when the controller already loaded it
     */
    public function load(
        Span $span,
        ?array $familyData = null,
        bool $includePageExtras = true,
        ?Connection $connectionForSpan = null,
    ): SpanShowPageData {
        [$parentConnections, $childConnections] = $this->connectionLists($span);
        $connections = new PrecomputedSpanConnections($parentConnections, $childConnections);
        $duringRows = $this->duringRows($connections);

        if ($familyData === null) {
            $familyData = $this->familyTree($span);
        }
        if ($familyData !== null) {
            $familyData = $this->enrichFamilyData($span, $familyData);
        }

        $seedService = app(SpanTimelineSeedService::class);
        $context = new SpanShowContext(
            $connections,
            $familyData,
            $this->educationCardData($span, $connections, $duringRows),
            $seedService->seed($span, $connections, $duringRows),
            $this->personalTimelineSeed($span),
            $this->desertIslandDiscsCardData($connections),
        );

        $story = $this->storyFor($span, $connections, $familyData);
        $resolvedConnection = $this->connectionForSpan($span, $connectionForSpan);

        if (! $includePageExtras) {
            return new SpanShowPageData(
                $span,
                $context,
                $parentConnections,
                $childConnections,
                $story,
                $resolvedConnection,
                collect(),
                null,
                collect(),
            );
        }

        return new SpanShowPageData(
            $span,
            $context,
            $parentConnections,
            $childConnections,
            $story,
            $resolvedConnection,
            $this->annotatingNotes($span),
            $this->bluePlaqueCardData($span),
            $this->directorConnectionsByFilmId($connections),
        );
    }

    /**
     * Parent and child connections for a span show, with the same eager load
     * and connection-span filter the connections list uses.
     *
     * @return array{0: Collection<int, Connection>, 1: Collection<int, Connection>}
     */
    public function connectionLists(Span $span): array
    {
        $with = PrecomputedSpanConnections::dumpEagerLoads();

        $parentConnections = $span->connectionsAsSubjectWithAccess()
            ->whereNotNull('connection_span_id')
            ->whereHas('connectionSpan')
            ->with($with)
            ->get()
            ->sortBy(fn ($connection) => $connection->getEffectiveSortDate());

        $childConnections = $span->connectionsAsObjectWithAccess()
            ->whereNotNull('connection_span_id')
            ->whereHas('connectionSpan')
            ->with($with)
            ->get()
            ->sortBy(fn ($connection) => $connection->getEffectiveSortDate());

        return [$parentConnections, $childConnections];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function familyTree(Span $span): ?array
    {
        if ($span->type_id !== 'person') {
            return null;
        }

        return [
            'ancestors' => $span->ancestors(3),
            'descendants' => $span->descendants(3),
            'siblings' => $span->siblings(),
            'unclesAndAunts' => $span->unclesAndAunts(),
            'cousins' => $span->cousins(),
            'nephewsAndNieces' => $span->nephewsAndNieces(),
            'extraNephewsAndNieces' => $span->extraNephewsAndNieces(),
            'stepParents' => $span->stepParents(),
            'inLawsAndOutLaws' => $span->inLawsAndOutLaws(),
            'extraInLawsAndOutLaws' => $span->extraInLawsAndOutLaws(),
            'childrenInLawsAndOutLaws' => $span->childrenInLawsAndOutLaws(),
            'grandchildrenInLawsAndOutLaws' => $span->grandchildrenInLawsAndOutLaws(),
        ];
    }

    public function bluePlaqueCardData(Span $span): ?array
    {
        if ($span->type_id === 'connection') {
            return null;
        }

        $plaqueConnections = $this->applyAsOfConnectionFilter(
            Connection::where('type_id', 'features')
                ->where('child_id', $span->id)
                ->whereHas('parent', function ($query) {
                    $query->where('type_id', 'thing')->whereJsonContains('metadata->subtype', 'plaque');
                })
                ->with(['parent'])
        )->get();
        if ($plaqueConnections->isEmpty()) {
            return null;
        }

        $plaque = $plaqueConnections->first()->parent;
        $locationConnection = $this->applyAsOfConnectionFilter(
            Connection::where('parent_id', $plaque->id)
                ->where('type_id', 'located')
                ->with(['child'])
        )->first();
        $location = $locationConnection ? $locationConnection->child : null;
        $plaqueMetadata = $plaque->metadata ?? [];

        return [
            'plaque' => $plaque,
            'photoUrl' => app(PlaqueVirtualPlaqueService::class)->photoUrlForPlaque($plaque),
            'locationName' => $location ? $location->name : null,
            'plaqueMetadata' => $plaqueMetadata,
            'plaqueColour' => $plaqueMetadata['colour'] ?? 'blue',
            'erectedYear' => $plaqueMetadata['erected'] ?? $plaque->start_year,
        ];
    }

    /**
     * @param  Collection<int, Connection>  $duringRows
     * @return array<string, mixed>|null
     */
    private function educationCardData(
        Span $span,
        PrecomputedSpanConnections $connections,
        Collection $duringRows,
    ): ?array {
        if ($span->type_id !== 'person') {
            return null;
        }

        $educationConnections = $connections->getParentByType('education')
            ->sortBy(function ($connection) {
                $parts = $connection->getEffectiveSortDate();
                $year = $parts[0] ?? PHP_INT_MAX;
                $month = $parts[1] ?? PHP_INT_MAX;
                $day = $parts[2] ?? PHP_INT_MAX;

                return sprintf('%08d-%02d-%02d', $year, $month, $day);
            })
            ->values();

        $educationIds = $educationConnections
            ->map(fn ($connection) => $connection->connectionSpan?->id)
            ->filter()
            ->unique()
            ->values();

        $allDuring = $duringRows->filter(function ($connection) use ($educationIds) {
            return $educationIds->contains($connection->parent_id)
                || $educationIds->contains($connection->child_id);
        });

        return [
            'connections' => $educationConnections,
            'duringBySubject' => $allDuring->groupBy('parent_id'),
            'duringByObject' => $allDuring->groupBy('child_id'),
        ];
    }

    /**
     * @return array{set: Span, tracks: Collection}|null
     */
    private function desertIslandDiscsCardData(PrecomputedSpanConnections $connections): ?array
    {
        $set = $connections->desertIslandDiscsSet();
        if (! $set) {
            return null;
        }

        return [
            'set' => $set,
            'tracks' => $connections->desertIslandDiscsTracks(),
        ];
    }

    /**
     * @return Collection<int, Connection>
     */
    private function duringRows(PrecomputedSpanConnections $connections): Collection
    {
        $seedService = app(SpanTimelineSeedService::class);
        $query = $seedService->duringConnectionsQuery($seedService->connectionSpanIds($connections));
        if ($query === null) {
            return collect();
        }

        return $this->applyAsOfConnectionFilter($query)->get();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function personalTimelineSeed(Span $span): ?array
    {
        return app(SpanTimelineSeedService::class)
            ->personalSeedForViewer($span, Auth::user()?->personalSpan);
    }

    /**
     * @param  array<string, mixed>|null  $familyData
     * @return array<string, mixed>
     */
    private function storyFor(Span $span, PrecomputedSpanConnections $connections, ?array $familyData): array
    {
        try {
            return app(ConfigurableStoryGeneratorService::class)
                ->generateStory($span, $connections, $familyData);
        } catch (\Exception $exception) {
            return [
                'paragraphs' => [],
                'metadata' => [],
                'error' => $exception->getMessage(),
            ];
        }
    }

    private function connectionForSpan(Span $span, ?Connection $current): ?Connection
    {
        if ($current) {
            $current->loadMissing(['parent.type', 'child.type', 'type', 'connectionSpan.type']);

            return $current;
        }

        if ($span->type_id !== 'connection') {
            return null;
        }

        return $this->applyAsOfConnectionFilter(
            Connection::where('connection_span_id', $span->id)
                ->with(['parent.type', 'child.type', 'type', 'connectionSpan.type'])
        )->first();
    }

    /**
     * @return Collection<int, Span>
     */
    private function annotatingNotes(Span $span): Collection
    {
        $user = Auth::user();
        $notes = $this->applyAsOfConnectionFilter(
            Connection::where('type_id', 'annotates')
                ->where('child_id', $span->id)
                ->with(['parent' => function ($query) {
                    $query->where('type_id', 'note')->with(['owner.personalSpan']);
                }])
        )->get()
            ->pluck('parent')
            ->filter();

        return $notes->filter(function ($note) use ($user) {
            if (! $note) {
                return false;
            }
            if (! $user) {
                return $note->access_level === 'public';
            }
            if ($note->owner_id === $user->id) {
                return true;
            }

            return $note->isAccessibleBy($user);
        })->unique('id')->values();
    }

    /**
     * @return Collection<int|string, Connection>
     */
    private function directorConnectionsByFilmId(PrecomputedSpanConnections $connections): Collection
    {
        $filmIds = $connections->getChildByType('features')
            ->filter(function ($connection) {
                $film = $connection->parent;

                return $film
                    && $film->type_id === 'thing'
                    && ($film->metadata['subtype'] ?? null) === 'film';
            })
            ->pluck('parent_id')
            ->unique()
            ->filter()
            ->values()
            ->all();

        if ($filmIds === []) {
            return collect();
        }

        return $this->applyAsOfConnectionFilter(
            Connection::where('type_id', 'created')
                ->whereIn('child_id', $filmIds)
                ->with('parent')
        )->get()
            ->groupBy('child_id')
            ->map(fn ($group) => $group->first());
    }

    /**
     * @param  array<string, mixed>  $familyData
     * @return array<string, mixed>
     */
    private function enrichFamilyData(Span $span, array $familyData): array
    {
        $descendants = $familyData['descendants'] ?? collect();
        $childrenForGrouped = $descendants->filter(fn ($item) => $item['generation'] === 1)->pluck('span');
        $childIdsForGrouped = $childrenForGrouped->pluck('id')->all();
        $otherParentConnectionsPrecomputed = ! empty($childIdsForGrouped)
            ? $this->applyAsOfConnectionFilter(
                Connection::where('type_id', 'family')
                    ->whereIn('child_id', $childIdsForGrouped)
                    ->where('parent_id', '!=', $span->id)
                    ->with('parent')
            )->get()
            : collect();

        $otherParentSpans = $otherParentConnectionsPrecomputed->pluck('parent')->unique('id')->filter();
        $allSpans = ($familyData['ancestors'] ?? collect())->pluck('span')
            ->merge($descendants->pluck('span'))
            ->merge($familyData['siblings'] ?? collect())->merge($familyData['unclesAndAunts'] ?? collect())
            ->merge($familyData['cousins'] ?? collect())->merge($familyData['nephewsAndNieces'] ?? collect())
            ->merge($familyData['extraNephewsAndNieces'] ?? collect())->merge($familyData['stepParents'] ?? collect())
            ->merge($familyData['inLawsAndOutLaws'] ?? collect())->merge($familyData['extraInLawsAndOutLaws'] ?? collect())
            ->merge($familyData['childrenInLawsAndOutLaws'] ?? collect())->merge($familyData['grandchildrenInLawsAndOutLaws'] ?? collect())
            ->merge($otherParentSpans)
            ->filter(fn ($person) => $person && $person->type_id === 'person')
            ->unique('id');
        $personIds = $allSpans->pluck('id')->filter()->unique()->values()->all();

        $photoConnections = collect();
        $parentConnectionsForMap = collect();
        if ($personIds !== []) {
            $photoConnections = $this->applyAsOfConnectionFilter(
                Connection::where('type_id', 'features')
                    ->whereIn('child_id', $personIds)
                    ->whereHas('parent', function ($query) {
                        $query->where('type_id', 'thing')->whereJsonContains('metadata->subtype', 'photo');
                    })
                    ->with(['parent'])
            )->get()
                ->groupBy('child_id')
                ->map(fn ($group) => $group->first());
            $parentConnectionsForMap = $this->applyAsOfConnectionFilter(
                Connection::where('type_id', 'family')
                    ->whereIn('child_id', $personIds)
                    ->whereHas('parent', function ($query) {
                        $query->where('type_id', 'person');
                    })
                    ->with(['parent'])
            )->get()
                ->groupBy('child_id');
        }

        $familyData['otherParentConnectionsPrecomputed'] = $otherParentConnectionsPrecomputed;
        $familyData['photoConnections'] = $photoConnections;
        $familyData['parentConnectionsForMap'] = $parentConnectionsForMap;
        $familyData['parentsMap'] = collect();
        foreach ($personIds as $personId) {
            $group = $parentConnectionsForMap->get($personId);
            if ($group && $group->isNotEmpty()) {
                $parentSpans = $group->map(fn ($connection) => $connection->parent)->filter()->values();
                if ($parentSpans->isNotEmpty()) {
                    $familyData['parentsMap']->put($personId, $parentSpans);
                }
            }
        }

        return $familyData;
    }

    private function applyAsOfConnectionFilter(Builder $query): Builder
    {
        $asOfParts = request()->attributes->get('as_of_calendar_parts');
        if (! is_array($asOfParts)) {
            return $query;
        }

        $year = (int) ($asOfParts['year'] ?? 0);
        $month = (int) ($asOfParts['month'] ?? 1);
        $day = (int) ($asOfParts['day'] ?? 1);

        $query->whereExists(function ($exists) use ($year, $month, $day) {
            $exists->selectRaw('1')
                ->from('connection_epistemic_revisions as cer')
                ->whereColumn('cer.connection_id', 'connections.id')
                ->where(function ($dateQuery) use ($year, $month, $day) {
                    $dateQuery->where('cer.effective_year', '<', $year)
                        ->orWhere(function ($inner) use ($year, $month) {
                            $inner->where('cer.effective_year', $year)
                                ->where('cer.effective_month', '<', $month);
                        })
                        ->orWhere(function ($inner) use ($year, $month, $day) {
                            $inner->where('cer.effective_year', $year)
                                ->where('cer.effective_month', $month)
                                ->where('cer.effective_day', '<=', $day);
                        });
                });
        });

        return $query;
    }
}
