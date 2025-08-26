@extends('layouts.app')

@section('title', 'At Your Age - Explore Lifespan')

@section('page_title')
    <x-breadcrumb :items="[
        [
            'text' => 'Explore',
            'icon' => 'view',
            'icon_category' => 'action',
            'url' => route('explore.index')
        ],
        [
            'text' => 'At Your Age',
            'icon' => 'arrow-left-right',
            'icon_category' => 'action'
        ]
    ]" />
@endsection

@section('content')
<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12 px-4">
            <div class="text-center mb-5">
                <h1 class="display-4 mb-3">At Your Age...</h1>
                <p class="lead text-muted">
                    You are {{ $age->y }} years, {{ $age->m }} months, and {{ $age->d }} days old.
                    Here's what others were doing when they were this age...
                </p>
            </div>
        </div>
    </div>

    @if(empty($randomComparisons))
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body text-center">
                        <i class="bi bi-search display-4 text-muted mb-3"></i>
                        <h3>No comparisons found</h3>
                        <p class="text-muted">
                            We couldn't find enough historical figures with sufficient data who were alive at your current age.
                            This might be because you're very young, or we need more historical data in the system.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    @else
        <div class="row g-4 px-3">
            @foreach($randomComparisons as $comparison)
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <h3 class="h6 mb-0">
                                <i class="bi bi-person-circle text-primary me-2"></i>
                                {{ $comparison['span']->name }}
                            </h3>
                        </div>
                        <div class="card-body">
                            @php
                                $comparisonDateObj = (object)[
                                    'year' => $comparison['date']->year,
                                    'month' => $comparison['date']->month,
                                    'day' => $comparison['date']->day,
                                ];
                                
                                // Check if the date is in the future
                                $isFutureDate = $comparison['date']->isFuture();
                            @endphp
                            
                            @if($isFutureDate)
                                <div class="alert alert-info small">
                                    <i class="bi bi-info-circle me-1"></i>
                                    This date is in the future ({{ $comparisonDateObj->year }}), so no historical connections are available yet.
                                </div>
                            @else
                                <div class="mb-3">
                                    <x-spans.display.statement-card 
                                        :span="$comparison['span']" 
                                        eventType="custom"
                                        :eventDate="$comparison['date']->format('Y-m-d')"
                                        customEventText="was your age on" />
                                </div>

                                <x-spans.display.connections-at-date 
                                    :span="$comparison['span']" 
                                    :date="$comparisonDateObj" />
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
