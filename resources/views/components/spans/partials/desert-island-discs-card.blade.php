@if($desertIslandDiscsSet)
<div class="card mb-4 desert-island-discs-card">
        <div class="card-body">
            <h2 class="card-title h5 mb-3">
            <a href="{{ route('sets.show', $desertIslandDiscsSet) }}" class="text-decoration-none">
                <i class="bi bi-disc-fill text-primary me-2"></i>
                Desert Island Discs
            </a>
            </h2>
            
            @php
                $contents = $desertIslandDiscsSet->getSetContents();
            @endphp
            
            @if($contents->isNotEmpty())
                <div class="mb-3">
                    <div class="set-preview-contents">
                        @foreach($contents->take(3) as $item)
                            @if($item->type_id === 'connection')
                                <x-unified.interactive-card :connection="$item" />
                            @else
                                <x-unified.interactive-card :span="$item" />
                            @endif
                        @endforeach
                        @if($contents->count() > 3)
                            <div class="text-center text-muted small mt-2">
                                <i class="bi bi-three-dots"></i>
                                and {{ $contents->count() - 3 }} more items
                            </div>
                        @endif
                    </div>
                </div>
                
            @else
                <div class="text-center py-3">
                    <i class="bi bi-music-note-beamed text-muted mb-2" style="font-size: 2rem;"></i>
                    <p class="text-muted small mb-0">No discs added yet</p>
                </div>
                
                @auth
                    <div class="d-grid">
                        <a href="{{ route('sets.show', $desertIslandDiscsSet) }}" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-plus-circle me-1"></i>
                            Add First Disc
                        </a>
                    </div>
                @endauth
            @endif
        </div>
    </div>
@endif 