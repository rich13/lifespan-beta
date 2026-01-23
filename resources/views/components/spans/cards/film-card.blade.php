@props(['span'])

@php
    // Only show for person spans
    if ($span->type_id !== 'person') {
        return;
    }

    // Get connections where this person is featured in films
    // Connection type: [film][features][person]
    // So person is the object (child) of the connection
    $filmConnections = $span->connectionsAsObject()
        ->whereHas('type', function($q) { $q->where('type', 'features'); })
        ->with(['parent', 'connectionSpan'])
        ->get()
        ->filter(function($conn) {
            // Only include if parent is a film (type=thing, subtype=film)
            $film = $conn->parent;
            return $film && 
                   $film->type_id === 'thing' && 
                   isset($film->metadata['subtype']) && 
                   $film->metadata['subtype'] === 'film';
        })
        ->sortBy(function($conn) {
            // Sort by film release date (start_year of the film)
            $film = $conn->parent;
            if ($film && $film->start_year) {
                return sprintf('%08d-%02d-%02d', 
                    $film->start_year, 
                    $film->start_month ?? 0, 
                    $film->start_day ?? 0
                );
            }
            // Push films without dates to the end
            return sprintf('%08d-%02d-%02d', PHP_INT_MAX, 0, 0);
        })
        ->values();
@endphp

@if($filmConnections->isNotEmpty())
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="card-title mb-0">
            <i class="bi bi-film me-2"></i>
            <a href="{{ url('/spans/' . $span->id . '/films') }}" class="text-decoration-none">
                Films
            </a>
        </h6>
    </div>
    <div class="card-body p-2">
        <div class="list-group list-group-flush">
            @foreach($filmConnections as $connection)
                @php
                    $film = $connection->parent;
                    
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
                        ->whereHas('type', function($q) { $q->where('type', 'created'); })
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
                            <x-span-link :span="$film" class="text-decoration-none fw-semibold" />
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
                                    <x-span-link :span="$director" class="text-decoration-none" />
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

