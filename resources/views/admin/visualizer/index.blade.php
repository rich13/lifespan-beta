@extends('layouts.app')

@section('scripts')
    <script src="https://d3js.org/d3.v7.min.js"></script>
    <style>
        .node {
            cursor: pointer;
        }
        .node circle {
            stroke: #fff;
            stroke-width: 2px;
        }
        .node text {
            font-size: 10px;
            font-family: sans-serif;
            pointer-events: none; /* Prevent text from interfering with drag */
        }
        .link {
            stroke: #999;
            stroke-opacity: 0.6;
        }
        #graph {
            width: 100%;
            height: calc(100vh - 2rem);
            background: #f8f9fa;
        }
        .tooltip {
            position: absolute;
            padding: 12px;
            background: white;
            border: 1px solid #ddd;
            border-radius: 6px;
            pointer-events: none;
            font-size: 12px;
            z-index: 1000;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .legend-panel {
            position: absolute;
            top: 20px;
            right: 20px;
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            z-index: 1000;
            min-width: 200px;
        }
        .legend-item {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
            padding: 4px;
            border-radius: 4px;
            transition: background-color 0.2s;
        }
        .legend-item:hover {
            background-color: #f8f9fa;
        }
        .legend-color {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
        }
        .legend-section {
            margin-bottom: 15px;
        }
        .legend-section h3 {
            font-size: 14px;
            margin-bottom: 10px;
            font-weight: 600;
        }
        .connection-type {
            display: flex;
            align-items: center;
            margin-bottom: 5px;
        }
        .connection-line {
            width: 20px;
            height: 2px;
            margin-right: 8px;
        }
    </style>
@endsection

@section('content')
<div id="graph"></div>

<div class="legend-panel">
    <div class="legend-section">
        <h3>Node Types</h3>
        <div id="node-filters"></div>
    </div>
    <div class="legend-section">
        <h3>Connection Types</h3>
        <div id="connection-types"></div>
    </div>
</div>

<script>
// Data from Laravel
const graphData = {
    nodes: {!! json_encode($nodes) !!},
    links: {!! json_encode($links) !!}
};

// Enhanced color scales
const typeColors = d3.scaleOrdinal()
    .domain(['person', 'organisation', 'place', 'event'])
    .range(['#ff7f0e', '#1f77b4', '#2ca02c', '#d62728']);

const connectionColors = d3.scaleOrdinal()
    .domain(['family', 'work', 'education', 'residence', 'member_of'])
    .range(['#e41a1c', '#377eb8', '#4daf4a', '#984ea3', '#ff7f00']);

// Calculate node degrees (number of connections)
const nodeDegrees = {};
graphData.links.forEach(link => {
    nodeDegrees[link.source] = (nodeDegrees[link.source] || 0) + 1;
    nodeDegrees[link.target] = (nodeDegrees[link.target] || 0) + 1;
});

// Get counts for each node type
const typeCounts = {};
graphData.nodes.forEach(node => {
    typeCounts[node.typeId] = (typeCounts[node.typeId] || 0) + 1;
});

// Create filters for node types
const nodeFilters = d3.select('#node-filters')
    .selectAll('.legend-item')
    .data(typeColors.domain())
    .join('div')
    .attr('class', 'legend-item');

nodeFilters.append('input')
    .attr('type', 'checkbox')
    .attr('checked', true)
    .attr('id', d => `filter-${d}`);

nodeFilters.append('div')
    .attr('class', 'legend-color')
    .style('background-color', d => typeColors(d));

nodeFilters.append('label')
    .attr('class', 'legend-label')
    .attr('for', d => `filter-${d}`)
    .text(d => `${d.charAt(0).toUpperCase() + d.slice(1)} (${typeCounts[d] || 0})`);

// Create legend for connection types
const connectionTypes = d3.select('#connection-types')
    .selectAll('.connection-type')
    .data(connectionColors.domain())
    .join('div')
    .attr('class', 'connection-type');

connectionTypes.append('div')
    .attr('class', 'connection-line')
    .style('background-color', d => connectionColors(d))
    .style('height', '2px');

connectionTypes.append('span')
    .text(d => d.charAt(0).toUpperCase() + d.slice(1));

// Set up the SVG
const width = document.getElementById('graph').clientWidth;
const height = document.getElementById('graph').clientHeight;

const svg = d3.select('#graph')
    .append('svg')
    .attr('width', width)
    .attr('height', height);

// Add zoom behavior
const g = svg.append('g');
const zoom = d3.zoom()
    .scaleExtent([0.1, 4])
    .on('zoom', (event) => {
        g.attr('transform', event.transform);
    });

svg.call(zoom);

// Create tooltip
const tooltip = d3.select('body').append('div')
    .attr('class', 'tooltip')
    .style('opacity', 0);

// Enhanced force simulation
const simulation = d3.forceSimulation(graphData.nodes)
    .force('link', d3.forceLink(graphData.links)
        .id(d => d.id)
        .distance(d => {
            // Adjust link distance based on node types
            if (d.typeId === 'family') return 80;
            if (d.source.typeId === d.target.typeId) return 120;
            return 150;
        }))
    .force('charge', d3.forceManyBody()
        .strength(d => {
            // Adjust repulsion based on node type and connections
            const degree = nodeDegrees[d.id] || 0;
            return -100 - (degree * 20);
        }))
    .force('center', d3.forceCenter(width / 2, height / 2))
    .force('collision', d3.forceCollide().radius(d => {
        const degree = nodeDegrees[d.id] || 0;
        return 10 + Math.sqrt(degree) * 3;
    }));

// Create arrow markers for directed relationships
svg.append('defs').selectAll('marker')
    .data(['family', 'work', 'education', 'residence', 'member_of'])
    .join('marker')
    .attr('id', d => `arrow-${d}`)
    .attr('viewBox', '0 -3 6 6')
    .attr('refX', 15)
    .attr('refY', 0)
    .attr('markerWidth', 4)
    .attr('markerHeight', 4)
    .attr('orient', 'auto')
    .append('path')
    .attr('fill', d => connectionColors(d))
    .attr('d', 'M0,-3L6,0L0,3');

// Create the links with enhanced styling
const link = g.append('g')
    .selectAll('line')
    .data(graphData.links)
    .join('line')
    .attr('class', 'link')
    .attr('stroke', d => connectionColors(d.typeId))
    .attr('stroke-width', d => d.typeId === 'family' ? 2 : 1)
    .attr('marker-end', d => `url(#arrow-${d.typeId})`);

// Create the nodes with enhanced styling
const node = g.append('g')
    .selectAll('.node')
    .data(graphData.nodes)
    .join('g')
    .attr('class', 'node')
    .call(d3.drag()
        .on('start', dragstarted)
        .on('drag', dragged)
        .on('end', dragended));

// Add circles to nodes with size based on connections
node.append('circle')
    .attr('r', d => {
        const degree = nodeDegrees[d.id] || 0;
        return 5 + Math.sqrt(degree) * 2;
    })
    .attr('fill', d => typeColors(d.typeId));

// Add labels to nodes
node.append('text')
    .attr('dx', d => {
        const degree = nodeDegrees[d.id] || 0;
        return 8 + Math.sqrt(degree) * 2;
    })
    .attr('dy', '.35em')
    .text(d => d.name);

// Enhanced hover effects
node.on('mouseover', function(event, d) {
    // Highlight connected nodes and links
    const connectedNodes = new Set();
    const connectedLinks = graphData.links.filter(l => {
        if (l.source.id === d.id) {
            connectedNodes.add(l.target.id);
            return true;
        }
        if (l.target.id === d.id) {
            connectedNodes.add(l.source.id);
            return true;
        }
        return false;
    });

    node.style('opacity', n => 
        n.id === d.id || connectedNodes.has(n.id) ? 1 : 0.1
    );
    link.style('opacity', l =>
        l.source.id === d.id || l.target.id === d.id ? 1 : 0.1
    );

    // Enhanced tooltip
    const connectionsByType = {};
    connectedLinks.forEach(l => {
        connectionsByType[l.type] = (connectionsByType[l.type] || 0) + 1;
    });

    tooltip.transition()
        .duration(200)
        .style('opacity', .9);
    
    let tooltipContent = `
        <strong>${d.name}</strong><br/>
        Type: ${d.type}<br/>
        Total Connections: ${connectedLinks.length}<br/>
        <small>Connection Types:</small><br/>
    `;
    
    Object.entries(connectionsByType).forEach(([type, count]) => {
        tooltipContent += `- ${type}: ${count}<br/>`;
    });

    tooltip.html(tooltipContent)
        .style('left', (event.pageX + 10) + 'px')
        .style('top', (event.pageY - 10) + 'px');
})
.on('mouseout', function() {
    // Reset highlights
    node.style('opacity', 1);
    link.style('opacity', 0.6);
    
    tooltip.transition()
        .duration(500)
        .style('opacity', 0);
});

// Update positions on each tick
simulation.on('tick', () => {
    link
        .attr('x1', d => d.source.x)
        .attr('y1', d => d.source.y)
        .attr('x2', d => d.target.x)
        .attr('y2', d => d.target.y);

    node.attr('transform', d => `translate(${d.x},${d.y})`);
});

// Drag functions
function dragstarted(event) {
    if (!event.active) simulation.alphaTarget(0.3).restart();
    event.subject.fx = event.subject.x;
    event.subject.fy = event.subject.y;
}

function dragged(event) {
    event.subject.fx = event.x;
    event.subject.fy = event.y;
}

function dragended(event) {
    if (!event.active) simulation.alphaTarget(0);
    event.subject.fx = null;
    event.subject.fy = null;
}

// Filter function
function filterNodes(type, isVisible) {
    const visibleTypes = typeColors.domain().filter(t => 
        document.getElementById(`filter-${t}`).checked
    );
    
    node.style('display', n => 
        visibleTypes.includes(n.typeId) ? null : 'none'
    );
    
    link.style('display', d => {
        const sourceVisible = visibleTypes.includes(d.source.typeId);
        const targetVisible = visibleTypes.includes(d.target.typeId);
        return sourceVisible && targetVisible ? null : 'none';
    });

    simulation.alpha(0.3).restart();
}

// Add filter event listeners
nodeFilters.select('input')
    .on('change', function(event, d) {
        filterNodes(d, this.checked);
    });
</script>
@endsection 