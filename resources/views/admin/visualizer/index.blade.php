@extends('layouts.app')

@section('page_title')
    Network Visualizer
@endsection

@section('scripts')
    {{-- Sigma.js and Graphology via CDN --}}
    <script src="https://cdnjs.cloudflare.com/ajax/libs/graphology/0.25.4/graphology.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/sigma.js/2.4.0/sigma.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/graphology-library@0.8.0/dist/graphology-library.min.js"></script>
    
    <style>
        #sigma-container {
            width: 100%;
            height: calc(100vh - 60px);
            background: #f8f9fa;
            position: relative;
        }
        .control-panel {
            position: absolute;
            top: 20px;
            right: 20px;
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.15);
            z-index: 1000;
            min-width: 280px;
            max-width: 320px;
            max-height: calc(100vh - 140px);
            overflow-y: auto;
        }
        .control-section {
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        .control-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        .control-section h3 {
            font-size: 14px;
            margin-bottom: 10px;
            font-weight: 600;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .type-checkbox {
            display: flex;
            align-items: center;
            margin-bottom: 6px;
            padding: 4px;
            border-radius: 4px;
            transition: background-color 0.2s;
            cursor: pointer;
        }
        .type-checkbox:hover {
            background-color: #f8f9fa;
        }
        .type-checkbox input {
            margin-right: 8px;
        }
        .type-color {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
            flex-shrink: 0;
        }
        .type-label {
            flex-grow: 1;
            font-size: 13px;
        }
        .type-count {
            color: #666;
            font-size: 0.85em;
        }
        .btn-action {
            width: 100%;
            margin-top: 8px;
        }
        .loading-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255,255,255,0.9);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 2000;
        }
        .loading-overlay.hidden {
            display: none;
        }
        .loading-content {
            text-align: center;
            width: 300px;
        }
        .loading-text {
            margin-bottom: 15px;
            color: #666;
            font-size: 14px;
        }
        .progress-container {
            width: 100%;
            height: 8px;
            background: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
        }
        .progress-bar {
            height: 100%;
            background: #0d6efd;
            border-radius: 4px;
            transition: width 0.1s ease-out;
            width: 0%;
        }
        .progress-details {
            margin-top: 8px;
            font-size: 12px;
            color: #999;
        }
        .stats-bar {
            position: absolute;
            bottom: 20px;
            left: 20px;
            background: white;
            padding: 10px 15px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            z-index: 1000;
            font-size: 13px;
        }
        .search-box {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 13px;
        }
        .search-box:focus {
            outline: none;
            border-color: #0d6efd;
        }
        .search-results {
            max-height: 150px;
            overflow-y: auto;
            margin-top: 5px;
        }
        .search-result-item {
            padding: 6px 8px;
            cursor: pointer;
            border-radius: 4px;
            font-size: 12px;
        }
        .search-result-item:hover {
            background: #f0f0f0;
        }
        .legend-item {
            display: flex;
            align-items: center;
            margin-bottom: 4px;
            font-size: 12px;
        }
        .node-tooltip {
            position: absolute;
            padding: 10px 14px;
            background: white;
            border: 1px solid #ddd;
            border-radius: 6px;
            pointer-events: none;
            font-size: 12px;
            z-index: 3000;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
            max-width: 250px;
        }
        .node-tooltip.hidden {
            display: none;
        }
        .gap-1 {
            gap: 0.25rem;
        }
        .flex-grow-1 {
            flex-grow: 1;
        }
    </style>
@endsection

@section('content')
<div id="sigma-container">
    <div id="loading-overlay" class="loading-overlay">
        <div class="loading-content">
            <div class="loading-text">Initialising visualiser...</div>
            <div class="progress-container">
                <div id="progress-bar" class="progress-bar"></div>
            </div>
            <div id="progress-details" class="progress-details"></div>
        </div>
    </div>
</div>

