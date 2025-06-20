@extends('layouts.app')

@section('page_title')
    Family Tree
@endsection

@section('page_tools')
    <div class="d-flex gap-2">
        <button class="btn btn-sm btn-outline-primary" onclick="resetZoom()">
            <i class="bi bi-arrows-fullscreen me-1"></i>Reset View
        </button>
    </div>
@endsection

@push('styles')
<style>
    .node {
        cursor: pointer;
        transition: all 0.3s ease;
        stroke-width: 2px;
    }

    .node:hover {
        stroke-width: 4px;
        filter: brightness(1.1);
    }

    .node.selected {
        stroke-width: 4px;
        stroke: #000;
    }

    .link {
        stroke: #6c757d;
        stroke-width: 2px;
        fill: none;
        opacity: 0.7;
    }

    .tooltip {
        position: absolute;
        background: rgba(0, 0, 0, 0.8);
        color: white;
        padding: 8px 12px;
        border-radius: 4px;
        font-size: 12px;
        pointer-events: none;
        z-index: 1000;
        max-width: 200px;
    }

    .no-family-message {
        text-align: center;
        padding: 2rem;
        color: #6c757d;
    }

    .loading {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        color: #6c757d;
    }

    .node-text {
        font-size: 11px;
        font-weight: 500;
        text-anchor: middle;
        dominant-baseline: middle;
        pointer-events: none;
        fill: #495057;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    }

    /* Family tree specific styles */
    .family-tree-container {
        height: calc(100vh - 200px);
        min-height: 500px;
        position: relative;
    }

    .family-tree-svg {
        width: 100%;
        height: 100%;
    }

    .info-panel {
        height: calc(100vh - 200px);
        min-height: 500px;
        overflow-y: auto;
    }
</style>
@endpush

@section('content')
<div class="row">
    <!-- Left column: Family Tree -->
    <div class="col-lg-8">
        @if($message)
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-yellow-800">No Family Relationships Found</h3>
                        <div class="mt-2 text-sm text-yellow-700">
                            <p>{{ $message }}</p>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        @if(!$familyData || empty($familyData['nodes']))
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-6">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-blue-800">No Family Relationships Found</h3>
                        <div class="mt-2 text-sm text-blue-700">
                            <p>No family relationships have been added for {{ $familyData['name'] ?? 'you' }} yet.</p>
                            <p class="mt-1">To see your family tree, you'll need to add family relationships through the Spans section.</p>
                        </div>
                    </div>
                </div>
            </div>
        @else
            <div class="bg-white rounded shadow border family-tree-container">
                <div id="family-tree" class="w-100 h-100"></div>
            </div>
        @endif
    </div>

    <!-- Right column: Information Panel -->
    <div class="col-lg-4">
        <div class="bg-light rounded shadow border p-4 info-panel">
            <!-- Color Key Section -->
            <div class="mb-4">
                <h5 class="card-title mb-3">Family Tree Key</h5>
                <div class="d-flex flex-wrap gap-2">
                    <span class="badge" style="background-color: #3B82F6; color: white;">Current User</span>
                    <span class="badge" style="background-color: #10B981; color: white;">Parent</span>
                    <span class="badge" style="background-color: #8B5CF6; color: white;">Grandparent</span>
                    <span class="badge" style="background-color: #7C3AED; color: white;">Ancestor</span>
                    <span class="badge" style="background-color: #F59E0B; color: white;">Sibling</span>
                    <span class="badge" style="background-color: #EF4444; color: white;">Child</span>
                    <span class="badge" style="background-color: #EC4899; color: white;">Grandchild</span>
                    <span class="badge" style="background-color: #FB7185; color: white;">Descendant</span>
                </div>
            </div>
            
            <hr class="my-4">
            
            <!-- Node Info Section -->
            <div id="info-panel" class="h-100">
                <div class="text-center text-muted mt-4">
                    <svg class="mx-auto h-12 w-12 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                    <h3 class="mt-2 text-sm font-medium">No person selected</h3>
                    <p class="mt-1 text-sm">Click on a family member to see their details.</p>
                </div>
            </div>
        </div>
    </div>
</div>

@if($familyData && !empty($familyData['nodes']))
<script src="https://d3js.org/d3.v7.min.js"></script>
<script>
// Helper function to get node color
function getNodeColor(type) {
    switch(type) {
        case 'current-user': return "#3B82F6";
        case 'parent': return "#10B981";
        case 'grandparent': return "#8B5CF6";
        case 'ancestor': return "#7C3AED";
        case 'sibling': return "#F59E0B";
        case 'child': return "#EF4444";
        case 'grandchild': return "#EC4899";
        case 'descendant': return "#FB7185";
        default: return "#6B7280";
    }
}

// Function to show node information in the right panel
function showNodeInfo(nodeData) {
    const infoPanel = document.getElementById('info-panel');
    
    // Get family relationships from the existing graph data
    const familyData = @json($familyData);
    
    // Find parents of this person (nodes that have links TO this person as target)
    const parents = familyData.links
        .filter(link => link.target === nodeData.id)
        .map(link => familyData.nodes.find(node => node.id === link.source))
        .filter(parent => parent);
    
    // Find children of this person (nodes that this person has links TO as source)
    const children = familyData.links
        .filter(link => link.source === nodeData.id)
        .map(link => familyData.nodes.find(node => node.id === link.target))
        .filter(child => child);
    
    // Create family relationships HTML
    let familyRelationships = '';
    
    if (parents.length > 0) {
        familyRelationships += `
            <div class="card mb-3">
                <div class="card-body">
                    <h6 class="card-title">Parents</h6>
                    <ul class="list-unstyled mb-0">
                        ${parents.map(parent => `
                            <li class="mb-2">
                                <a href="/spans/${parent.id}" class="text-decoration-none d-inline-flex align-items-center gap-1">
                                    <i class="bi bi-person-fill"></i>
                                    <strong>${parent.name}</strong>
                                </a>
                            </li>
                        `).join('')}
                    </ul>
                </div>
            </div>
        `;
    }
    
    if (children.length > 0) {
        familyRelationships += `
            <div class="card mb-3">
                <div class="card-body">
                    <h6 class="card-title">Children</h6>
                    <ul class="list-unstyled mb-0">
                        ${children.map(child => `
                            <li class="mb-2">
                                <a href="/spans/${child.id}" class="text-decoration-none d-inline-flex align-items-center gap-1">
                                    <i class="bi bi-person-fill"></i>
                                    <strong>${child.name}</strong>
                                </a>
                            </li>
                        `).join('')}
                    </ul>
                </div>
            </div>
        `;
    }
    
    // Create the HTML for node information
    const html = `
        <div class="space-y-4">
            <div class="text-center">
                <div class="w-16 h-16 mx-auto rounded-full flex items-center justify-center text-white font-bold text-lg" 
                     style="background-color: ${getNodeColor(nodeData.type)}">
                    ${nodeData.name.charAt(0).toUpperCase()}
                </div>
                <h2 class="mt-2 text-xl font-bold">${nodeData.name}</h2>
                <p class="text-sm text-muted capitalize">${nodeData.type.replace('-', ' ')}</p>
            </div>
            
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Details</h5>
                    <div class="space-y-2 text-sm">
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">ID:</span>
                            <span class="font-monospace">${nodeData.id}</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Type:</span>
                            <span class="capitalize">${nodeData.type.replace('-', ' ')}</span>
                        </div>
                    </div>
                    <div class="mt-3">
                        <a href="/spans/${nodeData.id}" class="btn btn-primary btn-sm me-2">
                            <i class="bi bi-eye"></i> View Full Details
                        </a>
                        <a href="/spans/${nodeData.id}/edit" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-pencil"></i> Edit
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Family Relationships</h5>
                    ${familyRelationships ? familyRelationships : `
                        <p class="text-muted small">No family relationships found in the graph.</p>
                        <button class="btn btn-outline-primary btn-sm w-100 mt-2" onclick="addParent(${nodeData.id})">
                            <i class="bi bi-plus-circle me-1"></i>Add Parent
                        </button>
                    `}
                </div>
            </div>
        </div>
    `;
    
    infoPanel.innerHTML = html;
}

// Function to handle adding a parent (placeholder for now)
function addParent(personId) {
    console.log('Add parent for person:', personId);
    // TODO: Implement add parent functionality
    alert('Add parent functionality will be implemented here');
}

document.addEventListener('DOMContentLoaded', function() {
    console.log('D3 script starting...');
    
    try {
        const familyData = @json($familyData);
        
        console.log('Family data:', familyData);
        console.log('Nodes count:', familyData.nodes.length);
        console.log('Links count:', familyData.links.length);
        
        // Get the container dimensions
        const container = document.getElementById('family-tree');
        if (!container) {
            console.error('Container not found!');
            return;
        }
        
        // Wait a bit for the layout to settle
        setTimeout(() => {
            let containerWidth = container.clientWidth || container.offsetWidth || container.getBoundingClientRect().width;
            let containerHeight = container.clientHeight || container.offsetHeight || container.getBoundingClientRect().height;
            
            console.log('Container dimensions:', { width: containerWidth, height: containerHeight });
            console.log('Container element:', container);
            console.log('Container styles:', window.getComputedStyle(container));
            
            if (containerWidth === 0 || containerHeight === 0) {
                console.error('Container has zero dimensions!');
                console.log('Trying alternative approach...');
                
                // Try to get dimensions from parent
                const parent = container.parentElement;
                const parentWidth = parent.clientWidth || parent.offsetWidth;
                const parentHeight = parent.clientHeight || parent.offsetHeight;
                
                console.log('Parent dimensions:', { width: parentWidth, height: parentHeight });
                
                if (parentWidth > 0 && parentHeight > 0) {
                    // Use parent dimensions
                    container.style.width = parentWidth + 'px';
                    container.style.height = parentHeight + 'px';
                    
                    // Update our variables
                    containerWidth = parentWidth;
                    containerHeight = parentHeight;
                } else {
                    console.error('Parent also has zero dimensions!');
                    console.log('Using fallback dimensions...');
                    
                    // Force reasonable dimensions
                    container.style.width = '800px';
                    container.style.height = '600px';
                    containerWidth = 800;
                    containerHeight = 600;
                }
            }
            
            // Set up the SVG
            const svg = d3.select("#family-tree")
                .append("svg")
                .attr("width", containerWidth)
                .attr("height", containerHeight)
                .attr("class", "family-tree-svg");
            
            console.log('SVG created');
            
            // Add zoom behavior
            const zoom = d3.zoom()
                .scaleExtent([0.1, 3])
                .on('zoom', (event) => {
                    svg.select('g').attr('transform', event.transform);
                });
            
            svg.call(zoom);
            
            // Create the main group for the visualization
            const g = svg.append('g');
            
            // Create the force simulation
            const simulation = d3.forceSimulation(familyData.nodes)
                .force("link", d3.forceLink(familyData.links).id(d => d.id).distance(120))
                .force("charge", d3.forceManyBody().strength(-200))
                .force("collision", d3.forceCollide().radius(d => Math.max(40, d.name.length * 4) + 10));
            
            console.log('Force simulation created');
            
            // Function to check if node has both parents
            function hasBothParents(nodeId) {
                const parentLinks = familyData.links.filter(link => 
                    link.target.id === nodeId && 
                    (link.source.type === 'parent' || link.source.type === 'grandparent' || link.source.type === 'ancestor')
                );
                return parentLinks.length >= 2;
            }
            
            // Function to get parent nodes
            function getParentNodes(nodeId) {
                const parentLinks = familyData.links.filter(link => 
                    link.target.id === nodeId && 
                    (link.source.type === 'parent' || link.source.type === 'grandparent' || link.source.type === 'ancestor')
                );
                return parentLinks.map(link => link.source);
            }
            
            // Function to highlight parents
            function highlightParents(nodeId) {
                const parentNodes = getParentNodes(nodeId);
                parentNodes.forEach(parent => {
                    const parentElement = node.filter(d => d.id === parent.id);
                    parentElement.select("rect")
                        .style("stroke", "#000")
                        .style("stroke-width", "4px")
                        .style("filter", "brightness(1.2)");
                });
            }
            
            // Function to clear all highlights
            function clearHighlights() {
                node.select("rect")
                    .style("stroke", "#fff")
                    .style("stroke-width", "2px")
                    .style("filter", "brightness(1)");
            }
            
            // Set initial positions to fit in viewport
            const nodeCount = familyData.nodes.length;
            const radius = Math.min(containerWidth, containerHeight) * 0.3;
            const centerX = containerWidth / 2;
            const centerY = containerHeight / 2;
            
            familyData.nodes.forEach((node, i) => {
                const angle = (i / nodeCount) * 2 * Math.PI;
                node.x = centerX + radius * Math.cos(angle);
                node.y = centerY + radius * Math.sin(angle);
            });
            
            console.log('Initial positions set');
            
            // Create the links
            const link = g.append("g")
                .selectAll("line")
                .data(familyData.links)
                .enter().append("line")
                .attr("stroke", "#999")
                .attr("stroke-opacity", 0.6)
                .attr("stroke-width", 2);
            
            console.log('Links created:', familyData.links.length);
            
            // Create the nodes
            const node = g.append("g")
                .selectAll("g")
                .data(familyData.nodes)
                .enter().append("g")
                .call(d3.drag()
                    .on("start", dragstarted)
                    .on("drag", dragged)
                    .on("end", dragended))
                .on("click", function(event, d) {
                    // Remove selection from all nodes
                    node.select("rect").classed("selected", false);
                    // Add selection to clicked node
                    d3.select(this).select("rect").classed("selected", true);
                    // Show information in right panel
                    showNodeInfo(d);
                })
                .on("mouseover", function(event, d) {
                    // Highlight parents when hovering over a node
                    highlightParents(d.id);
                })
                .on("mouseout", function() {
                    // Clear highlights when mouse leaves a node
                    clearHighlights();
                });
            
            console.log('Nodes created:', familyData.nodes.length);
            
            // Add rounded rectangles for the nodes
            node.append("rect")
                .attr("rx", 8) // Rounded corners
                .attr("ry", 8)
                .attr("width", d => Math.max(80, d.name.length * 8)) // Dynamic width based on name length
                .attr("height", 30)
                .attr("x", d => -Math.max(80, d.name.length * 8) / 2) // Center the rectangle
                .attr("y", -15)
                .style("fill", d => {
                    switch(d.type) {
                        case 'current-user': return "#3B82F6"; // Blue for current user
                        case 'parent': return "#10B981"; // Green for parents
                        case 'grandparent': return "#8B5CF6"; // Purple for grandparents
                        case 'ancestor': return "#7C3AED"; // Dark purple for ancestors
                        case 'sibling': return "#F59E0B"; // Orange for siblings
                        case 'child': return "#EF4444"; // Red for children
                        case 'grandchild': return "#EC4899"; // Pink for grandchildren
                        case 'descendant': return "#FB7185"; // Light red for descendants
                        default: return "#6B7280"; // Gray for unknown types
                    }
                })
                .style("stroke", "#fff")
                .style("stroke-width", "2px");
            
            // Add labels for the nodes
            node.append("text")
                .text(d => d.name)
                .attr("text-anchor", "middle")
                .attr("dy", "0.35em")
                .style("font-size", "11px")
                .style("font-weight", "bold")
                .style("fill", "#fff")
                .style("pointer-events", "none");
            
            console.log('Node styling applied');
            
            // Add boundary constraints
            simulation.on("tick", () => {
                // Keep nodes within bounds
                familyData.nodes.forEach(d => {
                    const nodeWidth = Math.max(80, d.name.length * 8);
                    const nodeHeight = 30;
                    const margin = Math.max(nodeWidth, nodeHeight) / 2 + 10;
                    
                    d.x = Math.max(margin, Math.min(containerWidth - margin, d.x));
                    d.y = Math.max(margin, Math.min(containerHeight - margin, d.y));
                });
                
                // Update link positions
                link
                    .attr("x1", d => d.source.x)
                    .attr("y1", d => d.source.y)
                    .attr("x2", d => d.target.x)
                    .attr("y2", d => d.target.y);
                
                // Update node positions
                node
                    .attr("transform", d => `translate(${d.x},${d.y})`);
            });
            
            console.log('Simulation tick handler set');
            
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
            
            // Handle window resize
            window.addEventListener('resize', function() {
                // Get new container dimensions
                const newContainerWidth = container.clientWidth;
                const newContainerHeight = container.clientHeight;
                
                // Update SVG size
                svg.attr("width", newContainerWidth)
                   .attr("height", newContainerHeight);
                
                // Update force center
                simulation.force("center", d3.forceCenter(newContainerWidth / 2, newContainerHeight / 2));
                simulation.alpha(0.3).restart();
            });
            
            console.log('D3 visualization complete!');
            
            // Set initial zoom to fit all nodes
            setTimeout(() => {
                // Calculate bounds of all nodes
                const xExtent = d3.extent(familyData.nodes, d => d.x);
                const yExtent = d3.extent(familyData.nodes, d => d.y);
                
                const nodeWidth = xExtent[1] - xExtent[0];
                const nodeHeight = yExtent[1] - yExtent[0];
                
                // Add some padding
                const padding = 50;
                const scaleX = (containerWidth - padding) / nodeWidth;
                const scaleY = (containerHeight - padding) / nodeHeight;
                const scale = Math.min(scaleX, scaleY, 1); // Don't zoom in, only out
                
                // Calculate center of nodes
                const centerX = (xExtent[0] + xExtent[1]) / 2;
                const centerY = (yExtent[0] + yExtent[1]) / 2;
                
                // Calculate translation to center the nodes
                const translateX = containerWidth / 2 - centerX * scale;
                const translateY = containerHeight / 2 - centerY * scale;
                
                // Apply the transform
                svg.transition().duration(1000).call(
                    zoom.transform,
                    d3.zoomIdentity.translate(translateX, translateY).scale(scale)
                );
            }, 2000); // Wait for simulation to settle
        }, 100);
        
    } catch (error) {
        console.error('Error in D3 script:', error);
    }
});

function resetZoom() {
    const svg = d3.select('#family-tree svg');
    svg.transition().duration(750).call(
        d3.zoom().transform,
        d3.zoomIdentity
    );
}
</script>
@endif
@endsection 