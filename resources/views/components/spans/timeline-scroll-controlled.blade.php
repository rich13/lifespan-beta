@props(['span', 'timeTravelDate' => null, 'timelineSeed' => null, 'personalTimelineSeed' => null])

@php
    $currentUserSpanId = optional(auth()->user())->personal_span_id;
@endphp

<div class="timeline-scroll-controlled-wrapper" data-span-id="{{ $span->id }}">
    @if($timelineSeed)
        <script type="application/json" id="timeline-seed-{{ $span->id }}">@json($timelineSeed)</script>
    @endif
    @if($personalTimelineSeed && isset($personalTimelineSeed['span']['span']['id']) && $personalTimelineSeed['span']['span']['id'] !== $span->id)
        <script type="application/json" id="timeline-seed-{{ $personalTimelineSeed['span']['span']['id'] }}">@json($personalTimelineSeed)</script>
    @endif
    <div class="d-flex justify-content-end align-items-center mb-1 px-2">
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
    </div>
    <div class="d-flex">
        <div id="timeline-scroll-container-{{ $span->id }}" style="height: 60px; flex: 1; position: relative; overflow: hidden; transition: height 0.3s cubic-bezier(.4,0,.2,1);">
            <div id="timeline-scroll-spinner-{{ $span->id }}" class="d-flex justify-content-center align-items-center h-100" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(255,255,255,0.7); z-index: 10;">
                <div class="spinner-border text-secondary" role="status" style="width: 2rem; height: 2rem;">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>
        </div>
        <div id="timeline-swimlane-panel-{{ $span->id }}" class="timeline-swimlane-panel ms-2" style="width: 160px; min-width: 160px; display: none; border-left: 1px solid #dee2e6; padding-left: 0.5rem; max-height: 300px; overflow-y: auto;">
            <div class="swimlane-panel-header" style="padding: 0.25rem 0; border-bottom: 1px solid #eee; margin-bottom: 0.5rem;">
                <small class="text-muted fw-bold">Hidden</small>
            </div>
            <div id="timeline-hidden-swimlanes-{{ $span->id }}" class="swimlane-panel-body" style="display: flex; flex-direction: column; gap: 0.25rem;">
            </div>
        </div>
    </div>
</div>

