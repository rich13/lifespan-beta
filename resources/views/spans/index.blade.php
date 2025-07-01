@extends('layouts.app')

@section('page_title')
    Spans
@endsection

@section('page_filters')
    <x-spans.filters 
        :route="route('spans.index')"
        :selected-types="request('types') ? explode(',', request('types')) : []"
        :show-search="true"
        :show-type-filters="true"
        :show-permission-mode="false"
        :show-visibility="false"
        :show-state="false"
    />
@endsection

@section('page_tools')
    <div class="d-flex gap-2 align-items-center">
        @auth
            <a href="{{ route('spans.create') }}" class="btn btn-sm btn-primary">
                <i class="bi bi-plus-circle me-1"></i>New Span
            </a>
        @endauth
    </div>
@endsection

@section('content')
<div class="container-fluid">
    @if(request('search'))
        <div class="alert alert-info alert-sm py-2 mb-3">
            <small>
                <i class="bi bi-info-circle me-1"></i>
                Found {{ $spans->total() }} {{ Str::plural('result', $spans->total()) }} for "<strong>{{ request('search') }}</strong>"
            </small>
        </div>
    @endif

    @if($spans->isEmpty())
        <div class="card">
            <div class="card-body">
                <p class="text-center text-muted my-5">No spans found.</p>
            </div>
        </div>
    @else
        <!-- Global Timescale -->
        <div class="global-timescale mb-3">
            <div class="card position-relative" style="min-height: 50px;">
                <!-- Timeline background that fills the entire container -->
                <div class="position-absolute w-100 h-100" style="top: 0; left: 0; z-index: 1;">
                    <div id="global-timescale" style="height: 100%; width: 100%; margin-left: 0;">
                        <!-- D3 timescale will be rendered here -->
                    </div>
                </div>
                
                <!-- Content positioned on top of the timeline -->
                <div class="position-relative" style="z-index: 2;">
                    <div class="card-body py-2">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <small class="text-muted" id="timescale-range">Loading...</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Interactive Button Group View -->
        <div class="spans-list">
            @foreach($spans as $span)
                <x-spans.display.interactive-card :span="$span" />
            @endforeach
        </div>

        <div class="mt-4">
            <x-pagination :paginator="$spans->appends(request()->query())" />
        </div>
    @endif
</div>

@push('scripts')
<script src="https://d3js.org/d3.v7.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Calculate global timeline bounds from all visible spans
    calculateGlobalTimelineBounds();
});

function calculateGlobalTimelineBounds() {
    // Get all spans data from the page
    const spans = @json($spans->items());
    
    let globalStart = null;
    let globalEnd = null;
    
    // Find the earliest and latest dates across all spans
    spans.forEach(span => {
        if (span.start_year) {
            if (globalStart === null || span.start_year < globalStart) {
                globalStart = span.start_year;
            }
        }
        
        // Use the same logic as card timelines: current year for null end years
        const endYear = span.end_year || new Date().getFullYear();
        if (globalEnd === null || endYear > globalEnd) {
            globalEnd = endYear;
        }
    });
    
    // Set default bounds if no spans have dates
    if (globalStart === null) {
        globalStart = 1900;
        globalEnd = new Date().getFullYear();
    }
    
    // Add some padding
    const padding = Math.max(5, Math.floor((globalEnd - globalStart) * 0.1));
    globalStart = globalStart - padding;
    globalEnd = globalEnd + padding;
    
    // Store global timeline data
    window.globalTimelineData = {
        start: globalStart,
        end: globalEnd
    };
    
    console.log('Global timeline bounds:', globalStart, 'to', globalEnd);
    
    // Update timescale range display
    document.getElementById('timescale-range').textContent = `${globalStart} - ${globalEnd}`;
    
    // Render global timescale
    renderGlobalTimescale();
    
    // Trigger event for card timelines to initialize
    document.dispatchEvent(new Event('globalTimelineReady'));
    
    // Wait for card timelines to be rendered, then re-render global timescale with correct width
    setTimeout(() => {
        renderGlobalTimescale();
    }, 100);
}

