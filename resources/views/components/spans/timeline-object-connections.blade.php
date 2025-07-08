@props(['span'])

<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="bi bi-arrow-down-circle me-2"></i>
            Incoming Connections Timeline
        </h5>
    </div>
    <div class="card-body">
        <div id="timeline-object-container-{{ $span->id }}" style="height: 120px; width: 100%;">
            <!-- D3 timeline will be rendered here -->
        </div>
    </div>
</div>

@push('scripts')
<script src="https://d3js.org/d3.v7.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    initializeObjectTimeline_{{ str_replace('-', '_', $span->id) }}();
});

function initializeObjectTimeline_{{ str_replace('-', '_', $span->id) }}() {
    const spanId = '{{ $span->id }}';
    // Consistent with the new API structure: use /api/spans/${spanId}/object-connections
    fetch(`/api/spans/${spanId}/object-connections`, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
        .then(response => response.json())
        .then(data => {
            renderObjectTimeline_{{ str_replace('-', '_', $span->id) }}(data.connections || [], data.span);
        })
        .catch(error => {
            document.getElementById(`timeline-object-container-${spanId}`).innerHTML = 
                '<div class="text-danger text-center py-4">Error loading object timeline data</div>';
        });
}

function renderObjectTimeline_{{ str_replace('-', '_', $span->id) }}(connections, span) {
    const spanId = '{{ $span->id }}';
    const container = document.getElementById(`timeline-object-container-${spanId}`);
    const width = container.clientWidth;
    const height = 120;
    const margin = { top: 20, right: 20, bottom: 30, left: 20 };

    container.innerHTML = '';
    const svg = d3.select(container)
        .append('svg')
        .attr('width', width)
        .attr('height', height);

    const timeRange = calculateObjectTimeRange_{{ str_replace('-', '_', $span->id) }}(connections, span);
    const xScale = d3.scaleLinear()
        .domain([timeRange.start, timeRange.end])
        .range([margin.left, width - margin.right]);
    const xAxis = d3.axisBottom(xScale)
        .tickFormat(d3.format('d'))
        .ticks(10);
    svg.append('g')
        .attr('transform', `translate(0, ${height - margin.bottom})`)
        .call(xAxis);

    function getConnectionColor(typeId) {
        const cssColor = getComputedStyle(document.documentElement)
            .getPropertyValue(`--connection-${typeId}-color`);
        if (cssColor && cssColor.trim() !== '') return cssColor.trim();
        const testElement = document.createElement('div');
        testElement.className = `bg-${typeId}`;
        testElement.style.display = 'none';
        document.body.appendChild(testElement);
        const computedStyle = getComputedStyle(testElement);
        const backgroundColor = computedStyle.backgroundColor;
        document.body.removeChild(testElement);
        if (backgroundColor && backgroundColor !== 'rgba(0, 0, 0, 0)' && backgroundColor !== 'transparent') return backgroundColor;
        const fallbackColors = {
            'residence': '#007bff', 'employment': '#28a745', 'education': '#ffc107', 'membership': '#dc3545',
            'family': '#6f42c1', 'relationship': '#fd7e14', 'travel': '#20c997', 'participation': '#e83e8c',
            'ownership': '#6c757d', 'created': '#17a2b8', 'contains': '#6610f2', 'has_role': '#fd7e14',
            'at_organisation': '#20c997', 'life': '#000000'
        };
        return fallbackColors[typeId] || '#6c757d';
    }
    const connectionColors = { 'life': '#000000' };
    const swimlaneHeight = 40;
    const swimlaneY = (height - margin.top - margin.bottom - swimlaneHeight) / 2 + margin.top;
    svg.append('rect')
        .attr('x', margin.left)
        .attr('y', swimlaneY)
        .attr('width', width - margin.left - margin.right)
        .attr('height', swimlaneHeight)
        .attr('fill', '#f8f9fa')
        .attr('stroke', '#dee2e6')
        .attr('stroke-width', 1)
        .attr('rx', 4)
        .attr('ry', 4);
    if (span.start_year) {
        const lifeStartYear = span.start_year;
        const lifeEndYear = span.end_year || new Date().getFullYear();
        const hasConnections = connections.length > 0;
        svg.append('rect')
            .attr('class', 'life-span')
            .attr('x', xScale(lifeStartYear))
            .attr('y', swimlaneY + 2)
            .attr('width', xScale(lifeEndYear) - xScale(lifeStartYear))
            .attr('height', swimlaneHeight - 4)
            .attr('fill', connectionColors.life)
            .attr('stroke', 'white')
            .attr('stroke-width', hasConnections ? 2 : 3)
            .attr('rx', 2)
            .attr('ry', 2)
            .style('opacity', hasConnections ? 0.3 : 0.7)
            .style('pointer-events', hasConnections ? 'none' : 'auto')
            .on('mouseover', function(event) {
                if (!hasConnections) {
                    d3.select(this).style('opacity', 0.9);
                    showObjectLifeSpanTooltip_{{ str_replace('-', '_', $span->id) }}(event, span);
                }
            })
            .on('mouseout', function() {
                if (!hasConnections) {
                    d3.select(this).style('opacity', 0.7);
                    hideObjectTooltip_{{ str_replace('-', '_', $span->id) }}();
                }
            });
    }
    svg.selectAll('.timeline-bar')
        .data(connections)
        .enter()
        .each(function(d, i) {
            const connection = d;
            const connectionType = connection.type_id;
            if (connectionType === 'created') {
                const x = xScale(connection.start_year);
                const y1 = swimlaneY;
                const y2 = swimlaneY + swimlaneHeight;
                const circleY = (y1 + y2) / 2;
                const circleRadius = 3;
                svg.append('line')
                    .attr('class', 'timeline-moment')
                    .attr('x1', x)
                    .attr('x2', x)
                    .attr('y1', y1)
                    .attr('y2', y2)
                    .attr('stroke', getConnectionColor(connectionType))
                    .attr('stroke-width', 2)
                    .style('opacity', 0.8)
                    .on('mouseover', function(event) {
                        const overlappingConnections = findOverlappingObjectConnections_{{ str_replace('-', '_', $span->id) }}(connection, connections);
                        d3.select(this).style('opacity', 0.9);
                        showObjectTooltip_{{ str_replace('-', '_', $span->id) }}(event, overlappingConnections);
                    })
                    .on('mouseout', function() {
                        d3.select(this).style('opacity', 0.8);
                        hideObjectTooltip_{{ str_replace('-', '_', $span->id) }}();
                    });
                svg.append('circle')
                    .attr('class', 'timeline-moment-circle')
                    .attr('cx', x)
                    .attr('cy', circleY)
                    .attr('r', circleRadius)
                    .attr('fill', getConnectionColor(connectionType))
                    .attr('stroke', 'white')
                    .attr('stroke-width', 1)
                    .style('opacity', 0.9)
                    .on('mouseover', function(event) {
                        const overlappingConnections = findOverlappingObjectConnections_{{ str_replace('-', '_', $span->id) }}(connection, connections);
                        d3.select(this).style('opacity', 1);
                        showObjectTooltip_{{ str_replace('-', '_', $span->id) }}(event, overlappingConnections);
                    })
                    .on('mouseout', function() {
                        d3.select(this).style('opacity', 0.9);
                        hideObjectTooltip_{{ str_replace('-', '_', $span->id) }}();
                    });
            } else {
                const endYear = connection.end_year || new Date().getFullYear();
                const width = xScale(endYear) - xScale(connection.start_year);
                svg.append('rect')
                    .attr('class', 'timeline-bar')
                    .attr('x', xScale(connection.start_year))
                    .attr('y', swimlaneY + 2)
                    .attr('width', Math.max(1, width))
                    .attr('height', swimlaneHeight - 4)
                    .attr('fill', getConnectionColor(connectionType))
                    .attr('stroke', 'white')
                    .attr('stroke-width', 1)
                    .attr('rx', 2)
                    .attr('ry', 2)
                    .style('opacity', 0.6)
                    .on('mouseover', function(event) {
                        const overlappingConnections = findOverlappingObjectConnections_{{ str_replace('-', '_', $span->id) }}(connection, connections);
                        d3.select(this).style('opacity', 0.9);
                        showObjectTooltip_{{ str_replace('-', '_', $span->id) }}(event, overlappingConnections);
                    })
                    .on('mouseout', function() {
                        d3.select(this).style('opacity', 0.6);
                        hideObjectTooltip_{{ str_replace('-', '_', $span->id) }}();
                    });
            }
        });
    svg.append('rect')
        .attr('x', margin.left)
        .attr('y', swimlaneY)
        .attr('width', width - margin.left - margin.right)
        .attr('height', swimlaneHeight)
        .attr('fill', 'transparent')
        .attr('cursor', 'crosshair')
        .on('mousemove', function(event) {
            const [mouseX] = d3.pointer(event);
            const hoveredYear = Math.round(xScale.invert(mouseX));
            const activeConnections = connections.filter(connection => {
                const startYear = connection.start_year;
                const endYear = connection.end_year || new Date().getFullYear();
                return hoveredYear >= startYear && hoveredYear <= endYear;
            });
            if (activeConnections.length > 0) {
                showObjectYearTooltip_{{ str_replace('-', '_', $span->id) }}(event, hoveredYear, activeConnections);
            } else {
                hideObjectTooltip_{{ str_replace('-', '_', $span->id) }}();
            }
        })
        .on('mouseout', function() {
            hideObjectTooltip_{{ str_replace('-', '_', $span->id) }}();
        });
    const tooltip = d3.select('body').append('div')
        .attr('class', `object-tooltip-${spanId}`)
        .style('position', 'absolute')
        .style('background', 'rgba(0, 0, 0, 0.8)')
        .style('color', 'white')
        .style('padding', '8px')
        .style('border-radius', '4px')
        .style('font-size', '12px')
        .style('pointer-events', 'none')
        .style('opacity', 0);
    function findOverlappingObjectConnections_{{ str_replace('-', '_', $span->id) }}(targetConnection, allConnections) {
        return allConnections.filter(connection => {
            const targetStart = targetConnection.start_year;
            const targetEnd = targetConnection.end_year || new Date().getFullYear();
            const connectionStart = connection.start_year;
            const connectionEnd = connection.end_year || new Date().getFullYear();
            return (targetStart <= connectionEnd && targetEnd >= connectionStart);
        });
    }
    function showObjectTooltip_{{ str_replace('-', '_', $span->id) }}(event, connections) {
        tooltip.transition().duration(200).style('opacity', 1);
        let tooltipContent = '';
        if (connections.length === 1) {
            const d = connections[0];
            const endYear = d.end_year || 'Present';
            tooltipContent = `<strong>${d.type_name} by ${d.target_name}</strong><br/>${d.start_year} - ${endYear}`;
        } else {
            tooltipContent = `<strong>${connections.length} overlapping incoming connections:</strong><br/>`;
            connections.forEach((d, index) => {
                const endYear = d.end_year || 'Present';
                const bulletColor = getConnectionColor(d.type_id);
                tooltipContent += `<span style="color: ${bulletColor};">●</span> <strong>${d.type_name} by ${d.target_name}</strong><br/>&nbsp;&nbsp;&nbsp;&nbsp;${d.start_year} - ${endYear}<br/>`;
            });
        }
        tooltip.html(tooltipContent)
            .style('left', (event.pageX - 50) + 'px')
            .style('top', (event.pageY + 20) + 'px');
    }
    function showObjectYearTooltip_{{ str_replace('-', '_', $span->id) }}(event, year, connections) {
        tooltip.transition().duration(200).style('opacity', 1);
        let tooltipContent = `<strong>Year ${year} - Incoming Connections:</strong><br/>`;
        connections.forEach((d, index) => {
            const endYear = d.end_year || 'Present';
            const bulletColor = getConnectionColor(d.type_id);
            tooltipContent += `<span style="color: ${bulletColor};">●</span> <strong>${d.type_name} by ${d.target_name}</strong><br/>&nbsp;&nbsp;&nbsp;&nbsp;${d.start_year} - ${endYear}<br/>`;
        });
        tooltip.html(tooltipContent)
            .style('left', (event.pageX - 50) + 'px')
            .style('top', (event.pageY + 20) + 'px');
    }
    function showObjectLifeSpanTooltip_{{ str_replace('-', '_', $span->id) }}(event, span) {
        tooltip.transition().duration(200).style('opacity', 1);
        const endYear = span.end_year || 'Present';
        const tooltipContent = `<strong>${span.name}'s Life</strong><br/>${span.start_year} - ${endYear}`;
        tooltip.html(tooltipContent)
            .style('left', (event.pageX - 50) + 'px')
            .style('top', (event.pageY + 20) + 'px');
    }
    function hideObjectTooltip_{{ str_replace('-', '_', $span->id) }}() {
        tooltip.transition().duration(500).style('opacity', 0);
    }
    function calculateObjectTimeRange_{{ str_replace('-', '_', $span->id) }}(connections, span) {
        let start = span.start_year || 1900;
        let end = span.end_year || new Date().getFullYear();
        connections.forEach(connection => {
            if (connection.start_year && connection.start_year < start) {
                start = connection.start_year;
            }
            if (connection.end_year && connection.end_year > end) {
                end = connection.end_year;
            }
        });
        const padding = Math.max(5, Math.floor((end - start) * 0.1));
        return { start: start - padding, end: end + padding };
    }
}
</script>
@endpush 