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
    <div class="card-body">
        <div id="{{ $containerId }}" style="height: 300px; width: 100%; cursor: crosshair;">
            <!-- D3 group timeline will be rendered here -->
        </div>
    </div>
</div>

@push('scripts')
<script src="https://d3js.org/d3.v7.min.js"></script>
<script>
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
            renderGroupTimeline_{{ str_replace('-', '_', $containerId) }}(timelineData, 'absolute', currentUserSpanId);
            setupModeToggle_{{ str_replace('-', '_', $containerId) }}(timelineData, currentUserSpanId);
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
            
            timelineData.push({
                id: spanId,
                name: currentSpanData.span.name,
                timeline: currentSpanData,
                duringConnections: duringConnectionsData.connections || [],
                isCurrentSpan: true,
                isCurrentUser: false
            });
            
            if (allSubjectIds.size > 0) {
                const subjectIdsToFetch = Array.from(allSubjectIds).filter(subjectId => 
                    subjectId !== currentUserSpanId && subjectId !== spanId
                );
                
                const subjectPromises = subjectIdsToFetch.map(subjectId => 
                    Promise.all([
                        fetch(`/api/spans/${subjectId}`, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } }).then(response => response.json()),
                        fetch(`/api/spans/${subjectId}/during-connections`, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } }).then(response => response.json())
                    ])
                    .then(([subjectData, duringData]) => ({
                        id: subjectId,
                        name: objectConnectionsData.connections.find(conn => conn.target_id === subjectId)?.target_name || 'Unknown',
                        timeline: subjectData,
                        duringConnections: duringData.connections || [],
                        isCurrentSpan: false,
                        isCurrentUser: false
                    }))
                    .catch(error => {
                        console.error(`Error loading timeline for subject ${subjectId}:`, error);
                        return null;
                    })
                );
                
                if (shouldIncludeUserSpan) {
                    subjectPromises.unshift(
                        Promise.all([
                            fetch(`/api/spans/${currentUserSpanId}`, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } }).then(response => response.json()),
                            fetch(`/api/spans/${currentUserSpanId}/during-connections`, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } }).then(response => response.json())
                        ])
                        .then(([subjectData, duringData]) => ({
                            id: currentUserSpanId,
                            name: 'You',
                            timeline: subjectData,
                            duringConnections: duringData.connections || [],
                            isCurrentSpan: false,
                            isCurrentUser: true
                        }))
                        .catch(error => {
                            console.error(`Error loading timeline for user ${currentUserSpanId}:`, error);
                            return null;
                        })
                    );
                }
                
                Promise.all(subjectPromises)
                    .then(subjectData => {
                        const validSubjects = subjectData.filter(subject => subject !== null);
                        
                        if (shouldIncludeUserSpan) {
                            const userData = validSubjects.find(subject => subject.id === currentUserSpanId);
                            if (userData) {
                                timelineData[0] = userData;
                            }
                        }
                        
                        const otherSubjects = validSubjects.filter(subject => 
                            subject.id !== currentUserSpanId && subject.id !== spanId
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
                        
                        renderGroupTimeline_{{ str_replace('-', '_', $containerId) }}(timelineData, 'absolute', currentUserSpanId);
                        setupModeToggle_{{ str_replace('-', '_', $containerId) }}(timelineData, currentUserSpanId);
                    })
                    .catch(error => {
                        console.error('Error loading subject timelines:', error);
                        renderGroupTimeline_{{ str_replace('-', '_', $containerId) }}(timelineData, 'absolute', currentUserSpanId);
                        setupModeToggle_{{ str_replace('-', '_', $containerId) }}(timelineData, currentUserSpanId);
                    });
            } else {
                renderGroupTimeline_{{ str_replace('-', '_', $containerId) }}(timelineData, 'absolute', currentUserSpanId);
                setupModeToggle_{{ str_replace('-', '_', $containerId) }}(timelineData, currentUserSpanId);
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
    timelineData.forEach((timeline, index) => {
        const swimlaneY = margin.top + index * (swimlaneHeight + swimlaneSpacing);
        const isCurrentSpan = timeline.isCurrentSpan;
        const isCurrentUser = currentUserSpanId && timeline.id === currentUserSpanId;
        
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
            swimlaneY: swimlaneY,
            isCurrentSpan: isCurrentSpan,
            isCurrentUser: isCurrentUser
        };

        // Add life span bar for this timeline
        const timelineSpan = timeline.timeline.span;
        if (timelineSpan && timelineSpan.start_year) {
            const lifeStartYear = mode === 'absolute' ? timelineSpan.start_year : 0;
            const lifeEndYear = mode === 'absolute' 
                ? (timelineSpan.end_year || new Date().getFullYear())
                : (timelineSpan.end_year ? timelineSpan.end_year - timelineSpan.start_year : new Date().getFullYear() - timelineSpan.start_year);
            const hasConnections = timeline.timeline.connections && timeline.timeline.connections.length > 0;
            
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
        if (timeline.timeline.connections) {
            timeline.timeline.connections
                .filter(connection => {
                    if (connection.target_type === 'connection') return false;
                    if (connection.type_id === 'created' && connection.target_type === 'thing' && connection.target_metadata?.subtype === 'photo') return false;
                    return true;
                })
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

        // Add "during" connections for this timeline
        if (timeline.duringConnections && timeline.duringConnections.length > 0 && index === 0) {
            timeline.duringConnections.forEach(connection => {
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
            const { name, swimlaneY, isCurrentSpan, isCurrentUser } = timeline.labelInfo;
            
            svg.append('text')
                .attr('class', 'swimlane-label')
                .attr('x', margin.left + 8)
                .attr('y', swimlaneY + swimlaneHeight / 2 + 4)
                .attr('text-anchor', 'start')
                .attr('font-size', '11px')
                .attr('font-weight', (isCurrentSpan || isCurrentUser) ? 'bold' : 'normal')
                .attr('fill', mode === 'relative' ? 'white' : (isCurrentSpan ? '#1976d2' : (isCurrentUser ? '#000000' : '#495057')))
                .style('pointer-events', 'none')
                .style('z-index', '1001')
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
}

function calculateCombinedTimeRange_{{ str_replace('-', '_', $containerId) }}(timelineData) {
    const currentYear = new Date().getFullYear();
    let start = currentYear;
    let end = currentYear;

    timelineData.forEach(timeline => {
        const timelineSpan = timeline.timeline.span;
        if (timelineSpan && timelineSpan.start_year) {
            if (timelineSpan.start_year < start) start = timelineSpan.start_year;
            if (timelineSpan.end_year && timelineSpan.end_year > end) end = timelineSpan.end_year;
        }

        if (timeline.timeline.connections) {
            timeline.timeline.connections.forEach(connection => {
                if (connection.start_year && connection.start_year < start) start = connection.start_year;
                if (connection.end_year && connection.end_year > end) end = connection.end_year;
            });
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
</script>
@endpush 