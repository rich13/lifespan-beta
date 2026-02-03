@props(['span', 'interactive' => false, 'columns' => 2, 'familyData' => null])

@php
// Use precomputed family data when provided (e.g. from SpanController) to avoid repeated queries.
// Fall back to View::shared() so re-renders (e.g. Debugbar) in the same request still get it.
$familyData = $familyData ?? \Illuminate\Support\Facades\View::shared('familyData', null);
if ($familyData !== null) {
    $ancestors = $familyData['ancestors'];
    $descendants = $familyData['descendants'];
    $siblings = $familyData['siblings'];
    $unclesAndAunts = $familyData['unclesAndAunts'];
    $cousins = $familyData['cousins'];
    $nephewsAndNieces = $familyData['nephewsAndNieces'];
    $extraNephewsAndNieces = $familyData['extraNephewsAndNieces'];
    $stepParents = $familyData['stepParents'];
    $inLawsAndOutLaws = $familyData['inLawsAndOutLaws'];
    $extraInLawsAndOutLaws = $familyData['extraInLawsAndOutLaws'];
    $childrenInLawsAndOutLaws = $familyData['childrenInLawsAndOutLaws'];
    $grandchildrenInLawsAndOutLaws = $familyData['grandchildrenInLawsAndOutLaws'];
} else {
    $ancestors = $span->ancestors(3);
    $descendants = $span->descendants(3);
    $siblings = $span->siblings();
    $unclesAndAunts = $span->unclesAndAunts();
    $cousins = $span->cousins();
    $nephewsAndNieces = $span->nephewsAndNieces();
    $extraNephewsAndNieces = $span->extraNephewsAndNieces();
    $stepParents = $span->stepParents();
    $inLawsAndOutLaws = $span->inLawsAndOutLaws();
    $extraInLawsAndOutLaws = $span->extraInLawsAndOutLaws();
    $childrenInLawsAndOutLaws = $span->childrenInLawsAndOutLaws();
    $grandchildrenInLawsAndOutLaws = $span->grandchildrenInLawsAndOutLaws();
}

// Compute Bootstrap column class
$colClass = $columns == 3 ? 'col-md-4' : 'col-md-6';

// Precompute photo and parent connections once for all family members (avoids N×2 queries per section)
$photoConnections = collect();
$parentsMap = collect();
$otherParentConnectionsPrecomputed = collect();
if (!$interactive) {
    $childrenForGrouped = $descendants->filter(fn ($item) => $item['generation'] === 1)->pluck('span');
    $childIdsForGrouped = $childrenForGrouped->pluck('id')->all();
    $otherParentConnectionsPrecomputed = !empty($childIdsForGrouped)
        ? \App\Models\Connection::where('type_id', 'family')
            ->whereIn('child_id', $childIdsForGrouped)
            ->where('parent_id', '!=', $span->id)
            ->with('parent')
            ->get()
        : collect();
    $otherParentSpans = $otherParentConnectionsPrecomputed->pluck('parent')->unique('id')->filter();

    $allSpans = $ancestors->pluck('span')
        ->merge($descendants->pluck('span'))
        ->merge($siblings)->merge($unclesAndAunts)->merge($cousins)
        ->merge($nephewsAndNieces)->merge($extraNephewsAndNieces)->merge($stepParents)
        ->merge($inLawsAndOutLaws)->merge($extraInLawsAndOutLaws)
        ->merge($childrenInLawsAndOutLaws)->merge($grandchildrenInLawsAndOutLaws)
        ->merge($otherParentSpans)
        ->filter(fn ($s) => $s && $s->type_id === 'person')->unique('id');
    $personIds = $allSpans->pluck('id')->filter()->unique()->values()->all();
    if (!empty($personIds)) {
        $photoConnections = \App\Models\Connection::where('type_id', 'features')
            ->whereIn('child_id', $personIds)
            ->whereHas('parent', function ($q) {
                $q->where('type_id', 'thing')->whereJsonContains('metadata->subtype', 'photo');
            })
            ->with(['parent'])
            ->get()
            ->groupBy('child_id')
            ->map(fn ($conns) => $conns->first());
        $parentConnectionsForMap = \App\Models\Connection::where('type_id', 'family')
            ->whereIn('child_id', $personIds)
            ->whereHas('parent', function ($q) {
                $q->where('type_id', 'person');
            })
            ->with(['parent'])
            ->get()
            ->groupBy('child_id');
        foreach ($personIds as $personId) {
            $connections = $parentConnectionsForMap->get($personId);
            if ($connections && $connections->isNotEmpty()) {
                $parentSpans = $connections->map(fn ($c) => $c->parent)->filter()->values();
                if ($parentSpans->isNotEmpty()) {
                    $parentsMap->put($personId, $parentSpans);
                }
            }
        }
    }
}

