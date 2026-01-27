@props(['span' => null, 'spans' => null])

@php
    $currentUserSpanId = auth()->user()->personal_span_id ?? null;
    $groupMode = $spans && count($spans) > 0;
    $containerId = $groupMode ? 'timeline-group-' . md5(json_encode(collect($spans)->pluck('id')->toArray())) : 'timeline-combined-container-' . ($span ? $span->id : '');
@endphp

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">
            <i class="bi bi-people-fill me-2"></i>
            Timeline
        </h5>
        <div class="d-flex gap-2 align-items-center">
            @if($span)
                <a href="{{ route('spans.all-connections', $span) }}" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-clock-history me-1"></i>
                    Overview
                </a>
            @endif
            <div class="btn-group btn-group-sm" role="group">
                <input type="radio" class="btn-check" name="timeline-mode-{{ $containerId }}" id="absolute-mode-{{ $containerId }}" value="absolute" checked>
                <label class="btn btn-outline-primary" for="absolute-mode-{{ $containerId }}">
                    Absolute
                </label>
                <input type="radio" class="btn-check" name="timeline-mode-{{ $containerId }}" id="relative-mode-{{ $containerId }}" value="relative">
                <label class="btn btn-outline-primary" for="relative-mode-{{ $containerId }}">
                    Relative
                </label>
            </div>
        </div>
    </div>
    <div class="card-body">
        <!-- Timeline Legend/Key -->
        <div id="timeline-legend-{{ $containerId }}" class="timeline-legend mb-2" style="display: none;">
            <div class="d-flex justify-content-end align-items-center mb-1">
                <button type="button" class="btn btn-sm btn-outline-secondary" id="timeline-legend-reset-{{ $containerId }}" style="display: none; font-size: 0.75rem;">
                    Reset
                </button>
            </div>
            <div class="d-flex flex-wrap gap-2" id="timeline-legend-items-{{ $containerId }}">
                <!-- Legend items will be populated by JavaScript -->
            </div>
            <small class="text-muted d-block mt-1" style="font-size: 0.7rem;">
                <span class="text-muted">Click to isolate</span> • 
                <span class="text-muted">Double-click to hide</span>
            </small>
        </div>
        
        <div id="{{ $containerId }}" style="height: 300px; width: 100%; cursor: crosshair;">
            <!-- D3 group timeline will be rendered here -->
        </div>
    </div>
</div>

@push('scripts')
<script src="https://d3js.org/d3.v7.min.js"></script>
<script>
// Filter state for connection types: 'visible', 'isolated', or 'hidden'
let connectionTypeFilters_{{ str_replace('-', '_', $containerId) }} = {};
let currentTimelineData_{{ str_replace('-', '_', $containerId) }} = null;
let currentUserSpanId_{{ str_replace('-', '_', $containerId) }} = null;
let currentMode_{{ str_replace('-', '_', $containerId) }} = 'absolute';

document.addEventListener('DOMContentLoaded', function() {
    setTimeout(() => {
        initializeGroupTimeline_{{ str_replace('-', '_', $containerId) }}();
    }, 100);
});

