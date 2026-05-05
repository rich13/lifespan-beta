@props(['span', 'timeTravelDate' => null])

@php
    $currentUserSpanId = optional(auth()->user())->personal_span_id;
@endphp

<div class="card timeline-combined-group-konva-card" data-span-id="{{ $span->id }}">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">
            <i class="bi bi-person-lines-fill me-2"></i>
            Timeline
        </h5>
        <div class="d-flex gap-2 align-items-center">
            <a href="{{ route('spans.all-connections', $span) }}" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-clock-history me-1"></i>
                Overview
            </a>
            <div class="btn-group btn-group-sm" role="group">
                <input type="radio" class="btn-check" name="timeline-mode-{{ $span->id }}" id="absolute-mode-{{ $span->id }}" value="absolute" checked>
                <label class="btn btn-outline-primary" for="absolute-mode-{{ $span->id }}">
                    Absolute
                </label>
                <input type="radio" class="btn-check" name="timeline-mode-{{ $span->id }}" id="relative-mode-{{ $span->id }}" value="relative">
                <label class="btn btn-outline-primary" for="relative-mode-{{ $span->id }}">
                    Relative
                </label>
            </div>
            <div class="form-check form-switch mb-0 ms-1">
                <input class="form-check-input" type="checkbox" role="switch" id="context-overlay-toggle-{{ $span->id }}">
                <label class="form-check-label small" for="context-overlay-toggle-{{ $span->id }}">Context overlay</label>
            </div>
        </div>
    </div>
    <div class="card-body">
        <div class="d-flex">
            <div id="timeline-combined-container-{{ $span->id }}" style="height: 60px; flex: 1; cursor: crosshair; position: relative; overflow: hidden; transition: height 0.3s cubic-bezier(.4,0,.2,1);">
                <div id="timeline-combined-spinner-{{ $span->id }}" class="d-flex justify-content-center align-items-center h-100" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(255,255,255,0.7); z-index: 10;">
                    <div class="spinner-border text-secondary" role="status" style="width: 2rem; height: 2rem;">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
                <!-- Konva timeline will be rendered here -->
            </div>
            <div id="timeline-swimlane-panel-{{ $span->id }}" class="timeline-swimlane-panel ms-2" style="width: 180px; min-width: 180px; display: none;">
                <div class="swimlane-panel-header">
                    <small class="text-muted fw-bold">Hidden Swimlanes</small>
                </div>
                <div id="timeline-hidden-swimlanes-{{ $span->id }}" class="swimlane-panel-body">
                    <!-- Hidden swimlane buttons will be added here dynamically -->
                </div>
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>
    .timeline-combined-group-konva-card .timeline-legend .d-flex {
        flex-wrap: wrap;
    }
    
    .timeline-swimlane-panel {
        border-left: 1px solid #dee2e6;
        padding-left: 0.5rem;
        max-height: 400px;
        overflow-y: auto;
    }
    
    .swimlane-panel-header {
        padding: 0.25rem 0;
        border-bottom: 1px solid #eee;
        margin-bottom: 0.5rem;
    }
    
    .swimlane-panel-body {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }
    
    .swimlane-visible-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        padding: 0.15rem 0.4rem;
        font-size: 0.7rem;
        background-color: #e9ecef;
        border-radius: 0.25rem;
        cursor: default;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 100%;
    }
    
    .swimlane-visible-badge.current-span {
        background-color: #e3f2fd;
        border: 1px solid #90caf9;
    }
    
    .swimlane-visible-badge .remove-swimlane {
        cursor: pointer;
        opacity: 0.6;
        transition: opacity 0.15s;
        flex-shrink: 0;
    }
    
    .swimlane-visible-badge .remove-swimlane:hover {
        opacity: 1;
        color: #dc3545;
    }
    
    .swimlane-hidden-btn {
        display: block;
        width: 100%;
        text-align: left;
        padding: 0.2rem 0.4rem;
        font-size: 0.7rem;
        border: 1px solid #dee2e6;
        background-color: #f8f9fa;
        border-radius: 0.25rem;
        cursor: pointer;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        transition: background-color 0.15s, border-color 0.15s;
    }
    
    .swimlane-hidden-btn:hover {
        background-color: #e9ecef;
        border-color: #adb5bd;
    }
    
    .swimlane-hidden-btn i {
        margin-right: 0.25rem;
        color: #6c757d;
    }
</style>
@endpush

