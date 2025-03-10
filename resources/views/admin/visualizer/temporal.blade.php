@extends('layouts.app')

@section('page_title')
    Temporal Visualization
@endsection

@section('scripts')
<script src="https://d3js.org/d3.v7.min.js"></script>
@endsection

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Temporal Visualization</h5>
                    <div class="btn-group">
                        <button id="filter-toggle" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-funnel"></i> Filter Unconnected
                        </button>
                        <select id="connection-depth" class="form-select form-select-sm" style="width: auto;">
                            <option value="1">Direct Connections</option>
                            <option value="2">2nd Degree</option>
                            <option value="3">3rd Degree</option>
                            <option value="-1">All Connected</option>
                        </select>
                        <button id="zoom-in" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-zoom-in"></i>
                        </button>
                        <button id="zoom-out" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-zoom-out"></i>
                        </button>
                        <button id="reset-zoom" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-arrow-counterclockwise"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body position-relative">
                    <div id="timeline-controls" class="bg-white border-bottom mb-3">
                        <div id="timeline-axis" class="px-4 position-relative"></div>
                    </div>
                    <div id="timeline" class="overflow-hidden"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.node-rect {
    fill-opacity: 0.8;
    stroke-width: 1;
    cursor: pointer;
    transition: opacity 0.3s, transform 0.3s, fill 0.3s;
}

.node-rect.active {
    stroke-width: 2;
    filter: brightness(1.1);
}

.node-rect.filtered {
    display: none;
}

/* Connection level styles */
.node-rect.level-0 {
    filter: brightness(1.2);
    stroke-width: 3;
}

.node-rect.level-1 {
    filter: brightness(0.9);
}

.node-rect.level-2 {
    filter: brightness(0.8);
}

.node-rect.level-3 {
    filter: brightness(0.7);
}

.node-rect.level-more {
    filter: brightness(0.6);
}

.node-label {
    font-size: 12px;
    font-weight: bold;
    pointer-events: none;
    transition: opacity 0.3s, transform 0.3s;
}

.node-label.filtered {
    display: none;
}

.link {
    fill: none;
    stroke: #666;
    stroke-width: 2;
    stroke-opacity: 0;
    transition: all 0.2s;
    shape-rendering: crispEdges;
}

.link.active {
    stroke-opacity: 1;
    stroke-width: 3;
}