function initializeGroupTimeline_{{ str_replace('-', '_', $containerId) }}() {
    const containerId = '{{ $containerId }}';
    const container = document.getElementById(containerId);
    const groupMode = {{ $groupMode ? 'true' : 'false' }};
    const currentUserSpanId = '{{ $currentUserSpanId }}';

    if (!container) {
        console.error('Group timeline container not found:', containerId);
        return;
    }

    if (groupMode) {
        // Group mode: fetch timeline data for each provided span
        const spans = @json(collect($spans)->map(function($s) {
            return [
                'id' => $s->id,
                'name' => $s->name,
            ];
        })->values()->toArray());

        // Fetch all timeline data in parallel
        Promise.all(spans.map(span =>
            fetch(`/api/spans/${span.id}`, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                .then(response => response.json())
                .then(data => ({
                    id: span.id,
                    name: span.name,
                    timeline: data,
                    isCurrentSpan: false,
                    isCurrentUser: false
                }))
        )).then(timelineData => {
            // Reset filters when new data loads
            connectionTypeFilters_{{ str_replace('-', '_', $containerId) }} = {};
            renderGroupTimeline_{{ str_replace('-', '_', $containerId) }}(timelineData, 'absolute', currentUserSpanId);
            setupModeToggle_{{ str_replace('-', '_', $containerId) }}(timelineData, currentUserSpanId);
            // Add reset button handler
            const resetButton = document.getElementById(`timeline-legend-reset-{{ $containerId }}`);
            if (resetButton) {
                resetButton.onclick = function() {
                    resetConnectionTypeFilters_{{ str_replace('-', '_', $containerId) }}();
                };
            }
        });
    } else {
        // Legacy mode: fallback to original timeline-combined-group logic
        const spanId = '{{ $span ? $span->id : '' }}';
        if (!spanId) {
            console.error('No span provided for legacy mode');
            return;
        }

        // Copy the original initialization logic here
        Promise.all([
            fetch(`/api/spans/${spanId}`, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } }).then(response => response.json()),
            fetch(`/api/spans/${spanId}/object-connections`, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } }).then(response => response.json()),
            fetch(`/api/spans/${spanId}/during-connections`, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } }).then(response => response.json())
        ])
        .then(([currentSpanData, objectConnectionsData, duringConnectionsData]) => {
            console.log('Fetched data for span:', spanId, {
                objectConnectionsData: objectConnectionsData,
                objectConnectionsCount: objectConnectionsData.connections?.length || 0,
                hasRoleConnections: objectConnectionsData.connections?.filter(conn => conn.type_id === 'has_role').length || 0
            });
            
            // Extract unique subjects from the object connections
            const subjects = [...new Set(
                objectConnectionsData.connections
                    .filter(conn => {
                        if (conn.target_type === 'connection') return false;
                        if (conn.type_id === 'created' && conn.target_type === 'thing' && conn.target_metadata?.subtype === 'photo') return false;
                        return true;
                    })
                    .map(conn => conn.target_id)
            )];
            
            const allSubjectIds = new Set(subjects);
            const shouldIncludeUserSpan = currentUserSpanId && currentUserSpanId !== spanId;
            
            if (shouldIncludeUserSpan) {
                allSubjectIds.add(currentUserSpanId);
            }
            
            let timelineData = [];
            
            if (shouldIncludeUserSpan) {
                timelineData.push({
                    id: currentUserSpanId,
                    name: 'You',
                    timeline: null,
                    isCurrentSpan: false,
                    isCurrentUser: true
                });
            }
            
            const roleOccupancies = objectConnectionsData.connections
                ? objectConnectionsData.connections.filter(conn => conn.type_id === 'has_role')
                : [];
            
            console.log('Role occupancies extracted:', roleOccupancies.length, roleOccupancies);

            timelineData.push({
                id: spanId,
                name: currentSpanData.span.name,
                timeline: currentSpanData,
                duringConnections: duringConnectionsData.connections || [],
                roleOccupancies: roleOccupancies,
                isCurrentSpan: true,
                isCurrentUser: false
            });
            
            console.log('Current span timeline data:', timelineData.find(t => t.isCurrentSpan));
            
            if (allSubjectIds.size > 0) {
                const subjectIdsToFetch = Array.from(allSubjectIds).filter(subjectId => 
                    subjectId !== currentUserSpanId && subjectId !== spanId
                );
                
                // Use batch endpoint for better performance (reduces 100+ requests to 1)
                const allIdsToFetch = shouldIncludeUserSpan 
                    ? [currentUserSpanId, ...subjectIdsToFetch]
                    : subjectIdsToFetch;
                
                // Batch endpoint supports up to 100 spans - split if needed
                const BATCH_SIZE = 100;
                const batches = [];
                for (let i = 0; i < allIdsToFetch.length; i += BATCH_SIZE) {
                    batches.push(allIdsToFetch.slice(i, i + BATCH_SIZE));
                }
                
                // Get CSRF token once
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                
                // Fetch all batches in parallel (each batch is one request)
                const batchPromises = batches.map(async (batch) => {
                    try {
                        const headers = {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        };
                        
                        // Add CSRF token if available (needed for Sanctum stateful API)
                        if (csrfToken) {
                            headers['X-CSRF-TOKEN'] = csrfToken;
                        }
                        
                        const response = await fetch('/api/spans/batch-timeline', {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: headers,
                            body: JSON.stringify({ span_ids: batch })
                        });
                        
                        if (!response.ok) {
                            const errorText = await response.text();
                            console.error(`Batch timeline request failed (${response.status}):`, errorText);
                            throw new Error(`HTTP ${response.status}: ${errorText.substring(0, 100)}`);
                        }
                        
                        const data = await response.json();
                        return data.results || {};
                    } catch (error) {
                        console.error('Error loading batch timeline for batch:', batch, error);
                        // Return empty object so we don't break the entire timeline
                        return {};
                    }
                });
                
                // Wait for all batches and combine results
                Promise.all(batchPromises)
                    .then(batchResults => {
                        const allResults = Object.assign({}, ...batchResults);
                        
                        // Convert results to the format expected by the timeline
                        const subjectData = allIdsToFetch
                            .map(spanId => {
                                const result = allResults[spanId];
                                if (!result) {
                                    return null;
                                }
                                
                                const isUser = spanId === currentUserSpanId;
                                return {
                                    id: spanId,
                                    name: isUser ? 'You' : (objectConnectionsData.connections.find(conn => conn.target_id === spanId)?.target_name || result.span?.name || 'Unknown'),
                                    timeline: {
                                        span: result.span,
                                        connections: result.connections || []
                                    },
                                    duringConnections: result.during_connections || [],
                                    roleOccupancies: [],
                                    isCurrentSpan: false,
                                    isCurrentUser: isUser
                                };
                            })
                            .filter(subject => subject !== null);
                        
                        // Update the timeline data with fetched data
                        if (shouldIncludeUserSpan) {
                            const userData = subjectData.find(subject => subject && subject.id === currentUserSpanId);
                            if (userData) {
                                timelineData[0] = userData;
                            }
                        }
                        
                        const otherSubjects = subjectData.filter(subject => 
                            subject && subject.id !== currentUserSpanId && subject.id !== spanId
                        );
                        timelineData.push(...otherSubjects);
                        
                        const allDuringConnections = [];
                        timelineData.forEach(timeline => {
                            if (timeline.duringConnections) {
                                allDuringConnections.push(...timeline.duringConnections);
                            }
                            if (timeline.timeline.connections) {
                                timeline.timeline.connections.forEach(connection => {
                                    if (connection.nested_connections) {
                                        allDuringConnections.push(...connection.nested_connections);
                                    }
                                });
                            }
                        });
                        
                        if (timelineData.length > 0) {
                            timelineData[0].duringConnections = allDuringConnections;
                        }
                        
                        // Reset filters when new data loads
                        connectionTypeFilters_{{ str_replace('-', '_', $containerId) }} = {};
                        renderGroupTimeline_{{ str_replace('-', '_', $containerId) }}(timelineData, 'absolute', currentUserSpanId);
                        setupModeToggle_{{ str_replace('-', '_', $containerId) }}(timelineData, currentUserSpanId);
                        // Add reset button handler
                        const resetButton = document.getElementById(`timeline-legend-reset-{{ $containerId }}`);
                        if (resetButton) {
                            resetButton.onclick = function() {
                                resetConnectionTypeFilters_{{ str_replace('-', '_', $containerId) }}();
                            };
                        }
                    })
                    .catch(error => {
                        console.error('Error loading subject timelines:', error);
                        // Reset filters when new data loads
                        connectionTypeFilters_{{ str_replace('-', '_', $containerId) }} = {};
                        renderGroupTimeline_{{ str_replace('-', '_', $containerId) }}(timelineData, 'absolute', currentUserSpanId);
                        setupModeToggle_{{ str_replace('-', '_', $containerId) }}(timelineData, currentUserSpanId);
                        // Add reset button handler
                        const resetButton = document.getElementById(`timeline-legend-reset-{{ $containerId }}`);
                        if (resetButton) {
                            resetButton.onclick = function() {
                                resetConnectionTypeFilters_{{ str_replace('-', '_', $containerId) }}();
                            };
                        }
                    });
            } else {
                // Reset filters when new data loads
                connectionTypeFilters_{{ str_replace('-', '_', $containerId) }} = {};
                renderGroupTimeline_{{ str_replace('-', '_', $containerId) }}(timelineData, 'absolute', currentUserSpanId);
                setupModeToggle_{{ str_replace('-', '_', $containerId) }}(timelineData, currentUserSpanId);
                // Add reset button handler
                const resetButton = document.getElementById(`timeline-legend-reset-{{ $containerId }}`);
                if (resetButton) {
                    resetButton.onclick = function() {
                        resetConnectionTypeFilters_{{ str_replace('-', '_', $containerId) }}();
                    };
                }
            }
        })
        .catch(error => {
            console.error('Error loading combined timeline data:', error);
            if (container) {
                container.innerHTML = '<div class="text-danger text-center py-4">Error loading combined timeline data</div>';
            }
        });
    }
}

