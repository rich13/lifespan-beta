@props(['span', 'connectionType', 'connections'])

<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="bi bi-clock-history me-2"></i>
            {{ ucfirst($connectionType->forward_predicate) }} Timeline
        </h5>
    </div>
    <div class="card-body">
        <div id="connection-timeline-container-{{ $span->id }}-{{ $connectionType->type }}" style="height: auto; min-height: 120px; width: 100%;">
            <!-- D3 timeline will be rendered here -->
        </div>
    </div>
</div>

@push('scripts')
<script src="https://d3js.org/d3.v7.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Add a small delay to ensure DOM is fully ready
    setTimeout(() => {
        initializeConnectionTimeline_{{ str_replace('-', '_', $span->id) }}_{{ str_replace('-', '_', $connectionType->type) }}();
    }, 100);
});

function initializeConnectionTimeline_{{ str_replace('-', '_', $span->id) }}_{{ str_replace('-', '_', $connectionType->type) }}() {
    const spanId = '{{ $span->id }}';
    const connectionType = '{{ $connectionType->type }}';
    const container = document.getElementById(`connection-timeline-container-${spanId}-${connectionType}`);
    
    // Check if container exists
    if (!container) {
        console.error('Connection timeline container not found:', `connection-timeline-container-${spanId}-${connectionType}`);
        return;
    }
    
    console.log('Initializing connection timeline for span:', spanId, 'connection type:', connectionType);
    
    // Transform the connections data for the timeline
    const timelineData = @json($connections->items()) || [];
    const spanData = @json($span);
    
    console.log('Timeline data:', timelineData);
    console.log('Span data:', spanData);
    console.log('Span start_year:', spanData.start_year);
    console.log('Span end_year:', spanData.end_year);
    
    // Render the timeline
    renderConnectionTimeline_{{ str_replace('-', '_', $span->id) }}_{{ str_replace('-', '_', $connectionType->type) }}(timelineData, spanData);
}

