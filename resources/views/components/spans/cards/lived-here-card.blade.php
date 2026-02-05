@props(['span'])

@php
    // Only show for place spans
    if ($span->type_id !== 'place') {
        return;
    }

    // Get all people who have residence connections to this place
    $residenceConnections = \App\Models\Connection::where('type_id', 'residence')
        ->where('child_id', $span->id) // Place is the child in residence connections
        ->whereHas('parent', function($q) { $q->where('type_id', 'person'); })
        ->with(['parent', 'connectionSpan'])
        ->get();

    // Collect all unique people first
    $personIds = $residenceConnections->pluck('parent_id')->filter()->unique()->toArray();
    
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
    
    // Collect all unique people with their photos and dates
    $allResidents = collect();
    
    // Add people from residence connections
    foreach ($residenceConnections as $connection) {
        if ($connection->parent && $connection->parent->type_id === 'person') {
            $person = $connection->parent;
            
            // Get photo from pre-loaded collection
            $photoConnection = $photoConnections->get($person->id);
            $photoUrl = null;
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
            
            // Get dates from connection span
            $dates = $connection->connectionSpan;
            $hasDates = $dates && ($dates->start_year || $dates->end_year);
            $dateText = null;
            if ($hasDates) {
                if ($dates->start_year && $dates->end_year) {
                    $dateText = ($dates->formatted_start_date ?? $dates->start_year) . ' – ' . ($dates->formatted_end_date ?? $dates->end_year);
                } elseif ($dates->start_year) {
                    $dateText = 'from ' . ($dates->formatted_start_date ?? $dates->start_year);
                } elseif ($dates->end_year) {
                    $dateText = 'until ' . ($dates->formatted_end_date ?? $dates->end_year);
                }
            }
            
            $allResidents->put($person->id, [
                'person' => $person,
                'connection_type' => 'residence',
                'connection' => $connection,
                'photo_url' => $photoUrl,
                'date_text' => $dateText
            ]);
        }
    }

    // Sort residents by name
    $allResidents = $allResidents->sortBy(function($item) {
        return $item['person']->name;
    })->values();

    // Get all things/organisations/events that are located at this place
    $locatedConnections = \App\Models\Connection::where('type_id', 'located')
        ->where('child_id', $span->id) // Place is the child in located connections
        ->whereHas('parent', function($q) {
            $q->whereIn('type_id', ['thing', 'organisation', 'event', 'place']);
        })
        ->with(['parent', 'connectionSpan'])
        ->get();

    // Collect all located items with their photos and dates
    $allLocated = collect();
    
    foreach ($locatedConnections as $connection) {
        if ($connection->parent) {
            $item = $connection->parent;
            $itemType = $item->type_id;
            
            // Get photo for things (photos, etc.)
            $photoUrl = null;
            if ($itemType === 'thing') {
                $metadata = $item->metadata ?? [];
                $subtype = $metadata['subtype'] ?? null;
                
                // Check if it's a photo
                if ($subtype === 'photo') {
                    $photoUrl = $metadata['thumbnail_url'] 
                        ?? $metadata['medium_url'] 
                        ?? $metadata['large_url'] 
                        ?? null;
                    
                    // If we have a filename but no URL, use proxy route
                    if (!$photoUrl && isset($metadata['filename']) && $metadata['filename']) {
                        $photoUrl = route('images.proxy', ['spanId' => $item->id, 'size' => 'thumbnail']);
                    }
                }
            }
            
            // Get dates from connection span
            $dates = $connection->connectionSpan;
            $hasDates = $dates && ($dates->start_year || $dates->end_year);
            $dateText = null;
            if ($hasDates) {
                if ($dates->start_year && $dates->end_year) {
                    $dateText = ($dates->formatted_start_date ?? $dates->start_year) . ' – ' . ($dates->formatted_end_date ?? $dates->end_year);
                } elseif ($dates->start_year) {
                    $dateText = 'from ' . ($dates->formatted_start_date ?? $dates->start_year);
                } elseif ($dates->end_year) {
                    $dateText = 'until ' . ($dates->formatted_end_date ?? $dates->end_year);
                }
            }
            
            $allLocated->put($item->id, [
                'item' => $item,
                'item_type' => $itemType,
                'connection_type' => 'located',
                'connection' => $connection,
                'photo_url' => $photoUrl,
                'date_text' => $dateText
            ]);
        }
    }

    // Sort located items by name
    $allLocated = $allLocated->sortBy(function($item) {
        return $item['item']->name;
    })->values();
    
    // Don't show the card if there are no residents and no located items
    if ($allResidents->isEmpty() && $allLocated->isEmpty()) {
        return;
    }
    
    // Determine which tab to show by default (show first non-empty tab)
    $defaultTab = $allResidents->isNotEmpty() ? 'lived' : 'located';
