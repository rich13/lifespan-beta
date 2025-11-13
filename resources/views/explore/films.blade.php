@extends('layouts.app')

@section('page_title')
    <x-breadcrumb :items="[
        [
            'text' => 'Explore',
            'icon' => 'view',
            'icon_category' => 'action',
            'url' => route('explore.index')
        ],
        [
            'text' => 'Films',
            'icon' => 'film',
            'icon_category' => 'bootstrap'
        ]
    ]" />
@endsection

@push('styles')
<style>
    .node {
        cursor: pointer;
    }
    .node circle {
        stroke: #fff;
        stroke-width: 2px;
    }
    .node text {
        font-size: 11px;
        font-family: sans-serif;
        pointer-events: none;
    }
    .link {
        stroke: #999;
        stroke-opacity: 0.6;
    }
    .link.features {
        stroke: #3b82f6;
        stroke-width: 2;
    }
    .link.created {
        stroke: #10b981;
        stroke-width: 2;
    }
    #graph {
        width: 100%;
        height: calc(100vh - 200px);
        min-height: 600px;
        background: #f8f9fa;
    }
    .tooltip {
        position: absolute;
        padding: 10px;
        background: white;
        border: 1px solid #ddd;
        border-radius: 6px;
        pointer-events: none;
        font-size: 12px;
        z-index: 1000;
        box-shadow: 0 2px 8px rgba(0,0,0,0.15);
    }
    .legend {
        background: white;
        padding: 15px;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .legend-item {
        display: flex;
        align-items: center;
        margin-bottom: 8px;
    }
    .legend-color {
        width: 16px;
        height: 16px;
        border-radius: 50%;
        margin-right: 8px;
        border: 2px solid #fff;
    }
    .legend-line {
        width: 30px;
        height: 2px;
        margin-right: 8px;
    }
</style>
@endpush

@section('content')
<div class="container-fluid px-0">

    <div class="position-relative">
        <div class="position-absolute" style="top: 20px; left: 20px; z-index: 1000;">
            <div class="legend">
                <h6 class="mb-3">Legend</h6>
                <div class="legend-item">
                    <div class="legend-color" style="background: #ef4444;"></div>
                    <span>Films</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background: #3b82f6;"></div>
                    <span>People</span>
                </div>
                <div class="legend-item">
                    <div class="legend-line" style="background: #10b981;"></div>
                    <span>Created</span>
                </div>
                <div class="legend-item">
                    <div class="legend-line" style="background: #3b82f6;"></div>
                    <span>Features</span>
                </div>
            </div>
        </div>
        <div id="graph"></div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://d3js.org/d3.v7.min.js"></script>
<script>
$(document).ready(function() {
    const graphData = @json($graphData);
    
    const container = document.getElementById('graph');
    const width = container.clientWidth;
    const height = container.clientHeight;
    
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
    
    // Color scale for node types
    const nodeColor = d => {
        if (d.type === 'film') return '#ef4444';
        if (d.type === 'person') return '#3b82f6';
        return '#999';
    };
    
    // Link color based on connection type
    const linkColor = d => {
        if (d.type === 'created') return '#10b981';
        if (d.type === 'features') return '#3b82f6';
        return '#999';
    };
    
    // Convert link indices to node references
    const links = graphData.links.map(link => ({
        source: graphData.nodes[link.source],
        target: graphData.nodes[link.target],
        type: link.type,
        type_id: link.type_id
    }));
    
    // Create force simulation
    const simulation = d3.forceSimulation(graphData.nodes)
        .force('link', d3.forceLink(links)
            .id(d => d.id)
            .distance(d => {
                // Shorter distance for created connections
                return d.type === 'created' ? 80 : 120;
            }))
        .force('charge', d3.forceManyBody().strength(-300))
        .force('center', d3.forceCenter(width / 2, height / 2))
        .force('collision', d3.forceCollide().radius(d => {
            return d.type === 'film' ? 25 : 20;
        }));
    
    // Create links
    const link = g.append('g')
        .selectAll('line')
        .data(links)
        .join('line')
        .attr('class', d => `link ${d.type}`)
        .attr('stroke', linkColor)
        .attr('stroke-width', 2)
        .attr('stroke-opacity', 0.6);
    
    // Create nodes
    const node = g.append('g')
        .selectAll('g')
        .data(graphData.nodes)
        .join('g')
        .attr('class', 'node')
        .call(d3.drag()
            .on('start', dragstarted)
            .on('drag', dragged)
            .on('end', dragended));
    
    // Add circles to nodes
    node.append('circle')
        .attr('r', d => d.type === 'film' ? 12 : 10)
        .attr('fill', nodeColor)
        .attr('stroke', '#fff')
        .attr('stroke-width', 2);
    
    // Add labels to nodes
    node.append('text')
        .attr('dx', d => d.type === 'film' ? 15 : 13)
        .attr('dy', '.35em')
        .text(d => d.name)
        .style('font-size', d => d.type === 'film' ? '12px' : '11px')
        .style('font-weight', d => d.type === 'film' ? '600' : '400');
    
    // Hover effects
    node.on('mouseover', function(event, d) {
        // Highlight connected nodes and links
        const connectedNodeIds = new Set();
        connectedNodeIds.add(d.id);
        
        const connectedLinks = links.filter(l => {
            if (l.source.id === d.id) {
                connectedNodeIds.add(l.target.id);
                return true;
            }
            if (l.target.id === d.id) {
                connectedNodeIds.add(l.source.id);
                return true;
            }
            return false;
        });
        
        node.style('opacity', n => connectedNodeIds.has(n.id) ? 1 : 0.1);
        link.style('opacity', l => {
            return connectedNodeIds.has(l.source.id) || connectedNodeIds.has(l.target.id) ? 0.8 : 0.05;
        });
        
        // Show tooltip
        tooltip.transition()
            .duration(200)
            .style('opacity', .9);
        tooltip.html(`
            <strong>${d.name}</strong><br/>
            Type: ${d.type}<br/>
            <small>Click to view</small>
        `)
        .style('left', (event.pageX + 10) + 'px')
        .style('top', (event.pageY - 28) + 'px');
    })
    .on('mouseout', function() {
        node.style('opacity', 1);
        link.style('opacity', 0.6);
        tooltip.transition()
            .duration(500)
            .style('opacity', 0);
    })
    .on('click', function(event, d) {
        window.location.href = d.url;
    });
    
    // Update positions on simulation tick
    simulation.on('tick', () => {
        link
            .attr('x1', d => d.source.x)
            .attr('y1', d => d.source.y)
            .attr('x2', d => d.target.x)
            .attr('y2', d => d.target.y);
        
        node.attr('transform', d => `translate(${d.x},${d.y})`);
    });
    
    // Drag functions
    function dragstarted(event, d) {
        if (!event.active) simulation.alphaTarget(0.3).restart();
        d.fx = d.x;
        d.fy = d.y;
    }
    
    function dragged(event, d) {
        d.fx = event.x;
        d.fy = event.y;
    }
    
    function dragended(event, d) {
        if (!event.active) simulation.alphaTarget(0);
        d.fx = null;
        d.fy = null;
    }
    
    // Zoom to fit all nodes after simulation settles
    simulation.on('end', function() {
        // Calculate bounding box of all nodes using their x,y coordinates
        const bounds = graphData.nodes.reduce((acc, n) => {
            if (n.x === undefined || n.y === undefined) return acc;
            if (acc.minX === null || n.x < acc.minX) acc.minX = n.x;
            if (acc.maxX === null || n.x > acc.maxX) acc.maxX = n.x;
            if (acc.minY === null || n.y < acc.minY) acc.minY = n.y;
            if (acc.maxY === null || n.y > acc.maxY) acc.maxY = n.y;
            return acc;
        }, { minX: null, maxX: null, minY: null, maxY: null });
        
        if (bounds.minX !== null && bounds.maxX !== null) {
            const padding = 50;
            const graphWidth = bounds.maxX - bounds.minX;
            const graphHeight = bounds.maxY - bounds.minY;
            
            // Only zoom if there's actual spread, and don't zoom in (only out)
            if (graphWidth > 0 && graphHeight > 0) {
                const scale = Math.min(
                    (width - padding * 2) / graphWidth,
                    (height - padding * 2) / graphHeight,
                    1 // Don't zoom in, only out
                );
                
                const centerX = (bounds.minX + bounds.maxX) / 2;
                const centerY = (bounds.minY + bounds.maxY) / 2;
                
                const translateX = width / 2 - centerX * scale;
                const translateY = height / 2 - centerY * scale;
                
                const transform = d3.zoomIdentity
                    .translate(translateX, translateY)
                    .scale(scale);
                
                svg.transition()
                    .duration(750)
                    .call(zoom.transform, transform);
            }
        }
    });
    
    // Handle window resize
    window.addEventListener('resize', function() {
        const newWidth = container.clientWidth;
        const newHeight = container.clientHeight;
        
        svg.attr('width', newWidth).attr('height', newHeight);
        simulation.force('center', d3.forceCenter(newWidth / 2, newHeight / 2));
        simulation.alpha(0.3).restart();
    });
});
</script>
@endpush

