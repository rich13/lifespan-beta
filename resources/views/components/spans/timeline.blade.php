@props(['span'])

<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="bi bi-clock-history me-2"></i>
            Timeline
        </h5>
    </div>
    <div class="card-body">
        <div id="timeline-container-{{ $span->id }}" style="height: 120px; width: 100%;">
            <!-- D3 timeline will be rendered here -->
        </div>
    </div>
</div>

@push('scripts')
<script src="https://d3js.org/d3.v7.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    initializeTimeline_{{ str_replace('-', '_', $span->id) }}();
});

function initializeTimeline_{{ str_replace('-', '_', $span->id) }}() {
    const spanId = '{{ $span->id }}';
    console.log('Initializing timeline for span:', spanId);
    
    // Fetch timeline data
    fetch(`/spans/${spanId}/timeline`)
        .then(response => {
            console.log('API response status:', response.status);
            return response.json();
        })
        .then(data => {
            console.log('API response data:', data);
            if (data.connections && data.connections.length > 0) {
                console.log('Found connections:', data.connections.length);
                renderTimeline_{{ str_replace('-', '_', $span->id) }}(data.connections, data.span);
            } else {
                console.log('No connections found in data');
                document.getElementById(`timeline-container-${spanId}`).innerHTML = 
                    '<div class="text-muted text-center py-4">No timeline data available</div>';
            }
        })
        .catch(error => {
            console.error('Error loading timeline data:', error);
            document.getElementById(`timeline-container-${spanId}`).innerHTML = 
                '<div class="text-danger text-center py-4">Error loading timeline data</div>';
        });
}

