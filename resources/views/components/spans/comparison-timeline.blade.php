@props(['span1', 'span2'])

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">
            <i class="bi bi-clock-history me-2"></i>
            Comparison Timeline
        </h5>
        <div class="btn-group btn-group-sm" role="group">
            <input type="radio" class="btn-check" name="timeline-mode-{{ $span1->id }}-{{ $span2->id }}" id="absolute-mode-{{ $span1->id }}-{{ $span2->id }}" value="absolute" checked>
            <label class="btn btn-outline-primary" for="absolute-mode-{{ $span1->id }}-{{ $span2->id }}">
                Absolute
            </label>
            
            <input type="radio" class="btn-check" name="timeline-mode-{{ $span1->id }}-{{ $span2->id }}" id="relative-mode-{{ $span1->id }}-{{ $span2->id }}" value="relative">
            <label class="btn btn-outline-primary" for="relative-mode-{{ $span1->id }}-{{ $span2->id }}">
                Relative
            </label>
        </div>
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
    try {
        initializeComparisonTimeline_{{ str_replace('-', '_', $span1->id) }}_{{ str_replace('-', '_', $span2->id) }}();
    } catch (error) {
        console.error('Error initializing comparison timeline:', error);
        const container = document.getElementById(`comparison-timeline-container-{{ $span1->id }}-{{ $span2->id }}`);
        if (container) {
            container.innerHTML = '<div class="text-danger text-center py-4">Error loading timeline data</div>';
        }
    }
});

function initializeComparisonTimeline_{{ str_replace('-', '_', $span1->id) }}_{{ str_replace('-', '_', $span2->id) }}() {
    const span1Id = '{{ $span1->id }}';
    const span2Id = '{{ $span2->id }}';
    
    // Fetch timeline data for both spans
    Promise.all([
        fetch(`/api/spans/${span1Id}`, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } }),
        fetch(`/api/spans/${span2Id}`, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
    ])
    .then(responses => Promise.all(responses.map(r => r.json())))
    .then(([data1, data2]) => {
        if ((data1.connections && data1.connections.length > 0) || (data2.connections && data2.connections.length > 0)) {
            renderComparisonTimeline_{{ str_replace('-', '_', $span1->id) }}_{{ str_replace('-', '_', $span2->id) }}(data1, data2, 'absolute');
            
            // Add event listeners for mode toggle
            setupModeToggle_{{ str_replace('-', '_', $span1->id) }}_{{ str_replace('-', '_', $span2->id) }}(data1, data2);
            
            // Demonstrate the toggle feature after a brief delay
            setTimeout(() => {
                const relativeRadio = document.getElementById(`relative-mode-${span1Id}-${span2Id}`);
                const absoluteRadio = document.getElementById(`absolute-mode-${span1Id}-${span2Id}`);
                
                if (relativeRadio && absoluteRadio) {
                    // Switch to relative mode
                    relativeRadio.checked = true;
                    relativeRadio.dispatchEvent(new Event('change'));
                    
                    // Switch back to absolute mode after animation completes
                    setTimeout(() => {
                        absoluteRadio.checked = true;
                        absoluteRadio.dispatchEvent(new Event('change'));
                    }, 1000); // Wait for the first animation to complete
                }
            }, 1500); // Wait 1.5 seconds after initial load
        } else {
            document.getElementById(`comparison-timeline-container-${span1Id}-${span2Id}`).innerHTML = 
                '<div class="text-muted text-center py-4">No timeline data available</div>';
        }
    })
    .catch(error => {
        document.getElementById(`comparison-timeline-container-${span1Id}-${span2Id}`).innerHTML = 
            '<div class="text-danger text-center py-4">Error loading timeline data</div>';
    });
}

function setupModeToggle_{{ str_replace('-', '_', $span1->id) }}_{{ str_replace('-', '_', $span2->id) }}(data1, data2) {
    const span1Id = '{{ $span1->id }}';
    const span2Id = '{{ $span2->id }}';
    
    // Handle mode toggle
    document.querySelectorAll(`input[name="timeline-mode-${span1Id}-${span2Id}"]`).forEach(radio => {
        radio.addEventListener('change', function() {
            const selectedMode = this.value;
            
            if (this.checked) {
                // Re-render timeline with new mode (will update existing elements with transitions)
                renderComparisonTimeline_{{ str_replace('-', '_', $span1->id) }}_{{ str_replace('-', '_', $span2->id) }}(data1, data2, selectedMode);
            }
        });
    });
}

