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

    // Add individual connection swimlanes (single chronological order)
    foreach ($connectionsCollection as $connection) {
        $timelineData[] = [
            'type' => 'connection',
            'connectionType' => $connection->connection_type_id ?? $connection->connection_type->type ?? 'connection',
            'connection' => $connection,
            'label' => $connection->other_span->name ?? '',
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
                Lifespan (Konva)
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
                    <label class="btn btn-outline-secondary" for="konva-all-connections-layout-expanded" title="Expanded">
                        <i class="bi bi-arrows-expand"></i>
                    </label>
                    <input type="radio" class="btn-check" name="all-connections-layout" id="konva-all-connections-layout-collapsed" value="collapsed">
                    <label class="btn btn-outline-secondary" for="konva-all-connections-layout-collapsed" title="Collapsed">
                        <i class="bi bi-arrows-collapse"></i>
                    </label>
                </div>
                <select class="form-select form-select-sm state-filter" style="width: auto; display: inline-block;" title="Filter by connection state">
                    <option value="all">All States</option>
                    <option value="placeholder">Placeholders Only</option>
                    <option value="draft">Drafts Only</option>
                    <option value="complete">Complete Only</option>
                </select>
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
                    var laneHeight = 20;
                    var laneSpacing = 8;

                    var timelineData = @json($timelineData);
                    var timeRange = @json($timeRange);
                    var subjectStartYear = {{ $subject->start_year ?? 'null' }};
                    var subjectEndYear = {{ $subject->end_year ?? 'null' }};
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

                    // Debug logging to understand where the effective width comes from
                    console.log('[Konva all-connections width debug]', {
                        rootWidth: rootWidth,
                        containerClientWidth: containerClientWidth,
                        containerRectWidth: containerRect.width,
                        containerIdWidth: $('#' + containerId).width(),
                        chosenStageWidth: width
                    });
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

                    var yearSpan = Math.max(1, (timeRange.end - timeRange.start) || 1);
                    // Use the width between left and right margins for the time scale
                    var usableWidth = Math.max(50, width - marginLeft - marginRight);
                    var pixelsPerYear = usableWidth / yearSpan;

                    function xForYear(year) {
                        return marginLeft + (year - timeRange.start) * pixelsPerYear;
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

                    // Convert year/month/day to fractional year for positioning
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

                    // Shared tooltip element for this Konva card
                    var $tooltip = $('<div class="konva-timeline-tooltip"></div>').appendTo('body');

                    function showTooltip(html, evt) {
                        if (!evt || !evt.evt) return;
                        var pageX = evt.evt.pageX || 0;
                        var pageY = evt.evt.pageY || 0;

                        $tooltip.html(html);

                        var offsetX = 12;
                        var offsetY = -12;
                        var left = pageX + offsetX;
                        var top = pageY + offsetY;

                        // Basic viewport clamp so tooltip doesn't fall off the right edge
                        var viewportWidth = window.innerWidth || document.documentElement.clientWidth;
                        var tooltipWidth = $tooltip.outerWidth();
                        if (left + tooltipWidth + 8 > viewportWidth) {
                            left = Math.max(8, viewportWidth - tooltipWidth - 8);
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

                    // Background swimlanes + register lanes
                    timelineData.forEach(function(swimlane, idx) {
                        var y = laneY(idx);
                        var bg = new Konva.Rect({
                            // Align swimlane backgrounds with the date axis scale:
                            // same left margin as the axis, and extend to the same right edge.
                            x: marginLeft,
                            y: y,
                            width: width - marginLeft - marginRight,
                            height: laneHeight,
                            fill: '#f8f9fa',
                            stroke: '#dee2e6',
                            strokeWidth: 1
                        });
                        backgroundLayer.add(bg);

                        var lane = {
                            index: idx,
                            type: swimlane.type === 'life' ? 'life' : (swimlane.connectionType || ''),
                            state: null,
                            bgRect: bg,
                            barRect: null,
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

                    // Bars (life + connections)
                    timelineData.forEach(function(swimlane, idx) {
                        var lane = lanes[idx];
                        var y = laneY(idx) + 2;
                        var rectHeight = laneHeight - 4;

                        if (swimlane.type === 'life') {
                            if (!subjectStartYear) {
                                return;
                            }

                            // If the life has no end year, treat it as ongoing to "now"
                            var lifeEndFrac;
                            if (subjectEndYear) {
                                lifeEndFrac = subjectEndYear;
                            } else {
                                lifeEndFrac = nowFrac;
                            }

                            lifeEndFrac = Math.min(Math.max(lifeEndFrac, timeRange.start), timeRange.end);
                            var startYear = Math.min(Math.max(subjectStartYear, timeRange.start), timeRange.end);

                            var x1 = xForYear(startYear);
                            var x2 = xForYear(lifeEndFrac);

                            var lifeBar = new Konva.Rect({
                                x: x1,
                                y: y,
                                width: Math.max(2, x2 - x1),
                                height: rectHeight,
                                fill: '#000000',
                                opacity: 0.9
                            });

                            lane.barRects = [lifeBar];
                            barLayer.add(lifeBar);

                            // Tooltip for life span
                            var lifeLabel = subjectName || 'Life';
                            var lifeStartLabel = formatDate(subjectStartYear, 0, 0);
                            var lifeEndLabel = subjectEndYear ? formatDate(subjectEndYear, 0, 0) : 'ongoing';
                            var lifeHtml = '<strong>' + lifeLabel + '</strong><br>' +
                                lifeStartLabel + ' - ' + lifeEndLabel;

                            lifeBar.on('mousemove', function(evt) {
                                showTooltip(lifeHtml, evt);
                            });
                            lifeBar.on('mouseout', function() {
                                hideTooltip();
                            });

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

                            // Fractional years for overall bar
                            var startFrac = dateToFractionalYear(startYear, startMonth, startDay) || startYear;
                            var endFrac = dateToFractionalYear(effEndYear, effEndMonth, effEndDay) || effEndYear;

                            // Clamp to visible range
                            startFrac = Math.min(Math.max(startFrac, timeRange.start), timeRange.end);
                            endFrac = Math.min(Math.max(endFrac, startFrac), timeRange.end);

                            var xStart = xForYear(startFrac);
                            var xEnd = xForYear(endFrac);
                            var fullWidth = Math.max(2, xEnd - xStart);

                            // Precision windows (in years) for each side
                            var leftWindowYears = precisionWindowYears(startMonth, startDay);
                            // Use the stored end precision (month/day) for the right-hand gradient.
                            // If the span is ongoing (no stored end year, effectively ending "now"),
                            // treat the end as day-precise and do NOT add a gradient.
                            var isOngoing = !connectionSpan.end_year;
                            var rightWindowYears = isOngoing ? 0 : precisionWindowYears(rawEndMonth, rawEndDay);

                            var leftPx = leftWindowYears > 0 ? leftWindowYears * pixelsPerYear : 0;
                            var rightPx = rightWindowYears > 0 ? rightWindowYears * pixelsPerYear : 0;

                            // Do not let gradients consume the whole bar
                            var maxSide = fullWidth / 2;
                            leftPx = Math.min(leftPx, maxSide);
                            rightPx = Math.min(rightPx, maxSide);

                            // If the gradients would eat the whole bar, collapse to solid
                            if (leftPx + rightPx >= fullWidth) {
                                leftPx = 0;
                                rightPx = 0;
                            }

                            var coreColour = barColor;
                            var transparentColour = makeRgba(barColor, 0);
                            var opaqueColour = makeRgba(barColor, 1);

                            // Single rect with horizontal gradient that encodes both fuzzy ends and crisp middle
                            var colourStops = [];

                            // Left side
                            if (leftPx > 0) {
                                var leftStop = leftPx / fullWidth;
                                colourStops.push(
                                    0, transparentColour,
                                    leftStop, opaqueColour
                                );
                            } else {
                                // No left fuzz: start opaque
                                colourStops.push(0, opaqueColour);
                            }

                            // Right side
                            if (rightPx > 0) {
                                var rightStart = (fullWidth - rightPx) / fullWidth;
                                // Ensure we don't go backwards
                                if (rightStart < 0) rightStart = 0;
                                if (rightStart > 1) rightStart = 1;

                                // Keep centre solid: stays opaque until rightStart
                                colourStops.push(
                                    rightStart, opaqueColour,
                                    1, transparentColour
                                );
                            } else {
                                // No right fuzz: stay opaque to end
                                colourStops.push(1, opaqueColour);
                            }

                            var barRect = new Konva.Rect({
                                x: xStart,
                                y: y,
                                width: fullWidth,
                                height: rectHeight,
                                fill: coreColour,
                                fillPriority: 'linear-gradient',
                                fillLinearGradientStartPoint: { x: 0, y: 0 },
                                fillLinearGradientEndPoint: { x: fullWidth, y: 0 },
                                fillLinearGradientColorStops: colourStops
                            });

                            lane.barRects.push(barRect);
                            barLayer.add(barRect);

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
                            if (isOngoing) {
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

                            barRect.on('mousemove', function(evt) {
                                showTooltip(connHtml, evt);
                            });
                            barRect.on('mouseout', function() {
                                hideTooltip();
                            });
                        } else {
                            // Placeholder / unknown dates: simple faint bar across full visible range
                            var barX = xForYear(timeRange.start);
                            var barWidth = xForYear(timeRange.end) - barX;
                            var placeholderRect = new Konva.Rect({
                                x: barX,
                                y: y,
                                width: barWidth,
                                height: rectHeight,
                                fill: barColor,
                                opacity: 0.25
                            });
                            lane.barRects.push(placeholderRect);
                            barLayer.add(placeholderRect);

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

                            placeholderRect.on('mousemove', function(evt) {
                                showTooltip(placeholderHtml, evt);
                            });
                            placeholderRect.on('mouseout', function() {
                                hideTooltip();
                            });
                        }
                    });

                    // Simple horizontal year axis at the bottom
                    var axisY = height - marginBottom + 10;

                    var axisLine = new Konva.Line({
                        points: [marginLeft, axisY, width - marginRight, axisY],
                        stroke: '#666666',
                        strokeWidth: 1
                    });
                    axisLayer.add(axisLine);

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
                        backgroundLayer.add(gridLine);

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

                        axisLayer.add(tickLine);
                        axisLayer.add(tickLabel);

                        ticks.push({ line: tickLine, label: tickLabel, grid: gridLine });
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

                        axisLayer.add(nowLine);
                        axisLayer.add(nowLabel);
                    }

                    // Lifespan start marker (subject start year), if in range
                    var lifeStartLine = null;
                    var lifeStartLabel = null;
                    if (subjectStartYear && subjectStartYear >= startYear && subjectStartYear <= endYear) {
                        var lifeStartX = xForYear(subjectStartYear);

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

                        axisLayer.add(lifeStartLine);
                        axisLayer.add(lifeStartLabel);
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

                    var layoutMode = 'expanded'; // 'expanded' | 'collapsed'
                    var stateFilter = 'all';

                    function updateButtonStates() {
                        $typeButtons.each(function() {
                            var $btn = $(this);
                            var type = $btn.data('connection-type');
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

                        // Layout
                        if (layoutMode === 'collapsed') {
                            var baseY;
                            if (lifeLaneIndex !== null && lanes[lifeLaneIndex]) {
                                baseY = lanes[lifeLaneIndex].bgRect.y();
                            } else {
                                baseY = marginTop;
                            }

                            visibleLanes.forEach(function(lane) {
                                lane.bgRect.y(baseY);
                                if (lane.barRects && lane.barRects.length) {
                                    lane.barRects.forEach(function(r) {
                                        r.y(baseY + 2);
                                    });
                                }
                            });

                            height = marginTop + laneHeight + marginBottom;
                        } else {
                            visibleLanes.forEach(function(lane, idx) {
                                var y = laneY(idx);
                                lane.bgRect.y(y);
                                if (lane.barRects && lane.barRects.length) {
                                    lane.barRects.forEach(function(r) {
                                        r.y(y + 2);
                                    });
                                }
                            });

                            height = marginTop + visibleLanes.length * (laneHeight + laneSpacing) + marginBottom;
                        }

                        // Update stage & container sizes
                        stage.height(height);
                        container.style.height = height + 'px';

                        // Reposition axis and ticks
                        axisY = height - marginBottom + 10;
                        axisLine.points([marginLeft, axisY, width - marginRight, axisY]);

                        ticks.forEach(function(t) {
                            var pts = t.line.points();
                            var x = pts[0];
                            t.line.points([x, axisY, x, axisY + 6]);
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

                    $typeButtons.on('click', function(e) {
                        if (e.which !== 1 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) {
                            return;
                        }
                        e.preventDefault();

                        var type = $(this).data('connection-type');
                        activeTypes = new Set([type]);
                        updateButtonStates();
                        applyFilters();
                    });

                    $layoutRadios.on('change', function() {
                        var selected = $(this).val();
                        if (selected === 'expanded' || selected === 'collapsed') {
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
                });
            });
        })(jQuery);
    </script>
@endpush

