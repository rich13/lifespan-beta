@extends('layouts.app')

@section('page_title')
    @php
        $breadcrumbItems = [
            [
                'text' => 'Settings',
                'url' => route('settings.index'),
                'icon' => 'gear',
                'icon_category' => 'action'
            ],
            [
                'text' => 'Spans',
                'url' => route('settings.spans'),
                'icon' => 'diagram-3',
                'icon_category' => 'action'
            ]
        ];
    @endphp
    
    <x-breadcrumb :items="$breadcrumbItems" />
@endsection

@push('styles')
<x-shared.interactive-card-styles />
<style>
    .graph-container {
        width: 100%;
        height: 600px;
        border: 1px solid #dee2e6;
        border-radius: 0.375rem;
        background: #f8f9fa;
    }
    
    .node {
        cursor: pointer;
    }
    
    .node circle {
        stroke: #fff;
        stroke-width: 2px;
    }
    
    .node text {
        font-size: 12px;
        font-family: sans-serif;
    }
    
    .link {
        stroke: #999;
        stroke-opacity: 0.6;
        stroke-width: 2px;
    }
    
    .link:hover {
        stroke-opacity: 1;
    }
    

    
    .legend {
        display: flex;
        justify-content: center;
        gap: 20px;
        margin-bottom: 20px;
        flex-wrap: wrap;
    }
    
    .legend-item {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 14px;
    }
    
    .legend-color {
        width: 16px;
        height: 16px;
        border-radius: 50%;
        border: 2px solid #fff;
    }
</style>
@endpush

