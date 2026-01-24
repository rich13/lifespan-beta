@extends('layouts.app')

@section('page_title')
    <x-breadcrumb :items="[
        //[
        //    'text' => 'Spans',
        //    'url' => route('spans.index'),
        //    'icon' => 'view',
        //    'icon_category' => 'action'
        //],
        [
            'text' => $subject->getDisplayTitle(),
            'url' => route('spans.show', $subject),
            'icon' => $subject->type_id,
            'icon_category' => 'span'
        ],
        [
            'text' => 'All Connections',
            'url' => route('spans.all-connections', $subject),
            'icon' => 'diagram-3',
            'icon_category' => 'connection'
        ]
    ]" />
@endsection

@section('content')
<div class="py-4">
    <div class="row">
        <div class="col-12">

            <!-- Connection Type Navigation moved into timeline card header -->

            <!-- Comprehensive Gantt Chart -->
            @if($allConnections->count() > 0)
                @php
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
                    
                    // Add individual connection swimlanes (now in single chronological order)
                    foreach ($allConnections as $connection) {
                        $timelineData[] = [
                            'type' => 'connection',
                            'connectionType' => $connection->connection_type_id ?? $connection->connection_type->type ?? 'connection',
                            'connection' => $connection,
                            'label' => $connection->other_span->name,
                            'y' => $overallSwimlaneY + ($swimlaneIndex * ($swimlaneHeight + $swimlaneSpacing))
                        ];
                        $swimlaneIndex++;
                    }
                    
                    // Calculate time range
                    $minYear = $subject->start_year ?? 1900;
                    $maxYear = $subject->end_year ?? date('Y');
                    
                    foreach ($allConnections as $connection) {
                        if ($connection->connectionSpan && $connection->connectionSpan->start_year) {
                            $minYear = min($minYear, $connection->connectionSpan->start_year);
                            $maxYear = max($maxYear, $connection->connectionSpan->end_year ?? date('Y'));
                        }
                    }
                    
                    $timeRange = ['start' => $minYear, 'end' => $maxYear];
                @endphp
                
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-2">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-clock-history me-2"></i>
                                Lifespan
                            </h5>
                            <div class="d-flex flex-wrap align-items-center gap-2">
                                @php
                                    // Filter to only connection types that have connections
                                    $typesWithConnections = $relevantConnectionTypes->filter(function($type) use ($connectionCounts) {
                                        $hasConnections = $connectionCounts[$type->type] ?? 0;
                                        return $hasConnections > 0;
                                    });
                                @endphp
                                @if($typesWithConnections->count() > 0)
                                    <div class="btn-group btn-group-sm connection-type-filter-buttons" role="group" aria-label="Connection types">
                                        <a href="{{ route('spans.all-connections', $subject) }}" 
                                           class="btn btn-sm btn-primary"
                                           data-connection-type="all">
                                            All Connections
                                        </a>
                                        @foreach($typesWithConnections as $type)
                                            @php
                                                // Use the appropriate predicate based on connection direction
                                                $directionInfo = $connectionTypeDirections[$type->type] ?? null;
                                                $predicate = ($directionInfo && isset($directionInfo['predicate'])) 
                                                    ? $directionInfo['predicate'] 
                                                    : $type->forward_predicate;
                                                $routePredicate = str_replace(' ', '-', $predicate);
                                                $url = route('spans.connections', ['subject' => $subject, 'predicate' => $routePredicate]);
                                            @endphp
                                            <a href="{{ $url }}" 
                                               class="btn btn-sm btn-secondary"
                                               data-connection-type="{{ $type->type }}"
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
                                <select class="form-select form-select-sm" id="state-filter" style="width: auto; display: inline-block;" title="Filter by connection state">
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
                        containerId="all-connections-timeline-container"
                        :subjectStartYear="$subject->start_year"
                        :subjectEndYear="$subject->end_year"
                        :timeRange="$timeRange"
                    />
                </div>
            @else
                <div class="card">
                    <div class="card-body text-center">
                        <i class="bi bi-info-circle text-muted" style="font-size: 3rem;"></i>
                        <h5 class="mt-3">No Connections Found</h5>
                        <p class="text-muted">This span doesn't have any connections yet.</p>
                    </div>
                </div>
            @endif
        </div>
    </div>
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
    // jQuery-based filtering of the shared timeline on the "All Connections" page
    (function($) {
        $(function() {
            const $buttons = $('.connection-type-filter-buttons a');
            const $timelineContainer = $('#all-connections-timeline-container');
            const $layoutRadios = $('input[name="all-connections-layout"]');
            let layoutMode = 'expanded'; // 'expanded' | 'collapsed'
            const $allButton = $buttons.filter('[data-connection-type="all"]');
            const $typeButtons = $buttons.not($allButton);
            const allTypes = new Set(
                $typeButtons
                    .map(function() { return $(this).data('connection-type'); })
                    .get()
            );
            let activeTypes = new Set(allTypes); // start with all types active
            
            // State filter: 'all' | 'placeholder' | 'draft' | 'complete'
            let stateFilter = 'all';
            const $stateFilter = $('#state-filter');
            
            if (!$stateFilter.length) {
                console.warn('State filter dropdown not found');
            }

            if (!$buttons.length || !$timelineContainer.length) {
                return;
            }

            function updateButtonStates() {
                // Update per-type buttons based on activeTypes
                $typeButtons.each(function() {
                    const $btn = $(this);
                    const type = $btn.data('connection-type');
                    if (activeTypes.has(type)) {
                        $btn.addClass('active');
                    } else {
                        $btn.removeClass('active');
                    }
                });

                // "All connections" button is active only when all types are active
                if (activeTypes.size === allTypes.size) {
                    $allButton.addClass('active');
                } else {
                    $allButton.removeClass('active');
                }
            }

            // Initialise button states (all types active)
            updateButtonStates();

            // Single-click handler for "All connections" – reset to show everything
            $allButton.on('click', function(e) {
                // Only intercept simple left-clicks without modifier keys
                if (e.which !== 1 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) {
                    return;
                }

                e.preventDefault();

                activeTypes = new Set(allTypes);
                updateButtonStates();
                filterTimelineByTypes(layoutMode);
            });

            // Single vs double click for individual type buttons
            let clickTimeout = null;

            $typeButtons.on('click', function(e) {
                // Only intercept simple left-clicks without modifier keys
                if (e.which !== 1 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) {
                    return;
                }

                e.preventDefault();

                const $btn = $(this);
                const type = $btn.data('connection-type');

                clearTimeout(clickTimeout);
                clickTimeout = setTimeout(function() {
                    // Toggle this type on/off
                    if (activeTypes.has(type)) {
                        activeTypes.delete(type);
                    } else {
                        activeTypes.add(type);
                    }

                    updateButtonStates();
                    filterTimelineByTypes(layoutMode);
                }, 250);
            });

            // Double-click isolates a single type (all others off)
            $typeButtons.on('dblclick', function(e) {
                // Only intercept simple left-clicks without modifier keys
                if (e.which !== 1 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) {
                    return;
                }

                e.preventDefault();

                clearTimeout(clickTimeout);

                const $btn = $(this);
                const type = $btn.data('connection-type');

                activeTypes = new Set([type]);
                updateButtonStates();
                filterTimelineByTypes(layoutMode);
            });

            $layoutRadios.on('change', function() {
                const selected = $(this).val();
                if (selected === 'expanded' || selected === 'collapsed') {
                    layoutMode = selected;
                    // Reapply current filter in new layout mode
                    filterTimelineByTypes(layoutMode);
                }
            });
            
            // State filter dropdown change handler
            if ($stateFilter.length) {
                $stateFilter.on('change', function() {
                    stateFilter = $(this).val();
                    filterTimelineByTypes(layoutMode);
                });
            }

            function filterTimelineByTypes(layout) {
                const $rows = $timelineContainer.find('.timeline-row');
                const $bars = $timelineContainer.find('.timeline-bar');
                const swimlaneHeight = 20; // Keep in sync with shared-timeline component
                const swimlaneSpacing = 8; // Keep in sync with shared-timeline component
                const marginTop = 10;
                const marginBottom = 100; // Matches shared-timeline component
                const axisOffsetFromRows = 20; // Keep in sync with shared-timeline component
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
                    // Apply state filter even when showing all types
                    $rows.each(function() {
                        const $row = $(this);
                        const rowType = $row.data('connection-type');
                        const rowState = $row.attr('data-connection-state'); // Use attr() instead of data() to get the actual attribute value
                        
                        let stateMatches = true;
                        if (rowType !== 'life') {
                            if (stateFilter === 'all') {
                                stateMatches = true; // Show all
                            } else {
                                // Filter to show only the selected state
                                stateMatches = rowState === stateFilter;
                            }
                        }
                        
                        if (stateMatches) {
                            $row.removeClass('timeline-row--hidden');
                        } else {
                            $row.addClass('timeline-row--hidden');
                        }
                    });
                    
                    // Set opacity for all bars except life bar
                    $bars.each(function() {
                        const $bar = $(this);
                        if ($bar.data('connection-type') !== 'life') {
                            $bar.css('opacity', 0.7);
                        }
                    });
                    
                    if (layout === 'collapsed') {
                        // Overlay all rows on top of the life row and hide extra backgrounds/labels
                        const $lifeRow = $rows.filter('[data-connection-type="life"]').first();
                        const lifeTransform = $lifeRow.attr('transform') || 'translate(0,0)';
                        const lifeY = parseFloat(lifeTransform.match(/translate\(0, *([0-9.]+)\)/)?.[1] || 0);

                        $rows.each(function() {
                            const $row = $(this);
                            const rowType = $row.data('connection-type');

                            // Move all rows to the life row Y (animated)
                            animateRowToY($row, lifeY);

                            // Show only the life row background; hide others
                            if (rowType === 'life') {
                                $row.find('.timeline-bg').css('opacity', 1);
                            } else {
                                $row.find('.timeline-bg').css('opacity', 0);
                            }
                        });

                        // Compact the container to a single swimlane height
                        const newHeightCollapsed = marginTop + swimlaneHeight + marginBottom;
                        $timelineContainer.height(newHeightCollapsed);
                        $svg.attr('height', newHeightCollapsed);
                        // Update viewBox to maintain proper scaling
                        const currentViewBox = $svg.attr('viewBox') || '0 0 0 0';
                        const viewBoxParts = currentViewBox.split(' ');
                        const viewBoxWidth = viewBoxParts[2] || $timelineContainer.width();
                        $svg.attr('viewBox', `0 0 ${viewBoxWidth} ${newHeightCollapsed}`);

                        const axisYCollapsed = marginTop + swimlaneHeight + axisOffsetFromRows;
                        $svg.find('.timeline-axis').attr('transform', 'translate(0,' + axisYCollapsed + ')');
                        $svg.find('.now-line').attr('y2', axisYCollapsed);
                    } else {
                        // Expanded: show backgrounds for visible rows only
                        $rows.each(function() {
                            const $row = $(this);
                            if ($row.hasClass('timeline-row--hidden')) {
                                $row.find('.timeline-bg').css('opacity', 0);
                            } else {
                                $row.find('.timeline-bg').css('opacity', 1);
                            }
                        });

                        // Re-stack only visible rows (animated) - skip hidden rows to remove gaps
                        let visibleIndexAll = 0;
                        $rows.each(function() {
                            const $row = $(this);
                            if ($row.hasClass('timeline-row--hidden')) {
                                return; // Skip hidden rows
                            }
                            const baseY = overallSwimlaneY + (visibleIndexAll * (swimlaneHeight + swimlaneSpacing));
                            visibleIndexAll++;
                            animateRowToY($row, baseY);
                        });

                        // Reposition axis immediately below the last visible row
                        const totalVisibleAll = visibleIndexAll;
                        if (totalVisibleAll > 0) {
                            const newHeightAll = marginTop + (totalVisibleAll * swimlaneHeight) + ((totalVisibleAll - 1) * swimlaneSpacing) + marginBottom;
                            $timelineContainer.height(newHeightAll);
                            $svg.attr('height', newHeightAll);
                            // Update viewBox to maintain proper scaling
                            const currentViewBox = $svg.attr('viewBox') || '0 0 0 0';
                            const viewBoxParts = currentViewBox.split(' ');
                            const viewBoxWidth = viewBoxParts[2] || $timelineContainer.width();
                            $svg.attr('viewBox', `0 0 ${viewBoxWidth} ${newHeightAll}`);

                            const axisYAll = marginTop + (totalVisibleAll * swimlaneHeight) + ((totalVisibleAll - 1) * swimlaneSpacing) + axisOffsetFromRows;
                            $svg.find('.timeline-axis').attr('transform', 'translate(0,' + axisYAll + ')');

                            // Adjust the "now" line so it ends at the new axis level
                            $svg.find('.now-line').attr('y2', axisYAll);
                        }
                    }

                    return;
                }

                // First determine which rows should be visible / hidden
                $rows.each(function() {
                    const $row = $(this);
                    const rowType = $row.data('connection-type');
                    const rowState = $row.attr('data-connection-state'); // Use attr() instead of data() to get the actual attribute value

                    // Check type filter
                    const typeMatches = rowType === 'life' || activeTypes.has(rowType);
                    
                    // Check state filter
                    let stateMatches = true;
                    if (rowType !== 'life') {
                        if (stateFilter === 'all') {
                            stateMatches = true; // Show all states
                        } else {
                            // Filter to show only the selected state
                            stateMatches = rowState === stateFilter;
                        }
                    }

                    // Row is visible if both type and state filters match
                    if (typeMatches && stateMatches) {
                        $row.removeClass('timeline-row--hidden');
                    } else {
                        $row.addClass('timeline-row--hidden');
                    }
                });

                if (layout === 'collapsed') {
                    // Overlay all visible rows on top of the life row and hide extra backgrounds/labels
                    const $lifeRow = $rows.filter('[data-connection-type="life"]').first();
                    const lifeTransform = $lifeRow.attr('transform') || 'translate(0,0)';
                    const lifeY = parseFloat(lifeTransform.match(/translate\(0, *([0-9.]+)\)/)?.[1] || 0);

                    $rows.each(function() {
                        const $row = $(this);
                        if ($row.hasClass('timeline-row--hidden')) {
                            return;
                        }
                        const rowType = $row.data('connection-type');

                        // Move visible rows to the life row Y (animated)
                        animateRowToY($row, lifeY);

                        // Show only the life row background; hide others
                        if (rowType === 'life') {
                            $row.find('.timeline-bg').css('opacity', 1);
                        } else {
                            $row.find('.timeline-bg').css('opacity', 0);
                        }
                    });

                    const newHeightCollapsed = marginTop + swimlaneHeight + marginBottom;
                    $timelineContainer.height(newHeightCollapsed);
                    $svg.attr('height', newHeightCollapsed);
                    // Update viewBox to maintain proper scaling
                    const currentViewBox = $svg.attr('viewBox') || '0 0 0 0';
                    const viewBoxParts = currentViewBox.split(' ');
                    const viewBoxWidth = viewBoxParts[2] || $timelineContainer.width();
                    $svg.attr('viewBox', `0 0 ${viewBoxWidth} ${newHeightCollapsed}`);

                    const axisYCollapsed = marginTop + swimlaneHeight + axisOffsetFromRows;
                    $svg.find('.timeline-axis').attr('transform', 'translate(0,' + axisYCollapsed + ')');
                    $svg.find('.now-line').attr('y2', axisYCollapsed);
                } else {
                    // Expanded: restore backgrounds and stack visible rows
                    $rows.each(function() {
                        const $row = $(this);
                        if ($row.hasClass('timeline-row--hidden')) {
                            return;
                        }
                        $row.find('.timeline-bg').css('opacity', 1);
                    });

                    // Then re-stack only the visible rows so there are no gaps (animated)
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
                        // Update viewBox to maintain proper scaling
                        const currentViewBox = $svg.attr('viewBox') || '0 0 0 0';
                        const viewBoxParts = currentViewBox.split(' ');
                        const viewBoxWidth = viewBoxParts[2] || $timelineContainer.width();
                        $svg.attr('viewBox', `0 0 ${viewBoxWidth} ${newHeight}`);

                        const axisY = marginTop + (totalVisible * swimlaneHeight) + ((totalVisible - 1) * swimlaneSpacing) + axisOffsetFromRows;
                        $svg.find('.timeline-axis').attr('transform', 'translate(0,' + axisY + ')');

                        // Adjust the "now" line so it ends at the new axis level
                        $svg.find('.now-line').attr('y2', axisY);
                    }
                }

                // Emphasise matching bars (but keep life bar at full opacity)
                $bars.each(function() {
                    const $bar = $(this);
                    const barType = $bar.data('connection-type');

                    // Life bar always stays at full opacity
                    if (barType === 'life') {
                        $bar.css('opacity', 0.9);
                        return;
                    }

                    if (activeTypes.size === 0) {
                        // No active types – de-emphasise everything
                        $bar.css('opacity', 0.15);
                    } else if (showAllTypes || activeTypes.has(barType)) {
                        $bar.css('opacity', 0.9);
                    } else {
                        $bar.css('opacity', 0.15);
                    }
                });
            }
        });
    })(jQuery);
</script>
@endpush

@endsection
