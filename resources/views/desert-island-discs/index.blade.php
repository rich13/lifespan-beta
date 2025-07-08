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
                @php
                    $cacheKey = 'desert_island_discs_set_card_' . $set->id . '_' . ($set->updated_at ?? '0');
                @endphp
                {!! \Cache::remember($cacheKey, 300, function() use ($set) {
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