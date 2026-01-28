@props(['span'])

@php
    // Only show for connection spans
    if ($span->type_id !== 'connection') {
        return;
    }

    // Spans without temporal data cannot participate in temporal relations
    // (they are effectively placeholders with unknown dates)
    if (!$span->start_year) {
        return;
    }

    // Find the connection that uses this span as its connection_span_id
    $currentConnection = \App\Models\Connection::where('connection_span_id', $span->id)
        ->with(['subject', 'object', 'type'])
        ->first();

    // If no connection found, don't show the component
    if (!$currentConnection) {
        return;
    }

    // Get the subject (we'll find other connections to the same subject)
    $subject = $currentConnection->subject;
    $user = auth()->user();
    
    // Get the current connection span's temporal range
    $temporalService = app(\App\Services\Temporal\TemporalService::class);
    $currentRange = \App\Services\Temporal\TemporalRange::fromSpan($span);

    // Find all connections connected to the same subject (with access control)
    // Get connections where subject is parent
    $connectionsAsSubject = $subject->connectionsAsSubjectWithAccess($user)
        ->where('id', '!=', $currentConnection->id)
        ->whereNotNull('connection_span_id')
        ->with(['connectionSpan', 'subject', 'object', 'type'])
        ->get();
    
    // Get connections where subject is child
    $connectionsAsObject = $subject->connectionsAsObjectWithAccess($user)
        ->where('id', '!=', $currentConnection->id)
        ->whereNotNull('connection_span_id')
        ->with(['connectionSpan', 'subject', 'object', 'type'])
        ->get();
    
    // Also get phase spans connected to the current connection span via "during" connections
    $phaseConnectionsQuery = \App\Models\Connection::where('type_id', 'during')
        ->where('parent_id', $span->id) // Current connection span contains the phase
        ->whereHas('child', function($q) {
            $q->where('type_id', 'phase');
        });
    
    // Apply access control to phase spans
    if (!$user) {
        // Guest users can only see connections to public phase spans
        $phaseConnectionsQuery->whereHas('child', function($q) {
            $q->where('access_level', 'public');
        });
    } elseif (!$user->is_admin) {
        // Regular users can see connections to spans they have permission to view
        $phaseConnectionsQuery->whereHas('child', function($q) use ($user) {
            $q->where(function($subQ) use ($user) {
                // Public spans
                $subQ->where('access_level', 'public')
                    // Owner's spans
                    ->orWhere('owner_id', $user->id)
                    // Spans with explicit user permissions
                    ->orWhereHas('spanPermissions', function($permQ) use ($user) {
                        $permQ->where('user_id', $user->id)
                              ->whereIn('permission_type', ['view', 'edit']);
                    })
                    // Spans with group permissions
                    ->orWhereHas('spanPermissions', function($permQ) use ($user) {
                        $permQ->whereNotNull('group_id')
                              ->whereIn('permission_type', ['view', 'edit'])
                              ->whereHas('group', function($groupQ) use ($user) {
                                  $groupQ->whereHas('users', function($userQ) use ($user) {
                                      $userQ->where('user_id', $user->id);
                                  });
                              });
                    });
            });
        });
    }
    
    $phaseConnections = $phaseConnectionsQuery
        ->with(['child', 'connectionSpan'])
        ->get()
        ->map(function($conn) {
            // Create a pseudo-connection structure for phase spans
            // Use the connection type if available, otherwise create a simple object
            $connectionType = $conn->type ?? (object)['forward_predicate' => 'contains', 'inverse_predicate' => 'during', 'type' => 'during'];
            $conn->connection_type = $connectionType;
            $conn->is_subject_parent = true;
            $conn->other_span = $conn->child;
            return $conn;
        });
    
    // Get the current span's start and end years for filtering
    $currentStartYear = $span->start_year;
    $currentEndYear = $span->end_year;
    
    // Merge the three collections
    $allConnections = $connectionsAsSubject->merge($connectionsAsObject)->merge($phaseConnections)
        ->filter(function($conn) use ($temporalService, $currentRange, $currentStartYear, $currentEndYear) {
            // For phase spans, use the phase span itself (not connectionSpan)
            // Check if this is a phase connection by checking if child is loaded and is a phase
            $isPhaseConnection = false;
            $phaseSpan = null;
            
            if (isset($conn->child) && $conn->child && $conn->child->type_id === 'phase') {
                $isPhaseConnection = true;
                $phaseSpan = $conn->child;
            } elseif (isset($conn->other_span) && $conn->other_span && $conn->other_span->type_id === 'phase') {
                $isPhaseConnection = true;
                $phaseSpan = $conn->other_span;
            }
            
            if ($isPhaseConnection && $phaseSpan) {
                $otherSpan = $phaseSpan;
            } else {
                // Filter out connections without connection spans
                if (!$conn->connectionSpan) {
                    return false;
                }
                $otherSpan = $conn->connectionSpan;
            }
            
            // Check if the span has temporal data (start_year)
            if (!$otherSpan->start_year) {
                return false;
            }
            
            // Check if this span overlaps in time with the current one
            try {
                $otherRange = \App\Services\Temporal\TemporalRange::fromSpan($otherSpan);
                return $temporalService->overlaps($currentRange, $otherRange);
            } catch (\Exception $e) {
                // If we can't create a temporal range, skip it
                return false;
            }
        })
        ->map(function($conn) use ($subject, $currentRange) {
            // For phase spans, use the phase span itself
            $isPhaseConnection = false;
            $phaseSpan = null;
            
            if (isset($conn->child) && $conn->child && $conn->child->type_id === 'phase') {
                $isPhaseConnection = true;
                $phaseSpan = $conn->child;
            } elseif (isset($conn->other_span) && $conn->other_span && $conn->other_span->type_id === 'phase') {
                $isPhaseConnection = true;
                $phaseSpan = $conn->other_span;
            }
            
            if ($isPhaseConnection && $phaseSpan) {
                $conn->other_span = $phaseSpan;
                $conn->connection_type = $conn->connection_type ?? $conn->type;
                $conn->is_subject_parent = true; // Phase is child of connection span
                $spanForRelation = $phaseSpan;
            } else {
                // Add the other span (the one that's not the subject)
                $isSubject = $conn->parent_id === $subject->id;
                $conn->other_span = $isSubject ? $conn->object : $conn->subject;
                $conn->connection_type = $conn->connection_type ?? $conn->type;
                $conn->is_subject_parent = $isSubject;
                $spanForRelation = $conn->connectionSpan;
            }
            
            // Determine the Allen temporal relation from the other span's perspective
            try {
                $otherRange = \App\Services\Temporal\TemporalRange::fromSpan($spanForRelation);
                $relation = $otherRange->getAllenRelation($currentRange);
                
                // For phase spans, if the phase is "during" the connection, the connection "contains" it
                $isPhase = ($conn->child && $conn->child->type_id === 'phase');
                
                // Handle precision edge case: if connection has year-only precision for end date
                // and current span has month/day precision ending in the same year, be more lenient
                // with "overlapped-by" -> treat as "during" since we can't know if it actually extends beyond
                if ($relation === 'overlapped-by' && $spanForRelation->end_year && !$spanForRelation->end_month) {
                    // Connection has year-only precision for end date
                    if ($span->end_year && ($span->end_month || $span->end_day)) {
                        // Current span has month/day precision
                        if ($spanForRelation->end_year === $span->end_year) {
                            // Same end year - assume they might end at the same time and map to "during"
                            $relation = 'during';
                        }
                    }
                }
                
                // Normalize relation names for display
                // Note: we compute from the connection span's perspective (otherRange->getAllenRelation(currentRange))
                // "overlaps" = connection starts before relationship, ends during it
                // "overlapped-by" = connection starts during relationship, ends after it - map to "starts" for clarity
                // "contains" = connection contains relationship, so from connection's perspective it's "contained by"
                // "during" = for phase spans, this means the connection "contains" the phase
                $relationMap = [
                    'before' => 'before',
                    'meets' => 'before',
                    'overlaps' => 'overlaps',
                    'overlapped-by' => 'starts', // Starts during relationship - more accurate than "overlaps"
                    'during' => $isPhase ? 'contains' : 'during', // Phase during connection = connection contains phase
                    'contains' => 'contained by', // Connection contains relationship, so connection is contained by it
                    'starts' => 'starts',
                    'started-by' => 'starts',
                    'finishes' => 'finishes',
                    'finished-by' => 'finishes',
                    'equals' => 'during',
                    'after' => 'after',
                    'met-by' => 'after',
                ];
                $conn->allen_relation = $relationMap[$relation] ?? $relation;
            } catch (\Exception $e) {
                $conn->allen_relation = 'overlaps';
            }
            
            return $conn;
        })
        ->sortBy(function($conn) {
            // Sort by start year, then start month, then start day
            // For phase spans, use the phase span itself
            $isPhase = false;
            $phaseSpan = null;
            
            if (isset($conn->child) && $conn->child && $conn->child->type_id === 'phase') {
                $isPhase = true;
                $phaseSpan = $conn->child;
            } elseif (isset($conn->other_span) && $conn->other_span && $conn->other_span->type_id === 'phase') {
                $isPhase = true;
                $phaseSpan = $conn->other_span;
            }
            
            $spanToSort = $isPhase ? $phaseSpan : $conn->connectionSpan;
            $year = $spanToSort->start_year ?? PHP_INT_MAX;
            $month = $spanToSort->start_month ?? PHP_INT_MAX;
            $day = $spanToSort->start_day ?? PHP_INT_MAX;
            return sprintf('%08d-%02d-%02d', $year, $month, $day);
        })
        ->values();

    // Don't show the card if there are no overlapping connections
    if ($allConnections->isEmpty()) {
        return;
    }