function renderComparisonTimeline_{{ str_replace('-', '_', $span1->id) }}_{{ str_replace('-', '_', $span2->id) }}(data1, data2, mode = 'absolute') {
    try {
    const span1Id = '{{ $span1->id }}';
    const span2Id = '{{ $span2->id }}';
    
    const container = document.getElementById(`comparison-timeline-container-${span1Id}-${span2Id}`);
    const width = container.clientWidth;
    const height = 200;
    const margin = { top: 20, right: 20, bottom: 30, left: 20 };
    const swimlaneHeight = 40;
    const swimlaneSpacing = 20;

    // Check if this is an update (timeline already exists)
    const existingSvg = d3.select(container).select('svg');
    const isUpdate = !existingSvg.empty();

    if (!isUpdate) {
        // Clear container only on first render
        container.innerHTML = '';

        // Create SVG
        const svg = d3.select(container)
            .append('svg')
            .attr('width', width)
            .attr('height', height);

        // Draw swimlane backgrounds
        svg.append('rect')
            .attr('x', margin.left)
            .attr('y', margin.top)
            .attr('width', width - margin.left - margin.right)
            .attr('height', swimlaneHeight)
            .attr('fill', '#f8f9fa')
            .attr('stroke', '#dee2e6')
            .attr('stroke-width', 1)
            .attr('rx', 4)
            .attr('ry', 4);

        svg.append('rect')
            .attr('x', margin.left)
            .attr('y', margin.top + swimlaneHeight + swimlaneSpacing)
            .attr('width', width - margin.left - margin.right)
            .attr('height', swimlaneHeight)
            .attr('fill', '#f8f9fa')
            .attr('stroke', '#dee2e6')
            .attr('stroke-width', 1)
            .attr('rx', 4)
            .attr('ry', 4);
    }

    const svg = d3.select(container).select('svg');

    // Calculate time range based on mode
    const timeRange = mode === 'absolute' 
        ? calculateComparisonTimeRange_{{ str_replace('-', '_', $span1->id) }}_{{ str_replace('-', '_', $span2->id) }}(data1, data2)
        : calculateRelativeTimeRange_{{ str_replace('-', '_', $span1->id) }}_{{ str_replace('-', '_', $span2->id) }}(data1, data2);
    
    // Create scales
    const xScale = d3.scaleLinear()
        .domain([timeRange.start, timeRange.end])
        .range([margin.left, width - margin.right]);

    // Create axis with appropriate formatting
    const xAxis = d3.axisBottom(xScale)
        .tickFormat(d3.format('d'))
        .ticks(10);

    // Update or create axis with transition
    const axisGroup = svg.select('.x-axis');
    if (axisGroup.empty()) {
        svg.append('g')
            .attr('class', 'x-axis')
            .attr('transform', `translate(0, ${height - margin.bottom})`)
            .call(xAxis);
    } else {
        axisGroup.transition()
            .duration(750)
            .call(xAxis);
    }

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

    // Calculate swimlane positions
    const swimlane1Y = margin.top;
    const swimlane2Y = margin.top + swimlaneHeight + swimlaneSpacing;

    // Update or create life span bars with animation
    if (data1.span.start_year) {
        const lifeStartYear = mode === 'absolute' ? data1.span.start_year : 0;
        const lifeEndYear = mode === 'absolute' 
            ? (data1.span.end_year || new Date().getFullYear())
            : (data1.span.end_year ? data1.span.end_year - data1.span.start_year : new Date().getFullYear() - data1.span.start_year);
        
        const lifeSpan1 = svg.select('.life-span-1');
        if (lifeSpan1.empty()) {
            svg.append('rect')
                .attr('class', 'life-span-1')
                .attr('x', xScale(lifeStartYear))
                .attr('y', swimlane1Y + 2)
                .attr('width', Math.max(0, xScale(lifeEndYear) - xScale(lifeStartYear)))
                .attr('height', swimlaneHeight - 4)
                .attr('fill', connectionColors.life)
                .attr('stroke', 'white')
                .attr('stroke-width', 2)
                .attr('rx', 2)
                .attr('ry', 2)
                .style('opacity', 0.3)
                .style('pointer-events', 'none');
        } else {
            lifeSpan1.transition()
                .duration(750)
                .attr('x', xScale(lifeStartYear))
                .attr('width', Math.max(0, xScale(lifeEndYear) - xScale(lifeStartYear)));
        }
    }

    if (data2.span.start_year) {
        const lifeStartYear = mode === 'absolute' ? data2.span.start_year : 0;
        const lifeEndYear = mode === 'absolute' 
            ? (data2.span.end_year || new Date().getFullYear())
            : (data2.span.end_year ? data2.span.end_year - data2.span.start_year : new Date().getFullYear() - data2.span.start_year);
        
        const lifeSpan2 = svg.select('.life-span-2');
        if (lifeSpan2.empty()) {
            svg.append('rect')
                .attr('class', 'life-span-2')
                .attr('x', xScale(lifeStartYear))
                .attr('y', swimlane2Y + 2)
                .attr('width', Math.max(0, xScale(lifeEndYear) - xScale(lifeStartYear)))
                .attr('height', swimlaneHeight - 4)
                .attr('fill', connectionColors.life)
                .attr('stroke', 'white')
                .attr('stroke-width', 2)
                .attr('rx', 2)
                .attr('ry', 2)
                .style('opacity', 0.3)
                .style('pointer-events', 'none');
        } else {
            lifeSpan2.transition()
                .duration(750)
                .attr('x', xScale(lifeStartYear))
                .attr('width', Math.max(0, xScale(lifeEndYear) - xScale(lifeStartYear)));
        }
    }

    // Update or create timeline bars for span 1 with animation
    if (data1.connections && data1.connections.length > 0) {
        // Filter out invalid connections
        const validConnections1 = data1.connections.filter(connection => {
            const startYear = mode === 'absolute' ? connection.start_year : connection.start_year - data1.span.start_year;
            const endYear = mode === 'absolute' 
                ? (connection.end_year || new Date().getFullYear())
                : (connection.end_year ? connection.end_year - data1.span.start_year : new Date().getFullYear() - data1.span.start_year);
            return startYear && endYear && endYear >= startYear;
        });

        // Separate created connections from other connections
        const createdConnections1 = validConnections1.filter(c => c.type_id === 'created');
        const otherConnections1 = validConnections1.filter(c => c.type_id !== 'created');

        // Handle regular timeline bars
        const timelineBars1 = svg.selectAll('.timeline-bar-1')
            .data(otherConnections1, d => d.id || d.start_year + '-' + d.type_id + '-' + d.target_name);

        // Remove old bars
        timelineBars1.exit().remove();

        // Add new bars
        const newBars1 = timelineBars1.enter()
            .append('rect')
            .attr('class', 'timeline-bar-1')
            .attr('y', swimlane1Y + 2)
            .attr('height', swimlaneHeight - 4)
            .attr('fill', d => getConnectionColor(d.type_id))
            .attr('stroke', 'white')
            .attr('stroke-width', 1)
            .attr('rx', 2)
            .attr('ry', 2)
            .style('opacity', 0.6)
            .on('mouseover', function(event, d) {
                const overlappingConnections = findOverlappingConnections_{{ str_replace('-', '_', $span1->id) }}_{{ str_replace('-', '_', $span2->id) }}(d, data1.connections, 1);
                d3.select(this).style('opacity', 0.9);
                showTooltip_{{ str_replace('-', '_', $span1->id) }}_{{ str_replace('-', '_', $span2->id) }}(event, overlappingConnections, 1, mode, data1.span.start_year);
            })
            .on('mouseout', function() {
                d3.select(this).style('opacity', 0.6);
                hideTooltip_{{ str_replace('-', '_', $span1->id) }}_{{ str_replace('-', '_', $span2->id) }}();
            });

        // Update all bars (existing + new) with animation
        const allBars1 = timelineBars1.merge(newBars1);
        allBars1.transition()
            .duration(750)
            .attr('x', d => {
                const startYear = mode === 'absolute' ? d.start_year : d.start_year - data1.span.start_year;
                return xScale(startYear);
            })
            .attr('width', d => {
                const startYear = mode === 'absolute' ? d.start_year : d.start_year - data1.span.start_year;
                const endYear = mode === 'absolute' 
                    ? (d.end_year || new Date().getFullYear())
                    : (d.end_year ? d.end_year - data1.span.start_year : new Date().getFullYear() - data1.span.start_year);
                const width = xScale(endYear) - xScale(startYear);
                return Math.max(0, width); // Ensure width is never negative
            });

        // Handle created connections as moments
        const timelineMoments1 = svg.selectAll('.timeline-moment-1')
            .data(createdConnections1, d => d.id || d.start_year + '-' + d.type_id + '-' + d.target_name);

        // Remove old moments
        timelineMoments1.exit().remove();

        // Add new moments - create groups for each moment
        const newMoments1 = timelineMoments1.enter()
            .append('g')
            .attr('class', 'timeline-moment-1')
            .each(function(d, i) {
                const connection = d;
                const x = mode === 'absolute' ? xScale(connection.start_year) : xScale(connection.start_year - data1.span.start_year);
                const y1 = swimlane1Y;
                const y2 = swimlane1Y + swimlaneHeight;
                const circleY = (y1 + y2) / 2;
                const circleRadius = 3;
                
                // Draw vertical line
                d3.select(this).append('line')
                    .attr('class', 'timeline-moment-line-1')
                    .attr('x1', x)
                    .attr('x2', x)
                    .attr('y1', y1)
                    .attr('y2', y2)
                    .attr('stroke', getConnectionColor(connection.type_id))
                    .attr('stroke-width', 2)
                    .style('opacity', 0.8)
                    .on('mouseover', function(event) {
                        const overlappingConnections = findOverlappingConnections_{{ str_replace('-', '_', $span1->id) }}_{{ str_replace('-', '_', $span2->id) }}(connection, data1.connections, 1);
                        d3.select(this).style('opacity', 0.9);
                        showTooltip_{{ str_replace('-', '_', $span1->id) }}_{{ str_replace('-', '_', $span2->id) }}(event, overlappingConnections, 1, mode, data1.span.start_year);
                    })
                    .on('mouseout', function() {
                        d3.select(this).style('opacity', 0.8);
                        hideTooltip_{{ str_replace('-', '_', $span1->id) }}_{{ str_replace('-', '_', $span2->id) }}();
                    });
                
                // Draw circle
                d3.select(this).append('circle')
                    .attr('class', 'timeline-moment-circle-1')
                    .attr('cx', x)
                    .attr('cy', circleY)
                    .attr('r', circleRadius)
                    .attr('fill', getConnectionColor(connection.type_id))
                    .attr('stroke', 'white')
                    .attr('stroke-width', 1)
                    .style('opacity', 0.9)
                    .on('mouseover', function(event) {
                        const overlappingConnections = findOverlappingConnections_{{ str_replace('-', '_', $span1->id) }}_{{ str_replace('-', '_', $span2->id) }}(connection, data1.connections, 1);
                        d3.select(this).style('opacity', 1);
                        showTooltip_{{ str_replace('-', '_', $span1->id) }}_{{ str_replace('-', '_', $span2->id) }}(event, overlappingConnections, 1, mode, data1.span.start_year);
                    })
                    .on('mouseout', function() {
                        d3.select(this).style('opacity', 0.9);
                        hideTooltip_{{ str_replace('-', '_', $span1->id) }}_{{ str_replace('-', '_', $span2->id) }}();
                    });
            });

        // Update existing moments with animation
        const allMoments1 = timelineMoments1.merge(newMoments1);
        allMoments1.transition()
            .duration(750)
            .select('.timeline-moment-line-1')
            .attr('x1', d => {
                const startYear = mode === 'absolute' ? d.start_year : d.start_year - data1.span.start_year;
                return xScale(startYear);
            })
            .attr('x2', d => {
                const startYear = mode === 'absolute' ? d.start_year : d.start_year - data1.span.start_year;
                return xScale(startYear);
            });

        // Update circles
        allMoments1.transition()
            .duration(750)
            .select('.timeline-moment-circle-1')
            .attr('cx', d => {
                const startYear = mode === 'absolute' ? d.start_year : d.start_year - data1.span.start_year;
                return xScale(startYear);
            });
    } else {
        // Remove all bars and moments if no connections
        svg.selectAll('.timeline-bar-1, .timeline-moment-1').remove();
    }

    // Update or create timeline bars for span 2 with animation
    if (data2.connections && data2.connections.length > 0) {
        // Filter out invalid connections
        const validConnections2 = data2.connections.filter(connection => {
            const startYear = mode === 'absolute' ? connection.start_year : connection.start_year - data2.span.start_year;
            const endYear = mode === 'absolute' 
                ? (connection.end_year || new Date().getFullYear())
                : (connection.end_year ? connection.end_year - data2.span.start_year : new Date().getFullYear() - data2.span.start_year);
            return startYear && endYear && endYear >= startYear;
        });

        // Separate created connections from other connections
        const createdConnections2 = validConnections2.filter(c => c.type_id === 'created');
        const otherConnections2 = validConnections2.filter(c => c.type_id !== 'created');

        // Handle regular timeline bars
        const timelineBars2 = svg.selectAll('.timeline-bar-2')
            .data(otherConnections2, d => d.id || d.start_year + '-' + d.type_id + '-' + d.target_name);

        // Remove old bars
        timelineBars2.exit().remove();

        // Add new bars
        const newBars2 = timelineBars2.enter()
            .append('rect')
            .attr('class', 'timeline-bar-2')
            .attr('y', swimlane2Y + 2)
            .attr('height', swimlaneHeight - 4)
            .attr('fill', d => getConnectionColor(d.type_id))
            .attr('stroke', 'white')
            .attr('stroke-width', 1)
            .attr('rx', 2)
            .attr('ry', 2)
            .style('opacity', 0.6)
            .on('mouseover', function(event, d) {
                const overlappingConnections = findOverlappingConnections_{{ str_replace('-', '_', $span1->id) }}_{{ str_replace('-', '_', $span2->id) }}(d, data2.connections, 2);
                d3.select(this).style('opacity', 0.9);
                showTooltip_{{ str_replace('-', '_', $span1->id) }}_{{ str_replace('-', '_', $span2->id) }}(event, overlappingConnections, 2, mode, data2.span.start_year);
            })
            .on('mouseout', function() {
                d3.select(this).style('opacity', 0.6);
                hideTooltip_{{ str_replace('-', '_', $span1->id) }}_{{ str_replace('-', '_', $span2->id) }}();
            });

        // Update all bars (existing + new) with animation
        const allBars2 = timelineBars2.merge(newBars2);
        allBars2.transition()
            .duration(750)
            .attr('x', d => {
                const startYear = mode === 'absolute' ? d.start_year : d.start_year - data2.span.start_year;
                return xScale(startYear);
            })
            .attr('width', d => {
                const startYear = mode === 'absolute' ? d.start_year : d.start_year - data2.span.start_year;
                const endYear = mode === 'absolute' 
                    ? (d.end_year || new Date().getFullYear())
                    : (d.end_year ? d.end_year - data2.span.start_year : new Date().getFullYear() - data2.span.start_year);
                const width = xScale(endYear) - xScale(startYear);
                return Math.max(0, width); // Ensure width is never negative
            });

        // Handle created connections as moments
        const timelineMoments2 = svg.selectAll('.timeline-moment-2')
            .data(createdConnections2, d => d.id || d.start_year + '-' + d.type_id + '-' + d.target_name);

        // Remove old moments
        timelineMoments2.exit().remove();

        // Add new moments - create groups for each moment
        const newMoments2 = timelineMoments2.enter()
            .append('g')
            .attr('class', 'timeline-moment-2')
            .each(function(d, i) {
                const connection = d;
                const x = mode === 'absolute' ? xScale(connection.start_year) : xScale(connection.start_year - data2.span.start_year);
                const y1 = swimlane2Y;
                const y2 = swimlane2Y + swimlaneHeight;
                const circleY = (y1 + y2) / 2;
                const circleRadius = 3;
                
                // Draw vertical line
                d3.select(this).append('line')
                    .attr('class', 'timeline-moment-line-2')
                    .attr('x1', x)
                    .attr('x2', x)
                    .attr('y1', y1)
                    .attr('y2', y2)
                    .attr('stroke', getConnectionColor(connection.type_id))
                    .attr('stroke-width', 2)
                    .style('opacity', 0.8)
                    .on('mouseover', function(event) {
                        const overlappingConnections = findOverlappingConnections_{{ str_replace('-', '_', $span1->id) }}_{{ str_replace('-', '_', $span2->id) }}(connection, data2.connections, 2);
                        d3.select(this).style('opacity', 0.9);
                        showTooltip_{{ str_replace('-', '_', $span1->id) }}_{{ str_replace('-', '_', $span2->id) }}(event, overlappingConnections, 2, mode, data2.span.start_year);
                    })
                    .on('mouseout', function() {
                        d3.select(this).style('opacity', 0.8);
                        hideTooltip_{{ str_replace('-', '_', $span1->id) }}_{{ str_replace('-', '_', $span2->id) }}();
                    });
                
                // Draw circle
                d3.select(this).append('circle')
                    .attr('class', 'timeline-moment-circle-2')
                    .attr('cx', x)
                    .attr('cy', circleY)
                    .attr('r', circleRadius)
                    .attr('fill', getConnectionColor(connection.type_id))
                    .attr('stroke', 'white')
                    .attr('stroke-width', 1)
                    .style('opacity', 0.9)
                    .on('mouseover', function(event) {
                        const overlappingConnections = findOverlappingConnections_{{ str_replace('-', '_', $span1->id) }}_{{ str_replace('-', '_', $span2->id) }}(connection, data2.connections, 2);
                        d3.select(this).style('opacity', 1);
                        showTooltip_{{ str_replace('-', '_', $span1->id) }}_{{ str_replace('-', '_', $span2->id) }}(event, overlappingConnections, 2, mode, data2.span.start_year);
                    })
                    .on('mouseout', function() {
                        d3.select(this).style('opacity', 0.9);
                        hideTooltip_{{ str_replace('-', '_', $span1->id) }}_{{ str_replace('-', '_', $span2->id) }}();
                    });
            });

        // Update existing moments with animation
        const allMoments2 = timelineMoments2.merge(newMoments2);
        allMoments2.transition()
            .duration(750)
            .select('.timeline-moment-line-2')
            .attr('x1', d => {
                const startYear = mode === 'absolute' ? d.start_year : d.start_year - data2.span.start_year;
                return xScale(startYear);
            })
            .attr('x2', d => {
                const startYear = mode === 'absolute' ? d.start_year : d.start_year - data2.span.start_year;
                return xScale(startYear);
            });

        // Update circles
        allMoments2.transition()
            .duration(750)
            .select('.timeline-moment-circle-2')
            .attr('cx', d => {
                const startYear = mode === 'absolute' ? d.start_year : d.start_year - data2.span.start_year;
                return xScale(startYear);
            });
    } else {
        // Remove all bars and moments if no connections
        svg.selectAll('.timeline-bar-2, .timeline-moment-2').remove();
    }

    // Add post-life overlays BEFORE interactive backgrounds (just for visual effect)
    // Post-life overlay for span 1
    console.log('Checking post-life overlay for span 1:', {
        endYear: data1.span.end_year,
        currentYear: new Date().getFullYear(),
        hasEndYear: data1.span.end_year && data1.span.end_year < new Date().getFullYear()
    });
    
    if (data1.span.end_year && data1.span.end_year < new Date().getFullYear()) {
        const postLifeStartYear = mode === 'absolute' ? data1.span.end_year : data1.span.end_year - data1.span.start_year;
        const postLifeEndYear = mode === 'absolute' 
            ? new Date().getFullYear()
            : new Date().getFullYear() - data1.span.start_year;
        
        console.log('Span 1 post-life check:', {
            postLifeStartYear,
            postLifeEndYear,
            connections: data1.connections?.map(c => ({ start: c.start_year, end: c.end_year, type: c.type_name }))
        });
        
        // Always create post-life overlay for deceased people
        console.log('Creating post-life overlay for span 1');
        const postLifeOverlay1 = svg.select('.post-life-overlay-1');
        if (postLifeOverlay1.empty()) {
            svg.append('rect')
                .attr('class', 'post-life-overlay-1')
                .attr('x', xScale(postLifeStartYear))
                .attr('y', swimlane1Y + 2)
                .attr('width', Math.max(0, xScale(postLifeEndYear) - xScale(postLifeStartYear)))
                .attr('height', swimlaneHeight - 4)
                .attr('fill', 'rgba(255, 255, 255, 0.7)')
                .attr('stroke', 'rgba(255, 255, 255, 0.9)')
                .attr('stroke-width', 1)
                .attr('rx', 2)
                .attr('ry', 2)
                .style('pointer-events', 'none')
                .style('z-index', 10);
            console.log('Post-life overlay 1 created');
        } else {
            postLifeOverlay1.transition()
                .duration(750)
                .attr('x', xScale(postLifeStartYear))
                .attr('width', Math.max(0, xScale(postLifeEndYear) - xScale(postLifeStartYear)));
            console.log('Post-life overlay 1 updated');
        }
    }

    // Post-life overlay for span 2
    console.log('Checking post-life overlay for span 2:', {
        endYear: data2.span.end_year,
        currentYear: new Date().getFullYear(),
        hasEndYear: data2.span.end_year && data2.span.end_year < new Date().getFullYear()
    });
    
    if (data2.span.end_year && data2.span.end_year < new Date().getFullYear()) {
        const postLifeStartYear = mode === 'absolute' ? data2.span.end_year : data2.span.end_year - data2.span.start_year;
        const postLifeEndYear = mode === 'absolute' 
            ? new Date().getFullYear()
            : new Date().getFullYear() - data2.span.start_year;
        
        console.log('Span 2 post-life check:', {
            postLifeStartYear,
            postLifeEndYear,
            connections: data2.connections?.map(c => ({ start: c.start_year, end: c.end_year, type: c.type_name }))
        });
        
        // Always create post-life overlay for deceased people
        console.log('Creating post-life overlay for span 2');
        const postLifeOverlay2 = svg.select('.post-life-overlay-2');
        if (postLifeOverlay2.empty()) {
            svg.append('rect')
                .attr('class', 'post-life-overlay-2')
                .attr('x', xScale(postLifeStartYear))
                .attr('y', swimlane2Y + 2)
                .attr('width', Math.max(0, xScale(postLifeEndYear) - xScale(postLifeStartYear)))
                .attr('height', swimlaneHeight - 4)
                .attr('fill', 'rgba(255, 255, 255, 0.7)')
                .attr('stroke', 'rgba(255, 255, 255, 0.9)')
                .attr('stroke-width', 1)
                .attr('rx', 2)
                .attr('ry', 2)
                .style('pointer-events', 'none')
                .style('z-index', 10);
            console.log('Post-life overlay 2 created');
        } else {
            postLifeOverlay2.transition()
                .duration(750)
                .attr('x', xScale(postLifeStartYear))
                .attr('width', Math.max(0, xScale(postLifeEndYear) - xScale(postLifeStartYear)));
            console.log('Post-life overlay 2 updated');
        }
    }

    // Update or create interactive backgrounds (AFTER post-life overlays)
    const background1 = svg.select('.interactive-background-1');
    if (background1.empty()) {
        svg.append('rect')
            .attr('class', 'interactive-background-1')
            .attr('x', margin.left)
            .attr('y', swimlane1Y)
            .attr('width', width - margin.left - margin.right)
            .attr('height', swimlaneHeight)
            .attr('fill', 'transparent')
            .attr('cursor', 'crosshair');
    }

    const background2 = svg.select('.interactive-background-2');
    if (background2.empty()) {
        svg.append('rect')
            .attr('class', 'interactive-background-2')
            .attr('x', margin.left)
            .attr('y', swimlane2Y)
            .attr('width', width - margin.left - margin.right)
            .attr('height', swimlaneHeight)
            .attr('fill', 'transparent')
            .attr('cursor', 'crosshair');
    }

    // Create tooltip (unique to this comparison timeline instance)
    const tooltip = d3.select('body').select(`.comparison-tooltip-${span1Id}-${span2Id}`);
    if (tooltip.empty()) {
        d3.select('body').append('div')
            .attr('class', `comparison-tooltip-${span1Id}-${span2Id}`)
            .style('position', 'absolute')
            .style('background', 'rgba(0, 0, 0, 0.8)')
            .style('color', 'white')
            .style('padding', '8px')
            .style('border-radius', '4px')
            .style('font-size', '12px')
            .style('pointer-events', 'none')
            .style('opacity', 0);
    }

    // Attach event handlers after all elements are created/updated
    setTimeout(() => {
        attachEventHandlers_{{ str_replace('-', '_', $span1->id) }}_{{ str_replace('-', '_', $span2->id) }}(data1, data2, mode);
    }, isUpdate ? 800 : 100); // Longer delay for updates, shorter for initial render

    function findOverlappingConnections_{{ str_replace('-', '_', $span1->id) }}_{{ str_replace('-', '_', $span2->id) }}(targetConnection, allConnections, spanNumber) {
        return allConnections.filter(connection => {
            const targetStart = targetConnection.start_year;
            const targetEnd = targetConnection.end_year || new Date().getFullYear();
            const connectionStart = connection.start_year;
            const connectionEnd = connection.end_year || new Date().getFullYear();
            return (targetStart <= connectionEnd && targetEnd >= connectionStart);
        });
    }

    function showTooltip_{{ str_replace('-', '_', $span1->id) }}_{{ str_replace('-', '_', $span2->id) }}(event, connections, spanNumber, mode, spanStartYear) {
        // Force tooltip to be visible
        tooltip.style('opacity', 1);
        
        let tooltipContent = '';
        const spanName = spanNumber === 1 ? '{{ $span1->name }}' : '{{ $span2->name }}';
        const spanData = spanNumber === 1 ? data1 : data2;
        
        // Check if we're in a post-life period
        const isPostLifePeriod = spanData.span.end_year && 
            ((mode === 'absolute' && new Date().getFullYear() > spanData.span.end_year) ||
             (mode === 'relative' && (new Date().getFullYear() - spanData.span.start_year) > (spanData.span.end_year - spanData.span.start_year)));
        
        if (isPostLifePeriod) {
            const deathYear = mode === 'absolute' ? spanData.span.end_year : `Age ${spanData.span.end_year - spanData.span.start_year}`;
            tooltipContent += `<span style="color: #666;">●</span> <strong>Died in ${deathYear}</strong><br/>`;
        }
        
        if (connections.length === 1) {
            const d = connections[0];
            const endYear = d.end_year || 'Present';
            const startDisplay = mode === 'absolute' ? d.start_year : `Age ${d.start_year - spanStartYear}`;
            const endDisplay = mode === 'absolute' ? endYear : (endYear === 'Present' ? 'Present' : `Age ${endYear - spanStartYear}`);
            
            tooltipContent += `
                <strong>${spanName}</strong><br/>
                <strong>${d.type_name} ${d.target_name}</strong><br/>
                ${startDisplay} - ${endDisplay}
            `;
        } else {
            tooltipContent += `<strong>${spanName} - ${connections.length} overlapping connections:</strong><br/>`;
            
            // Sort connections: post-life connections first, then by start year
            const sortedConnections = connections.sort((a, b) => {
                const aIsPostLife = (a.end_year && a.end_year > spanData.span.end_year) ||
                                   (!a.end_year && a.start_year <= spanData.span.end_year);
                const bIsPostLife = (b.end_year && b.end_year > spanData.span.end_year) ||
                                   (!b.end_year && b.start_year <= spanData.span.end_year);
                
                if (aIsPostLife && !bIsPostLife) return -1;
                if (!aIsPostLife && bIsPostLife) return 1;
                return a.start_year - b.start_year;
            });
            
            sortedConnections.forEach((d, index) => {
                const endYear = d.end_year || 'Present';
                const startDisplay = mode === 'absolute' ? d.start_year : `Age ${d.start_year - spanStartYear}`;
                const endDisplay = mode === 'absolute' ? endYear : (endYear === 'Present' ? 'Present' : `Age ${endYear - spanStartYear}`);
                const bulletColor = getConnectionColor(d.type_id);
                
                tooltipContent += `
                    <span style="color: ${bulletColor};">●</span> <strong>${d.type_name} ${d.target_name}</strong><br/>
                    &nbsp;&nbsp;&nbsp;&nbsp;${startDisplay} - ${endDisplay}<br/>
                `;
            });
        }
        
        tooltip.html(tooltipContent)
            .style('left', (event.pageX - 50) + 'px')
            .style('top', (event.pageY + 20) + 'px');
    }

    function showYearTooltip_{{ str_replace('-', '_', $span1->id) }}_{{ str_replace('-', '_', $span2->id) }}(event, value, connections1, connections2, mode, span1StartYear, span2StartYear) {
        // Force tooltip to be visible
        tooltip.style('opacity', 1);
        
        const valueDisplay = mode === 'absolute' ? `Year ${value}` : `Age ${value}`;
        let tooltipContent = `<strong>${valueDisplay}</strong><br/>`;
        
        if (connections1.length > 0) {
            tooltipContent += `<br/><strong>{{ $span1->name }}:</strong><br/>`;
            
            // Check if we're in a post-life period for span 1
            const isPostLifePeriod1 = data1.span.end_year && 
                ((mode === 'absolute' && value > data1.span.end_year) ||
                 (mode === 'relative' && value > (data1.span.end_year - data1.span.start_year)));
            
            if (isPostLifePeriod1) {
                const deathYear = mode === 'absolute' ? data1.span.end_year : `Age ${data1.span.end_year - data1.span.start_year}`;
                tooltipContent += `<span style="color: #666;">●</span> <strong>Died in ${deathYear}</strong><br/>`;
            }
            
            // Sort connections: post-life connections first, then by start year
            const sortedConnections1 = connections1.sort((a, b) => {
                const aIsPostLife = (a.end_year && a.end_year > data1.span.end_year) ||
                                   (!a.end_year && a.start_year <= data1.span.end_year);
                const bIsPostLife = (b.end_year && b.end_year > data1.span.end_year) ||
                                   (!b.end_year && b.start_year <= data1.span.end_year);
                
                if (aIsPostLife && !bIsPostLife) return -1;
                if (!aIsPostLife && bIsPostLife) return 1;
                return a.start_year - b.start_year;
            });
            
            sortedConnections1.forEach((d, index) => {
                const endYear = d.end_year || 'Present';
                const startDisplay = mode === 'absolute' ? d.start_year : `Age ${d.start_year - span1StartYear}`;
                const endDisplay = mode === 'absolute' ? endYear : (endYear === 'Present' ? 'Present' : `Age ${endYear - span1StartYear}`);
                const bulletColor = getConnectionColor(d.type_id);
                
                tooltipContent += `
                    <span style="color: ${bulletColor};">●</span> <strong>${d.type_name} ${d.target_name}</strong><br/>
                    &nbsp;&nbsp;&nbsp;&nbsp;${startDisplay} - ${endDisplay}<br/>
                `;
            });
        }
        
        if (connections2.length > 0) {
            tooltipContent += `<br/><strong>{{ $span2->name }}:</strong><br/>`;
            
            // Check if we're in a post-life period for span 2
            const isPostLifePeriod2 = data2.span.end_year && 
                ((mode === 'absolute' && value > data2.span.end_year) ||
                 (mode === 'relative' && value > (data2.span.end_year - data2.span.start_year)));
            
            if (isPostLifePeriod2) {
                const deathYear = mode === 'absolute' ? data2.span.end_year : `Age ${data2.span.end_year - data2.span.start_year}`;
                tooltipContent += `<span style="color: #666;">●</span> <strong>Died in ${deathYear}</strong><br/>`;
            }
            
            // Sort connections: post-life connections first, then by start year
            const sortedConnections2 = connections2.sort((a, b) => {
                const aIsPostLife = (a.end_year && a.end_year > data2.span.end_year) ||
                                   (!a.end_year && a.start_year <= data2.span.end_year);
                const bIsPostLife = (b.end_year && b.end_year > data2.span.end_year) ||
                                   (!b.end_year && b.start_year <= data2.span.end_year);
                
                if (aIsPostLife && !bIsPostLife) return -1;
                if (!aIsPostLife && bIsPostLife) return 1;
                return a.start_year - b.start_year;
            });
            
            sortedConnections2.forEach((d, index) => {
                const endYear = d.end_year || 'Present';
                const startDisplay = mode === 'absolute' ? d.start_year : `Age ${d.start_year - span2StartYear}`;
                const endDisplay = mode === 'absolute' ? endYear : (endYear === 'Present' ? 'Present' : `Age ${endYear - span2StartYear}`);
                const bulletColor = getConnectionColor(d.type_id);
                
                tooltipContent += `
                    <span style="color: ${bulletColor};">●</span> <strong>${d.type_name} ${d.target_name}</strong><br/>
                    &nbsp;&nbsp;&nbsp;&nbsp;${startDisplay} - ${endDisplay}<br/>
                `;
            });
        }
        
        tooltip.html(tooltipContent)
            .style('left', (event.pageX - 50) + 'px')
            .style('top', (event.pageY + 20) + 'px');
    }

    function hideTooltip_{{ str_replace('-', '_', $span1->id) }}_{{ str_replace('-', '_', $span2->id) }}() {
        tooltip.style('opacity', 0);
    }

    // Function to re-attach event handlers after animations
    function attachEventHandlers_{{ str_replace('-', '_', $span1->id) }}_{{ str_replace('-', '_', $span2->id) }}(data1, data2, mode) {
        // Re-attach mouseover/mouseout events to timeline bars
        svg.selectAll('.timeline-bar-1')
            .on('mouseover', function(event, d) {
                const overlappingConnections = findOverlappingConnections_{{ str_replace('-', '_', $span1->id) }}_{{ str_replace('-', '_', $span2->id) }}(d, data1.connections, 1);
                d3.select(this).style('opacity', 0.9);
                showTooltip_{{ str_replace('-', '_', $span1->id) }}_{{ str_replace('-', '_', $span2->id) }}(event, overlappingConnections, 1, mode, data1.span.start_year);
            })
            .on('mouseout', function() {
                d3.select(this).style('opacity', 0.6);
                hideTooltip_{{ str_replace('-', '_', $span1->id) }}_{{ str_replace('-', '_', $span2->id) }}();
            });

        svg.selectAll('.timeline-bar-2')
            .on('mouseover', function(event, d) {
                const overlappingConnections = findOverlappingConnections_{{ str_replace('-', '_', $span1->id) }}_{{ str_replace('-', '_', $span2->id) }}(d, data2.connections, 2);
                d3.select(this).style('opacity', 0.9);
                showTooltip_{{ str_replace('-', '_', $span1->id) }}_{{ str_replace('-', '_', $span2->id) }}(event, overlappingConnections, 2, mode, data2.span.start_year);
            })
            .on('mouseout', function() {
                d3.select(this).style('opacity', 0.6);
                hideTooltip_{{ str_replace('-', '_', $span1->id) }}_{{ str_replace('-', '_', $span2->id) }}();
            });

        // Re-attach mousemove events to interactive backgrounds
        svg.select('.interactive-background-1')
            .on('mousemove', function(event) {
                const [mouseX] = d3.pointer(event);
                const hoveredValue = Math.round(xScale.invert(mouseX));
                
                const activeConnections1 = data1.connections ? data1.connections.filter(connection => {
                    const startYear = mode === 'absolute' ? connection.start_year : connection.start_year - data1.span.start_year;
                    const endYear = mode === 'absolute' 
                        ? (connection.end_year || new Date().getFullYear())
                        : (connection.end_year ? connection.end_year - data1.span.start_year : new Date().getFullYear() - data1.span.start_year);
                    return hoveredValue >= startYear && hoveredValue <= endYear;
                }) : [];
                
                const activeConnections2 = data2.connections ? data2.connections.filter(connection => {
                    const startYear = mode === 'absolute' ? connection.start_year : connection.start_year - data2.span.start_year;
                    const endYear = mode === 'absolute' 
                        ? (connection.end_year || new Date().getFullYear())
                        : (connection.end_year ? connection.end_year - data2.span.start_year : new Date().getFullYear() - data2.span.start_year);
                    return hoveredValue >= startYear && hoveredValue <= endYear;
                }) : [];
                
                if (activeConnections1.length > 0 || activeConnections2.length > 0) {
                    showYearTooltip_{{ str_replace('-', '_', $span1->id) }}_{{ str_replace('-', '_', $span2->id) }}(event, hoveredValue, activeConnections1, activeConnections2, mode, data1.span.start_year, data2.span.start_year);
                } else {
                    hideTooltip_{{ str_replace('-', '_', $span1->id) }}_{{ str_replace('-', '_', $span2->id) }}();
                }
            })
            .on('mouseout', function() {
                hideTooltip_{{ str_replace('-', '_', $span1->id) }}_{{ str_replace('-', '_', $span2->id) }}();
            });

        svg.select('.interactive-background-2')
            .on('mousemove', function(event) {
                const [mouseX] = d3.pointer(event);
                const hoveredValue = Math.round(xScale.invert(mouseX));
                
                const activeConnections1 = data1.connections ? data1.connections.filter(connection => {
                    const startYear = mode === 'absolute' ? connection.start_year : connection.start_year - data1.span.start_year;
                    const endYear = mode === 'absolute' 
                        ? (connection.end_year || new Date().getFullYear())
                        : (connection.end_year ? connection.end_year - data1.span.start_year : new Date().getFullYear() - data1.span.start_year);
                    return hoveredValue >= startYear && hoveredValue <= endYear;
                }) : [];
                
                const activeConnections2 = data2.connections ? data2.connections.filter(connection => {
                    const startYear = mode === 'absolute' ? connection.start_year : connection.start_year - data2.span.start_year;
                    const endYear = mode === 'absolute' 
                        ? (connection.end_year || new Date().getFullYear())
                        : (connection.end_year ? connection.end_year - data2.span.start_year : new Date().getFullYear() - data2.span.start_year);
                    return hoveredValue >= startYear && hoveredValue <= endYear;
                }) : [];
                
                if (activeConnections1.length > 0 || activeConnections2.length > 0) {
                    showYearTooltip_{{ str_replace('-', '_', $span1->id) }}_{{ str_replace('-', '_', $span2->id) }}(event, hoveredValue, activeConnections1, activeConnections2, mode, data1.span.start_year, data2.span.start_year);
                } else {
                    hideTooltip_{{ str_replace('-', '_', $span1->id) }}_{{ str_replace('-', '_', $span2->id) }}();
                }
            })
            .on('mouseout', function() {
                hideTooltip_{{ str_replace('-', '_', $span1->id) }}_{{ str_replace('-', '_', $span2->id) }}();
            });
    }
    } catch (error) {
        console.error('Error rendering comparison timeline:', error);
        const container = document.getElementById(`comparison-timeline-container-{{ $span1->id }}-{{ $span2->id }}`);
        if (container) {
            container.innerHTML = '<div class="text-danger text-center py-4">Error rendering timeline</div>';
        }
    }
}

