@props(['span'])

@php
    // Only show for album spans
    if ($span->subtype !== 'album') {
        return;
    }

    // Get all tracks that are connected to this album via "contains" connection
    $trackConnections = $span->connectionsAsSubject()
        ->where('type_id', 'contains')
        ->whereHas('child', function($q) {
            $q->where('type_id', 'thing')
              ->whereJsonContains('metadata->subtype', 'track');
        })
        ->with(['child', 'connectionSpan'])
        ->get();

    // Get all track IDs for efficient Desert Island Discs lookup
    $trackIds = $trackConnections->pluck('child.id')->toArray();
    
    // Query all Desert Island Discs sets that contain these tracks (efficient batch query)
    $didConnections = \App\Models\Connection::where('type_id', 'contains')
        ->whereIn('child_id', $trackIds)
        ->whereHas('parent', function($q) {
            $q->where('type_id', 'set')
              ->whereJsonContains('metadata->subtype', 'desertislanddiscs');
        })
        ->with(['parent:id,name', 'parent.connectionsAsObject' => function($q) {
            $q->where('type_id', 'created')
              ->whereHas('parent', function($q2) {
                  $q2->where('type_id', 'person');
              })
              ->with(['parent:id,name']);
        }, 'parent.connectionsAsObject.parent'])
        ->get()
        ->groupBy('child_id'); // Group by track ID
    
    // Map to track data with ordering and Desert Island Discs info
    $tracks = $trackConnections->map(function($connection) use ($didConnections) {
        $track = $connection->child;
        $connectionSpan = $connection->connectionSpan;
        
        // Check if this track is in any Desert Island Discs set
        $didSets = $didConnections->get($track->id, collect());
        
        // Get all people whose Desert Island Discs sets contain this track
        $people = collect();
        foreach ($didSets as $didConnection) {
            $set = $didConnection->parent;
            if ($set && $set->connectionsAsObject) {
                // The set's connectionsAsObject where type is 'created' gives us the person who created it
                foreach ($set->connectionsAsObject as $createdConnection) {
                    if ($createdConnection->type_id === 'created' && $createdConnection->parent && $createdConnection->parent->type_id === 'person') {
                        $people->push($createdConnection->parent);
                    }
                }
            }
        }
        $people = $people->unique('id');
        
        // Build tooltip text with person names
        $tooltipText = 'Part of Desert Island Discs';
        if ($people->isNotEmpty()) {
            $names = $people->pluck('name')->toArray();
            if (count($names) === 1) {
                $tooltipText = "Part of " . $names[0] . "'s Desert Island Discs";
            } elseif (count($names) === 2) {
                $tooltipText = "Part of " . $names[0] . " and " . $names[1] . "'s Desert Island Discs";
            } else {
                $lastName = array_pop($names);
                $tooltipText = "Part of " . implode(', ', $names) . ', and ' . $lastName . "'s Desert Island Discs";
            }
        }
        
        return [
            'track' => $track,
            'connection' => $connection,
            'connectionSpan' => $connectionSpan,
            'isInDesertIslandDiscs' => $didSets->isNotEmpty(),
            'desertIslandDiscsSets' => $didSets->pluck('parent')->unique('id'),
            'desertIslandDiscsPeople' => $people,
            'desertIslandDiscsTooltip' => $tooltipText,
            // For sorting: use connection start date if available, otherwise use a large number
            'sort_key' => $connectionSpan && $connectionSpan->start_year 
                ? [
                    $connectionSpan->start_year ?? 9999,
                    $connectionSpan->start_month ?? 12,
                    $connectionSpan->start_day ?? 31,
                    $track->name
                ]
                : [9999, 12, 31, $track->name]
        ];
    });

    // Sort tracks by connection start date (release date), then by name
    $tracks = $tracks->sortBy('sort_key')->values();
    
    // Don't show the card if there are no tracks
    if ($tracks->isEmpty()) {
        return;
    }
@endphp

<div class="card mb-4">
    <div class="card-header">
        <h6 class="card-title mb-0">
            <i class="bi bi-music-note-list me-2"></i>Tracks
        </h6>
    </div>
    <div class="card-body p-2">
        <div class="list-group list-group-flush">
            @foreach($tracks as $index => $trackData)
                @php
                    $track = $trackData['track'];
                    $connectionSpan = $trackData['connectionSpan'];
                @endphp
                <div class="list-group-item px-0 py-2 border-0 border-bottom">
                    <div class="d-flex align-items-center">
                        <!-- Track number on the left -->
                        <div class="me-3 flex-shrink-0 text-muted" style="min-width: 24px; text-align: right;">
                            {{ $loop->iteration }}.
                        </div>
                        
                        <!-- Track name and Desert Island Discs indicator -->
                        <div class="flex-grow-1 d-flex align-items-center gap-2">
                            <a href="{{ route('spans.show', $track) }}" 
                               class="text-decoration-none">
                                {{ $track->name }}
                            </a>
                            @if($trackData['isInDesertIslandDiscs'])
                                @php
                                    $firstSet = $trackData['desertIslandDiscsSets']->first();
                                @endphp
                                @if($firstSet)
                                    <a href="{{ route('spans.show', $firstSet) }}" 
                                       class="badge bg-primary text-decoration-none" 
                                       title="{{ $trackData['desertIslandDiscsTooltip'] }}"
                                       data-bs-toggle="tooltip"
                                       data-bs-placement="top">
                                        <i class="bi bi-vinyl-fill"></i>
                                    </a>
                                @else
                                    <span class="badge bg-primary" 
                                          title="{{ $trackData['desertIslandDiscsTooltip'] }}"
                                          data-bs-toggle="tooltip"
                                          data-bs-placement="top">
                                        <i class="bi bi-vinyl-fill"></i>
                                    </span>
                                @endif
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

