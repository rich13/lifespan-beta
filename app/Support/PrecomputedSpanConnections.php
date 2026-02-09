<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * Precomputed connections for a span, grouped by connection type.
 *
 * Use this whenever you have already loaded parent/child connections for a span
 * (e.g. from getConnectionsForSpanShow) and want to avoid re-querying in views
 * or components. Cards and partials can slice by type instead of running
 * their own connectionsAsSubject()->whereHas('type', ...) queries.
 *
 * Pattern:
 * 1. In the controller: build once from parentConnections + childConnections,
 *    e.g. `new PrecomputedSpanConnections($parentConnections, $childConnections)`.
 * 2. Pass to the view as e.g. `precomputedConnections`.
 * 3. In Blade components: accept optional `precomputedConnections` prop; when
 *    present use `$precomputedConnections->getParentByType('education')` (or
 *    getChildByType / getParentByTypes); when null, run the existing query
 *    (fallback for when the component is used outside span show).
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
}