<div class="control-panel">
    <div class="control-section">
        <h3>Search</h3>
        <input type="text" id="search-input" class="search-box" placeholder="Search nodes...">
        <div id="search-results" class="search-results"></div>
    </div>

    <div class="control-section">
        <h3>
            Node Types
            <span>
                <a href="#" id="select-all-types" class="small">All</a> |
                <a href="#" id="select-none-types" class="small">None</a>
            </span>
        </h3>
        <div id="type-filters"></div>
        <button id="load-btn" class="btn btn-primary btn-sm btn-action">Load Graph</button>
    </div>

    <div class="control-section">
        <h3>Layout</h3>
        <button id="relayout-btn" class="btn btn-outline-secondary btn-sm btn-action">
            <i class="bi bi-arrow-repeat"></i> Re-layout
        </button>
        <div class="mt-2">
            <label class="small text-muted">Gravity:</label>
            <input type="range" id="gravity-slider" class="form-range" min="0.1" max="10" step="0.1" value="1">
        </div>
    </div>

    <div class="control-section">
        <h3>Selection</h3>
        <div id="selection-info" class="small mb-2">Click nodes to select</div>
        <div class="d-flex gap-1">
            <button id="expand-selection-btn" class="btn btn-outline-secondary btn-sm flex-grow-1" disabled>
                <i class="bi bi-arrows-expand"></i> Expand
            </button>
            <button id="clear-selection-btn" class="btn btn-outline-secondary btn-sm flex-grow-1" disabled>
                <i class="bi bi-x-circle"></i> Clear
            </button>
        </div>
        <div class="small text-muted mt-2">
            Click: select/deselect<br>
            Cmd/Ctrl+click: open span
        </div>
    </div>

    <div class="control-section">
        <h3>Display</h3>
        <div class="form-check">
            <input class="form-check-input" type="checkbox" id="show-labels">
            <label class="form-check-label small" for="show-labels">Show labels</label>
        </div>
        <div class="form-check">
            <input class="form-check-input" type="checkbox" id="show-edges" checked>
            <label class="form-check-label small" for="show-edges">Show connections</label>
        </div>
        <div class="form-check">
            <input class="form-check-input" type="checkbox" id="show-orphans">
            <label class="form-check-label small" for="show-orphans">Show orphaned nodes</label>
        </div>
        <div id="orphan-count" class="small text-muted mt-1"></div>
    </div>
</div>

<div class="stats-bar">
    <span id="node-count">0</span> nodes, <span id="edge-count">0</span> connections, <span id="orphan-stat">0</span> orphans
</div>

<div id="node-tooltip" class="node-tooltip hidden"></div>

