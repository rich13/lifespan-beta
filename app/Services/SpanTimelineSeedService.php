<?php

namespace App\Services;

use App\Models\Connection;
use App\Models\Span;
use App\Models\User;
use App\Support\PrecomputedSpanConnections;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

/**
 * Build the Konva timeline's current-span payloads from a connections dump
 * so the span page does not re-query subject/object/during connections on first hit.
 */
class SpanTimelineSeedService
{
    /**
     * @param  Collection<int, Connection>|null  $duringRows  During connections already loaded for connection-spans in the dump
     * @return array{span: array{span: array<string, mixed>, connections: array<int, array<string, mixed>>}, subject_connections: array{span: array<string, mixed>, connections: array<int, array<string, mixed>>}, object_connections: array{span: array<string, mixed>, connections: array<int, array<string, mixed>>}, during_connections: array{span: array<string, mixed>, connections: array<int, array<string, mixed>>}}
     */
    public function seed(Span $span, PrecomputedSpanConnections $connections, ?Collection $duringRows = null): array
    {
        $duringRows ??= $this->duringConnectionsForSpans($this->connectionSpanIds($connections));
        $spanPayload = [
            'id' => $span->id,
            'name' => $span->name,
            'start_year' => $span->start_year,
            'end_year' => $span->end_year,
        ];

        $subjectConnections = $connections->parentByType
            ->flatten()
            ->filter(fn ($connection) => $connection instanceof Connection && $connection->type_id !== 'during')
            ->map(fn ($connection) => $this->mapSubjectConnection($connection))
            ->filter()
            ->sortBy('start_year')
            ->values();

        $objectConnections = $connections->childByType
            ->flatten()
            ->filter(fn ($connection) => $connection instanceof Connection && $connection->type_id !== 'during')
            ->map(fn ($connection) => $this->mapObjectConnection($connection))
            ->filter()
            ->sortBy('start_year')
            ->values();

        $duringConnections = $connections->getChildByType('during')
            ->map(fn ($connection) => $this->mapObjectConnection($connection))
            ->filter()
            ->sortBy('start_year')
            ->values();

        $nestedBySpanId = $this->nestedDuringByConnectionSpanId($duringRows);

        $timelineConnections = $subjectConnections->map(function (array $row) use ($nestedBySpanId) {
            $connectionSpanId = $row['connection_span_id'] ?? null;
            unset($row['connection_span_id']);
            $row['nested_connections'] = $connectionSpanId
                ? ($nestedBySpanId[$connectionSpanId] ?? [])
                : [];

            return $row;
        })->all();

        $subjectWithoutNested = $subjectConnections->map(function (array $row) {
            unset($row['connection_span_id']);

            return $row;
        })->all();

        return [
            'span' => [
                'span' => $spanPayload,
                'connections' => $timelineConnections,
            ],
            'subject_connections' => [
                'span' => $spanPayload,
                'connections' => $subjectWithoutNested,
            ],
            'object_connections' => [
                'span' => $spanPayload,
                'connections' => $objectConnections->all(),
            ],
            'during_connections' => [
                'span' => $spanPayload,
                'connections' => $duringConnections->all(),
            ],
        ];
    }

