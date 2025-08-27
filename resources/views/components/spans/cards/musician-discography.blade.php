@php
    // Get the musician's albums (discography)
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
        <i class="bi bi-music-note-beamed me-2"></i>Discography
    </div>
    <div class="card-body">
        <div class="row g-3">
            @foreach($albums as $album)
                <div class="col-6 col-md-3 col-lg-3">
                    <a href="{{ route('spans.show', $album['id']) }}" class="text-decoration-none">
                        <div class="ratio ratio-1x1" style="background: #f8f9fa; border-radius: 8px; overflow: hidden;">
                            @if($album['has_cover_art'] && $album['cover_art_small_url'])
                                <img src="{{ $album['cover_art_small_url'] }}" alt="{{ $album['name'] }} cover" class="img-fluid object-fit-cover w-100 h-100" style="object-fit: cover;" loading="lazy">
                            @else
                                <div class="d-flex align-items-center justify-content-center h-100">
                                    <i class="bi bi-music-note-beamed text-muted" style="font-size: 2rem;"></i>
                                </div>
                            @endif
                        </div>
                        @if($album['year'])
                            <div class="text-center mt-1">
                                <small class="text-muted">{{ $album['year'] }}</small>
                            </div>
                        @endif
                    </a>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endif
