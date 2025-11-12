@props(['title', 'members', 'isLegacy' => false, 'interactive' => false, 'colClass' => 'col-md-6'])

@php
    // Sort members by start date (birth year) - oldest first, null dates last
    $sortedMembers = $members->sortBy(function($member) {
        return $member->start_year ?? PHP_INT_MAX; // Put null dates at the end
    });
    
    // Only load photos and parents for non-legacy, non-interactive, person spans
    $photoConnections = collect();
    $parentsMap = collect();
    if (!$isLegacy && !$interactive) {
        // Get all person IDs for photo and parent lookup
        $personIds = $sortedMembers->filter(function($member) {
            return $member->type_id === 'person';
        })->pluck('id')->filter()->unique()->toArray();
        
        if (!empty($personIds)) {
            // Get first photo for each person in one query (optimize to avoid N+1)
            $photoConnections = \App\Models\Connection::where('type_id', 'features')
                ->whereIn('child_id', $personIds)
                ->whereHas('parent', function($q) {
                    $q->where('type_id', 'thing')
                      ->whereJsonContains('metadata->subtype', 'photo');
                })
                ->with(['parent'])
                ->get()
                ->groupBy('child_id')
                ->map(function($connections) {
                    // Get first photo for each person
                    return $connections->first();
                });
            
            // Get all parents for each person in one query (optimize to avoid N+1)
            $parentConnections = \App\Models\Connection::where('type_id', 'family')
                ->whereIn('child_id', $personIds)
                ->whereHas('parent', function($q) {
                    $q->where('type_id', 'person');
                })
                ->with(['parent'])
                ->get()
                ->groupBy('child_id');
            
            // Build parents map: person_id => array of parent spans
            foreach ($personIds as $personId) {
                $connections = $parentConnections->get($personId);
                if ($connections && $connections->isNotEmpty()) {
                    $parentSpans = $connections->map(function($connection) {
                        return $connection->parent;
                    })->filter()->values();
                    if ($parentSpans->isNotEmpty()) {
                        $parentsMap->put($personId, $parentSpans);
                    }
                }
            }
        }
    }
@endphp

@if($sortedMembers->isNotEmpty())
    <div class="{{ $colClass }}">
        <div class="border rounded p-2 bg-light h-100" style="font-size: 0.875rem;">
            <h6 class="small mb-1 text-muted fw-bold" style="font-size: 0.75rem;">{{ $title }}</h6>
            <ul class="list-unstyled mb-0">
                @foreach($sortedMembers as $member)
                    <li class="mb-1" style="font-size: 0.875rem; line-height: 1.4;">
                        @if($isLegacy)
                            <i class="bi bi-person-fill me-1" style="font-size: 0.8rem;"></i>
                            <span>{{ $member }}</span>
                        @elseif($interactive)
                            <x-spans.display.interactive-card :span="$member" />
                        @else
                            @php
                                // Get photo for this person if available
                                $photoUrl = null;
                                if ($member->type_id === 'person') {
                                    $photoConnection = $photoConnections->get($member->id);
                                    if ($photoConnection && $photoConnection->parent) {
                                        $metadata = $photoConnection->parent->metadata ?? [];
                                        $photoUrl = $metadata['thumbnail_url'] 
                                            ?? $metadata['medium_url'] 
                                            ?? $metadata['large_url'] 
                                            ?? null;
                                        
                                        // If we have a filename but no URL, use proxy route
                                        if (!$photoUrl && isset($metadata['filename']) && $metadata['filename']) {
                                            $photoUrl = route('images.proxy', ['spanId' => $photoConnection->parent->id, 'size' => 'thumbnail']);
                                        }
                                    }
                                }
                            @endphp
                            
                            @php
                                // Calculate age for living people
                                $age = null;
                                if ($member->type_id === 'person' && $member->start_year) {
                                    $currentYear = date('Y');
                                    $isAlive = !$member->end_year || $member->end_year >= $currentYear;
                                    if ($isAlive) {
                                        $age = $currentYear - $member->start_year;
                                    }
                                }
                                
                                // Get parents for tooltip with links
                                $parentSpans = $parentsMap->get($member->id);
                                $parentsData = null;
                                if ($parentSpans && $parentSpans->isNotEmpty()) {
                                    // Build array of parent data for JavaScript
                                    $parentsData = $parentSpans->map(function($parentSpan) {
                                        if ($parentSpan) {
                                            return [
                                                'name' => $parentSpan->name,
                                                'url' => route('spans.show', $parentSpan)
                                            ];
                                        }
                                        return null;
                                    })->filter()->values()->toArray();
                                }
                            @endphp
                            
                            <a href="{{ route('spans.show', $member) }}" 
                               class="text-decoration-none d-inline-flex align-items-center gap-1 family-member-link {{ $member->state === 'placeholder' ? 'text-placeholder' : 'text-' . $member->type_id }}"
                               style="font-size: inherit;"
                               @if($parentsData && !empty($parentsData))
                                   data-parents='@json($parentsData)'
                               @endif>
                                @if($photoUrl)
                                    <img src="{{ $photoUrl }}" 
                                         alt="{{ $member->name }}"
                                         class="rounded"
                                         style="width: 24px; height: 24px; object-fit: cover; flex-shrink: 0;"
                                         loading="lazy">
                                @else
                                    <x-icon :span="$member" style="font-size: 0.8rem; width: 0.875rem; height: 0.875rem; flex-shrink: 0;" />
                                @endif
                                <span style="font-size: inherit; line-height: 1.3;">
                                    {{ $member->name }}
                                    @if($age !== null)
                                        <span class="text-muted" style="font-size: 0.8em;">({{ $age }})</span>
                                    @endif
                                </span>
                            </a>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
@endif 