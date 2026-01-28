@props([
    'subject',
    'connections',                // Flat collection or paginator of Connection models
    'relevantConnectionTypes',    // Collection of ConnectionType models
    'connectionCounts' => [],     // [type => count]
    'connectionTypeDirections' => [], // [type => ['predicate' => string, ...]]
    'containerId',                // Unique container ID for the timeline
    'initialConnectionType' => 'all', // 'all' or a specific connection type ID
])

@php
    // Normalise connections into a collection
    $connectionsCollection = $connections instanceof \Illuminate\Pagination\LengthAwarePaginator
        ? collect($connections->items())
        : ($connections instanceof \Illuminate\Support\Collection ? $connections : collect($connections));

    // Prepare timeline data
    $timelineData = [];
    $swimlaneHeight = 20;
    $swimlaneSpacing = 8;
    $marginTop = 10;
    $overallSwimlaneY = $marginTop + 10;
    $swimlaneIndex = 0;
    
    // Add life swimlane
    $timelineData[] = [
        'type' => 'life',
        'label' => 'Life',
        'y' => $overallSwimlaneY + ($swimlaneIndex * ($swimlaneHeight + $swimlaneSpacing))
    ];
    $swimlaneIndex++;
    
    // Add individual connection swimlanes (single chronological order)
    foreach ($connectionsCollection as $connection) {
        $timelineData[] = [
            'type' => 'connection',
            'connectionType' => $connection->connection_type_id ?? $connection->connection_type->type ?? 'connection',
            'connection' => $connection,
            'label' => $connection->other_span->name ?? '',
            'y' => $overallSwimlaneY + ($swimlaneIndex * ($swimlaneHeight + $swimlaneSpacing))
        ];
        $swimlaneIndex++;
    }
    
    // Calculate time range
    $minYear = $subject->start_year ?? 1900;
    $maxYear = $subject->end_year ?? date('Y');
    
    foreach ($connectionsCollection as $connection) {
        if ($connection->connectionSpan && $connection->connectionSpan->start_year) {
            $minYear = min($minYear, $connection->connectionSpan->start_year);
            $maxYear = max($maxYear, $connection->connectionSpan->end_year ?? date('Y'));
        }
    }
    
    $timeRange = ['start' => $minYear, 'end' => $maxYear];
    
    // Filter to only connection types that have connections
    $typesWithConnections = $relevantConnectionTypes->filter(function($type) use ($connectionCounts) {
        $hasConnections = $connectionCounts[$type->type] ?? 0;
        return $hasConnections > 0;
    });
@endphp

<div class="card connections-timeline-card"
     data-timeline-container-id="{{ $containerId }}"
     data-initial-connection-type="{{ $initialConnectionType }}">
    <div class="card-header">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-2">
            <h5 class="card-title mb-0">
                <i class="bi bi-clock-history me-2"></i>
                Lifespan
            </h5>
            <div class="d-flex flex-wrap align-items-center gap-2">
                @if($typesWithConnections->count() > 0)
                    <div class="btn-group btn-group-sm connection-type-filter-buttons" role="group" aria-label="Connection types">
                        <a href="{{ route('spans.all-connections', $subject) }}" 
                           class="btn btn-sm btn-primary"
                           data-connection-type="all">
                            All Connections
                        </a>
                        @foreach($typesWithConnections as $type)
                            @php
                                // Use the appropriate predicate based on connection direction when available
                                $directionInfo = $connectionTypeDirections[$type->type] ?? null;
                                $predicate = ($directionInfo && isset($directionInfo['predicate'])) 
                                    ? $directionInfo['predicate'] 
                                    : $type->forward_predicate;
                                $routePredicate = str_replace(' ', '-', $predicate);
                                $allConnectionsUrl = route('spans.all-connections', $subject);
                            @endphp
                            <a href="{{ $allConnectionsUrl }}#{{ $routePredicate }}" 
                               class="btn btn-sm btn-secondary"
                               data-connection-type="{{ $type->type }}"
                               data-predicate="{{ $routePredicate }}"
                               style="background-color: var(--connection-{{ $type->type }}-color, #007bff); border-color: var(--connection-{{ $type->type }}-color, #007bff); color: white;">
                                {{ ucfirst($predicate) }}
                            </a>
                        @endforeach
                    </div>
                @endif
                <div class="btn-group btn-group-sm" role="group" aria-label="Timeline layout">
                    <input type="radio" class="btn-check" name="all-connections-layout" id="all-connections-layout-expanded" value="expanded" checked>
                    <label class="btn btn-outline-secondary" for="all-connections-layout-expanded" title="Expanded">
                        <i class="bi bi-arrows-expand"></i>
                    </label>
                    <input type="radio" class="btn-check" name="all-connections-layout" id="all-connections-layout-collapsed" value="collapsed">
                    <label class="btn btn-outline-secondary" for="all-connections-layout-collapsed" title="Collapsed">
                        <i class="bi bi-arrows-collapse"></i>
                    </label>
                </div>
                <select class="form-select form-select-sm state-filter" style="width: auto; display: inline-block;" title="Filter by connection state">
                    <option value="all">All States</option>
                    <option value="placeholder">Placeholders Only</option>
                    <option value="draft">Drafts Only</option>
                    <option value="complete">Complete Only</option>
                </select>
                @auth
                    @if(auth()->user()->can('update', $subject))
                        <button type="button" class="btn btn-sm btn-outline-primary" 
                                data-bs-toggle="modal" data-bs-target="#addConnectionModal"
                                data-span-id="{{ $subject->id }}" data-span-name="{{ $subject->name }}" data-span-type="{{ $subject->type_id }}">
                            <i class="bi bi-plus-lg"></i>
                        </button>
                    @endif
                @endauth
            </div>
        </div>
    </div>
    <x-spans.shared-timeline
        :subject="$subject"
        :timelineData="$timelineData"
        :containerId="$containerId"
        :subjectStartYear="$subject->start_year"
        :subjectEndYear="$subject->end_year"
        :timeRange="$timeRange"
        :connectionType="$initialConnectionType"
    />
