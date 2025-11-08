@extends('layouts.app')

@section('page_title')
    <x-breadcrumb :items="[
        [
            'text' => 'Explore',
            'icon' => 'view',
            'icon_category' => 'action',
            'url' => route('explore.index')
        ],
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
                    <i class="bi bi-disc-fill text-muted mb-3" style="font-size: 3rem;"></i>
                    <h3 class="text-muted">No Desert Island Discs sets found</h3>
                    <p class="text-muted">There are no Desert Island Discs sets available to view.</p>
                </div>
            </div>
        </div>
    @else
        <div class="row">
            @foreach($sets as $set)
                @php
                    // Include layout version in cache key to force refresh when layout changes
                    $cacheKey = 'desert_island_discs_set_card_v2_' . $set->id . '_' . ($set->updated_at ?? '0');
                @endphp
                {!! \Cache::remember($cacheKey, 604800, function() use ($set) {
                    return view('components.spans.partials.desert-island-discs-set-card', ['set' => $set])->render();
                }) !!}
            @endforeach
        </div>
        
        <div class="mt-4">
            <x-pagination :paginator="$sets->appends(request()->query())" />
        </div>
    @endif
</div>
@endsection 