@extends('layouts.app')

@section('page_title')
    <x-breadcrumb :items="[
        [
            'text' => 'Spans',
            'icon' => 'view',
            'icon_category' => 'action',
            'url' => route('spans.index')
        ],
        [
            'text' => $span->name,
            'icon' => 'person',
            'icon_category' => 'span',
            'url' => route('spans.show', $span)
        ],
        [
            'text' => 'Family',
            'icon' => 'people',
            'icon_category' => 'action',
            'url' => route('family.show', $span)
        ],
        [
            'text' => 'Tree',
            'icon' => 'diagram-3',
            'icon_category' => 'action'
        ]
    ]" />
@endsection

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-diagram-3 me-2"></i>
                        Family Tree - {{ $span->name }}
                    </h5>
                </div>
                <div class="card-body">
                    <div id="family-tree-container" style="width: 100%; height: 1000px; overflow: auto;">
                        <svg id="family-tree-svg"></svg>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script src="https://d3js.org/d3.v7.min.js"></script>
<script>
$(function() {
    // Get tree data from backend
    const treeData = @json($treeData);
    
    if (!treeData) {
        $('#family-tree-container').html('<p class="text-muted">No family tree data available.</p>');
        return;
    }
    
    // Set up D3 tree layout with ancestors on left and descendants on right
    const container = $('#family-tree-container');
    const width = container.width();
    const height = 1000;
    const margin = { top: 20, right: 120, bottom: 20, left: 120 };
    const treeWidth = width - margin.right - margin.left;
    const treeHeight = height - margin.top - margin.bottom;
    const rootX = treeWidth / 2; // Root positioned in the middle horizontally
    
    const svg = d3.select('#family-tree-svg')
        .attr('width', width)
        .attr('height', height);
    
    // Clear any existing content
    svg.selectAll('*').remove();
    
    const g = svg.append('g')
        .attr('transform', `translate(${margin.left},${margin.top})`);
    
    // Create tree layout (horizontal, left to right)
    // We'll adjust y positions based on direction after layout
    const tree = d3.tree()
        .size([treeHeight, treeWidth]);
    
    // Convert data to hierarchical format
    const root = d3.hierarchy(treeData);
    tree(root);
    
    // Adjust y positions: ancestors go left, descendants go right
    // In d3.tree horizontal layout: x = vertical position, y = horizontal position
    root.eachBefore(node => {
        if (node.depth === 0) {
            // Root node - position in the middle horizontally
            node.y = rootX;
        } else {
            // Determine direction from root
            let direction = node.data.direction;
            if (!direction && node.parent) {
                // Inherit direction from parent
                direction = node.parent.data.direction;
            }
            
            if (direction === 'left') {
                // Ancestors: position to the left of root
                node.y = rootX - (node.depth * (treeWidth / 6));
            } else if (direction === 'right') {
                // Descendants: position to the right of root
                node.y = rootX + (node.depth * (treeWidth / 6));
            } else if (direction === 'same') {
                // Siblings: position near root
                node.y = rootX;
            }
            // Otherwise keep the tree layout position
        }
    });
    
    // Add links (curved paths)
    const link = g.selectAll('.link')
        .data(root.links())
        .enter().append('path')
        .attr('class', 'link')
        .attr('d', d3.linkHorizontal()
            .x(d => d.y)  // y is horizontal position
            .y(d => d.x)) // x is vertical position
        .style('fill', 'none')
        .style('stroke', '#ccc')
        .style('stroke-width', '1.5px');
    
    // Add nodes
    const node = g.selectAll('.node')
        .data(root.descendants())
        .enter().append('g')
        .attr('class', d => 'node' + (d.data.isSpouse ? ' spouse' : '') + (d.data.isPartner ? ' partner' : ''))
        .attr('transform', d => `translate(${d.y},${d.x})`); // y is horizontal, x is vertical
    
    // Add circles for nodes
    node.append('circle')
        .attr('r', 4.5)
        .style('fill', d => {
            if (d.data.isSpouse || d.data.isPartner) return '#e74c3c'; // Red for spouses/partners
            if (d.data.isRoot || d.depth === 0) return '#6366f1'; // Indigo for root person
            if (d.data.generation > 0 || d.data.direction === 'left') return '#3b82f6'; // Blue for ancestors
            if (d.data.generation < 0 || d.data.direction === 'right') return '#10b981'; // Green for descendants
            return '#555'; // Default gray
        })
        .style('stroke', '#fff')
        .style('stroke-width', '2px');
    
    // Add text labels
    node.append('text')
        .attr('dy', '.35em')
        .attr('x', d => {
            if (d.data.direction === 'left') return -8; // Ancestors: label on left
            if (d.data.direction === 'right') return 8; // Descendants: label on right
            return d.children ? -8 : 8; // Default based on children
        })
        .style('text-anchor', d => {
            if (d.data.direction === 'left') return 'end';
            if (d.data.direction === 'right') return 'start';
            return d.children ? 'end' : 'start';
        })
        .style('font-size', '12px')
        .style('fill', '#333')
        .text(d => d.data.name)
        .style('cursor', 'pointer');
    
    // Add tooltips
    node.append('title')
        .text(d => {
            let text = d.data.name;
            if (d.data.isSpouse) text += ' (Spouse)';
            if (d.data.isPartner) text += ' (Partner)';
            if (d.data.start_year) {
                text += `\nBorn: ${d.data.start_year}`;
            }
            if (d.data.end_year) {
                text += `\nDied: ${d.data.end_year}`;
            }
            if (d.data.generation !== undefined) {
                if (d.data.generation > 0) {
                    text += `\nGeneration: +${d.data.generation} (Ancestor)`;
                } else if (d.data.generation < 0) {
                    text += `\nGeneration: ${d.data.generation} (Descendant)`;
                } else {
                    text += '\nGeneration: 0';
                }
            }
            return text;
        });
    
    // Make nodes clickable
    node.style('cursor', 'pointer')
        .on('click', function(event, d) {
            window.location.href = `/spans/${d.data.id}`;
        });
    
    // Handle window resize
    $(window).on('resize', function() {
        const newWidth = container.width();
        if (newWidth !== width) {
            location.reload();
        }
    });
});
</script>
@endpush
@endsection