function renderGroupTimeline_{{ str_replace('-', '_', $containerId) }}(timelineData, mode = 'absolute', currentUserSpanId = null) {
    const containerId = '{{ $containerId }}';
    
    // Store current state for filtering
    currentTimelineData_{{ str_replace('-', '_', $containerId) }} = timelineData;
    currentUserSpanId_{{ str_replace('-', '_', $containerId) }} = currentUserSpanId;
    currentMode_{{ str_replace('-', '_', $containerId) }} = mode;
    
    const container = document.getElementById(containerId);
    
    if (!container) {
        console.error('Group timeline container not found during render:', containerId);
        return;
    }
    
    const width = container.clientWidth;
    const margin = { top: 20, right: 20, bottom: 30, left: 20 };
    const swimlaneHeight = 20;
    const swimlaneSpacing = 10;
    const swimlaneBottomMargin = 30;
    const totalSwimlanes = timelineData.length;
    const totalHeight = totalSwimlanes * (swimlaneHeight + swimlaneSpacing) - swimlaneSpacing + swimlaneBottomMargin;
    const adjustedHeight = totalHeight + margin.top + margin.bottom;
    
    container.style.height = `${adjustedHeight}px`;
    container.innerHTML = '';
    const svg = d3.select(container)
        .append('svg')
        .attr('width', width)
        .attr('height', adjustedHeight);

    // Calculate global time range across all timelines based on mode
    const timeRange = mode === 'absolute' 
        ? calculateCombinedTimeRange_{{ str_replace('-', '_', $containerId) }}(timelineData)
        : calculateRelativeTimeRange_{{ str_replace('-', '_', $containerId) }}(timelineData);
    
    const xScale = d3.scaleLinear()
        .domain([timeRange.start, timeRange.end])
        .range([margin.left, width - margin.right]);

    const xAxis = d3.axisBottom(xScale)
        .tickFormat(mode === 'absolute' ? d3.format('d') : (d) => `Age ${d}`)
        .ticks(10);

    svg.append('g')
        .attr('transform', `translate(0, ${adjustedHeight - margin.bottom})`)
        .call(xAxis);

    function getConnectionColor(typeId) {
        const cssColor = getComputedStyle(document.documentElement)
            .getPropertyValue(`--connection-${typeId}-color`);
        if (cssColor && cssColor.trim() !== '') return cssColor.trim();
        const testElement = document.createElement('div');
        testElement.className = `bg-${typeId}`;
        testElement.style.display = 'none';
        document.body.appendChild(testElement);
        const computedStyle = getComputedStyle(testElement);
        const backgroundColor = computedStyle.backgroundColor;
        document.body.removeChild(testElement);
        if (backgroundColor && backgroundColor !== 'rgba(0, 0, 0, 0)' && backgroundColor !== 'transparent') return backgroundColor;
        const fallbackColors = {
            'residence': '#007bff', 'employment': '#28a745', 'education': '#ffc107', 'membership': '#dc3545',
            'family': '#6f42c1', 'relationship': '#fd7e14', 'travel': '#20c997', 'participation': '#e83e8c',
            'ownership': '#6c757d', 'created': '#17a2b8', 'contains': '#6610f2', 'has_role': '#fd7e14',
            'at_organisation': '#20c997', 'life': '#000000'
        };
        return fallbackColors[typeId] || '#6c757d';
    }

    const connectionColors = { 'life': '#000000' };

    // Create swimlanes for each timeline
    console.log('Rendering timeline data:', timelineData.length, 'timelines', timelineData.map(t => ({ name: t.name, isCurrentSpan: t.isCurrentSpan, hasRoleOccupancies: !!t.roleOccupancies, roleOccupanciesCount: t.roleOccupancies?.length || 0 })));
    timelineData.forEach((timeline, index) => {
        const swimlaneY = margin.top + index * (swimlaneHeight + swimlaneSpacing);
        const isCurrentSpan = timeline.isCurrentSpan;
        const isCurrentUser = currentUserSpanId && timeline.id === currentUserSpanId;
        console.log(`Timeline ${index}: ${timeline.name}, isCurrentSpan: ${isCurrentSpan}, roleOccupancies:`, timeline.roleOccupancies);
        
        // Draw swimlane background
        svg.append('rect')
            .attr('x', margin.left)
            .attr('y', swimlaneY)
            .attr('width', width - margin.left - margin.right)
            .attr('height', swimlaneHeight)
            .attr('fill', isCurrentSpan ? '#e3f2fd' : '#f8f9fa')
            .attr('stroke', isCurrentUser ? '#000000' : (isCurrentSpan ? '#dee2e6' : '#dee2e6'))
            .attr('stroke-width', isCurrentUser ? 1 : (isCurrentSpan ? 1 : 1))
            .attr('rx', 4)
            .attr('ry', 4);

        // Store label info for later rendering
        timeline.labelInfo = {
            name: timeline.name,
            id: timeline.id,
            swimlaneY: swimlaneY,
            isCurrentSpan: isCurrentSpan,
            isCurrentUser: isCurrentUser
        };

        // Add life span bar for this timeline
        const timelineSpan = timeline.timeline && timeline.timeline.span ? timeline.timeline.span : null;
        if (timelineSpan && timelineSpan.start_year) {
            const lifeStartYear = mode === 'absolute' ? timelineSpan.start_year : 0;
            const lifeEndYear = mode === 'absolute' 
                ? (timelineSpan.end_year || new Date().getFullYear())
                : (timelineSpan.end_year ? timelineSpan.end_year - timelineSpan.start_year : new Date().getFullYear() - timelineSpan.start_year);
            const hasConnections = timeline.timeline && timeline.timeline.connections && timeline.timeline.connections.length > 0;
            
            svg.append('rect')
                .attr('class', 'life-span')
                .attr('x', xScale(lifeStartYear))
                .attr('y', swimlaneY + 2)
                .attr('width', xScale(lifeEndYear) - xScale(lifeStartYear))
                .attr('height', swimlaneHeight - 4)
                .attr('fill', connectionColors.life)
                .attr('stroke', 'white')
                .attr('stroke-width', hasConnections ? 2 : 3)
                .attr('rx', 2)
                .attr('ry', 2)
                .style('opacity', hasConnections ? 0.3 : 0.7)
                .style('pointer-events', 'auto')
                .on('mouseover', function(event) {
                    d3.select(this).style('opacity', 0.9);
                    updateLifeSpanTooltip_{{ str_replace('-', '_', $containerId) }}(event, timelineSpan, timelineData, timeline.name, isCurrentSpan, mode);
                })
                .on('mousemove', function(event) {
                    updateLifeSpanTooltip_{{ str_replace('-', '_', $containerId) }}(event, timelineSpan, timelineData, timeline.name, isCurrentSpan, mode);
                })
                .on('mouseout', function() {
                    d3.select(this).style('opacity', hasConnections ? 0.3 : 0.7);
                    hideCombinedTooltip_{{ str_replace('-', '_', $containerId) }}();
                });
        }

        // Add connections for this timeline
        if (timeline.timeline && timeline.timeline.connections) {
            timeline.timeline.connections
                .filter(connection => {
                    if (connection.target_type === 'connection') return false;
                    if (connection.type_id === 'created' && connection.target_type === 'thing' && connection.target_metadata?.subtype === 'photo') return false;
                    return true;
                })
                .filter(connection => shouldShowConnection_{{ str_replace('-', '_', $containerId) }}(connection.type_id))
                .forEach(connection => {
                const connectionType = connection.type_id;
                
                if (connectionType === 'created') {
                    const connectionStartYear = mode === 'absolute' ? connection.start_year : connection.start_year - timelineSpan.start_year;
                    const x = xScale(connectionStartYear);
                    const y1 = swimlaneY;
                    const y2 = swimlaneY + swimlaneHeight;
                    const circleY = (y1 + y2) / 2;
                    const circleRadius = 3;
                    
                    svg.append('line')
                        .attr('class', 'timeline-moment')
                        .attr('x1', x)
                        .attr('x2', x)
                        .attr('y1', y1)
                        .attr('y2', y2)
                        .attr('stroke', getConnectionColor(connectionType))
                        .attr('stroke-width', 2)
                        .style('opacity', 0.8)
                        .on('mouseover', function(event) {
                            d3.select(this).style('opacity', 0.9);
                            updateConnectionTooltip_{{ str_replace('-', '_', $containerId) }}(event, [connection], timeline.name, isCurrentSpan, mode);
                        })
                        .on('mousemove', function(event) {
                            updateConnectionTooltip_{{ str_replace('-', '_', $containerId) }}(event, [connection], timeline.name, isCurrentSpan, mode);
                        })
                        .on('mouseout', function() {
                            d3.select(this).style('opacity', 0.8);
                            hideCombinedTooltip_{{ str_replace('-', '_', $containerId) }}();
                        });
                    
                    svg.append('circle')
                        .attr('class', 'timeline-moment-circle')
                        .attr('cx', x)
                        .attr('cy', circleY)
                        .attr('r', circleRadius)
                        .attr('fill', getConnectionColor(connectionType))
                        .attr('stroke', 'white')
                        .attr('stroke-width', 1)
                        .style('opacity', 0.9)
                        .on('mouseover', function(event) {
                            d3.select(this).style('opacity', 1);
                            updateConnectionTooltip_{{ str_replace('-', '_', $containerId) }}(event, [connection], timeline.name, isCurrentSpan, mode);
                        })
                        .on('mousemove', function(event) {
                            updateConnectionTooltip_{{ str_replace('-', '_', $containerId) }}(event, [connection], timeline.name, isCurrentSpan, mode);
                        })
                        .on('mouseout', function() {
                            d3.select(this).style('opacity', 0.9);
                            hideCombinedTooltip_{{ str_replace('-', '_', $containerId) }}();
                        });
                } else {
                    const connectionStartYear = mode === 'absolute' ? connection.start_year : connection.start_year - timelineSpan.start_year;
                    const connectionEndYear = mode === 'absolute' 
                        ? (connection.end_year || new Date().getFullYear())
                        : (connection.end_year ? connection.end_year - timelineSpan.start_year : new Date().getFullYear() - timelineSpan.start_year);
                    const connectionWidth = xScale(connectionEndYear) - xScale(connectionStartYear);
                    
                    svg.append('rect')
                        .attr('class', 'timeline-bar')
                        .attr('x', xScale(connectionStartYear))
                        .attr('y', swimlaneY + 2)
                        .attr('width', Math.max(1, connectionWidth))
                        .attr('height', swimlaneHeight - 4)
                        .attr('fill', getConnectionColor(connectionType))
                        .attr('stroke', 'white')
                        .attr('stroke-width', 1)
                        .attr('rx', 2)
                        .attr('ry', 2)
                        .style('opacity', 0.6)
                        .on('mouseover', function(event) {
                            d3.select(this).style('opacity', 0.9);
                            updateConnectionTooltip_{{ str_replace('-', '_', $containerId) }}(event, [connection], timeline.name, isCurrentSpan, mode);
                        })
                        .on('mousemove', function(event) {
                            updateConnectionTooltip_{{ str_replace('-', '_', $containerId) }}(event, [connection], timeline.name, isCurrentSpan, mode);
                        })
                        .on('mouseout', function() {
                            d3.select(this).style('opacity', 0.6);
                            hideCombinedTooltip_{{ str_replace('-', '_', $containerId) }}();
                        });
                }
            });
        }

        // Render aggregated role occupancy bars for the current span if available
        if (isCurrentSpan) {
            console.log('Checking role occupancies for current span:', {
                hasRoleOccupancies: !!timeline.roleOccupancies,
                roleOccupanciesLength: timeline.roleOccupancies?.length || 0,
                roleOccupancies: timeline.roleOccupancies,
                timelineName: timeline.name,
                timelineId: timeline.id
            });
        }
        if (isCurrentSpan && timeline.roleOccupancies && timeline.roleOccupancies.length > 0 && shouldShowConnection_{{ str_replace('-', '_', $containerId) }}('has_role')) {
            const occupancyColor = getConnectionColor('has_role');
            const occupancies = timeline.roleOccupancies.filter(conn => conn.start_year);
            console.log('Filtered occupancies with start_year:', occupancies.length, occupancies);
            
            if (occupancies.length > 0) {
                const maxVisibleBars = Math.max(1, Math.min(occupancies.length, 6));
                const occupancyHeight = Math.max(4, (swimlaneHeight - 6) / maxVisibleBars);
                
                // For roles (which may be timeless), use the earliest occupancy year as base for relative mode
                const earliestYear = Math.min(...occupancies.map(occ => occ.start_year));

                occupancies.forEach((connection, occIndex) => {
                    // Use timeline span's start_year if available, otherwise use earliest occupancy year
                    const baseYear = (timelineSpan && timelineSpan.start_year) ? timelineSpan.start_year : earliestYear;

                    const startYear = mode === 'absolute'
                        ? connection.start_year
                        : (connection.start_year - baseYear);

                    let endYear = connection.end_year;
                    if (!endYear) {
                        endYear = mode === 'absolute'
                            ? new Date().getFullYear()
                            : (new Date().getFullYear() - baseYear);
                    } else if (mode !== 'absolute') {
                        endYear = endYear - baseYear;
                    }

                    const xStart = xScale(startYear);
                    let xEnd = xScale(endYear);

                    if (xEnd <= xStart) {
                        xEnd = xStart + 3; // ensure visible width
                    }

                    const effectiveIndex = occIndex % maxVisibleBars;
                    const offsetMultiplier = Math.floor(occIndex / maxVisibleBars);
                    const occupancyY = swimlaneY + 3 + effectiveIndex * (occupancyHeight + 2);

                    svg.append('rect')
                        .attr('class', 'role-occupancy')
                        .attr('x', xStart)
                        .attr('y', occupancyY + offsetMultiplier)
                        .attr('width', xEnd - xStart)
                        .attr('height', occupancyHeight)
                        .attr('fill', occupancyColor)
                        .attr('stroke', '#ffffff')
                        .attr('stroke-width', 1)
                        .style('opacity', 0.7)
                        .style('pointer-events', 'auto')
                        .on('mouseover', function(event) {
                            d3.select(this).style('opacity', 0.95);
                            updateConnectionTooltip_{{ str_replace('-', '_', $containerId) }}(event, [connection], timeline.name, isCurrentSpan, mode);
                        })
                        .on('mousemove', function(event) {
                            updateConnectionTooltip_{{ str_replace('-', '_', $containerId) }}(event, [connection], timeline.name, isCurrentSpan, mode);
                        })
                        .on('mouseout', function() {
                            d3.select(this).style('opacity', 0.7);
                            hideCombinedTooltip_{{ str_replace('-', '_', $containerId) }}();
                        });
                });
            }
        }

        // Add "during" connections for this timeline
        if (timeline.duringConnections && timeline.duringConnections.length > 0 && index === 0) {
            timeline.duringConnections
                .filter(connection => shouldShowConnection_{{ str_replace('-', '_', $containerId) }}(connection.type_id))
                .forEach(connection => {
                const connectionStartYear = mode === 'absolute' ? connection.start_year : connection.start_year - timelineSpan.start_year;
                const connectionEndYear = mode === 'absolute' 
                    ? (connection.end_year || new Date().getFullYear())
                    : (connection.end_year ? connection.end_year - timelineSpan.start_year : new Date().getFullYear() - timelineSpan.start_year);
                const connectionWidth = xScale(connectionEndYear) - xScale(connectionStartYear);
                
                svg.append('rect')
                    .attr('class', 'timeline-during-bar')
                    .attr('x', xScale(connectionStartYear))
                    .attr('y', swimlaneY + 4)
                    .attr('width', Math.max(1, connectionWidth))
                    .attr('height', swimlaneHeight - 8)
                    .attr('fill', getConnectionColor(connection.type_id))
                    .attr('stroke', 'white')
                    .attr('stroke-width', 1)
                    .attr('rx', 1)
                    .attr('ry', 1)
                    .style('opacity', 0.4)
                    .on('mouseover', function(event) {
                        d3.select(this).style('opacity', 1);
                        updateConnectionTooltip_{{ str_replace('-', '_', $containerId) }}(event, [connection], timeline.name, isCurrentSpan, mode, true);
                    })
                    .on('mousemove', function(event) {
                        updateConnectionTooltip_{{ str_replace('-', '_', $containerId) }}(event, [connection], timeline.name, isCurrentSpan, mode, true);
                    })
                    .on('mouseout', function() {
                        d3.select(this).style('opacity', 0.4);
                        hideCombinedTooltip_{{ str_replace('-', '_', $containerId) }}();
                    });
            });
        }
    });

    // Add "now" line (current year) - only in absolute mode
    if (mode === 'absolute') {
        const currentYear = new Date().getFullYear();
        const nowX = xScale(currentYear);
        
        if (nowX >= margin.left && nowX <= width - margin.right) {
            svg.append('line')
                .attr('class', 'now-line')
                .attr('x1', nowX)
                .attr('x2', nowX)
                .attr('y1', margin.top)
                .attr('y2', adjustedHeight - margin.bottom)
                .attr('stroke', '#dc3545')
                .attr('stroke-width', 1)
                .style('opacity', 0.8);
            
            svg.append('text')
                .attr('class', 'now-label')
                .attr('x', nowX + 5)
                .attr('y', margin.top + 15)
                .attr('text-anchor', 'start')
                .attr('font-size', '10px')
                .attr('font-weight', 'bold')
                .attr('fill', '#dc3545')
                .text('NOW');
        }
    }

    // Add swimlane labels
    timelineData.forEach((timeline) => {
        if (timeline.labelInfo) {
            const { name, id, swimlaneY, isCurrentSpan, isCurrentUser } = timeline.labelInfo;
            
            // Create link element for the label
            const link = svg.append('a')
                .attr('href', id ? `/spans/${id}` : '#')
                .attr('class', 'swimlane-label-link')
                .style('cursor', id ? 'pointer' : 'default')
                .style('text-decoration', 'none');
            
            // Add swimlane label floating on top
            link.append('text')
                .attr('class', 'swimlane-label')
                .attr('x', margin.left + 8)
                .attr('y', swimlaneY + swimlaneHeight / 2 + 4)
                .attr('text-anchor', 'start')
                .attr('font-size', '11px')
                .attr('font-weight', (isCurrentSpan || isCurrentUser) ? 'bold' : 'normal')
                .attr('fill', mode === 'relative' ? 'white' : (isCurrentSpan ? '#1976d2' : (isCurrentUser ? '#000000' : '#495057')))
                .style('pointer-events', 'auto')
                .style('z-index', '1001')
                .on('mouseenter', function() {
                    if (id) {
                        d3.select(this).attr('fill', '#007bff').style('text-decoration', 'underline');
                    }
                })
                .on('mouseleave', function() {
                    if (id) {
                        d3.select(this).attr('fill', mode === 'relative' ? 'white' : (isCurrentSpan ? '#1976d2' : (isCurrentUser ? '#000000' : '#495057')))
                            .style('text-decoration', 'none');
                    }
                })
                .text(name);
        }
    });

    // Create tooltip
    const tooltip = d3.select('body').append('div')
        .attr('class', `combined-tooltip-${containerId}`)
        .style('position', 'absolute')
        .style('background', 'rgba(0, 0, 0, 0.8)')
        .style('color', 'white')
        .style('padding', '8px')
        .style('border-radius', '4px')
        .style('font-size', '12px')
        .style('pointer-events', 'none')
        .style('opacity', 0);

    function showCombinedTooltip_{{ str_replace('-', '_', $containerId) }}(event, connections, timelineName, isCurrentSpan, hoverYear, mode = 'absolute', isDuring = false) {
        tooltip.transition().duration(200).style('opacity', 1);
        let tooltipContent = '';
        
        if (mode === 'relative') {
            tooltipContent = `<strong>At age ${hoverYear}</strong>`;
        } else {
            tooltipContent = `<strong>In ${hoverYear}</strong>`;
        }
        
        if (connections && connections.length > 0) {
            const connection = connections[0];
            const connectionType = isDuring ? 'during' : connection.type_id;
            const targetName = connection.target_name || 'Unknown';
            const targetType = connection.target_type || 'unknown';
            
            tooltipContent += `<br/><br/>`;
            if (isDuring) {
                tooltipContent += `<strong>${targetName}</strong> (${targetType})<br/>`;
                tooltipContent += `<em>Phase during ${timelineName}'s activities</em>`;
            } else {
                tooltipContent += `<strong>${connectionType}</strong> ${targetName} (${targetType})`;
            }
            
            const startYear = connection.start_year;
            const endYear = connection.end_year || 'Present';
            if (mode === 'absolute') {
                tooltipContent += `<br/>${startYear} - ${endYear}`;
            } else {
                const startAge = startYear - (timelineData.find(t => t.name === timelineName)?.timeline?.span?.start_year || startYear);
                const endAge = endYear === 'Present' ? 'Present' : (endYear - (timelineData.find(t => t.name === timelineName)?.timeline?.span?.start_year || endYear));
                tooltipContent += `<br/>Age ${startAge} - ${endAge}`;
            }
        }
        
        const concurrentActivities = findActivitiesAtTime_{{ str_replace('-', '_', $containerId) }}(hoverYear, timelineData, timelineName, mode);
        if (concurrentActivities.length > 0) {
            tooltipContent += `<br/><br/>`;
            tooltipContent += formatActivitiesWithDividers_{{ str_replace('-', '_', $containerId) }}(concurrentActivities, mode, hoverYear);
        }
        
        tooltip.html(tooltipContent)
            .style('left', (event.pageX - 50) + 'px')
            .style('top', (event.pageY + 20) + 'px');
    }

    function findActivitiesAtTime_{{ str_replace('-', '_', $containerId) }}(year, timelineData, currentTimelineName, mode = 'absolute') {
        const activities = [];
        
        if (mode === 'absolute') {
            timelineData.forEach(timeline => {
                if (timeline.timeline.connections) {
                    timeline.timeline.connections
                        .filter(otherConnection => {
                            if (otherConnection.target_type === 'connection') return false;
                            if (otherConnection.type_id === 'created' && otherConnection.target_type === 'thing' && otherConnection.target_metadata?.subtype === 'photo') return false;
                            return true;
                        })
                        .forEach(otherConnection => {
                        const otherStart = otherConnection.start_year;
                        const otherEnd = otherConnection.end_year || new Date().getFullYear();
                        if (otherStart <= year && otherEnd >= year) {
                            activities.push({
                                timelineName: timeline.name,
                                type_name: otherConnection.type_name,
                                type_id: otherConnection.type_id,
                                target_name: otherConnection.target_name,
                                start_year: otherConnection.start_year,
                                end_year: otherConnection.end_year,
                                isCurrentSpan: timeline.isCurrentSpan,
                                nested_connections: otherConnection.nested_connections || []
                            });
                        }
                    });
                }
            });
        } else {
            timelineData.forEach(timeline => {
                let found = false;
                let lifeSpanIncluded = false;
                let timelineSpan = timeline.timeline.span;
                let lifeStartAge = 0;
                let lifeEndAge = null;
                if (timelineSpan && timelineSpan.start_year) {
                    lifeEndAge = (timelineSpan.end_year ? timelineSpan.end_year : new Date().getFullYear()) - timelineSpan.start_year;
                }
                let alive = (lifeEndAge !== null && year >= lifeStartAge && year <= lifeEndAge);
                if (lifeEndAge !== null) {
                    activities.push({
                        timelineName: timeline.name,
                        type_name: 'Life span',
                        type_id: 'life-span',
                        target_name: '',
                        start_age: lifeStartAge,
                        end_age: lifeEndAge,
                        isCurrentSpan: timeline.isCurrentSpan,
                        isLifeSpan: true,
                        alive: alive
                    });
                    lifeSpanIncluded = true;
                }
                if (timeline.timeline.connections) {
                    timeline.timeline.connections
                        .filter(otherConnection => {
                            if (otherConnection.target_type === 'connection') return false;
                            if (otherConnection.type_id === 'created' && otherConnection.target_type === 'thing' && otherConnection.target_metadata?.subtype === 'photo') return false;
                            return true;
                        })
                        .forEach(otherConnection => {
                        if (timelineSpan && timelineSpan.start_year) {
                            const otherStart = otherConnection.start_year - timelineSpan.start_year;
                            const otherEnd = otherConnection.end_year 
                                ? otherConnection.end_year - timelineSpan.start_year 
                                : new Date().getFullYear() - timelineSpan.start_year;
                            if (otherStart <= year && otherEnd >= year) {
                                activities.push({
                                    timelineName: timeline.name,
                                    type_name: otherConnection.type_name,
                                    type_id: otherConnection.type_id,
                                    target_name: otherConnection.target_name,
                                    start_year: otherConnection.start_year,
                                    end_year: otherConnection.end_year,
                                    start_age: otherStart,
                                    end_age: otherEnd,
                                    isCurrentSpan: timeline.isCurrentSpan,
                                    nested_connections: otherConnection.nested_connections || []
                                });
                                found = true;
                            }
                        }
                    });
                }
                if (!lifeSpanIncluded) {
                    activities.push({
                        timelineName: timeline.name,
                        notAlive: true,
                        isCurrentSpan: timeline.isCurrentSpan
                    });
                } else if (!alive) {
                    activities.push({
                        timelineName: timeline.name,
                        notAlive: true,
                        isCurrentSpan: timeline.isCurrentSpan
                    });
                } else if (!found) {
                    activities.push({
                        timelineName: timeline.name,
                        noActivity: true,
                        isCurrentSpan: timeline.isCurrentSpan
                    });
                }
            });
        }
        return activities.sort((a, b) => {
            const aIndex = timelineData.findIndex(t => t.name === a.timelineName);
            const bIndex = timelineData.findIndex(t => t.name === b.timelineName);
            return aIndex - bIndex;
        });
    }

    function formatActivitiesWithDividers_{{ str_replace('-', '_', $containerId) }}(activities, mode = 'absolute', hoverYear = null) {
        if (activities.length === 0) return '';
        
        let formattedContent = '';
        let currentTimeline = '';
        
        activities.forEach((activity, index) => {
            if (activity.timelineName !== currentTimeline) {
                if (currentTimeline !== '') {
                    formattedContent += '<hr style="margin: 4px 0; border: none; border-top: 1px solid white;">';
                }
                currentTimeline = activity.timelineName;
            }
            
            if (activity.notAlive) {
                formattedContent += `<span style='color: #ccc;'>●</span> <em>${activity.timelineName}</em>: <span style='color:#aaa'>Not alive at this age</span><br/>`;
            } else if (activity.noActivity) {
                formattedContent += `<span style='color: #ccc;'>●</span> <em>${activity.timelineName}</em>: <span style='color:#aaa'>No span at this age</span><br/>`;
            } else if (activity.isLifeSpan && mode === 'absolute') {
                let timeInfo = ` (${activity.start_year} - ${activity.end_year})`;
                formattedContent += `<span style='color: black;'>●</span> <em>${activity.timelineName}</em>: <strong>Life span</strong>${timeInfo}<br/>`;
            } else if (!activity.isLifeSpan) {
                const bulletColor = getConnectionColor(activity.type_id);
                let timeInfo = '';
                if (mode === 'absolute') {
                    const endYear = activity.end_year || 'Present';
                    timeInfo = ` (${activity.start_year} - ${endYear})`;
                } else {
                    const endAge = activity.end_age || 'Present';
                    timeInfo = ` (Age ${activity.start_age} - ${endAge})`;
                }
                formattedContent += `<span style="color: ${bulletColor};">●</span> <em>${activity.timelineName}</em>: ${activity.type_name} ${activity.target_name}${timeInfo}<br/>`;
                
                if (activity.nested_connections && activity.nested_connections.length > 0) {
                    activity.nested_connections.forEach(nestedConnection => {
                        const nestedStart = nestedConnection.start_year;
                        const nestedEnd = nestedConnection.end_year || new Date().getFullYear();
                        
                        if (nestedStart <= hoverYear && nestedEnd >= hoverYear) {
                            let nestedTimeInfo = '';
                            if (mode === 'absolute') {
                                const nestedEndYear = nestedConnection.end_year || 'Present';
                                nestedTimeInfo = ` (${nestedConnection.start_year} - ${nestedEndYear})`;
                            } else {
                                const timelineSpan = timelineData.find(t => t.name === activity.timelineName)?.timeline?.span;
                                if (timelineSpan && timelineSpan.start_year) {
                                    const nestedStartAge = nestedConnection.start_year - timelineSpan.start_year;
                                    const nestedEndAge = nestedConnection.end_year 
                                        ? nestedConnection.end_year - timelineSpan.start_year 
                                        : new Date().getFullYear() - timelineSpan.start_year;
                                    nestedTimeInfo = ` (Age ${nestedStartAge} - ${nestedEndAge})`;
                                }
                            }
                            
                            const nestedBulletColor = getConnectionColor(nestedConnection.type_id);
                            formattedContent += `<span style="color: ${nestedBulletColor}; margin-left: 12px;">└─</span> ${nestedConnection.type_name} ${nestedConnection.target_name}${nestedTimeInfo}<br/>`;
                        }
                    });
                }
            }
        });
        
        return formattedContent;
    }

    function updateLifeSpanTooltip_{{ str_replace('-', '_', $containerId) }}(event, span, timelineData, timelineName, isCurrentSpan, mode = 'absolute') {
        const svgRect = svg.node().getBoundingClientRect();
        const mouseX = event.clientX - svgRect.left;
        const hoverYear = Math.round(xScale.invert(mouseX));
        
        tooltip.transition().duration(100).style('opacity', 1);
        
        let tooltipContent = '';
        if (mode === 'relative') {
            tooltipContent = `<strong>At age ${hoverYear}</strong>`;
        } else {
            tooltipContent = `<strong>In ${hoverYear}</strong>`;
        }
        
        const concurrentActivities = findActivitiesAtTime_{{ str_replace('-', '_', $containerId) }}(hoverYear, timelineData, timelineName, mode);
        if (concurrentActivities.length > 0) {
            tooltipContent += `<br/><br/>`;
            tooltipContent += formatActivitiesWithDividers_{{ str_replace('-', '_', $containerId) }}(concurrentActivities, mode);
        }
        
        tooltip.html(tooltipContent)
            .style('left', (event.pageX - 50) + 'px')
            .style('top', (event.pageY + 20) + 'px');
    }

    function updateConnectionTooltip_{{ str_replace('-', '_', $containerId) }}(event, connections, timelineName, isCurrentSpan, mode = 'absolute', isDuring = false) {
        const svgRect = svg.node().getBoundingClientRect();
        const mouseX = event.clientX - svgRect.left;
        const hoverYear = Math.round(xScale.invert(mouseX));
        
        showCombinedTooltip_{{ str_replace('-', '_', $containerId) }}(event, connections, timelineName, isCurrentSpan, hoverYear, mode, isDuring);
    }

    function hideCombinedTooltip_{{ str_replace('-', '_', $containerId) }}() {
        tooltip.transition().duration(500).style('opacity', 0);
    }
    
    // Update legend with connection types found in the timeline
    updateTimelineLegend_{{ str_replace('-', '_', $containerId) }}(timelineData);
}

