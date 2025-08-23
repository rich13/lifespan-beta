@extends('layouts.app')

@section('page_title')
    Span Completeness Metrics
@endsection

@section('content')
<div class="py-4">
    <div class="row">
        <div class="col-12 d-flex justify-content-between align-items-center mb-4">
                <h1>Span Completeness Metrics</h1>
                <div>
                    <a href="{{ route('admin.metrics.export') }}?{{ request()->getQueryString() }}" class="btn btn-outline-secondary">
                        <i class="fas fa-download"></i> Export CSV
                    </a>
                    <a href="{{ route('admin.metrics.low-completeness') }}" class="btn btn-warning">
                        <i class="fas fa-exclamation-triangle"></i> Low Completeness
                    </a>
                    <a href="{{ route('admin.metrics.residence-gaps') }}" class="btn btn-info">
                        <i class="fas fa-map-marker-alt"></i> Residence Gaps
                    </a>
                    <a href="{{ route('admin.metrics.calculate-all') }}" class="btn btn-success me-2" onclick="return confirm('This will calculate metrics for spans without cached data. Continue?')">
                        <i class="fas fa-calculator"></i> Calculate Missing Metrics
                    </a>
                    <a href="{{ route('admin.metrics.calculate-person-spans') }}" class="btn btn-info me-2" onclick="return confirm('This will refresh metrics for all 466 person spans. This may take some time. Continue?')">
                        <i class="fas fa-users"></i> Refresh Person Metrics
                    </a>
                    <a href="{{ route('admin.metrics.force-calculate-all') }}" class="btn btn-warning" onclick="return confirm('This will FORCE recalculation of ALL metrics for ALL spans. This will take a very long time. Continue?')">
                        <i class="fas fa-sync-alt"></i> Force Refresh All
                    </a>
                </div>
            </div>

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label for="type" class="form-label">Span Type</label>
                            <select name="type" id="type" class="form-select">
                                {{-- Options expect an associative array: [type_id => name] --}}
                                <option value="all" {{ $typeFilter == 'all' ? 'selected' : '' }}>All Types</option>
                                @foreach(($spanTypeOptions ?? []) as $spanTypeId => $spanTypeName)
                                    <option value="{{ $spanTypeId }}" {{ $typeFilter == $spanTypeId ? 'selected' : '' }}>
                                        {{ $spanTypeName }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="score" class="form-label">Score Range</label>
                            <select name="score" id="score" class="form-select">
                                <option value="all" {{ $scoreFilter == 'all' ? 'selected' : '' }}>All Scores</option>
                                <option value="excellent" {{ $scoreFilter == 'excellent' ? 'selected' : '' }}>Excellent (90-100%)</option>
                                <option value="good" {{ $scoreFilter == 'good' ? 'selected' : '' }}>Good (70-89%)</option>
                                <option value="fair" {{ $scoreFilter == 'fair' ? 'selected' : '' }}>Fair (50-69%)</option>
                                <option value="poor" {{ $scoreFilter == 'poor' ? 'selected' : '' }}>Poor (30-49%)</option>
                                <option value="very_poor" {{ $scoreFilter == 'very_poor' ? 'selected' : '' }}>Very Poor (0-29%)</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="sort" class="form-label">Sort By</label>
                            <select name="sort" id="sort" class="form-select">
                                <option value="residence" {{ ($sort ?? 'residence') === 'residence' ? 'selected' : '' }}>Residence Coverage</option>
                                <option value="granularity" {{ ($sort ?? '') === 'granularity' ? 'selected' : '' }}>Granularity</option>
                                <option value="quality" {{ ($sort ?? '') === 'quality' ? 'selected' : '' }}>Quality Score</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="dir" class="form-label">Direction</label>
                            <select name="dir" id="dir" class="form-select">
                                <option value="desc" {{ ($dir ?? 'desc') === 'desc' ? 'selected' : '' }}>Highest First</option>
                                <option value="asc" {{ ($dir ?? '') === 'asc' ? 'selected' : '' }}>Lowest First</option>
                            </select>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">Apply</button>
                            <a href="{{ route('admin.metrics.index') }}" class="btn btn-outline-secondary">Reset</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Alerts -->
            @if(session('success'))
                <div class="alert alert-success mb-4">
                    <i class="fas fa-check-circle"></i>
                    {{ session('success') }}
                </div>
            @endif
            
            @if(isset($metrics['summary']['spans_without_metrics']) && $metrics['summary']['spans_without_metrics'] > 0)
                <div class="alert alert-info mb-4">
                    <i class="fas fa-info-circle"></i>
                    <strong>{{ $metrics['summary']['spans_without_metrics'] }} spans</strong> don't have metrics yet. 
                    Use the "Calculate All Metrics" button to start background calculations.
                </div>
            @endif

            <!-- Histogram and Summary Side by Side -->
            <div class="row mb-4">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-chart-bar text-primary"></i>
                                Score Distribution Histogram
                            </h5>
                        </div>
                        <div class="card-body">
                            <div id="histogram-container" style="height: 300px;"></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-info-circle text-info"></i>
                                Summary Statistics
                            </h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-sm mb-0">
                                    <tbody>
                                        <tr>
                                            <td class="fw-bold">Total Spans:</td>
                                            <td class="text-end">{{ ($pagination['total_items'] ?? 0) + ($metrics['summary']['spans_without_metrics'] ?? 0) }}</td>
                                        </tr>
                                        <tr>
                                            <td class="fw-bold">With Metrics:</td>
                                            <td class="text-end">{{ $pagination['total_items'] ?? ($metrics['summary']['total_spans'] ?? 0) }}</td>
                                        </tr>
                                        <tr class="table-primary">
                                            <td class="fw-bold">Avg Residence:</td>
                                            <td class="text-end">{{ $metrics['summary']['average_scores']['residence'] ?? 0 }}%</td>
                                        </tr>
                                        <tr class="table-warning">
                                            <td class="fw-bold">Avg Granularity:</td>
                                            <td class="text-end">{{ $metrics['summary']['average_scores']['granularity'] ?? 0 }}%</td>
                                        </tr>
                                        <tr class="table-danger">
                                            <td class="fw-bold">Avg Quality:</td>
                                            <td class="text-end">{{ $metrics['summary']['average_scores']['quality'] ?? 0 }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>



            <!-- Spans Table -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Span Completeness Details</h5>
                </div>
                <div class="card-body">
                    @if(empty($metrics['spans']))
                        <div class="text-center py-4">
                            <i class="fas fa-search fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No spans found matching the current filters.</p>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Span</th>
                                        <th>Type</th>
                                        <th>
                                            Residence Coverage
                                            @if(($sort ?? 'residence') === 'residence')
                                                <i class="fas fa-sort-{{ $dir === 'desc' ? 'down' : 'up' }} ms-1"></i>
                                            @endif
                                        </th>
                                        <th>
                                            Granularity
                                            @if(($sort ?? '') === 'granularity')
                                                <i class="fas fa-sort-{{ $dir === 'desc' ? 'down' : 'up' }} ms-1"></i>
                                            @endif
                                        </th>
                                        <th>
                                            Quality Score
                                            @if(($sort ?? '') === 'quality')
                                                <i class="fas fa-sort-{{ $dir === 'desc' ? 'down' : 'up' }} ms-1"></i>
                                            @endif
                                        </th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach(($metrics['spans'] ?? []) as $spanId => $spanMetrics)
                                        @php
                                            $span = \App\Models\Span::find($spanId);
                                            if (!$span) continue;
                                            
                                            // Handle both SpanMetric objects and arrays
                                            if (is_object($spanMetrics)) {
                                                $spanMetrics = $spanMetrics->metrics_data;
                                            }
                                            
                                            // Debug: Check what we have
                                            if (!is_array($spanMetrics)) {
                                                continue; // Skip if not an array
                                            }
                                            
                                            $residenceScore = $spanMetrics['residence_completeness']['percentage'] ?? 0;
                                            $scoreClass = '';
                                            if ($residenceScore >= 90) $scoreClass = 'table-success';
                                            elseif ($residenceScore >= 70) $scoreClass = 'table-primary';
                                            elseif ($residenceScore >= 50) $scoreClass = 'table-warning';
                                            elseif ($residenceScore >= 30) $scoreClass = 'table-orange';
                                            else $scoreClass = 'table-danger';
                                        @endphp
                                        <tr class="{{ $scoreClass }}">
                                            <td>
                                                <a href="{{ route('spans.show', $span) }}" class="text-decoration-none">
                                                    {{ $span->name }}
                                                </a>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary">{{ ucfirst($span->type_id) }}</span>
                                            </td>
                                            
                                            <td>
                                                @if(isset($spanMetrics['residence_completeness']) && $spanMetrics['residence_completeness'])
                                                    <div class="d-flex align-items-center">
                                                        <div class="progress flex-grow-1 me-2" style="height: 20px;">
                                                            <div class="progress-bar bg-success" style="width: {{ $spanMetrics['residence_completeness']['percentage'] ?? 0 }}%">
                                                                {{ $spanMetrics['residence_completeness']['percentage'] ?? 0 }}%
                                                            </div>
                                                        </div>
                                                    </div>
                                                @else
                                                    <span class="text-muted">N/A</span>
                                                @endif
                                            </td>

                                            <td>
                                                @if(isset($spanMetrics['residence_completeness']['granularity']))
                                                    @php
                                                        $granularity = $spanMetrics['residence_completeness']['granularity']['relative_granularity'] ?? 0;
                                                        $granularityClass = '';
                                                        if ($granularity >= 20) $granularityClass = 'text-success';
                                                        elseif ($granularity >= -20) $granularityClass = 'text-primary';
                                                        elseif ($granularity >= -50) $granularityClass = 'text-warning';
                                                        else $granularityClass = 'text-danger';
                                                    @endphp
                                                    <span class="{{ $granularityClass }}">
                                                        {{ $granularity > 0 ? '+' : '' }}{{ $granularity }}%
                                                    </span>
                                                @else
                                                    <span class="text-muted">N/A</span>
                                                @endif
                                            </td>

                                            <td>
                                                @if(isset($spanMetrics['residence_completeness']['quality_score']))
                                                    @php
                                                        $qualityScore = $spanMetrics['residence_completeness']['quality_score']['score'] ?? 0;
                                                        $qualityClass = '';
                                                        if ($qualityScore >= 90) $qualityClass = 'text-success';
                                                        elseif ($qualityScore >= 70) $qualityClass = 'text-primary';
                                                        elseif ($qualityScore >= 50) $qualityClass = 'text-warning';
                                                        elseif ($qualityScore >= 30) $qualityClass = 'text-orange';
                                                        else $qualityClass = 'text-danger';
                                                    @endphp
                                                    <span class="{{ $qualityClass }} fw-bold">
                                                        {{ $qualityScore }}
                                                    </span>
                                                @else
                                                    <span class="text-muted">N/A</span>
                                                @endif
                                            </td>

                                            <td>
                                                <a href="{{ route('admin.metrics.show', $span) }}" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-chart-line"></i> Details
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        @if(isset($pagination) && $pagination['total_pages'] > 1)
                            <div class="card-footer">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="text-muted">
                                        Showing {{ (($pagination['current_page'] - 1) * $pagination['per_page']) + 1 }} 
                                        to {{ min($pagination['current_page'] * $pagination['per_page'], $pagination['total_items']) }} 
                                        of {{ $pagination['total_items'] }} spans
                                    </div>
                                    <nav aria-label="Metrics pagination">
                                        <ul class="pagination pagination-sm mb-0">
                                            {{-- Previous Page --}}
                                            @if($pagination['has_previous'])
                                                <li class="page-item">
                                                    <a class="page-link" href="{{ request()->fullUrlWithQuery(['page' => $pagination['previous_page']]) }}">
                                                        <i class="fas fa-chevron-left"></i> Previous
                                                    </a>
                                                </li>
                                            @else
                                                <li class="page-item disabled">
                                                    <span class="page-link">
                                                        <i class="fas fa-chevron-left"></i> Previous
                                                    </span>
                                                </li>
                                            @endif

                                            {{-- Page Numbers --}}
                                            @php
                                                $start = max(1, $pagination['current_page'] - 2);
                                                $end = min($pagination['total_pages'], $pagination['current_page'] + 2);
                                            @endphp

                                            @if($start > 1)
                                                <li class="page-item">
                                                    <a class="page-link" href="{{ request()->fullUrlWithQuery(['page' => 1]) }}">1</a>
                                                </li>
                                                @if($start > 2)
                                                    <li class="page-item disabled">
                                                        <span class="page-link">...</span>
                                                    </li>
                                                @endif
                                            @endif

                                            @for($i = $start; $i <= $end; $i++)
                                                <li class="page-item {{ $i == $pagination['current_page'] ? 'active' : '' }}">
                                                    <a class="page-link" href="{{ request()->fullUrlWithQuery(['page' => $i]) }}">{{ $i }}</a>
                                                </li>
                                            @endfor

                                            @if($end < $pagination['total_pages'])
                                                @if($end < $pagination['total_pages'] - 1)
                                                    <li class="page-item disabled">
                                                        <span class="page-link">...</span>
                                                    </li>
                                                @endif
                                                <li class="page-item">
                                                    <a class="page-link" href="{{ request()->fullUrlWithQuery(['page' => $pagination['total_pages']]) }}">{{ $pagination['total_pages'] }}</a>
                                                </li>
                                            @endif

                                            {{-- Next Page --}}
                                            @if($pagination['has_next'])
                                                <li class="page-item">
                                                    <a class="page-link" href="{{ request()->fullUrlWithQuery(['page' => $pagination['next_page']]) }}">
                                                        Next <i class="fas fa-chevron-right"></i>
                                                    </a>
                                                </li>
                                            @else
                                                <li class="page-item disabled">
                                                    <span class="page-link">
                                                        Next <i class="fas fa-chevron-right"></i>
                                                    </span>
                                                </li>
                                            @endif
                                        </ul>
                                    </nav>
                                </div>
                            </div>
                        @endif
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.table-orange {
    background-color: #fff3cd !important;
}
.progress {
    min-width: 80px;
}
.progress-bar {
    font-size: 0.75rem;
    line-height: 20px;
}
</style>
@endsection

@push('scripts')
<script src="https://d3js.org/d3.v7.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Get the histogram data from the server
    const histogramData = @json($metrics['histogram_data'] ?? []);
    const currentSort = '{{ $sort }}';
    const currentDir = '{{ $dir }}';
    

    
    if (histogramData.length > 0) {
        createHistogram(histogramData, currentSort, currentDir);
    }
    
    function createHistogram(data, sortType, direction) {
        // Clear previous histogram
        d3.select("#histogram-container").selectAll("*").remove();
        
        const margin = {top: 20, right: 30, bottom: 40, left: 60};
        const width = document.getElementById('histogram-container').offsetWidth - margin.left - margin.right;
        const height = 300 - margin.top - margin.bottom;
        
        // Create SVG
        const svg = d3.select("#histogram-container")
            .append("svg")
            .attr("width", width + margin.left + margin.right)
            .attr("height", height + margin.top + margin.bottom)
            .append("g")
            .attr("transform", `translate(${margin.left},${margin.top})`);
        
        // Create scales
        const x = d3.scaleLinear()
            .domain([d3.min(data, d => d.bin_start), d3.max(data, d => d.bin_end)])
            .range([0, width]);
        
        const y = d3.scaleLinear()
            .domain([0, d3.max(data, d => d.count)])
            .range([height, 0]);
        
        // Add X axis
        svg.append("g")
            .attr("transform", `translate(0,${height})`)
            .call(d3.axisBottom(x).ticks(10))
            .selectAll("text")
            .style("text-anchor", "middle");
        
        // Add Y axis
        svg.append("g")
            .call(d3.axisLeft(y).ticks(5));
        
        // Add X axis label
        svg.append("text")
            .attr("text-anchor", "middle")
            .attr("x", width / 2)
            .attr("y", height + margin.bottom - 5)
            .text(getAxisLabel(sortType));
        
        // Add Y axis label
        svg.append("text")
            .attr("text-anchor", "middle")
            .attr("transform", "rotate(-90)")
            .attr("x", -height / 2)
            .attr("y", -margin.left + 20)
            .text("Frequency");
        
        // Add title
        svg.append("text")
            .attr("text-anchor", "middle")
            .attr("x", width / 2)
            .attr("y", -5)
            .style("font-size", "14px")
            .style("font-weight", "bold")
            .text(`Distribution of ${getMetricName(sortType)} Scores`);
        
        // Create bars
        svg.selectAll("rect")
            .data(data)
            .enter()
            .append("rect")
            .attr("x", d => x(d.bin_start))
            .attr("y", d => y(d.count))
            .attr("width", d => Math.max(0, x(d.bin_end) - x(d.bin_start) - 1))
            .attr("height", d => height - y(d.count))
            .attr("fill", "#007bff")
            .attr("opacity", 0.8)
            .on("mouseover", function(event, d) {
                d3.select(this).attr("opacity", 1);
                
                // Add tooltip
                const tooltip = d3.select("body").append("div")
                    .attr("class", "tooltip")
                    .style("position", "absolute")
                    .style("background", "rgba(0,0,0,0.8)")
                    .style("color", "white")
                    .style("padding", "8px")
                    .style("border-radius", "4px")
                    .style("font-size", "12px")
                    .style("pointer-events", "none")
                    .style("z-index", "1000");
                
                tooltip.html(`
                    <strong>${d.bin_start} - ${d.bin_end}</strong><br/>
                    Count: ${d.count}<br/>
                    Percentage: ${((d.count / d.total) * 100).toFixed(1)}%
                `)
                .style("left", (event.pageX + 10) + "px")
                .style("top", (event.pageY - 10) + "px");
            })
            .on("mouseout", function() {
                d3.select(this).attr("opacity", 0.8);
                d3.selectAll(".tooltip").remove();
            });
        
        // Add mean line
        const mean = d3.mean(data, d => (d.bin_start + d.bin_end) / 2);
        if (mean) {
            svg.append("line")
                .attr("x1", x(mean))
                .attr("x2", x(mean))
                .attr("y1", 0)
                .attr("y2", height)
                .attr("stroke", "#dc3545")
                .attr("stroke-width", 2)
                .attr("stroke-dasharray", "5,5");
            
            svg.append("text")
                .attr("x", x(mean) + 5)
                .attr("y", 20)
                .attr("fill", "#dc3545")
                .style("font-size", "12px")
                .text(`Mean: ${mean.toFixed(1)}`);
        }
    }
    
    function getAxisLabel(sortType) {
        switch(sortType) {
            case 'granularity': return 'Relative Granularity (%)';
            case 'quality': return 'Quality Score';
            case 'residence':
            default: return 'Residence Coverage (%)';
        }
    }
    
    function getMetricName(sortType) {
        switch(sortType) {
            case 'granularity': return 'Granularity';
            case 'quality': return 'Quality';
            case 'residence':
            default: return 'Residence Coverage';
        }
    }
});
</script>
@endpush
