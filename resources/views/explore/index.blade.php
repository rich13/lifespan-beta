@extends('layouts.app')

@section('title', 'Explore - Lifespan')

@section('content')
<div class="container py-4">
    <div class="row">
        <div class="col-12">
            <div class="text-center mb-5">
                <h1 class="display-4 mb-3">Explore</h1>
                <p class="lead text-muted">Some ideas for exploring different sides of Lifespan</p>
            </div>
        </div>
    </div>

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
                    <p class="card-text">Discover fascinating connection paths between people through multiple degrees of separation.</p>
                    <a href="{{ route('explore.journeys') }}" class="btn btn-warning">
                        <i class="bi bi-arrow-right me-2"></i>
                        Explore Journeys
                    </a>
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
b
 oly