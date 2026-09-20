@props(['span', 'desertIslandDiscsSet' => null, 'desertIslandDiscsTracks' => null])

@if($desertIslandDiscsSet)
<div class="card mb-4 desert-island-discs-tracks-card" data-desert-island-discs-card>
    <div class="card-header">
        <h6 class="card-title mb-0">
            <a href="{{ route('spans.show', $desertIslandDiscsSet) }}" class="text-decoration-none">
                <i class="bi bi-vinyl-fill text-primary me-2"></i>
                Desert Island Discs
            </a>
        </h6>
    </div>
    <div class="card-body">
        @php
            $tracks = $desertIslandDiscsTracks;
            if ($tracks === null) {
                $tracks = $desertIslandDiscsSet->getSetContents()->filter(function ($item) {
                    return $item->type_id === 'thing' &&
                           ($item->metadata['subtype'] ?? null) === 'track';
                });
            }
        @endphp

        @if($tracks->isNotEmpty())
            <x-spans.partials.desert-island-discs-tracks-grid :tracks="$tracks" />
        @else
            <div class="text-center py-3">
                <i class="bi bi-music-note-beamed text-muted mb-2 did-empty-icon"></i>
                <p class="text-muted small mb-0">No tracks added yet</p>
            </div>

            @auth
                <div class="d-grid">
                    <a href="{{ route('sets.show', $desertIslandDiscsSet) }}" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-plus-circle me-1"></i>
                        Add First Track
                    </a>
                </div>
            @endauth
        @endif
    </div>
</div>
@endif
