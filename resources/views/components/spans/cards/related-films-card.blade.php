@props(['span'])

@php
    // Only show for film spans
    if ($span->type_id !== 'thing' || !isset($span->metadata['subtype']) || $span->metadata['subtype'] !== 'film') {
        return;
    }

    $user = auth()->user();

    // Get the director of this film (if any)
    // Connection: [person][created][film]
    // So film is the child (object) and director is the parent (subject)
    $directorConnection = $span->connectionsAsObjectWithAccess($user)
        ->whereHas('type', function($q) { 
            $q->where('type_id', 'created'); 
        })
        ->whereHas('parent', function($q) { 
            $q->where('type_id', 'person'); 
        })
        ->with(['parent'])
        ->first();
    
    $currentFilmDirector = $directorConnection ? $directorConnection->parent : null;
    $directorId = $currentFilmDirector ? $currentFilmDirector->id : null;

    // Get all actors connected to this film via "features" connections
    // Connection: [film][features][person]
    // So film is the parent (subject) and actor is the child (object)
    $actorConnections = $span->connectionsAsSubjectWithAccess($user)
        ->whereHas('type', function($q) { 
            $q->where('type_id', 'features'); 
        })
        ->whereHas('child', function($q) { 
            $q->where('type_id', 'person'); 
        })
        ->with(['child'])
        ->get();

    // Get all actor IDs
    $actorIds = $actorConnections->pluck('child_id')->unique()->toArray();

    // If we have neither actors nor a director, don't show the component
    if (empty($actorIds) && !$directorId) {
        return;
    }

    // Build query for related films
    $relatedFilmsQuery = \App\Models\Span::where('type_id', 'thing')
        ->whereJsonContains('metadata->subtype', 'film')
        ->where('id', '!=', $span->id)
        ->where(function($q) use ($actorIds, $directorId) {
            // Films that share actors
            if (!empty($actorIds)) {
                $q->whereHas('connectionsAsSubject', function($subQ) use ($actorIds) {
                    $subQ->where('type_id', 'features')
                      ->whereIn('child_id', $actorIds)
                      ->whereHas('child', function($q2) {
                          $q2->where('type_id', 'person');
                      });
                });
            }
            
            // Films directed by the same director
            if ($directorId) {
                if (!empty($actorIds)) {
                    $q->orWhereHas('connectionsAsObject', function($subQ) use ($directorId) {
                        $subQ->where('type_id', 'created')
                          ->where('parent_id', $directorId)
                          ->whereHas('parent', function($q2) {
                              $q2->where('type_id', 'person');
                          });
                    });
                } else {
                    $q->whereHas('connectionsAsObject', function($subQ) use ($directorId) {
                        $subQ->where('type_id', 'created')
                          ->where('parent_id', $directorId)
                          ->whereHas('parent', function($q2) {
                              $q2->where('type_id', 'person');
                          });
                    });
                }
            }
        });

    // Apply access control
    if (!$user) {
        $relatedFilmsQuery->where('access_level', 'public');
    } else {
        if (!$user->is_admin) {
            $relatedFilmsQuery->where(function ($query) use ($user) {
                $query->where('access_level', 'public')
                    ->orWhere('owner_id', $user->id)
                    ->orWhere(function ($query) use ($user) {
                        $query->where('access_level', 'shared')
                            ->whereExists(function ($subquery) use ($user) {
                                $subquery->select('id')
                                    ->from('span_permissions')
                                    ->whereColumn('span_permissions.span_id', 'spans.id')
                                    ->where('span_permissions.user_id', $user->id);
                            });
                    });
            });
        }
    }

    $relatedFilms = $relatedFilmsQuery
        ->with([
            'connectionsAsSubject' => function($q) use ($actorIds) {
                if (!empty($actorIds)) {
                    $q->where('type_id', 'features')
                      ->whereIn('child_id', $actorIds)
                      ->with(['child:id,name']);
                }
            },
            'connectionsAsObject' => function($q) use ($directorId) {
                if ($directorId) {
                    $q->where('type_id', 'created')
                      ->where('parent_id', $directorId)
                      ->with(['parent:id,name']);
                }
            }
        ])
        ->get()
        ->map(function($film) use ($actorIds, $directorId) {
            // Check if related via actors
            $sharedActors = collect();
            if (!empty($actorIds)) {
                $sharedActors = $film->connectionsAsSubject
                    ->filter(function($conn) {
                        return $conn->type_id === 'features' && $conn->child && $conn->child->name;
                    })
                    ->pluck('child.name')
                    ->unique()
                    ->values();
            }
            
            // Check if related via director
            $sameDirector = false;
            if ($directorId) {
                $directorConn = $film->connectionsAsObject
                    ->where('type_id', 'created')
                    ->where('parent_id', $directorId)
                    ->first();
                $sameDirector = $directorConn !== null;
            }
            
            $film->shared_actors_count = $sharedActors->count();
            $film->shared_actors = $sharedActors;
            $film->same_director = $sameDirector;
            
            // Build related_via array first, then assign
            $relatedVia = [];
            if ($sharedActors->isNotEmpty()) {
                $relatedVia[] = 'actors';
            }
            if ($sameDirector) {
                $relatedVia[] = 'director';
            }
            $film->related_via = $relatedVia;
            
            return $film;
        })
        ->sortBy(function($film) {
            // Sort by release date (earliest first)
            if ($film->start_year) {
                return sprintf('%08d-%02d-%02d', 
                    $film->start_year, 
                    $film->start_month ?? 0, 
                    $film->start_day ?? 0
                );
            }
            // Films without dates go to the end
            return PHP_INT_MAX;
        })
        ->values()
        ->take(20); // Limit to 20 films
