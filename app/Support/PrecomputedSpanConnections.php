<?php

namespace App\Support;

use App\Models\Connection;
use App\Models\Span;
use Illuminate\Support\Collection;

/**
 * Precomputed connections for a span, grouped by connection type.
 *
 * Use this whenever you have already loaded parent/child connections for a span
 * (from SpanShowPageLoader::connectionLists) and want to avoid re-querying in views
 * or components. Cards and partials can slice by type instead of running
 * their own connectionsAsSubject()->whereHas('type', ...) queries.
 *
 * Pattern:
 * 1. SpanShowPageLoader builds the dump once for a show request.
 * 2. Pass to the view as e.g. `precomputedConnections`.
 * 3. In Blade components: accept optional `precomputedConnections` prop; when
 *    present slice with getParentByType / getChildByType (or the ByTypes
 *    variants); when null, run the existing query (fallback for callers
 *    outside span show).
 *
 * What belongs where:
 * - This span’s connections: slice the dump. Both directions are already here.
 * - Facts that are not this span’s dump (photos of listed people, other spans
 *   that contain these children): SpanShowLookups, one batched query.
 * - Do not add card-named dump methods or extra SpanShowContext constructor
 *   arguments. Cards filter the slice they need.
 *
 * Reuse elsewhere: Any page that loads connections for a span (e.g. at-date,
 * compare, embed) can build PrecomputedSpanConnections and pass it in; the
 * same card components will then slice from it and avoid duplicate queries.
 *
 * @see SpanController::show()
 */
final class PrecomputedSpanConnections
{
    /** @var Collection<string, Collection> Connections where span is subject (parent), keyed by type_id */
    public readonly Collection $parentByType;

    /** @var Collection<string, Collection> Connections where span is object (child), keyed by type_id */
    public readonly Collection $childByType;

    public function __construct(Collection $parentConnections, Collection $childConnections)
    {
        $this->parentByType = $parentConnections->groupBy('type_id');
        $this->childByType = $childConnections->groupBy('type_id');
    }

    /**
     * Get connections where the span is the subject (parent), for a given type.
     *
     * @param  string  $typeId  connection_types.type (e.g. 'education', 'employment', 'residence')
     * @return Collection<int, \App\Models\Connection>
     */
    public function getParentByType(string $typeId): Collection
    {
        return $this->parentByType->get($typeId, collect());
    }

    /**
     * Get connections where the span is the object (child), for a given type.
     *
     * @param  string  $typeId  connection_types.type
     * @return Collection<int, \App\Models\Connection>
     */
    public function getChildByType(string $typeId): Collection
    {
        return $this->childByType->get($typeId, collect());
    }

    /**
     * Get connections where span is subject, for any of the given types (merged).
     *
     * @param  array<string>  $typeIds  e.g. ['employment', 'has_role']
     * @return Collection<int, \App\Models\Connection>
     */
    public function getParentByTypes(array $typeIds): Collection
    {
        $out = collect();
        foreach ($typeIds as $typeId) {
            $out = $out->merge($this->getParentByType($typeId));
        }

        return $out->values();
    }

    /**
     * Get connections where span is object, for any of the given types (merged).
     *
     * @param  array<string>  $typeIds
     * @return Collection<int, \App\Models\Connection>
     */
    public function getChildByTypes(array $typeIds): Collection
    {
        $out = collect();
        foreach ($typeIds as $typeId) {
            $out = $out->merge($this->getChildByType($typeId));
        }

        return $out->values();
    }

    /**
     * Eager loads for a span-show connections dump. Nested has_role → at_organisation
     * must be included so employment and getEffectiveSortDate() do not N+1.
     *
     * @return list<string>
     */
    public static function dumpEagerLoads(): array
    {
        return [
            'child',
            'child.type',
            'parent',
            'parent.type',
            'type',
            'connectionSpan.type',
            'connectionSpan.connectionsAsSubject.child',
            'connectionSpan.connectionsAsSubject.child.type',
            'connectionSpan.connectionsAsSubject.type',
            'connectionSpan.connectionsAsSubject.connectionSpan',
        ];
    }