function calculateCombinedTimeRange_{{ str_replace('-', '_', $containerId) }}(timelineData) {
    const currentYear = new Date().getFullYear();
    let start = currentYear;
    let end = currentYear;

    timelineData.forEach(timeline => {
        // Check role occupancies first (for timeless roles, this is the only source of dates)
        if (timeline.roleOccupancies && timeline.roleOccupancies.length > 0) {
            timeline.roleOccupancies.forEach(connection => {
                if (connection.start_year && connection.start_year < start) start = connection.start_year;
                if (connection.end_year && connection.end_year > end) {
                    end = connection.end_year;
                } else if (!connection.end_year && currentYear > end) {
                    end = currentYear;
                }
            });
        }

        if (timeline.timeline && timeline.timeline.span) {
            const timelineSpan = timeline.timeline.span;
            if (timelineSpan.start_year) {
                if (timelineSpan.start_year < start) start = timelineSpan.start_year;
                if (timelineSpan.end_year && timelineSpan.end_year > end) end = timelineSpan.end_year;
            }

            if (timeline.timeline.connections) {
                timeline.timeline.connections.forEach(connection => {
                    if (connection.start_year && connection.start_year < start) start = connection.start_year;
                    if (connection.end_year && connection.end_year > end) end = connection.end_year;
                });
            }
        }
        
        if (timeline.duringConnections) {
            timeline.duringConnections.forEach(connection => {
                if (connection.start_year && connection.start_year < start) start = connection.start_year;
                if (connection.end_year && connection.end_year > end) end = connection.end_year;
            });
        }
    });

    if (start === currentYear) start = 1900;
    if (end === currentYear) end = currentYear;
    
    const padding = Math.max(5, Math.floor((end - start) * 0.1));
    return { start: start - padding, end: end + padding };
}

