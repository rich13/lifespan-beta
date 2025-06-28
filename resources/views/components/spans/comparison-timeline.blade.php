@props(['span1', 'span2'])

<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="bi bi-clock-history me-2"></i>
            Comparison Timeline
        </h5>
    </div>
    <div class="card-body">
        <div id="comparison-timeline-container-{{ $span1->id }}-{{ $span2->id }}" style="height: 200px; width: 100%;">
            <!-- D3 comparison timeline will be rendered here -->
        </div>
    </div>
</div>

@push('scripts')
<script src="https://d3js.org/d3.v7.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    initializeComparisonTimeline_{{ str_replace('-', '_', $span1->id) }}_{{ str_replace('-', '_', $span2->id) }}();
});

function initializeComparisonTimeline_{{ str_replace('-', '_', $span1->id) }}_{{ str_replace('-', '_', $span2->id) }}() {
    const span1Id = '{{ $span1->id }}';
    const span2Id = '{{ $span2->id }}';
    console.log('Initializing comparison timeline for spans:', span1Id, span2Id);
    
    // Fetch timeline data for both spans
    Promise.all([
        fetch(`/spans/${span1Id}/timeline`),
        fetch(`/spans/${span2Id}/timeline`)
    ])
    .then(responses => Promise.all(responses.map(r => r.json())))
    .then(([data1, data2]) => {
        console.log('API response data:', data1, data2);
        if ((data1.connections && data1.connections.length > 0) || (data2.connections && data2.connections.length > 0)) {
            console.log('Found connections:', data1.connections?.length || 0, data2.connections?.length || 0);
            renderComparisonTimeline_{{ str_replace('-', '_', $span1->id) }}_{{ str_replace('-', '_', $span2->id) }}(data1, data2);
        } else {
            console.log('No connections found in data');
            document.getElementById(`comparison-timeline-container-${span1Id}-${span2Id}`).innerHTML = 
                '<div class="text-muted text-center py-4">No timeline data available</div>';
        }
    })
    .catch(error => {
        console.error('Error loading timeline data:', error);
        document.getElementById(`comparison-timeline-container-${span1Id}-${span2Id}`).innerHTML = 
            '<div class="text-danger text-center py-4">Error loading timeline data</div>';
    });
}