function renderGlobalTimescale() {
    const container = document.getElementById('global-timescale');
    if (!container || !window.globalTimelineData) return;
    
    // Get the width from a representative card timeline to ensure exact same scale
    const spansList = document.querySelector('.spans-list');
    let cardTimelineWidth = null;
    
    if (spansList) {
        // Find the first card timeline to get the reference width
        const firstCardTimeline = spansList.querySelector('.card-timeline-container');
        if (firstCardTimeline) {
            cardTimelineWidth = firstCardTimeline.clientWidth;
            console.log('Found card timeline width:', cardTimelineWidth);
        }
    }
    
    // Use the card timeline width directly - this is the key fix
    const width = cardTimelineWidth || container.clientWidth;
    console.log('Global timescale container width:', container.clientWidth);
    console.log('Card timeline width:', cardTimelineWidth);
    console.log('Final used width:', width);
    const height = container.clientHeight;
    const margin = { left: 0, right: 2, top: 2, bottom: 2 }; // Match card timeline margins exactly
    
    // Clear container
    container.innerHTML = '';
    
    // Create SVG with the exact same width as card timelines
    const svg = d3.select(container)
        .append('svg')
        .attr('width', width)
        .attr('height', height);
    
    // Create time scale - use exact same calculation as card timelines
    const xScale = d3.scaleLinear()
        .domain([window.globalTimelineData.start, window.globalTimelineData.end])
        .range([margin.left, width - margin.right]);
    
    // Calculate the actual span bounds (without padding) to determine the offset
    const spans = @json($spans->items());
    let actualStart = null;
    let actualEnd = null;
    
    spans.forEach(span => {
        if (span.start_year) {
            if (actualStart === null || span.start_year < actualStart) {
                actualStart = span.start_year;
            }
        }
        const endYear = span.end_year || new Date().getFullYear();
        if (actualEnd === null || endYear > actualEnd) {
            actualEnd = endYear;
        }
    });
    
    // Calculate the padding offset
    const paddingOffset = actualStart - window.globalTimelineData.start;
    const timelineStartX = xScale(actualStart);
    
    // Debug: Log positioning information
    console.log('Global timescale debug:');
    console.log('- Actual start year:', actualStart);
    console.log('- Global timeline start:', window.globalTimelineData.start);
    console.log('- Padding offset:', paddingOffset);
    console.log('- Timeline start X position:', timelineStartX);
    console.log('- Scale domain:', [window.globalTimelineData.start, window.globalTimelineData.end]);
    console.log('- Scale range:', [margin.left, width - margin.right]);
    console.log('- Used width:', width);
    
    // Draw timeline background (subtle like card timelines) - offset to match actual span positions
    svg.append('rect')
        .attr('x', timelineStartX)
        .attr('y', margin.top)
        .attr('width', xScale(actualEnd) - timelineStartX)
        .attr('height', height - margin.top - margin.bottom)
        .attr('fill', '#f8f9fa')
        .attr('stroke', '#dee2e6')
        .attr('stroke-width', 1)
        .attr('rx', 4)
        .attr('ry', 4)
        .style('opacity', 0.3)
        .style('pointer-events', 'none');
    
    // Calculate tick intervals based on time range
    const timeRange = window.globalTimelineData.end - window.globalTimelineData.start;
    let tickInterval = 10; // Show every decade by default
    
    // Adjust interval based on time range
    if (timeRange > 200) {
        tickInterval = 50;
    } else if (timeRange > 100) {
        tickInterval = 25;
    } else if (timeRange > 50) {
        tickInterval = 10;
    } else if (timeRange > 20) {
        tickInterval = 5;
    } else if (timeRange > 10) {
        tickInterval = 2;
    } else {
        tickInterval = 1;
    }
    
    // Generate tick values - round to nearest interval
    const ticks = [];
    const startYear = Math.floor(window.globalTimelineData.start / tickInterval) * tickInterval;
    for (let year = startYear; year <= window.globalTimelineData.end; year += tickInterval) {
        if (year >= window.globalTimelineData.start) {
            ticks.push(year);
        }
    }
    
    // Draw tick marks
    svg.selectAll('.tick-mark')
        .data(ticks)
        .enter()
        .append('line')
        .attr('class', 'tick-mark')
        .attr('x1', d => xScale(d))
        .attr('x2', d => xScale(d))
        .attr('y1', margin.top)
        .attr('y2', height - margin.bottom)
        .attr('stroke', '#6c757d')
        .attr('stroke-width', 1)
        .style('opacity', 0.5)
        .style('pointer-events', 'none');
    
    // Draw tick labels at the top
    svg.selectAll('.tick-label')
        .data(ticks)
        .enter()
        .append('text')
        .attr('class', 'tick-label')
        .attr('x', d => xScale(d))
        .attr('y', 15) // Position within the visible area
        .attr('text-anchor', 'middle')
        .attr('font-size', '10px')
        .attr('fill', '#6c757d')
        .text(d => d)
        .style('pointer-events', 'none');
    
    // Draw current year indicator
    const currentYear = new Date().getFullYear();
    if (currentYear >= window.globalTimelineData.start && currentYear <= window.globalTimelineData.end) {
        svg.append('line')
            .attr('class', 'current-year-indicator')
            .attr('x1', xScale(currentYear))
            .attr('x2', xScale(currentYear))
            .attr('y1', margin.top)
            .attr('y2', height - margin.bottom)
            .attr('stroke', '#dc3545')
            .attr('stroke-width', 2)
            .style('opacity', 0.8)
            .style('pointer-events', 'none');
        
        svg.append('text')
            .attr('class', 'current-year-label')
            .attr('x', xScale(currentYear))
            .attr('y', margin.top - 2)
            .attr('text-anchor', 'middle')
            .attr('font-size', '9px')
            .attr('fill', '#dc3545')
            .attr('font-weight', 'bold')
            .text('Now')
            .style('pointer-events', 'none');
    }
}
</script>
@endpush
@endsection 