@push('scripts')
    @once
        <script src="https://unpkg.com/konva@9/konva.min.js"></script>
    @endonce
    <script>
    (function() {
        var spanId = '{{ $span->id }}';
        var currentUserSpanId = '{{ $currentUserSpanId }}';
        var timeTravelDate = @json($timeTravelDate);
        var spanName = @json($span->name ?? 'Unknown');

        var currentTimelineData = null;
        var allTimelineData = null;
        var currentSpan = null;
        var currentMode = 'absolute';
        var stage = null;
        var timeMarker = null;
        var timeMarkerLayer = null;
        var timeMarkerLabel = null;
        var timeMarkerLabelBg = null;
        var hiddenSwimlanes = {};
        var timelineContentStartX = 20;
        var timelineContentWidth = 800;

        var virtualScroll = 0;
        var scrollRange = 5000;
        var timeRange = { start: 1900, end: 2025 };
        var contentRange = { start: 1900, end: 2025 };
        var margin = { top: 24, right: 20, bottom: 30, left: 20 };

        function fetchJsonOrFallback(url, fallback) {
            return $.ajax({
                url: url,
                dataType: 'json'
            }).then(function(data) {
                return data;
            }, function() {
                return fallback;
            });
        }

        function loadTimelineSeedFor(id) {
            var raw = $('#timeline-seed-' + id).text();
            if (!raw) {
                return null;
            }
            try {
                return JSON.parse(raw);
            } catch (e) {
                return null;
            }
        }

        function loadTimelineSeed() {
            return loadTimelineSeedFor(spanId);
        }

        function swimlaneFromSeed(seed, id, name, isCurrentUser) {
            if (!seed || !seed.span || !seed.span.span) {
                return null;
            }

            var duringConnections = (seed.during_connections && seed.during_connections.connections) || [];
            (seed.span.connections || []).forEach(function(connection) {
                if (connection.nested_connections) {
                    duringConnections = duringConnections.concat(connection.nested_connections);
                }
            });

            return {
                id: id,
                name: name,
                timeline: { span: seed.span.span, connections: seed.span.connections || [] },
                duringConnections: duringConnections,
                isCurrentSpan: false,
                isCurrentUser: !!isCurrentUser
            };
        }

        function loadCurrentSpanTimelinePayloads() {
            var seed = loadTimelineSeed();
            var spanFallback = { span: { id: spanId, name: spanName, start_year: null, end_year: null }, connections: [] };
            var connectionsFallback = { span: { id: spanId, name: spanName, start_year: null, end_year: null }, connections: [] };

            if (seed && seed.span && seed.object_connections && seed.subject_connections && seed.during_connections) {
                return $.Deferred().resolve([
                    seed.span,
                    seed.object_connections,
                    seed.subject_connections,
                    seed.during_connections
                ]).promise();
            }

            return $.when(
                fetchJsonOrFallback('/api/spans/' + spanId, spanFallback),
                fetchJsonOrFallback('/api/spans/' + spanId + '/object-connections', connectionsFallback),
                fetchJsonOrFallback('/api/spans/' + spanId + '/subject-connections', connectionsFallback),
                fetchJsonOrFallback('/api/spans/' + spanId + '/during-connections', connectionsFallback)
            ).then(function(spanResult, objectResult, subjectResult, duringResult) {
                return [spanResult, objectResult, subjectResult, duringResult];
            });
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

        function calculateContentRange(timelineData, currentSpan) {
            var currentYear = new Date().getFullYear();
            var start = currentSpan.start_year || 1900;
            var end = currentSpan.end_year || currentYear;
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
                    if (ts.end_year && ts.end_year > end) end = ts.end_year;
                    else if (!ts.end_year && currentYear > end) end = currentYear;
                    if (timeline.timeline.connections) {
                        timeline.timeline.connections.forEach(function(c) {
                            if (c.start_year && c.start_year < start) start = c.start_year;
                            if (c.end_year && c.end_year > end) end = c.end_year;
                        });
                    }
                }
                if (timeline.duringConnections) {
                    timeline.duringConnections.forEach(function(c) {
                        if (c.start_year && c.start_year < start) start = c.start_year;
                        if (c.end_year && c.end_year > end) end = c.end_year;
                    });
                }
            });
            return { start: start, end: end };
        }

        function calculateCombinedTimeRange(timelineData, currentSpan) {
            var range = calculateContentRange(timelineData, currentSpan);
            var padding = Math.max(5, Math.floor((range.end - range.start) * 0.1));
            return { start: range.start - padding, end: range.end + padding };
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

        function findActivitiesAtTime(year, timelineData, currentTimelineName, mode) {
            var activities = [];
            if (mode === 'absolute') {
                timelineData.forEach(function(timeline) {
                    var timelineSpan = timeline.timeline && timeline.timeline.span ? timeline.timeline.span : null;
                    var spanStart = timelineSpan ? timelineSpan.start_year : null;
                    var spanEnd = timelineSpan ? (timelineSpan.end_year || new Date().getFullYear()) : null;
                    var alive = spanStart !== null && year >= spanStart && year <= spanEnd;
                    
                    if (spanStart !== null) {
                        activities.push({
                            timelineName: timeline.name,
                            type_name: 'Life span',
                            type_id: 'life-span',
                            target_name: '',
                            start_year: spanStart,
                            end_year: timelineSpan.end_year,
                            isCurrentSpan: timeline.isCurrentSpan,
                            isLifeSpan: true,
                            alive: alive,
                            span_id: timeline.id,
                            target_id: null
                        });
                    }
                    
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
                    
                    if (spanStart === null) {
                        activities.push({ timelineName: timeline.name, notAlive: true, isCurrentSpan: timeline.isCurrentSpan, span_id: timeline.id, target_id: null });
                    } else if (!alive) {
                        activities.push({ timelineName: timeline.name, notAlive: true, isCurrentSpan: timeline.isCurrentSpan, span_id: timeline.id, target_id: null });
                    } else if (!activities.some(function(a) { return a.timelineName === timeline.name && !a.isLifeSpan && !a.notAlive; })) {
                        activities.push({ timelineName: timeline.name, noActivity: true, isCurrentSpan: timeline.isCurrentSpan, span_id: timeline.id, target_id: null });
                    }
                });
            } else {
                timelineData.forEach(function(timeline) {
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
            return activities;
        }

        function formatActivitiesForDisplay(activities, mode, year) {
            if (!activities.length) {
                return '<div class="timeline-no-activity">No activities at this time</div>';
            }
            
            var grouped = {};
            var orderedKeys = [];
            activities.forEach(function(a) {
                if (!grouped[a.timelineName]) {
                    grouped[a.timelineName] = [];
                    orderedKeys.push(a.timelineName);
                }
                grouped[a.timelineName].push(a);
            });
            
            var columnCount = orderedKeys.length;
            var maxColumns = 6;
            var displayColumns = Math.min(columnCount, maxColumns);
            var html = '<div class="timeline-columns" style="display: grid; grid-template-columns: repeat(' + displayColumns + ', minmax(150px, 1fr)); gap: 0.75rem;">';
            
            orderedKeys.forEach(function(timelineName) {
                var acts = grouped[timelineName];
                var firstAct = acts.find(function(a) { return a.span_id; });
                var nameHtml = timelineName;
                if (firstAct && firstAct.span_id) {
                    nameHtml = '<a href="/spans/' + firstAct.span_id + '">' + timelineName + '</a>';
                }
                
                var lifeSpanAct = acts.find(function(a) { return a.isLifeSpan; });
                var ageBadge = '';
                if (lifeSpanAct) {
                    var birthYear = lifeSpanAct.start_year;
                    var deathYear = lifeSpanAct.end_year;
                    var currentYear = Math.round(year);
                    
                    if (currentYear < birthYear) {
                        var yearsBeforeBirth = birthYear - currentYear;
                        ageBadge = '<span class="timeline-age-badge timeline-age-unborn">' + yearsBeforeBirth + 'y before birth</span>';
                    } else if (deathYear && currentYear > deathYear) {
                        var yearsAfterDeath = currentYear - deathYear;
                        ageBadge = '<span class="timeline-age-badge timeline-age-deceased">' + yearsAfterDeath + 'y after death</span>';
                    } else {
                        var age = currentYear - birthYear;
                        ageBadge = '<span class="timeline-age-badge">Age ' + age + '</span>';
                    }
                }
                
                var isCurrentSpan = acts[0] && acts[0].isCurrentSpan;
                var columnClass = isCurrentSpan ? 'timeline-column timeline-column-primary' : 'timeline-column';
                
                html += '<div class="' + columnClass + '">';
                html += '<div class="timeline-column-header">' + nameHtml + ageBadge + '</div>';
                html += '<div class="timeline-column-content">';
                
                acts.forEach(function(activity) {
                    if (activity.isLifeSpan) return;
                    
                    var activityColor = activity.type_id ? getConnectionColor(activity.type_id) : '#6c757d';
                    
                    if (activity.notAlive || activity.noActivity) {
                        html += '<div class="timeline-activity-item timeline-activity-empty">';
                        html += '<span class="timeline-no-activity">No recorded activity</span>';
                    } else {
                        html += '<div class="timeline-activity-item" style="border-left: 3px solid ' + activityColor + ';">';
                        
                        var timeInfo = '';
                        if (mode === 'absolute') {
                            var endD = (activity.end_year == null) ? 'now' : activity.end_year;
                            timeInfo = activity.start_year + ' - ' + endD;
                        } else if (typeof activity.start_age !== 'undefined' && typeof activity.end_age !== 'undefined') {
                            var endD = (activity.end_age == null) ? 'now' : activity.end_age;
                            timeInfo = 'Age ' + activity.start_age + ' - ' + endD;
                        }
                        
                        var targetHtml = activity.target_name;
                        if (activity.target_id) {
                            targetHtml = '<a href="/spans/' + activity.target_id + '">' + activity.target_name + '</a>';
                        }
                        
                        html += '<span class="timeline-activity-type" style="color: ' + activityColor + ';">' + activity.type_name + '</span> ';
                        html += '<span class="timeline-activity-target">' + targetHtml + '</span>';
                        if (timeInfo) {
                            html += '<div class="timeline-activity-dates">' + timeInfo + '</div>';
                        }
                    
                        if (activity.nested_connections && activity.nested_connections.length > 0) {
                            activity.nested_connections.forEach(function(nc) {
                                var nEnd = nc.end_year || new Date().getFullYear();
                                if (nc.start_year <= year && nEnd >= year) {
                                    var ncColor = getConnectionColor(nc.type_id);
                                    html += '<div class="timeline-nested" style="border-left: 3px solid ' + ncColor + ';">';
                                    html += '<span class="timeline-activity-type" style="color: ' + ncColor + ';">' + nc.type_name + '</span> ';
                                    html += '<span class="timeline-activity-target">' + nc.target_name + '</span>';
                                    html += '</div>';
                                }
                            });
                        }
                    }
                    
                    html += '</div>';
                });
                
                html += '</div>';
                html += '</div>';
            });
            
            html += '</div>';
            return html;
        }

        function updateContentArea(year) {
            var activities = findActivitiesAtTime(year, currentTimelineData, null, currentMode);
            var html = formatActivitiesForDisplay(activities, currentMode, year);
            jQuery('#timeline-content-area').html(html);
        }

        function updateTimeMarker() {
            if (!timeMarker || !stage) return;
            
            var container = document.getElementById('timeline-scroll-container-' + spanId);
            if (!container) return;
            
            var progress = virtualScroll / scrollRange;
            var markerX = timelineContentStartX + (progress * timelineContentWidth);
            
            timeMarker.x(markerX);
            
            var yearSpan = timeRange.end - timeRange.start;
            var currentYear = timeRange.start + (progress * yearSpan);
            updateContentArea(currentYear);

            if (timeMarkerLabel && timeMarkerLabelBg) {
                var labelText = currentMode === 'relative'
                    ? 'Age ' + Math.round(currentYear)
                    : String(Math.round(currentYear));
                timeMarkerLabel.text(labelText);

                // Centre badge above marker, clamped within content area
                var paddingX = 6;
                var textWidth = timeMarkerLabel.width();
                var badgeWidth = textWidth + paddingX * 2;

                var stageWidth = stage ? stage.width() : (timelineContentStartX + timelineContentWidth);
                var minX = timelineContentStartX + paddingX;
                var maxX = stageWidth - 20 - paddingX - textWidth; // keep badge inside with some right margin

                var labelX = markerX - (textWidth / 2);
                if (labelX < minX) labelX = minX;
                if (labelX > maxX) labelX = maxX;

                timeMarkerLabel.x(labelX);
                timeMarkerLabelBg.x(labelX - paddingX);
                timeMarkerLabelBg.width(badgeWidth);

                timeMarkerLayer.batchDraw();
            }
        }

        function setupScrollHandler() {
            var page = document.querySelector('.timeline-view-page');
            if (!page) return;
            
            jQuery(page).on('wheel', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                var delta = e.originalEvent.deltaY;
                var sensitivity = 0.5;
                
                virtualScroll = Math.max(0, Math.min(scrollRange, virtualScroll + (delta * sensitivity)));
                updateTimeMarker();
            });

            var yearSpan = timeRange.end - timeRange.start;
            if (yearSpan > 0 && currentSpan && currentSpan.start_year) {
                var primarySpanStart = currentMode === 'absolute' 
                    ? currentSpan.start_year 
                    : 0;
                var startProgress = (primarySpanStart - timeRange.start) / yearSpan;
                virtualScroll = Math.max(0, Math.min(scrollRange, startProgress * scrollRange));
            } else {
                virtualScroll = 0;
            }
            updateTimeMarker();
        }

        function setupModeToggle(spanData, userSpanId) {
            var radios = document.querySelectorAll('input[name="timeline-mode-' + spanId + '"]');
            radios.forEach(function(radio) {
                radio.removeEventListener('change', radio._modeHandler);
                var handler = function() {
                    if (this.checked) {
                        currentMode = this.value;
                        renderKonvaTimeline(allTimelineData, spanData, this.value, userSpanId);
                    }
                };
                radio._modeHandler = handler;
                radio.addEventListener('change', handler);
            });
        }

        function isSwimlaneVisible(timelineId) {
            return !hiddenSwimlanes[timelineId];
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

        function reRenderTimeline() {
            if (allTimelineData && currentSpan) {
                renderKonvaTimeline(allTimelineData, currentSpan, currentMode, currentUserSpanId);
            }
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
                btn.style.cssText = 'display: block; width: 100%; text-align: left; padding: 0.2rem 0.4rem; font-size: 0.7rem; border: 1px solid #dee2e6; background-color: #f8f9fa; border-radius: 0.25rem; cursor: pointer; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;';
                btn.innerHTML = '<i class="bi bi-plus-circle me-1"></i>' + (lane.name || 'Unknown');
                btn.title = 'Click to show "' + (lane.name || 'Unknown') + '" on the timeline';
                btn.addEventListener('click', function() {
                    showSwimlane(id);
                });
                btn.addEventListener('mouseenter', function() {
                    this.style.backgroundColor = '#e9ecef';
                    this.style.borderColor = '#adb5bd';
                });
                btn.addEventListener('mouseleave', function() {
                    this.style.backgroundColor = '#f8f9fa';
                    this.style.borderColor = '#dee2e6';
                });
                hiddenContainer.appendChild(btn);
            });

            if (hiddenKeys.length > 1) {
                var showAllBtn = document.createElement('button');
                showAllBtn.type = 'button';
                showAllBtn.style.cssText = 'display: block; width: 100%; text-align: left; padding: 0.2rem 0.4rem; font-size: 0.7rem; border: 1px solid #dee2e6; background-color: #f8f9fa; border-radius: 0.25rem; cursor: pointer; margin-top: 0.5rem; font-weight: bold;';
                showAllBtn.innerHTML = '<i class="bi bi-eye me-1"></i>Show All';
                showAllBtn.title = 'Show all hidden swimlanes';
                showAllBtn.addEventListener('click', function() {
                    hiddenSwimlanes = {};
                    updateSwimlanePanel();
                    reRenderTimeline();
                });
                showAllBtn.addEventListener('mouseenter', function() {
                    this.style.backgroundColor = '#e9ecef';
                });
                showAllBtn.addEventListener('mouseleave', function() {
                    this.style.backgroundColor = '#f8f9fa';
                });
                hiddenContainer.appendChild(showAllBtn);
            }
        }

        function renderKonvaTimeline(timelineData, spanData, mode, userSpanId) {
            currentSpan = spanData;
            currentMode = mode;

            // Filter out hidden swimlanes
            var visibleTimelineData = timelineData.filter(function(timeline) {
                return isSwimlaneVisible(timeline.id);
            });
            currentTimelineData = visibleTimelineData;

            var container = document.getElementById('timeline-scroll-container-' + spanId);
            if (!container || !window.Konva) return;

            var width = container.clientWidth;
            var swimlaneHeight = 20;
            var swimlaneSpacing = 10;
            var swimlaneBottomMargin = 30;
            timeRange = mode === 'absolute'
                ? calculateCombinedTimeRange(visibleTimelineData, spanData)
                : calculateRelativeTimeRange(visibleTimelineData, spanData);
            
            if (mode === 'absolute') {
                contentRange = calculateContentRange(visibleTimelineData, spanData);
            } else {
                contentRange = { start: 0, end: timeRange.end - timeRange.start };
            }

            var totalSwimlanes = visibleTimelineData.length;
            var totalHeight = totalSwimlanes * (swimlaneHeight + swimlaneSpacing) - swimlaneSpacing + swimlaneBottomMargin;
            var adjustedHeight = totalHeight + margin.top + margin.bottom;

            container.style.height = adjustedHeight + 'px';
            container.innerHTML = '';

            if (stage) {
                stage.destroy();
                stage = null;
            }

            stage = new Konva.Stage({
                container: 'timeline-scroll-container-' + spanId,
                width: width,
                height: adjustedHeight
            });

            var backgroundLayer = new Konva.Layer();
            var barLayer = new Konva.Layer();
            var labelLayer = new Konva.Layer();
            var axisLayer = new Konva.Layer();
            timeMarkerLayer = new Konva.Layer();
            stage.add(backgroundLayer);
            stage.add(barLayer);
            stage.add(labelLayer);
            stage.add(axisLayer);
            stage.add(timeMarkerLayer);

            var labelColumnWidth = 140;
            var contentStartX = margin.left + labelColumnWidth;
            var contentWidth = width - contentStartX - margin.right;
            timelineContentStartX = contentStartX;
            timelineContentWidth = contentWidth;

            var yearSpan = Math.max(1, timeRange.end - timeRange.start);

            function xForValue(v) {
                return contentStartX + (v - timeRange.start) / yearSpan * contentWidth;
            }

            visibleTimelineData.forEach(function(timeline, index) {
                var swimlaneY = margin.top + index * (swimlaneHeight + swimlaneSpacing);
                var isCurrentSpan = timeline.isCurrentSpan;
                var isCurrentUser = userSpanId && timeline.id === userSpanId;
                var timelineSpan = timeline.timeline && timeline.timeline.span ? timeline.timeline.span : null;

                var bgFill = isCurrentSpan ? '#e3f2fd' : '#f8f9fa';
                var bgStroke = isCurrentUser ? '#000000' : '#dee2e6';
                backgroundLayer.add(new Konva.Rect({
                    x: contentStartX,
                    y: swimlaneY,
                    width: contentWidth,
                    height: swimlaneHeight,
                    fill: bgFill,
                    stroke: bgStroke,
                    strokeWidth: 1,
                    cornerRadius: 4
                }));

                var labelText = timeline.name || 'Unknown';
                if (labelText.length > 20) {
                    labelText = labelText.substring(0, 18) + '...';
                }
                var labelBgWidth = labelColumnWidth - 4;
                labelLayer.add(new Konva.Rect({
                    x: margin.left + 2,
                    y: swimlaneY + 2,
                    width: labelBgWidth,
                    height: swimlaneHeight - 4,
                    fill: 'rgba(255, 255, 255, 0.85)',
                    cornerRadius: 2,
                    listening: false
                }));
                
                labelLayer.add(new Konva.Text({
                    x: margin.left + 6,
                    y: swimlaneY + 4,
                    text: labelText,
                    fontSize: 11,
                    fontFamily: 'Arial, sans-serif',
                    fontStyle: isCurrentSpan ? 'bold' : 'normal',
                    fill: isCurrentSpan ? '#0d6efd' : '#333',
                    width: labelBgWidth - 8,
                    wrap: 'none',
                    ellipsis: true,
                    listening: false
                }));

                // Add hide button (×) on the right side of the swimlane
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
                            labelLayer.batchDraw();
                        });
                        hideBtn.on('mouseout', function() {
                            this.setAttr('fill', '#adb5bd');
                            document.body.style.cursor = 'default';
                            labelLayer.batchDraw();
                        });
                    })(timeline.id, timeline.name, isCurrentSpan);
                    labelLayer.add(hideBtn);
                }

                if (timelineSpan && timelineSpan.start_year) {
                    var lifeStart = mode === 'absolute' ? timelineSpan.start_year : 0;
                    var lifeEnd = mode === 'absolute'
                        ? (timelineSpan.end_year || new Date().getFullYear())
                        : (timelineSpan.end_year ? timelineSpan.end_year - timelineSpan.start_year : new Date().getFullYear() - timelineSpan.start_year);
                    var hasConnections = timeline.timeline.connections && timeline.timeline.connections.length > 0;

                    barLayer.add(new Konva.Rect({
                        x: xForValue(lifeStart),
                        y: swimlaneY + 2,
                        width: Math.max(2, xForValue(lifeEnd) - xForValue(lifeStart)),
                        height: swimlaneHeight - 4,
                        fill: '#000000',
                        stroke: 'white',
                        strokeWidth: hasConnections ? 2 : 3,
                        cornerRadius: 2,
                        opacity: hasConnections ? 0.3 : 0.7,
                        listening: false
                    }));
                }

                if (timeline.timeline && timeline.timeline.connections) {
                    filterConnectionsDeep(timeline.timeline.connections).forEach(function(connection) {
                        if (!timelineSpan || !timelineSpan.start_year) return;
                        var connType = connection.type_id;
                        if (connType === 'created') {
                            var connStart = mode === 'absolute' ? connection.start_year : connection.start_year - timelineSpan.start_year;
                            var x = xForValue(connStart);
                            barLayer.add(new Konva.Line({
                                points: [x, swimlaneY, x, swimlaneY + swimlaneHeight],
                                stroke: getConnectionColor(connType),
                                strokeWidth: 2,
                                opacity: 0.8,
                                listening: false
                            }));
                            barLayer.add(new Konva.Circle({
                                x: x,
                                y: swimlaneY + swimlaneHeight / 2,
                                radius: 3,
                                fill: getConnectionColor(connType),
                                stroke: 'white',
                                strokeWidth: 1,
                                opacity: 0.9,
                                listening: false
                            }));
                        } else {
                            var connStart = mode === 'absolute' ? connection.start_year : connection.start_year - timelineSpan.start_year;
                            var connEnd = mode === 'absolute'
                                ? (connection.end_year || new Date().getFullYear())
                                : (connection.end_year ? connection.end_year - timelineSpan.start_year : new Date().getFullYear() - timelineSpan.start_year);
                            var barWidth = Math.max(1, xForValue(connEnd) - xForValue(connStart));
                            barLayer.add(new Konva.Rect({
                                x: xForValue(connStart),
                                y: swimlaneY + 2,
                                width: barWidth,
                                height: swimlaneHeight - 4,
                                fill: getConnectionColor(connType),
                                stroke: 'white',
                                strokeWidth: 1,
                                cornerRadius: 2,
                                opacity: 0.6,
                                listening: false
                            }));
                        }
                    });
                }

                if (isCurrentSpan && timeline.roleOccupancies && timeline.roleOccupancies.length > 0) {
                    var occupancies = timeline.roleOccupancies.filter(function(c) { return c.start_year; });
                    if (occupancies.length > 0 && timelineSpan) {
                        var baseYear = timelineSpan.start_year || Math.min.apply(null, occupancies.map(function(o) { return o.start_year; }));
                        occupancies.forEach(function(connection) {
                            var connStart = mode === 'absolute' ? connection.start_year : connection.start_year - baseYear;
                            var connEnd = mode === 'absolute'
                                ? (connection.end_year || new Date().getFullYear())
                                : (connection.end_year ? connection.end_year - baseYear : new Date().getFullYear() - baseYear);
                            var barWidth = Math.max(1, xForValue(connEnd) - xForValue(connStart));
                            barLayer.add(new Konva.Rect({
                                x: xForValue(connStart),
                                y: swimlaneY + 2,
                                width: barWidth,
                                height: swimlaneHeight - 4,
                                fill: getConnectionColor('has_role'),
                                stroke: 'white',
                                strokeWidth: 1,
                                cornerRadius: 2,
                                opacity: 0.6,
                                listening: false
                            }));
                        });
                    }
                }

                if (timeline.duringConnections && timeline.duringConnections.length > 0 && timelineSpan && timelineSpan.start_year) {
                    timeline.duringConnections.forEach(function(connection) {
                        var connStart = mode === 'absolute' ? connection.start_year : connection.start_year - timelineSpan.start_year;
                        var connEnd = mode === 'absolute'
                            ? (connection.end_year || new Date().getFullYear())
                            : (connection.end_year ? connection.end_year - timelineSpan.start_year : new Date().getFullYear() - timelineSpan.start_year);
                        var barWidth = Math.max(1, xForValue(connEnd) - xForValue(connStart));
                        barLayer.add(new Konva.Rect({
                            x: xForValue(connStart),
                            y: swimlaneY + 4,
                            width: barWidth,
                            height: swimlaneHeight - 8,
                            fill: getConnectionColor(connection.type_id),
                            stroke: 'white',
                            strokeWidth: 1,
                            cornerRadius: 1,
                            opacity: 0.4,
                            listening: false
                        }));
                    });
                }
            });

            var tickInterval = yearSpan > 200 ? 50 : (yearSpan > 100 ? 25 : (yearSpan > 50 ? 10 : 5));
            var labelInterval = yearSpan > 100 ? tickInterval * 2 : tickInterval;
            var startTick = Math.ceil(timeRange.start / tickInterval) * tickInterval;
            var axisY = adjustedHeight - margin.bottom + 5;
            
            for (var tick = startTick; tick <= timeRange.end; tick += tickInterval) {
                var tickX = xForValue(tick);
                var isLabel = tick % labelInterval === 0;
                axisLayer.add(new Konva.Line({
                    points: [tickX, axisY, tickX, axisY + (isLabel ? 8 : 4)],
                    stroke: '#aaa',
                    strokeWidth: 1
                }));
                if (isLabel) {
                    axisLayer.add(new Konva.Text({
                        x: tickX,
                        y: axisY + 10,
                        text: String(tick),
                        fontSize: 10,
                        fontFamily: 'Arial',
                        fill: '#666',
                        offsetX: 10,
                        listening: false
                    }));
                }
            }

            axisLayer.add(new Konva.Line({
                points: [contentStartX, axisY, width - margin.right, axisY],
                stroke: '#aaa',
                strokeWidth: 1
            }));

            var markerTriangle = new Konva.RegularPolygon({
                x: contentStartX,
                y: margin.top - 6,
                sides: 3,
                radius: 8,
                fill: '#007bff',
                rotation: 180,
                listening: false
            });
            timeMarkerLayer.add(markerTriangle);

            var markerLine = new Konva.Line({
                points: [contentStartX, margin.top - 3, contentStartX, adjustedHeight - margin.bottom + 5],
                stroke: '#007bff',
                strokeWidth: 3,
                opacity: 0.9,
                lineCap: 'round',
                listening: false
            });
            timeMarkerLayer.add(markerLine);

            // Badge background + label that move with the time marker, just above the triangle
            timeMarkerLabelBg = new Konva.Rect({
                x: contentStartX,
                y: margin.top - 24,
                width: 40,
                height: 16,
                fill: '#007bff',
                cornerRadius: 8,
                listening: false
            });
            timeMarkerLayer.add(timeMarkerLabelBg);

            timeMarkerLabel = new Konva.Text({
                x: contentStartX,
                y: margin.top - 22,
                text: '',
                fontSize: 11,
                fontFamily: 'Arial, sans-serif',
                fill: '#ffffff',
                fontStyle: 'bold',
                listening: false
            });
            timeMarkerLayer.add(timeMarkerLabel);

            var markerLineTop = margin.top - 3;
            var markerLineBottom = adjustedHeight - margin.bottom + 5;
            
            timeMarker = {
                currentX: contentStartX,
                line: markerLine,
                triangle: markerTriangle,
                x: function(val) {
                    if (val === undefined) return this.currentX;
                    this.currentX = val;
                    this.line.points([val, markerLineTop, val, markerLineBottom]);
                    this.triangle.x(val);
                    return this;
                }
            };

            backgroundLayer.draw();
            barLayer.draw();
            labelLayer.draw();
            axisLayer.draw();
            timeMarkerLayer.draw();

            setupScrollHandler();
        }

        function initializeScrollTimeline() {
            var container = document.getElementById('timeline-scroll-container-' + spanId);
            var spinner = document.getElementById('timeline-scroll-spinner-' + spanId);
            if (!container) return;

            var spanFallback = { span: { id: spanId, name: spanName, start_year: null, end_year: null }, connections: [] };
            var connectionsFallback = { span: { id: spanId, name: spanName, start_year: null, end_year: null }, connections: [] };

            loadCurrentSpanTimelinePayloads().then(function(results) {
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
                
                var allowedTypes = ['person', 'organisation', 'band', 'place', 'event', 'thing', 'concept'];
                
                combinedConnections.forEach(function(conn) {
                    if (conn.target_type === 'connection' || conn.target_type === 'note') return;
                    if (allowedTypes.indexOf(conn.target_type) === -1) return;
                    if (conn.target_type === 'thing' && conn.target_metadata && (conn.target_metadata.subtype === 'photo' || conn.target_metadata.subtype === 'set')) return;
                    if (!seen[conn.target_id]) {
                        seen[conn.target_id] = true;
                        subjects.push(conn.target_id);
                    }
                });

                var allSubjectIds = subjects.slice();
                var shouldIncludeUserSpan = currentUserSpanId && currentUserSpanId !== spanId;
                if (shouldIncludeUserSpan) allSubjectIds.push(currentUserSpanId);

                var timelineData = [];
                var personalSeed = shouldIncludeUserSpan ? loadTimelineSeedFor(currentUserSpanId) : null;
                var personalSwimlane = swimlaneFromSeed(personalSeed, currentUserSpanId, 'You', true);
                if (shouldIncludeUserSpan) {
                    timelineData.push(personalSwimlane || { id: currentUserSpanId, name: 'You', timeline: null, isCurrentSpan: false, isCurrentUser: true });
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
                var allIdsToFetch = shouldIncludeUserSpan && !personalSwimlane ? [currentUserSpanId].concat(subjectIdsToFetch) : subjectIdsToFetch;

                function finishRender(td) {
                    allTimelineData = td;
                    
                    var swimlaneHeight = 20;
                    var swimlaneSpacing = 10;
                    var swimlaneBottomMargin = 30;
                    var totalSwimlanes = td.length;
                    var totalHeight = totalSwimlanes * (swimlaneHeight + swimlaneSpacing) - swimlaneSpacing + swimlaneBottomMargin;
                    var adjustedHeight = totalHeight + margin.top + margin.bottom;

                    container.style.height = '60px';
                    if (spinner) spinner.style.display = 'none';
                    container.style.height = adjustedHeight + 'px';

                    setTimeout(function() {
                        renderKonvaTimeline(td, currentSpanData.span, 'absolute', currentUserSpanId);
                        setupModeToggle(currentSpanData.span, currentUserSpanId);
                        setupFilterToggle(currentSpanData.span, currentUserSpanId);
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
                        return $.ajax({
                            url: '/api/spans/batch-timeline',
                            method: 'POST',
                            contentType: 'application/json',
                            dataType: 'json',
                            headers: {
                                'X-CSRF-TOKEN': csrfToken
                            },
                            data: JSON.stringify({ span_ids: batch })
                        }).then(function(data) {
                            return data.results || {};
                        }, function() {
                            return {};
                        });
                    })).then(function(batchResults) {
                        var allResults = {};
                        batchResults.forEach(function(br) { Object.assign(allResults, br); });

                        allIdsToFetch.forEach(function(sid) {
                        var result = allResults[sid];
                            if (!result) return;
                            var spanDatesPresent = result.span && (result.span.start_year || result.span.end_year);
                            if (!spanDatesPresent) return;
                            var isUser = sid === currentUserSpanId;
                            var matchingConns = combinedConnections.filter(function(c) { return c.target_id === sid; });
                            var conn = matchingConns[0];
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
                console.error('Error loading scroll timeline data:', err);
                if (container) {
                    container.innerHTML = '<div class="text-danger text-center py-4">Error loading timeline data</div>';
                }
            });
        }

        $(function() {
            setTimeout(initializeScrollTimeline, 100);
        });
    })();
    </script>
@endpush
