@props([
    'subject',
    'connections',                // Flat collection or paginator of Connection models
    'relevantConnectionTypes',    // Collection of ConnectionType models
    'connectionCounts' => [],     // [type => count]
    'connectionTypeDirections' => [], // [type => ['predicate' => string, ...]]
    'containerId' => 'konva-all-connections-timeline',
])

@php
    // Normalise connections into a collection
    $connectionsCollection = $connections instanceof \Illuminate\Pagination\LengthAwarePaginator
        ? collect($connections->items())
        : ($connections instanceof \Illuminate\Support\Collection ? $connections : collect($connections));

    // Prepare timeline data (life + one lane per connection, like the existing D3 timeline)
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
        'y' => $overallSwimlaneY + ($swimlaneIndex * ($swimlaneHeight + $swimlaneSpacing)),
    ];
    $swimlaneIndex++;

    // Load phase spans (during connections) for each connection's connection_span
    $connectionSpanIds = $connectionsCollection
        ->map(fn ($c) => $c->connectionSpan?->id ?? $c->connection_span_id ?? null)
        ->filter()
        ->unique()
        ->values()
        ->all();
    $phaseSpansByConnectionSpanId = collect();
    if (!empty($connectionSpanIds)) {
        $duringConnections = \App\Models\Connection::where('type_id', 'during')
            ->whereIn('parent_id', $connectionSpanIds)
            ->with('child:id,name,type_id,start_year,start_month,start_day,end_year,end_month,end_day')
            ->get();
        foreach ($duringConnections as $during) {
            $child = $during->child;
            if (!$child || !$child->start_year) {
                continue;
            }
            $pid = $during->parent_id;
            if (!isset($phaseSpansByConnectionSpanId[$pid])) {
                $phaseSpansByConnectionSpanId[$pid] = [];
            }
            $phaseSpansByConnectionSpanId[$pid][] = [
                'name' => $child->name ?? 'Phase',
                'start_year' => $child->start_year,
                'start_month' => $child->start_month ?? 0,
                'start_day' => $child->start_day ?? 0,
                'end_year' => $child->end_year,
                'end_month' => $child->end_month ?? 0,
                'end_day' => $child->end_day ?? 0,
            ];
        }
    }

    // Add individual connection swimlanes (single chronological order)
    foreach ($connectionsCollection as $connection) {
        $connSpanId = $connection->connectionSpan?->id ?? $connection->connection_span_id ?? null;
        $phaseSpans = $connSpanId ? ($phaseSpansByConnectionSpanId[$connSpanId] ?? []) : [];
        $timelineData[] = [
            'type' => 'connection',
            'connectionType' => $connection->connection_type_id ?? $connection->connection_type->type ?? 'connection',
            'connection' => $connection,
            'label' => $connection->other_span->name ?? '',
            'phaseSpans' => $phaseSpans,
            'y' => $overallSwimlaneY + ($swimlaneIndex * ($swimlaneHeight + $swimlaneSpacing)),
        ];
        $swimlaneIndex++;
    }

    // Calculate time range (roughly matching the existing behaviour)
    $minYear = $subject->start_year ?? 1900;
    $maxYear = $subject->end_year ?? date('Y');

    foreach ($connectionsCollection as $connection) {
        $connectionSpan = $connection->connectionSpan ?? $connection->connection_span ?? null;
        if ($connectionSpan) {
            if ($connectionSpan->start_year && $connectionSpan->start_year < $minYear) {
                $minYear = $connectionSpan->start_year;
            }
            if ($connectionSpan->end_year && $connectionSpan->end_year > $maxYear) {
                $maxYear = $connectionSpan->end_year;
            }
        }
    }

    // Add a little padding so bars are not flush with the edge
    $padding = max(2, (int) floor(($maxYear - $minYear) * 0.05));
    $minYear = max(1800, $minYear - $padding);
    $maxYear = min((int) date('Y') + 5, $maxYear + $padding);

    $timeRange = ['start' => $minYear, 'end' => $maxYear];

    // Filter to only connection types that have connections (same as D3 header)
    $typesWithConnections = $relevantConnectionTypes->filter(function($type) use ($connectionCounts) {
        $hasConnections = $connectionCounts[$type->type] ?? 0;
        return $hasConnections > 0;
    });
@endphp

<div class="card konva-connections-timeline-card"
     data-timeline-container-id="{{ $containerId }}"
     data-initial-connection-type="all">
    <div class="card-header">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-2">
            <h5 class="card-title mb-0">
                <i class="bi bi-clock-history me-2"></i>
                Lifespanner
                <span class="badge bg-secondary ms-2">Experimental</span>
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
                                $allConnectionsUrl = route('spans.all', $subject);
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
                    <input type="radio" class="btn-check" name="all-connections-layout" id="konva-all-connections-layout-expanded" value="expanded" checked>
                    <label class="btn btn-outline-secondary" for="konva-all-connections-layout-expanded" title="Expanded (one lane per connection)">
                        <i class="bi bi-arrows-expand"></i>
                    </label>
                    <input type="radio" class="btn-check" name="all-connections-layout" id="konva-all-connections-layout-grouped" value="grouped">
                    <label class="btn btn-outline-secondary" for="konva-all-connections-layout-grouped" title="Group by connection type">
                        <i class="bi bi-layers-half"></i>
                    </label>
                    <input type="radio" class="btn-check" name="all-connections-layout" id="konva-all-connections-layout-collapsed" value="collapsed">
                    <label class="btn btn-outline-secondary" for="konva-all-connections-layout-collapsed" title="Collapsed (all lanes overlapped)">
                        <i class="bi bi-arrows-collapse"></i>
                    </label>
                </div>
                <select class="form-select form-select-sm state-filter" style="width: auto; display: inline-block;" title="Filter by connection state">
                    <option value="all">All States</option>
                    <option value="placeholder">Placeholders Only</option>
                    <option value="draft">Drafts Only</option>
                    <option value="complete">Complete Only</option>
                </select>
                <div class="btn-group btn-group-sm konva-timeline-zoom-buttons" role="group" aria-label="Timeline zoom">
                    <button type="button" class="btn btn-outline-secondary" title="Zoom out" aria-label="Zoom out">
                        <i class="bi bi-dash-lg"></i>
                    </button>
                    <button type="button" class="btn btn-outline-secondary konva-timeline-zoom-reset" title="Reset zoom (1:1)" aria-label="Reset zoom">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </button>
                    <button type="button" class="btn btn-outline-secondary" title="Zoom in" aria-label="Zoom in">
                        <i class="bi bi-plus-lg"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
    <div class="card-body" style="overflow-x: auto;">
        <div
            id="{{ $containerId }}"
            style="height: auto; min-height: 220px; width: 100%;"
        ></div>
    </div>
</div>

@push('styles')
<style>
    /* Let the Konva canvas span the full card width with no internal horizontal padding */
    .konva-connections-timeline-card .card-body {
        padding-left: 0;
        padding-right: 0;
    }

    .konva-connections-timeline-card .card-body > div[id^="konva-"] {
        width: 100%;
    }

    .konva-timeline-tooltip {
        position: absolute;
        background: rgba(0, 0, 0, 0.85);
        color: #fff;
        padding: 6px 8px;
        border-radius: 4px;
        font-size: 12px;
        pointer-events: none;
        z-index: 2000;
        display: none;
        max-width: 260px;
    }

    /* Inactive connection-type buttons (not in current swimlane set) */
    .konva-connections-timeline-card .connection-type-filter-buttons .btn.connection-type-inactive {
        opacity: 0.5;
    }