@endphp

<div class="card mb-4 place-residence-card" data-place-id="{{ $span->id }}">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="card-title mb-0">
            <i class="bi bi-geo-alt me-2"></i>
            <a href="{{ url('/spans/' . $span->id . '/lived-in') }}" class="text-decoration-none">
                In {{ $span->name }}
            </a>
        </h6>
        <!-- Button Group Toggle -->
        <div class="btn-group btn-group-sm" role="group" aria-label="Toggle view">
            <input type="radio" class="btn-check" name="view-toggle-{{ $span->id }}" id="lived-toggle-{{ $span->id }}" autocomplete="off" {{ $defaultTab === 'lived' ? 'checked' : '' }}>
            <label class="btn btn-outline-primary" for="lived-toggle-{{ $span->id }}">
                <i class="bi bi-house me-1"></i>
                Lived
                @if($allResidents->isNotEmpty())
                    <span class="badge bg-secondary ms-1">{{ $allResidents->count() }}</span>
                @endif
            </label>

            <input type="radio" class="btn-check" name="view-toggle-{{ $span->id }}" id="located-toggle-{{ $span->id }}" autocomplete="off" {{ $defaultTab === 'located' ? 'checked' : '' }}>
            <label class="btn btn-outline-primary" for="located-toggle-{{ $span->id }}">
                <i class="bi bi-geo-alt me-1"></i>
                Located
                @if($allLocated->isNotEmpty())
                    <span class="badge bg-secondary ms-1">{{ $allLocated->count() }}</span>
                @endif
            </label>
        </div>
    </div>
    <div class="card-body p-2">
        <!-- Lived Here View -->
        <div class="view-content" id="lived-view-{{ $span->id }}" style="display: {{ $defaultTab === 'lived' ? 'block' : 'none' }};">
                @if($allResidents->isNotEmpty())
                    <div class="list-group list-group-flush">
                        @foreach($allResidents as $resident)
                            @php
                                $person = $resident['person'];
                                $connectionSpan = $resident['connection']->connectionSpan;
                                $linkSpan = $connectionSpan ?? $person;
                                $isAccessible = $linkSpan->isAccessibleBy(auth()->user());
                            @endphp
                            <div class="list-group-item px-0 py-2 border-0 border-bottom">
                                <div class="d-flex align-items-center">
                                    <!-- Photo on the left -->
                                    <div class="me-3 flex-shrink-0">
                                        @if($resident['photo_url'] && $isAccessible)
                                            <a href="{{ route('spans.show', $linkSpan) }}">
                                                <img src="{{ $resident['photo_url'] }}" 
                                                     alt="{{ $person->name }}"
                                                     class="rounded"
                                                     style="width: 50px; height: 50px; object-fit: cover;"
                                                     loading="lazy">
                                            </a>
                                        @else
                                            @if($isAccessible)
                                                <a href="{{ route('spans.show', $linkSpan) }}" 
                                                   class="d-flex align-items-center justify-content-center bg-light rounded text-muted text-decoration-none"
                                                   style="width: 50px; height: 50px;">
                                                    <i class="bi bi-person"></i>
                                                </a>
                                            @else
                                                <div class="d-flex align-items-center justify-content-center bg-light rounded text-muted"
                                                     style="width: 50px; height: 50px;">
                                                    <i class="bi bi-person"></i>
                                                </div>
                                            @endif
                                        @endif
                                    </div>
                                    
                                    <!-- Name and dates on the right (link to connection span so user sees "lived in [place]" page) -->
                                    <div class="flex-grow-1">
                                        @if($linkSpan && $isAccessible)
                                            <a href="{{ route('spans.show', $linkSpan) }}" class="text-decoration-none fw-semibold">{{ trim($person->name) }}</a>
                                        @elseif($person->isAccessibleBy(auth()->user()))
                                            <x-span-link :span="$person" class="text-decoration-none fw-semibold" />
                                        @else
                                            <span class="text-muted fst-italic fw-semibold">Private person</span>
                                        @endif
                                        @if($resident['date_text'])
                                            <div class="text-muted small">
                                                <i class="bi bi-calendar me-1"></i>{{ $resident['date_text'] }}
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-muted small mb-0">No residents found.</p>
                @endif
        </div>
        
        <!-- Located Here View -->
        <div class="view-content" id="located-view-{{ $span->id }}" style="display: {{ $defaultTab === 'located' ? 'block' : 'none' }};">
                @if($allLocated->isNotEmpty())
                    <div class="list-group list-group-flush">
                        @foreach($allLocated as $located)
                            <div class="list-group-item px-0 py-2 border-0 border-bottom">
                                <div class="d-flex align-items-center">
                                    <!-- Photo/Icon on the left -->
                                    <div class="me-3 flex-shrink-0">
                                        @php
                                            $item = $located['item'];
                                            $isAccessible = $item->isAccessibleBy(auth()->user());
                                        @endphp
                                        @if($located['photo_url'] && $isAccessible)
                                            <a href="{{ route('spans.show', $item) }}">
                                                <img src="{{ $located['photo_url'] }}" 
                                                     alt="{{ $item->name }}"
                                                     class="rounded"
                                                     style="width: 50px; height: 50px; object-fit: cover;"
                                                     loading="lazy">
                                            </a>
                                        @else
                                            @if($isAccessible)
                                                <a href="{{ route('spans.show', $item) }}" 
                                                   class="d-flex align-items-center justify-content-center bg-light rounded text-muted text-decoration-none"
                                                   style="width: 50px; height: 50px;">
                                                    @if($located['item_type'] === 'organisation')
                                                        <i class="bi bi-building"></i>
                                                    @elseif($located['item_type'] === 'event')
                                                        <i class="bi bi-calendar-event"></i>
                                                    @elseif($located['item_type'] === 'thing')
                                                        <i class="bi bi-box"></i>
                                                    @else
                                                        <i class="bi bi-geo-alt"></i>
                                                    @endif
                                                </a>
                                            @else
                                                <div class="d-flex align-items-center justify-content-center bg-light rounded text-muted"
                                                     style="width: 50px; height: 50px;">
                                                    @if($located['item_type'] === 'organisation')
                                                        <i class="bi bi-building"></i>
                                                    @elseif($located['item_type'] === 'event')
                                                        <i class="bi bi-calendar-event"></i>
                                                    @elseif($located['item_type'] === 'thing')
                                                        <i class="bi bi-box"></i>
                                                    @else
                                                        <i class="bi bi-geo-alt"></i>
                                                    @endif
                                                </div>
                                            @endif
                                        @endif
                                    </div>
                                    
                                    <!-- Name and dates on the right -->
                                    <div class="flex-grow-1">
                                        <x-span-link :span="$located['item']" class="text-decoration-none fw-semibold" />
                                        <div class="text-muted small">
                                            <span class="badge bg-secondary">{{ ucfirst($located['item_type']) }}</span>
                                        </div>
                                        @if($located['date_text'])
                                            <div class="text-muted small mt-1">
                                                <i class="bi bi-calendar me-1"></i>{{ $located['date_text'] }}
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-muted small mb-0">No items located here.</p>
                @endif
        </div>
    </div>
</div>