function renderConnectionTimeline_{{ str_replace('-', '_', $span->id) }}_{{ str_replace('-', '_', $connectionType->type) }}(connections, span) {
    const spanId = '{{ $span->id }}';
    const connectionType = '{{ $connectionType->type }}';
    const container = document.getElementById(`connection-timeline-container-${spanId}-${connectionType}`);
    
    // Check if container exists
    if (!container) {
        console.error('Connection timeline container not found during render:', `connection-timeline-container-${spanId}-${connectionType}`);
        return;
    }
    
    const width = container.clientWidth;
    const margin = { top: 10, right: 20, bottom: 50, left: 20 };
    const swimlaneHeight = 20;
    const swimlaneSpacing = 8;
    const overallSwimlaneY = margin.top + 10;
    
    // Calculate dynamic height based on number of connections
    const allConnections = connections.filter(conn => conn.connection_span);
    const connectionsWithDates = allConnections.filter(conn => conn.connection_span.start_year);
    const connectionsWithoutDates = allConnections.filter(conn => !conn.connection_span.start_year);
    
    // Sort connections with dates by start year (earliest first)
    connectionsWithDates.sort((a, b) => {
        const yearA = a.connection_span.start_year;
        const yearB = b.connection_span.start_year;
        return yearA - yearB;
    });
    
    // Combine: connections with dates first, then connections without dates
    const validConnections = [...connectionsWithDates, ...connectionsWithoutDates];
    
    const numConnectionSwimlanes = validConnections.length;
    const totalSwimlanes = 1 + numConnectionSwimlanes; // Life + connections
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

    // Calculate time range
    const timeRange = calculateConnectionTimeRange_{{ str_replace('-', '_', $span->id) }}_{{ str_replace('-', '_', $connectionType->type) }}(connections, span);
    console.log('Calculated time range:', timeRange);
    
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

    // Add life swimlane label
    svg.append('text')
        .attr('x', margin.left - 5)
        .attr('y', overallSwimlaneY + swimlaneHeight / 2)
        .attr('text-anchor', 'end')
        .attr('dominant-baseline', 'middle')
        .style('font-size', '11px')
        .style('fill', '#666')
        .text('Life');

    // Add overall life span background
    console.log('Span data for life span:', span);
    console.log('Start year:', span.start_year, 'End year:', span.end_year);
    
    if (span.start_year) {
        const lifeStart = xScale(span.start_year);
        const lifeEnd = span.end_year ? xScale(span.end_year) : xScale(new Date().getFullYear());
        
        console.log('Life span coordinates:', lifeStart, lifeEnd);
        
        svg.append('rect')
            .attr('x', lifeStart)
            .attr('y', overallSwimlaneY)
            .attr('width', lifeEnd - lifeStart)
            .attr('height', swimlaneHeight)
            .attr('fill', '#e9ecef')
            .attr('stroke', '#dee2e6')
            .attr('stroke-width', 1);
    }

    // Add connection swimlanes
    validConnections.forEach((connection, index) => {
        const connectionSpan = connection.connection_span;
        const hasDates = connectionSpan.start_year;
        
        // Calculate swimlane position
        const swimlaneY = overallSwimlaneY + swimlaneHeight + swimlaneSpacing + (index * (swimlaneHeight + swimlaneSpacing));
        
        // Add swimlane label
        svg.append('text')
            .attr('x', margin.left - 5)
            .attr('y', swimlaneY + swimlaneHeight / 2)
            .attr('text-anchor', 'end')
            .attr('dominant-baseline', 'middle')
            .style('font-size', '11px')
            .style('fill', '#666')
            .text(connection.other_span.name.length > 15 ? connection.other_span.name.substring(0, 15) + '...' : connection.other_span.name);
        
        // Add swimlane background
        svg.append('rect')
            .attr('x', margin.left)
            .attr('y', swimlaneY)
            .attr('width', width - margin.left - margin.right)
            .attr('height', swimlaneHeight)
            .attr('fill', '#f8f9fa')
            .attr('stroke', '#dee2e6')
            .attr('stroke-width', 1);
        
        // Create connection bar
        let barX, barWidth, barColor, barOpacity, isClickable;
        
        if (hasDates) {
            // Connection with dates - show as colored bar
            const startYear = connectionSpan.start_year;
            const endYear = connectionSpan.end_year || new Date().getFullYear(); // Ongoing if no end date
            barX = xScale(startYear);
            barWidth = xScale(endYear) - xScale(startYear);
            barColor = getConnectionColor(connectionType);
            barOpacity = 0.7;
            isClickable = true;
        } else {
            // Connection without dates - show as grey bar spanning full life
            barX = xScale(timeRange.start);
            barWidth = xScale(timeRange.end) - xScale(timeRange.start);
            barColor = '#6c757d'; // Grey color for unknown dates
            barOpacity = 0.4;
            isClickable = false;
        }
        
        const bar = svg.append('rect')
            .attr('class', 'timeline-bar')
            .attr('x', barX)
            .attr('y', swimlaneY + 2)
            .attr('width', Math.max(barWidth, 2)) // Minimum 2px width for single-year events
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
                // Navigate to the connection span
                window.location.href = `/spans/${connectionSpan.slug}`;
            });
        } else {
            // For connections without dates, show a different tooltip
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
    });
}

function calculateConnectionTimeRange_{{ str_replace('-', '_', $span->id) }}_{{ str_replace('-', '_', $connectionType->type) }}(connections, span) {
    console.log('Time range calculation - span:', span);
    let minYear = span.start_year || 1900;
    let maxYear = span.end_year || new Date().getFullYear();
    
    // Find the earliest and latest years from connections
    connections.forEach(connection => {
        const connectionSpan = connection.connection_span;
        if (connectionSpan) {
            if (connectionSpan.start_year && connectionSpan.start_year < minYear) {
                minYear = connectionSpan.start_year;
            }
            if (connectionSpan.end_year && connectionSpan.end_year > maxYear) {
                maxYear = connectionSpan.end_year;
            }
        }
    });
    
    // Add some padding
    const padding = Math.max(5, Math.floor((maxYear - minYear) * 0.1));
    minYear = Math.max(1800, minYear - padding);
    maxYear = Math.min(2030, maxYear + padding);
    
    return { start: minYear, end: maxYear };
}
</script>
@endpush
