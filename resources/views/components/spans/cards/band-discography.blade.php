@php
    // Get the band's albums (discography)
    $albums = $span->connectionsAsSubject()
        ->where('type_id', 'created')
        ->whereHas('child', function ($query) {
            $query->where('type_id', 'thing')
                  ->where('metadata->subtype', 'album');
        })
        ->with(['child'])
        ->get()
        ->map(function ($connection) {
            $album = $connection->child;
            return [
                'name' => $album->name,
                'year' => $album->start_year,
                'has_cover_art' => $album->has_cover_art ?? false,
                'cover_art_small_url' => $album->cover_art_small_url ?? null,
                'id' => $album->id,
            ];
        });
@endphp
@if($albums->isNotEmpty())
<div class="card mb-4">
    <div class="card-header fw-bold">
        Discography
    </div>
    <div class="card-body">
        <div class="d-flex flex-row overflow-auto gap-3">
            @foreach($albums as $album)
                <a href="{{ route('spans.show', $album['id']) }}" class="text-decoration-none text-center">
                    <div style="width: 120px;">
                        <div class="ratio ratio-1x1 mb-2" style="background: #f8f9fa; border-radius: 8px; overflow: hidden;">
                            @if($album['has_cover_art'] && $album['cover_art_small_url'])
                                <img src="{{ $album['cover_art_small_url'] }}" alt="{{ $album['name'] }} cover" class="img-fluid object-fit-cover w-100 h-100" style="object-fit: cover;" loading="lazy">
                            @else
                                <div class="d-flex align-items-center justify-content-center h-100">
                                    <i class="bi bi-music-note-beamed text-muted" style="font-size: 2rem;"></i>
                                </div>
                            @endif
                        </div>
                        <div class="small fw-semibold text-dark">{{ $album['name'] }}</div>
                        <div class="text-muted small">{{ $album['year'] }}</div>
                    </div>
                </a>
            @endforeach
        </div>
    </div>
</div>
@endif 