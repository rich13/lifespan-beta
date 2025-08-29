@extends('layouts.app')

@section('page_title')
    <x-breadcrumb :items="[
        //[
        //    'text' => 'Spans',
        //    'url' => route('spans.index'),
        //    'icon' => 'view',
        //    'icon_category' => 'action'
        //],
        [
            'text' => $subject->getDisplayTitle(),
            'url' => route('spans.show', $subject),
            'icon' => $subject->type_id,
            'icon_category' => 'span'
        ],
        [
            'text' => 'All Connections',
            'url' => route('spans.all-connections', $subject),
            'icon' => 'diagram-3',
            'icon_category' => 'connection'
        ]
    ]" />
@endsection

@section('content')
<div class="py-4">
    <div class="row">
        <div class="col-12">

            <!-- Connection Type Navigation -->
            @if($relevantConnectionTypes->count() > 1)
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title mb-3">
                            <i class="bi bi-diagram-3 me-2"></i>
                            Connection Types
                        </h5>
                        <div class="d-flex flex-wrap gap-2">
                            <a href="{{ route('spans.all-connections', $subject) }}" 
                               class="btn btn-sm btn-primary">
                                All Connections
                            </a>
                            @foreach($relevantConnectionTypes as $type)
                                @php
                                    $hasConnections = $connectionCounts[$type->type] ?? 0;
                                    $routePredicate = str_replace(' ', '-', $type->forward_predicate);
                                    $url = route('spans.connections', ['subject' => $subject, 'predicate' => $routePredicate]);
                                @endphp
                                @if($hasConnections > 0)
                                    <a href="{{ $url }}" 
                                       class="btn btn-sm btn-secondary"
                                       style="background-color: var(--connection-{{ $type->type }}-color, #007bff); border-color: var(--connection-{{ $type->type }}-color, #007bff); color: white;">
                                        {{ ucfirst($type->forward_predicate) }}
                                        <span class="badge bg-secondary ms-1">{{ $hasConnections }}</span>
                                    </a>
                                @else
                                    <span class="btn btn-sm btn-outline-secondary disabled" style="opacity: 0.5;">
                                        {{ ucfirst($type->forward_predicate) }}
                                        <span class="badge bg-secondary ms-1">0</span>
                                    </span>
                                @endif
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

            <!-- Comprehensive Gantt Chart -->
            @if(count($allConnections) > 0)
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-clock-history me-2"></i>
                            Complete Life Timeline
                        </h5>
                    </div>
                    <div class="card-body">
                        <div id="all-connections-timeline-container" style="height: auto; min-height: 200px; width: 100%;">
                            <!-- D3 timeline will be rendered here -->
                        </div>
                    </div>
                </div>
            @else
                <div class="card">
                    <div class="card-body text-center">
                        <i class="bi bi-info-circle text-muted" style="font-size: 3rem;"></i>
                        <h5 class="mt-3">No Connections Found</h5>
                        <p class="text-muted">This span doesn't have any connections yet.</p>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