@push('scripts')
    @once
        <script src="https://unpkg.com/konva@9/konva.min.js"></script>
    @endonce
    <script>
    (function() {
        var spanId = '{{ $span->id }}';
        var spanIdSafe = '{{ str_replace('-', '_', $span->id) }}';
        var currentUserSpanId = '{{ $currentUserSpanId }}';
        var timeTravelDate = @json($timeTravelDate);
        var spanName = @json($span->name ?? 'Unknown');

        var tooltipLocked = false;
        var tooltipMask = null;
        var tooltipEscapeListener = null;
        var unlockListener = null;
        var connectionTypeFilters = {};
        var currentTimelineData = null;
        var currentSpan = null;
        var currentUserSpanIdStored = null;
        var currentMode = 'absolute';
        var stage = null;
        var $tooltip = null;
        var contextOverlayEnabled = false;
        var eventsOverlayCache = {};
        var eventsOverlayDebounceTimer = null;
        var pmOverlayCache = {};
        var pmOverlayDebounceTimer = null;
        var presidentOverlayCache = {};
        var presidentOverlayDebounceTimer = null;
        var renderGeneration = 0;
        var currentContextOverlayActivities = [];
        var currentTooltipTimelineOrder = [];
        var contextOverlayBarColour = '#4f86c6';
        var hiddenSwimlanes = {}; // Track hidden swimlanes by id

        function fetchJsonOrFallback(url, fallback) {
            return fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                .then(function(response) {
                    if (!response.ok) return fallback;
                    return response.json();
                })
                .catch(function() { return fallback; });
        }

        function filterConnectionsDeep(connections) {
            return (connections || []).filter(function(conn) {
                if (conn.target_type === 'connection') return false;
                if (conn.target_type === 'note') return false;
                if (conn.target_type === 'thing' && (conn.target_metadata && (conn.target_metadata.subtype === 'photo' || conn.target_metadata.subtype === 'set'))) return false;
                if (conn.type_id === 'family') return false;
                return true;
            }).map(function(conn) {
                if (Array.isArray(conn.nested_connections)) {
                    return Object.assign({}, conn, { nested_connections: filterConnectionsDeep(conn.nested_connections) });
                }
                return conn;
            });
        }

        function shouldShowConnection(typeId) {
            var filterState = connectionTypeFilters[typeId];
            if (!filterState) return true;
            if (filterState === 'hidden') return false;
            if (filterState === 'isolated') {
                var isolatedTypes = Object.keys(connectionTypeFilters).filter(function(k) { return connectionTypeFilters[k] === 'isolated'; });
                return isolatedTypes.length === 0 ? true : isolatedTypes.indexOf(typeId) >= 0;
            }
            return true;
        }

        function getConnectionColor(typeId) {
            var cssColor = getComputedStyle(document.documentElement).getPropertyValue('--connection-' + typeId + '-color');
            if (cssColor && cssColor.trim() !== '') return cssColor.trim();
            var fallbackColors = {
                'residence': '#007bff', 'employment': '#28a745', 'education': '#ffc107', 'membership': '#dc3545',
                'family': '#6f42c1', 'relationship': '#fd7e14', 'travel': '#20c997', 'participation': '#e83e8c',
                'ownership': '#6c757d', 'created': '#17a2b8', 'contains': '#6610f2', 'has_role': '#fd7e14',
                'at_organisation': '#20c997', 'life': '#000000'
            };
            return fallbackColors[typeId] || '#6c757d';
        }

        function calculateCombinedTimeRange(timelineData, currentSpan) {
            var currentYear = new Date().getFullYear();
            var start = currentSpan.start_year || 1900;
            var end = currentYear;
            timelineData.forEach(function(timeline) {
                if (timeline.roleOccupancies && timeline.roleOccupancies.length > 0) {
                    timeline.roleOccupancies.forEach(function(c) {
                        if (c.start_year && c.start_year < start) start = c.start_year;
                        if (c.end_year && c.end_year > end) end = c.end_year;
                        else if (!c.end_year && currentYear > end) end = currentYear;
                    });
                }
                if (timeline.timeline && timeline.timeline.span) {
                    var ts = timeline.timeline.span;
                    if (ts.start_year && ts.start_year < start) start = ts.start_year;
                    if (timeline.timeline.connections) {
                        timeline.timeline.connections.forEach(function(c) {
                            if (c.start_year && c.start_year < start) start = c.start_year;
                        });
                    }
                }
                if (timeline.duringConnections) {
                    timeline.duringConnections.forEach(function(c) {
                        if (c.start_year && c.start_year < start) start = c.start_year;
                    });
                }
            });
            var padding = Math.max(5, Math.floor((end - start) * 0.1));
            return { start: start - padding, end: end + padding };
        }

        function calculateRelativeTimeRange(timelineData, currentSpan) {
            var currentYear = new Date().getFullYear();
            var allAges = [];
            timelineData.forEach(function(timeline) {
                if (timeline.roleOccupancies && timeline.roleOccupancies.length > 0) {
                    var occupancies = timeline.roleOccupancies.filter(function(c) { return c.start_year; });
                    if (occupancies.length > 0) {
                        var earliestYear = Math.min.apply(null, occupancies.map(function(o) { return o.start_year; }));
                        occupancies.forEach(function(c) {
                            if (c.start_year) {
                                allAges.push(c.start_year - earliestYear);
                                if (c.end_year) allAges.push(c.end_year - earliestYear);
                                else allAges.push(currentYear - earliestYear);
                            }
                        });
                    }
                }
                if (timeline.timeline && timeline.timeline.span) {
                    var ts = timeline.timeline.span;
                    if (ts.start_year) {
                        var lifeEndAge = ts.end_year ? ts.end_year - ts.start_year : currentYear - ts.start_year;
                        allAges.push(0, lifeEndAge);
                        if (timeline.timeline.connections) {
                            timeline.timeline.connections.forEach(function(c) {
                                if (c.start_year) {
                                    allAges.push(c.start_year - ts.start_year);
                                    if (c.end_year) allAges.push(c.end_year - ts.start_year);
                                }
                            });
                        }
                        if (timeline.duringConnections) {
                            timeline.duringConnections.forEach(function(c) {
                                if (c.start_year) {
                                    allAges.push(c.start_year - ts.start_year);
                                    if (c.end_year) allAges.push(c.end_year - ts.start_year);
                                }
                            });
                        }
                    }
                }
            });
            if (allAges.length === 0) return { start: 0, end: 100 };
            var minAge = Math.min.apply(null, allAges);
            var maxAge = Math.max.apply(null, allAges);
            var padding = Math.max(2, Math.floor((maxAge - minAge) * 0.1));
            return { start: Math.max(0, minAge - padding), end: maxAge + padding };
        }

        // Unpadded content range from the non-leader swimlanes.
        // Used to clip leader overlay bars so they don't extend into timeline padding.
        function calculateMainAbsoluteContentRange(timelineData, currentSpan) {
            var currentYear = new Date().getFullYear();
            var startCandidates = [];
            var endCandidates = [];

            if (currentSpan && currentSpan.start_year) {
                startCandidates.push(currentSpan.start_year);
            }

            timelineData.forEach(function(timeline) {
                var timelineSpan = timeline.timeline && timeline.timeline.span ? timeline.timeline.span : null;
                if (timelineSpan && timelineSpan.start_year) {
                    startCandidates.push(timelineSpan.start_year);
                    endCandidates.push(Math.min(currentYear, timelineSpan.end_year || currentYear));
                }

                if (timeline.timeline && timeline.timeline.connections) {
                    timeline.timeline.connections.forEach(function(connection) {
                        if (connection.start_year) {
                            startCandidates.push(connection.start_year);
                            endCandidates.push(Math.min(currentYear, connection.end_year || currentYear));
                        }
                    });
                }

                if (timeline.duringConnections) {
                    timeline.duringConnections.forEach(function(connection) {
                        if (connection.start_year) {
                            startCandidates.push(connection.start_year);
                            endCandidates.push(Math.min(currentYear, connection.end_year || currentYear));
                        }
                    });
                }

                if (timeline.roleOccupancies) {
                    timeline.roleOccupancies.forEach(function(connection) {
                        if (connection.start_year) {
                            startCandidates.push(connection.start_year);
                            endCandidates.push(Math.min(currentYear, connection.end_year || currentYear));
                        }
                    });
                }
            });

            var minStart = startCandidates.length ? Math.min.apply(null, startCandidates) : (currentSpan && currentSpan.start_year ? currentSpan.start_year : (currentYear - 100));
            var maxEnd = endCandidates.length ? Math.max.apply(null, endCandidates) : currentYear;
            maxEnd = Math.min(currentYear, maxEnd);

            if (maxEnd < minStart) {
                maxEnd = minStart;
            }

            return { start: minStart, end: maxEnd };
        }

        function findActivitiesAtTime(year, timelineData, currentTimelineName, mode) {
            var activities = [];
            // Filter to only visible swimlanes
            var visibleTimelines = timelineData.filter(function(t) {
                return isSwimlaneVisible(t.id);
            });
            if (mode === 'absolute') {
                visibleTimelines.forEach(function(timeline) {
                    if (timeline.timeline && timeline.timeline.connections) {
                        filterConnectionsDeep(timeline.timeline.connections).forEach(function(oc) {
                            var otherEnd = oc.end_year || new Date().getFullYear();
                            if (oc.start_year <= year && otherEnd >= year) {
                                activities.push({
                                    timelineName: timeline.name,
                                    type_name: oc.type_name,
                                    type_id: oc.type_id,
                                    target_name: oc.target_name,
                                    start_year: oc.start_year,
                                    end_year: oc.end_year,
                                    isCurrentSpan: timeline.isCurrentSpan,
                                    nested_connections: oc.nested_connections || [],
                                    span_id: timeline.id,
                                    target_id: oc.target_id
                                });
                            }
                        });
                    }
                });

                currentContextOverlayActivities.forEach(function(leaderActivity) {
                    var leaderEnd = leaderActivity.end_year || new Date().getFullYear();
                    if (leaderActivity.start_year <= year && leaderEnd >= year) {
                        activities.push({
                            timelineName: leaderActivity.timelineName,
                            type_name: leaderActivity.type_name,
                            type_id: leaderActivity.type_id,
                            target_name: leaderActivity.target_name,
                            start_year: leaderActivity.start_year,
                            end_year: leaderActivity.end_year,
                            isCurrentSpan: false,
                            nested_connections: [],
                            span_id: null,
                            target_id: leaderActivity.target_id
                        });
                    }
                });
            } else {
                visibleTimelines.forEach(function(timeline) {
                    var timelineSpan = timeline.timeline && timeline.timeline.span ? timeline.timeline.span : null;
                    var lifeEndAge = null;
                    if (timelineSpan && timelineSpan.start_year) {
                        lifeEndAge = (timelineSpan.end_year || new Date().getFullYear()) - timelineSpan.start_year;
                    }
                    var alive = lifeEndAge !== null && year >= 0 && year <= lifeEndAge;
                    if (lifeEndAge !== null) {
                        activities.push({
                            timelineName: timeline.name,
                            type_name: 'Life span',
                            type_id: 'life-span',
                            target_name: '',
                            start_age: 0,
                            end_age: lifeEndAge,
                            isCurrentSpan: timeline.isCurrentSpan,
                            isLifeSpan: true,
                            alive: alive,
                            span_id: timeline.id,
                            target_id: null
                        });
                    }
                    if (timeline.timeline && timeline.timeline.connections && timelineSpan && timelineSpan.start_year) {
                        filterConnectionsDeep(timeline.timeline.connections).forEach(function(oc) {
                            var otherStart = oc.start_year - timelineSpan.start_year;
                            var otherEnd = oc.end_year ? oc.end_year - timelineSpan.start_year : new Date().getFullYear() - timelineSpan.start_year;
                            if (otherStart <= year && otherEnd >= year) {
                                activities.push({
                                    timelineName: timeline.name,
                                    type_name: oc.type_name,
                                    type_id: oc.type_id,
                                    target_name: oc.target_name,
                                    start_year: oc.start_year,
                                    end_year: oc.end_year,
                                    start_age: otherStart,
                                    end_age: otherEnd,
                                    isCurrentSpan: timeline.isCurrentSpan,
                                    nested_connections: oc.nested_connections || [],
                                    span_id: timeline.id,
                                    target_id: oc.target_id
                                });
                            }
                        });
                    }
                    if (lifeEndAge === null) {
                        activities.push({ timelineName: timeline.name, notAlive: true, isCurrentSpan: timeline.isCurrentSpan, span_id: timeline.id, target_id: null });
                    } else if (!alive) {
                        activities.push({ timelineName: timeline.name, notAlive: true, isCurrentSpan: timeline.isCurrentSpan, span_id: timeline.id, target_id: null });
                    } else if (!activities.some(function(a) { return a.timelineName === timeline.name && !a.isLifeSpan && !a.notAlive; })) {
                        activities.push({ timelineName: timeline.name, noActivity: true, isCurrentSpan: timeline.isCurrentSpan, span_id: timeline.id, target_id: null });
                    }
                });
            }
            return activities.sort(function(a, b) {
                var ai = currentTooltipTimelineOrder.indexOf(a.timelineName);
                var bi = currentTooltipTimelineOrder.indexOf(b.timelineName);
                if (ai === -1) ai = 9999;
                if (bi === -1) bi = 9999;
                return ai - bi;
            });
        }

        function formatActivitiesWithDividers(activities, mode, hoverYear) {
            if (!activities.length) return '';
            var grouped = {};
            activities.forEach(function(a) {
                if (!grouped[a.timelineName]) grouped[a.timelineName] = [];
                grouped[a.timelineName].push(a);
            });
            var html = '';
            Object.keys(grouped).forEach(function(timelineName, idx) {
                if (idx > 0) html += '<hr style="margin: 4px 0; border: none; border-top: 1px solid white;">';
                var acts = grouped[timelineName];
                var firstAct = acts.find(function(a) { return a.span_id; });
                var nameHtml = timelineName;
                if (tooltipLocked && firstAct && firstAct.span_id) {
                    nameHtml = '<a href="/spans/' + firstAct.span_id + '" style="color: #fff; text-decoration: underline;">' + timelineName + '</a>';
                }
                html += '<div><strong>' + nameHtml + '</strong></div>';
                acts.forEach(function(activity) {
                    if (activity.notAlive) {
                        html += '<div style="color:#aaa; margin-left: 1.5em;">Not alive at this age</div>';
                    } else if (activity.noActivity) {
                        html += '<div style="color:#aaa; margin-left: 1.5em;">No span at this age</div>';
                    } else if (activity.isLifeSpan && mode === 'absolute') {
                        var endD = (activity.end_year == null) ? 'now' : activity.end_year;
                        html += '<div style="margin-left: 1.5em;">• <strong>Life span</strong> (' + activity.start_year + ' - ' + endD + ')</div>';
                    } else {
                        var timeInfo = '';
                        if (mode === 'absolute') {
                            var endD = (activity.end_year == null) ? 'now' : activity.end_year;
                            timeInfo = ' (' + activity.start_year + ' - ' + endD + ')';
                        } else if (typeof activity.start_age !== 'undefined' && typeof activity.end_age !== 'undefined') {
                            var endD = (activity.end_age == null) ? 'now' : activity.end_age;
                            timeInfo = ' (Age ' + activity.start_age + ' - ' + endD + ')';
                        }
                        var targetHtml = activity.target_name;
                        if (tooltipLocked && activity.target_id) {
                            targetHtml = '<a href="/spans/' + activity.target_id + '" style="color: #fff; text-decoration: underline;">' + activity.target_name + '</a>';
                        }
                        var isContextLane = activity.timelineName === 'Events' || activity.timelineName === 'Prime Ministers' || activity.timelineName === 'US Presidents';
                        if (isContextLane) {
                            html += '<div style="margin-left: 1.5em;">- ' + targetHtml + timeInfo + '</div>';
                        } else {
                            html += '<div style="margin-left: 1.5em;">- ' + activity.type_name + ' ' + targetHtml + timeInfo + '</div>';
                        }
                    }
                    if (activity.nested_connections && activity.nested_connections.length > 0 && hoverYear != null) {
                        activity.nested_connections.forEach(function(nc) {
                            var nEnd = nc.end_year || new Date().getFullYear();
                            if (nc.start_year <= hoverYear && nEnd >= hoverYear) {
                                var ncColor = getConnectionColor(nc.type_id);
                                html += '<div style="margin-left: 3em; color: ' + ncColor + ';">└─ ' + nc.type_name + ' ' + nc.target_name + '</div>';
                            }
                        });
                    }
                });
            });
            return html;
        }

        function showTooltipContent(event, connections, timelineName, isCurrentSpan, hoverYear, mode, isDuring) {
            var tooltipContent = '';
            if (mode === 'relative') {
                tooltipContent = '<strong>At age ' + hoverYear + '</strong>';
            } else {
                tooltipContent = '<strong>In ' + hoverYear + '</strong>';
            }
            if (connections && connections.length > 0) {
                var c = connections[0];
                var connType = isDuring ? 'during' : c.type_id;
                var targetName = c.target_name || 'Unknown';
                var targetType = c.target_type || 'unknown';
                tooltipContent += '<br/><br/>';
                if (isDuring) {
                    tooltipContent += '<strong>' + targetName + '</strong> (' + targetType + ')<br/><em>Phase during ' + timelineName + "'s activities</em>";
                } else {
                    tooltipContent += '<strong>' + connType + '</strong> ' + targetName + ' (' + targetType + ')';
                }
                var endYear = c.end_year || 'Present';
                if (mode === 'absolute') {
                    tooltipContent += '<br/>' + c.start_year + ' - ' + endYear;
                } else {
                    var tl = currentTimelineData.find(function(t) { return t.name === timelineName; });
                    var baseYear = (tl && tl.timeline && tl.timeline.span && tl.timeline.span.start_year) ? tl.timeline.span.start_year : c.start_year;
                    var startAge = c.start_year - baseYear;
                    var endAge = endYear === 'Present' ? 'Present' : (endYear - baseYear);
                    tooltipContent += '<br/>Age ' + startAge + ' - ' + endAge;
                }
            }
            var concurrentActivities = findActivitiesAtTime(hoverYear, currentTimelineData, timelineName, mode);
            if (concurrentActivities.length > 0) {
                tooltipContent += '<br/><br/>' + formatActivitiesWithDividers(concurrentActivities, mode, hoverYear);
            }
            return tooltipContent;
        }

        function showTooltip(html, evt) {
            if (!$tooltip || !evt) return;
            var pageX = evt.pageX || (evt.clientX + (window.scrollX || document.documentElement.scrollLeft));
            var pageY = evt.pageY || (evt.clientY + (window.scrollY || document.documentElement.scrollTop));
            $tooltip.html(html).css({
                left: (pageX - 50) + 'px',
                top: (pageY + 20) + 'px',
                display: 'block',
                opacity: 1
            });
        }

        function hideTooltip() {
            if ($tooltip) $tooltip.css({ opacity: 0, display: 'none' });
        }

        function lockTooltip(evt, html) {
            tooltipLocked = true;
            if ($tooltip) $tooltip.css('pointer-events', 'auto');
            if (!tooltipMask) {
                tooltipMask = document.createElement('div');
                tooltipMask.className = 'timeline-tooltip-mask';
                Object.assign(tooltipMask.style, {
                    position: 'fixed', top: 0, left: 0, width: '100vw', height: '100vh',
                    background: 'rgba(255,255,255,0.7)', zIndex: 9998, cursor: 'pointer'
                });
                tooltipMask.addEventListener('mousedown', function() {
                    tooltipLocked = false;
                    hideTooltip();
                    if ($tooltip) $tooltip.css('pointer-events', 'none');
                    if (tooltipMask && tooltipMask.parentNode) tooltipMask.parentNode.removeChild(tooltipMask);
                    tooltipMask = null;
                    if (tooltipEscapeListener) {
                        document.removeEventListener('keydown', tooltipEscapeListener);
                        tooltipEscapeListener = null;
                    }
                    if (unlockListener) {
                        document.removeEventListener('mousedown', unlockListener);
                        unlockListener = null;
                    }
                });
            }
            document.body.appendChild(tooltipMask);
            if (!tooltipEscapeListener) {
                tooltipEscapeListener = function(e) {
                    if (e.key === 'Escape') {
                        tooltipLocked = false;
                        hideTooltip();
                        if ($tooltip) $tooltip.css('pointer-events', 'none');
                        if (tooltipMask && tooltipMask.parentNode) tooltipMask.parentNode.removeChild(tooltipMask);
                        tooltipMask = null;
                        document.removeEventListener('keydown', tooltipEscapeListener);
                        tooltipEscapeListener = null;
                        if (unlockListener) {
                            document.removeEventListener('mousedown', unlockListener);
                            unlockListener = null;
                        }
                    }
                };
                document.addEventListener('keydown', tooltipEscapeListener);
            }
            if (unlockListener) document.removeEventListener('mousedown', unlockListener);
            unlockListener = function(e) {
                var container = document.getElementById('timeline-combined-container-' + spanId);
                if (tooltipMask && e.target === tooltipMask) return;
                var tooltipNode = $tooltip ? $tooltip[0] : null;
                if (tooltipNode && !tooltipNode.contains(e.target) && container && !container.contains(e.target)) {
                    tooltipLocked = false;
                    hideTooltip();
                    if ($tooltip) $tooltip.css('pointer-events', 'none');
                    if (tooltipMask && tooltipMask.parentNode) tooltipMask.parentNode.removeChild(tooltipMask);
                    tooltipMask = null;
                    document.removeEventListener('mousedown', unlockListener);
                    unlockListener = null;
                    if (tooltipEscapeListener) {
                        document.removeEventListener('keydown', tooltipEscapeListener);
                        tooltipEscapeListener = null;
                    }
                }
            };
            document.addEventListener('mousedown', unlockListener);
            showTooltip(html, evt);
        }

        function updateTimelineLegend(timelineData) {
            var legendContainer = document.getElementById('timeline-legend-' + spanId);
            var legendItems = document.getElementById('timeline-legend-items-' + spanId);
            var resetBtn = document.getElementById('timeline-legend-reset-' + spanId);
            if (!legendContainer || !legendItems) return;
            var connectionTypes = new Set();
            timelineData.forEach(function(t) {
                if (t.timeline && t.timeline.connections) {
                    t.timeline.connections.forEach(function(c) {
                        if (c.type_id && c.type_id !== 'life') connectionTypes.add(c.type_id);
                    });
                }
                if (t.roleOccupancies && t.roleOccupancies.length > 0) connectionTypes.add('has_role');
                if (t.duringConnections) {
                    t.duringConnections.forEach(function(c) {
                        if (c.type_id) connectionTypes.add(c.type_id);
                    });
                }
            });
            if (connectionTypes.size === 0) {
                legendContainer.style.display = 'none';
                return;
            }
            var typeLabels = {
                'residence': 'Residence', 'employment': 'Employment', 'education': 'Education', 'membership': 'Membership',
                'family': 'Family', 'relationship': 'Relationship', 'travel': 'Travel', 'participation': 'Participation',
                'ownership': 'Ownership', 'created': 'Created', 'contains': 'Contains', 'has_role': 'Role', 'at_organisation': 'At Organisation'
            };
            legendItems.innerHTML = '';
            Array.from(connectionTypes).sort().forEach(function(typeId) {
                var color = getConnectionColor(typeId);
                var label = typeLabels[typeId] || typeId.replace('_', ' ').replace(/\b\w/g, function(l) { return l.toUpperCase(); });
                var filterState = connectionTypeFilters[typeId] || 'visible';
                var item = document.createElement('div');
                item.className = 'd-flex align-items-center';
                item.style.cssText = 'font-size: 0.75rem; cursor: pointer; user-select: none; padding: 2px 6px; border-radius: 4px;';
                item.dataset.typeId = typeId;
                if (filterState === 'hidden') {
                    item.style.opacity = '0.3';
                    item.style.textDecoration = 'line-through';
                } else if (filterState === 'isolated') {
                    item.style.backgroundColor = 'rgba(13, 110, 253, 0.1)';
                    item.style.border = '1px solid rgba(13, 110, 253, 0.3)';
                }
                var clickTimeout;
                item.addEventListener('click', function() {
                    clearTimeout(clickTimeout);
                    clickTimeout = setTimeout(function() { isolateConnectionType(typeId); }, 250);
                });
                item.addEventListener('dblclick', function(e) {
                    e.preventDefault();
                    clearTimeout(clickTimeout);
                    toggleHideConnectionType(typeId);
                });
                var colorBox = document.createElement('span');
                colorBox.style.cssText = 'display: inline-block; width: 12px; height: 12px; background-color: ' + color + '; border: 1px solid #fff; border-radius: 2px; margin-right: 4px;';
                var labelText = document.createElement('span');
                labelText.textContent = label;
                item.appendChild(colorBox);
                item.appendChild(labelText);
                legendItems.appendChild(item);
            });
            legendContainer.style.display = 'block';
            if (resetBtn) resetBtn.style.display = Object.keys(connectionTypeFilters).length > 0 ? 'block' : 'none';
        }

        function isolateConnectionType(typeId) {
            if (connectionTypeFilters[typeId] === 'isolated') {
                Object.keys(connectionTypeFilters).forEach(function(k) { delete connectionTypeFilters[k]; });
            } else {
                var allTypes = new Set();
                if (currentTimelineData) {
                    currentTimelineData.forEach(function(t) {
                        if (t.timeline && t.timeline.connections) {
                            t.timeline.connections.forEach(function(c) {
                                if (c.type_id && c.type_id !== 'life') allTypes.add(c.type_id);
                            });
                        }
                        if (t.roleOccupancies && t.roleOccupancies.length > 0) allTypes.add('has_role');
                        if (t.duringConnections) {
                            t.duringConnections.forEach(function(c) { if (c.type_id) allTypes.add(c.type_id); });
                        }
                    });
                }
                allTypes.forEach(function(ot) {
                    if (ot !== typeId) connectionTypeFilters[ot] = 'hidden';
                });
                connectionTypeFilters[typeId] = 'isolated';
            }
            reRenderTimeline();
        }

        function toggleHideConnectionType(typeId) {
            if (connectionTypeFilters[typeId] === 'hidden') {
                delete connectionTypeFilters[typeId];
            } else {
                if (connectionTypeFilters[typeId] === 'isolated') {
                    Object.keys(connectionTypeFilters).forEach(function(k) {
                        if (connectionTypeFilters[k] === 'hidden') delete connectionTypeFilters[k];
                    });
                    delete connectionTypeFilters[typeId];
                }
                connectionTypeFilters[typeId] = 'hidden';
            }
            reRenderTimeline();
        }

        function resetConnectionTypeFilters() {
            connectionTypeFilters = {};
            reRenderTimeline();
        }

        function reRenderTimeline() {
            if (currentTimelineData && currentSpan) {
                renderKonvaTimeline(currentTimelineData, currentSpan, currentMode, currentUserSpanIdStored);
            }
        }

        function hideSwimlane(timelineId, timelineName, isCurrentSpan) {
            hiddenSwimlanes[timelineId] = { id: timelineId, name: timelineName, isCurrentSpan: isCurrentSpan };
            updateSwimlanePanel();
            reRenderTimeline();
        }

        function showSwimlane(timelineId) {
            delete hiddenSwimlanes[timelineId];
            updateSwimlanePanel();
            reRenderTimeline();
        }

        function isSwimlaneVisible(timelineId) {
            return !hiddenSwimlanes[timelineId];
        }

        function updateSwimlanePanel() {
            var panel = document.getElementById('timeline-swimlane-panel-' + spanId);
            var hiddenContainer = document.getElementById('timeline-hidden-swimlanes-' + spanId);
            if (!panel || !hiddenContainer) return;

            var hiddenKeys = Object.keys(hiddenSwimlanes);
            
            if (hiddenKeys.length === 0) {
                panel.style.display = 'none';
                return;
            }

            panel.style.display = 'block';
            hiddenContainer.innerHTML = '';

            hiddenKeys.forEach(function(id) {
                var lane = hiddenSwimlanes[id];
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'swimlane-hidden-btn';
                btn.innerHTML = '<i class="bi bi-plus-circle"></i>' + (lane.name || 'Unknown');
                btn.title = 'Click to show "' + (lane.name || 'Unknown') + '" on the timeline';
                btn.addEventListener('click', function() {
                    showSwimlane(id);
                });
                hiddenContainer.appendChild(btn);
            });

            // Add "Show All" button if multiple hidden
            if (hiddenKeys.length > 1) {
                var showAllBtn = document.createElement('button');
                showAllBtn.type = 'button';
                showAllBtn.className = 'swimlane-hidden-btn mt-2';
                showAllBtn.style.fontWeight = 'bold';
                showAllBtn.innerHTML = '<i class="bi bi-eye"></i>Show All';
                showAllBtn.title = 'Show all hidden swimlanes';
                showAllBtn.addEventListener('click', function() {
                    hiddenSwimlanes = {};
                    updateSwimlanePanel();
                    reRenderTimeline();
                });
                hiddenContainer.appendChild(showAllBtn);
            }
        }

        function getPersonOverlayColour(personId) {
            var palette = ['#0d6efd', '#198754', '#fd7e14', '#6f42c1', '#20c997', '#dc3545', '#0dcaf0', '#6610f2'];
            var hash = 0;
            var text = String(personId || '');
            for (var i = 0; i < text.length; i++) {
                hash = ((hash << 5) - hash) + text.charCodeAt(i);
                hash |= 0;
            }
            return palette[Math.abs(hash) % palette.length];
        }

        function setupContextOverlayToggle() {
            var toggle = document.getElementById('context-overlay-toggle-' + spanId);
            if (!toggle) return;

            toggle.removeEventListener('change', toggle._contextOverlayHandler);
            var handler = function() {
                contextOverlayEnabled = !!this.checked;
                reRenderTimeline();
            };
            toggle._contextOverlayHandler = handler;
            toggle.addEventListener('change', handler);

            toggle.disabled = currentMode !== 'absolute';
            if (currentMode !== 'absolute') {
                toggle.checked = false;
                contextOverlayEnabled = false;
            }
        }

        function requestPmOverlayForRange(viewStartYear, viewEndYear, callback) {
            var startYear = Math.floor(viewStartYear);
            var endYear = Math.ceil(viewEndYear);
            var cacheKey = startYear + ':' + endYear;

            if (pmOverlayCache[cacheKey]) {
                callback(pmOverlayCache[cacheKey]);
                return;
            }

            clearTimeout(pmOverlayDebounceTimer);
            pmOverlayDebounceTimer = setTimeout(function() {
                var url = '/api/spans/' + spanId + '/leadership-overlay?role=prime_minister&start_year=' + startYear + '&end_year=' + endYear;
                fetchJsonOrFallback(url, {
                    role: 'prime_minister',
                    role_name: 'Prime Minister of the United Kingdom',
                    start_year: startYear,
                    end_year: endYear,
                    bars: []
                }).then(function(data) {
                    pmOverlayCache[cacheKey] = data;
                    callback(data);
                }).catch(function() {
                    callback({
                        role: 'prime_minister',
                        role_name: 'Prime Minister of the United Kingdom',
                        start_year: startYear,
                        end_year: endYear,
                        bars: []
                    });
                });
            }, 180);
        }

        function requestPresidentOverlayForRange(viewStartYear, viewEndYear, callback) {
            var startYear = Math.floor(viewStartYear);
            var endYear = Math.ceil(viewEndYear);
            var cacheKey = startYear + ':' + endYear;

            if (presidentOverlayCache[cacheKey]) {
                callback(presidentOverlayCache[cacheKey]);
                return;
            }

            clearTimeout(presidentOverlayDebounceTimer);
            presidentOverlayDebounceTimer = setTimeout(function() {
                var url = '/api/spans/' + spanId + '/leadership-overlay?role=president&start_year=' + startYear + '&end_year=' + endYear;
                fetchJsonOrFallback(url, {
                    role: 'president',
                    role_name: 'President of the United States',
                    start_year: startYear,
                    end_year: endYear,
                    bars: []
                }).then(function(data) {
                    presidentOverlayCache[cacheKey] = data;
                    callback(data);
                }).catch(function() {
                    callback({
                        role: 'president',
                        role_name: 'President of the United States',
                        start_year: startYear,
                        end_year: endYear,
                        bars: []
                    });
                });
            }, 180);
        }

        function requestEventsOverlayForRange(viewStartYear, viewEndYear, callback) {
            var startYear = Math.floor(viewStartYear);
            var endYear = Math.ceil(viewEndYear);
            var cacheKey = startYear + ':' + endYear;

            if (eventsOverlayCache[cacheKey]) {
                callback(eventsOverlayCache[cacheKey]);
                return;
            }

            clearTimeout(eventsOverlayDebounceTimer);
            eventsOverlayDebounceTimer = setTimeout(function() {
                var url = '/api/spans/' + spanId + '/context-overlay-events?start_year=' + startYear + '&end_year=' + endYear;
                fetchJsonOrFallback(url, {
                    start_year: startYear,
                    end_year: endYear,
                    bars: []
                }).then(function(data) {
                    eventsOverlayCache[cacheKey] = data;
                    callback(data);
                }).catch(function() {
                    callback({ start_year: startYear, end_year: endYear, bars: [] });
                });
            }, 180);
        }

        function setupModeToggle(timelineData, currentSpan, currentUserSpanId) {
            var radios = document.querySelectorAll('input[name="timeline-mode-' + spanId + '"]');
            radios.forEach(function(radio) {
                radio.removeEventListener('change', radio._modeHandler);
                var handler = function() {
                    if (this.checked) {
                        currentMode = this.value;
                        setupContextOverlayToggle();
                        renderKonvaTimeline(timelineData, currentSpan, this.value, currentUserSpanId);
                    }
                };
                radio._modeHandler = handler;
                radio.addEventListener('change', handler);
            });
        }

        function renderKonvaTimeline(timelineData, spanData, mode, currentUserSpanId) {
            currentTimelineData = timelineData;
            currentSpan = spanData;
            currentUserSpanIdStored = currentUserSpanId;
            currentMode = mode;

            var container = document.getElementById('timeline-combined-container-' + spanId);
            if (!container || !window.Konva) return;

            var width = container.clientWidth;
            var margin = { top: 8, right: 20, bottom: 30, left: 20 };
            var swimlaneHeight = 20;
            var swimlaneSpacing = 10;
            var swimlaneBottomMargin = 30;
            // Filter to visible swimlanes for time range calculation
            var visibleForRange = timelineData.filter(function(t) {
                return isSwimlaneVisible(t.id);
            });
            var timeRange = mode === 'absolute'
                ? calculateCombinedTimeRange(visibleForRange, spanData)
                : calculateRelativeTimeRange(visibleForRange, spanData);

            var eventsOverlayVisible = mode === 'absolute' && contextOverlayEnabled;
            var eventsOverlayData = null;
            var pmOverlayVisible = mode === 'absolute' && contextOverlayEnabled;
            var pmOverlayData = null;
            var presidentOverlayVisible = mode === 'absolute' && contextOverlayEnabled;
            var presidentOverlayData = null;
            var viewportStartYear = Math.floor(timeRange.start);
            var viewportEndYear = Math.ceil(timeRange.end);

            if (eventsOverlayVisible) {
                var eventsOverlayKey = viewportStartYear + ':' + viewportEndYear;
                if (eventsOverlayCache[eventsOverlayKey]) {
                    eventsOverlayData = eventsOverlayCache[eventsOverlayKey];
                } else {
                    var currentEventsRender = ++renderGeneration;
                    requestEventsOverlayForRange(viewportStartYear, viewportEndYear, function() {
                        if (currentEventsRender !== renderGeneration) return;
                        reRenderTimeline();
                    });
                }
            }

            if (pmOverlayVisible) {
                var overlayKey = viewportStartYear + ':' + viewportEndYear;
                if (pmOverlayCache[overlayKey]) {
                    pmOverlayData = pmOverlayCache[overlayKey];
                } else {
                    var currentRender = ++renderGeneration;
                    requestPmOverlayForRange(viewportStartYear, viewportEndYear, function() {
                        if (currentRender !== renderGeneration) return;
                        reRenderTimeline();
                    });
                }
            }

            if (presidentOverlayVisible) {
                var presidentOverlayKey = viewportStartYear + ':' + viewportEndYear;
                if (presidentOverlayCache[presidentOverlayKey]) {
                    presidentOverlayData = presidentOverlayCache[presidentOverlayKey];
                } else {
                    var currentPresidentRender = ++renderGeneration;
                    requestPresidentOverlayForRange(viewportStartYear, viewportEndYear, function() {
                        if (currentPresidentRender !== renderGeneration) return;
                        reRenderTimeline();
                    });
                }
            }

            var contextLaneCount = (eventsOverlayVisible ? 1 : 0) + (pmOverlayVisible ? 1 : 0) + (presidentOverlayVisible ? 1 : 0);
            var leaderClipRange = mode === 'absolute'
                ? calculateMainAbsoluteContentRange(timelineData, spanData)
                : null;
            currentTooltipTimelineOrder = [];
            if (eventsOverlayVisible) {
                currentTooltipTimelineOrder.push('Events');
            }
            if (pmOverlayVisible) {
                currentTooltipTimelineOrder.push('Prime Ministers');
            }
            if (presidentOverlayVisible) {
                currentTooltipTimelineOrder.push('US Presidents');
            }
            timelineData.forEach(function(t) {
                if (isSwimlaneVisible(t.id)) {
                    currentTooltipTimelineOrder.push(t.name);
                }
            });

            currentContextOverlayActivities = [];
            if (eventsOverlayVisible && eventsOverlayData && Array.isArray(eventsOverlayData.bars)) {
                eventsOverlayData.bars.forEach(function(bar) {
                    currentContextOverlayActivities.push({
                        timelineName: 'Events',
                        type_name: 'Event',
                        type_id: 'event_overlay',
                        target_name: bar.event_name || 'Unknown',
                        start_year: bar.start_year,
                        end_year: bar.end_year,
                        target_id: bar.event_id || null
                    });
                });
            }
            if (pmOverlayVisible && pmOverlayData && Array.isArray(pmOverlayData.bars)) {
                pmOverlayData.bars.forEach(function(bar) {
                    currentContextOverlayActivities.push({
                        timelineName: 'Prime Ministers',
                        type_name: 'Prime Minister',
                        type_id: 'prime_minister_overlay',
                        target_name: bar.person_name || 'Unknown',
                        start_year: bar.start_year,
                        end_year: bar.end_year,
                        target_id: bar.person_id || null
                    });
                });
            }
            if (presidentOverlayVisible && presidentOverlayData && Array.isArray(presidentOverlayData.bars)) {
                presidentOverlayData.bars.forEach(function(bar) {
                    currentContextOverlayActivities.push({
                        timelineName: 'US Presidents',
                        type_name: 'US President',
                        type_id: 'president_overlay',
                        target_name: bar.person_name || 'Unknown',
                        start_year: bar.start_year,
                        end_year: bar.end_year,
                        target_id: bar.person_id || null
                    });
                });
            }

            // Filter out hidden swimlanes
            var visibleTimelineData = timelineData.filter(function(timeline) {
                return isSwimlaneVisible(timeline.id);
            });

            var totalSwimlanes = visibleTimelineData.length + contextLaneCount;
            var totalHeight = totalSwimlanes * (swimlaneHeight + swimlaneSpacing) - swimlaneSpacing + swimlaneBottomMargin;
            var adjustedHeight = totalHeight + margin.top + margin.bottom;

            container.style.height = adjustedHeight + 'px';
            container.innerHTML = '';

            if (stage) {
                stage.destroy();
                stage = null;
            }

            stage = new Konva.Stage({
                container: 'timeline-combined-container-' + spanId,
                width: width,
                height: adjustedHeight
            });

            var backgroundLayer = new Konva.Layer();
            var barLayer = new Konva.Layer();
            var axisLayer = new Konva.Layer();
            stage.add(backgroundLayer);
            stage.add(barLayer);
            stage.add(axisLayer);

            var contentWidth = width - margin.left - margin.right;
            var yearSpan = Math.max(1, timeRange.end - timeRange.start);
            var pixelsPerYear = contentWidth / yearSpan;

            function xForValue(v) {
                return margin.left + (v - timeRange.start) / yearSpan * contentWidth;
            }

            function valueForX(x) {
                return timeRange.start + (x - margin.left) / contentWidth * yearSpan;
            }

            visibleTimelineData.forEach(function(timeline, index) {
                var swimlaneY = margin.top + (index + contextLaneCount) * (swimlaneHeight + swimlaneSpacing);
                var isCurrentSpan = timeline.isCurrentSpan;
                var isCurrentUser = currentUserSpanId && timeline.id === currentUserSpanId;
                var timelineSpan = timeline.timeline && timeline.timeline.span ? timeline.timeline.span : null;

                var bgFill = isCurrentSpan ? '#e3f2fd' : '#f8f9fa';
                var bgStroke = isCurrentUser ? '#000000' : '#dee2e6';
                backgroundLayer.add(new Konva.Rect({
                    x: margin.left,
                    y: swimlaneY,
                    width: contentWidth,
                    height: swimlaneHeight,
                    fill: bgFill,
                    stroke: bgStroke,
                    strokeWidth: 1,
                    cornerRadius: 4
                }));

                if (timelineSpan && timelineSpan.start_year) {
                    var lifeStart = mode === 'absolute' ? timelineSpan.start_year : 0;
                    var lifeEnd = mode === 'absolute'
                        ? (timelineSpan.end_year || new Date().getFullYear())
                        : (timelineSpan.end_year ? timelineSpan.end_year - timelineSpan.start_year : new Date().getFullYear() - timelineSpan.start_year);
                    var hasConnections = timeline.timeline.connections && timeline.timeline.connections.length > 0;

                    var lifeRect = new Konva.Rect({
                        x: xForValue(lifeStart),
                        y: swimlaneY + 2,
                        width: Math.max(2, xForValue(lifeEnd) - xForValue(lifeStart)),
                        height: swimlaneHeight - 4,
                        fill: '#000000',
                        stroke: 'white',
                        strokeWidth: hasConnections ? 2 : 3,
                        cornerRadius: 2,
                        opacity: hasConnections ? 0.3 : 0.7,
                        listening: true
                    });
                    lifeRect.setAttr('timelineData', { timeline: timeline, timelineSpan: timelineSpan, isCurrentSpan: isCurrentSpan, mode: mode });
                    lifeRect.on('mouseenter mousemove', function(evt) {
                        if (!tooltipLocked) {
                            var pos = stage.getPointerPosition();
                            if (!pos) return;
                            var hoverYear = Math.round(valueForX(pos.x));
                            var html = mode === 'relative' ? '<strong>At age ' + hoverYear + '</strong>' : '<strong>In ' + hoverYear + '</strong>';
                            var acts = findActivitiesAtTime(hoverYear, timelineData, timeline.name, mode);
                            if (acts.length > 0) html += '<br/><br/>' + formatActivitiesWithDividers(acts, mode, hoverYear);
                            showTooltip(html, evt.evt);
                        }
                    });
                    lifeRect.on('mouseout', function() { if (!tooltipLocked) hideTooltip(); });
                    lifeRect.on('click', function(evt) {
                        var pos = stage.getPointerPosition();
                        if (!pos) return;
                        var hoverYear = Math.round(valueForX(pos.x));
                        var html = mode === 'relative' ? '<strong>At age ' + hoverYear + '</strong>' : '<strong>In ' + hoverYear + '</strong>';
                        var acts = findActivitiesAtTime(hoverYear, timelineData, timeline.name, mode);
                        if (acts.length > 0) html += '<br/><br/>' + formatActivitiesWithDividers(acts, mode, hoverYear);
                        lockTooltip(evt.evt, html);
                    });
                    barLayer.add(lifeRect);
                }

                if (timeline.timeline && timeline.timeline.connections) {
                    filterConnectionsDeep(timeline.timeline.connections)
                        .filter(function(c) { return shouldShowConnection(c.type_id); })
                        .forEach(function(connection) {
                            if (!timelineSpan || !timelineSpan.start_year) return;
                            var connType = connection.type_id;
                            if (connType === 'created') {
                                var connStart = mode === 'absolute' ? connection.start_year : connection.start_year - timelineSpan.start_year;
                                var x = xForValue(connStart);
                                var line = new Konva.Line({
                                    points: [x, swimlaneY, x, swimlaneY + swimlaneHeight],
                                    stroke: getConnectionColor(connType),
                                    strokeWidth: 2,
                                    opacity: 0.8,
                                    listening: true
                                });
                                line.setAttr('connectionData', { connection: connection, timeline: timeline, isCurrentSpan: isCurrentSpan, mode: mode, isDuring: false });
                                line.on('mouseenter mousemove', function(evt) {
                                    if (!tooltipLocked) {
                                        var pos = stage.getPointerPosition();
                                        var hoverYear = Math.round(valueForX(pos ? pos.x : 0));
                                        var html = showTooltipContent(evt.evt, [connection], timeline.name, isCurrentSpan, hoverYear, mode, false);
                                        showTooltip(html, evt.evt);
                                    }
                                });
                                line.on('mouseout', function() { if (!tooltipLocked) hideTooltip(); });
                                line.on('click', function(evt) {
                                    var pos = stage.getPointerPosition();
                                    var hoverYear = Math.round(valueForX(pos ? pos.x : 0));
                                    var html = showTooltipContent(evt.evt, [connection], timeline.name, isCurrentSpan, hoverYear, mode, false);
                                    lockTooltip(evt.evt, html);
                                });
                                barLayer.add(line);
                                var circle = new Konva.Circle({
                                    x: x,
                                    y: swimlaneY + swimlaneHeight / 2,
                                    radius: 3,
                                    fill: getConnectionColor(connType),
                                    stroke: 'white',
                                    strokeWidth: 1,
                                    opacity: 0.9,
                                    listening: true
                                });
                                circle.setAttr('connectionData', { connection: connection, timeline: timeline, isCurrentSpan: isCurrentSpan, mode: mode, isDuring: false });
                                circle.on('mouseenter mousemove', function(evt) {
                                    if (!tooltipLocked) {
                                        var pos = stage.getPointerPosition();
                                        var hoverYear = Math.round(valueForX(pos ? pos.x : 0));
                                        var html = showTooltipContent(evt.evt, [connection], timeline.name, isCurrentSpan, hoverYear, mode, false);
                                        showTooltip(html, evt.evt);
                                    }
                                });
                                circle.on('mouseout', function() { if (!tooltipLocked) hideTooltip(); });
                                circle.on('click', function(evt) {
                                    var pos = stage.getPointerPosition();
                                    var hoverYear = Math.round(valueForX(pos ? pos.x : 0));
                                    var html = showTooltipContent(evt.evt, [connection], timeline.name, isCurrentSpan, hoverYear, mode, false);
                                    lockTooltip(evt.evt, html);
                                });
                                barLayer.add(circle);
                            } else {
                                var connStart = mode === 'absolute' ? connection.start_year : connection.start_year - timelineSpan.start_year;
                                var connEnd = mode === 'absolute'
                                    ? (connection.end_year || new Date().getFullYear())
                                    : (connection.end_year ? connection.end_year - timelineSpan.start_year : new Date().getFullYear() - timelineSpan.start_year);
                                var barWidth = Math.max(1, xForValue(connEnd) - xForValue(connStart));
                                var bar = new Konva.Rect({
                                    x: xForValue(connStart),
                                    y: swimlaneY + 2,
                                    width: barWidth,
                                    height: swimlaneHeight - 4,
                                    fill: getConnectionColor(connType),
                                    stroke: 'white',
                                    strokeWidth: 1,
                                    cornerRadius: 2,
                                    opacity: 0.6,
                                    listening: true
                                });
                                bar.setAttr('connectionData', { connection: connection, timeline: timeline, isCurrentSpan: isCurrentSpan, mode: mode, isDuring: false });
                                bar.on('mouseenter mousemove', function(evt) {
                                    if (!tooltipLocked) {
                                        var pos = stage.getPointerPosition();
                                        var hoverYear = Math.round(valueForX(pos ? pos.x : 0));
                                        var html = showTooltipContent(evt.evt, [connection], timeline.name, isCurrentSpan, hoverYear, mode, false);
                                        showTooltip(html, evt.evt);
                                    }
                                });
                                bar.on('mouseout', function() { if (!tooltipLocked) hideTooltip(); });
                                bar.on('click', function(evt) {
                                    var pos = stage.getPointerPosition();
                                    var hoverYear = Math.round(valueForX(pos ? pos.x : 0));
                                    var html = showTooltipContent(evt.evt, [connection], timeline.name, isCurrentSpan, hoverYear, mode, false);
                                    lockTooltip(evt.evt, html);
                                });
                                barLayer.add(bar);
                            }
                        });
                }

                if (isCurrentSpan && timeline.roleOccupancies && timeline.roleOccupancies.length > 0 && shouldShowConnection('has_role')) {
                    var occupancies = timeline.roleOccupancies.filter(function(c) { return c.start_year; });
                    if (occupancies.length > 0 && timelineSpan) {
                        var baseYear = timelineSpan.start_year || Math.min.apply(null, occupancies.map(function(o) { return o.start_year; }));
                        occupancies.forEach(function(connection) {
                            var connStart = mode === 'absolute' ? connection.start_year : connection.start_year - baseYear;
                            var connEnd = mode === 'absolute'
                                ? (connection.end_year || new Date().getFullYear())
                                : (connection.end_year ? connection.end_year - baseYear : new Date().getFullYear() - baseYear);
                            var barWidth = Math.max(1, xForValue(connEnd) - xForValue(connStart));
                            var bar = new Konva.Rect({
                                x: xForValue(connStart),
                                y: swimlaneY + 2,
                                width: barWidth,
                                height: swimlaneHeight - 4,
                                fill: getConnectionColor('has_role'),
                                stroke: 'white',
                                strokeWidth: 1,
                                cornerRadius: 2,
                                opacity: 0.6,
                                listening: true
                            });
                            bar.setAttr('connectionData', { connection: connection, timeline: timeline, isCurrentSpan: true, mode: mode, isDuring: false });
                            bar.on('mouseenter mousemove', function(evt) {
                                if (!tooltipLocked) {
                                    var pos = stage.getPointerPosition();
                                    var hoverYear = Math.round(valueForX(pos ? pos.x : 0));
                                    var html = showTooltipContent(evt.evt, [connection], timeline.name, true, hoverYear, mode, false);
                                    showTooltip(html, evt.evt);
                                }
                            });
                            bar.on('mouseout', function() { if (!tooltipLocked) hideTooltip(); });
                            bar.on('click', function(evt) {
                                var pos = stage.getPointerPosition();
                                var hoverYear = Math.round(valueForX(pos ? pos.x : 0));
                                var html = showTooltipContent(evt.evt, [connection], timeline.name, true, hoverYear, mode, false);
                                lockTooltip(evt.evt, html);
                            });
                            barLayer.add(bar);
                        });
                    }
                }

                if (timeline.duringConnections && timeline.duringConnections.length > 0 && timelineSpan && timelineSpan.start_year) {
                    timeline.duringConnections
                        .filter(function(c) { return shouldShowConnection(c.type_id); })
                        .forEach(function(connection) {
                            var connStart = mode === 'absolute' ? connection.start_year : connection.start_year - timelineSpan.start_year;
                            var connEnd = mode === 'absolute'
                                ? (connection.end_year || new Date().getFullYear())
                                : (connection.end_year ? connection.end_year - timelineSpan.start_year : new Date().getFullYear() - timelineSpan.start_year);
                            var barWidth = Math.max(1, xForValue(connEnd) - xForValue(connStart));
                            var bar = new Konva.Rect({
                                x: xForValue(connStart),
                                y: swimlaneY + 4,
                                width: barWidth,
                                height: swimlaneHeight - 8,
                                fill: getConnectionColor(connection.type_id),
                                stroke: 'white',
                                strokeWidth: 1,
                                cornerRadius: 1,
                                opacity: 0.4,
                                listening: true
                            });
                            bar.setAttr('connectionData', { connection: connection, timeline: timeline, isCurrentSpan: isCurrentSpan, mode: mode, isDuring: true });
                            bar.on('mouseenter mousemove', function(evt) {
                                if (!tooltipLocked) {
                                    var pos = stage.getPointerPosition();
                                    var hoverYear = Math.round(valueForX(pos ? pos.x : 0));
                                    var html = showTooltipContent(evt.evt, [connection], timeline.name, isCurrentSpan, hoverYear, mode, true);
                                    showTooltip(html, evt.evt);
                                }
                            });
                            bar.on('mouseout', function() { if (!tooltipLocked) hideTooltip(); });
                            bar.on('click', function(evt) {
                                var pos = stage.getPointerPosition();
                                var hoverYear = Math.round(valueForX(pos ? pos.x : 0));
                                var html = showTooltipContent(evt.evt, [connection], timeline.name, isCurrentSpan, hoverYear, mode, true);
                                lockTooltip(evt.evt, html);
                            });
                            barLayer.add(bar);
                        });
                }

                var labelColour = mode === 'relative' ? 'white' : (isCurrentSpan ? '#1976d2' : (isCurrentUser ? '#000000' : '#495057'));
                var label = new Konva.Text({
                    x: margin.left + 8,
                    y: swimlaneY + swimlaneHeight / 2 - 6,
                    text: timeline.name,
                    fontSize: 11,
                    fontFamily: 'Arial',
                    fill: labelColour,
                    fontStyle: (isCurrentSpan || isCurrentUser) ? 'bold' : 'normal',
                    listening: true
                });
                if (timeline.id) {
                    label.on('click', function() {
                        window.location.href = '/spans/' + timeline.id;
                    });
                    label.on('mouseenter', function() {
                        this.setAttr('fill', '#007bff');
                        this.setAttr('textDecoration', 'underline');
                        document.body.style.cursor = 'pointer';
                        barLayer.draw();
                    });
                    label.on('mouseout', function() {
                        this.setAttr('fill', labelColour);
                        this.setAttr('textDecoration', '');
                        document.body.style.cursor = 'default';
                        barLayer.draw();
                    });
                }
                barLayer.add(label);

                // Add hide button (x) on the right side of the swimlane
                // Only show if there's more than one visible swimlane (can't hide the last one)
                if (visibleTimelineData.length > 1) {
                    var hideBtn = new Konva.Text({
                        x: width - margin.right - 16,
                        y: swimlaneY + swimlaneHeight / 2 - 6,
                        text: '×',
                        fontSize: 14,
                        fontFamily: 'Arial',
                        fill: '#adb5bd',
                        fontStyle: 'bold',
                        listening: true
                    });
                    (function(tid, tname, tIsCurrentSpan) {
                        hideBtn.on('click', function(evt) {
                            evt.cancelBubble = true;
                            hideSwimlane(tid, tname, tIsCurrentSpan);
                        });
                        hideBtn.on('mouseenter', function() {
                            this.setAttr('fill', '#dc3545');
                            document.body.style.cursor = 'pointer';
                            barLayer.draw();
                        });
                        hideBtn.on('mouseout', function() {
                            this.setAttr('fill', '#adb5bd');
                            document.body.style.cursor = 'default';
                            barLayer.draw();
                        });
                    })(timeline.id, timeline.name, isCurrentSpan);
                    barLayer.add(hideBtn);
                }
            });

            if (eventsOverlayVisible) {
                var eventsLaneIndex = 0;
                var eventsSwimlaneY = margin.top + eventsLaneIndex * (swimlaneHeight + swimlaneSpacing);
                var eventsBars = (eventsOverlayData && Array.isArray(eventsOverlayData.bars)) ? eventsOverlayData.bars : [];

                backgroundLayer.add(new Konva.Rect({
                    x: margin.left,
                    y: eventsSwimlaneY,
                    width: contentWidth,
                    height: swimlaneHeight,
                    fill: '#f0f4f8',
                    stroke: '#dee2e6',
                    strokeWidth: 1,
                    cornerRadius: 4
                }));

                var eventsLabel = new Konva.Text({
                    x: margin.left + 8,
                    y: eventsSwimlaneY + swimlaneHeight / 2 - 6,
                    text: 'Events',
                    fontSize: 11,
                    fontFamily: 'Arial',
                    fill: '#495057',
                    fontStyle: 'bold',
                    listening: false
                });
                barLayer.add(eventsLabel);

                if (eventsBars.length === 0) {
                    barLayer.add(new Konva.Text({
                        x: margin.left + 140,
                        y: eventsSwimlaneY + swimlaneHeight / 2 - 5,
                        text: 'No events in range',
                        fontSize: 10,
                        fontFamily: 'Arial',
                        fill: '#6c757d',
                        listening: false
                    }));
                }

                eventsBars.forEach(function(eventBar) {
                    var startYear = Math.max(
                        leaderClipRange ? leaderClipRange.start : timeRange.start,
                        eventBar.start_year
                    );
                    var endYear = Math.min(
                        leaderClipRange ? leaderClipRange.end : timeRange.end,
                        eventBar.end_year || new Date().getFullYear()
                    );
                    if (endYear < startYear) return;

                    var centreY = eventsSwimlaneY + swimlaneHeight / 2;
                    var eventId = eventBar.event_id;

                    if (eventBar.is_short) {
                        var centreX = xForValue((startYear + endYear) / 2);
                        var diamondSize = 6;
                        var diamond = new Konva.Line({
                            points: [
                                centreX, centreY - diamondSize,
                                centreX + diamondSize, centreY,
                                centreX, centreY + diamondSize,
                                centreX - diamondSize, centreY
                            ],
                            closed: true,
                            fill: '#c62828',
                            stroke: 'white',
                            strokeWidth: 1,
                            opacity: 0.9,
                            listening: true
                        });
                        if (eventId) {
                            diamond.on('dblclick dbltap', function() {
                                window.location.href = '/spans/' + eventId;
                            });
                        }
                        barLayer.add(diamond);
                    } else {
                        var rect = new Konva.Rect({
                            x: xForValue(startYear),
                            y: eventsSwimlaneY + 2,
                            width: Math.max(1, xForValue(endYear) - xForValue(startYear)),
                            height: swimlaneHeight - 4,
                            fill: contextOverlayBarColour,
                            stroke: 'white',
                            strokeWidth: 1,
                            cornerRadius: 2,
                            opacity: 0.85,
                            listening: true
                        });
                        if (eventId) {
                            rect.on('dblclick dbltap', function() {
                                window.location.href = '/spans/' + eventId;
                            });
                        }
                        barLayer.add(rect);
                    }
                });
            }

            if (pmOverlayVisible) {
                var pmLaneIndex = eventsOverlayVisible ? 1 : 0;
                var pmSwimlaneY = margin.top + pmLaneIndex * (swimlaneHeight + swimlaneSpacing);
                var pmBars = (pmOverlayData && Array.isArray(pmOverlayData.bars)) ? pmOverlayData.bars : [];

                backgroundLayer.add(new Konva.Rect({
                    x: margin.left,
                    y: pmSwimlaneY,
                    width: contentWidth,
                    height: swimlaneHeight,
                    fill: '#fff7e6',
                    stroke: '#dee2e6',
                    strokeWidth: 1,
                    cornerRadius: 4
                }));

                var pmLabel = new Konva.Text({
                    x: margin.left + 8,
                    y: pmSwimlaneY + swimlaneHeight / 2 - 6,
                    text: 'Prime Ministers',
                    fontSize: 11,
                    fontFamily: 'Arial',
                    fill: '#7a5200',
                    fontStyle: 'bold',
                    listening: false
                });
                barLayer.add(pmLabel);

                if (pmBars.length === 0) {
                    barLayer.add(new Konva.Text({
                        x: margin.left + 140,
                        y: pmSwimlaneY + swimlaneHeight / 2 - 5,
                        text: 'No PMs in range',
                        fontSize: 10,
                        fontFamily: 'Arial',
                        fill: '#6c757d',
                        listening: false
                    }));
                }

                pmBars.forEach(function(pmBar) {
                    var startYear = Math.max(
                        leaderClipRange ? leaderClipRange.start : timeRange.start,
                        pmBar.start_year
                    );
                    var endYear = Math.min(
                        leaderClipRange ? leaderClipRange.end : timeRange.end,
                        pmBar.end_year || new Date().getFullYear()
                    );
                    if (endYear < startYear) return;
                    var rect = new Konva.Rect({
                        x: xForValue(startYear),
                        y: pmSwimlaneY + 2,
                        width: Math.max(1, xForValue(endYear) - xForValue(startYear)),
                        height: swimlaneHeight - 4,
                        fill: contextOverlayBarColour,
                        stroke: 'white',
                        strokeWidth: 1,
                        cornerRadius: 2,
                        opacity: 0.85,
                        listening: true
                    });

                    if (pmBar.person_id) {
                        rect.on('dblclick dbltap', function() {
                            window.location.href = '/spans/' + pmBar.person_id;
                        });
                    }

                    barLayer.add(rect);
                });
            }

            if (presidentOverlayVisible) {
                var presidentLaneIndex = (eventsOverlayVisible ? 1 : 0) + (pmOverlayVisible ? 1 : 0);
                var presidentSwimlaneY = margin.top + presidentLaneIndex * (swimlaneHeight + swimlaneSpacing);
                var presidentBars = (presidentOverlayData && Array.isArray(presidentOverlayData.bars)) ? presidentOverlayData.bars : [];

                backgroundLayer.add(new Konva.Rect({
                    x: margin.left,
                    y: presidentSwimlaneY,
                    width: contentWidth,
                    height: swimlaneHeight,
                    fill: '#eef7ff',
                    stroke: '#dee2e6',
                    strokeWidth: 1,
                    cornerRadius: 4
                }));

                var presidentLabel = new Konva.Text({
                    x: margin.left + 8,
                    y: presidentSwimlaneY + swimlaneHeight / 2 - 6,
                    text: 'US Presidents',
                    fontSize: 11,
                    fontFamily: 'Arial',
                    fill: '#0b4f8a',
                    fontStyle: 'bold',
                    listening: false
                });
                barLayer.add(presidentLabel);

                if (presidentBars.length === 0) {
                    barLayer.add(new Konva.Text({
                        x: margin.left + 140,
                        y: presidentSwimlaneY + swimlaneHeight / 2 - 5,
                        text: 'No US Presidents in range',
                        fontSize: 10,
                        fontFamily: 'Arial',
                        fill: '#6c757d',
                        listening: false
                    }));
                }

                presidentBars.forEach(function(presidentBar) {
                    var startYear = Math.max(
                        leaderClipRange ? leaderClipRange.start : timeRange.start,
                        presidentBar.start_year
                    );
                    var endYear = Math.min(
                        leaderClipRange ? leaderClipRange.end : timeRange.end,
                        presidentBar.end_year || new Date().getFullYear()
                    );
                    if (endYear < startYear) return;
                    var rect = new Konva.Rect({
                        x: xForValue(startYear),
                        y: presidentSwimlaneY + 2,
                        width: Math.max(1, xForValue(endYear) - xForValue(startYear)),
                        height: swimlaneHeight - 4,
                        fill: contextOverlayBarColour,
                        stroke: 'white',
                        strokeWidth: 1,
                        cornerRadius: 2,
                        opacity: 0.85,
                        listening: true
                    });

                    if (presidentBar.person_id) {
                        rect.on('dblclick dbltap', function() {
                            window.location.href = '/spans/' + presidentBar.person_id;
                        });
                    }

                    barLayer.add(rect);
                });
            }

            var timelineTopY = margin.top;
            var timelineBottomY = margin.top + (totalSwimlanes * (swimlaneHeight + swimlaneSpacing)) - swimlaneSpacing;

            function showTimelineAreaTooltip(evt, hoverYear) {
                var tooltipContent = mode === 'relative'
                    ? '<strong>At age ' + hoverYear + '</strong>'
                    : '<strong>In ' + hoverYear + '</strong>';

                var concurrentActivities = findActivitiesAtTime(hoverYear, currentTimelineData || [], null, mode);
                if (concurrentActivities.length > 0) {
                    tooltipContent += '<br/><br/>' + formatActivitiesWithDividers(concurrentActivities, mode, hoverYear);
                } else {
                    tooltipContent += '<br/><br/><span style="color:#aaa;">No spans in view at this time</span>';
                }

                showTooltip(tooltipContent, evt);
            }

            // Global timeline-area tooltip trigger so leader lanes and empty areas
            // participate in the same concurrent-activity tooltip behaviour.
            stage.off('mousemove.timelineArea');
            stage.off('mouseleave.timelineArea');
            stage.off('click.timelineArea');

            stage.on('mousemove.timelineArea', function(evt) {
                if (tooltipLocked) return;

                var target = evt.target;
                var targetAttrs = target && target.attrs ? target.attrs : {};
                // Keep existing per-bar tooltip behaviour for connection bars.
                if (targetAttrs.connectionData || targetAttrs.timelineData) return;

                var pointer = stage.getPointerPosition();
                if (!pointer) return;
                if (pointer.x < margin.left || pointer.x > (width - margin.right)) {
                    hideTooltip();
                    return;
                }
                if (pointer.y < timelineTopY || pointer.y > timelineBottomY) {
                    hideTooltip();
                    return;
                }

                var hoverYear = Math.round(valueForX(pointer.x));
                showTimelineAreaTooltip(evt.evt, hoverYear);
            });

            stage.on('mouseleave.timelineArea', function() {
                if (!tooltipLocked) hideTooltip();
            });

            stage.on('click.timelineArea', function(evt) {
                if (tooltipLocked) return;

                var target = evt.target;
                var targetAttrs = target && target.attrs ? target.attrs : {};
                if (targetAttrs.connectionData || targetAttrs.timelineData) return;

                var pointer = stage.getPointerPosition();
                if (!pointer) return;
                if (pointer.x < margin.left || pointer.x > (width - margin.right)) return;
                if (pointer.y < timelineTopY || pointer.y > timelineBottomY) return;

                var hoverYear = Math.round(valueForX(pointer.x));
                var tooltipContent = mode === 'relative'
                    ? '<strong>At age ' + hoverYear + '</strong>'
                    : '<strong>In ' + hoverYear + '</strong>';
                var concurrentActivities = findActivitiesAtTime(hoverYear, currentTimelineData || [], null, mode);
                if (concurrentActivities.length > 0) {
                    tooltipContent += '<br/><br/>' + formatActivitiesWithDividers(concurrentActivities, mode, hoverYear);
                } else {
                    tooltipContent += '<br/><br/><span style="color:#aaa;">No spans in view at this time</span>';
                }
                lockTooltip(evt.evt, tooltipContent);
            });

            var axisY = adjustedHeight - margin.bottom;
            var tickCount = 10;
            for (var i = 0; i <= tickCount; i++) {
                var t = timeRange.start + (timeRange.end - timeRange.start) * i / tickCount;
                var x = xForValue(t);
                var labelText = mode === 'absolute' ? Math.round(t).toString() : 'Age ' + Math.round(t);
                axisLayer.add(new Konva.Line({
                    points: [x, axisY, x, axisY + 5],
                    stroke: '#333',
                    strokeWidth: 1
                }));
                axisLayer.add(new Konva.Text({
                    x: x - 15,
                    y: axisY + 8,
                    text: labelText,
                    fontSize: 10,
                    fontFamily: 'Arial',
                    fill: '#333',
                    width: 30,
                    align: 'center'
                }));
            }
            axisLayer.add(new Konva.Line({
                points: [margin.left, axisY, width - margin.right, axisY],
                stroke: '#333',
                strokeWidth: 1
            }));

            var nowX = null;
            var nowLabel = null;
            if (mode === 'absolute') {
                var nowYear = timeTravelDate ? new Date(timeTravelDate).getFullYear() : new Date().getFullYear();
                nowX = xForValue(nowYear);
                nowLabel = timeTravelDate ? (function() {
                    var d = new Date(timeTravelDate);
                    return d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
                })() : 'NOW';
            } else if (currentUserSpanId) {
                var userTimeline = timelineData.find(function(t) { return t.id === currentUserSpanId; });
                if (userTimeline && userTimeline.timeline && userTimeline.timeline.span && userTimeline.timeline.span.start_year) {
                    var userStart = userTimeline.timeline.span.start_year;
                    var userAge = new Date().getFullYear() - userStart;
                    nowX = xForValue(userAge);
                    nowLabel = 'NOW';
                }
            }
            if (nowX != null && nowX >= margin.left && nowX <= width - margin.right) {
                barLayer.add(new Konva.Line({
                    points: [nowX, margin.top, nowX, adjustedHeight - margin.bottom],
                    stroke: '#dc3545',
                    strokeWidth: 1,
                    opacity: 0.8,
                    listening: false
                }));
                barLayer.add(new Konva.Text({
                    x: nowX + 5,
                    y: margin.top + 5,
                    text: nowLabel,
                    fontSize: 10,
                    fontFamily: 'Arial',
                    fill: '#dc3545',
                    fontStyle: 'bold',
                    listening: false
                }));
            }

            if (!$tooltip) {
                $tooltip = jQuery('<div></div>').css({
                    position: 'absolute',
                    background: 'rgba(0, 0, 0, 0.8)',
                    color: 'white',
                    padding: '8px',
                    'border-radius': '4px',
                    'font-size': '12px',
                    'pointer-events': 'none',
                    opacity: 0,
                    'z-index': 9999,
                    'max-width': '320px'
                }).appendTo('body');
            }

            setupModeToggle(timelineData, currentSpan, currentUserSpanId);
            setupContextOverlayToggle();

            backgroundLayer.draw();
            barLayer.draw();
            axisLayer.draw();
        }

        function initializeCombinedTimeline() {
            var container = document.getElementById('timeline-combined-container-' + spanId);
            var spinner = document.getElementById('timeline-combined-spinner-' + spanId);
            if (!container) return;

            var spanFallback = { span: { id: spanId, name: spanName, start_year: null, end_year: null }, connections: [] };
            var connectionsFallback = { span: { id: spanId, name: spanName, start_year: null, end_year: null }, connections: [] };

            Promise.all([
                fetchJsonOrFallback('/api/spans/' + spanId, spanFallback),
                fetchJsonOrFallback('/api/spans/' + spanId + '/object-connections', connectionsFallback),
                fetchJsonOrFallback('/api/spans/' + spanId + '/subject-connections', connectionsFallback),
                fetchJsonOrFallback('/api/spans/' + spanId + '/during-connections', connectionsFallback)
            ]).then(function(results) {
                var currentSpanData = results[0];
                var objectConnectionsData = results[1];
                var subjectConnectionsData = results[2];
                var duringConnectionsData = results[3];

                if (!objectConnectionsData || !Array.isArray(objectConnectionsData.connections)) objectConnectionsData = connectionsFallback;
                if (!subjectConnectionsData || !Array.isArray(subjectConnectionsData.connections)) subjectConnectionsData = connectionsFallback;
                if (!duringConnectionsData || !Array.isArray(duringConnectionsData.connections)) duringConnectionsData = connectionsFallback;
                if (!currentSpanData || !currentSpanData.span) currentSpanData = spanFallback;

                var subjects = [];
                var seen = {};
                var combinedConnections = (objectConnectionsData.connections || []).concat(subjectConnectionsData.connections || []);
                combinedConnections.forEach(function(conn) {
                    if (conn.target_type === 'connection' || conn.target_type === 'note') return;
                    if (conn.target_type !== 'person') return;
                    if (conn.target_type === 'thing' && conn.target_metadata && (conn.target_metadata.subtype === 'photo' || conn.target_metadata.subtype === 'set')) return;
                    if (!conn.start_year) return;
                    if (!seen[conn.target_id]) {
                        seen[conn.target_id] = true;
                        subjects.push(conn.target_id);
                    }
                });

                var allSubjectIds = subjects.slice();
                var shouldIncludeUserSpan = currentUserSpanId && currentUserSpanId !== spanId;
                if (shouldIncludeUserSpan) allSubjectIds.push(currentUserSpanId);

                var timelineData = [];
                if (shouldIncludeUserSpan) {
                    timelineData.push({ id: currentUserSpanId, name: 'You', timeline: null, isCurrentSpan: false, isCurrentUser: true });
                }

                var roleOccupancies = (objectConnectionsData.connections || []).filter(function(c) { return c.type_id === 'has_role'; });
                timelineData.push({
                    id: spanId,
                    name: currentSpanData.span.name,
                    timeline: currentSpanData,
                    duringConnections: duringConnectionsData.connections || [],
                    roleOccupancies: roleOccupancies,
                    isCurrentSpan: true,
                    isCurrentUser: false
                });

                var subjectIdsToFetch = allSubjectIds.filter(function(id) { return id !== currentUserSpanId && id !== spanId; });
                var allIdsToFetch = shouldIncludeUserSpan ? [currentUserSpanId].concat(subjectIdsToFetch) : subjectIdsToFetch;

                function finishRender(td) {
                    var swimlaneHeight = 20;
                    var swimlaneSpacing = 10;
                    var swimlaneBottomMargin = 30;
                    var margin = { top: 8, right: 20, bottom: 30, left: 20 };
                    var totalSwimlanes = td.length;
                    var totalHeight = totalSwimlanes * (swimlaneHeight + swimlaneSpacing) - swimlaneSpacing + swimlaneBottomMargin;
                    var adjustedHeight = totalHeight + margin.top + margin.bottom;

                    container.style.height = '60px';
                    if (spinner) spinner.style.display = 'none';
                    container.style.height = adjustedHeight + 'px';

                    setTimeout(function() {
                        connectionTypeFilters = {};
                        hiddenSwimlanes = {};
                        updateSwimlanePanel();
                        renderKonvaTimeline(td, currentSpanData.span, 'absolute', currentUserSpanId);
                        setupModeToggle(td, currentSpanData.span, currentUserSpanId);
                    }, 50);
                }

                if (allIdsToFetch.length > 0) {
                    var BATCH_SIZE = 100;
                    var batches = [];
                    for (var i = 0; i < allIdsToFetch.length; i += BATCH_SIZE) {
                        batches.push(allIdsToFetch.slice(i, i + BATCH_SIZE));
                    }
                    var csrfToken = document.querySelector('meta[name="csrf-token"]') ? document.querySelector('meta[name="csrf-token"]').getAttribute('content') : '';

                    Promise.all(batches.map(function(batch) {
                        return fetch('/api/spans/batch-timeline', {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrfToken
                            },
                            body: JSON.stringify({ span_ids: batch })
                        }).then(function(r) {
                            if (!r.ok) throw new Error('Batch request failed');
                            return r.json();
                        }).then(function(data) {
                            return data.results || {};
                        }).catch(function() { return {}; });
                    })).then(function(batchResults) {
                        var allResults = {};
                        batchResults.forEach(function(br) { Object.assign(allResults, br); });

                        allIdsToFetch.forEach(function(sid) {
                            var result = allResults[sid];
                            if (!result) return;
                            var spanDatesPresent = result.span && (result.span.start_year || result.span.end_year);
                            if (!spanDatesPresent) return;
                            var isUser = sid === currentUserSpanId;
                            var conn = combinedConnections.find(function(c) { return c.target_id === sid; });
                            var name = isUser ? 'You' : (conn ? conn.target_name : (result.span ? result.span.name : 'Unknown'));
                            var entry = {
                                id: sid,
                                name: name,
                                timeline: { span: result.span, connections: result.connections || [] },
                                duringConnections: result.during_connections || [],
                                isCurrentSpan: false,
                                isCurrentUser: isUser
                            };
                            if (sid === currentUserSpanId && timelineData[0] && timelineData[0].isCurrentUser) {
                                timelineData[0] = entry;
                            } else if (sid !== spanId) {
                                timelineData.push(entry);
                            }
                        });

                        timelineData.forEach(function(t) {
                            if (t.timeline && t.timeline.connections) {
                                t.timeline.connections.forEach(function(c) {
                                    if (c.nested_connections) {
                                        if (!t.duringConnections) t.duringConnections = [];
                                        t.duringConnections = t.duringConnections.concat(c.nested_connections);
                                    }
                                });
                            }
                        });

                        finishRender(timelineData);
                    }).catch(function() {
                        finishRender(timelineData);
                    });
                } else {
                    finishRender(timelineData);
                }
            }).catch(function(err) {
                console.error('Error loading combined timeline data:', err);
                if (container) {
                    container.innerHTML = '<div class="text-danger text-center py-4">Error loading combined timeline data</div>';
                }
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(initializeCombinedTimeline, 100);
        });
    })();
    </script>
@endpush
