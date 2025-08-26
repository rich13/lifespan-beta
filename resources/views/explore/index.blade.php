@extends('layouts.app')

@section('title', 'Explore - Lifespan')

@section('page_title')
    <x-breadcrumb :items="[
        [
            'text' => 'Explore',
            'icon' => 'view',
            'icon_category' => 'action'
        ]
    ]" />
@endsection
@section('content')
<div class="container py-4">

    <div class="row g-4">

    <div class="col-md-6 col-lg-4">
            <div class="card h-100 shadow-sm">
                <div class="card-body text-center">
                    <div class="mb-3">
                        <i class="bi bi-bar-chart-steps display-4 text-dark"></i>
                    </div>
                    <h5 class="card-title">All spans</h5>
                    <p class="card-text">Browse <strong>all the spans</strong> in Lifespan... in a really massive and unhelpful list.</p>
                    <a href="{{ route('spans.index') }}" class="btn btn-dark">
                        <i class="bi bi-arrow-right me-2"></i>
                        Explore Spans
                    </a>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-lg-4">
            <div class="card h-100 shadow-sm">
                <div class="card-body text-center">
                    <div class="mb-3">
                        <i class="bi bi-collection display-4 text-warning"></i>
                    </div>
                    <h5 class="card-title">Span Types</h5>
                    <p class="card-text">Browse the different types of span and their subtypes... although it's pretty basic.</p>
                    <a href="{{ route('spans.types') }}" class="btn btn-warning">
                        <i class="bi bi-arrow-right me-2"></i>
                        Explore Types
                    </a>
                </div>
            </div>
        </div>

        <!-- Desert Island Discs -->
        <div class="col-md-6 col-lg-4">
            <div class="card h-100 shadow-sm">
                <div class="card-body text-center">
                    <div class="mb-3">
                        <i class="bi bi-vinyl-fill display-4 text-primary"></i>
                    </div>
                    <h5 class="card-title">Desert Island Discs</h5>
                    <p class="card-text">By importing the Desert Island Discs data, we have a way to explore the links between people and music...</p>
                    <a href="{{ route('explore.desert-island-discs') }}" class="btn btn-primary">
                        <i class="bi bi-arrow-right me-2"></i>
                        Explore DID
                    </a>
                </div>
            </div>
        </div>

        <!-- Plaques -->
        <div class="col-md-6 col-lg-4">
            <div class="card h-100 shadow-sm">
                <div class="card-body text-center">
                    <div class="mb-3">
                        <i class="bi bi-geo-alt display-4 text-success"></i>
                    </div>
                    <h5 class="card-title">London Plaques</h5>
                    <p class="card-text">The London Blue Plaques data is available as open data, and it's been imported into Lifespan...</p>
                    <a href="{{ route('explore.plaques') }}" class="btn btn-success">
                        <i class="bi bi-arrow-right me-2"></i>
                        Explore Plaques
                    </a>
                </div>
            </div>
        </div>

        <!-- Family -->
        <div class="col-md-6 col-lg-4">
            <div class="card h-100 shadow-sm">
                <div class="card-body text-center">
                    <div class="mb-3">
                        <i class="bi bi-people-fill display-4 text-info"></i>
                    </div>
                    <h5 class="card-title">Your Family</h5>
                    <p class="card-text">As you add more connections, you can explore your own family tree...</p>
                    @if(auth()->check() && auth()->user()->personalSpan)
                        <a href="{{ route('family.show', auth()->user()->personalSpan) }}" class="btn btn-info">
                            <i class="bi bi-arrow-right me-2"></i>
                            Explore Family
                        </a>
                    @else
                        <span class="text-muted small">Sign in to explore your family tree</span>
                    @endif
                </div>
            </div>
        </div>

        <!-- Journeys -->
        <div class="col-md-6 col-lg-4">
            <div class="card h-100 shadow-sm">
                <div class="card-body text-center">
                    <div class="mb-3">
                        <i class="bi bi-arrow-right-circle display-4 text-warning"></i>
                    </div>
                    <h5 class="card-title">Journeys</h5>
                    <p class="card-text">Discover connection paths between people, a bit like "6 degrees of Kevin Bacon".</p>
                    <a href="{{ route('explore.journeys') }}" class="btn btn-warning">
                        <i class="bi bi-arrow-right me-2"></i>
                        Explore Journeys
                    </a>
                </div>
            </div>
        </div>

        <!-- At Your Age -->
        <div class="col-md-6 col-lg-4">
            <div class="card h-100 shadow-sm">
                <div class="card-body text-center">
                    <div class="mb-3">
                        <i class="bi bi-arrow-left-right display-4 text-secondary"></i>
                    </div>
                    <h5 class="card-title">At Your Age</h5>
                    <p class="card-text">Discover what historical figures were doing when they were your current age.</p>
                    @if(auth()->check() && auth()->user()->personalSpan)
                        <a href="{{ route('explore.at-your-age') }}" class="btn btn-secondary">
                            <i class="bi bi-arrow-right me-2"></i>
                            Explore At Your Age
                        </a>
                    @else
                        <span class="text-muted small">Sign in with a personal span to explore</span>
                    @endif
                </div>
            </div>
        </div>

        <!-- Coming Soon -->
        <div class="col-md-6 col-lg-4">
            <div class="card h-100 shadow-sm border-dashed">
                <div class="card-body text-center">
                    <div class="mb-3">
                        <i class="bi bi-plus-circle display-4 text-muted"></i>
                    </div>
                    <h5 class="card-title text-muted">More Coming Soon</h5>
                </div>
            </div>
        </div>
    </div>

    
</div>

<style>
.border-dashed {
    border-style: dashed !important;
}
</style>
@endsection