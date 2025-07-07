@extends('layouts.app')

@section('page_title')
    <x-breadcrumb :items="[
        [
            'text' => 'Desert Island Discs',
            'icon' => 'vinyl-fill',
            'icon_category' => 'bootstrap'
        ]
    ]" />
@endsection

@section('page_filters')
    <!-- No filters needed for this page -->
@endsection

@section('page_tools')
    <!-- Page-specific tools can be added here -->
@endsection

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-md-12 mb-4">
            <div class="card">
                <div class="card-body bg-warning-subtle">
                    <div class="text-center py-3">
                        <i class="bi bi-vinyl-fill text-muted mb-3" style="font-size: 3rem;"></i>
                        <h3>This is a work in progress</h3>
                        <p class="text-muted">And yes, everything is a span.</p>
                    </div>
                </div>
            </div>
        </div>
    
    @if($sets->isEmpty())
        <div class="card">
            <div class="card-body">
                <div class="text-center py-5">
                    <i class="bi bi-disc-fill text-muted mb-3" style="font-size: 3rem;"></i>
                    <h3 class="text-muted">No Desert Island Discs sets found</h3>
                    <p class="text-muted">There are no Desert Island Discs sets available to view.</p>
                </div>
            </div>
        </div>
    @else
        <div class="row">
            @foreach($sets as $set)
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card h-100 desert-island-discs-tracks-card">
                        <div class="card-body">
                            <h5 class="card-title">
                                <a href="{{ route('spans.show', $set) }}" class="text-decoration-none">
                                    <i class="bi bi-disc-fill text-primary me-2"></i>
                                    {{ $set->name }}
                                </a>
                            </h5>
                            
                            @php
                                $contents = $set->getSetContents();
                            @endphp
                            
                            @php
                                $tracks = $contents->filter(function($item) {
                                    return $item->type_id === 'thing' && 
                                           ($item->metadata['subtype'] ?? null) === 'track';
                                });
                            @endphp
                            
                            @if($tracks->isNotEmpty())
                                <div class="tracks-grid">
                                    @foreach($tracks->take(8) as $track)
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
            @endforeach
        </div>
        
        <div class="mt-4">
            <x-pagination :paginator="$sets->appends(request()->query())" />
        </div>
    @endif
</div>
@endsection 