    /**
     * Connection-span ids already present on a dump, used to batch-load during/phase rows.
     *
     * @return list<string>
     */
    public function connectionSpanIds(PrecomputedSpanConnections $connections): array
    {
        return $connections->parentByType->flatten()
            ->merge($connections->childByType->flatten())
            ->map(fn ($connection) => $connection->connection_span_id ?? $connection->connectionSpan?->id)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * During connections that touch the given span ids (as parent or child).
     *
     * @param  list<string>  $spanIds
     * @return Collection<int, Connection>
     */
    public function duringConnectionsForSpans(array $spanIds): Collection
    {
        $query = $this->duringConnectionsQuery($spanIds);

        return $query ? $query->get() : collect();
    }

    /**
     * @param  list<string>  $spanIds
     */
    public function duringConnectionsQuery(array $spanIds): ?Builder
    {
        $spanIds = array_values(array_filter(array_unique($spanIds)));
        if ($spanIds === []) {
            return null;
        }

        return Connection::where('type_id', 'during')
            ->where(function ($query) use ($spanIds) {
                $query->whereIn('parent_id', $spanIds)->orWhereIn('child_id', $spanIds);
            })
            ->with(['parent', 'child', 'connectionSpan', 'type']);
    }

    /**
     * Nested during rows that hang off connection-spans (child_id).
     *
     * @param  list<string>  $connectionSpanIds
     */
    public function nestedDuringQueryForConnectionSpans(array $connectionSpanIds): ?Builder
    {
        $connectionSpanIds = array_values(array_filter(array_unique($connectionSpanIds)));
        if ($connectionSpanIds === []) {
            return null;
        }

        return Connection::where('type_id', 'during')
            ->whereIn('child_id', $connectionSpanIds)
            ->whereHas('connectionSpan', fn ($query) => $query->whereNotNull('start_year'))
            ->with(['parent', 'child', 'connectionSpan', 'type']);
    }

    /**
     * Light eager-loads for timeline bars. Card dumps still use dumpEagerLoads().
     *
     * @return list<string>
     */
    public function timelineEagerLoads(): array
    {
        return [
            'child',
            'parent',
            'type',
            'connectionSpan',
            'connectionSpan.type',
        ];
    }

    /**
     * Load a span’s connections dump and build a timeline seed (no as-of filter).
     *
     * @return array{span: array{span: array<string, mixed>, connections: array<int, array<string, mixed>>}, subject_connections: array{span: array<string, mixed>, connections: array<int, array<string, mixed>>}, object_connections: array{span: array<string, mixed>, connections: array<int, array<string, mixed>>}, during_connections: array{span: array<string, mixed>, connections: array<int, array<string, mixed>>}}
     */
    public function dumpAndSeed(Span $span): array
    {
        $with = PrecomputedSpanConnections::dumpEagerLoads();
        $connections = new PrecomputedSpanConnections(
            $span->connectionsAsSubjectWithAccess()->with($with)->get(),
            $span->connectionsAsObjectWithAccess()->with($with)->get(),
        );
        $duringRows = $this->duringConnectionsForSpans($this->connectionSpanIds($connections));

        return $this->seed($span, $connections, $duringRows);
    }

    /**
     * Timeline seeds for several already-authorised spans, with one connections
     * dump and one nested-during load for the whole set.
     *
     * @param  iterable<int, Span>  $spans
     * @return array<string, array{span: array{span: array<string, mixed>, connections: array<int, array<string, mixed>>}, subject_connections: array{span: array<string, mixed>, connections: array<int, array<string, mixed>>}, object_connections: array{span: array<string, mixed>, connections: array<int, array<string, mixed>>}, during_connections: array{span: array<string, mixed>, connections: array<int, array<string, mixed>>}}>
     */
    public function seedsForSpans(iterable $spans): array
    {
        $spans = collect($spans)->filter()->unique('id')->keyBy('id');
        if ($spans->isEmpty()) {
            return [];
        }

        $spanIds = $spans->keys()->all();
        $user = Auth::user();
        $with = $this->timelineEagerLoads();

        $parentConnections = $this->constrainRelatedSpanAccess(
            Connection::whereIn('parent_id', $spanIds)
                ->whereNotNull('connection_span_id')
                ->whereHas('connectionSpan', fn ($query) => $query->whereNotNull('start_year'))
                ->with($with),
            'child',
            $user
        )->get()->groupBy('parent_id');

        $childConnections = $this->constrainRelatedSpanAccess(
            Connection::whereIn('child_id', $spanIds)
                ->whereNotNull('connection_span_id')
                ->whereHas('connectionSpan', fn ($query) => $query->whereNotNull('start_year'))
                ->with($with),
            'parent',
            $user
        )->get()->groupBy('child_id');

        $dumps = [];
        $connectionSpanIds = [];
        foreach ($spans as $spanId => $span) {
            $dump = new PrecomputedSpanConnections(
                $parentConnections->get($spanId, collect()),
                $childConnections->get($spanId, collect()),
            );
            $dumps[$spanId] = $dump;
            $connectionSpanIds = array_merge($connectionSpanIds, $this->connectionSpanIds($dump));
        }

        $nestedQuery = $this->nestedDuringQueryForConnectionSpans($connectionSpanIds);
        $duringRows = collect();
        if ($nestedQuery !== null) {
            $this->constrainNestedDuringToAccessibleParents($nestedQuery, $user);
            $duringRows = $nestedQuery->get();
        }

        $seeds = [];
        foreach ($spans as $spanId => $span) {
            $seeds[$spanId] = $this->seed($span, $dumps[$spanId], $duringRows);
        }

        return $seeds;
    }

    /**
     * JSON shape used by POST /api/spans/batch-timeline.
     *
     * @param  iterable<int, Span>  $spans
     * @return array<string, array{span: array<string, mixed>, connections: array<int, array<string, mixed>>, during_connections: array<int, array<string, mixed>>}>
     */
    public function batchPayloads(iterable $spans): array
    {
        $payloads = [];
        foreach ($this->seedsForSpans($spans) as $spanId => $seed) {
            $payloads[$spanId] = [
                'span' => $seed['span']['span'],
                'connections' => $seed['span']['connections'],
                'during_connections' => $seed['during_connections']['connections'],
            ];
        }

        return $payloads;
    }

    /**
     * Timeline seed for the viewer’s personal span, when the You swimlane is eligible.
     *
     * @return array<string, mixed>|null
     */
    public function personalSeedForViewer(Span $viewedSpan, ?Span $personalSpan): ?array
    {
        if (! $personalSpan || $personalSpan->id === $viewedSpan->id) {
            return null;
        }

        $userId = Auth::id() ?? 'guest';

        return Cache::remember(
            'personal_timeline_seed_'.$userId.'_'.$personalSpan->id,
            300,
            fn () => $this->seedsForSpans([$personalSpan])[$personalSpan->id] ?? null
        );
    }

    /**
     * Current-span plus You seeds in one dump, for pages that embed both
     * without the card eager-loads.
     *
     * @return array{timelineSeed: array<string, mixed>|null, personalTimelineSeed: array<string, mixed>|null}
     */
    public function seedsForSpanAndViewer(Span $span, ?Span $personalSpan): array
    {
        $spans = collect([$span]);
        if ($personalSpan && $personalSpan->id !== $span->id) {
            $spans->push($personalSpan);
        }

        $seeds = $this->seedsForSpans($spans);

        return [
            'timelineSeed' => $seeds[$span->id] ?? null,
            'personalTimelineSeed' => ($personalSpan && $personalSpan->id !== $span->id)
                ? ($seeds[$personalSpan->id] ?? null)
                : null,
        ];
    }

    /**
     * Nested during rows keyed by the connection-span they hang off (child_id).
     *
     * @param  Collection<int, Connection>  $duringRows
     * @return array<string, list<array<string, mixed>>>
     */
    public function nestedDuringByConnectionSpanId(Collection $duringRows): array
    {
        $grouped = [];
        foreach ($duringRows as $connection) {
            if ($connection->type_id !== 'during' || ! $connection->child_id) {
                continue;
            }
            $mapped = $this->mapObjectConnection($connection);
            if ($mapped === null) {
                continue;
            }
            $mapped['is_nested'] = true;
            $mapped['parent_connection_id'] = $connection->parent_id;
            $grouped[$connection->child_id][] = $mapped;
        }

        return $grouped;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function mapSubjectConnection(Connection $connection): ?array
    {
        $connectionSpan = $connection->connectionSpan;
        $target = $connection->child;
        if (! $connectionSpan || $connectionSpan->start_year === null || ! $target) {
            return null;
        }

        if ($this->shouldOmitCreatedSetOrPhoto($connection->type_id, $target)) {
            return null;
        }

        return [
            'id' => $connection->id,
            'type_id' => $connection->type_id,
            'type_name' => $connection->type->forward_predicate ?? $connection->type_id,
            'target_name' => $target->name,
            'target_id' => $target->id,
            'target_type' => $target->type_id,
            'target_metadata' => $target->metadata ?? [],
            'target_accessible' => true,
            'start_year' => $connectionSpan->start_year,
            'start_month' => $connectionSpan->start_month,
            'start_day' => $connectionSpan->start_day,
            'end_year' => $connectionSpan->end_year,
            'end_month' => $connectionSpan->end_month,
            'end_day' => $connectionSpan->end_day,
            'metadata' => $connection->metadata ?? [],
            'connection_span_id' => $connectionSpan->id,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function mapObjectConnection(Connection $connection): ?array
    {
        $connectionSpan = $connection->connectionSpan;
        $target = $connection->parent;
        if (! $connectionSpan || $connectionSpan->start_year === null || ! $target) {
            return null;
        }

        return [
            'id' => $connection->id,
            'type_id' => $connection->type_id,
            'type_name' => $connection->type->inverse_predicate ?? $connection->type_id,
            'target_name' => $target->name,
            'target_id' => $target->id,
            'target_type' => $target->type_id,
            'target_metadata' => $target->metadata ?? [],
            'start_year' => $connectionSpan->start_year,
            'start_month' => $connectionSpan->start_month,
            'start_day' => $connectionSpan->start_day,
            'end_year' => $connectionSpan->end_year,
            'end_month' => $connectionSpan->end_month,
            'end_day' => $connectionSpan->end_day,
            'metadata' => $connection->metadata ?? [],
        ];
    }

    /**
     * Same target-span visibility as connectionsAsSubjectWithAccess / ObjectWithAccess.
     */
    private function constrainRelatedSpanAccess(Builder $query, string $relation, ?User $user): Builder
    {
        if ($user && $user->is_admin) {
            return $query;
        }

        if (! $user) {
            return $query->whereHas($relation, function ($related) {
                $related->where('access_level', 'public');
            });
        }

        return $query->whereHas($relation, function ($related) use ($user) {
            $related->where(function ($subQuery) use ($user) {
                $subQuery->where('access_level', 'public')
                    ->orWhere('owner_id', $user->id)
                    ->orWhereHas('spanPermissions', function ($permQuery) use ($user) {
                        $permQuery->where('user_id', $user->id)
                            ->whereIn('permission_type', ['view', 'edit']);
                    })
                    ->orWhereHas('spanPermissions', function ($permQuery) use ($user) {
                        $permQuery->whereNotNull('group_id')
                            ->whereIn('permission_type', ['view', 'edit'])
                            ->whereHas('group', function ($groupQuery) use ($user) {
                                $groupQuery->whereHas('users', function ($userQuery) use ($user) {
                                    $userQuery->where('user_id', $user->id);
                                });
                            });
                    });
            });
        });
    }

    /**
     * Nested during parents must be visible to the viewer.
     */
    public function constrainNestedDuringToAccessibleParents(Builder $query, ?User $user): void
    {
        if (! $user) {
            $query->whereHas('parent', function ($parentQuery) {
                $parentQuery->where('access_level', 'public');
            });

            return;
        }

        if ($user->is_admin) {
            return;
        }

        $query->whereHas('parent', function ($parentQuery) use ($user) {
            $parentQuery->where(function ($subQuery) use ($user) {
                $subQuery->where('access_level', 'public')
                    ->orWhere('owner_id', $user->id)
                    ->orWhereHas('spanPermissions', function ($permQuery) use ($user) {
                        $permQuery->where('user_id', $user->id)
                            ->whereIn('permission_type', ['view', 'edit']);
                    })
                    ->orWhereHas('spanPermissions', function ($permQuery) use ($user) {
                        $permQuery->whereNotNull('group_id')
                            ->whereIn('permission_type', ['view', 'edit'])
                            ->whereHas('group', function ($groupQuery) use ($user) {
                                $groupQuery->whereHas('users', function ($userQuery) use ($user) {
                                    $userQuery->where('user_id', $user->id);
                                });
                            });
                    });
            });
        });
    }

    private function shouldOmitCreatedSetOrPhoto(string $typeId, Span $target): bool
    {
        if ($typeId !== 'created') {
            return false;
        }

        if ($target->type_id === 'set') {
            return true;
        }

        $subtype = $target->metadata['subtype'] ?? null;

        return $target->type_id === 'thing' && in_array($subtype, ['photo', 'set'], true);
    }
}
