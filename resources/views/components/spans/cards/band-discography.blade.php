@props(['span', 'precomputedConnections' => null])

@php
    if ($span->type_id !== 'band') {
        return;
    }

    if ($precomputedConnections instanceof \App\Support\PrecomputedSpanConnections) {
        $albums = $precomputedConnections->createdAlbumSpans();
    } else {
        $albums = $span->connectionsAsSubject()
            ->where('type_id', 'created')
            ->whereHas('child', function ($query) {
                $query->where('type_id', 'thing')
                    ->where('metadata->subtype', 'album');
            })
            ->with(['child'])
            ->get()
            ->map(fn ($connection) => $connection->child)
            ->filter();
    }
@endphp
@if($albums->isNotEmpty())
<div class="card mb-4" data-band-discography>
    <div class="card-header fw-bold">
        Discography
    </div>
    <div class="card-body">
        <div class="row g-3">
            @foreach($albums as $album)
                <div class="col-6 col-md-3 col-lg-3">
                    <div class="position-relative">
                        <a href="{{ route('spans.show', $album) }}" class="text-decoration-none">
                            <div class="ratio ratio-1x1 cover-art-frame">
                                <x-spans.cover-art :album="$album" size="small" variant="square" :alt="$album->name . ' cover'" />
                            </div>
                        </a>
                        
                        {{-- Album name badge at bottom (like photo dates) --}}
                        @if($album->name)
                            <div class="position-absolute bottom-0 start-50 translate-middle-x mb-2 cover-art-caption">
                                <a href="{{ route('spans.show', $album) }}" class="badge bg-dark bg-opacity-75 text-white text-decoration-none cover-art-caption-badge"
                                   title="{{ $album->name }}">
                                    {{ $album->name }}
                                </a>
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endif