.person { fill: #ff7f0e; stroke: #e67300; }
.organisation { fill: #1f77b4; stroke: #1a65a3; }
.event { fill: #2ca02c; stroke: #267d26; }
.place { fill: #d62728; stroke: #b92223; }

.axis-label {
    font-size: 12px;
    font-weight: bold;
}

.x-axis {
    font-size: 12px;
    z-index: 2;
}

.x-axis path,
.x-axis line {
    stroke: #000;
    shape-rendering: crispEdges;
}

.x-axis text {
    fill: #000;
}

#timeline-controls {
    height: 60px;
    padding: 20px 0;
    display: flex;
    flex-direction: column;
}

#timeline-axis {
    height: 60px;
    position: relative;
    display: flex;
    flex-direction: column;
    justify-content: flex-start;
}

#timeline {
    height: calc(100vh - 240px); /* Adjusted for removed minimap */
    overflow: hidden;
}

.card-body {
    padding: 0;
    overflow: hidden;
}

.span {
    transition: transform 0.3s;
}

.span.filtered {
    display: none;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const data = {
        nodes: @json($nodes),
        links: @json($links)
    };

    // Use maxYear from backend instead of current date for consistency
    const maxYear = {{ $maxYear }};
    const minYear = {{ $minYear }};
    
    // Calculate dimensions
    const margin = { top: 20, right: 200, bottom: 20, left: 50 };
    const width = document.getElementById('timeline').clientWidth - margin.left - margin.right;
    const height = document.getElementById('timeline').clientHeight - margin.top - margin.bottom;
    
    // Create main SVG
    const svg = d3.select('#timeline')
        .append('svg')
        .attr('width', width + margin.left + margin.right)
        .attr('height', height + margin.top + margin.bottom);

    // Add a background rect to catch drag events
    const background = svg.append('rect')
        .attr('class', 'background')
        .attr('width', width + margin.left + margin.right)
        .attr('height', height + margin.top + margin.bottom)
        .attr('fill', 'transparent')
        .style('cursor', 'grab');

    // Create a single container for both content and axis
    const container = svg.append('g')
        .attr('transform', `translate(${margin.left},0)`);

    // Create groups for content and axis within the container
    const contentGroup = container.append('g')
        .attr('class', 'content');

    const axisGroup = container.append('g')
        .attr('class', 'axis')
        .attr('transform', `translate(0,30)`);

    // Create scales
    const xScale = d3.scaleLinear()
        .domain([minYear, maxYear])
        .range([0, width]);

    // Create fixed x-axis
    const xAxis = d3.axisBottom(xScale)
        .tickFormat(d3.format('d'));
    
    const xAxisGroup = d3.select('#timeline-axis')
        .append('svg')
        .attr('width', width + margin.left + margin.right)
        .attr('height', 60)
        .append('g')
        .attr('transform', `translate(${margin.left},30)`)
        .attr('class', 'x-axis')
        .call(xAxis);

    function updateTimelineView(transform) {
        // Update axis with the transform
        xAxisGroup.call(xAxis.scale(transform.rescaleX(xScale)));
    }

    // Helper function to get end year (use maxYear from backend)
    function getEndYear(d) {
        if (d.isOngoing) return maxYear;
        return d.endYear || d.startYear;
    }

    // Log span positions for debugging
    data.nodes.forEach(d => {
        const startX = xScale(d.startYear);
        const endX = xScale(getEndYear(d));
        console.log(`Span: ${d.name}`);
        console.log(`  Years: ${d.startYear} → ${d.isOngoing ? 'Present' : getEndYear(d)}`);
        console.log(`  X-pos: ${Math.round(startX)} → ${Math.round(endX)}`);
    });

    // Sort nodes by start year
    data.nodes.sort((a, b) => a.startYear - b.startYear);

    // Create static spans
    const spanGroup = contentGroup.append('g')
        .attr('class', 'spans');

    // Create span groups with fixed positioning
    const spans = spanGroup.selectAll('.span')
        .data(data.nodes)
        .enter()
        .append('g')
        .attr('class', 'span')
        .attr('transform', (d, i) => {
            d.y = i * 40 + margin.top;
            return `translate(0,${d.y})`;
        });

    // Add dragging styles
    const style = document.createElement('style');
    style.textContent = `
        .dragging {
            cursor: grabbing !important;
        }
        .background.dragging {
            cursor: grabbing;
        }
    `;
    document.head.appendChild(style);

    // Add background rectangles
    spans.append('rect')
        .attr('class', d => `node-rect ${d.typeId}`)
        .attr('x', d => xScale(d.startYear))
        .attr('y', -15)
        .attr('width', d => {
            const endYear = getEndYear(d);
            return Math.max(50, xScale(endYear) - xScale(d.startYear));
        })
        .attr('height', 30)
        .attr('rx', 5)
        .attr('ry', 5);

    // Add name labels
    spans.append('text')
        .attr('class', 'node-label')
        .attr('x', d => xScale(d.startYear) + 10)
        .attr('y', 5)
        .text(d => d.name);

    // Add tooltips
    spans.append('title')
        .text(d => `${d.name}\n${d.startYear} - ${d.isOngoing ? 'Present' : getEndYear(d)}`);

    // Keep track of container translation
    let containerTransform = { x: margin.left, y: 0, k: 1 };

    // Background drag behavior
    const backgroundDrag = d3.drag()
        .on('start', () => {
            background.classed('dragging', true);
            const transform = d3.zoomTransform(svg.node());
            containerTransform = { x: transform.x, y: transform.y, k: transform.k };
        })
        .on('drag', (event) => {
            // Update the container transform with both x and y movement
            containerTransform.x += event.dx;
            containerTransform.y += event.dy;
            
            // Apply the new transform to the container
            const transform = d3.zoomIdentity
                .translate(containerTransform.x, containerTransform.y)
                .scale(containerTransform.k);
            
            container.attr('transform', `translate(${transform.x},${transform.y}) scale(${transform.k})`);
            
            // Update the axis
            xAxisGroup.call(xAxis.scale(transform.rescaleX(xScale)));
            updateTimelineView(transform);
        })
        .on('end', () => {
            background.classed('dragging', false);
        });

    background.call(backgroundDrag);

    // Modify zoom behavior to work with background drag
    const zoom = d3.zoom()
        .scaleExtent([0.5, 5])
        .on('zoom', (event) => {
            // Update container transform
            containerTransform = { 
                x: event.transform.x, 
                y: event.transform.y, 
                k: event.transform.k 
            };
            
            // Apply transform to container
            container.attr('transform', 
                `translate(${event.transform.x},${event.transform.y}) scale(${event.transform.k})`
            );
            
            // Update axis
            xAxisGroup.call(xAxis.scale(event.transform.rescaleX(xScale)));
            updateTimelineView(event.transform);
        });

    svg.call(zoom);

    // Zoom controls
    document.getElementById('zoom-in').onclick = () => {
        svg.transition().duration(750).call(zoom.scaleBy, 1.2);
    };

    document.getElementById('zoom-out').onclick = () => {
        svg.transition().duration(750).call(zoom.scaleBy, 0.8);
    };

    document.getElementById('reset-zoom').onclick = () => {
        svg.transition().duration(750).call(zoom.transform, d3.zoomIdentity);
    };

    // Update the resize handler
    window.addEventListener('resize', () => {
        const newWidth = document.getElementById('timeline').clientWidth - margin.left - margin.right;
        const newHeight = document.getElementById('timeline').clientHeight - margin.top - margin.bottom;
        
        // Update main SVG and background
        svg.attr('width', newWidth + margin.left + margin.right)
           .attr('height', newHeight + margin.top + margin.bottom);
        
        background.attr('width', newWidth + margin.left + margin.right)
                 .attr('height', newHeight + margin.top + margin.bottom);
        
        xScale.range([0, newWidth]);
        
        // Update axis
        d3.select('#timeline-axis svg').attr('width', newWidth + margin.left + margin.right);
        
        // Update view
        const transform = d3.zoomTransform(svg.node());
        updateTimelineView(transform);
    });

    let isFilterActive = false;
    const filterToggle = document.getElementById('filter-toggle');
    
    function getConnectedSpans(selectedSpan, maxDepth = 1) {
        const connectedIds = new Map([[selectedSpan.id, 0]]); // Map of id to depth level
        const toProcess = new Map([[selectedSpan.id, 0]]); // Map of id to depth level
        const processed = new Set();

        while (toProcess.size > 0) {
            const [[currentId, currentDepth]] = toProcess.entries();
            toProcess.delete(currentId);
            processed.add(currentId);

            // If we've reached max depth and it's not -1 (unlimited), skip processing more connections
            if (maxDepth !== -1 && currentDepth >= maxDepth) continue;

            // Check all links for connections
            if (data.links) {
                data.links.forEach(link => {
                    let connectedId = null;
                    
                    if (link.source === currentId) {
                        connectedId = link.target;
                    } else if (link.target === currentId) {
                        connectedId = link.source;
                    }

                    if (connectedId && !processed.has(connectedId) && !toProcess.has(connectedId)) {
                        const newDepth = currentDepth + 1;
                        connectedIds.set(connectedId, newDepth);
                        toProcess.set(connectedId, newDepth);
                    }
                });
            }
        }

        return connectedIds;
    }

    function updateSpanPositions(connectedIds = null) {
        let visibleIndex = 0;
        spans.each(function(d) {
            const span = d3.select(this);
            const nodeRect = span.select('.node-rect');
            
            // Remove all level classes first
            nodeRect.classed('level-0', false)
                .classed('level-1', false)
                .classed('level-2', false)
                .classed('level-3', false)
                .classed('level-more', false);
            
            if (connectedIds instanceof Map) {
                const depth = connectedIds.get(d.id);
                const isVisible = depth !== undefined;
                
                // Set display property on the span group
                span.classed('filtered', isFilterActive && !isVisible);
                
                if (!isFilterActive || isVisible) {
                    // Apply level-based styling
                    if (depth === 0) nodeRect.classed('level-0', true);
                    else if (depth === 1) nodeRect.classed('level-1', true);
                    else if (depth === 2) nodeRect.classed('level-2', true);
                    else if (depth === 3) nodeRect.classed('level-3', true);
                    else if (depth > 3) nodeRect.classed('level-more', true);
                    
                    d.y = visibleIndex * 40 + margin.top;
                    visibleIndex++;
                    
                    span.transition()
                        .duration(300)
                        .attr('transform', `translate(0,${d.y})`);
                }
            } else {
                // Reset when filter is inactive
                span.classed('filtered', false);
                d.y = visibleIndex * 40 + margin.top;
                visibleIndex++;
                
                span.transition()
                    .duration(300)
                    .attr('transform', `translate(0,${d.y})`);
            }
        });
    }

    filterToggle.addEventListener('click', () => {
        isFilterActive = !isFilterActive;
        filterToggle.classList.toggle('active');
        
        const activeSpan = data.nodes.find(d => 
            spans.filter(s => s.id === d.id).select('.node-rect').classed('active')
        );
        
        if (isFilterActive && activeSpan) {
            const depth = parseInt(document.getElementById('connection-depth').value);
            const connectedIds = getConnectedSpans(activeSpan, depth);
            updateSpanPositions(connectedIds);
        } else {
            updateSpanPositions();
        }
    });

    // Add function to center a span in the viewport
    function centerSpanInView(spanElement, d) {
        const svgBounds = svg.node().getBoundingClientRect();
        const spanBounds = spanElement.getBoundingClientRect();
        
        // Calculate the center position of the span
        const spanCenterX = spanBounds.x + spanBounds.width / 2;
        const spanCenterY = spanBounds.y + spanBounds.height / 2;
        
        // Calculate the center of the viewport
        const viewportCenterX = svgBounds.x + svgBounds.width / 2;
        const viewportCenterY = svgBounds.y + svgBounds.height / 2;
        
        // Calculate the required translation
        const dx = viewportCenterX - spanCenterX;
        const dy = viewportCenterY - spanCenterY;
        
        // Get current transform
        const transform = d3.zoomTransform(svg.node());
        
        // Create new transform with adjusted translation
        const newTransform = d3.zoomIdentity
            .translate(transform.x + dx, transform.y + dy)
            .scale(transform.k);
        
        // Apply the transform with a smooth transition
        svg.transition()
            .duration(750)
            .call(zoom.transform, newTransform);
    }

    // Modify the existing click handler for spans
    spans.on('click', (event, d) => {
        const spanElement = event.currentTarget;
        const isActive = d3.select(spanElement).select('.node-rect').classed('active');
        
        // Clear all active states first
        spans.selectAll('.node-rect').classed('active', false);
        
        if (!isActive) {
            // Set clicked span as active
            d3.select(spanElement).select('.node-rect').classed('active', true);
            
            // Center the clicked span in the viewport
            centerSpanInView(spanElement, d);
            
            // Update positions if filter is active
            if (isFilterActive) {
                const depth = parseInt(document.getElementById('connection-depth').value);
                const connectedIds = getConnectedSpans(d, depth);
                updateSpanPositions(connectedIds);
            }
        } else if (isFilterActive) {
            // Reset positions when deselecting if filter is active
            updateSpanPositions();
        }
        
        // Stop event propagation to prevent background click
        event.stopPropagation();
    });

    // Add event listener for connection depth changes
    document.getElementById('connection-depth').addEventListener('change', () => {
        const activeSpan = data.nodes.find(d => 
            spans.filter(s => s.id === d.id).select('.node-rect').classed('active')
        );
        
        if (isFilterActive && activeSpan) {
            const depth = parseInt(document.getElementById('connection-depth').value);
            const connectedIds = getConnectedSpans(activeSpan, depth);
            updateSpanPositions(connectedIds);
        }
    });

    // Modify the background click handler
    background.on('click', () => {
        spans.selectAll('.node-rect').classed('active', false);
        if (isFilterActive) {
            updateSpanPositions();
        }
    });
});
</script>
@endsection 