function renderComparisonTimeline_{{ str_replace('-', '_', $span1->id) }}_{{ str_replace('-', '_', $span2->id) }}(data1, data2) {
    const span1Id = '{{ $span1->id }}';
    const span2Id = '{{ $span2->id }}';
    const container = document.getElementById(`comparison-timeline-container-${span1Id}-${span2Id}`);
    const width = container.clientWidth;
    const height = 200;
    const margin = { top: 20, right: 20, bottom: 30, left: 20 };
    const swimlaneHeight = 40;
    const swimlaneSpacing = 20;

    // Clear container
    container.innerHTML = '';

    // Create SVG
    const svg = d3.select(container)
        .append('svg')
        .attr('width', width)
        .attr('height', height);

    // Calculate combined time range for proportional scaling
    const timeRange = calculateComparisonTimeRange_{{ str_replace('-', '_', $span1->id) }}_{{ str_replace('-', '_', $span2->id) }}(data1, data2);
    
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

    // Define colors for different connection types
    const connectionColors = {
        'residence': '#007bff',
        'employment': '#28a745',
        'education': '#ffc107',
        'membership': '#dc3545',
        'family': '#6f42c1',
        'relationship': '#fd7e14',
        'travel': '#20c997',
        'participation': '#e83e8c',
        'ownership': '#6c757d',
        'created': '#17a2b8',
        'contains': '#6610f2',
        'has_role': '#fd7e14',
        'at_organisation': '#20c997',
        'life': '#000000' // Black for the life span
    };

    // Calculate swimlane positions
    const swimlane1Y = margin.top;
    const swimlane2Y = margin.top + swimlaneHeight + swimlaneSpacing;

    // Draw swimlane backgrounds
    svg.append('rect')
        .attr('x', margin.left)
        .attr('y', swimlane1Y)
        .attr('width', width - margin.left - margin.right)
        .attr('height', swimlaneHeight)
        .attr('fill', '#f8f9fa')
        .attr('stroke', '#dee2e6')
        .attr('stroke-width', 1)
        .attr('rx', 4)
        .attr('ry', 4);

    svg.append('rect')
        .attr('x', margin.left)
        .attr('y', swimlane2Y)
        .attr('width', width - margin.left - margin.right)
        .attr('height', swimlaneHeight)
        .attr('fill', '#f8f9fa')
        .attr('stroke', '#dee2e6')
        .attr('stroke-width', 1)
        .attr('rx', 4)
        .attr('ry', 4);

    // Add life span bars
    if (data1.span.start_year) {
        const lifeStartYear = data1.span.start_year;
        const lifeEndYear = data1.span.end_year || new Date().getFullYear();
        
        svg.append('rect')
            .attr('class', 'life-span-1')
            .attr('x', xScale(lifeStartYear))
            .attr('y', swimlane1Y + 2)
            .attr('width', xScale(lifeEndYear) - xScale(lifeStartYear))
            .attr('height', swimlaneHeight - 4)
            .attr('fill', connectionColors.life)
            .attr('stroke', 'white')
            .attr('stroke-width', 2)
            .attr('rx', 2)
            .attr('ry', 2)
            .style('opacity', 0.3)
            .style('pointer-events', 'none');
    }

    if (data2.span.start_year) {
        const lifeStartYear = data2.span.start_year;
        const lifeEndYear = data2.span.end_year || new Date().getFullYear();
        
        svg.append('rect')
            .attr('class', 'life-span-2')
            .attr('x', xScale(lifeStartYear))
            .attr('y', swimlane2Y + 2)
            .attr('width', xScale(lifeEndYear) - xScale(lifeStartYear))
            .attr('height', swimlaneHeight - 4)
            .attr('fill', connectionColors.life)
            .attr('stroke', 'white')
            .attr('stroke-width', 2)
            .attr('rx', 2)
            .attr('ry', 2)
            .style('opacity', 0.3)
            .style('pointer-events', 'none');
    }

    // Create timeline bars for span 1
    if (data1.connections && data1.connections.length > 0) {
        svg.selectAll('.timeline-bar-1')
            .data(data1.connections)
            .enter()
            .append('rect')
            .attr('class', 'timeline-bar-1')
            .attr('x', d => xScale(d.start_year))
            .attr('y', swimlane1Y + 2)
            .attr('width', d => {
                const endYear = d.end_year || new Date().getFullYear();
                return xScale(endYear) - xScale(d.start_year);
            })
            .attr('height', swimlaneHeight - 4)
            .attr('fill', d => connectionColors[d.type_id] || '#6c757d')
            .attr('stroke', 'white')
            .attr('stroke-width', 1)
            .attr('rx', 2)
            .attr('ry', 2)
            .style('opacity', 0.6)
            .on('mouseover', function(event, d) {
                const overlappingConnections = findOverlappingConnections_{{ str_replace('-', '_', $span1->id) }}_{{ str_replace('-', '_', $span2->id) }}(d, data1.connections, 1);
                d3.select(this).style('opacity', 0.9);
                showTooltip_{{ str_replace('-', '_', $span1->id) }}_{{ str_replace('-', '_', $span2->id) }}(event, overlappingConnections, 1);
            })
            .on('mouseout', function() {
                d3.select(this).style('opacity', 0.6);
                hideTooltip_{{ str_replace('-', '_', $span1->id) }}_{{ str_replace('-', '_', $span2->id) }}();
            });
    }

    // Create timeline bars for span 2
    if (data2.connections && data2.connections.length > 0) {
        svg.selectAll('.timeline-bar-2')
            .data(data2.connections)
            .enter()
            .append('rect')
            .attr('class', 'timeline-bar-2')
            .attr('x', d => xScale(d.start_year))
            .attr('y', swimlane2Y + 2)
            .attr('width', d => {
                const endYear = d.end_year || new Date().getFullYear();
                return xScale(endYear) - xScale(d.start_year);
            })
            .attr('height', swimlaneHeight - 4)
            .attr('fill', d => connectionColors[d.type_id] || '#6c757d')
            .attr('stroke', 'white')
            .attr('stroke-width', 1)
            .attr('rx', 2)
            .attr('ry', 2)
            .style('opacity', 0.6)
            .on('mouseover', function(event, d) {
                const overlappingConnections = findOverlappingConnections_{{ str_replace('-', '_', $span1->id) }}_{{ str_replace('-', '_', $span2->id) }}(d, data2.connections, 2);
                d3.select(this).style('opacity', 0.9);
                showTooltip_{{ str_replace('-', '_', $span1->id) }}_{{ str_replace('-', '_', $span2->id) }}(event, overlappingConnections, 2);
            })
            .on('mouseout', function() {
                d3.select(this).style('opacity', 0.6);
                hideTooltip_{{ str_replace('-', '_', $span1->id) }}_{{ str_replace('-', '_', $span2->id) }}();
            });
    }

    // Add interactive backgrounds for year-based tooltips
    svg.append('rect')
        .attr('x', margin.left)
        .attr('y', swimlane1Y)
        .attr('width', width - margin.left - margin.right)
        .attr('height', swimlaneHeight)
        .attr('fill', 'transparent')
        .attr('cursor', 'crosshair')
        .on('mousemove', function(event) {
            const [mouseX] = d3.pointer(event);
            const hoveredYear = Math.round(xScale.invert(mouseX));
            
            const activeConnections1 = data1.connections ? data1.connections.filter(connection => {
                const startYear = connection.start_year;
                const endYear = connection.end_year || new Date().getFullYear();
                return hoveredYear >= startYear && hoveredYear <= endYear;
            }) : [];
            
            const activeConnections2 = data2.connections ? data2.connections.filter(connection => {
                const startYear = connection.start_year;
                const endYear = connection.end_year || new Date().getFullYear();
                return hoveredYear >= startYear && hoveredYear <= endYear;
            }) : [];
            
            if (activeConnections1.length > 0 || activeConnections2.length > 0) {
                showYearTooltip_{{ str_replace('-', '_', $span1->id) }}_{{ str_replace('-', '_', $span2->id) }}(event, hoveredYear, activeConnections1, activeConnections2);
            } else {
                hideTooltip_{{ str_replace('-', '_', $span1->id) }}_{{ str_replace('-', '_', $span2->id) }}();
            }
        })
        .on('mouseout', function() {
            hideTooltip_{{ str_replace('-', '_', $span1->id) }}_{{ str_replace('-', '_', $span2->id) }}();
        });

    svg.append('rect')
        .attr('x', margin.left)
        .attr('y', swimlane2Y)
        .attr('width', width - margin.left - margin.right)
        .attr('height', swimlaneHeight)
        .attr('fill', 'transparent')
        .attr('cursor', 'crosshair')
        .on('mousemove', function(event) {
            const [mouseX] = d3.pointer(event);
            const hoveredYear = Math.round(xScale.invert(mouseX));
            
            const activeConnections1 = data1.connections ? data1.connections.filter(connection => {
                const startYear = connection.start_year;
                const endYear = connection.end_year || new Date().getFullYear();
                return hoveredYear >= startYear && hoveredYear <= endYear;
            }) : [];
            
            const activeConnections2 = data2.connections ? data2.connections.filter(connection => {
                const startYear = connection.start_year;
                const endYear = connection.end_year || new Date().getFullYear();
                return hoveredYear >= startYear && hoveredYear <= endYear;
            }) : [];
            
            if (activeConnections1.length > 0 || activeConnections2.length > 0) {
                showYearTooltip_{{ str_replace('-', '_', $span1->id) }}_{{ str_replace('-', '_', $span2->id) }}(event, hoveredYear, activeConnections1, activeConnections2);
            } else {
                hideTooltip_{{ str_replace('-', '_', $span1->id) }}_{{ str_replace('-', '_', $span2->id) }}();
            }
        })
        .on('mouseout', function() {
            hideTooltip_{{ str_replace('-', '_', $span1->id) }}_{{ str_replace('-', '_', $span2->id) }}();
        });

    // Create tooltip (unique to this comparison timeline instance)
    const tooltip = d3.select('body').append('div')
        .attr('class', `comparison-tooltip-${span1Id}-${span2Id}`)
        .style('position', 'absolute')
        .style('background', 'rgba(0, 0, 0, 0.8)')
        .style('color', 'white')
        .style('padding', '8px')
        .style('border-radius', '4px')
        .style('font-size', '12px')
        .style('pointer-events', 'none')
        .style('opacity', 0);

    function findOverlappingConnections_{{ str_replace('-', '_', $span1->id) }}_{{ str_replace('-', '_', $span2->id) }}(targetConnection, allConnections, spanNumber) {
        return allConnections.filter(connection => {
            const targetStart = targetConnection.start_year;
            const targetEnd = targetConnection.end_year || new Date().getFullYear();
            const connectionStart = connection.start_year;
            const connectionEnd = connection.end_year || new Date().getFullYear();
            return (targetStart <= connectionEnd && targetEnd >= connectionStart);
        });
    }

    function showTooltip_{{ str_replace('-', '_', $span1->id) }}_{{ str_replace('-', '_', $span2->id) }}(event, connections, spanNumber) {
        tooltip.transition()
            .duration(200)
            .style('opacity', 1);
        
        let tooltipContent = '';
        const spanName = spanNumber === 1 ? '{{ $span1->name }}' : '{{ $span2->name }}';
        
        if (connections.length === 1) {
            const d = connections[0];
            const endYear = d.end_year || 'Present';
            tooltipContent = `
                <strong>${spanName}</strong><br/>
                <strong>${d.type_name} ${d.target_name}</strong><br/>
                ${d.start_year} - ${endYear}
            `;
        } else {
            tooltipContent = `<strong>${spanName} - ${connections.length} overlapping connections:</strong><br/>`;
            connections.forEach((d, index) => {
                const endYear = d.end_year || 'Present';
                const bulletColor = connectionColors[d.type_id] || '#6c757d';
                tooltipContent += `
                    <span style="color: ${bulletColor};">●</span> <strong>${d.type_name} ${d.target_name}</strong><br/>
                    &nbsp;&nbsp;&nbsp;&nbsp;${d.start_year} - ${endYear}<br/>
                `;
            });
        }
        
        tooltip.html(tooltipContent)
            .style('left', (event.pageX - 50) + 'px')
            .style('top', (event.pageY + 20) + 'px');
    }

    function showYearTooltip_{{ str_replace('-', '_', $span1->id) }}_{{ str_replace('-', '_', $span2->id) }}(event, year, connections1, connections2) {
        tooltip.transition()
            .duration(200)
            .style('opacity', 1);
        
        let tooltipContent = `<strong>Year ${year}</strong><br/>`;
        
        if (connections1.length > 0) {
            tooltipContent += `<br/><strong>{{ $span1->name }}:</strong><br/>`;
            connections1.forEach((d, index) => {
                const endYear = d.end_year || 'Present';
                const bulletColor = connectionColors[d.type_id] || '#6c757d';
                tooltipContent += `
                    <span style="color: ${bulletColor};">●</span> <strong>${d.type_name} ${d.target_name}</strong><br/>
                    &nbsp;&nbsp;&nbsp;&nbsp;${d.start_year} - ${endYear}<br/>
                `;
            });
        }
        
        if (connections2.length > 0) {
            tooltipContent += `<br/><strong>{{ $span2->name }}:</strong><br/>`;
            connections2.forEach((d, index) => {
                const endYear = d.end_year || 'Present';
                const bulletColor = connectionColors[d.type_id] || '#6c757d';
                tooltipContent += `
                    <span style="color: ${bulletColor};">●</span> <strong>${d.type_name} ${d.target_name}</strong><br/>
                    &nbsp;&nbsp;&nbsp;&nbsp;${d.start_year} - ${endYear}<br/>
                `;
            });
        }
        
        tooltip.html(tooltipContent)
            .style('left', (event.pageX - 50) + 'px')
            .style('top', (event.pageY + 20) + 'px');
    }

    function hideTooltip_{{ str_replace('-', '_', $span1->id) }}_{{ str_replace('-', '_', $span2->id) }}() {
        tooltip.transition()
            .duration(500)
            .style('opacity', 0);
    }
}

function calculateComparisonTimeRange_{{ str_replace('-', '_', $span1->id) }}_{{ str_replace('-', '_', $span2->id) }}(data1, data2) {
    let start = Math.min(
        data1.span.start_year || 1900,
        data2.span.start_year || 1900
    );
    let end = Math.max(
        data1.span.end_year || new Date().getFullYear(),
        data2.span.end_year || new Date().getFullYear()
    );

    // Extend range to include all connections from both spans
    const allConnections = [
        ...(data1.connections || []),
        ...(data2.connections || [])
    ];

    allConnections.forEach(connection => {
        if (connection.start_year && connection.start_year < start) {
            start = connection.start_year;
        }
        if (connection.end_year && connection.end_year > end) {
            end = connection.end_year;
        }
    });

    // Add some padding
    const padding = Math.max(5, Math.floor((end - start) * 0.1));
    return {
        start: start - padding,
        end: end + padding
    };
}
</script>
@endpush 