function renderTimeline_{{ str_replace('-', '_', $span->id) }}(connections, span) {
    const spanId = '{{ $span->id }}';
    const container = document.getElementById(`timeline-container-${spanId}`);
    const width = container.clientWidth;
    const height = 120;
    const margin = { top: 20, right: 20, bottom: 30, left: 20 };

    // Clear container
    container.innerHTML = '';

    // Create SVG
    const svg = d3.select(container)
        .append('svg')
        .attr('width', width)
        .attr('height', height);

    // Calculate time range
    const timeRange = calculateTimeRange_{{ str_replace('-', '_', $span->id) }}(connections, span);
    
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

    // Define colors for different connection types - now reading from CSS
    function getConnectionColor(typeId) {
        // Try to get color from CSS custom property first
        const cssColor = getComputedStyle(document.documentElement)
            .getPropertyValue(`--connection-${typeId}-color`);
        
        if (cssColor && cssColor.trim() !== '') {
            return cssColor.trim();
        }
        
        // Fallback to a function that reads from the existing CSS classes
        const testElement = document.createElement('div');
        testElement.className = `bg-${typeId}`;
        testElement.style.display = 'none';
        document.body.appendChild(testElement);
        
        const computedStyle = getComputedStyle(testElement);
        const backgroundColor = computedStyle.backgroundColor;
        
        document.body.removeChild(testElement);
        
        // If we got a valid color, return it
        if (backgroundColor && backgroundColor !== 'rgba(0, 0, 0, 0)' && backgroundColor !== 'transparent') {
            return backgroundColor;
        }
        
        // Final fallback colors
        const fallbackColors = {
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
        
        return fallbackColors[typeId] || '#6c757d';
    }

    const connectionColors = {
        'life': '#000000' // Keep life span color as black
    };

    // Create a single swimlane
    const swimlaneHeight = 40;
    const swimlaneY = (height - margin.top - margin.bottom - swimlaneHeight) / 2 + margin.top;

    // Draw the swimlane background
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

    // Add life span bar (if the span has temporal data)
    if (span.start_year) {
        const lifeStartYear = span.start_year;
        const lifeEndYear = span.end_year || new Date().getFullYear();
        
        svg.append('rect')
            .attr('class', 'life-span')
            .attr('x', xScale(lifeStartYear))
            .attr('y', swimlaneY + 2)
            .attr('width', xScale(lifeEndYear) - xScale(lifeStartYear))
            .attr('height', swimlaneHeight - 4)
            .attr('fill', connectionColors.life)
            .attr('stroke', 'white')
            .attr('stroke-width', 2) // Thicker border to make it stand out
            .attr('rx', 2)
            .attr('ry', 2)
            .style('opacity', 0.3) // Very transparent so connections show through
            .style('pointer-events', 'none'); // Don't interfere with connection hover
    }

    // Create timeline bars (all in the same swimlane)
    svg.selectAll('.timeline-bar')
        .data(connections)
        .enter()
        .append('rect')
        .attr('class', 'timeline-bar')
        .attr('x', d => xScale(d.start_year))
        .attr('y', swimlaneY + 2) // Small margin from swimlane edge
        .attr('width', d => {
            const endYear = d.end_year || new Date().getFullYear();
            return xScale(endYear) - xScale(d.start_year);
        })
        .attr('height', swimlaneHeight - 4) // Leave small margin
        .attr('fill', d => getConnectionColor(d.type_id))
        .attr('stroke', 'white') // White border
        .attr('stroke-width', 1) // 1px border width
        .attr('rx', 2)
        .attr('ry', 2)
        .style('opacity', 0.6) // More transparent default opacity
        .on('mouseover', function(event, d) {
            // Find all overlapping connections
            const overlappingConnections = findOverlappingConnections_{{ str_replace('-', '_', $span->id) }}(d, connections);
            d3.select(this).style('opacity', 0.9); // Less transparent on hover
            showTooltip_{{ str_replace('-', '_', $span->id) }}(event, overlappingConnections);
        })
        .on('mouseout', function() {
            d3.select(this).style('opacity', 0.6); // Back to default transparency
            hideTooltip_{{ str_replace('-', '_', $span->id) }}();
        });

    // Add interactive background for year-based tooltips
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
            
            // Find all connections active in this year
            const activeConnections = connections.filter(connection => {
                const startYear = connection.start_year;
                const endYear = connection.end_year || new Date().getFullYear();
                return hoveredYear >= startYear && hoveredYear <= endYear;
            });
            
            if (activeConnections.length > 0) {
                showYearTooltip_{{ str_replace('-', '_', $span->id) }}(event, hoveredYear, activeConnections);
            } else {
                hideTooltip_{{ str_replace('-', '_', $span->id) }}();
            }
        })
        .on('mouseout', function() {
            hideTooltip_{{ str_replace('-', '_', $span->id) }}();
        });

    // Create tooltip (unique to this timeline instance)
    const tooltip = d3.select('body').append('div')
        .attr('class', `tooltip-${spanId}`)
        .style('position', 'absolute')
        .style('background', 'rgba(0, 0, 0, 0.8)')
        .style('color', 'white')
        .style('padding', '8px')
        .style('border-radius', '4px')
        .style('font-size', '12px')
        .style('pointer-events', 'none')
        .style('opacity', 0);

    function findOverlappingConnections_{{ str_replace('-', '_', $span->id) }}(targetConnection, allConnections) {
        return allConnections.filter(connection => {
            // Check if connections overlap in time
            const targetStart = targetConnection.start_year;
            const targetEnd = targetConnection.end_year || new Date().getFullYear();
            const connectionStart = connection.start_year;
            const connectionEnd = connection.end_year || new Date().getFullYear();
            
            // Check for overlap: one starts before the other ends and ends after the other starts
            return (targetStart <= connectionEnd && targetEnd >= connectionStart);
        });
    }

    function showTooltip_{{ str_replace('-', '_', $span->id) }}(event, connections) {
        tooltip.transition()
            .duration(200)
            .style('opacity', 1);
        
        let tooltipContent = '';
        
        if (connections.length === 1) {
            // Single connection
            const d = connections[0];
            const endYear = d.end_year || 'Present';
            tooltipContent = `
                <strong>${d.type_name} ${d.target_name}</strong><br/>
                ${d.start_year} - ${endYear}
            `;
        } else {
            // Multiple overlapping connections
            tooltipContent = `<strong>${connections.length} overlapping connections:</strong><br/>`;
            connections.forEach((d, index) => {
                const endYear = d.end_year || 'Present';
                const bulletColor = getConnectionColor(d.type_id);
                tooltipContent += `
                    <span style="color: ${bulletColor};">●</span> <strong>${d.type_name} ${d.target_name}</strong><br/>
                    &nbsp;&nbsp;&nbsp;&nbsp;${d.start_year} - ${endYear}<br/>
                `;
            });
        }
        
        tooltip.html(tooltipContent)
            .style('left', (event.pageX - 50) + 'px') // Center horizontally
            .style('top', (event.pageY + 20) + 'px'); // Position below cursor with distance
    }

    function showYearTooltip_{{ str_replace('-', '_', $span->id) }}(event, year, connections) {
        tooltip.transition()
            .duration(200)
            .style('opacity', 1);
        
        let tooltipContent = `<strong>Year ${year}</strong><br/>`;
        
        connections.forEach((d, index) => {
            const endYear = d.end_year || 'Present';
            const bulletColor = getConnectionColor(d.type_id);
            tooltipContent += `
                <span style="color: ${bulletColor};">●</span> <strong>${d.type_name} ${d.target_name}</strong><br/>
                &nbsp;&nbsp;&nbsp;&nbsp;${d.start_year} - ${endYear}<br/>
            `;
        });
        
        tooltip.html(tooltipContent)
            .style('left', (event.pageX - 50) + 'px') // Center horizontally
            .style('top', (event.pageY + 20) + 'px'); // Position below cursor with distance
    }

    function hideTooltip_{{ str_replace('-', '_', $span->id) }}() {
        tooltip.transition()
            .duration(500)
            .style('opacity', 0);
    }
}

function calculateTimeRange_{{ str_replace('-', '_', $span->id) }}(connections, span) {
    let start = span.start_year || 1900;
    let end = span.end_year || new Date().getFullYear();

    // Extend range to include all connections
    connections.forEach(connection => {
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