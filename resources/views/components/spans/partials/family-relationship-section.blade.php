@props(['title', 'members', 'isLegacy' => false, 'interactive' => false, 'colClass' => 'col-md-6', 'photoConnections' => null, 'parentsMap' => null])

@php
    // Sort members by start date (birth year) - oldest first, null dates last
    $sortedMembers = $members->sortBy(function($member) {
        return $member->start_year ?? PHP_INT_MAX; // Put null dates at the end
    });

    // Use precomputed photo/parent data when passed from family-relationships; otherwise run queries (e.g. family/index)
    $photoConnectionsResolved = $photoConnections ?? collect();
    $parentsMapResolved = $parentsMap ?? collect();
    if (!$isLegacy && !$interactive && $photoConnectionsResolved->isEmpty() && $parentsMapResolved->isEmpty()) {
        $personIds = $sortedMembers->filter(fn ($member) => $member->type_id === 'person')->pluck('id')->filter()->unique()->toArray();
        if (!empty($personIds)) {
            $photoConnectionsResolved = \App\Models\Connection::where('type_id', 'features')
                ->whereIn('child_id', $personIds)
                ->whereHas('parent', function ($q) {
                    $q->where('type_id', 'thing')->whereJsonContains('metadata->subtype', 'photo');
                })
                ->with(['parent'])
                ->get()
                ->groupBy('child_id')
                ->map(fn ($connections) => $connections->first());
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
@endphp

@if($sortedMembers->isNotEmpty())
    <div class="{{ $colClass }}">
        <div class="border rounded bg-light h-100" style="font-size: 0.875rem; padding: 0.375rem;">
            <h6 class="small text-muted fw-bold" style="font-size: 0.75rem; margin-bottom: 0.375rem;">{{ $title }}</h6>
            <ul class="list-unstyled mb-0">
                @foreach($sortedMembers as $member)
                    <li style="font-size: 0.875rem; line-height: 1.4; @if(!$loop->last) margin-bottom: 0.375rem; @endif">
                        @if($isLegacy)
                            <i class="bi bi-person-fill me-1" style="font-size: 0.8rem;"></i>
                            <span>{{ $member }}</span>
                        @elseif($interactive)
                            <x-spans.display.interactive-card :span="$member" />
                        @else
                            @include('components.spans.partials.family-member-link', [
                                'member' => $member,
                                'photoConnections' => $photoConnectionsResolved,
                                'parentsMap' => $parentsMapResolved,
                            ])
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
@endif 