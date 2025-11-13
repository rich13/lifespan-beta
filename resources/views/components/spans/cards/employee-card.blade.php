@props(['span'])

@php
    // Only show for organisation spans
    if ($span->type_id !== 'organisation') {
        return;
    }

    // Get all people who have employment connections to this organisation
    $employmentConnections = \App\Models\Connection::where('type_id', 'employment')
        ->where('child_id', $span->id) // Organisation is the child in employment connections
        ->whereHas('parent', function($q) { $q->where('type_id', 'person'); })
        ->with(['parent', 'connectionSpan'])
        ->get();

    // Get all people who have has_role connections with at_organisation connections to this organisation
    $roleConnections = \App\Models\Connection::where('type_id', 'at_organisation')
        ->where('child_id', $span->id) // Organisation is the child in at_organisation connections
        ->whereHas('parent', function($q) {
            $q->whereHas('connectionsAsSubject', function($q2) {
                $q2->where('type_id', 'has_role');
            });
        })
        ->whereHas('parent.connectionsAsSubject', function($q) {
            $q->where('type_id', 'has_role')
              ->whereHas('parent', function($q2) { $q2->where('type_id', 'person'); });
        })
        ->with(['parent.connectionsAsSubject.parent', 'parent.connectionsAsSubject.connectionSpan'])
        ->get();

    // Also get people who have has_role connections where the connection span has at_organisation connections to this organisation
    // This covers the case: Person -> has_role -> Role (creates connection span) -> at_organisation -> Organisation
    $roleToOrgConnections = \App\Models\Connection::where('type_id', 'has_role')
        ->whereHas('connectionSpan', function($q) use ($span) {
            $q->whereHas('connectionsAsSubject', function($q2) use ($span) {
                $q2->where('type_id', 'at_organisation')
                   ->where('child_id', $span->id);
            });
        })
        ->whereHas('parent', function($q) { $q->where('type_id', 'person'); })
        ->with(['parent', 'connectionSpan', 'connectionSpan.connectionsAsSubject'])
        ->get();

    // Collect all unique people first
    $allEmployees = collect();
    
    // Add people from employment connections
    foreach ($employmentConnections as $connection) {
        if ($connection->parent && $connection->parent->type_id === 'person') {
            $allEmployees->put($connection->parent->id, [
                'person' => $connection->parent,
                'connection_type' => 'employment',
                'connection' => $connection
            ]);
        }
    }
    
    // Add people from role connections (at_organisation -> has_role -> person)
    foreach ($roleConnections as $connection) {
        // Find the has_role connection that connects to a person
        foreach ($connection->parent->connectionsAsSubject as $roleConnection) {
            if ($roleConnection->type_id === 'has_role' && $roleConnection->parent && $roleConnection->parent->type_id === 'person') {
                $allEmployees->put($roleConnection->parent->id, [
                    'person' => $roleConnection->parent,
                    'connection_type' => 'has_role',
                    'connection' => $roleConnection
                ]);
                break; // Only need one connection per person
            }
        }
    }
    
    // Add people from role-to-organisation connections (person -> has_role -> role -> at_organisation -> organisation)
    foreach ($roleToOrgConnections as $connection) {
        if ($connection->parent && $connection->parent->type_id === 'person') {
            $allEmployees->put($connection->parent->id, [
                'person' => $connection->parent,
                'connection_type' => 'has_role_via_role',
                'connection' => $connection
            ]);
        }
    }

    // Get all person IDs for photo lookup
    $personIds = $allEmployees->pluck('person.id')->filter()->unique()->toArray();
    
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
    
    // Add photos and dates to each employee
    $allEmployees = $allEmployees->map(function($employee) use ($photoConnections) {
        $person = $employee['person'];
        $connection = $employee['connection'];
        
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
        
        // Get dates from connection span (varies by connection type)
        $dates = null;
        if ($employee['connection_type'] === 'employment') {
            $dates = $connection->connectionSpan;
        } elseif ($employee['connection_type'] === 'has_role') {
            $dates = $connection->connectionSpan;
        } elseif ($employee['connection_type'] === 'has_role_via_role') {
            $dates = $connection->connectionSpan;
        }
        
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
        
        $employee['photo_url'] = $photoUrl;
        $employee['date_text'] = $dateText;
        // Store dates for sorting
        $employee['dates'] = $dates;
        return $employee;
    });

    // Sort employees by role start date (earliest first), then by name for same dates
    $allEmployees = $allEmployees->sortBy(function($item) {
        $dates = $item['dates'];
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
    
    // Don't show the card if there are no employees
    if ($allEmployees->isEmpty()) {
        return;
    }
@endphp

<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="card-title mb-0">
            <i class="bi bi-people me-2"></i>
            <a href="{{ url('/spans/' . $span->id . '/worked-at') }}" class="text-decoration-none">
                Worked at {{ $span->name }}
            </a>
        </h6>
    </div>
    <div class="card-body p-2">
        <div class="list-group list-group-flush">
            @foreach($allEmployees as $employee)
                <div class="list-group-item px-0 py-2 border-0 border-bottom">
                    <div class="d-flex align-items-center">
                        <!-- Photo on the left -->
                        <div class="me-3 flex-shrink-0">
                            @if($employee['photo_url'])
                                    <a href="{{ route('spans.show', $employee['person']) }}">
                                        <img src="{{ $employee['photo_url'] }}" 
                                             alt="{{ $employee['person']->name }}"
                                             class="rounded"
                                             style="width: 50px; height: 50px; object-fit: cover;"
                                             loading="lazy">
                                    </a>
                                @else
                                    <a href="{{ route('spans.show', $employee['person']) }}" 
                                       class="d-flex align-items-center justify-content-center bg-light rounded text-muted text-decoration-none"
                                       style="width: 50px; height: 50px;">
                                        <i class="bi bi-person"></i>
                                    </a>
                                @endif
                        </div>
                        
                        <!-- Name and dates on the right -->
                        <div class="flex-grow-1">
                            <a href="{{ route('spans.show', $employee['person']) }}" 
                               class="text-decoration-none fw-semibold">
                                {{ $employee['person']->name }}
                            </a>
                            @if($employee['date_text'])
                                    <div class="text-muted small">
                                        <i class="bi bi-calendar me-1"></i>{{ $employee['date_text'] }}
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
        </div>
    </div>
</div>