@endphp

<div class="card mb-4">
    <div class="card-header">
        <h6 class="card-title mb-0">
            <i class="bi bi-border-style me-2"></i>
            Time Relationships
        </h6>
    </div>
    <div class="card-body p-2">
        @php
            $relationCounts = $allConnections->groupBy('allen_relation')->map->count();
            $defaultRelation = $relationCounts->get('during') ? 'during' : $relationCounts->keys()->first();
            
            // Group by predicate for the second layer of tabs
            $predicateCounts = $allConnections->map(function($conn) {
                // Check if this is a phase connection
                $isPhase = false;
                if (isset($conn->child) && $conn->child && $conn->child->type_id === 'phase') {
                    $isPhase = true;
                } elseif (isset($conn->other_span) && $conn->other_span && $conn->other_span->type_id === 'phase') {
                    $isPhase = true;
                }
                
                if ($isPhase) {
                    return 'contains'; // Phase connections use "contains" predicate
                }
                
                $isSubjectParent = $conn->is_subject_parent ?? true;
                $connectionType = $conn->connection_type;
                return $isSubjectParent 
                    ? ($connectionType->forward_predicate ?? $connectionType->type ?? 'unknown')
                    : ($connectionType->inverse_predicate ?? $connectionType->type ?? 'unknown');
            })->countBy();
        @endphp
        <ul class="nav nav-tabs nav-tabs-sm small mb-2 temporal-relations-tabs" role="tablist">
            @foreach($relationCounts->keys() as $relation)
                <li class="nav-item" role="presentation">
                    <button class="nav-link {{ $relation === $defaultRelation ? 'active' : '' }}" type="button" data-relation-filter="{{ $relation }}">
                        {{ $relation }}
                    </button>
                </li>
            @endforeach
        </ul>
        @if($predicateCounts->count() > 1)
        <ul class="nav nav-tabs nav-tabs-sm small mb-2 temporal-relations-predicate-tabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" type="button" data-predicate-filter="all">
                    all
                </button>
            </li>
            @foreach($predicateCounts->keys()->sort() as $predicate)
                <li class="nav-item" role="presentation">
                    <button class="nav-link" type="button" data-predicate-filter="{{ $predicate }}">
                        {{ $predicate }}
                    </button>
                </li>
            @endforeach
        </ul>
        @endif
        <div class="list-group list-group-flush temporal-relations-list">
            @foreach($allConnections as $connection)
                @php
                    $otherSpan = $connection->other_span;
                    $connectionType = $connection->connection_type;
                    
                    // For phase spans, use the phase span itself
                    $isPhase = $otherSpan && $otherSpan->type_id === 'phase';
                    if ($isPhase) {
                        $spanForDisplay = $otherSpan;
                    } else {
                        $connectionSpan = $connection->connectionSpan;
                        // Skip if connection span doesn't exist
                        if (!$connectionSpan || !$otherSpan) {
                            continue;
                        }
                        $spanForDisplay = $connectionSpan;
                    }
                    
                    $hasDates = $spanForDisplay->start_year || $spanForDisplay->end_year;
                    $dateText = null;
                    if ($hasDates) {
                        if ($spanForDisplay->start_year && $spanForDisplay->end_year) {
                            $dateText = ($spanForDisplay->formatted_start_date ?? $spanForDisplay->start_year) . ' – ' . ($spanForDisplay->formatted_end_date ?? $spanForDisplay->end_year);
                        } elseif ($spanForDisplay->start_year) {
                            $dateText = 'from ' . ($spanForDisplay->formatted_start_date ?? $spanForDisplay->start_year);
                        } elseif ($spanForDisplay->end_year) {
                            $dateText = 'until ' . ($spanForDisplay->formatted_end_date ?? $spanForDisplay->end_year);
                        }
                    }
                    
                    // Determine the predicate direction
                    // For phase spans, use "contains" (connection span contains phase)
                    // is_subject_parent was set in the map function above
                    $isSubjectParent = $connection->is_subject_parent ?? true;
                    if ($isPhase) {
                        $predicate = 'contains';
                    } else {
                        $predicate = $isSubjectParent 
                            ? ($connectionType->forward_predicate ?? $connectionType->type)
                            : ($connectionType->inverse_predicate ?? $connectionType->type);
                    }
                @endphp
                <div class="list-group-item px-0 py-2 border-0 border-bottom" data-relation="{{ $connection->allen_relation }}" data-predicate="{{ $predicate }}">
                    <div class="small text-muted d-flex align-items-center flex-wrap">
                        @if($isPhase)
                            <x-span-link :span="$otherSpan" class="text-decoration-none" />
                        @else
                            <x-span-link :span="$subject" class="text-decoration-none" /><span class="mx-1">{{ $predicate }}</span><x-span-link :span="$otherSpan" class="text-decoration-none" />
                        @endif
                        @if($dateText)
                            <span class="ms-2">{{ $dateText }}</span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>