@if($desertIslandDiscsSet)
<div class="card mb-4 desert-island-discs-tracks-card">
    <div class="card-body">
        <h2 class="card-title h5 mb-3">
            <a href="{{ route('spans.show', $desertIslandDiscsSet) }}" class="text-decoration-none">
                <i class="bi bi-vinyl-fill text-primary me-2"></i>
                Desert Island Discs
            </a>
        </h2>
        
        @php
            $contents = $desertIslandDiscsSet->getSetContents();
            $tracks = $contents->filter(function($item) {
                return $item->type_id === 'thing' && 
                       ($item->metadata['subtype'] ?? null) === 'track';
            });
        @endphp
        
        @if($tracks->isNotEmpty())
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
                    @endphp
                    
                    <a href="{{ route('spans.show', $track) }}" class="track-square text-decoration-none">
                        <div class="track-number">{{ $loop->iteration }}</div>
                        <div class="track-title">
                            {{ $track->name }}
                        </div>
                        @if($artist)
                            <div class="track-artist text-muted">{{ $artist->parent->name }}</div>
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
            
            <!-- <div class="mt-3 text-center">
                <a href="{{ route('sets.show', $desertIslandDiscsSet) }}" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-collection me-1"></i>
                    View Full Set
                </a>
            </div> -->
            
        @else
            <div class="text-center py-3">
                <i class="bi bi-music-note-beamed text-muted mb-2" style="font-size: 2rem;"></i>
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