function calculateRelativeTimeRange_{{ str_replace('-', '_', $containerId) }}(timelineData) {
    const currentYear = new Date().getFullYear();
    const allAges = [];
    
    timelineData.forEach(timeline => {
        const timelineSpan = timeline.timeline.span;
        if (timelineSpan && timelineSpan.start_year) {
            const lifeEndAge = timelineSpan.end_year 
                ? timelineSpan.end_year - timelineSpan.start_year 
                : currentYear - timelineSpan.start_year;
            allAges.push(0, lifeEndAge);
            
            if (timeline.timeline.connections) {
                timeline.timeline.connections.forEach(connection => {
                    if (connection.start_year) {
                        const startAge = connection.start_year - timelineSpan.start_year;
                        allAges.push(startAge);
                        
                        if (connection.end_year) {
                            const endAge = connection.end_year - timelineSpan.start_year;
                            allAges.push(endAge);
                        }
                    }
                });
            }
            
            if (timeline.duringConnections) {
                timeline.duringConnections.forEach(connection => {
                    if (connection.start_year) {
                        const startAge = connection.start_year - timelineSpan.start_year;
                        allAges.push(startAge);
                        
                        if (connection.end_year) {
                            const endAge = connection.end_year - timelineSpan.start_year;
                            allAges.push(endAge);
                        }
                    }
                });
            }

            if (timeline.roleOccupancies) {
                timeline.roleOccupancies.forEach(connection => {
                    if (connection.start_year) {
                        const startAge = connection.start_year - timelineSpan.start_year;
                        allAges.push(startAge);

                        if (connection.end_year) {
                            const endAge = connection.end_year - timelineSpan.start_year;
                            allAges.push(endAge);
                        } else {
                            allAges.push(currentYear - timelineSpan.start_year);
                        }
                    }
                });
            }
        }
    });

    const minAge = Math.min(...allAges);
    const maxAge = Math.max(...allAges);
    const padding = Math.max(2, Math.floor((maxAge - minAge) * 0.1));
    return {
        start: Math.max(0, minAge - padding),
        end: maxAge + padding
    };
}