@section('content')
    <div class="container-fluid">
        <div class="row">
            <!-- Left Sidebar Menu -->
            <div class="col-md-3">
                <x-settings-nav active="spans" />
            </div>

            <!-- Main Content Area -->
            <div class="col-md-6">
                <!-- Spans Overview -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-eye me-2"></i>Visibility of your spans (experimental)
                        </h5>
                    </div>
                    <div class="card-body">
                        @if($personalSpan)
                            <p>
                                This is an <strong>not-working-yet</strong> feature to help you understand who can see what.
                            </p>
                                                     
                            <!-- Legend -->
                            <div class="legend">
                                <div class="legend-item">
                                    <div class="legend-color" style="background-color: #3b82f6;"></div>
                                    <span>Your personal span</span>
                                </div>
                                <div class="legend-item">
                                    <div class="legend-color" style="background-color: #f59e0b;"></div>
                                    <span>Types of connection</span>
                                </div>
                                <div class="legend-item">
                                    <div class="legend-color" style="background-color: #d97706;"></div>
                                    <span>Connected Spans</span>
                                </div>
                            </div>
                            
                            <!-- Perspective Descriptions could go here-->
                            
                            
                            <!-- Perspective Switcher and Group Selector -->
                            <div class="mb-3 d-flex justify-content-between align-items-start">
                                <!-- Perspective Switcher (left-aligned) -->
                                <div>
                                    <div class="btn-group" role="group" aria-label="View perspective">
                                        <input type="radio" class="btn-check" name="perspective" id="perspective-you" value="you" checked>
                                        <label class="btn btn-outline-primary btn-sm" for="perspective-you">
                                            <i class="bi bi-person-fill me-1"></i>You
                                        </label>
                                        
                                        <input type="radio" class="btn-check" name="perspective" id="perspective-shared" value="shared">
                                        <label class="btn btn-outline-primary btn-sm" for="perspective-shared">
                                            <i class="bi bi-people-fill me-1"></i>Shared Access
                                        </label>
                                        
                                        <input type="radio" class="btn-check" name="perspective" id="perspective-public" value="public">
                                        <label class="btn btn-outline-primary btn-sm" for="perspective-public">
                                            <i class="bi bi-person-x me-1"></i>Public/Guest
                                        </label>
                                    </div>
                                </div>
                                
                                <!-- Group Selector (right-aligned, shown when Shared Access is selected) -->
                                <div id="group-selector" style="display: none;">
                                    @if($userGroups->count() > 0)
                                        <div class="text-end">
                                            <label class="form-label small text-muted">View as member of:</label>
                                            <div class="btn-group" role="group" aria-label="Group perspective">
                                                @foreach($userGroups as $group)
                                                <input type="radio" class="btn-check" name="group-perspective" id="group-perspective-{{ $group->id }}" value="{{ $group->id }}" {{ $loop->first ? 'checked' : '' }}>
                                                <label class="btn btn-outline-secondary btn-sm" for="group-perspective-{{ $group->id }}">
                                                    <i class="bi bi-people-fill me-1"></i>{{ $group->name }}
                                                </label>
                                                @endforeach
                                            </div>
                                        </div>
                                    @else
                                        <div class="alert alert-info small">
                                            <i class="bi bi-info-circle me-1"></i>
                                            You're not a member of any groups, so there are no shared spans to view.
                                        </div>
                                    @endif
                                </div>
                            </div>
                            
                            <!-- Graph Container -->
                            <div class="graph-container" id="graph-container"></div>
                            
                            <!-- Statistics -->
                            <div class="row mt-4">
                                @php
                                    $predicateNodes = collect($graphData['nodes'])->where('type', 'predicate');
                                    $connectedSpans = collect($graphData['nodes'])->where('type', '!=', 'predicate')->where('isPersonal', false);
                                    $totalConnections = collect($graphData['nodes'])->where('type', 'predicate')->sum('connectionCount');
                                @endphp
                                <div class="col-md-3">
                                    <div class="card bg-primary-subtle">
                                        <div class="card-body text-center">
                                            <div class="stat-number text-primary">{{ count($graphData['nodes']) }}</div>
                                            <div class="stat-label">Total Spans</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card bg-warning-subtle">
                                        <div class="card-body text-center">
                                            <div class="stat-number text-warning">{{ $predicateNodes->count() }}</div>
                                            <div class="stat-label">Connection Types</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card bg-success-subtle">
                                        <div class="card-body text-center">
                                            <div class="stat-number text-success">{{ $totalConnections }}</div>
                                            <div class="stat-label">Total Connections</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card bg-info-subtle">
                                        <div class="card-body text-center">
                                            <div class="stat-number text-info">{{ $connectedSpans->count() }}</div>
                                            <div class="stat-label">Connected Spans</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @else
                            <div class="text-center">
                                <i class="bi bi-person-x text-muted mb-3" style="font-size: 3rem;"></i>
                                <h5 class="card-title text-muted">No Personal Span</h5>
                                <p class="card-text text-muted">
                                    You don't have a personal span yet. This is needed to show your connections.
                                </p>
                                <a href="{{ route('spans.create') }}" class="btn btn-outline-primary">
                                    <i class="bi bi-plus-circle me-2"></i>Create Personal Span
                                </a>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Details Panel -->
            <div class="col-md-3">
                <div class="card" id="span-details-card" style="display: none;">
                    <div class="card-header">
                        <h6 class="card-title mb-0">
                            <i class="bi bi-info-circle me-2"></i>Span Details
                        </h6>
                    </div>
                    <div class="card-body" id="span-details-content">
                        <!-- Content will be populated by JavaScript -->
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
@if($personalSpan)
<script src="https://d3js.org/d3.v7.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Graph data from PHP
    const graphData = @json($graphData);
    
    // Set up the graph container
    const container = document.getElementById('graph-container');
    const width = container.clientWidth;
    const height = container.clientHeight;
    
    // Create SVG
    const svg = d3.select('#graph-container')
        .append('svg')
        .attr('width', width)
        .attr('height', height);
    

    
    // Color scale for node types - using the same colors as the connection cards
    const color = d3.scaleOrdinal()
        .domain(['person', 'organisation', 'place', 'event', 'band', 'role', 'thing', 'connection', 'predicate'])
        .range(['#3b82f6', '#059669', '#d97706', '#17596d', '#7c3aed', '#6366f1', '#06b6d4', '#6b7280', '#f59e0b']);
    
    // Create force simulation with radial layout
    const simulation = d3.forceSimulation(graphData.nodes)
        .force('link', d3.forceLink(graphData.links).id(d => d.id).distance(60))
        .force('charge', d3.forceManyBody().strength(-150))
        .force('center', d3.forceCenter(width / 2, height / 2))
        .force('collision', d3.forceCollide().radius(30))
        .force('radial', d3.forceRadial(d => {
            if (d.isPersonal) return 0; // Center
            if (d.type === 'predicate') return 120; // Middle ring
            return 200; // Outer ring
        }, width / 2, height / 2).strength(0.8));
    
    // Create links
    const link = svg.append('g')
        .selectAll('line')
        .data(graphData.links)
        .enter()
        .append('line')
        .attr('class', 'link')
        .style('stroke-dasharray', 'none')

    
    // Create nodes
    const node = svg.append('g')
        .selectAll('g')
        .data(graphData.nodes)
        .enter()
        .append('g')
        .attr('class', 'node')
        .call(d3.drag()
            .on('start', dragstarted)
            .on('drag', dragged)
            .on('end', dragended));
    
    // Add circles to nodes
    node.append('circle')
        .attr('r', d => {
            if (d.isPersonal) return 20;
            if (d.type === 'predicate') return Math.max(12, Math.min(20, 12 + (d.connectionCount || 0) * 0.5));
            return 15;
        })
        .style('fill', d => {
            if (d.isPersonal) return '#3b82f6';
            if (d.type === 'predicate') return '#f59e0b';
            return color(d.type);
        })
        .style('stroke', '#fff')
        .style('stroke-width', d => d.isPersonal ? 3 : 2)
        .style('opacity', 1);
    
    // Add labels to nodes (hidden by default)
    const labels = node.append('text')
        .text(d => {
            if (d.type === 'predicate') {
                let label = d.name.length > 12 ? d.name.substring(0, 12) + '...' : d.name;
                if (d.connectionCount && d.connectionCount > 1) {
                    label += ` (${d.connectionCount})`;
                }
                return label;
            }
            return d.name.length > 15 ? d.name.substring(0, 15) + '...' : d.name;
        })
        .attr('x', 0)
        .attr('y', d => {
            if (d.isPersonal) return 35;
            if (d.type === 'predicate') return 20;
            return 30;
        })
        .attr('text-anchor', 'middle')
        .style('font-size', d => d.type === 'predicate' ? '10px' : '12px')
        .style('font-weight', d => d.isPersonal ? 'bold' : 'normal')
        .style('opacity', 0) // Hidden by default
        .style('pointer-events', 'none'); // Prevent interference with hover
    

    

    
    // Add hover functionality to nodes
    node.on('mouseover', function(event, d) {
        
        // Show labels based on node type and perspective
                if (d.type === 'predicate') {
            // For predicates, show the predicate label and all connected object labels
            labels.style('opacity', labelD => {
                if (labelD.id === d.id) return 1; // Show predicate label
                
                // Check if this node is connected to the hovered predicate
                const isConnected = graphData.links.some(link => 
                    (link.source.id === d.id && link.target.id === labelD.id) ||
                    (link.target.id === d.id && link.source.id === labelD.id)
                );
                
                if (!isConnected) return 0;
                
                // Show connected object labels regardless of their visibility
                return 1;
            });
        } else {
            // For other nodes, just show their own label
            labels.style('opacity', labelD => {
                if (labelD.id !== d.id) return 0;
                return 1; // Show label regardless of node visibility
            });
        }
    })
    .on('mouseout', function() {
        // Hide all labels
        labels.style('opacity', 0);
    })
    .on('click', function(event, d) {
        // Show span details in the card
        showSpanDetails(d);
    });
    
    // Update positions on simulation tick
    simulation.on('tick', () => {
        link
            .attr('x1', d => {
                // Calculate the point where the link meets the source node's edge
                const dx = d.target.x - d.source.x;
                const dy = d.target.y - d.source.y;
                const distance = Math.sqrt(dx * dx + dy * dy);
                const sourceRadius = d.source.isPersonal ? 20 : (d.source.type === 'predicate' ? Math.max(12, Math.min(20, 12 + (d.source.connectionCount || 0) * 0.5)) : 15);
                
                if (distance > 0) {
                    return d.source.x + (dx / distance) * sourceRadius;
                }
                return d.source.x;
            })
            .attr('y1', d => {
                const dx = d.target.x - d.source.x;
                const dy = d.target.y - d.source.y;
                const distance = Math.sqrt(dx * dx + dy * dy);
                const sourceRadius = d.source.isPersonal ? 20 : (d.source.type === 'predicate' ? Math.max(12, Math.min(20, 12 + (d.source.connectionCount || 0) * 0.5)) : 15);
                
                if (distance > 0) {
                    return d.source.y + (dy / distance) * sourceRadius;
                }
                return d.source.y;
            })
            .attr('x2', d => {
                // Calculate the point where the link meets the target node's edge
                const dx = d.target.x - d.source.x;
                const dy = d.target.y - d.source.y;
                const distance = Math.sqrt(dx * dx + dy * dy);
                const targetRadius = d.target.isPersonal ? 20 : (d.target.type === 'predicate' ? Math.max(12, Math.min(20, 12 + (d.target.connectionCount || 0) * 0.5)) : 15);
                
                if (distance > 0) {
                    return d.target.x - (dx / distance) * targetRadius;
                }
                return d.target.x;
            })
            .attr('y2', d => {
                const dx = d.target.x - d.source.x;
                const dy = d.target.y - d.source.y;
                const distance = Math.sqrt(dx * dx + dy * dy);
                const targetRadius = d.target.isPersonal ? 20 : (d.target.type === 'predicate' ? Math.max(12, Math.min(20, 12 + (d.target.connectionCount || 0) * 0.5)) : 15);
                
                if (distance > 0) {
                    return d.target.y - (dy / distance) * targetRadius;
                }
                return d.target.y;
            });
        
        node
            .attr('transform', d => `translate(${d.x},${d.y})`);
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
    
    // Handle window resize
    window.addEventListener('resize', function() {
        const newWidth = container.clientWidth;
        const newHeight = container.clientHeight;
        
        svg.attr('width', newWidth).attr('height', newHeight);
        simulation.force('center', d3.forceCenter(newWidth / 2, newHeight / 2));
        simulation.alpha(0.3).restart();
    });
    
    // Start with personal span details shown
    const personalSpanNode = graphData.nodes.find(n => n.isPersonal);
    if (personalSpanNode) {
        showSpanDetails(personalSpanNode);
    }
    
    // Perspective switching functionality
    let currentPerspective = 'you';
    
    // Function to update graph based on perspective
    function updateGraphPerspective(perspective, groupId = null) {
        currentPerspective = perspective;
        
        // Update node visibility and opacity based on perspective
        node.select('circle').style('opacity', d => {
            const spansData = @json($spansData ?? []);
            
            if (d.type === 'predicate') {
                // For predicate nodes, check the visibility of connected spans
                // A predicate should be translucent if the spans it connects are translucent
                const connectedSpans = getConnectedSpansForPredicate(d.id);
                if (connectedSpans.length === 0) return 1; // No connections, show normally
                
                // Check if any connected spans are fully visible
                const hasVisibleSpans = connectedSpans.some(spanId => {
                    const spanData = spansData[spanId];
                    if (!spanData) return true; // If no data, assume visible
                    const visibility = getNodeVisibility(spanData, perspective, groupId);
                    return visibility === true; // Fully visible
                });
                
                // If no connected spans are fully visible, make predicate translucent
                return hasVisibleSpans ? 1 : 0.2;
            }
            
            // For regular spans, use normal visibility logic
            const spanData = spansData[d.id];
            if (!spanData) return 1;
            
            switch (perspective) {
                case 'you':
                    // You can see everything
                    return 1;
                    
                case 'shared':
                    // Shared access can see shared and public spans
                    if (spanData.permissions && spanData.permissions.includes('Private')) {
                        return 0.2; // Show private spans at 20% opacity
                    }
                    
                    // If a specific group is selected, check if that group has access
                    if (groupId) {
                        // Check if this span is shared with the specific group
                        const groupAccess = checkGroupAccess(spanData, groupId);
                        return groupAccess ? 1 : 0.2; // Show inaccessible spans at 20% opacity
                    }
                    
                    return 1;
                    
                case 'public':
                    // Public/Guest access can see public spans only
                    if (spanData.permissions && spanData.permissions.includes('Public')) {
                        return 1;
                    }
                    return 0.2; // Show private and shared spans at 20% opacity
                    
                default:
                    return 1;
            }
        });
        
        // Update link styling based on perspective
        link.style('opacity', 1) // Always show links at full opacity
        .style('stroke-dasharray', d => {
            const spansData = @json($spansData ?? []);
            const sourceData = spansData[d.source.id];
            const targetData = spansData[d.target.id];
            
            // Check visibility of both ends
            const sourceVisible = sourceData ? getNodeVisibility(sourceData, perspective, groupId) : true;
            const targetVisible = targetData ? getNodeVisibility(targetData, perspective, groupId) : true;
            
            // If both ends are fully visible, show solid line
            if (sourceVisible === true && targetVisible === true) {
                return 'none';
            }
            
            // If either end is hidden, show dashed line
            return '5,5';
        });
        
        // Update labels visibility
        labels.style('opacity', d => {
            const spansData = @json($spansData ?? []);
            const spanData = spansData[d.id];
            if (!spanData) return 0;
            
            const nodeVisible = getNodeVisibility(spanData, perspective, groupId);
            // Keep labels hidden by default, only show on hover
            return 0;
        });
    }
    
    // Helper function to determine node visibility for a perspective
    function getNodeVisibility(spanData, perspective, groupId = null) {
        if (!spanData) return true;
        
        // For predicate nodes, we need to check the visibility of connected spans
        // This is handled separately in updateGraphPerspective for better performance
        if (spanData.type === 'predicate') {
            return true; // Default to visible, actual visibility calculated in updateGraphPerspective
        }
        
        switch (perspective) {
            case 'you':
                return true; // You can see everything
                
            case 'shared':
                // Shared access can see shared and public spans
                if (spanData.permissions.includes('Private')) {
                    return 0.2; // Show private spans at 20% opacity
                }
                
                // If a specific group is selected, check if that group has access
                if (groupId) {
                    const groupAccess = checkGroupAccess(spanData, groupId);
                    return groupAccess ? true : 0.2; // Show inaccessible spans at 20% opacity
                }
                
                return true;
                
            case 'public':
                // Public/Guest access can see public spans only
                return spanData.permissions.includes('Public') ? true : 0.2;
                
            default:
                return true;
        }
    }
    
    // Helper function to get spans connected to a predicate node
    function getConnectedSpansForPredicate(predicateId) {
        const connectedSpans = [];
        
        // Find all links that connect to this predicate
        graphData.links.forEach(link => {
            if (link.source.id === predicateId) {
                // Predicate is source, target is a span
                if (link.target.type !== 'predicate') {
                    connectedSpans.push(link.target.id);
                }
            } else if (link.target.id === predicateId) {
                // Predicate is target, source is a span
                if (link.source.type !== 'predicate') {
                    connectedSpans.push(link.source.id);
                }
            }
        });
        
        // Remove duplicates
        return [...new Set(connectedSpans)];
    }
    
    // Helper function to check if a specific group has access to a span
    function checkGroupAccess(spanData, groupId) {
        // This would need to be implemented based on your actual permission data
        // For now, we'll assume that if a span is shared, the group has access
        // In a real implementation, you'd check the actual group permissions
        
        // Check if the span permissions mention this specific group
        if (spanData.permissions && spanData.permissions.includes('Shared with group')) {
            // You could enhance this by passing group-specific permission data from PHP
            return true;
        }
        
        // For now, assume shared spans are accessible to groups
        return spanData.permissions && !spanData.permissions.includes('Private');
    }
    

    
    // Add event listeners for perspective switching
    document.querySelectorAll('input[name="perspective"]').forEach(radio => {
        radio.addEventListener('change', function() {
            updateGraphPerspective(this.value);
            
            // Show/hide group selector based on perspective
            const groupSelector = document.getElementById('group-selector');
            if (this.value === 'shared') {
                groupSelector.style.display = 'block';
            } else {
                groupSelector.style.display = 'none';
            }
            
            // If switching to shared and no groups are available, show a message
            if (this.value === 'shared' && !document.querySelector('input[name="group-perspective"]')) {
                // No groups available - this is handled by the PHP template
            }
        });
    });
    
    // Add event listeners for group perspective switching
    document.querySelectorAll('input[name="group-perspective"]').forEach(radio => {
        radio.addEventListener('change', function() {
            if (currentPerspective === 'shared') {
                updateGraphPerspective('shared', this.value);
            }
        });
    });
    
    // If no groups are available, handle shared perspective differently
    if (document.querySelectorAll('input[name="group-perspective"]').length === 0) {
        // When switching to shared perspective with no groups, show empty graph
        document.querySelectorAll('input[name="perspective"]').forEach(radio => {
            radio.addEventListener('change', function() {
                if (this.value === 'shared') {
                    // Hide all nodes and links since there are no groups to share with
                    node.select('circle').style('opacity', 0);
                    link.style('opacity', 0);
                    labels.style('opacity', 0);
                }
            });
        });
    }
    
    // Function to show span details in the card
    function showSpanDetails(nodeData) {
        const detailsCard = document.getElementById('span-details-card');
        const detailsContent = document.getElementById('span-details-content');
        
        if (nodeData.type === 'predicate') {
            // Show predicate details with interactive connection cards
            const predicateKey = nodeData.id.replace('pred_', '');
            const connectionsData = @json($connectionsData ?? []);
            const predicateData = connectionsData[predicateKey];
            
            if (predicateData && predicateData.connections && predicateData.connections.length > 0) {
                // Create connection cards HTML
                const connectionCardsHtml = predicateData.connections.map(connectionData => {
                    const direction = connectionData.direction;
                    
                    // Determine the spans based on direction
                    const subject = direction === 'forward' ? connectionData.parent : connectionData.child;
                    const object = direction === 'forward' ? connectionData.child : connectionData.parent;
                    const predicate = direction === 'forward' ? connectionData.type.forward_predicate : connectionData.type.inverse_predicate;
                    
                    // Safety checks
                    if (!subject || !object) {
                        return '<div class="text-muted small">Invalid connection data</div>';
                    }
                    
                    // Create the interactive card HTML
                    return `
                        <div class="interactive-card-base mb-3 position-relative">
                            <div class="btn-group btn-group-sm" role="group">
                                <!-- Connection type icon button -->
                                <a href="/spans/${subject.id}/connections/${predicate.replace(/ /g, '-')}" 
                                   class="btn btn-outline-${connectionData.type_id}" 
                                   style="min-width: 40px;"
                                   title="View connection details">
                                    <i class="bi bi-arrow-left-right"></i>
                                </a>
                                
                                <!-- Subject span name -->
                                <a href="/spans/${subject.id}" 
                                   class="btn btn-${subject.type_id}">
                                    ${subject.name}
                                </a>
                                
                                <!-- Predicate -->
                                <a href="/spans/${subject.id}/connections/${predicate.replace(/ /g, '-')}" 
                                   class="btn btn-${connectionData.type_id}">
                                    ${predicate}
                                </a>
                                
                                <!-- Object span name -->
                                <a href="/spans/${object.id}" 
                                   class="btn btn-${object.type_id}">
                                    ${object.name}
                                </a>
                                
                                ${connectionData.connectionSpan && connectionData.connectionSpan.start_year ? `
                                    <!-- Date information -->
                                    <button type="button" class="btn inactive">
                                        from
                                    </button>
                                    <a href="/date/${connectionData.connectionSpan.start_year}" 
                                       class="btn btn-outline-secondary">
                                        ${connectionData.connectionSpan.start_year}
                                    </a>
                                    ${connectionData.connectionSpan.end_year ? `
                                        <button type="button" class="btn inactive">
                                            to
                                        </button>
                                        <a href="/date/${connectionData.connectionSpan.end_year}" 
                                           class="btn btn-outline-secondary">
                                            ${connectionData.connectionSpan.end_year}
                                        </a>
                                    ` : ''}
                                ` : ''}
                            </div>
                            
                            ${connectionData.connectionSpan && connectionData.connectionSpan.description ? `
                                <div class="mt-2">
                                    <small class="text-muted">${connectionData.connectionSpan.description}</small>
                                </div>
                            ` : ''}
                        </div>
                    `;
                }).join('');
                
                detailsContent.innerHTML = `
                    <h6 class="text-warning mb-3">
                        <i class="bi bi-arrow-left-right me-2"></i>${nodeData.name}
                    </h6>
                    <p class="text-muted small mb-3">Relationship Type</p>
                    <div class="mb-3">
                        <strong>Connections (${predicateData.connections.length}):</strong>
                        <div class="mt-3">
                            ${connectionCardsHtml}
                        </div>
                    </div>
                `;
            } else {
                detailsContent.innerHTML = `
                    <h6 class="text-warning mb-3">
                        <i class="bi bi-arrow-left-right me-2"></i>${nodeData.name}
                    </h6>
                    <p class="text-muted small mb-3">Relationship Type</p>
                    <p class="text-muted">No connection details available</p>
                `;
            }
        } else {
            // Show span details
            const spansData = @json($spansData ?? []);
            const spanData = spansData[nodeData.id];
            
            if (spanData) {
                const timeRange = spanData.start_year || spanData.end_year ? 
                    `${spanData.start_year || '?'} - ${spanData.end_year || '?'}` : 
                    'No dates';
                
                detailsContent.innerHTML = `
                    <h6 class="mb-3">
                        <i class="bi bi-circle-fill me-2" style="color: ${color(nodeData.type)};"></i>
                        ${spanData.name}
                    </h6>
                    <p class="text-muted small mb-3">${spanData.type}</p>
                    ${spanData.description ? `<p class="mb-3">${spanData.description}</p>` : ''}
                    <div class="row mb-3">
                        <div class="col-6">
                            <small class="text-muted">Time Period</small><br>
                            <strong>${timeRange}</strong>
                        </div>
                        <div class="col-6">
                            <small class="text-muted">Created</small><br>
                            <strong>${spanData.created_at}</strong>
                        </div>
                    </div>
                    <div class="mb-3">
                        <small class="text-muted">Visibility</small><br>
                        <strong>${spanData.permissions}</strong>
                    </div>
                    ${spanData.isPersonal ? '<div class="alert alert-info small">This is your personal span</div>' : ''}
                `;
            } else {
                detailsContent.innerHTML = `
                    <h6 class="mb-3">
                        <i class="bi bi-circle-fill me-2" style="color: ${color(nodeData.type)};"></i>
                        ${nodeData.name}
                    </h6>
                    <p class="text-muted small mb-3">${nodeData.type}</p>
                    <p class="text-muted">No additional details available</p>
                `;
            }
        }
        
        detailsCard.style.display = 'block';
    }
});
</script>
@endif
@endpush 