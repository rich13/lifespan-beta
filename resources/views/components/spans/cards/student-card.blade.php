@props(['span'])

@php
    // Only show for organisation spans
    if ($span->type_id !== 'organisation') {
        return;
    }

    // Get all people who have education connections to this organisation
    $educationConnections = \App\Models\Connection::where('type_id', 'education')
        ->where('child_id', $span->id) // Organisation is the child in education connections
        ->whereHas('parent', function($q) { $q->where('type_id', 'person'); })
        ->with(['parent', 'connectionSpan'])
        ->get();

    // Collect all unique people first
    $personIds = $educationConnections->pluck('parent_id')->filter()->unique()->toArray();
    
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
    $allStudents = collect();
    
    // Add people from education connections
    foreach ($educationConnections as $connection) {
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
            
            $allStudents->put($person->id, [
                'person' => $person,
                'connection_type' => 'education',
                'connection' => $connection,
                'photo_url' => $photoUrl,
                'date_text' => $dateText
            ]);
        }
    }

    // Sort students by education start date (earliest first), then by name for same dates
    $allStudents = $allStudents->sortBy(function($item) {
        $dates = $item['connection']->connectionSpan;
        if (!$dates) {
            // Put items without dates at the end (use a very large year)
            return [9999, 12, 31, $item['person']->name];
        }
        // Sort by start_year, start_month, start_day, then name
        return [
            $dates->start_year ?? 9999,
            $dates->start_month ?? 12,
            $dates->start_day ?? 31,
            $item['person']->name
        ];
    })->values();
    
    // Don't show the card if there are no students
    if ($allStudents->isEmpty()) {
        return;
    }
@endphp

<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="card-title mb-0">
            <i class="bi bi-mortarboard me-2"></i>
            <a href="{{ url('/spans/' . $span->id . '/studied-at') }}" class="text-decoration-none">
                Studied at {{ $span->name }}
            </a>
        </h6>
    </div>
    <div class="card-body p-2">
        <div class="list-group list-group-flush">
            @foreach($allStudents as $student)
                <div class="list-group-item px-0 py-2 border-0 border-bottom">
                    <div class="d-flex align-items-center">
                        <!-- Photo on the left -->
                        <div class="me-3 flex-shrink-0">
                            @if($student['photo_url'])
                                    <a href="{{ route('spans.show', $student['person']) }}">
                                        <img src="{{ $student['photo_url'] }}" 
                                             alt="{{ $student['person']->name }}"
                                             class="rounded"
                                             style="width: 50px; height: 50px; object-fit: cover;"
                                             loading="lazy">
                                    </a>
                                @else
                                    <a href="{{ route('spans.show', $student['person']) }}" 
                                       class="d-flex align-items-center justify-content-center bg-light rounded text-muted text-decoration-none"
                                       style="width: 50px; height: 50px;">
                                        <i class="bi bi-person"></i>
                                    </a>
                                @endif
                        </div>
                        
                        <!-- Name and dates on the right -->
                        <div class="flex-grow-1">
                            <a href="{{ route('spans.show', $student['person']) }}" 
                               class="text-decoration-none fw-semibold">
                                {{ $student['person']->name }}
                            </a>
                            @if($student['date_text'])
                                    <div class="text-muted small">
                                        <i class="bi bi-calendar me-1"></i>{{ $student['date_text'] }}
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
        </div>
    </div>
</div>

