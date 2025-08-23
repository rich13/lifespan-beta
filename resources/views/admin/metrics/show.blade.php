@extends('layouts.app')

@section('page_title')
    Span Completeness Details - {{ $span->name }}
@endsection

@section('content')
<div class="py-4">
    <div class="row">
        <div class="col-12 d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1>Span Completeness Details</h1>
                    <p class="text-muted">{{ $span->name }} ({{ ucfirst($span->type_id) }})</p>
                </div>
                <div>
                    <a href="{{ route('spans.show', $span) }}" class="btn btn-outline-primary">
                        <i class="fas fa-eye"></i> View Span
                    </a>
                    <a href="{{ route('admin.metrics.index') }}" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Metrics
                    </a>
                </div>
            </div>

            <!-- Overall Score Card -->


            <!-- Detailed Metrics -->
            <div class="row">
                <!-- Residence Completeness Only -->
                @if($metrics['residence_completeness'])
                    <div class="col-12 mb-4">
                        <div class="card h-100">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-map-marker-alt text-success"></i>
                                    Residence Completeness ({{ $metrics['residence_completeness']['percentage'] }}%)
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="progress mb-3" style="height: 25px;">
                                    <div class="progress-bar bg-success" style="width: {{ $metrics['residence_completeness']['percentage'] }}%">
                                        {{ $metrics['residence_completeness']['percentage'] }}%
                                    </div>
                                </div>
                                <!-- Existing residence details below -->
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            <!-- Residence Completeness (for person spans) -->
            @if($metrics['residence_completeness'])
                <div class="row">
                    <div class="col-12 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-map-marker-alt text-success"></i>
                                    Residence Completeness ({{ $metrics['residence_completeness']['percentage'] }}%)
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="progress mb-3" style="height: 25px;">
                                    <div class="progress-bar bg-success" style="width: {{ $metrics['residence_completeness']['percentage'] }}%">
                                        {{ $metrics['residence_completeness']['percentage'] }}%
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <h6>Lifespan Coverage</h6>
                                        <p>
                                            <strong>Lifespan:</strong> {{ $metrics['residence_completeness']['lifespan_range']['start_year'] }} - 
                                            {{ $metrics['residence_completeness']['lifespan_range']['end_year'] }}
                                            ({{ $metrics['residence_completeness']['lifespan_range']['duration_years'] }} years)
                                        </p>
                                        <p>
                                            <strong>Covered:</strong> {{ $metrics['residence_completeness']['details']['coverage']['covered_years'] ?? 0 }} of 
                                            {{ $metrics['residence_completeness']['details']['coverage']['total_years'] ?? 0 }} years
                                        </p>
                                        <p>
                                            <strong>Gaps:</strong> {{ $metrics['residence_completeness']['details']['gaps']['count'] ?? 0 }} years
                                            @if(($metrics['residence_completeness']['details']['gaps']['largest_gap'] ?? 0) > 0)
                                                (largest: {{ $metrics['residence_completeness']['details']['gaps']['largest_gap'] }} years)
                                            @endif
                                        </p>
                                    </div>
                                    <div class="col-md-6">
                                        <h6>Residence Periods</h6>
                                        @if(!empty($metrics['residence_completeness']['residence_periods']))
                                            <div class="list-group list-group-flush">
                                                @foreach($metrics['residence_completeness']['residence_periods'] as $period)
                                                    <div class="list-group-item d-flex justify-content-between align-items-center">
                                                        <div>
                                                            <strong>{{ $period['place'] }}</strong><br>
                                                            <small class="text-muted">
                                                                {{ $period['start_year'] }} - {{ $period['end_year'] }}
                                                                ({{ $period['duration'] }} years)
                                                            </small>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @else
                                            <p class="text-muted">No residence periods found.</p>
                                        @endif
                                    </div>
                                </div>

                                @if(!empty($metrics['residence_completeness']['details']['gaps']['years']))
                                    <div class="mt-3">
                                        <h6>Gaps in Residence History</h6>
                                        <div class="alert alert-warning">
                                            <strong>Years with no residence data:</strong><br>
                                            @php
                                                $gapYears = $metrics['residence_completeness']['details']['gaps']['years'];
                                                $gapChunks = array_chunk($gapYears, 10);
                                            @endphp
                                            @foreach($gapChunks as $chunk)
                                                <code>{{ implode(', ', $chunk) }}</code><br>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Recommendations -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-lightbulb text-warning"></i>
                                Recommendations for Improvement
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                @php
                                    $recommendations = [];
                                    
                                    // Residence-focused recommendations (for person spans)
                                    if ($metrics['residence_completeness'] && $metrics['residence_completeness']['percentage'] < 80) {
                                        if (($metrics['residence_completeness']['details']['gaps']['count'] ?? 0) > 0) {
                                            $recommendations[] = "Fill residence gaps to improve location history coverage";
                                        }
                                        if (empty($metrics['residence_completeness']['residence_periods'])) {
                                            $recommendations[] = "Add residence connections to track location history";
                                        }
                                    }
                                    
                                    if (empty($recommendations)) {
                                        $recommendations[] = "This span has excellent completeness! Consider adding more detailed metadata or connections for even better coverage.";
                                    }
                                @endphp
                                
                                @foreach($recommendations as $recommendation)
                                    <div class="col-12 mb-2">
                                        <div class="d-flex align-items-start">
                                            <i class="fas fa-arrow-right text-primary mt-1 me-2"></i>
                                            <span>{{ $recommendation }}</span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.progress {
    min-width: 100px;
}
.progress-bar {
    font-size: 0.875rem;
    line-height: 25px;
}
.display-1 {
    font-size: 4rem;
}
</style>
@endsection