    /**
     * Nested at_organisation rows for has_role connection-spans, keyed by that span id.
     * Uses already-loaded nested relations when present; otherwise one batched query.
     *
     * @return Collection<string, Connection>
     */
    public function atOrganisationByHasRoleConnectionSpanId(): Collection
    {
        $bySpanId = collect();
        $missingIds = [];

        foreach ($this->getParentByType('has_role') as $connection) {
            $connectionSpan = $connection->relationLoaded('connectionSpan')
                ? $connection->connectionSpan
                : null;

            if ($connectionSpan && $connectionSpan->relationLoaded('connectionsAsSubject')) {
                $nested = $connectionSpan->connectionsAsSubject->first(
                    fn ($row) => $row instanceof Connection
                        && $row->type_id === 'at_organisation'
                        && $row->child
                );
                if ($nested) {
                    $bySpanId->put($connectionSpan->id, $nested);
                }

                continue;
            }

            $id = $connectionSpan?->id ?? $connection->connection_span_id;
            if ($id) {
                $missingIds[] = $id;
            }
        }

        if ($missingIds !== []) {
            $rows = Connection::where('type_id', 'at_organisation')
                ->whereIn('parent_id', array_values(array_unique($missingIds)))
                ->with(['child', 'connectionSpan'])
                ->get();

            foreach ($rows as $row) {
                if ($row->child) {
                    $bySpanId->put($row->parent_id, $row);
                }
            }
        }

        return $bySpanId;
    }

    /**
     * Residence rows for the places-lived card. Uses loaded place children when
     * present; otherwise one batched span lookup so coordinates are not N+1.
     *
     * @return Collection<int, array{connection: Connection, place: Span, coordinates: ?array, dates: ?string, url: string}>
     */
    public function placesLivedRows(): Collection
    {
        $residences = $this->getParentByType('residence')
            ->sortBy(function ($connection) {
                $parts = $connection->getEffectiveSortDate();
                $y = $parts[0] ?? PHP_INT_MAX;
                $m = $parts[1] ?? PHP_INT_MAX;
                $d = $parts[2] ?? PHP_INT_MAX;

                return sprintf('%08d-%02d-%02d', $y, $m, $d);
            })
            ->values();

        $missingIds = [];
        foreach ($residences as $connection) {
            $childLoaded = $connection->relationLoaded('child') && $connection->child;
            if (! $childLoaded && $connection->child_id) {
                $missingIds[] = $connection->child_id;
            }
        }

        $placesById = collect();
        if ($missingIds !== []) {
            $placesById = Span::query()
                ->whereIn('id', array_values(array_unique($missingIds)))
                ->get()
                ->keyBy('id');
        }

        return $residences->map(function ($connection) use ($placesById) {
            $place = ($connection->relationLoaded('child') && $connection->child)
                ? $connection->child
                : $placesById->get($connection->child_id);

            if (! $place) {
                return null;
            }

            $dates = $connection->relationLoaded('connectionSpan')
                ? $connection->connectionSpan
                : null;

            return [
                'connection' => $connection,
                'place' => $place,
                'coordinates' => $place->getCoordinates(),
                'dates' => $dates?->formatted_date_range,
                'url' => route('spans.show', $dates ?: $place),
            ];
        })->filter()->values();
    }