</div>

@push('styles')
<style>
    .connection-type-filter-buttons .btn {
        opacity: 0.45;
        transition: opacity 150ms ease-in-out;
    }

    .connection-type-filter-buttons .btn.active {
        opacity: 1;
    }
</style>
@endpush

@push('scripts')
<script>
    // jQuery-based filtering of the shared timeline, reusable for connections pages
    (function($) {
        $(function() {
            $('.connections-timeline-card').each(function() {
                const $root = $(this);
                const containerId = $root.data('timeline-container-id');
                const initialType = $root.data('initial-connection-type') || 'all';

                const $buttons = $root.find('.connection-type-filter-buttons a');
                const $timelineContainer = $('#' + containerId);
                const $layoutRadios = $root.find('input[name="all-connections-layout"]');
                let layoutMode = 'expanded'; // 'expanded' | 'collapsed'
                const $allButton = $buttons.filter('[data-connection-type="all"]');
                const $typeButtons = $buttons.not($allButton);
                const allTypes = new Set(
                    $typeButtons
                        .map(function() { return $(this).data('connection-type'); })
                        .get()
                );
                let activeTypes;

                // State filter
                let stateFilter = 'all';
                const $stateFilter = $root.find('.state-filter');

                if (!$timelineContainer.length) {
                    return;
                }

                // Build a map from predicate (hash) to connection type ID
                const predicateToTypeMap = {};
                $typeButtons.each(function() {
                    const $btn = $(this);
                    const type = $btn.data('connection-type');
                    const predicate = $btn.data('predicate');
                    if (predicate && type) {
                        predicateToTypeMap[predicate] = type;
                    }
                });

                // Check URL hash to determine initial filter state
                let hashType = null;
                const hash = window.location.hash.substring(1); // Remove the #
                if (hash && predicateToTypeMap[hash]) {
                    hashType = predicateToTypeMap[hash];
                }

                // Initialise activeTypes based on hash (if present) or initialType
                if (hashType && allTypes.has(hashType)) {
                    activeTypes = new Set([hashType]);
                } else if (initialType && initialType !== 'all' && allTypes.has(initialType)) {
                    activeTypes = new Set([initialType]);
                } else {
                    activeTypes = new Set(allTypes);
                }

                function updateButtonStates() {
                    $typeButtons.each(function() {
                        const $btn = $(this);
                        const type = $btn.data('connection-type');
                        if (activeTypes.has(type)) {
                            $btn.addClass('active');
                        } else {
                            $btn.removeClass('active');
                        }
                    });

                    if (activeTypes.size === allTypes.size) {
                        $allButton.addClass('active');
                    } else {
                        $allButton.removeClass('active');
                    }
                }

                updateButtonStates();

                // Function to update URL hash based on active types
                function updateHash() {
                    if (activeTypes.size === allTypes.size) {
                        // All types active - remove hash
                        if (window.location.hash) {
                            history.replaceState(null, '', window.location.pathname + window.location.search);
                        }
                    } else if (activeTypes.size === 1) {
                        // Single type active - set hash to predicate
                        const activeType = Array.from(activeTypes)[0];
                        const $activeButton = $typeButtons.filter('[data-connection-type="' + activeType + '"]');
                        const predicate = $activeButton.data('predicate');
                        if (predicate) {
                            const newHash = '#' + predicate;
                            if (window.location.hash !== newHash) {
                                history.replaceState(null, '', window.location.pathname + window.location.search + newHash);
                            }
                        }
                    } else {
                        // Multiple types active - remove hash (can't represent multiple in hash)
                        if (window.location.hash) {
                            history.replaceState(null, '', window.location.pathname + window.location.search);
                        }
                    }
                }

                function filterTimelineByTypes(layout) {
                    const $rows = $timelineContainer.find('.timeline-row');
                    const $bars = $timelineContainer.find('.timeline-bar');
                    const swimlaneHeight = 20;
                    const swimlaneSpacing = 8;
                    const marginTop = 10;
                    const marginBottom = 100;
                    const axisOffsetFromRows = 20;
                    const overallSwimlaneY = marginTop + 10;

                    const $svg = $timelineContainer.find('svg');

                    function animateRowToY($row, targetY) {
                        const currentTransform = $row.attr('transform') || 'translate(0,0)';
                        const match = currentTransform.match(/translate\(0, *([0-9.]+)\)/);
                        const currentY = match ? parseFloat(match[1]) : overallSwimlaneY;

                        $({ y: currentY }).stop(true).animate(
                            { y: targetY },
                            {
                                duration: 250,
                                easing: 'swing',
                                step: function(now) {
                                    $row.attr('transform', 'translate(0,' + now + ')');
                                }
                            }
                        );
                    }

                    const showAllTypes = activeTypes.size === allTypes.size;

                    if (showAllTypes) {
                        $rows.each(function() {
                            const $row = $(this);
                            const rowType = $row.data('connection-type');
                            const rowState = $row.attr('data-connection-state');
                            
                            let stateMatches = true;
                            if (rowType !== 'life') {
                                if (stateFilter === 'all') {
                                    stateMatches = true;
                                } else {
                                    stateMatches = rowState === stateFilter;
                                }
                            }
                            
                            if (stateMatches) {
                                $row.removeClass('timeline-row--hidden');
                            } else {
                                $row.addClass('timeline-row--hidden');
                            }
                        });
                        
                        $bars.each(function() {
                            const $bar = $(this);
                            if ($bar.data('connection-type') !== 'life') {
                                $bar.css('opacity', 0.7);
                            }
                        });
                        
                        if (layout === 'collapsed') {
                            const $lifeRow = $rows.filter('[data-connection-type="life"]').first();
                            const lifeTransform = $lifeRow.attr('transform') || 'translate(0,0)';
                            const lifeY = parseFloat(lifeTransform.match(/translate\(0, *([0-9.]+)\)/)?.[1] || 0);

                            $rows.each(function() {
                                const $row = $(this);
                                const rowType = $row.data('connection-type');
                                animateRowToY($row, lifeY);
                                if (rowType === 'life') {
                                    $row.find('.timeline-bg').css('opacity', 1);
                                } else {
                                    $row.find('.timeline-bg').css('opacity', 0);
                                }
                            });

                            const newHeightCollapsed = marginTop + swimlaneHeight + marginBottom;
                            $timelineContainer.height(newHeightCollapsed);
                            $svg.attr('height', newHeightCollapsed);
                            const currentViewBox = $svg.attr('viewBox') || '0 0 0 0';
                            const viewBoxParts = currentViewBox.split(' ');
                            const viewBoxWidth = viewBoxParts[2] || $timelineContainer.width();
                            $svg.attr('viewBox', `0 0 ${viewBoxWidth} ${newHeightCollapsed}`);

                            const axisYCollapsed = marginTop + swimlaneHeight + axisOffsetFromRows;
                            $svg.find('.timeline-axis').attr('transform', 'translate(0,' + axisYCollapsed + ')');
                            $svg.find('.now-line').attr('y2', axisYCollapsed);
                        } else {
                            $rows.each(function() {
                                const $row = $(this);
                                if ($row.hasClass('timeline-row--hidden')) {
                                    $row.find('.timeline-bg').css('opacity', 0);
                                } else {
                                    $row.find('.timeline-bg').css('opacity', 1);
                                }
                            });

                            let visibleIndexAll = 0;
                            $rows.each(function() {
                                const $row = $(this);
                                if ($row.hasClass('timeline-row--hidden')) {
                                    return;
                                }
                                const baseY = overallSwimlaneY + (visibleIndexAll * (swimlaneHeight + swimlaneSpacing));
                                visibleIndexAll++;
                                animateRowToY($row, baseY);
                            });

                            const totalVisibleAll = visibleIndexAll;
                            if (totalVisibleAll > 0) {
                                const newHeightAll = marginTop + (totalVisibleAll * swimlaneHeight) + ((totalVisibleAll - 1) * swimlaneSpacing) + marginBottom;
                                $timelineContainer.height(newHeightAll);
                                $svg.attr('height', newHeightAll);
                                const currentViewBox = $svg.attr('viewBox') || '0 0 0 0';
                                const viewBoxParts = currentViewBox.split(' ');
                                const viewBoxWidth = viewBoxParts[2] || $timelineContainer.width();
                                $svg.attr('viewBox', `0 0 ${viewBoxWidth} ${newHeightAll}`);

                                const axisYAll = marginTop + (totalVisibleAll * swimlaneHeight) + ((totalVisibleAll - 1) * swimlaneSpacing) + axisOffsetFromRows;
                                $svg.find('.timeline-axis').attr('transform', 'translate(0,' + axisYAll + ')');
                                $svg.find('.now-line').attr('y2', axisYAll);
                            }
                        }

                        return;
                    }

                    $rows.each(function() {
                        const $row = $(this);
                        const rowType = $row.data('connection-type');
                        const rowState = $row.attr('data-connection-state');

                        const typeMatches = rowType === 'life' || activeTypes.has(rowType);
                        
                        let stateMatches = true;
                        if (rowType !== 'life') {
                            if (stateFilter === 'all') {
                                stateMatches = true;
                            } else {
                                stateMatches = rowState === stateFilter;
                            }
                        }

                        if (typeMatches && stateMatches) {
                            $row.removeClass('timeline-row--hidden');
                        } else {
                            $row.addClass('timeline-row--hidden');
                        }
                    });

                    if (layout === 'collapsed') {
                        const $lifeRow = $rows.filter('[data-connection-type="life"]').first();
                        const lifeTransform = $lifeRow.attr('transform') || 'translate(0,0)';
                        const lifeY = parseFloat(lifeTransform.match(/translate\(0, *([0-9.]+)\)/)?.[1] || 0);

                        $rows.each(function() {
                            const $row = $(this);
                            if ($row.hasClass('timeline-row--hidden')) {
                                return;
                            }
                            const rowType = $row.data('connection-type');
                            animateRowToY($row, lifeY);
                            if (rowType === 'life') {
                                $row.find('.timeline-bg').css('opacity', 1);
                            } else {
                                $row.find('.timeline-bg').css('opacity', 0);
                            }
                        });

                        const newHeightCollapsed = marginTop + swimlaneHeight + marginBottom;
                        $timelineContainer.height(newHeightCollapsed);
                        $svg.attr('height', newHeightCollapsed);
                        const currentViewBox = $svg.attr('viewBox') || '0 0 0 0';
                        const viewBoxParts = currentViewBox.split(' ');
                        const viewBoxWidth = viewBoxParts[2] || $timelineContainer.width();
                        $svg.attr('viewBox', `0 0 ${viewBoxWidth} ${newHeightCollapsed}`);

                        const axisYCollapsed = marginTop + swimlaneHeight + axisOffsetFromRows;
                        $svg.find('.timeline-axis').attr('transform', 'translate(0,' + axisYCollapsed + ')');
                        $svg.find('.now-line').attr('y2', axisYCollapsed);
                    } else {
                        $rows.each(function() {
                            const $row = $(this);
                            if ($row.hasClass('timeline-row--hidden')) {
                                return;
                            }
                            $row.find('.timeline-bg').css('opacity', 1);
                        });

                        let visibleIndex = 0;
                        $rows.each(function() {
                            const $row = $(this);
                            if ($row.hasClass('timeline-row--hidden')) {
                                return;
                            }

                            const baseY = overallSwimlaneY + (visibleIndex * (swimlaneHeight + swimlaneSpacing));
                            visibleIndex++;
                            animateRowToY($row, baseY);
                        });

                        const totalVisible = visibleIndex;
                        if (totalVisible > 0) {
                            const newHeight = marginTop + (totalVisible * swimlaneHeight) + ((totalVisible - 1) * swimlaneSpacing) + marginBottom;
                            $timelineContainer.height(newHeight);
                            $svg.attr('height', newHeight);
                            const currentViewBox = $svg.attr('viewBox') || '0 0 0 0';
                            const viewBoxParts = currentViewBox.split(' ');
                            const viewBoxWidth = viewBoxParts[2] || $timelineContainer.width();
                            $svg.attr('viewBox', `0 0 ${viewBoxWidth} ${newHeight}`);

                            const axisY = marginTop + (totalVisible * swimlaneHeight) + ((totalVisible - 1) * swimlaneSpacing) + axisOffsetFromRows;
                            $svg.find('.timeline-axis').attr('transform', 'translate(0,' + axisY + ')');
                            $svg.find('.now-line').attr('y2', axisY);
                        }
                    }

                    $bars.each(function() {
                        const $bar = $(this);
                        const barType = $bar.data('connection-type');

                        if (barType === 'life') {
                            $bar.css('opacity', 0.9);
                            return;
                        }

                        if (activeTypes.size === 0) {
                            $bar.css('opacity', 0.15);
                        } else if (showAllTypes || activeTypes.has(barType)) {
                            $bar.css('opacity', 0.9);
                        } else {
                            $bar.css('opacity', 0.15);
                        }
                    });
                }

                // Button handlers
                $allButton.on('click', function(e) {
                    if (e.which !== 1 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) {
                        return;
                    }
                    e.preventDefault();

                    activeTypes = new Set(allTypes);
                    updateButtonStates();
                    filterTimelineByTypes(layoutMode);
                    updateHash();
                });

                let clickTimeout = null;

                $typeButtons.on('click', function(e) {
                    if (e.which !== 1 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) {
                        return;
                    }
                    e.preventDefault();

                    const $btn = $(this);
                    const type = $btn.data('connection-type');

                    clearTimeout(clickTimeout);
                    clickTimeout = setTimeout(function() {
                        // Single click: isolate this type only
                        activeTypes = new Set([type]);

                        updateButtonStates();
                        filterTimelineByTypes(layoutMode);
                        updateHash();
                    }, 250);
                });

                $typeButtons.on('dblclick', function(e) {
                    if (e.which !== 1 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) {
                        return;
                    }
                    e.preventDefault();

                    clearTimeout(clickTimeout);

                    const $btn = $(this);
                    const type = $btn.data('connection-type');

                    // Double click: toggle this type in/out of the active set
                    if (activeTypes.has(type)) {
                        activeTypes.delete(type);
                    } else {
                        activeTypes.add(type);
                    }

                    updateButtonStates();
                    filterTimelineByTypes(layoutMode);
                    updateHash();
                });

                // Listen for hash changes (browser back/forward)
                $(window).on('hashchange', function() {
                    const hash = window.location.hash.substring(1);
                    if (hash && predicateToTypeMap[hash]) {
                        const hashType = predicateToTypeMap[hash];
                        if (allTypes.has(hashType)) {
                            activeTypes = new Set([hashType]);
                            updateButtonStates();
                            filterTimelineByTypes(layoutMode);
                        }
                    } else if (!hash) {
                        // No hash - show all
                        activeTypes = new Set(allTypes);
                        updateButtonStates();
                        filterTimelineByTypes(layoutMode);
                    }
                });

                $layoutRadios.on('change', function() {
                    const selected = $(this).val();
                    if (selected === 'expanded' || selected === 'collapsed') {
                        layoutMode = selected;
                        filterTimelineByTypes(layoutMode);
                    }
                });

                if ($stateFilter.length) {
                    $stateFilter.on('change', function() {
                        stateFilter = $(this).val();
                        filterTimelineByTypes(layoutMode);
                    });
                }

                // Apply initial filter state
                filterTimelineByTypes(layoutMode);
                updateHash(); // Update hash to match initial state
            });
        });
    })(jQuery);
</script>
@endpush

