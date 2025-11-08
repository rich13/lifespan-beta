<div class="col-md-6 col-lg-3 mb-4">
    <div class="card h-100 desert-island-discs-tracks-card">
        <div class="card-body">
            <h5 class="card-title">
                <a href="{{ route('spans.show', $set) }}" class="text-decoration-none">
                    <i class="bi bi-disc-fill text-primary me-2"></i>
                    {{ $set->name }}
                </a>
            </h5>
            @php
                $tracks = $set->preloaded_tracks ?? collect();
            @endphp
            @if($tracks->isNotEmpty())
                <x-spans.partials.desert-island-discs-tracks-grid :tracks="$tracks->take(8)" />
                @if($tracks->count() > 8)
                    <div class="text-center text-muted small mt-2">
                        <i class="bi bi-three-dots"></i>
                        and {{ $tracks->count() - 8 }} more tracks
                    </div>
                @endif
            @else
                <div class="text-center py-3">
                    <i class="bi bi-music-note-beamed text-muted mb-2" style="font-size: 2rem;"></i>
                    <p class="text-muted small mb-0">No tracks added yet</p>
                </div>
            @endif
        </div>
    </div>
</div> 