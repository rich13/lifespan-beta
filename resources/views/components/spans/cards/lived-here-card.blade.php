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
    
    // Don't show the card if there are no residents
    if ($allResidents->isEmpty()) {
        return;
    }
@endphp

<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="card-title mb-0">
            <i class="bi bi-house me-2"></i>
            <a href="{{ url('/spans/' . $span->id . '/lived-in') }}" class="text-decoration-none">
                Lived at {{ $span->name }}
            </a>
        </h6>
    </div>
    <div class="card-body p-2">
        <div class="list-group list-group-flush">
            @foreach($allResidents as $resident)
                <div class="list-group-item px-0 py-2 border-0 border-bottom">
                    <div class="d-flex align-items-center">
                        <!-- Photo on the left -->
                        <div class="me-3 flex-shrink-0">
                            @if($resident['photo_url'])
                                <a href="{{ route('spans.show', $resident['person']) }}">
                                    <img src="{{ $resident['photo_url'] }}" 
                                         alt="{{ $resident['person']->name }}"
                                         class="rounded"
                                         style="width: 50px; height: 50px; object-fit: cover;"
                                         loading="lazy">
                                </a>
                            @else
                                <a href="{{ route('spans.show', $resident['person']) }}" 
                                   class="d-flex align-items-center justify-content-center bg-light rounded text-muted text-decoration-none"
                                   style="width: 50px; height: 50px;">
                                    <i class="bi bi-person"></i>
                                </a>
                            @endif
                        </div>
                        
                        <!-- Name and dates on the right -->
                        <div class="flex-grow-1">
                            <a href="{{ route('spans.show', $resident['person']) }}" 
                               class="text-decoration-none fw-semibold">
                                {{ $resident['person']->name }}
                            </a>
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
    </div>
</div>