function setupModeToggle_{{ str_replace('-', '_', $containerId) }}(timelineData, currentUserSpanId) {
    const containerId = '{{ $containerId }}';
    const radioButtons = document.querySelectorAll(`input[name="timeline-mode-${containerId}"]`);
    
    radioButtons.forEach(radio => {
        radio.removeEventListener('change', radio._modeToggleHandler);
    });
    
    radioButtons.forEach(radio => {
        const handler = function() {
            const selectedMode = this.value;
            
            if (this.checked) {
                renderGroupTimeline_{{ str_replace('-', '_', $containerId) }}(timelineData, selectedMode, currentUserSpanId);
            }
        };
        
        radio._modeToggleHandler = handler;
        radio.addEventListener('change', handler);
    });
}

// Function to check if a connection type should be shown based on filters
function shouldShowConnection_{{ str_replace('-', '_', $containerId) }}(typeId) {
    const filters = connectionTypeFilters_{{ str_replace('-', '_', $containerId) }};
    const filterState = filters[typeId];
    
    // If no filter state, show by default
    if (!filterState) return true;
    
    // If hidden, don't show
    if (filterState === 'hidden') return false;
    
    // If isolated, check if this is the isolated type
    if (filterState === 'isolated') {
        // Check if there are any isolated types
        const isolatedTypes = Object.keys(filters).filter(key => filters[key] === 'isolated');
        if (isolatedTypes.length === 0) return true; // No isolation, show all
        return isolatedTypes.includes(typeId); // Only show if this type is isolated
    }
    
    // If visible (or any other state), show
    return true;
}

