@props(['span', 'precomputedConnections' => null])

@php
    // Only show for film spans
    if ($span->type_id !== 'thing' || !isset($span->metadata['subtype']) || $span->metadata['subtype'] !== 'film') {
        return;
    }

    $user = auth()->user();

    // Director: [person][created][film]. Actors: [film][features][person].
    if ($precomputedConnections instanceof \App\Support\PrecomputedSpanConnections) {
        $directorConnection = $precomputedConnections->getChildByType('created')
            ->first(fn ($connection) => $connection->parent && $connection->parent->type_id === 'person');
        $actorConnections = $precomputedConnections->getParentByType('features')
            ->filter(fn ($connection) => $connection->child && $connection->child->type_id === 'person')
            ->values();
    } else {
        $directorConnection = $span->connectionsAsObjectWithAccess($user)
            ->whereHas('type', function($q) {
                $q->where('type_id', 'created');
            })
            ->whereHas('parent', function($q) {
                $q->where('type_id', 'person');
            })
            ->with(['parent'])
            ->first();

        $actorConnections = $span->connectionsAsSubjectWithAccess($user)
            ->whereHas('type', function($q) {
                $q->where('type_id', 'features');
            })
            ->whereHas('child', function($q) {
                $q->where('type_id', 'person');
            })
            ->with(['child'])
            ->get();
    }

    $currentFilmDirector = $directorConnection ? $directorConnection->parent : null;
    $directorId = $currentFilmDirector ? $currentFilmDirector->id : null;
    $actorIds = $actorConnections->pluck('child_id')->unique()->toArray();

    if (empty($actorIds) && !$directorId) {
        return;
    }

    $relatedFilms = \App\Support\SpanShowLookups::filmsRelatedByCastOrDirector($span, $actorIds, $directorId)
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
<div class="card mb-4" data-related-films-card>
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
                    
                    $director = $film->relationLoaded('connectionsAsObject')
                        ? $film->connectionsAsObject
                            ->first(fn ($connection) => $connection->type_id === 'created' && $connection->parent)
                            ?->parent
                        : $film->connectionsAsObject()
                            ->whereHas('type', function($q) { $q->where('type_id', 'created'); })
                            ->with('parent')
                            ->first()
                            ?->parent;
                    
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

