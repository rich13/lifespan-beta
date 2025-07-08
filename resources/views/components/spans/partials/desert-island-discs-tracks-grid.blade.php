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
        @endphp
        
        <a href="{{ route('spans.show', $track) }}" class="track-square text-decoration-none @if($album && $album->has_cover_art) has-cover-art @endif" 
           @if($album && $album->has_cover_art) style="background-image: url('{{ $album->cover_art_small_url }}')" @endif>
            <div class="track-number">{{ $loop->iteration }}</div>
            
            <div class="track-title">
                {{ $track->name }}
            </div>
            @if($artist)
                <div class="track-artist text-muted">{{ $artist->parent->name }}</div>
            @endif
            @if($album)
                <div class="track-album text-muted small">{{ $album->name }}</div>
            @endif
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