function updateTimelineLegend_{{ str_replace('-', '_', $containerId) }}(timelineData) {
    const containerId = '{{ $containerId }}';
    const legendContainer = document.getElementById(`timeline-legend-${containerId}`);
    const legendItems = document.getElementById(`timeline-legend-items-${containerId}`);
    const resetButton = document.getElementById(`timeline-legend-reset-${containerId}`);
    
    if (!legendContainer || !legendItems) {
        return;
    }
    
    // Collect all unique connection types from the timeline data
    const connectionTypes = new Set();
    
    timelineData.forEach(timeline => {
        if (timeline.timeline && timeline.timeline.connections) {
            timeline.timeline.connections.forEach(connection => {
                if (connection.type_id && connection.type_id !== 'life') {
                    connectionTypes.add(connection.type_id);
                }
            });
        }
        if (timeline.roleOccupancies && timeline.roleOccupancies.length > 0) {
            connectionTypes.add('has_role');
        }
        if (timeline.duringConnections && timeline.duringConnections.length > 0) {
            timeline.duringConnections.forEach(connection => {
                if (connection.type_id) {
                    connectionTypes.add(connection.type_id);
                }
            });
        }
    });
    
    // If no connection types found, hide the legend
    if (connectionTypes.size === 0) {
        legendContainer.style.display = 'none';
        return;
    }
    
    // Connection type labels (human-readable names)
    const typeLabels = {
        'residence': 'Residence',
        'employment': 'Employment',
        'education': 'Education',
        'membership': 'Membership',
        'family': 'Family',
        'relationship': 'Relationship',
        'travel': 'Travel',
        'participation': 'Participation',
        'ownership': 'Ownership',
        'created': 'Created',
        'contains': 'Contains',
        'has_role': 'Role',
        'at_organisation': 'At Organisation'
    };
    
    // Function to get connection color (same logic as in renderGroupTimeline)
    function getConnectionColor(typeId) {
        const cssColor = getComputedStyle(document.documentElement)
            .getPropertyValue(`--connection-${typeId}-color`);
        if (cssColor && cssColor.trim() !== '') return cssColor.trim();
        
        const testElement = document.createElement('div');
        testElement.className = `bg-${typeId}`;
        testElement.style.display = 'none';
        document.body.appendChild(testElement);
        const computedStyle = getComputedStyle(testElement);
        const backgroundColor = computedStyle.backgroundColor;
        document.body.removeChild(testElement);
        
        if (backgroundColor && backgroundColor !== 'rgba(0, 0, 0, 0)' && backgroundColor !== 'transparent') {
            return backgroundColor;
        }
        
        const fallbackColors = {
            'residence': '#007bff', 'employment': '#28a745', 'education': '#ffc107', 'membership': '#dc3545',
            'family': '#6f42c1', 'relationship': '#fd7e14', 'travel': '#20c997', 'participation': '#e83e8c',
            'ownership': '#6c757d', 'created': '#17a2b8', 'contains': '#6610f2', 'has_role': '#fd7e14',
            'at_organisation': '#20c997', 'life': '#000000'
        };
        
        return fallbackColors[typeId] || '#6c757d';
    }
    
    // Clear existing legend items
    legendItems.innerHTML = '';
    
    // Sort connection types for consistent display
    const sortedTypes = Array.from(connectionTypes).sort();
    
    // Check if any filters are active
    const hasActiveFilters = Object.keys(connectionTypeFilters_{{ str_replace('-', '_', $containerId) }}).length > 0;
    if (resetButton) {
        resetButton.style.display = hasActiveFilters ? 'block' : 'none';
    }
    
    // Create legend items
    sortedTypes.forEach(typeId => {
        const color = getConnectionColor(typeId);
        const label = typeLabels[typeId] || typeId.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase());
        const filterState = connectionTypeFilters_{{ str_replace('-', '_', $containerId) }}[typeId] || 'visible';
        
        const legendItem = document.createElement('div');
        legendItem.className = 'd-flex align-items-center';
        legendItem.style.cssText = 'font-size: 0.75rem; cursor: pointer; user-select: none; padding: 2px 6px; border-radius: 4px; transition: background-color 0.2s;';
        legendItem.dataset.typeId = typeId;
        
        // Style based on filter state
        if (filterState === 'hidden') {
            legendItem.style.opacity = '0.3';
            legendItem.style.textDecoration = 'line-through';
        } else if (filterState === 'isolated') {
            legendItem.style.backgroundColor = 'rgba(13, 110, 253, 0.1)';
            legendItem.style.border = '1px solid rgba(13, 110, 253, 0.3)';
        }
        
        // Hover effect
        legendItem.addEventListener('mouseenter', function() {
            if (filterState !== 'hidden') {
                this.style.backgroundColor = 'rgba(0, 0, 0, 0.05)';
            }
        });
        legendItem.addEventListener('mouseleave', function() {
            if (filterState === 'hidden') {
                this.style.backgroundColor = 'transparent';
            } else if (filterState === 'isolated') {
                this.style.backgroundColor = 'rgba(13, 110, 253, 0.1)';
            } else {
                this.style.backgroundColor = 'transparent';
            }
        });
        
        // Single click handler (isolate)
        let clickTimeout;
        legendItem.addEventListener('click', function(e) {
            clearTimeout(clickTimeout);
            clickTimeout = setTimeout(function() {
                isolateConnectionType_{{ str_replace('-', '_', $containerId) }}(typeId);
            }, 250); // Wait to see if it's a double click
        });
        
        // Double click handler (hide)
        legendItem.addEventListener('dblclick', function(e) {
            e.preventDefault();
            clearTimeout(clickTimeout);
            toggleHideConnectionType_{{ str_replace('-', '_', $containerId) }}(typeId);
        });
        
        const colorBox = document.createElement('span');
        colorBox.style.cssText = `display: inline-block; width: 12px; height: 12px; background-color: ${color}; border: 1px solid ${filterState === 'hidden' ? '#ccc' : '#fff'}; border-radius: 2px; margin-right: 4px; flex-shrink: 0;`;
        
        const labelText = document.createElement('span');
        labelText.textContent = label;
        labelText.className = filterState === 'hidden' ? 'text-muted' : 'text-dark';
        
        // Add indicator for isolated state
        if (filterState === 'isolated') {
            const indicator = document.createElement('span');
            indicator.textContent = '●';
            indicator.style.cssText = 'color: #0d6efd; margin-left: 4px; font-size: 0.6rem;';
            legendItem.appendChild(colorBox);
            legendItem.appendChild(labelText);
            legendItem.appendChild(indicator);
        } else {
            legendItem.appendChild(colorBox);
            legendItem.appendChild(labelText);
        }
        
        legendItems.appendChild(legendItem);
    });
    
    // Show the legend
    legendContainer.style.display = 'block';
}