    /**
     * Photos that feature this span (features connections where this span is the object).
     * Uses loaded photo parents when present; otherwise one batched span lookup.
     *
     * @return Collection<int, Connection>
     */
    public function featuredPhotoConnections(): Collection
    {
        $missingIds = [];
        foreach ($this->getChildByType('features') as $connection) {
            $parentLoaded = $connection->relationLoaded('parent') && $connection->parent;
            if (! $parentLoaded && $connection->parent_id) {
                $missingIds[] = $connection->parent_id;
            }
        }

        $parentsById = collect();
        if ($missingIds !== []) {
            $parentsById = Span::query()
                ->whereIn('id', array_values(array_unique($missingIds)))
                ->get()
                ->keyBy('id');
        }

        return $this->getChildByType('features')
            ->map(function ($connection) use ($parentsById) {
                $parent = ($connection->relationLoaded('parent') && $connection->parent)
                    ? $connection->parent
                    : $parentsById->get($connection->parent_id);

                if (! $parent) {
                    return null;
                }

                if ($parent->type_id !== 'thing' || ($parent->metadata['subtype'] ?? null) !== 'photo') {
                    return null;
                }

                $connection->setRelation('parent', $parent);

                return $connection;
            })
            ->filter()
            ->sortBy(function ($connection) {
                $imageSpan = $connection->parent;

                return sprintf(
                    '%08d-%02d-%02d',
                    $imageSpan->start_year ?? PHP_INT_MAX,
                    $imageSpan->start_month ?? PHP_INT_MAX,
                    $imageSpan->start_day ?? PHP_INT_MAX
                );
            })
            ->values();
    }

    /**
     * Albums this span created (thing / album), in dump order.
     *
     * @return Collection<int, \App\Models\Span>
     */
    public function createdAlbumSpans(): Collection
    {
        return $this->getParentByType('created')
            ->map(fn ($connection) => $connection->child)
            ->filter(function ($child) {
                if (! $child || $child->type_id !== 'thing') {
                    return false;
                }

                return ($child->metadata['subtype'] ?? null) === 'album';
            })
            ->unique('id')
            ->values();
    }

    public function hasRoleNamed(string $roleName): bool
    {
        return $this->getParentByType('has_role')
            ->contains(function ($connection) use ($roleName) {
                return $connection->child
                    && strcasecmp((string) $connection->child->name, $roleName) === 0;
            });
    }

    /**
     * Public Desert Island Discs set this person created, if it is already in the dump.
     */
    public function desertIslandDiscsSet(): ?Span
    {
        return $this->getParentByType('created')
            ->map(fn ($connection) => $connection->child)
            ->first(function ($child) {
                return $child
                    && $child->type_id === 'set'
                    && ($child->metadata['subtype'] ?? null) === 'desertislanddiscs';
            });
    }

    /**
     * Tracks in this span’s Desert Island Discs set, with album and artist attached
     * from the set-contents eager load so the card does not query per track.
     *
     * @return Collection<int, Span>
     */
    public function desertIslandDiscsTracks(): Collection
    {
        $set = $this->desertIslandDiscsSet();
        if (! $set) {
            return collect();
        }

        return $set->getSetContents()
            ->filter(function ($item) {
                return $item->type_id === 'thing'
                    && ($item->metadata['subtype'] ?? null) === 'track';
            })
            ->values()
            ->each(function (Span $track) {
                $album = $this->albumFromLoadedTrackRelations($track);
                $track->cached_album = $album;
                $track->cached_album_creator = $album ? $this->creatorFromLoadedRelations($album) : null;
                $track->cached_artist = $this->creatorFromLoadedRelations($track);
            });
    }

    private function albumFromLoadedTrackRelations(Span $track): ?Span
    {
        if (! $track->relationLoaded('connectionsAsObject')) {
            return null;
        }

        $connection = $track->connectionsAsObject->first(function ($connection) {
            $parent = $connection->parent;

            return $connection->type_id === 'contains'
                && $parent
                && $parent->type_id === 'thing'
                && ($parent->metadata['subtype'] ?? null) === 'album';
        });

        return $connection?->parent;
    }

    private function creatorFromLoadedRelations(Span $span): ?Span
    {
        if (! $span->relationLoaded('connectionsAsObject')) {
            return null;
        }

        $connection = $span->connectionsAsObject->first(function ($connection) {
            $parent = $connection->parent;

            return $connection->type_id === 'created'
                && $parent
                && in_array($parent->type_id, ['person', 'band'], true);
        });

        return $connection?->parent;
    }
}
