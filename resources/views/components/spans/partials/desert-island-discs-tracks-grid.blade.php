@props(['tracks'])

<div class="tracks-grid">
    @foreach($tracks as $track)
        @php
            // Use pre-loaded artist data if available, otherwise fall back to query
            $artist = null;
            if (isset($track->connectionsAsObject) && $track->connectionsAsObject->isNotEmpty()) {
                $artist = $track->connectionsAsObject->first();
            } else {
                // Fallback to query if not pre-loaded
                $artist = $track->connectionsAsObject()
                    ->whereHas('type', function($q) {
                        $q->where('type', 'created');
                    })
                    ->whereHas('parent', function($q) {
                        $q->whereIn('type_id', ['person', 'band']);
                    })
                    ->with('parent')
                    ->first();
            }
            
            // Use pre-loaded album data if available, otherwise fall back to query
            $album = $track->cached_album ?? $track->getContainingAlbum();
            
            // Get album creator (artist/band) from the album's "created" connection
            $albumCreator = null;
            if ($album) {
                $albumCreator = $album->connectionsAsObject()
                    ->whereHas('type', function($q) {
                        $q->where('type', 'created');
                    })
                    ->whereHas('parent', function($q) {
                        $q->whereIn('type_id', ['person', 'band']);
                    })
                    ->with('parent')
                    ->first()
                    ?->parent;
            }
        @endphp
        
        <a href="{{ route('spans.show', $track) }}" class="track-square text-decoration-none @if($album && $album->has_cover_art) has-cover-art @endif" 
           @if($album && $album->has_cover_art) style="background-image: url('{{ $album->cover_art_small_url }}')" @endif>
            <div class="track-number">{{ $loop->iteration }}</div>
            
            {{-- Track and artist name badges at bottom (like photo dates) --}}
            <div class="position-absolute bottom-0 start-50 translate-middle-x mb-1" style="width: calc(100% - 0.5rem); text-align: center; display: flex; flex-direction: column; gap: 2px; pointer-events: none;">
                @if($track->name)
                    <span class="badge bg-dark bg-opacity-75 text-white" 
                          style="font-size: 0.65rem; backdrop-filter: blur(4px); max-width: 100%; display: inline-block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; pointer-events: auto;"
                          title="{{ $track->name }}">
                        {{ $track->name }}
                    </span>
                @endif
                @if($albumCreator && $albumCreator->name)
                    <span class="badge bg-dark bg-opacity-75 text-white" 
                          style="font-size: 0.6rem; backdrop-filter: blur(4px); max-width: 100%; display: inline-block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; pointer-events: auto;"
                          title="{{ $albumCreator->name }}">
                        {{ $albumCreator->name }}
                    </span>
                @endif
            </div>
        </a>
    @endforeach
    
    {{-- Fill remaining squares if less than 8 tracks --}}
    @for($i = $tracks->count() + 1; $i <= 8; $i++)
        <div class="track-square empty">
            <div class="track-number">{{ $i }}</div>
            <div class="track-title text-muted">Empty</div>
        </div>
    @endfor
</div> 