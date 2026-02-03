@props(['span', 'children', 'interactive' => false, 'colClass' => 'col-md-6', 'photoConnections' => null, 'parentsMap' => null, 'otherParentConnections' => null])

@php
$childIds = $children->pluck('id')->all();
$otherParentConnections = $otherParentConnections ?? (
    !empty($childIds)
        ? \App\Models\Connection::where('type_id', 'family')
            ->whereIn('child_id', $childIds)
            ->where('parent_id', '!=', $span->id)
            ->with('parent')
            ->get()
        : collect()
);

// Group by other parent: each group has other_parent (Span|null) and children (Collection)
$groupsByParentId = $otherParentConnections->groupBy('parent_id');
$groups = $groupsByParentId->map(function ($conns, $parentId) use ($children) {
    $otherParent = $conns->first()->parent;
    $childSpans = $conns->map(fn ($c) => $children->firstWhere('id', $c->child_id))->filter()->values();
    return [
        'other_parent' => $otherParent,
        'children' => $childSpans->sortBy(fn ($c) => $c->start_year ?? PHP_INT_MAX)->values(),
    ];
})->values();

// Children with no other parent recorded
$childIdsWithOtherParent = $otherParentConnections->pluck('child_id')->unique();
$childrenWithNoOther = $children->filter(fn ($c) => !$childIdsWithOtherParent->contains($c->id));
if ($childrenWithNoOther->isNotEmpty()) {
    $groups->push([
        'other_parent' => null,
        'children' => $childrenWithNoOther->sortBy(fn ($c) => $c->start_year ?? PHP_INT_MAX)->values(),
    ]);
}

// Sort groups: null other_parent last; else by other parent's start_year
$groups = $groups->sortBy(function ($g) {
    if ($g['other_parent'] === null) {
        return PHP_INT_MAX;
    }
    return $g['other_parent']->start_year ?? PHP_INT_MAX;
})->values();

// Use precomputed photo/parent when passed from family-relationships; otherwise run queries (e.g. family/index)
$photoConnectionsResolved = $photoConnections ?? collect();
$parentsMapResolved = $parentsMap ?? collect();
if (!$interactive && $photoConnectionsResolved->isEmpty() && $parentsMapResolved->isEmpty()) {
    $allMembers = $groups->flatMap(fn ($g) => array_merge($g['other_parent'] ? [$g['other_parent']] : [], $g['children']->all()))->unique('id')->values();
    if ($allMembers->isNotEmpty()) {
        $personIds = $allMembers->filter(fn ($m) => $m->type_id === 'person')->pluck('id')->filter()->unique()->toArray();
        if (!empty($personIds)) {
            $photoConnectionsResolved = \App\Models\Connection::where('type_id', 'features')
                ->whereIn('child_id', $personIds)
                ->whereHas('parent', fn ($q) => $q->where('type_id', 'thing')->whereJsonContains('metadata->subtype', 'photo'))
                ->with(['parent'])
                ->get()
                ->groupBy('child_id')
                ->map(fn ($conns) => $conns->first());
            $parentConnections = \App\Models\Connection::where('type_id', 'family')
                ->whereIn('child_id', $personIds)
                ->whereHas('parent', fn ($q) => $q->where('type_id', 'person'))
                ->with(['parent'])
                ->get()
                ->groupBy('child_id');
            foreach ($personIds as $personId) {
                $connections = $parentConnections->get($personId);
                if ($connections && $connections->isNotEmpty()) {
                    $parentSpans = $connections->map(fn ($c) => $c->parent)->filter()->values();
                    if ($parentSpans->isNotEmpty()) {
                        $parentsMapResolved->put($personId, $parentSpans);
                    }
                }
            }
        }
    }
}
@endphp

@if($children->isNotEmpty())
    <div class="{{ $colClass }}">
        <div class="border rounded bg-light h-100" style="font-size: 0.875rem; padding: 0.375rem;">
            <h6 class="small text-muted fw-bold" style="font-size: 0.75rem; margin-bottom: 0.375rem;">Partners & Children</h6>
            <ul class="list-unstyled mb-0">
                @foreach($groups as $group)
                    @php
                        $otherParent = $group['other_parent'];
                        $groupChildren = $group['children'];
                    @endphp
                    @if($otherParent !== null)
                        <li class="mb-2">
                            {{-- Other parent (co-parent) --}}
                            <div style="font-size: 0.875rem; line-height: 1.4; margin-bottom: 0.25rem;">
                                @if($interactive)
                                    <x-spans.display.interactive-card :span="$otherParent" />
                                @else
                                    @include('components.spans.partials.family-member-link', [
                                        'member' => $otherParent,
                                        'photoConnections' => $photoConnectionsResolved,
                                        'parentsMap' => $parentsMapResolved,
                                    ])
                                @endif
                            </div>
                            {{-- Children nested underneath --}}
                            <ul class="list-unstyled ms-3 mb-2" style="border-left: 2px solid rgba(0,0,0,0.08); padding-left: 0.5rem;">
                                @foreach($groupChildren as $child)
                                    <li style="font-size: 0.8125rem; line-height: 1.4; margin-bottom: 0.25rem;">
                                        @if($interactive)
                                            <x-spans.display.interactive-card :span="$child" />
                                        @else
                                            @include('components.spans.partials.family-member-link', [
                                                'member' => $child,
                                                'photoConnections' => $photoConnectionsResolved,
                                                'parentsMap' => $parentsMapResolved,
                                            ])
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </li>
                    @else
                        {{-- Children with no other parent recorded --}}
                        @foreach($groupChildren as $child)
                            <li style="font-size: 0.875rem; line-height: 1.4; margin-bottom: 0.375rem;">
                                @if($interactive)
                                    <x-spans.display.interactive-card :span="$child" />
                                @else
                                    @include('components.spans.partials.family-member-link', [
                                        'member' => $child,
                                        'photoConnections' => $photoConnectionsResolved,
                                        'parentsMap' => $parentsMapResolved,
                                    ])
                                @endif
                            </li>
                        @endforeach
                    @endif
                @endforeach
            </ul>
        </div>
    </div>
@endif
