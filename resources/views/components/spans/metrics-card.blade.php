@props(['span'])

@php
    $metrics = $span->getMetrics();
@endphp

@if($metrics)
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="card-title mb-0">
                <i class="fas fa-chart-line text-primary"></i>
                Completeness Score
            </h6>
            <div class="d-flex align-items-center">
                @if($metrics->isStale())
                    <span class="badge bg-warning me-2" title="Metrics are being updated">
                        <i class="fas fa-sync-alt fa-spin"></i> Updating
                    </span>
                @endif
                @if($metrics->residence_score !== null)
                    <span class="badge bg-{{ $metrics->score_category_class }}">
                        {{ $metrics->residence_score }}%
                    </span>
                @else
                    <span class="badge bg-secondary">
                        N/A
                    </span>
                @endif
            </div>
        </div>
        <div class="card-body">
            <div class="row">
                @if($metrics->residence_score !== null)
                    <div class="col-md-6 mb-2">
                        <div class="text-center">
                            <div class="h5 mb-1 text-success">{{ $metrics->residence_score }}%</div>
                            <small class="text-muted">Residence Coverage</small>
                        </div>
                    </div>
                @endif

            </div>
            
            <div class="mt-2 text-center">
                <small class="text-muted">
                    Last updated: {{ $metrics->calculated_at->diffForHumans() }}
                    @if(auth()->user() && auth()->user()->is_admin)
                        · <a href="{{ route('admin.metrics.show', $span) }}" class="text-decoration-none">View Details</a>
                    @endif
                </small>
            </div>
        </div>
    </div>
@else
    <div class="card mb-3">
        <div class="card-header">
            <h6 class="card-title mb-0">
                <i class="fas fa-chart-line text-muted"></i>
                Completeness Score
            </h6>
        </div>
        <div class="card-body text-center">
            <div class="text-muted">
                <i class="fas fa-spinner fa-spin"></i>
                Calculating metrics...
            </div>
            <small class="text-muted">This may take a few moments</small>
        </div>
    </div>
@endif
