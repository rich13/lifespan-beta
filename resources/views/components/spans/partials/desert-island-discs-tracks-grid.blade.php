@props(['tracks'])

<div class="tracks-grid">
    @foreach($tracks as $track)
        @php
            $album = $track->cached_album ?? null;
            $albumCreator = $track->cached_album_creator ?? null;
        @endphp

        <a href="{{ route('spans.show', $track) }}" class="track-square text-decoration-none @if($album && $album->has_cover_art) has-cover-art @endif">
            @if($album)
                <x-spans.cover-art :album="$album" size="small" variant="track" :alt="$album->name . ' cover'" />
            @endif
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