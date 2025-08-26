@extends('layouts.app')

@section('title', 'Explore Family - Lifespan')

@section('page_title')
    <x-breadcrumb :items="[
        [
            'text' => 'Explore',
            'icon' => 'view',
            'icon_category' => 'action',
            'url' => route('explore.index')
        ],
        [
            'text' => 'Family',
            'icon' => 'people-fill',
            'icon_category' => 'bootstrap'
        ]
    ]" />
@endsection

@section('content')
<div class="container py-4">
    <div class="row">
        <div class="col-12">
            <div class="text-center mb-5">
                <h1 class="display-4 mb-3">Explore Family Connections</h1>
                <p class="lead text-muted">Discover and visualize the connections that bind families together</p>
            </div>
        </div>
    </div>

    @if($personalSpan)
        <div class="row g-4">
            <!-- Your Family Tree -->
            <div class="col-md-6">
                <div class="card h-100 shadow-sm">
                    <div class="card-body text-center">
                        <div class="mb-3">
                            <i class="bi bi-diagram-3 display-4 text-primary"></i>
                        </div>
                        <h5 class="card-title">Your Family Tree</h5>
                        <p class="card-text">Explore your personal family tree and discover connections between your family members.</p>
                        <a href="{{ route('family.show', $personalSpan) }}" class="btn btn-primary">
                            <i class="bi bi-arrow-right me-2"></i>
                            View My Family Tree
                        </a>
                    </div>
                </div>
            </div>

            <!-- Family Search -->
            <div class="col-md-6">
                <div class="card h-100 shadow-sm">
                    <div class="card-body text-center">
                        <div class="mb-3">
                            <i class="bi bi-search display-4 text-success"></i>
                        </div>
                        <h5 class="card-title">Search Family Connections</h5>
                        <p class="card-text">Search for family members and explore how they're connected to you and each other.</p>
                        <a href="{{ route('spans.search') }}" class="btn btn-success">
                            <i class="bi bi-arrow-right me-2"></i>
                            Search Family
                        </a>
                    </div>
                </div>
            </div>
        </div>
    @else
        <div class="row">
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-body text-center">
                        <div class="mb-3">
                            <i class="bi bi-person-plus display-4 text-info"></i>
                        </div>
                        <h5 class="card-title">Start Your Family Tree</h5>
                        <p class="card-text">Create your personal span and start building your family tree to explore connections.</p>
                        <a href="{{ route('family.index') }}" class="btn btn-info">
                            <i class="bi bi-arrow-right me-2"></i>
                            Create Family Tree
                        </a>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Family Features -->
    <div class="row mt-5">
        <div class="col-12">
            <h3 class="mb-4">Family Exploration Features</h3>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title">
                        <i class="bi bi-diagram-3 text-primary me-2"></i>
                        Family Trees
                    </h5>
                    <p class="card-text">Visualize family relationships with interactive family trees that show connections between generations.</p>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title">
                        <i class="bi bi-people text-success me-2"></i>
                        Relationship Mapping
                    </h5>
                    <p class="card-text">Discover how family members are connected through marriages, births, and other relationships.</p>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title">
                        <i class="bi bi-calendar-event text-info me-2"></i>
                        Life Events
                    </h5>
                    <p class="card-text">Track important family events like births, marriages, and other significant milestones.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Back to Explore -->
    <div class="row mt-5">
        <div class="col-12 text-center">
            <a href="{{ route('explore.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-2"></i>
                Back to Explore
            </a>
        </div>
    </div>
</div>
@endsection