function calculateComparisonTimeRange_{{ str_replace('-', '_', $span1->id) }}_{{ str_replace('-', '_', $span2->id) }}(data1, data2) {
    let start = Math.min(
        data1.span.start_year || 1900,
        data2.span.start_year || 1900
    );
    
    // Always extend to today's date to show full post-life periods
    let end = new Date().getFullYear();

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

function calculateRelativeTimeRange_{{ str_replace('-', '_', $span1->id) }}_{{ str_replace('-', '_', $span2->id) }}(data1, data2) {
    // Calculate ages for all connections
    const allAges = [];
    
    // Add life span ages
    if (data1.span.start_year) {
        const lifeEndAge = data1.span.end_year 
            ? data1.span.end_year - data1.span.start_year 
            : new Date().getFullYear() - data1.span.start_year;
        allAges.push(0, lifeEndAge);
    }
    
    if (data2.span.start_year) {
        const lifeEndAge = data2.span.end_year 
            ? data2.span.end_year - data2.span.start_year 
            : new Date().getFullYear() - data2.span.start_year;
        allAges.push(0, lifeEndAge);
    }
    
    // Add connection ages for span 1
    if (data1.connections) {
        data1.connections.forEach(connection => {
            if (connection.start_year && data1.span.start_year) {
                const startAge = connection.start_year - data1.span.start_year;
                allAges.push(startAge);
                
                if (connection.end_year) {
                    const endAge = connection.end_year - data1.span.start_year;
                    allAges.push(endAge);
                }
            }
        });
    }
    
    // Add connection ages for span 2
    if (data2.connections) {
        data2.connections.forEach(connection => {
            if (connection.start_year && data2.span.start_year) {
                const startAge = connection.start_year - data2.span.start_year;
                allAges.push(startAge);
                
                if (connection.end_year) {
                    const endAge = connection.end_year - data2.span.start_year;
                    allAges.push(endAge);
                }
            }
        });
    }

    const minAge = Math.min(...allAges);
    const maxAge = Math.max(...allAges);

    // Add some padding
    const padding = Math.max(2, Math.floor((maxAge - minAge) * 0.1));
    return {
        start: Math.max(0, minAge - padding),
        end: maxAge + padding
    };
}
</script>
@endpush 