@if(count($allConnections) > 0)
    @push('scripts')
    <script src="https://d3js.org/d3.v7.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const container = document.getElementById('all-connections-timeline-container');
        if (!container) {
            console.error('All connections timeline container not found');
            return;
        }

        const width = container.clientWidth;
        const margin = { top: 10, right: 20, bottom: 50, left: 120 }; // Increased left margin for labels
        const swimlaneHeight = 20;
        const swimlaneSpacing = 8;
        const overallSwimlaneY = margin.top + 10;

        // Prepare data for the timeline
        const timelineData = [];
        let swimlaneIndex = 0;

        // Add life swimlane
        timelineData.push({
            type: 'life',
            label: 'Life',
            y: overallSwimlaneY + (swimlaneIndex * (swimlaneHeight + swimlaneSpacing)),
            connections: []
        });
        swimlaneIndex++;

        // Add individual connection swimlanes
        @foreach($allConnections as $connectionType => $connections)
            @foreach($connections as $connection)
                timelineData.push({
                    type: 'connection',
                    connectionType: '{{ $connectionType }}',
                    connection: @json($connection),
                    label: '{{ $connection->other_span->name }}',
                    y: overallSwimlaneY + (swimlaneIndex * (swimlaneHeight + swimlaneSpacing))
                });
                swimlaneIndex++;
            @endforeach
        @endforeach

        // Calculate time range
        let minYear = {{ $subject->start_year ?? 1900 }};
        let maxYear = {{ $subject->end_year ?? date('Y') }};

        @foreach($allConnections as $connections)
            @foreach($connections as $connection)
                @if($connection->connectionSpan && $connection->connectionSpan->start_year)
                    minYear = Math.min(minYear, {{ $connection->connectionSpan->start_year }});
                    maxYear = Math.max(maxYear, {{ $connection->connectionSpan->end_year ?? date('Y') }});
                @endif
            @endforeach
        @endforeach

        const timeRange = { start: minYear, end: maxYear };
        const totalSwimlanes = timelineData.length;
        const height = margin.top + (totalSwimlanes * swimlaneHeight) + ((totalSwimlanes - 1) * swimlaneSpacing) + margin.bottom;

        // Update container height
        container.style.height = height + 'px';

        // Clear container
        container.innerHTML = '';

        // Create SVG
        const svg = d3.select(container)
            .append('svg')
            .attr('width', width)
            .attr('height', height);

        // Create scales
        const xScale = d3.scaleLinear()
            .domain([timeRange.start, timeRange.end])
            .range([margin.left, width - margin.right]);

        // Create axis
        const xAxis = d3.axisBottom(xScale)
            .tickFormat(d3.format('d'))
            .ticks(10);

        svg.append('g')
            .attr('transform', `translate(0, ${height - margin.bottom})`)
            .call(xAxis);

        // Add axis label
        svg.append('text')
            .attr('x', width / 2)
            .attr('y', height - 5)
            .attr('text-anchor', 'middle')
            .style('font-size', '12px')
            .style('fill', '#666')
            .text('Year');

        // Get connection color function
        function getConnectionColor(typeId) {
            return getComputedStyle(document.documentElement)
                .getPropertyValue(`--connection-${typeId}-color`) || '#007bff';
        }

        // Render each swimlane
        timelineData.forEach((swimlane) => {
            // Add swimlane label
            svg.append('text')
                .attr('x', margin.left - 10)
                .attr('y', swimlane.y + swimlaneHeight / 2)
                .attr('text-anchor', 'end')
                .attr('dominant-baseline', 'middle')
                .style('font-size', '11px')
                .style('fill', '#666')
                .style('font-weight', swimlane.type === 'life' ? 'bold' : 'normal')
                .text(swimlane.label);

            // Add swimlane background
            svg.append('rect')
                .attr('x', margin.left)
                .attr('y', swimlane.y)
                .attr('width', width - margin.left - margin.right)
                .attr('height', swimlaneHeight)
                .attr('fill', '#f8f9fa')
                .attr('stroke', '#dee2e6')
                .attr('stroke-width', 1);

            if (swimlane.type === 'life') {
                // Add life span bar
                const lifeStart = xScale({{ $subject->start_year ?? 1900 }});
                const lifeEnd = xScale({{ $subject->end_year ?? date('Y') }});
                
                svg.append('rect')
                    .attr('x', lifeStart)
                    .attr('y', swimlane.y)
                    .attr('width', lifeEnd - lifeStart)
                    .attr('height', swimlaneHeight)
                    .attr('fill', '#e9ecef')
                    .attr('stroke', '#dee2e6')
                    .attr('stroke-width', 1);
            } else {
                // Add individual connection bar
                const connection = swimlane.connection;
                const connectionSpan = connection.connection_span;
                const hasDates = connectionSpan && connectionSpan.start_year;
                
                let barX, barWidth, barColor, barOpacity, isClickable;
                
                if (hasDates) {
                    const startYear = connectionSpan.start_year;
                    const endYear = connectionSpan.end_year || new Date().getFullYear();
                    barX = xScale(startYear);
                    barWidth = xScale(endYear) - xScale(startYear);
                    barColor = getConnectionColor(swimlane.connectionType);
                    barOpacity = 0.7;
                    isClickable = true;
                } else {
                    barX = xScale(timeRange.start);
                    barWidth = xScale(timeRange.end) - xScale(timeRange.start);
                    barColor = '#6c757d';
                    barOpacity = 0.4;
                    isClickable = false;
                }

                const bar = svg.append('rect')
                    .attr('class', 'timeline-bar')
                    .attr('x', barX)
                    .attr('y', swimlane.y + 2)
                    .attr('width', Math.max(barWidth, 2))
                    .attr('height', swimlaneHeight - 4)
                    .attr('fill', barColor)
                    .attr('stroke', 'white')
                    .attr('stroke-width', 1)
                    .attr('rx', 2)
                    .attr('ry', 2)
                    .style('opacity', barOpacity)
                    .style('cursor', isClickable ? 'pointer' : 'default');

                // Add tooltip
                const tooltip = d3.select('body').append('div')
                    .attr('class', 'tooltip')
                    .style('position', 'absolute')
                    .style('background', 'rgba(0,0,0,0.8)')
                    .style('color', 'white')
                    .style('padding', '8px')
                    .style('border-radius', '4px')
                    .style('font-size', '12px')
                    .style('pointer-events', 'none')
                    .style('z-index', '1000')
                    .style('opacity', 0);

                if (isClickable) {
                    bar.on('mouseover', function(event) {
                        bar.style('opacity', 0.9);
                        tooltip.transition()
                            .duration(200)
                            .style('opacity', 1);
                        
                        const otherSpan = connection.other_span;
                        const startYear = connectionSpan.start_year;
                        const endYear = connectionSpan.end_year || new Date().getFullYear();
                        const duration = endYear - startYear + 1;
                        const isOngoing = !connectionSpan.end_year;
                        
                        tooltip.html(`
                            <strong>${otherSpan.name}</strong><br/>
                            ${startYear}${endYear !== startYear ? ` - ${endYear}` : ''}${isOngoing ? ' (ongoing)' : ''}<br/>
                            ${duration > 1 ? `(${duration} years)` : ''}
                        `)
                        .style('left', (event.pageX + 10) + 'px')
                        .style('top', (event.pageY - 10) + 'px');
                    })
                    .on('mouseout', function() {
                        bar.style('opacity', barOpacity);
                        tooltip.transition()
                            .duration(500)
                            .style('opacity', 0);
                    })
                    .on('click', function() {
                        window.location.href = `/spans/${connectionSpan.slug}`;
                    });
                } else {
                    bar.on('mouseover', function(event) {
                        bar.style('opacity', 0.6);
                        tooltip.transition()
                            .duration(200)
                            .style('opacity', 1);
                        
                        const otherSpan = connection.other_span;
                        
                        tooltip.html(`
                            <strong>${otherSpan.name}</strong><br/>
                            <em>Dates unknown</em>
                        `)
                        .style('left', (event.pageX + 10) + 'px')
                        .style('top', (event.pageY - 10) + 'px');
                    })
                    .on('mouseout', function() {
                        bar.style('opacity', barOpacity);
                        tooltip.transition()
                            .duration(500)
                            .style('opacity', 0);
                    });
                }
            }
        });
    });
    </script>
    @endpush
@endif
@endsection