@endphp

@if($relatedFilms->isNotEmpty())
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="card-title mb-0">
            <i class="bi bi-film me-2"></i>
            Related Films
        </h6>
    </div>
    <div class="card-body p-2">
        <div class="list-group list-group-flush">
            @foreach($relatedFilms as $film)
                @php
                    // Format release date as human-readable and create link
                    $releaseDate = null;
                    $releaseDateLink = null;
                    if ($film->start_year) {
                        if ($film->start_year && $film->start_month && $film->start_day) {
                            // Full date format: March 12, 1984
                            $date = \Carbon\Carbon::createFromDate($film->start_year, $film->start_month, $film->start_day);
                            $releaseDate = $date->format('F j, Y');
                            $releaseDateLink = $date->format('Y-m-d');
                        } elseif ($film->start_year && $film->start_month) {
                            // Month and year format: January 2020
                            $date = \Carbon\Carbon::createFromDate($film->start_year, $film->start_month, 1);
                            $releaseDate = $date->format('F Y');
                            $releaseDateLink = $date->format('Y-m');
                        } else {
                            // Year only format: 1976
                            $releaseDate = (string)$film->start_year;
                            $releaseDateLink = (string)$film->start_year;
                        }
                    }
                    
                    // Get director if available
                    $director = null;
                    $directorConnection = $film->connectionsAsObject()
                        ->whereHas('type', function($q) { $q->where('type_id', 'created'); })
                        ->with('parent')
                        ->first();
                    if ($directorConnection) {
                        $director = $directorConnection->parent;
                    }
                    
                    // Get film poster/image if available
                    $metadata = $film->metadata ?? [];
                    $posterUrl = $metadata['thumbnail_url'] 
                        ?? $metadata['image_url'] 
                        ?? $metadata['poster_url'] 
                        ?? $metadata['medium_url'] 
                        ?? $metadata['large_url'] 
                        ?? null;
                @endphp
                <div class="list-group-item px-0 py-2 border-0 border-bottom">
                    <div class="d-flex align-items-center">
                        <!-- Poster/image on the left -->
                        <div class="me-3 flex-shrink-0">
                            @if($posterUrl)
                                <a href="{{ route('spans.show', $film) }}">
                                    <img src="{{ $posterUrl }}" 
                                         alt="{{ $film->name }}"
                                         class="rounded"
                                         style="width: 50px; height: 75px; object-fit: cover;"
                                         loading="lazy">
                                </a>
                            @else
                                <a href="{{ route('spans.show', $film) }}" 
                                   class="d-flex align-items-center justify-content-center bg-light rounded text-muted text-decoration-none"
                                   style="width: 50px; height: 75px;">
                                    <i class="bi bi-film"></i>
                                </a>
                            @endif
                        </div>
                        
                        <!-- Film name and details on the right -->
                        <div class="flex-grow-1">
                            <a href="{{ route('spans.show', $film) }}" 
                               class="text-decoration-none fw-semibold">
                                {{ $film->name }}
                            </a>
                            @if($releaseDate && $releaseDateLink)
                                <div class="text-muted small">
                                    <i class="bi bi-calendar me-1"></i>
                                    <a href="{{ route('date.explore', ['date' => $releaseDateLink]) }}" class="text-decoration-none">
                                        {{ $releaseDate }}
                                    </a>
                                </div>
                            @endif
                            @if($director)
                                <div class="text-muted small">
                                    <i class="bi bi-camera-reels me-1"></i>Directed by 
                                    <a href="{{ route('spans.show', $director) }}" class="text-decoration-none">
                                        {{ $director->name }}
                                    </a>
                                </div>
                            @endif
                            @php
                                $relationshipText = [];
                                if ($film->shared_actors && $film->shared_actors->isNotEmpty()) {
                                    if ($film->shared_actors->count() === 1) {
                                        $relationshipText[] = 'Also features ' . $film->shared_actors->first();
                                    } elseif ($film->shared_actors->count() <= 3) {
                                        $relationshipText[] = 'Also features ' . $film->shared_actors->join(', ', ' and ');
                                    } else {
                                        $relationshipText[] = 'Also features ' . $film->shared_actors->take(2)->join(', ') . ' and ' . ($film->shared_actors->count() - 2) . ' other' . ($film->shared_actors->count() - 2 !== 1 ? 's' : '');
                                    }
                                }
                                if ($film->same_director && $currentFilmDirector) {
                                    $relationshipText[] = 'Also directed by ' . $currentFilmDirector->name;
                                }
                            @endphp
                            @if(!empty($relationshipText))
                                <div class="text-muted small">
                                    @if(in_array('actors', $film->related_via))
                                        <i class="bi bi-people me-1"></i>
                                    @elseif($film->same_director)
                                        <i class="bi bi-camera-reels me-1"></i>
                                    @endif
                                    {{ implode('; ', $relationshipText) }}
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endif