// Check if we have any family relationships to show
$hasFamily = $ancestors->isNotEmpty() || $descendants->isNotEmpty() || 
    $siblings->isNotEmpty() || $unclesAndAunts->isNotEmpty() || 
    $cousins->isNotEmpty() || $nephewsAndNieces->isNotEmpty() || 
    $extraNephewsAndNieces->isNotEmpty() || $stepParents->isNotEmpty() || 
    $inLawsAndOutLaws->isNotEmpty() || $extraInLawsAndOutLaws->isNotEmpty() || $childrenInLawsAndOutLaws->isNotEmpty() || $grandchildrenInLawsAndOutLaws->isNotEmpty();
@endphp

@if($hasFamily)
    <div class="card mb-4" id="family-card">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <h6 class="card-title mb-0">
                    <i class="bi bi-people-fill me-2"></i>
                    <a href="{{ route('family.show', $span) }}" class="text-decoration-none">Family</a>
                </h6>
                <div class="btn-group btn-group-sm" role="group" aria-label="View toggle">
                    <button type="button" class="btn btn-outline-secondary active" id="family-list-toggle">
                        <i class="bi bi-list-ul"></i> List
                    </button>
                    <button type="button" class="btn btn-outline-secondary" id="family-graph-toggle">
                        <i class="bi bi-diagram-3"></i> Graph
                    </button>
                </div>
            </div>
        </div>
        <div class="card-body" style="font-size: 0.875rem;">
            <div id="family-list-view" class="row g-2">
                {{-- Generation +3: Great-Grandparents --}}
                @php $greatGrandparents = $ancestors->filter(function($item) { return $item['generation'] === 3; })->pluck('span'); @endphp
                <x-spans.partials.family-relationship-section 
                    title="Great-Grandparents" 
                    :members="$greatGrandparents"
                    :interactive="$interactive"
                    :colClass="$colClass"
                    :photoConnections="$photoConnections"
                    :parentsMap="$parentsMap" />

                {{-- Generation +2: Grandparents --}}
                @php $grandparents = $ancestors->filter(function($item) { return $item['generation'] === 2; })->pluck('span'); @endphp
                <x-spans.partials.family-relationship-section 
                    title="Grandparents" 
                    :members="$grandparents"
                    :interactive="$interactive"
                    :colClass="$colClass"
                    :photoConnections="$photoConnections"
                    :parentsMap="$parentsMap" />

                {{-- Generation +1: Parents --}}
                @php $parents = $ancestors->filter(function($item) { return $item['generation'] === 1; })->pluck('span'); @endphp
                <x-spans.partials.family-relationship-section 
                    title="Parents" 
                    :members="$parents"
                    :interactive="$interactive"
                    :colClass="$colClass"
                    :photoConnections="$photoConnections"
                    :parentsMap="$parentsMap" />

                {{-- Step-parents --}}
                <x-spans.partials.family-relationship-section 
                    title="Step-parents" 
                    :members="$stepParents"
                    :interactive="$interactive"
                    :colClass="$colClass"
                    :photoConnections="$photoConnections"
                    :parentsMap="$parentsMap" />

                {{-- Uncles & Aunts --}}
                <x-spans.partials.family-relationship-section 
                    title="Uncles & Aunts" 
                    :members="$unclesAndAunts"
                    :interactive="$interactive"
                    :colClass="$colClass"
                    :photoConnections="$photoConnections"
                    :parentsMap="$parentsMap" />

                {{-- Partners & Children (grouped by co-parent) --}}
                @php $children = $descendants->filter(function($item) { return $item['generation'] === 1; })->pluck('span'); @endphp
                <x-spans.partials.family-relationship-children-grouped 
                    :span="$span" 
                    :children="$children"
                    :interactive="$interactive"
                    :colClass="$colClass"
                    :photoConnections="$photoConnections"
                    :parentsMap="$parentsMap"
                    :otherParentConnections="$otherParentConnectionsPrecomputed" />

                {{-- Generation 0: Siblings --}}
                <x-spans.partials.family-relationship-section 
                    title="Siblings" 
                    :members="$siblings"
                    :interactive="$interactive"
                    :colClass="$colClass"
                    :photoConnections="$photoConnections"
                    :parentsMap="$parentsMap" />

                {{-- In-laws & out-laws (people with whom siblings have had children) --}}
                <x-spans.partials.family-relationship-section 
                    title="In-laws & out-laws" 
                    :members="$inLawsAndOutLaws"
                    :interactive="$interactive"
                    :colClass="$colClass"
                    :photoConnections="$photoConnections"
                    :parentsMap="$parentsMap" />

                {{-- Extra In-laws & out-laws (people with whom cousins have had children) --}}
                <x-spans.partials.family-relationship-section 
                    title="Extra In-laws & out-laws" 
                    :members="$extraInLawsAndOutLaws"
                    :interactive="$interactive"
                    :colClass="$colClass"
                    :photoConnections="$photoConnections"
                    :parentsMap="$parentsMap" />

                {{-- Cousins --}}
                <x-spans.partials.family-relationship-section 
                    title="Cousins" 
                    :members="$cousins"
                    :interactive="$interactive"
                    :colClass="$colClass"
                    :photoConnections="$photoConnections"
                    :parentsMap="$parentsMap" />

                {{-- Nephews & Nieces --}}
                <x-spans.partials.family-relationship-section 
                    title="Nephews & Nieces" 
                    :members="$nephewsAndNieces"
                    :interactive="$interactive"
                    :colClass="$colClass"
                    :photoConnections="$photoConnections"
                    :parentsMap="$parentsMap" />

                {{-- Extra Nephews & Nieces --}}
                <x-spans.partials.family-relationship-section 
                    title="Extra Nephews & Nieces" 
                    :members="$extraNephewsAndNieces"
                    :interactive="$interactive"
                    :colClass="$colClass"
                    :photoConnections="$photoConnections"
                    :parentsMap="$parentsMap" />

                {{-- Children-in/out-law (people with whom the user's children have had children) --}}
                <x-spans.partials.family-relationship-section 
                    title="Children-in/out-law" 
                    :members="$childrenInLawsAndOutLaws"
                    :interactive="$interactive"
                    :colClass="$colClass"
                    :photoConnections="$photoConnections"
                    :parentsMap="$parentsMap" />

                {{-- Generation -2: Grandchildren --}}
                @php $grandchildren = $descendants->filter(function($item) { return $item['generation'] === 2; })->pluck('span'); @endphp
                <x-spans.partials.family-relationship-section 
                    title="Grandchildren" 
                    :members="$grandchildren"
                    :interactive="$interactive"
                    :colClass="$colClass"
                    :photoConnections="$photoConnections"
                    :parentsMap="$parentsMap" />

                {{-- Grandchildren-in/out-law (people with whom the user's grandchildren have had children) --}}
                <x-spans.partials.family-relationship-section 
                    title="Grandchildren-in/out-law" 
                    :members="$grandchildrenInLawsAndOutLaws"
                    :interactive="$interactive"
                    :colClass="$colClass"
                    :photoConnections="$photoConnections"
                    :parentsMap="$parentsMap" />

                {{-- Generation -3: Great-Grandchildren --}}
                @php $greatGrandchildren = $descendants->filter(function($item) { return $item['generation'] === 3; })->pluck('span'); @endphp
                <x-spans.partials.family-relationship-section 
                    title="Great-Grandchildren" 
                    :members="$greatGrandchildren"
                    :interactive="$interactive"
                    :colClass="$colClass"
                    :photoConnections="$photoConnections"
                    :parentsMap="$parentsMap" />
            </div>
            
            {{-- Graph View Container (hidden by default) --}}
            <div id="family-graph-view" style="display: none; height: 600px; position: relative;">
                <div class="text-center text-muted" id="family-graph-loading">
                    <div class="spinner-border spinner-border-sm me-2" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    Loading family graph...
                </div>
                <div id="family-graph-container" style="width: 100%; height: 100%;"></div>
                
                {{-- Legend --}}
                <div style="position: absolute; top: 10px; right: 10px; background: white; border: 1px solid #ddd; border-radius: 6px; padding: 12px; font-size: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); z-index: 10;">
                    <div style="font-weight: 600; margin-bottom: 8px;">Generations</div>
                    <div style="display: flex; align-items: center; margin-bottom: 4px;">
                        <div style="width: 12px; height: 12px; border-radius: 50%; background: #93c5fd; margin-right: 6px;"></div>
                        <span>Great-grandparents</span>
                    </div>
                    <div style="display: flex; align-items: center; margin-bottom: 4px;">
                        <div style="width: 12px; height: 12px; border-radius: 50%; background: #60a5fa; margin-right: 6px;"></div>
                        <span>Grandparents</span>
                    </div>
                    <div style="display: flex; align-items: center; margin-bottom: 4px;">
                        <div style="width: 12px; height: 12px; border-radius: 50%; background: #3b82f6; margin-right: 6px;"></div>
                        <span>Parents</span>
                    </div>
                    <div style="display: flex; align-items: center; margin-bottom: 4px;">
                        <div style="width: 14px; height: 14px; border-radius: 50%; background: #6366f1; margin-right: 6px; border: 2px solid #4f46e5;"></div>
                        <span style="font-weight: 600;">You</span>
                    </div>
                    <div style="display: flex; align-items: center; margin-bottom: 4px;">
                        <div style="width: 12px; height: 12px; border-radius: 50%; background: #f59e0b; margin-right: 6px;"></div>
                        <span>Siblings / Spouses</span>
                    </div>
                    <div style="display: flex; align-items: center; margin-bottom: 4px;">
                        <div style="width: 12px; height: 12px; border-radius: 50%; background: #10b981; margin-right: 6px;"></div>
                        <span>Children</span>
                    </div>
                    <div style="display: flex; align-items: center;">
                        <div style="width: 12px; height: 12px; border-radius: 50%; background: #86efac; margin-right: 6px;"></div>
                        <span>Grandchildren</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif

@push('scripts')
<script src="https://d3js.org/d3.v7.min.js"></script>
<script>
$(document).ready(function() {
    // Check if elements exist
    if ($('#family-list-toggle').length === 0 || $('#family-graph-toggle').length === 0) {
        console.log('Family toggle buttons not found');
        return;
    }
    
    console.log('Family graph toggle initialized');
    
    let familyGraphInitialized = false;
    let simulation = null;
    
    // Toggle between list and graph view
    $('#family-list-toggle').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        console.log('List toggle clicked');
        $('#family-list-view').css('display', '');  // Reset to default (flex from row class)
        $('#family-graph-view').css('display', 'none');
        $('#family-list-toggle').addClass('active');
        $('#family-graph-toggle').removeClass('active');
    });
    
    $('#family-graph-toggle').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        console.log('Graph toggle clicked');
        $('#family-list-view').css('display', 'none');
        $('#family-graph-view').css('display', 'block');
        $('#family-list-toggle').removeClass('active');
        $('#family-graph-toggle').addClass('active');
        
        // Initialize graph on first view
        if (!familyGraphInitialized) {
            console.log('Initializing family graph for the first time');
            initializeFamilyGraph();
            familyGraphInitialized = true;
        }
    });
    
    // Initialize the force-directed family graph
    function initializeFamilyGraph() {
        const spanId = '{{ $span->id }}';
        console.log('Fetching family graph for span:', spanId);
        
        // Fetch family graph data
        $.ajax({
            url: `/api/spans/${spanId}/family-graph`,
            method: 'GET',
            success: function(graphData) {
                console.log('Family graph data received:', graphData);
                $('#family-graph-loading').hide();
                renderFamilyGraph(graphData);
            },
            error: function(xhr, status, error) {
                console.error('Error loading family graph:', error, xhr);
                $('#family-graph-loading').html(
                    '<div class="text-danger"><i class="bi bi-exclamation-triangle"></i> Error loading family graph</div>'
                );
            }
        });
    }
    
    // Render the family graph using D3
    function renderFamilyGraph(graphData) {
        const container = document.getElementById('family-graph-container');
        const width = container.clientWidth;
        const height = container.clientHeight;
        
        // Clear any existing content
        container.innerHTML = '';
        
        // Create SVG
        const svg = d3.select('#family-graph-container')
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
            .attr('class', 'graph-tooltip')
            .style('position', 'absolute')
            .style('background', 'rgba(0, 0, 0, 0.8)')
            .style('color', 'white')
            .style('padding', '8px 12px')
            .style('border-radius', '4px')
            .style('font-size', '12px')
            .style('pointer-events', 'none')
            .style('opacity', 0)
            .style('z-index', 10000);
        
        // Color scale for relationship types
        const linkColor = d => {
            if (d.type === 'parent') return '#3b82f6';
            if (d.type === 'child') return '#10b981';
            if (d.type === 'spouse') return '#ef4444';
            return '#999';
        };
        
        // Node color based on generation
        const nodeColor = d => {
            if (d.isCurrent) return '#6366f1'; // Current person - indigo
            
            // Ancestors - blues (lighter for older generations)
            if (d.generation === 3) return '#93c5fd'; // Great-grandparents - light blue
            if (d.generation === 2) return '#60a5fa'; // Grandparents - medium blue
            if (d.generation === 1) return '#3b82f6'; // Parents - darker blue
            
            // Descendants - greens (lighter for younger generations)
            if (d.generation === -2) return '#86efac'; // Grandchildren - light green
            if (d.generation === -1) return '#10b981'; // Children - medium green
            
            return '#f59e0b'; // Same generation (siblings, spouses, cousins) - orange
        };
        
        // Create force simulation
        simulation = d3.forceSimulation(graphData.nodes)
            .force('link', d3.forceLink(graphData.links)
                .id(d => d.id)
                .distance(d => {
                    // Closer spacing for parent-child relationships
                    if (d.type === 'parent' || d.type === 'child') return 100;
                    if (d.type === 'spouse') return 80;
                    return 120;
                }))
            .force('charge', d3.forceManyBody().strength(-400))
            .force('center', d3.forceCenter(width / 2, height / 2))
            .force('collision', d3.forceCollide().radius(35))
            .force('y', d3.forceY(d => {
                // Vertical positioning based on generation
                return height / 2 + (d.generation * 120);
            }).strength(0.3));
        
        // Create links
        const link = g.append('g')
            .selectAll('line')
            .data(graphData.links)
            .enter().append('line')
            .attr('stroke', linkColor)
            .attr('stroke-opacity', 0.6)
            .attr('stroke-width', 2);
        
        // Create nodes
        const node = g.append('g')
            .selectAll('g')
            .data(graphData.nodes)
            .enter().append('g')
            .attr('class', 'node')
            .style('cursor', 'pointer')
            .call(d3.drag()
                .on('start', dragstarted)
                .on('drag', dragged)
                .on('end', dragended))
            .on('click', (event, d) => {
                window.location.href = `/spans/${d.id}`;
            });
        
        // Add circles to nodes
        node.append('circle')
            .attr('r', d => d.isCurrent ? 25 : 20)
            .attr('fill', nodeColor)
            .attr('stroke', '#fff')
            .attr('stroke-width', d => d.isCurrent ? 3 : 2);
        
        // Add labels to nodes
        node.append('text')
            .text(d => d.name)
            .attr('x', 0)
            .attr('y', 35)
            .attr('text-anchor', 'middle')
            .attr('font-size', '11px')
            .attr('fill', '#333')
            .attr('font-weight', d => d.isCurrent ? 'bold' : 'normal');
        
        // Add tooltips
        node.on('mouseenter', function(event, d) {
            let tooltipText = d.name;
            if (d.dates) {
                tooltipText += `<br/>${d.dates}`;
            }
            if (d.relationshipLabel) {
                tooltipText += `<br/><em>${d.relationshipLabel}</em>`;
            }
            
            tooltip.html(tooltipText)
                .style('opacity', 1)
                .style('left', (event.pageX + 10) + 'px')
                .style('top', (event.pageY - 10) + 'px');
        })
        .on('mousemove', function(event) {
            tooltip.style('left', (event.pageX + 10) + 'px')
                .style('top', (event.pageY - 10) + 'px');
        })
        .on('mouseleave', function() {
            tooltip.style('opacity', 0);
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
        
        // Handle window resize
        $(window).on('resize', function() {
            const newWidth = container.clientWidth;
            const newHeight = container.clientHeight;
            
            svg.attr('width', newWidth).attr('height', newHeight);
            simulation.force('center', d3.forceCenter(newWidth / 2, newHeight / 2));
            simulation.alpha(0.3).restart();
        });
    }
    
    // Initialize Bootstrap tooltips for family members with parents
    $('.family-member-link[data-parents]').each(function() {
        const $link = $(this);
        const linkElement = $link[0];
        
        // Get parents data from JSON attribute
        const parentsData = $link.data('parents');
        
        if (parentsData && Array.isArray(parentsData) && parentsData.length > 0 && typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
            try {
                // Build HTML content from parent data
                const parentLinks = parentsData.map(function(parent) {
                    return '<a href="' + parent.url + '" class="text-white text-decoration-underline" style="font-weight: 500;">' + 
                           $('<div>').text(parent.name).html() + // Escape HTML in name
                           '</a>';
                });
                
                const parentsHtml = '<span style="font-weight: 600;">Parents:</span> ' + parentLinks.join(' and ');
                
                // Dispose any existing tooltip first
                const existingTooltip = bootstrap.Tooltip.getInstance(linkElement);
                if (existingTooltip) {
                    existingTooltip.dispose();
                }
                
                // Create new Bootstrap tooltip with HTML content
                const tooltip = new bootstrap.Tooltip(linkElement, {
                    placement: 'top',
                    trigger: 'manual', // Use manual trigger for more control
                    html: true,
                    sanitize: false, // Allow HTML content - critical for HTML rendering
                    title: parentsHtml // Set HTML content directly
                });
                
                let hideTimeout;
                let isOverTooltip = false;
                
                // Show tooltip on mouseenter of link
                $link.on('mouseenter', function() {
                    clearTimeout(hideTimeout);
                    if (!tooltip.tip || !$(tooltip.tip).is(':visible')) {
                        tooltip.show();
                    }
                });
                
                // Hide tooltip on mouseleave of link (with delay)
                $link.on('mouseleave', function() {
                    hideTimeout = setTimeout(function() {
                        if (!isOverTooltip) {
                            tooltip.hide();
                        }
                    }, 150); // Delay to allow mouse to move to tooltip
                });
                
                // When tooltip is shown, handle interactions
                $link.on('shown.bs.tooltip', function() {
                    const tooltipEl = tooltip.tip;
                    if (tooltipEl) {
                        // Mark as over tooltip when mouse enters
                        $(tooltipEl).on('mouseenter', function() {
                            isOverTooltip = true;
                            clearTimeout(hideTimeout);
                        });
                        
                        // Hide tooltip when mouse leaves tooltip
                        $(tooltipEl).on('mouseleave', function() {
                            isOverTooltip = false;
                            tooltip.hide();
                        });
                        
                        // Allow clicks on links inside tooltip - let them navigate normally
                        $(tooltipEl).find('a').on('click', function(e) {
                            e.stopPropagation();
                            // Link will navigate - tooltip will be hidden automatically
                        });
                    }
                });
                
                // Reset flag when tooltip is hidden
                $link.on('hidden.bs.tooltip', function() {
                    isOverTooltip = false;
                    clearTimeout(hideTimeout);
                });
            } catch (e) {
                console.debug('Bootstrap tooltip initialization failed', e);
            }
        }
    });
});
</script>
@endpush 