</style>
@endpush

@push('scripts')
    @once
        <script src="https://unpkg.com/konva@9/konva.min.js"></script>
    @endonce
    <script>
        (function($) {
            // Render after full window load so layout (including scrollbars/breakpoints)
            // has fully settled before we measure widths.
            $(window).on('load', function() {
                $('.konva-connections-timeline-card').each(function() {
                    var $root = $(this);
                    var containerId = $root.data('timeline-container-id');
                    var initialType = $root.data('initial-connection-type') || 'all';

                    var container = document.getElementById(containerId);
                    if (!container) {
                        return;
                    }

                    // Basic geometry (symmetric horizontal margins inside the card)
                    var marginTop = 20;
                    var marginLeft = 40;
                    var marginRight = 40;
                    var marginBottom = 40;
                    var laneHeight = 16;
                    var laneSpacing = 1;

                    var timelineData = @json($timelineData);
                    var timeRange = @json($timeRange);
                    var subjectStartYear = {{ $subject->start_year ?? 'null' }};
                    var subjectStartMonth = {{ $subject->start_month ?? 'null' }};
                    var subjectStartDay = {{ $subject->start_day ?? 'null' }};
                    var subjectEndYear = {{ $subject->end_year ?? 'null' }};
                    var subjectEndMonth = {{ $subject->end_month ?? 'null' }};
                    var subjectEndDay = {{ $subject->end_day ?? 'null' }};
                    var subjectName = @json($subject->name ?? '');

                    if (!timelineData || !timelineData.length) {
                        return;
                    }

                    var totalLanes = timelineData.length;

                    // Derive width from the Konva container (which itself is 100% of the card body)
                    // so that the stage width exactly matches the DOM box and there is no scaling.
                    var rootWidth = $root.width();
                    var containerRect = container.getBoundingClientRect();
                    var containerClientWidth = container.clientWidth;
                    var width = containerClientWidth || containerRect.width || rootWidth || 800;
                    var height = marginTop + totalLanes * (laneHeight + laneSpacing) + marginBottom;

                    container.style.height = height + 'px';

                    // Create Konva stage
                    if (!window.Konva) {
                        console.error('Konva not loaded – timeline cannot be rendered');
                        return;
                    }

                    var stage = new Konva.Stage({
                        container: containerId,
                        width: width,
                        height: height
                    });

                    var backgroundLayer = new Konva.Layer();
                    var barLayer = new Konva.Layer();
                    var axisLayer = new Konva.Layer();

                    stage.add(backgroundLayer);
                    stage.add(barLayer);
                    stage.add(axisLayer);

                    var viewportWidth = Math.max(50, width - marginLeft - marginRight);
                    var zoomScale = 1;
                    var panX = 0;
                    var panY = 0;
                    var zoomMin = 1;
                    var zoomMax = 500;
                    var contentTimelineWidth = viewportWidth * zoomScale;

                    var backgroundGroup = new Konva.Group({ x: marginLeft - panX, y: 0 });
                    var barGroup = new Konva.Group({ x: marginLeft - panX, y: 0 });
                    var axisGroup = new Konva.Group({ x: marginLeft - panX, y: 0 });
                    var overlayGroup = new Konva.Group({ x: marginLeft - panX, y: 0 });
                    backgroundLayer.add(backgroundGroup);
                    barLayer.add(barGroup);
                    var dimShape = new Konva.Shape({
                        sceneFunc: function(context, shape) {
                            var w = contentTimelineWidth;
                            var stageObj = shape.getStage();
                            var h = stageObj ? stageObj.height() : height;
                            var holeX = shape.getAttr('holeX');
                            var holeY = shape.getAttr('holeY');
                            var holeW = shape.getAttr('holeW');
                            var holeH = shape.getAttr('holeH');
                            context.fillStyle = 'rgba(0,0,0,0.5)';
                            if (holeX != null && holeW > 0 && holeH > 0) {
                                // Draw four rects around the hole so the span is never covered
                                context.beginPath();
                                context.rect(0, 0, w, holeY);
                                context.fill();
                                context.beginPath();
                                context.rect(0, holeY, holeX, holeH);
                                context.fill();
                                context.beginPath();
                                context.rect(holeX + holeW, holeY, w - (holeX + holeW), holeH);
                                context.fill();
                                context.beginPath();
                                context.rect(0, holeY + holeH, w, h - (holeY + holeH));
                                context.fill();
                            } else {
                                context.beginPath();
                                context.rect(0, 0, w, h);
                                context.fill();
                            }
                        },
                        visible: false,
                        listening: false
                    });
                    dimShape.setAttr('holeX', null);
                    dimShape.setAttr('holeY', null);
                    dimShape.setAttr('holeW', 0);
                    dimShape.setAttr('holeH', 0);
                    overlayGroup.add(dimShape);
                    barLayer.add(overlayGroup);
                    axisLayer.add(axisGroup);
                    backgroundLayer.clip({ x: 0, y: 0, width: width, height: height });
                    barLayer.clip({ x: 0, y: 0, width: width, height: height });
                    axisLayer.clip({ x: 0, y: 0, width: width, height: height });

                    function clampPan() {
                        var maxPanX = Math.max(0, contentTimelineWidth - viewportWidth);
                        panX = Math.max(0, Math.min(panX, maxPanX));
                    }

                    function setGroupsX(x) {
                        backgroundGroup.x(x);
                        barGroup.x(x);
                        axisGroup.x(x);
                        overlayGroup.x(x);
                    }

                    function applyZoomPan() {
                        dimShape.visible(false);
                        contentTimelineWidth = viewportWidth * zoomScale;
                        clampPan();
                        setGroupsX(marginLeft - panX);
                        if (typeof updateTimelinePositions === 'function') {
                            updateTimelinePositions();
                        }
                        if (zoomScale > 1) {
                            var maxPanX = contentTimelineWidth - viewportWidth;
                            var boundFunc = function(pos) {
                                return {
                                    x: Math.max(marginLeft - maxPanX, Math.min(marginLeft, pos.x)),
                                    y: 0
                                };
                            };
                            backgroundGroup.draggable(true);
                            backgroundGroup.dragBoundFunc(boundFunc);
                            barGroup.draggable(true);
                            barGroup.dragBoundFunc(boundFunc);
                            axisGroup.draggable(true);
                            axisGroup.dragBoundFunc(boundFunc);
                        } else {
                            backgroundGroup.draggable(false);
                            barGroup.draggable(false);
                            axisGroup.draggable(false);
                        }
                        backgroundLayer.batchDraw();
                        barLayer.batchDraw();
                        axisLayer.batchDraw();
                    }

                    function syncGroupsFromPanSource(sourceGroup) {
                        var gx = sourceGroup.x();
                        backgroundGroup.x(gx);
                        barGroup.x(gx);
                        axisGroup.x(gx);
                        overlayGroup.x(gx);
                        panX = marginLeft - gx;
                    }

                    backgroundGroup.on('dragmove', function() {
                        syncGroupsFromPanSource(backgroundGroup);
                    });
                    backgroundGroup.on('dragend', function() {
                        panX = marginLeft - backgroundGroup.x();
                    });
                    barGroup.on('dragmove', function() {
                        syncGroupsFromPanSource(barGroup);
                    });
                    barGroup.on('dragend', function() {
                        panX = marginLeft - barGroup.x();
                    });
                    axisGroup.on('dragmove', function() {
                        syncGroupsFromPanSource(axisGroup);
                    });
                    axisGroup.on('dragend', function() {
                        panX = marginLeft - axisGroup.x();
                    });

                    var zoomTweenRaf = null;
                    function easeInOutQuad(t) {
                        return t < 0.5 ? 2 * t * t : 1 - Math.pow(-2 * t + 2, 2) / 2;
                    }
                    function tweenZoomPan(targetZoomScale, targetPanX, durationMs) {
                        if (zoomTweenRaf != null) {
                            cancelAnimationFrame(zoomTweenRaf);
                        }
                        var startZoom = zoomScale;
                        var startPan = panX;
                        var startTime = null;
                        function tick(now) {
                            if (startTime == null) startTime = now;
                            var elapsed = now - startTime;
                            var t = Math.min(elapsed / durationMs, 1);
                            t = easeInOutQuad(t);
                            zoomScale = startZoom + (targetZoomScale - startZoom) * t;
                            panX = startPan + (targetPanX - startPan) * t;
                            applyZoomPan();
                            if (t < 1) {
                                zoomTweenRaf = requestAnimationFrame(tick);
                            } else {
                                zoomTweenRaf = null;
                            }
                        }
                        zoomTweenRaf = requestAnimationFrame(tick);
                    }

                    var yearSpan = Math.max(1, (timeRange.end - timeRange.start) || 1);
                    var pixelsPerYear = contentTimelineWidth / yearSpan;

                    function xForYear(year) {
                        return (year - timeRange.start) / yearSpan * contentTimelineWidth;
                    }

                    function getConnectionColor(typeId) {
                        var raw = getComputedStyle(document.documentElement)
                            .getPropertyValue('--connection-' + typeId + '-color');
                        if (raw && raw.trim() !== '') {
                            return raw.trim();
                        }
                        return '#007bff';
                    }

                    function normaliseConnectionSpan(connection) {
                        if (!connection) {
                            return null;
                        }
                        var span = connection.connection_span || connection.connectionSpan || null;
                        if (span && span.data) {
                            span = span.data;
                        }
                        return span || null;
                    }

                    // Convert year/month/day to fractional year (midpoint of the precision window)
                    function dateToFractionalYear(year, month, day) {
                        if (!year) return null;
                        if (!month || month === 0) {
                            // Year precision: middle of year
                            return year + 0.5;
                        }
                        if (!day || day === 0) {
                            // Month precision: middle of month
                            var daysInMonth = new Date(year, month, 0).getDate();
                            var monthStart = new Date(year, month - 1, 1);
                            var yearStart = new Date(year, 0, 1);
                            var daysFromStart = (monthStart - yearStart) / (1000 * 60 * 60 * 24);
                            var monthMid = daysInMonth / 2;
                            return year + (daysFromStart + monthMid) / 365.25;
                        }
                        // Day precision: exact day
                        var date = new Date(year, month - 1, day);
                        var startOfYear = new Date(year, 0, 1);
                        var days = (date - startOfYear) / (1000 * 60 * 60 * 24);
                        return year + days / 365.25;
                    }

                    // Fractional year from the *earliest* possible date represented by the precision
                    // (used for the left edge of bars)
                    function fractionalYearFromStart(year, month, day) {
                        if (!year) return null;
                        var m = month && month !== 0 ? month : 1;
                        var d = day && day !== 0 ? day : 1;
                        var date = new Date(year, m - 1, d);
                        var startOfYear = new Date(year, 0, 1);
                        var days = (date - startOfYear) / (1000 * 60 * 60 * 24);
                        return year + days / 365.25;
                    }

                    // Fractional year to the *latest* possible date represented by the precision
                    // (used for the right edge of bars)
                    function fractionalYearToEnd(year, month, day) {
                        if (!year) return null;
                        var m, d;
                        if (!month || month === 0) {
                            // Year precision: last day of year
                            m = 12;
                            d = 31;
                        } else if (!day || day === 0) {
                            // Month precision: last day of month
                            m = month;
                            d = new Date(year, month, 0).getDate();
                        } else {
                            m = month;
                            d = day;
                        }
                        var date = new Date(year, m - 1, d);
                        var startOfYear = new Date(year, 0, 1);
                        var days = (date - startOfYear) / (1000 * 60 * 60 * 24);
                        return year + days / 365.25;
                    }

                    // How wide should the gradient be (in "years") based on precision
                    function precisionWindowYears(month, day) {
                        // Day precision => crisp edge
                        if (day && day !== 0) {
                            return 0;
                        }
                        // Month precision => about a month on each side
                        if (month && month !== 0) {
                            return 1 / 12;
                        }
                        // Year precision => a year on each side
                        return 1;
                    }

                    function makeRgba(colour, alpha) {
                        var c = (colour || '').toString().trim();
                        if (!c) {
                            return 'rgba(0,0,0,' + alpha + ')';
                        }

                        // Handle hex colours: #rgb or #rrggbb
                        if (c[0] === '#' && (c.length === 4 || c.length === 7)) {
                            var r, g, b;
                            if (c.length === 7) {
                                r = parseInt(c.substr(1, 2), 16);
                                g = parseInt(c.substr(3, 2), 16);
                                b = parseInt(c.substr(5, 2), 16);
                            } else {
                                r = parseInt(c[1] + c[1], 16);
                                g = parseInt(c[2] + c[2], 16);
                                b = parseInt(c[3] + c[3], 16);
                            }
                            return 'rgba(' + r + ',' + g + ',' + b + ',' + alpha + ')';
                        }

                        // Handle rgb()/rgba()
                        if (c.toLowerCase().startsWith('rgb')) {
                            // Extract numbers
                            var nums = c.replace(/rgba?\(/i, '').replace(')', '').split(',');
                            var r = parseInt(nums[0], 10) || 0;
                            var g = parseInt(nums[1], 10) || 0;
                            var b = parseInt(nums[2], 10) || 0;
                            return 'rgba(' + r + ',' + g + ',' + b + ',' + alpha + ')';
                        }

                        // Fallback: return as-is (no transparency change)
                        return c;
                    }

                    function deriveConnectionState(connection) {
                        if (!connection) {
                            return 'placeholder';
                        }
                        var span = connection.connection_span || connection.connectionSpan || null;
                        if (span && span.data) {
                            span = span.data;
                        }
                        if (span && span.state) {
                            return span.state;
                        }
                        if (connection.state) {
                            return connection.state;
                        }
                        return 'placeholder';
                    }

                    function formatDate(year, month, day) {
                        if (!year) return '';
                        var monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                        if (!month || month === 0) {
                            return String(year);
                        }
                        if (!day || day === 0) {
                            return monthNames[month - 1] + ' ' + year;
                        }
                        return monthNames[month - 1] + ' ' + day + ', ' + year;
                    }

                    function laneY(index) {
                        return marginTop + index * (laneHeight + laneSpacing);
                    }

                    var lanes = [];
                    var lifeLaneIndex = null;
                    var lifeStartFracGlobal = null; // for age calculations under mouse guide

                    // Shared tooltip element for this Konva card
                    var $tooltip = $('<div class="konva-timeline-tooltip"></div>').appendTo('body');

                    function showTooltip(html, evt) {
                        if (!evt) return;
                        var pageX = evt.pageX || 0;
                        var pageY = evt.pageY || 0;

                        $tooltip.html(html);

                        var offsetX = 8;
                        // Push tooltip further below the pointer so its top edge
                        // is clearly underneath the cursor hotspot.
                        var offsetY = 16;

                        // Measure after content set
                        var tooltipWidth = $tooltip.outerWidth();
                        var tooltipHeight = $tooltip.outerHeight();

                        // Position so the tooltip's top-right corner is just
                        // below and to the left of the mouse pointer.
                        var left = pageX - tooltipWidth - offsetX;
                        var top = pageY + offsetY;

                        var viewportWidth = window.innerWidth || document.documentElement.clientWidth;
                        var viewportHeight = window.innerHeight || document.documentElement.clientHeight;

                        // Clamp horizontally
                        if (left < 8) {
                            left = 8;
                        } else if (left + tooltipWidth + 8 > viewportWidth) {
                            left = Math.max(8, viewportWidth - tooltipWidth - 8);
                        }

                        // Clamp vertically (keep fully visible when near bottom)
                        if (top + tooltipHeight + 8 > viewportHeight) {
                            top = Math.max(8, viewportHeight - tooltipHeight - 8);
                        }

                        $tooltip.css({
                            left: left + 'px',
                            top: top + 'px',
                            display: 'block'
                        });
                    }

                    function hideTooltip() {
                        $tooltip.hide();
                    }

                    // Background swimlanes + register lanes (in group: x 0..contentTimelineWidth)
                    timelineData.forEach(function(swimlane, idx) {
                        var y = laneY(idx);
                        var bg = new Konva.Rect({
                            x: 0,
                            y: y,
                            width: contentTimelineWidth,
                            height: laneHeight,
                            fill: '#e9ecef',
                            strokeWidth: 0
                        });
                        backgroundGroup.add(bg);

                        var lane = {
                            index: idx,
                            type: swimlane.type === 'life' ? 'life' : (swimlane.connectionType || ''),
                            state: null,
                            bgRect: bg,
                            barRect: null,      // kept for backward compatibility (unused)
                            mainBarRect: null,  // primary bar rect for hit-testing
                            connection: swimlane.connection || null
                        };

                        if (lane.type === 'life') {
                            lifeLaneIndex = lanes.length;
                        }

                        lanes.push(lane);
                    });

                    var now = new Date();
                    var currentYear = now.getFullYear();
                    var currentMonth = now.getMonth() + 1;
                    var currentDay = now.getDate();
                    // Fractional "now" (for precise alignment of ongoing spans and the NOW marker)
                    var nowFrac = dateToFractionalYear(currentYear, currentMonth, currentDay);

                    // Arrow-headed bar: 45° corners meeting at a point at each end (pointer left/right)
                    var arrowSize = Math.min(5, (laneHeight - 4) / 2);
                    function arrowBarPoints(fullWidth, h) {
                        var as = arrowSize;
                        return [
                            0, h / 2,
                            as, h,
                            as + fullWidth, h,
                            fullWidth + 2 * as, h / 2,
                            as + fullWidth, 0,
                            as, 0
                        ];
                    }

                    // Bars (life + connections)
                    timelineData.forEach(function(swimlane, idx) {
                        var lane = lanes[idx];
                        var y = laneY(idx) + 2;
                        var rectHeight = laneHeight - 4;

                        if (swimlane.type === 'life') {
                            if (!subjectStartYear) {
                                return;
                            }

                            // Respect full precision for the life span: use earliest possible start
                            // and latest possible end (or "now" if ongoing).
                            var lifeStartFrac = fractionalYearFromStart(
                                subjectStartYear,
                                subjectStartMonth || 0,
                                subjectStartDay || 0
                            ) || subjectStartYear;

                            var lifeEndFrac;
                            if (subjectEndYear) {
                                lifeEndFrac = fractionalYearToEnd(
                                    subjectEndYear,
                                    subjectEndMonth || 0,
                                    subjectEndDay || 0
                                ) || subjectEndYear;
                            } else {
                                lifeEndFrac = nowFrac;
                            }

                            lifeStartFrac = Math.min(Math.max(lifeStartFrac, timeRange.start), timeRange.end);
                            lifeEndFrac = Math.min(Math.max(lifeEndFrac, lifeStartFrac), timeRange.end);

                            lifeStartFracGlobal = lifeStartFrac;

                            var x1 = xForYear(lifeStartFrac);
                            var x2 = xForYear(lifeEndFrac);
                            var lifeFullWidth = Math.max(2, x2 - x1);

                            var lifeBar = new Konva.Line({
                                x: x1 - arrowSize,
                                y: y,
                                points: arrowBarPoints(lifeFullWidth, rectHeight),
                                closed: true,
                                fill: '#ffffff',
                                stroke: '#e9ecef',
                                strokeWidth: 1,
                                opacity: 1
                            });

                            lifeBar.setAttr('startFrac', lifeStartFrac);
                            lifeBar.setAttr('endFrac', lifeEndFrac);
                            lane.barRects = [lifeBar];
                            lane.mainBarRect = lifeBar;
                            barGroup.add(lifeBar);

                            // Tooltip for life span
                            var lifeLabel = subjectName || 'Life';
                            var lifeStartLabel = formatDate(subjectStartYear, 0, 0);
                            var lifeEndLabel = subjectEndYear ? formatDate(subjectEndYear, 0, 0) : 'ongoing';
                            var lifeHtml = '<strong>' + lifeLabel + '</strong><br>' +
                                lifeStartLabel + ' - ' + lifeEndLabel;

                            // Per-bar tooltip handling is centralised in the stage-level
                            // mousemove handler, so these listeners are intentionally empty.
                            lifeBar.on('mousemove', function() {});
                            lifeBar.on('mouseout', function() {});

                            return;
                        }

                        var connection = swimlane.connection || null;
                        if (!connection) {
                            lane.state = 'placeholder';
                            return;
                        }

                        var connectionSpan = normaliseConnectionSpan(connection);
                        lane.state = deriveConnectionState(connection);

                        var hasStartYear = connectionSpan && connectionSpan.start_year;
                        var barColor;

                        var typeId = swimlane.connectionType || connection.connection_type_id || 'connection';
                        barColor = getConnectionColor(typeId);

                        // Default: no bars yet
                        lane.barRects = [];

                        if (hasStartYear) {
                            var startYear = connectionSpan.start_year;
                            var startMonth = connectionSpan.start_month || 0;
                            var startDay = connectionSpan.start_day || 0;

                            var rawEndYear = connectionSpan.end_year || null;
                            var rawEndMonth = connectionSpan.end_month || 0;
                            var rawEndDay = connectionSpan.end_day || 0;

                            // Treat spans without an end year as "ongoing" up to now,
                            // and cap any future-dated ends at today's date.
                            var effEndYear = rawEndYear || currentYear;
                            var effEndMonth = rawEndYear ? (rawEndMonth || 0) : currentMonth;
                            var effEndDay = rawEndYear ? (rawEndDay || 0) : currentDay;

                            // If we have an explicit end date beyond "now", cap it to today
                            if (rawEndYear) {
                                if (
                                    effEndYear > currentYear ||
                                    (effEndYear === currentYear && effEndMonth > currentMonth) ||
                                    (effEndYear === currentYear && effEndMonth === currentMonth && effEndDay > currentDay)
                                ) {
                                    effEndYear = currentYear;
                                    effEndMonth = currentMonth;
                                    effEndDay = currentDay;
                                }
                            }

                            // Fractional years for overall bar – use earliest possible start
                            // and latest possible end, so that imprecise spans are not "shrunk"
                            // when one side has finer precision than the other.
                            var startFrac = fractionalYearFromStart(startYear, startMonth, startDay) || startYear;
                            var endFrac = fractionalYearToEnd(effEndYear, effEndMonth, effEndDay) || effEndYear;

                            // Clamp to visible range
                            startFrac = Math.min(Math.max(startFrac, timeRange.start), timeRange.end);
                            endFrac = Math.min(Math.max(endFrac, startFrac), timeRange.end);

                            var xStart = xForYear(startFrac);
                            var xEnd = xForYear(endFrac);
                            var fullWidth = Math.max(2, xEnd - xStart);

                            var coreColour = barColor;

                            // Arrow-headed bar with white stroke
                            var barRect = new Konva.Line({
                                x: xStart - arrowSize,
                                y: y,
                                points: arrowBarPoints(fullWidth, rectHeight),
                                closed: true,
                                fill: coreColour,
                                opacity: 0.9,
                                stroke: '#e9ecef',
                                strokeWidth: 1
                            });

                            barRect.setAttr('startFrac', startFrac);
                            barRect.setAttr('endFrac', endFrac);
                            lane.barRects.push(barRect);
                            lane.mainBarRect = barRect;
                            barGroup.add(barRect);

                            // Phase spans (during) drawn inside the connection bar
                            var phaseSpans = swimlane.phaseSpans || [];
                            var phaseBarHeight = Math.max(4, rectHeight - 6);
                            var phaseArrowSize = Math.min(4, phaseBarHeight / 2);
                            var phaseY = y + (rectHeight - phaseBarHeight) / 2;
                            phaseSpans.forEach(function(phase) {
                                var pStart = fractionalYearFromStart(
                                    phase.start_year,
                                    phase.start_month || 0,
                                    phase.start_day || 0
                                ) || phase.start_year;
                                var pEnd = phase.end_year
                                    ? (fractionalYearToEnd(
                                        phase.end_year,
                                        phase.end_month || 0,
                                        phase.end_day || 0
                                    ) || phase.end_year)
                                    : nowFrac;
                                pStart = Math.min(Math.max(pStart, timeRange.start), timeRange.end);
                                pEnd = Math.min(Math.max(pEnd, pStart), timeRange.end);
                                var pxStart = xForYear(pStart);
                                var pxEnd = xForYear(pEnd);
                                var pWidth = Math.max(2, pxEnd - pxStart);
                                var phasePoints = [
                                    0, phaseBarHeight / 2,
                                    phaseArrowSize, phaseBarHeight,
                                    phaseArrowSize + pWidth, phaseBarHeight,
                                    pWidth + 2 * phaseArrowSize, phaseBarHeight / 2,
                                    phaseArrowSize + pWidth, 0,
                                    phaseArrowSize, 0
                                ];
                                var phaseBar = new Konva.Line({
                                    x: pxStart - phaseArrowSize,
                                    y: phaseY,
                                    points: phasePoints,
                                    closed: true,
                                    fill: coreColour,
                                    opacity: 0.5,
                                    stroke: '#e9ecef',
                                    strokeWidth: 1,
                                    listening: false
                                });
                                phaseBar.setAttr('startFrac', pStart);
                                phaseBar.setAttr('endFrac', pEnd);
                                phaseBar.setAttr('isPhase', true);
                                phaseBar.setAttr('phaseBarHeight', phaseBarHeight);
                                phaseBar.setAttr('phaseArrowSize', phaseArrowSize);
                                lane.barRects.push(phaseBar);
                                barGroup.add(phaseBar);
                            });

                            // Tooltip for dated connection
                            var otherSpan = connection.other_span || connection.otherSpan || {};
                            var predicate = connection.predicate || '';
                            var title;
                            if (predicate) {
                                title = predicate + ' ' + (otherSpan.name || '');
                            } else {
                                title = otherSpan.name || '';
                            }

                            var startLabel = formatDate(
                                connectionSpan.start_year,
                                connectionSpan.start_month || 0,
                                connectionSpan.start_day || 0
                            );
                            var endLabel;
                            if (!connectionSpan.end_year) {
                                // Show "now" as the current end date for ongoing spans
                                endLabel = formatDate(effEndYear, effEndMonth, effEndDay) + ' (ongoing)';
                            } else {
                                endLabel = formatDate(
                                    connectionSpan.end_year,
                                    connectionSpan.end_month || 0,
                                    connectionSpan.end_day || 0
                                );
                            }

                            var connHtml = '<strong>' + title + '</strong><br>' +
                                startLabel + ' - ' + endLabel;

                            barRect.on('mousemove', function() {});
                            barRect.on('mouseout', function() {});
                        } else {
                            // Placeholder / unknown dates: faint arrow-headed bar across full visible range
                            var barX = xForYear(timeRange.start);
                            var barWidth = Math.max(2, xForYear(timeRange.end) - barX);
                            var placeholderRect = new Konva.Line({
                                x: barX - arrowSize,
                                y: y,
                                points: arrowBarPoints(barWidth, rectHeight),
                                closed: true,
                                fill: barColor,
                                opacity: 0.25
                            });
                            placeholderRect.setAttr('startFrac', timeRange.start);
                            placeholderRect.setAttr('endFrac', timeRange.end);
                            lane.barRects.push(placeholderRect);
                            lane.mainBarRect = lane.mainBarRect || placeholderRect;
                            barGroup.add(placeholderRect);

                            // Tooltip for placeholder / unknown dates
                            var otherSpan2 = connection.other_span || connection.otherSpan || {};
                            var predicate2 = connection.predicate || '';
                            var title2;
                            if (predicate2) {
                                title2 = predicate2 + ' ' + (otherSpan2.name || '');
                            } else {
                                title2 = otherSpan2.name || '';
                            }

                            var placeholderHtml = '<strong>' + title2 + '</strong><br>' +
                                '<em>Dates unknown</em>';

                            placeholderRect.on('mousemove', function() {});
                            placeholderRect.on('mouseout', function() {});
                        }
                    });

                    // Simple horizontal year axis at the bottom
                    var axisY = height - marginBottom + 10;

                    var axisLine = new Konva.Line({
                        points: [0, axisY, contentTimelineWidth, axisY],
                        stroke: '#666666',
                        strokeWidth: 1
                    });
                    axisGroup.add(axisLine);

                    // Tick marks on clean 5-year boundaries (…, 1990, 1995, 2000, 2005, …)
                    var tickInterval = 5;

                    var startYear = timeRange.start;
                    var endYear = timeRange.end;

                    var ticks = [];

                    // Find first tick aligned to a 5-year boundary within the visible range
                    var firstTickYear = Math.ceil(startYear / tickInterval) * tickInterval;
                    for (var year = firstTickYear; year <= endYear; year += tickInterval) {
                        var x = xForYear(year);

                        // Vertical year gridline from top to axis (slightly more visible)
                        var gridLine = new Konva.Line({
                            points: [x, marginTop, x, axisY],
                            stroke: '#ced4da',
                            strokeWidth: 1,
                            opacity: 0.45
                        });
                        backgroundGroup.add(gridLine);

                        var tickLine = new Konva.Line({
                            points: [x, axisY, x, axisY + 6],
                            stroke: '#666666',
                            strokeWidth: 1
                        });

                        var tickLabel = new Konva.Text({
                            x: x - 12,
                            y: axisY + 8,
                            text: String(year),
                            fontSize: 10,
                            fill: '#666666'
                        });

                        axisGroup.add(tickLine);
                        axisGroup.add(tickLabel);

                        ticks.push({ line: tickLine, label: tickLabel, grid: gridLine, year: year });
                    }

                    // "Now" marker if current moment is in range (fractional year)
                    var nowLine = null;
                    var nowLabel = null;
                    if (nowFrac >= startYear && nowFrac <= endYear) {
                        var nowX = xForYear(nowFrac);

                        nowLine = new Konva.Line({
                            points: [nowX, marginTop, nowX, axisY],
                            stroke: '#dc3545',
                            strokeWidth: 1,
                            dash: [4, 4]
                        });

                        nowLabel = new Konva.Text({
                            x: nowX - 20,
                            y: marginTop - 14,
                            width: 40,
                            align: 'center',
                            text: 'NOW',
                            fontSize: 10,
                            fontStyle: 'bold',
                            fill: '#dc3545'
                        });

                        axisGroup.add(nowLine);
                        axisGroup.add(nowLabel);
                    }

                    // Lifespan start marker (subject start date, respecting precision), if in range
                    var lifeStartLine = null;
                    var lifeStartLabel = null;
                    var lifeStartFracMarkerStored = null;
                    if (subjectStartYear && subjectStartYear >= startYear && subjectStartYear <= endYear) {
                        lifeStartFracMarkerStored = fractionalYearFromStart(
                            subjectStartYear,
                            subjectStartMonth || 0,
                            subjectStartDay || 0
                        ) || subjectStartYear;
                        var lifeStartX = xForYear(lifeStartFracMarkerStored);

                        lifeStartLine = new Konva.Line({
                            points: [lifeStartX, marginTop, lifeStartX, axisY],
                            stroke: '#0d6efd',
                            strokeWidth: 1,
                            dash: [4, 4]
                        });

                        lifeStartLabel = new Konva.Text({
                            x: lifeStartX - 20,
                            y: marginTop - 28,
                            width: 40,
                            align: 'center',
                            text: String(subjectStartYear),
                            fontSize: 10,
                            fontStyle: 'bold',
                            fill: '#0d6efd'
                        });

                        axisGroup.add(lifeStartLine);
                        axisGroup.add(lifeStartLabel);
                    }

                    // Mouse-following vertical guide line (subtle)
                    var mouseGuideLine = new Konva.Line({
                        points: [0, marginTop, 0, axisY],
                        stroke: '#999999',
                        strokeWidth: 1,
                        dash: [2, 2],
                        opacity: 0.5,
                        visible: false
                    });
                    axisGroup.add(mouseGuideLine);

                    // Age label centered above the guide line (only when within lifespan)
                    var mouseGuideLabel = new Konva.Text({
                        x: -20,
                        y: marginTop - 14,
                        width: 40,
                        align: 'center',
                        text: '',
                        fontSize: 10,
                        fontStyle: 'bold',
                        fill: '#333333',
                        visible: false
                    });
                    axisGroup.add(mouseGuideLabel);

                    function updateTimelinePositions() {
                        pixelsPerYear = contentTimelineWidth / yearSpan;
                        lanes.forEach(function(lane) {
                            if (lane.bgRect) {
                                lane.bgRect.width(contentTimelineWidth);
                            }
                            if (lane.barRects && lane.barRects.length) {
                                var rectHeight = laneHeight - 4;
                                lane.barRects.forEach(function(bar) {
                                    var startFrac = bar.getAttr('startFrac');
                                    var endFrac = bar.getAttr('endFrac');
                                    if (startFrac != null && endFrac != null) {
                                        var xStart = xForYear(startFrac);
                                        var xEnd = xForYear(endFrac);
                                        var fullWidth = Math.max(2, xEnd - xStart);
                                        if (bar.getAttr('isPhase')) {
                                            var ph = bar.getAttr('phaseBarHeight');
                                            var pas = bar.getAttr('phaseArrowSize');
                                            if (ph != null && pas != null) {
                                                bar.x(xStart - pas);
                                                bar.points([
                                                    0, ph / 2,
                                                    pas, ph,
                                                    pas + fullWidth, ph,
                                                    fullWidth + 2 * pas, ph / 2,
                                                    pas + fullWidth, 0,
                                                    pas, 0
                                                ]);
                                            }
                                        } else {
                                            bar.x(xStart - arrowSize);
                                            bar.points(arrowBarPoints(fullWidth, rectHeight));
                                        }
                                    }
                                });
                            }
                        });
                        axisLine.points([0, axisY, contentTimelineWidth, axisY]);
                        ticks.forEach(function(t) {
                            var x = xForYear(t.year);
                            t.line.points([x, axisY, x, axisY + 6]);
                            t.label.x(x - 12);
                            t.label.y(axisY + 8);
                            if (t.grid) {
                                t.grid.points([x, marginTop, x, axisY]);
                            }
                        });
                        if (nowLine && nowFrac >= startYear && nowFrac <= endYear) {
                            var nowX = xForYear(nowFrac);
                            nowLine.points([nowX, marginTop, nowX, axisY]);
                            nowLabel.x(nowX - 20);
                        }
                        if (lifeStartLine && lifeStartFracMarkerStored != null) {
                            var lsX = xForYear(lifeStartFracMarkerStored);
                            lifeStartLine.points([lsX, marginTop, lsX, axisY]);
                            lifeStartLabel.x(lsX - 20);
                        }
                        backgroundLayer.batchDraw();
                        barLayer.batchDraw();
                        axisLayer.batchDraw();
                    }

                    backgroundLayer.draw();
                    barLayer.draw();
                    axisLayer.draw();

                    // ---- Filtering & layout (inspired by existing D3 behaviour) ----
                    var $buttons = $root.find('.connection-type-filter-buttons a');
                    var $layoutRadios = $root.find('input[name="all-connections-layout"]');
                    var $stateFilter = $root.find('.state-filter');

                    var $allButton = $buttons.filter('[data-connection-type="all"]');
                    var $typeButtons = $buttons.not($allButton);

                    var allTypes = new Set(
                        $typeButtons
                            .map(function() { return $(this).data('connection-type'); })
                            .get()
                    );

                    var activeTypes;
                    if (initialType && initialType !== 'all' && allTypes.has(initialType)) {
                        activeTypes = new Set([initialType]);
                    } else {
                        activeTypes = new Set(allTypes);
                    }

                    var layoutMode = 'expanded'; // 'expanded' | 'grouped' | 'collapsed'
                    var stateFilter = 'all';

                    function updateButtonStates() {
                        $typeButtons.each(function() {
                            var $btn = $(this);
                            var type = $btn.data('connection-type');
                            if (activeTypes.has(type)) {
                                $btn.addClass('active').removeClass('connection-type-inactive');
                            } else {
                                $btn.removeClass('active').addClass('connection-type-inactive');
                            }
                        });

                        if (activeTypes.size === allTypes.size) {
                            $allButton.addClass('active');
                        } else {
                            $allButton.removeClass('active');
                        }
                    }

                    function applyFilters() {
                        var showAllTypes = activeTypes.size === allTypes.size;
                        var visibleLanes = [];

                        lanes.forEach(function(lane) {
                            var visible = true;

                            if (lane.type !== 'life') {
                                if (!showAllTypes && !activeTypes.has(lane.type)) {
                                    visible = false;
                                }
                                if (stateFilter !== 'all' && lane.state && lane.state !== stateFilter) {
                                    visible = false;
                                }
                            }

                            lane.visible = visible;
                            if (visible) {
                                visibleLanes.push(lane);
                            }

                            lane.bgRect.visible(visible);
                            if (lane.barRects && lane.barRects.length) {
                                lane.barRects.forEach(function(r) { r.visible(visible); });
                            }
                        });

                        // Helper to animate a lane's background and all of its bars to a new Y position
                        function animateLaneToY(lane, targetBaseY) {
                            lane.bgRect.to({
                                y: targetBaseY,
                                duration: 0.25,
                                easing: Konva.Easings.EaseInOut
                            });
                            if (lane.barRects && lane.barRects.length) {
                                var rectHeight = laneHeight - 4;
                                var phaseBarHeight = Math.max(4, rectHeight - 6);
                                var phaseYInset = (rectHeight - phaseBarHeight) / 2;
                                lane.barRects.forEach(function(r) {
                                    var yTarget = r.getAttr('isPhase')
                                        ? targetBaseY + 2 + phaseYInset
                                        : targetBaseY + 2;
                                    r.to({
                                        y: yTarget,
                                        duration: 0.25,
                                        easing: Konva.Easings.EaseInOut
                                    });
                                });
                            }
                        }

                        // Layout
                        if (layoutMode === 'collapsed') {
                            var baseY;
                            if (lifeLaneIndex !== null && lanes[lifeLaneIndex]) {
                                baseY = lanes[lifeLaneIndex].bgRect.y();
                            } else {
                                baseY = marginTop;
                            }

                            visibleLanes.forEach(function(lane) {
                                animateLaneToY(lane, baseY);
                            });

                            height = marginTop + laneHeight + marginBottom;
                        } else if (layoutMode === 'grouped') {
                            // Group all visible lanes by connection type (plus a separate lane for "life"),
                            // and stack those groups vertically. All connections of the same type share
                            // the same Y position.
                            var groupOrder = [];
                            var groupIndexByType = {};

                            visibleLanes.forEach(function(lane) {
                                var key = lane.type || 'other';
                                if (groupIndexByType[key] === undefined) {
                                    groupIndexByType[key] = groupOrder.length;
                                    groupOrder.push(key);
                                }
                            });

                            groupOrder.forEach(function(groupKey, groupIdx) {
                                var y = laneY(groupIdx);
                                visibleLanes.forEach(function(lane) {
                                    if (lane.type !== groupKey) {
                                        return;
                                    }
                                    animateLaneToY(lane, y);
                                });
                            });

                            height = marginTop + groupOrder.length * (laneHeight + laneSpacing) + marginBottom;
                        } else {
                            visibleLanes.forEach(function(lane, idx) {
                                var y = laneY(idx);
                                animateLaneToY(lane, y);
                            });

                            height = marginTop + visibleLanes.length * (laneHeight + laneSpacing) + marginBottom;
                        }

                        // Update stage & container sizes
                        stage.height(height);
                        container.style.height = height + 'px';

                        applyZoomPan();

                        // Reposition axis and ticks (x in content coords)
                        axisY = height - marginBottom + 10;
                        axisLine.points([0, axisY, contentTimelineWidth, axisY]);

                        ticks.forEach(function(t) {
                            var x = xForYear(t.year);
                            t.line.points([x, axisY, x, axisY + 6]);
                            t.label.x(x - 12);
                            t.label.y(axisY + 8);
                            if (t.grid) {
                                t.grid.points([x, marginTop, x, axisY]);
                            }
                        });

                        if (nowLine) {
                            var nowPts = nowLine.points();
                            var nowX = nowPts[0];
                            nowLine.points([nowX, marginTop, nowX, axisY]);
                        }

                        if (lifeStartLine) {
                            var lsPts = lifeStartLine.points();
                            var lsX = lsPts[0];
                            lifeStartLine.points([lsX, marginTop, lsX, axisY]);
                            if (lifeStartLabel) {
                                lifeStartLabel.y(marginTop - 28);
                            }
                        }

                        // Keep the mouse guide line spanning from the top margin to the axis
                        if (mouseGuideLine) {
                            var mgPts = mouseGuideLine.points();
                            var mgX = mgPts[0];
                            mouseGuideLine.points([mgX, marginTop, mgX, axisY]);

                            if (mouseGuideLabel && mouseGuideLabel.visible()) {
                                mouseGuideLabel.y(marginTop - 14);
                            }
                        }

                        // Ensure any previously visible tooltip is hidden after layout/filter changes,
                        // so hover areas always correspond exactly to the currently visible bars.
                        hideTooltip();

                        backgroundLayer.batchDraw();
                        barLayer.batchDraw();
                        axisLayer.batchDraw();
                    }

                    updateButtonStates();
                    applyFilters();

                    // Button handlers
                    $allButton.on('click', function(e) {
                        if (e.which !== 1 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) {
                            return;
                        }
                        e.preventDefault();

                        activeTypes = new Set(allTypes);
                        updateButtonStates();
                        applyFilters();
                    });

                    // Distinguish between single-click (isolate) and double-click (toggle)
                    var clickTimeout = null;

                    $typeButtons.on('click', function(e) {
                        if (e.which !== 1 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) {
                            return;
                        }
                        e.preventDefault();

                        var type = $(this).data('connection-type');

                        clearTimeout(clickTimeout);
                        clickTimeout = setTimeout(function() {
                            // Single click: isolate this type only
                            activeTypes = new Set([type]);
                            updateButtonStates();
                            applyFilters();
                        }, 250);
                    });

                    $typeButtons.on('dblclick', function(e) {
                        if (e.which !== 1 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) {
                            return;
                        }
                        e.preventDefault();

                        clearTimeout(clickTimeout);

                        var type = $(this).data('connection-type');

                        // Double click: toggle this type in/out of the active set
                        if (activeTypes.has(type)) {
                            activeTypes.delete(type);
                        } else {
                            activeTypes.add(type);
                        }

                        updateButtonStates();
                        applyFilters();
                    });

                    $layoutRadios.on('change', function() {
                        var selected = $(this).val();
                        if (selected === 'expanded' || selected === 'grouped' || selected === 'collapsed') {
                            layoutMode = selected;
                            applyFilters();
                        }
                    });

                    if ($stateFilter.length) {
                        $stateFilter.on('change', function() {
                            stateFilter = $(this).val();
                            applyFilters();
                        });
                    }

                    var zoomTweenDurationMs = 250;
                    var $zoomButtons = $root.find('.konva-timeline-zoom-buttons button');
                    $zoomButtons.first().on('click', function() {
                        var oldScale = zoomScale;
                        var newScale = Math.max(zoomMin, zoomScale / 1.25);
                        var centerContentX = panX + viewportWidth / 2;
                        var centerContentXNew = centerContentX * (newScale / oldScale);
                        var targetPanX = centerContentXNew - viewportWidth / 2;
                        tweenZoomPan(newScale, targetPanX, zoomTweenDurationMs);
                    });
                    $root.find('.konva-timeline-zoom-reset').on('click', function() {
                        tweenZoomPan(1, 0, zoomTweenDurationMs);
                    });
                    $zoomButtons.last().on('click', function() {
                        var oldScale = zoomScale;
                        var newScale = Math.min(zoomMax, zoomScale * 1.25);
                        var centerContentX = panX + viewportWidth / 2;
                        var centerContentXNew = centerContentX * (newScale / oldScale);
                        var targetPanX = centerContentXNew - viewportWidth / 2;
                        tweenZoomPan(newScale, targetPanX, zoomTweenDurationMs);
                    });

                    stage.on('dblclick', function(evt) {
                        var target = evt.target;
                        if (!target || target.getAttr('startFrac') == null) {
                            return;
                        }
                        var startFrac = target.getAttr('startFrac');
                        var endFrac = target.getAttr('endFrac');
                        if (startFrac == null || endFrac == null) {
                            return;
                        }
                        var spanYearWidth = Math.max(endFrac - startFrac, 0.1);
                        var targetSpanViewportFraction = 0.6;
                        zoomScale = Math.min(zoomMax, Math.max(zoomMin, targetSpanViewportFraction * yearSpan / spanYearWidth));
                        contentTimelineWidth = viewportWidth * zoomScale;
                        var xStart = (startFrac - timeRange.start) / yearSpan * contentTimelineWidth;
                        var xEnd = (endFrac - timeRange.start) / yearSpan * contentTimelineWidth;
                        var spanCenter = (xStart + xEnd) / 2;
                        panX = spanCenter - viewportWidth / 2;
                        panX = Math.max(0, Math.min(panX, contentTimelineWidth - viewportWidth));
                        applyZoomPan();

                        var holeX = xStart - arrowSize;
                        var holeY = target.y();
                        var holeW = (xEnd - xStart) + 2 * arrowSize;
                        var holeH = laneHeight - 4;
                        dimShape.setAttr('holeX', holeX);
                        dimShape.setAttr('holeY', holeY);
                        dimShape.setAttr('holeW', holeW);
                        dimShape.setAttr('holeH', holeH);
                        dimShape.visible(true);
                        barLayer.batchDraw();
                    });

                    stage.on('click', function(evt) {
                        if (dimShape.visible() && (!evt.target || evt.target.getAttr('startFrac') == null)) {
                            dimShape.visible(false);
                            barLayer.batchDraw();
                        }
                    });

                    function fractionalYearFromContentX(contentX) {
                        var t = contentX / Math.max(1, contentTimelineWidth);
                        t = Math.min(Math.max(t, 0), 1);
                        return timeRange.start + t * yearSpan;
                    }

                    // Stage-level mouse handlers to move the vertical guide line with the cursor
                    stage.on('mousemove', function(evt) {
                        if (!mouseGuideLine) {
                            return;
                        }
                        var pos = stage.getPointerPosition();
                        if (!pos) {
                            return;
                        }
                        // Pointer to content coords (timeline group is at marginLeft - panX)
                        var contentX = pos.x - marginLeft + panX;
                        if (contentX < 0 || contentX > contentTimelineWidth) {
                            mouseGuideLine.visible(false);
                            if (mouseGuideLabel) {
                                mouseGuideLabel.visible(false);
                            }
                            axisLayer.batchDraw();
                            return;
                        }

                        mouseGuideLine.points([contentX, marginTop, contentX, axisY]);
                        mouseGuideLine.visible(true);

                        if (lifeStartFracGlobal !== null && mouseGuideLabel) {
                            var fracAtMouse = fractionalYearFromContentX(contentX);
                            var ageYears = fracAtMouse - lifeStartFracGlobal;
                            if (ageYears >= 0) {
                                var ageInt = Math.floor(ageYears);
                                mouseGuideLabel.text(ageInt + 'y');
                                mouseGuideLabel.x(contentX - mouseGuideLabel.width() / 2);
                                mouseGuideLabel.y(marginTop - 14);
                                mouseGuideLabel.visible(true);
                            } else {
                                mouseGuideLabel.visible(false);
                            }
                        }

                        // Show stacked tooltips for all bars under the guide (hit-test in stage coords)
                        var htmlParts = [];
                        lanes.forEach(function(lane) {
                            if (!lane.visible || !lane.mainBarRect) {
                                return;
                            }
                            var rect = lane.mainBarRect;
                            var cr = rect.getClientRect();
                            var bx = cr.x;
                            var by = cr.y;
                            var bw = cr.width;
                            var bh = cr.height;
                            if (pos.x >= bx && pos.x <= bx + bw && pos.y >= by && pos.y <= by + bh) {
                                var conn = lane.connection;
                                var isLife = lane.type === 'life';
                                var title = '';
                                var detail = '';

                                if (isLife) {
                                    var lifeLabel = subjectName || 'Life';
                                    var lifeStartLabel = formatDate(subjectStartYear, subjectStartMonth || 0, subjectStartDay || 0);
                                    var lifeEndLabel = subjectEndYear
                                        ? formatDate(subjectEndYear, subjectEndMonth || 0, subjectEndDay || 0)
                                        : 'ongoing';
                                    title = lifeLabel;
                                    detail = lifeStartLabel + ' - ' + lifeEndLabel;
                                } else if (conn) {
                                    var otherSpan = conn.other_span || conn.otherSpan || {};
                                    var predicate = conn.predicate || '';
                                    if (predicate) {
                                        title = predicate + ' ' + (otherSpan.name || '');
                                    } else {
                                        title = otherSpan.name || '';
                                    }

                                    var cSpan = conn.connection_span || conn.connectionSpan || null;
                                    if (cSpan && cSpan.data) {
                                        cSpan = cSpan.data;
                                    }
                                    if (cSpan && cSpan.start_year) {
                                        var startLabel2 = formatDate(
                                            cSpan.start_year,
                                            cSpan.start_month || 0,
                                            cSpan.start_day || 0
                                        );
                                        var endLabel2;
                                        if (!cSpan.end_year) {
                                            endLabel2 = 'ongoing';
                                        } else {
                                            endLabel2 = formatDate(
                                                cSpan.end_year,
                                                cSpan.end_month || 0,
                                                cSpan.end_day || 0
                                            );
                                        }
                                        detail = startLabel2 + ' - ' + endLabel2;
                                    } else {
                                        detail = 'Dates unknown';
                                    }
                                }

                                if (title) {
                                    htmlParts.push(
                                        '<div style="margin-bottom:4px;">' +
                                            '<strong>' + title + '</strong><br>' +
                                            '<span>' + detail + '</span>' +
                                        '</div>'
                                    );
                                }
                            }
                        });

                        if (htmlParts.length && evt && evt.evt) {
                            container.style.cursor = 'pointer';
                            $tooltip.html(htmlParts.join(''));
                            showTooltip($tooltip.html(), evt.evt);
                        } else {
                            container.style.cursor = '';
                            hideTooltip();
                        }

                        axisLayer.batchDraw();
                    });

                    stage.on('mouseleave', function() {
                        if (!mouseGuideLine) {
                            return;
                        }
                        mouseGuideLine.visible(false);
                        if (mouseGuideLabel) {
                            mouseGuideLabel.visible(false);
                        }
                        container.style.cursor = '';
                        hideTooltip();
                        axisLayer.batchDraw();
                    });
                });
            });
        })(jQuery);
    </script>
@endpush

