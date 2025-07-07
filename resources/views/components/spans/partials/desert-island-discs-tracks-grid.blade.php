@props(['tracks'])

<div class="tracks-grid">
    @foreach($tracks as $track)
        @php
            // Get the artist for this track
            $artist = $track->connectionsAsObject()
                ->whereHas('type', function($q) {
                    $q->where('type', 'created');
                })
                ->whereHas('parent', function($q) {
                    $q->whereIn('type_id', ['person', 'band']);
                })
                ->with('parent')
                ->first();
            
            // Get the album that contains this track
            $album = $track->getContainingAlbum();
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