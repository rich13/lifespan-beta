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
    @if($sets->isEmpty())
        <div class="card">
            <div class="card-body">
                <div class="text-center py-5">
                    <i class="bi bi-vinyl-fill text-muted mb-3" style="font-size: 3rem;"></i>
                    <h3 class="text-muted">No Desert Island Discs sets found</h3>
                    <p class="text-muted">There are no Desert Island Discs sets available to view.</p>
                </div>
            </div>
        </div>
    @else
        <div class="row">
            @foreach($sets as $set)
                <div class="col-md-6 col-lg-3 mb-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title">
                                <a href="{{ route('sets.show', $set) }}" class="text-decoration-none">
                                    {{ $set->name }}
                                </a>
                            </h5>
                            
                            @php
                                $contents = $set->getSetContents();
                                $tracks = $contents->filter(function($item) {
                                    return $item->type_id === 'thing' && 
                                           ($item->metadata['subtype'] ?? null) === 'track';
                                });
                            @endphp
                            
                            <p class="card-text text-muted small">
                                {{ $tracks->count() }} tracks
                            </p>
                            
                            @if($tracks->count() > 0)
                                <div class="mt-2">
                                    <small class="text-muted">Sample tracks:</small>
                                    <ul class="list-unstyled small">
                                        @foreach($tracks->take(3) as $track)
                                            <li class="text-truncate">
                                                {{ $track->name }}
                                            </li>
                                        @endforeach
                                        @if($tracks->count() > 3)
                                            <li class="text-muted">... and {{ $tracks->count() - 3 }} more</li>
                                        @endif
                                    </ul>
                                </div>
                            @endif
                        </div>
                        <div class="card-footer">
                            <a href="{{ route('sets.show', $set) }}" class="btn btn-outline-primary btn-sm">
                                View Set
                            </a>
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