<script>
$(function() {
    const spanTypes = {!! json_encode($spanTypes) !!};
    const connectionTypes = {!! json_encode($connectionTypes) !!};

    // Colour palette for types
    const typeColours = {};
    const colourPalette = [
        '#e6194b', '#3cb44b', '#ffe119', '#4363d8', '#f58231',
        '#911eb4', '#46f0f0', '#f032e6', '#bcf60c', '#fabebe',
        '#008080', '#e6beff', '#9a6324', '#fffac8', '#800000'
    ];
    spanTypes.forEach((type, i) => {
        typeColours[type.id] = colourPalette[i % colourPalette.length];
    });

    // Create type filter checkboxes
    const $typeFilters = $('#type-filters');
    spanTypes.forEach(type => {
        const $div = $('<label class="type-checkbox"></label>');
        $div.append(`<input type="checkbox" value="${type.id}" checked>`);
        $div.append(`<div class="type-color" style="background-color: ${typeColours[type.id]}"></div>`);
        $div.append(`<span class="type-label">${type.name}</span>`);
        $div.append(`<span class="type-count">(${type.count})</span>`);
        $typeFilters.append($div);
    });

    // Select all/none
    $('#select-all-types').on('click', function(e) {
        e.preventDefault();
        $('#type-filters input').prop('checked', true);
    });
    $('#select-none-types').on('click', function(e) {
        e.preventDefault();
        $('#type-filters input').prop('checked', false);
    });

    // Graph and renderer
    let graph = new graphology.Graph();
    let renderer = null;
    
    // Raw data from server - used to rebuild graph with filters
    let rawNodes = [];
    let rawEdges = [];
    let orphanNodeIds = new Set();
    let connectedNodeIds = new Set();

    function updateStats() {
        const visibleNodes = graph.order;
        const visibleEdges = graph.size;
        const orphanCount = orphanNodeIds.size;
        
        $('#node-count').text(visibleNodes);
        $('#edge-count').text(visibleEdges);
        $('#orphan-stat').text(orphanCount);
        $('#orphan-count').text(`${orphanCount} orphaned nodes`);
    }

    function identifyOrphansFromRawData() {
        // Build set of node IDs that have connections
        connectedNodeIds = new Set();
        rawEdges.forEach(edge => {
            connectedNodeIds.add(edge.source);
            connectedNodeIds.add(edge.target);
        });
        
        // Orphans are nodes not in the connected set
        orphanNodeIds = new Set();
        rawNodes.forEach(node => {
            if (!connectedNodeIds.has(node.id)) {
                orphanNodeIds.add(node.id);
            }
        });
    }

    async function buildGraph() {
        const showOrphans = $('#show-orphans').is(':checked');
        
        // Clear and rebuild graph
        graph.clear();
        
        // Clear any existing selection
        selectedNodes = new Set();
        activeNode = null;
        hoveredNode = null;
        
        // Filter nodes based on orphan toggle
        const nodesToAdd = showOrphans 
            ? rawNodes 
            : rawNodes.filter(node => !orphanNodeIds.has(node.id));
        
        const totalNodes = nodesToAdd.length;
        const totalEdges = rawEdges.length;
        
        // Phase 1: Add nodes (0-30% of progress)
        showLoading('Adding nodes...', 0, `0 / ${totalNodes} nodes`);
        await sleep(10);
        
        for (let i = 0; i < nodesToAdd.length; i++) {
            const node = nodesToAdd[i];
            graph.addNode(node.id, {
                label: node.label,
                spanType: node.type,
                subtype: node.subtype,
                x: Math.random() * 1000,
                y: Math.random() * 1000,
                size: 5,
                color: typeColours[node.type] || '#999'
            });
            
            // Update progress every 500 nodes
            if (i % 500 === 0 || i === nodesToAdd.length - 1) {
                const progress = Math.round((i / totalNodes) * 30);
                updateProgress(progress, `${i + 1} / ${totalNodes} nodes`);
                await sleep(1);
            }
        }

        // Phase 2: Add edges (30-50% of progress)
        showLoading('Adding connections...', 30, `0 / ${totalEdges} connections`);
        await sleep(10);
        
        let edgesAdded = 0;
        for (let i = 0; i < rawEdges.length; i++) {
            const edge = rawEdges[i];
            if (graph.hasNode(edge.source) && graph.hasNode(edge.target)) {
                try {
                    graph.addEdge(edge.source, edge.target, {
                        connectionType: edge.type,
                        size: 1,
                        color: '#ccc'
                    });
                    edgesAdded++;
                } catch (e) {
                    // Skip duplicate edges
                }
            }
            
            // Update progress every 500 edges
            if (i % 500 === 0 || i === rawEdges.length - 1) {
                const progress = 30 + Math.round((i / totalEdges) * 20);
                updateProgress(progress, `${edgesAdded} connections added`);
                await sleep(1);
            }
        }

        // Phase 3: Calculate sizes (50-55% of progress)
        showLoading('Calculating sizes...', 50);
        await sleep(10);
        
        graph.forEachNode((node) => {
            const degree = graph.degree(node);
            graph.setNodeAttribute(node, 'size', 3 + Math.sqrt(degree) * 2);
        });
        updateProgress(55);

        // Phase 4: Run layout (55-95% of progress)
        // ForceAtlas2 converges quickly - 50-100 iterations is usually enough
        const iterations = Math.min(100, Math.max(30, Math.round(graph.order / 20)));
        await runLayoutWithProgress(iterations);

        // Phase 5: Render (95-100%)
        showLoading('Rendering...', 95);
        await sleep(10);
        
        updateStats();
        initRenderer();
        
        updateProgress(100, 'Complete');
    }
    
    function sleep(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    function showLoading(message, progress = 0, details = '') {
        $('#loading-overlay').removeClass('hidden');
        $('#loading-overlay .loading-text').text(message || 'Loading...');
        $('#progress-bar').css('width', progress + '%');
        $('#progress-details').text(details);
    }

    function updateProgress(progress, details = '') {
        $('#progress-bar').css('width', progress + '%');
        $('#progress-details').text(details);
    }

    function hideLoading() {
        $('#loading-overlay').addClass('hidden');
        $('#progress-bar').css('width', '0%');
        $('#progress-details').text('');
    }

    // Selection state - persists across interactions
    let selectedNodes = new Set();
    let activeNode = null;  // The most recently selected node (shows its neighbours as options)
    let hoveredNode = null;

    function updateSelectionUI() {
        const count = selectedNodes.size;
        if (count > 0) {
            $('#selection-info').html(`<strong>${count}</strong> node${count !== 1 ? 's' : ''} selected`);
            $('#clear-selection-btn').prop('disabled', false);
            $('#expand-selection-btn').prop('disabled', false);
        } else {
            $('#selection-info').html('Click nodes to select');
            $('#clear-selection-btn').prop('disabled', true);
            $('#expand-selection-btn').prop('disabled', true);
        }
    }

    function clearSelection() {
        selectedNodes = new Set();
        activeNode = null;
        updateSelectionUI();
        applyHighlighting();
    }

    function expandSelection() {
        // Add all neighbors of the active node (or all selected if no active)
        if (activeNode && graph.hasNode(activeNode)) {
            graph.neighbors(activeNode).forEach(neighbor => {
                selectedNodes.add(neighbor);
            });
        } else {
            // Fallback: expand from all selected nodes
            const newNodes = new Set(selectedNodes);
            selectedNodes.forEach(nodeId => {
                graph.neighbors(nodeId).forEach(neighbor => {
                    newNodes.add(neighbor);
                });
            });
            selectedNodes = newNodes;
        }
        // Keep active node the same so user can see what was just expanded
        updateSelectionUI();
        applyHighlighting();
    }

    function applyHighlighting() {
        if (!renderer) return;

        if (selectedNodes.size === 0 && !hoveredNode) {
            // No selection and no hover - show everything normally
            renderer.setSetting('nodeReducer', null);
            renderer.setSetting('edgeReducer', null);
            return;
        }

        // Build sets for different highlight levels
        let optionNodes = new Set();  // Neighbours of active node only (clickable options)
        let edgesBetweenSelected = new Set();
        let edgesToOptions = new Set();

        // Only the active node shows its unselected neighbours as options
        if (activeNode && graph.hasNode(activeNode)) {
            graph.neighbors(activeNode).forEach(neighbor => {
                if (!selectedNodes.has(neighbor)) {
                    optionNodes.add(neighbor);
                }
            });
            // Edges from active node to options
            graph.edges(activeNode).forEach(edgeId => {
                const source = graph.source(edgeId);
                const target = graph.target(edgeId);
                const other = source === activeNode ? target : source;
                if (optionNodes.has(other)) {
                    edgesToOptions.add(edgeId);
                }
            });
        }

        // Edges between all selected nodes (the subgraph)
        selectedNodes.forEach(nodeId => {
            graph.edges(nodeId).forEach(edgeId => {
                const source = graph.source(edgeId);
                const target = graph.target(edgeId);
                if (selectedNodes.has(source) && selectedNodes.has(target)) {
                    edgesBetweenSelected.add(edgeId);
                }
            });
        });

        // If hovering over an option node, highlight it
        let hoverHighlight = null;
        if (hoveredNode && optionNodes.has(hoveredNode)) {
            hoverHighlight = hoveredNode;
        }

        renderer.setSetting('nodeReducer', (node, attrs) => {
            // Selected nodes - full colour with border
            if (selectedNodes.has(node)) {
                // Active node gets a thicker/different border
                if (node === activeNode) {
                    return { 
                        ...attrs, 
                        zIndex: 3,
                        borderColor: '#0d6efd',
                        borderSize: 3
                    };
                }
                return { 
                    ...attrs, 
                    zIndex: 2,
                    borderColor: '#000',
                    borderSize: 2
                };
            }
            // Option nodes (neighbours of active) - visible as clickable choices
            if (optionNodes.has(node)) {
                return { 
                    ...attrs, 
                    zIndex: 1,
                    // Hovered option is brighter
                    color: node === hoverHighlight ? attrs.color : attrs.color
                };
            }
            // Other nodes - fully dimmed
            return { ...attrs, color: '#e0e0e0', zIndex: 0, label: null };
        });

        renderer.setSetting('edgeReducer', (edge, attrs) => {
            // Edges between selected nodes (the subgraph)
            if (edgesBetweenSelected.has(edge)) {
                return { ...attrs, color: '#333', size: 2.5 };
            }
            // Edges from active node to options
            if (edgesToOptions.has(edge)) {
                return { ...attrs, color: '#999', size: 1.5 };
            }
            // All other edges dimmed
            return { ...attrs, color: '#f0f0f0', size: 0.5 };
        });
    }

    function initRenderer() {
        if (renderer) {
            renderer.kill();
        }

        const container = document.getElementById('sigma-container');
        
        renderer = new Sigma(graph, container, {
            renderLabels: $('#show-labels').is(':checked'),
            renderEdgeLabels: false,
            labelSize: 12,
            labelWeight: 'bold',
            labelColor: { color: '#333' },
            defaultNodeColor: '#999',
            defaultEdgeColor: '#ccc',
            minCameraRatio: 0.01,
            maxCameraRatio: 10,
        });

        // Hover behaviour - show tooltip
        renderer.on('enterNode', ({ node }) => {
            const attrs = graph.getNodeAttributes(node);
            const degree = graph.degree(node);
            
            const $tooltip = $('#node-tooltip');
            $tooltip.html(`
                <strong>${attrs.label}</strong><br>
                Type: ${attrs.spanType}${attrs.subtype ? ` (${attrs.subtype})` : ''}<br>
                Connections: ${degree}<br>
                <small class="text-muted">Click to select, Cmd/Ctrl+click to open</small>
            `);
            $tooltip.removeClass('hidden');

            // Set hover state and update highlighting
            hoveredNode = node;
            applyHighlighting();
        });

        renderer.on('leaveNode', () => {
            $('#node-tooltip').addClass('hidden');
            hoveredNode = null;
            applyHighlighting();
        });

        // Track mouse for tooltip positioning
        renderer.getMouseCaptor().on('mousemove', (e) => {
            const $tooltip = $('#node-tooltip');
            if (!$tooltip.hasClass('hidden')) {
                $tooltip.css({
                    left: e.original.clientX + 15,
                    top: e.original.clientY + 15
                });
            }
        });

        // Click to select/deselect, Cmd/Ctrl+click to open
        renderer.on('clickNode', ({ node, event }) => {
            if (event.original.metaKey || event.original.ctrlKey) {
                // Cmd/Ctrl+click opens the span page
                window.open(`/s/${node}`, '_blank');
            } else {
                // Regular click - add to selection and make active
                if (selectedNodes.has(node)) {
                    // Clicking an already-selected node makes it the active one
                    // (so you can see its connections again)
                    activeNode = node;
                } else {
                    // Add new node to selection and make it active
                    selectedNodes.add(node);
                    activeNode = node;
                }
                updateSelectionUI();
                applyHighlighting();
            }
        });

        // Click on background clears selection
        renderer.on('clickStage', () => {
            if (selectedNodes.size > 0) {
                clearSelection();
            }
        });

        // Apply initial highlighting state
        applyHighlighting();
        updateSelectionUI();
    }

    async function loadGraph() {
        const selectedTypes = [];
        $('#type-filters input:checked').each(function() {
            selectedTypes.push($(this).val());
        });

        if (selectedTypes.length === 0) {
            alert('Please select at least one type to load.');
            return;
        }

        showLoading('Loading graph data...');

        try {
            const response = await $.ajax({
                url: '{{ route("admin.visualizer.graph-data") }}',
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                data: JSON.stringify({ types: selectedTypes }),
                contentType: 'application/json'
            });

            showLoading('Building graph...');

            // Store raw data for filtering
            rawNodes = response.nodes;
            rawEdges = response.edges;
            
            // Identify orphans from raw data
            identifyOrphansFromRawData();
            
            // Build graph with current filter settings (includes layout)
            await buildGraph();
            
            hideLoading();

        } catch (error) {
            console.error('Error loading graph:', error);
            alert('Error loading graph data. Please try again.');
            hideLoading();
        }
    }

    async function runLayoutWithProgress(totalIterations = 100) {
        if (graph.order === 0) return;

        const gravity = parseFloat($('#gravity-slider').val());
        
        // Use ForceAtlas2 settings - tuned for quick convergence
        const settings = graphologyLibrary.layoutForceAtlas2.inferSettings(graph);
        settings.gravity = gravity;
        settings.scalingRatio = 5;
        settings.barnesHutOptimize = graph.order > 500;
        settings.barnesHutTheta = 0.5;
        settings.slowDown = 1 + graph.order / 1000;
        settings.strongGravityMode = true;

        // Run layout in chunks to allow progress updates
        const chunkSize = 20;
        let completed = 0;
        
        showLoading('Running layout...', 55, `0 / ${totalIterations} iterations`);
        
        while (completed < totalIterations) {
            const iterationsThisChunk = Math.min(chunkSize, totalIterations - completed);
            
            graphologyLibrary.layoutForceAtlas2.assign(graph, {
                iterations: iterationsThisChunk,
                settings: settings
            });
            
            completed += iterationsThisChunk;
            
            // Progress from 55% to 95% during layout
            const progress = 55 + Math.round((completed / totalIterations) * 40);
            updateProgress(progress, `${completed} / ${totalIterations} iterations`);
            
            // Yield to UI
            await sleep(1);
        }
        
        console.log(`Layout completed: ${totalIterations} iterations on ${graph.order} nodes`);
    }
    
    function runLayoutSync(iterations = 100) {
        if (graph.order === 0) return;

        const gravity = parseFloat($('#gravity-slider').val());
        
        const settings = graphologyLibrary.layoutForceAtlas2.inferSettings(graph);
        settings.gravity = gravity;
        settings.scalingRatio = 5;
        settings.barnesHutOptimize = graph.order > 500;
        settings.barnesHutTheta = 0.5;
        settings.slowDown = 1 + graph.order / 1000;
        settings.strongGravityMode = true;

        graphologyLibrary.layoutForceAtlas2.assign(graph, {
            iterations: iterations,
            settings: settings
        });
    }

    // Event handlers
    $('#load-btn').on('click', loadGraph);
    
    $('#relayout-btn').on('click', async function() {
        if (graph.order > 0) {
            // Randomise positions first for a fresh layout
            graph.forEachNode((node) => {
                graph.setNodeAttribute(node, 'x', Math.random() * 1000);
                graph.setNodeAttribute(node, 'y', Math.random() * 1000);
            });
            await runLayoutWithProgress(100);
            renderer.refresh();
            hideLoading();
        }
    });

    $('#clear-selection-btn').on('click', clearSelection);
    $('#expand-selection-btn').on('click', expandSelection);

    $('#show-labels').on('change', function() {
        if (renderer) {
            renderer.setSetting('renderLabels', $(this).is(':checked'));
        }
    });

    $('#show-edges').on('change', function() {
        if (renderer) {
            renderer.setSetting('renderEdges', $(this).is(':checked'));
        }
    });

    $('#show-orphans').on('change', async function() {
        if (rawNodes.length > 0) {
            await buildGraph();
            hideLoading();
        }
    });

    // Search functionality
    let searchTimeout;
    $('#search-input').on('input', function() {
        clearTimeout(searchTimeout);
        const query = $(this).val().toLowerCase().trim();
        
        if (query.length < 2) {
            $('#search-results').empty();
            return;
        }

        searchTimeout = setTimeout(() => {
            const results = [];
            graph.forEachNode((node, attrs) => {
                if (attrs.label.toLowerCase().includes(query)) {
                    results.push({ id: node, label: attrs.label, spanType: attrs.spanType });
                }
                if (results.length >= 20) return false;
            });

            const $results = $('#search-results');
            $results.empty();

            results.forEach(result => {
                const $item = $(`<div class="search-result-item">
                    <span class="type-color" style="display:inline-block; width:8px; height:8px; border-radius:50%; background:${typeColours[result.spanType]}; margin-right:5px;"></span>
                    ${result.label}
                </div>`);
                $item.on('click', () => {
                    if (renderer) {
                        const camera = renderer.getCamera();
                        const nodePos = graph.getNodeAttributes(result.id);
                        camera.animate({ x: nodePos.x, y: nodePos.y, ratio: 0.1 }, { duration: 500 });
                    }
                    $('#search-results').empty();
                    $('#search-input').val('');
                });
                $results.append($item);
            });
        }, 200);
    });

    // Initial state
    hideLoading();
    updateStats();
});
</script>
@endsection