// Function to isolate a connection type (single click)
function isolateConnectionType_{{ str_replace('-', '_', $containerId) }}(typeId) {
    const filters = connectionTypeFilters_{{ str_replace('-', '_', $containerId) }};
    const currentState = filters[typeId];
    
    // If already isolated, toggle it off (reset all filters)
    if (currentState === 'isolated') {
        // Clear all filters to show everything
        Object.keys(filters).forEach(key => {
            delete filters[key];
        });
    } else {
        // Isolate this type - hide all others that are visible
        // First, collect all connection types from timeline data
        const allTypes = new Set();
        if (currentTimelineData_{{ str_replace('-', '_', $containerId) }}) {
            currentTimelineData_{{ str_replace('-', '_', $containerId) }}.forEach(timeline => {
                if (timeline.timeline && timeline.timeline.connections) {
                    timeline.timeline.connections.forEach(connection => {
                        if (connection.type_id && connection.type_id !== 'life') {
                            allTypes.add(connection.type_id);
                        }
                    });
                }
                if (timeline.roleOccupancies && timeline.roleOccupancies.length > 0) {
                    allTypes.add('has_role');
                }
                if (timeline.duringConnections && timeline.duringConnections.length > 0) {
                    timeline.duringConnections.forEach(connection => {
                        if (connection.type_id) {
                            allTypes.add(connection.type_id);
                        }
                    });
                }
            });
        }
        
        // Hide all types except the one being isolated
        allTypes.forEach(otherTypeId => {
            if (otherTypeId !== typeId) {
                filters[otherTypeId] = 'hidden';
            }
        });
        
        // Isolate the selected type
        filters[typeId] = 'isolated';
    }
    
    // Re-render timeline and update legend
    reRenderTimelineWithFilters_{{ str_replace('-', '_', $containerId) }}();
}

// Function to toggle hide a connection type (double click)
function toggleHideConnectionType_{{ str_replace('-', '_', $containerId) }}(typeId) {
    const filters = connectionTypeFilters_{{ str_replace('-', '_', $containerId) }};
    const currentState = filters[typeId];
    
    // If hidden, make it visible (just remove the filter)
    if (currentState === 'hidden') {
        delete filters[typeId];
    } else {
        // Hide it - if it was isolated, clear isolation and just hide this one
        if (currentState === 'isolated') {
            // Clear isolation - show all other types, hide this one
            Object.keys(filters).forEach(key => {
                if (filters[key] === 'hidden') {
                    delete filters[key];
                }
            });
            // Remove the isolated state
            delete filters[typeId];
        }
        // Now hide this type
        filters[typeId] = 'hidden';
    }
    
    // Re-render timeline and update legend
    reRenderTimelineWithFilters_{{ str_replace('-', '_', $containerId) }}();
}

// Function to reset all filters
function resetConnectionTypeFilters_{{ str_replace('-', '_', $containerId) }}() {
    connectionTypeFilters_{{ str_replace('-', '_', $containerId) }} = {};
    reRenderTimelineWithFilters_{{ str_replace('-', '_', $containerId) }}();
}

// Function to re-render timeline with current filters
function reRenderTimelineWithFilters_{{ str_replace('-', '_', $containerId) }}() {
    if (currentTimelineData_{{ str_replace('-', '_', $containerId) }}) {
        renderGroupTimeline_{{ str_replace('-', '_', $containerId) }}(
            currentTimelineData_{{ str_replace('-', '_', $containerId) }},
            currentMode_{{ str_replace('-', '_', $containerId) }},
            currentUserSpanId_{{ str_replace('-', '_', $containerId) }}
        );
    }
}
</script>
@endpush 