@props(['span', 'timeTravelDate' => null])

@php
    $currentUserSpanId = auth()->user()->personal_span_id ?? null;
@endphp

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">
            <i class="bi bi-person-lines-fill me-2"></i>
            Timeline
        </h5>
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
    <div class="card-body">
        <div id="timeline-combined-container-{{ $span->id }}" style="height: 60px; width: 100%; cursor: crosshair; position: relative; overflow: hidden; transition: height 0.3s cubic-bezier(.4,0,.2,1);">
            <div id="timeline-combined-spinner-{{ $span->id }}" class="d-flex justify-content-center align-items-center h-100" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(255,255,255,0.7); z-index: 10;">
                <div class="spinner-border text-secondary" role="status" style="width: 2rem; height: 2rem;">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>
            <!-- D3 combined timeline will be rendered here -->
        </div>
    </div>
</div>

@push('scripts')
<script src="https://d3js.org/d3.v7.min.js"></script>
<script>
// Use a unique identifier to avoid conflicts with other timeline components
const combinedTimelineId = 'combined_{{ str_replace('-', '_', $span->id) }}';
let tooltipLocked_{{ str_replace('-', '_', $span->id) }} = false;
let lastTooltipEvent_{{ str_replace('-', '_', $span->id) }} = null;
let unlockListener_{{ str_replace('-', '_', $span->id) }} = null;
let tooltipMask_{{ str_replace('-', '_', $span->id) }} = null;
let tooltipEscapeListener_{{ str_replace('-', '_', $span->id) }} = null;

document.addEventListener('DOMContentLoaded', function() {
    // Add a small delay to ensure DOM is fully ready
    setTimeout(() => {
        initializeCombinedTimeline_{{ str_replace('-', '_', $span->id) }}();
    }, 100);
});

function initializeCombinedTimeline_{{ str_replace('-', '_', $span->id) }}() {
    const spanId = '{{ $span->id }}';
    const currentUserSpanId = '{{ $currentUserSpanId }}';
    const container = document.getElementById(`timeline-combined-container-${spanId}`);
    const spinner = document.getElementById(`timeline-combined-spinner-${spanId}`);
    
    // Check if container exists
    if (!container) {
        console.error('Combined timeline container not found:', `timeline-combined-container-${spanId}`);
        return;
    }
    
    console.log('Initializing combined timeline for span:', spanId);
    console.log('Current user span ID:', currentUserSpanId);
    
    // Fetch both the current span's timeline and object connections
    Promise.all([
        fetch(`/api/spans/${spanId}`, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } }).then(response => response.json()),
        fetch(`/api/spans/${spanId}/object-connections`, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } }).then(response => response.json()),
        fetch(`/api/spans/${spanId}/during-connections`, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } }).then(response => response.json())
    ])
    .then(([currentSpanData, objectConnectionsData, duringConnectionsData]) => {
        // Extract unique subjects from the object connections, excluding connection spans and photos
        const subjects = [...new Set(
            objectConnectionsData.connections
                .filter(conn => {
                    // Exclude connections to connection spans
                    if (conn.target_type === 'connection') return false;
                    // Exclude "created" connections to photos or sets
                    if (
                        conn.type_id === 'created' &&
                        conn.target_type === 'thing' &&
                        (conn.target_metadata?.subtype === 'photo' || conn.target_metadata?.subtype === 'set')
                    ) return false;
                    return true;
                })
                .map(conn => conn.target_id)
        )];
        
        // ALWAYS include the current user's span at the top if it exists and is different from current span
        // This ensures the user's span is always visible, regardless of connections
        const allSubjectIds = new Set(subjects);
        const shouldIncludeUserSpan = currentUserSpanId && currentUserSpanId !== spanId;
        
        if (shouldIncludeUserSpan) {
            allSubjectIds.add(currentUserSpanId);
        }
        
        // Prepare timeline data array - we'll build it in the desired order
        let timelineData = [];
        
        // ALWAYS add user's span first if it exists and is different from current span
        if (shouldIncludeUserSpan) {
            timelineData.push({
                id: currentUserSpanId,
                name: 'You',
                timeline: null, // Will be fetched
                isCurrentSpan: false,
                isCurrentUser: true
            });
        }
        
        // Add current span
        timelineData.push({
            id: spanId,
            name: currentSpanData.span.name,
            timeline: currentSpanData,
            duringConnections: duringConnectionsData.connections || [],
            isCurrentSpan: true,
            isCurrentUser: false
        });
        
        if (allSubjectIds.size > 0) {
            // Fetch timeline data for each subject (excluding user and current span)
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
            
            // ALWAYS fetch user's timeline data if we're including it
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
                    
                    // Update the timeline data with fetched data
                    if (shouldIncludeUserSpan) {
                        const userData = validSubjects.find(subject => subject.id === currentUserSpanId);
                        if (userData) {
                            timelineData[0] = userData; // Update the user's entry with fetched data
                        }
                    }
                    
                    // Add other subjects after current span
                    const otherSubjects = validSubjects.filter(subject => 
                        subject.id !== currentUserSpanId && subject.id !== spanId
                    );
                    timelineData.push(...otherSubjects);
                    
                    // Collect all during connections and nested connections from all timelines
                    const allDuringConnections = [];
                    timelineData.forEach(timeline => {
                        if (timeline.duringConnections) {
                            allDuringConnections.push(...timeline.duringConnections);
                        }
                        // Also collect nested connections from the timeline data itself
                        if (timeline.timeline.connections) {
                            timeline.timeline.connections.forEach(connection => {
                                if (connection.nested_connections) {
                                    allDuringConnections.push(...connection.nested_connections);
                                }
                            });
                        }
                    });
                    
                    // Add all during connections to the person's timeline (assuming the first timeline is the person)
                    if (timelineData.length > 0) {
                        timelineData[0].duringConnections = allDuringConnections;
                    }
                    
                    const finishRender = (timelineData) => {
                        // Calculate the final height for the timeline
                        const swimlaneHeight = 20;
                        const swimlaneSpacing = 10;
                        const swimlaneBottomMargin = 30;
                        const margin = { top: 20, right: 20, bottom: 30, left: 20 };
                        const totalSwimlanes = timelineData.length;
                        const totalHeight = totalSwimlanes * (swimlaneHeight + swimlaneSpacing) - swimlaneSpacing + swimlaneBottomMargin;
                        const adjustedHeight = totalHeight + margin.top + margin.bottom;
                        // Animate the container height
                        container.style.height = '60px';
                        // Hide spinner after a short delay to ensure DOM update
                        setTimeout(() => {
                            if (spinner) spinner.style.display = 'none';
                            container.style.height = adjustedHeight + 'px';
                            // Wait for the transition to finish before rendering SVG
                            setTimeout(() => {
                                renderCombinedTimeline_{{ str_replace('-', '_', $span->id) }}(timelineData, currentSpanData.span, 'absolute', currentUserSpanId);
                                // Add event listeners for mode toggle
                                setupModeToggle_{{ str_replace('-', '_', $span->id) }}(timelineData, currentSpanData.span, currentUserSpanId);
                            }, 300);
                        }, 50);
                    };
                    finishRender(timelineData);
                })
                .catch(error => {
                    console.error('Error loading subject timelines:', error);
                    const finishRender = (timelineData) => {
                        // Calculate the final height for the timeline
                        const swimlaneHeight = 20;
                        const swimlaneSpacing = 10;
                        const swimlaneBottomMargin = 30;
                        const margin = { top: 20, right: 20, bottom: 30, left: 20 };
                        const totalSwimlanes = timelineData.length;
                        const totalHeight = totalSwimlanes * (swimlaneHeight + swimlaneSpacing) - swimlaneSpacing + swimlaneBottomMargin;
                        const adjustedHeight = totalHeight + margin.top + margin.bottom;
                        // Animate the container height
                        container.style.height = '60px';
                        // Hide spinner after a short delay to ensure DOM update
                        setTimeout(() => {
                            if (spinner) spinner.style.display = 'none';
                            container.style.height = adjustedHeight + 'px';
                            // Wait for the transition to finish before rendering SVG
                            setTimeout(() => {
                                renderCombinedTimeline_{{ str_replace('-', '_', $span->id) }}(timelineData, currentSpanData.span, 'absolute', currentUserSpanId);
                                // Add event listeners for mode toggle
                                setupModeToggle_{{ str_replace('-', '_', $span->id) }}(timelineData, currentSpanData.span, currentUserSpanId);
                            }, 300);
                        }, 50);
                    };
                    finishRender(timelineData);
                });
        } else {
            const finishRender = (timelineData) => {
                // Calculate the final height for the timeline
                const swimlaneHeight = 20;
                const swimlaneSpacing = 10;
                const swimlaneBottomMargin = 30;
                const margin = { top: 20, right: 20, bottom: 30, left: 20 };
                const totalSwimlanes = timelineData.length;
                const totalHeight = totalSwimlanes * (swimlaneHeight + swimlaneSpacing) - swimlaneSpacing + swimlaneBottomMargin;
                const adjustedHeight = totalHeight + margin.top + margin.bottom;
                // Animate the container height
                container.style.height = '60px';
                // Hide spinner after a short delay to ensure DOM update
                setTimeout(() => {
                    if (spinner) spinner.style.display = 'none';
                    container.style.height = adjustedHeight + 'px';
                    // Wait for the transition to finish before rendering SVG
                    setTimeout(() => {
                        renderCombinedTimeline_{{ str_replace('-', '_', $span->id) }}(timelineData, currentSpanData.span, 'absolute', currentUserSpanId);
                        // Add event listeners for mode toggle
                        setupModeToggle_{{ str_replace('-', '_', $span->id) }}(timelineData, currentSpanData.span, currentUserSpanId);
                    }, 300);
                }, 50);
            };
            finishRender(timelineData);
        }
    })
    .catch(error => {
        console.error('Error loading combined timeline data:', error);
        const container = document.getElementById(`timeline-combined-container-${spanId}`);
        if (container) {
            container.innerHTML = '<div class="text-danger text-center py-4">Error loading combined timeline data</div>';
        }
    });
}

function renderCombinedTimeline_{{ str_replace('-', '_', $span->id) }}(timelineData, currentSpan, mode = 'absolute', currentUserSpanId = null) {
    const spanId = '{{ $span->id }}';
    console.log('Rendering combined timeline with mode:', mode);
    console.log('Current user span ID:', currentUserSpanId);
    const container = document.getElementById(`timeline-combined-container-${spanId}`);
    
    // Check if container exists
    if (!container) {
        console.error('Combined timeline container not found during render:', `timeline-combined-container-${spanId}`);
        return;
    }
    
    const width = container.clientWidth;
    const margin = { top: 20, right: 20, bottom: 30, left: 20 };
    const swimlaneHeight = 20;
    const swimlaneSpacing = 10;
    const swimlaneBottomMargin = 30; // Extra space between swimlanes and scale
    const totalSwimlanes = timelineData.length;
    const totalHeight = totalSwimlanes * (swimlaneHeight + swimlaneSpacing) - swimlaneSpacing + swimlaneBottomMargin;
    const adjustedHeight = totalHeight + margin.top + margin.bottom;
    
    // Set container height to fit content
    container.style.height = `${adjustedHeight}px`;

    container.innerHTML = '';
    const svg = d3.select(container)
        .append('svg')
        .attr('width', width)
        .attr('height', adjustedHeight);

    // Calculate global time range across all timelines based on mode
    const timeRange = mode === 'absolute' 
        ? calculateCombinedTimeRange_{{ str_replace('-', '_', $span->id) }}(timelineData, currentSpan)
        : calculateRelativeTimeRange_{{ str_replace('-', '_', $span->id) }}(timelineData, currentSpan);
    
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
        
        // Draw swimlane background with special styling for current span and current user
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

        // Store label info for later rendering (after all timeline elements)
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
                .style('pointer-events', 'auto') // Always allow interaction
                .on('mouseover', function(event) {
                    persistentTooltipHandler(event, updateLifeSpanTooltip_{{ str_replace('-', '_', $span->id) }}, timelineSpan, timelineData, timeline.name, isCurrentSpan, mode);
                })
                .on('mousemove', function(event) {
                    persistentTooltipHandler(event, updateLifeSpanTooltip_{{ str_replace('-', '_', $span->id) }}, timelineSpan, timelineData, timeline.name, isCurrentSpan, mode);
                })
                .on('mouseout', function() {
                    persistentTooltipHide();
                })
                .on('click', function(event) {
                    persistentTooltipClick(event, updateLifeSpanTooltip_{{ str_replace('-', '_', $span->id) }}, timelineSpan, timelineData, timeline.name, isCurrentSpan, mode);
                });
        }

        // Add connections for this timeline
        if (timeline.timeline.connections) {
            filterConnectionsDeep(timeline.timeline.connections)
                .forEach(connection => {
                    const connectionType = connection.type_id;
                    // Only render 'created' moments if not filtered (filterTimelineConnections already excludes sets/photos)
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
                                persistentTooltipHandler(event, updateConnectionTooltip_{{ str_replace('-', '_', $span->id) }}, [connection], timeline.name, isCurrentSpan, mode);
                            })
                            .on('mousemove', function(event) {
                                persistentTooltipHandler(event, updateConnectionTooltip_{{ str_replace('-', '_', $span->id) }}, [connection], timeline.name, isCurrentSpan, mode);
                            })
                            .on('mouseout', function() {
                                persistentTooltipHide();
                            })
                            .on('click', function(event) {
                                persistentTooltipClick(event, updateConnectionTooltip_{{ str_replace('-', '_', $span->id) }}, [connection], timeline.name, isCurrentSpan, mode);
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
                                persistentTooltipHandler(event, updateConnectionTooltip_{{ str_replace('-', '_', $span->id) }}, [connection], timeline.name, isCurrentSpan, mode);
                            })
                            .on('mousemove', function(event) {
                                persistentTooltipHandler(event, updateConnectionTooltip_{{ str_replace('-', '_', $span->id) }}, [connection], timeline.name, isCurrentSpan, mode);
                            })
                            .on('mouseout', function() {
                                persistentTooltipHide();
                            })
                            .on('click', function(event) {
                                persistentTooltipClick(event, updateConnectionTooltip_{{ str_replace('-', '_', $span->id) }}, [connection], timeline.name, isCurrentSpan, mode);
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
                                persistentTooltipHandler(event, updateConnectionTooltip_{{ str_replace('-', '_', $span->id) }}, [connection], timeline.name, isCurrentSpan, mode);
                            })
                            .on('mousemove', function(event) {
                                persistentTooltipHandler(event, updateConnectionTooltip_{{ str_replace('-', '_', $span->id) }}, [connection], timeline.name, isCurrentSpan, mode);
                            })
                            .on('mouseout', function() {
                                persistentTooltipHide();
                            })
                            .on('click', function(event) {
                                persistentTooltipClick(event, updateConnectionTooltip_{{ str_replace('-', '_', $span->id) }}, [connection], timeline.name, isCurrentSpan, mode);
                            });
                    }
                });
        }

        // Add "during" connections for this timeline (nested within the span)
        if (timeline.duringConnections && timeline.duringConnections.length > 0 && index === 0) {
            // Only show during connections in the person's timeline (first timeline)
            timeline.duringConnections.forEach(connection => {
                const connectionStartYear = mode === 'absolute' ? connection.start_year : connection.start_year - timelineSpan.start_year;
                const connectionEndYear = mode === 'absolute' 
                    ? (connection.end_year || new Date().getFullYear())
                    : (connection.end_year ? connection.end_year - timelineSpan.start_year : new Date().getFullYear() - timelineSpan.start_year);
                const connectionWidth = xScale(connectionEndYear) - xScale(connectionStartYear);
                
                // Create a nested bar with a different style to show it's within the span
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
                    .style('opacity', 0.4) // Make more subtle
                    // .style('stroke-dasharray', '2,2') // Remove dashed border
                    .on('mouseover', function(event) {
                        persistentTooltipHandler(event, updateConnectionTooltip_{{ str_replace('-', '_', $span->id) }}, [connection], timeline.name, isCurrentSpan, mode, true);
                    })
                    .on('mousemove', function(event) {
                        persistentTooltipHandler(event, updateConnectionTooltip_{{ str_replace('-', '_', $span->id) }}, [connection], timeline.name, isCurrentSpan, mode, true);
                    })
                    .on('mouseout', function() {
                        persistentTooltipHide();
                    })
                    .on('click', function(event) {
                        persistentTooltipClick(event, updateConnectionTooltip_{{ str_replace('-', '_', $span->id) }}, [connection], timeline.name, isCurrentSpan, mode, true);
                    });
            });
        }
    });

    // Add "now" line (current year or time travel date) - only in absolute mode
    if (mode === 'absolute') {
        @if($timeTravelDate)
            const timeTravelYear = new Date('{{ $timeTravelDate }}').getFullYear();
            const nowX = xScale(timeTravelYear);
            const indicatorText = '{{ date("M j Y", strtotime($timeTravelDate)) }}';
        @else
            const currentYear = new Date().getFullYear();
            const nowX = xScale(currentYear);
            const indicatorText = 'NOW';
        @endif
        // Only show the indicator line if it's within the visible time range
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
                .text(indicatorText);
        }
    } else if (mode === 'relative') {
        // In relative mode, show "now" at the current user's age (if available)
        if (currentUserSpanId) {
            const userTimeline = timelineData.find(t => t.id == currentUserSpanId);
            if (userTimeline && userTimeline.timeline && userTimeline.timeline.span && userTimeline.timeline.span.start_year) {
                const currentYear = new Date().getFullYear();
                const userStartYear = userTimeline.timeline.span.start_year;
                const userCurrentAge = currentYear - userStartYear;
                const nowX = xScale(userCurrentAge);
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
        }
    }

    // Add swimlane labels at the very end to ensure they're on top
    timelineData.forEach((timeline) => {
        if (timeline.labelInfo) {
            const { name, swimlaneY, isCurrentSpan, isCurrentUser } = timeline.labelInfo;
            
            // Add swimlane label floating on top
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
        .attr('class', `combined-tooltip-${spanId}`)
        .style('position', 'absolute')
        .style('background', 'rgba(0, 0, 0, 0.8)')
        .style('color', 'white')
        .style('padding', '8px')
        .style('border-radius', '4px')
        .style('font-size', '12px')
        .style('pointer-events', 'none')
        .style('opacity', 0)
        .style('z-index', 9999);

    // --- MODIFIED EVENT HANDLERS FOR PERSISTENT TOOLTIP ---
    function persistentTooltipHandler(event, showFn, ...args) {
        if (!tooltipLocked_{{ str_replace('-', '_', $span->id) }}) {
            showFn(event, ...args);
            lastTooltipEvent_{{ str_replace('-', '_', $span->id) }} = { showFn, args, event };
        }
    }
    function persistentTooltipHide() {
        if (!tooltipLocked_{{ str_replace('-', '_', $span->id) }}) {
            hideCombinedTooltip_{{ str_replace('-', '_', $span->id) }}();
        }
    }
    function persistentTooltipClick(event, showFn, ...args) {
        tooltipLocked_{{ str_replace('-', '_', $span->id) }} = true;
        showFn(event, ...args);
        lastTooltipEvent_{{ str_replace('-', '_', $span->id) }} = { showFn, args, event };
        tooltip.style('pointer-events', 'auto');
        // Add fullscreen mask
        if (!tooltipMask_{{ str_replace('-', '_', $span->id) }}) {
            tooltipMask_{{ str_replace('-', '_', $span->id) }} = document.createElement('div');
            tooltipMask_{{ str_replace('-', '_', $span->id) }}.className = 'timeline-tooltip-mask';
            Object.assign(tooltipMask_{{ str_replace('-', '_', $span->id) }}.style, {
                position: 'fixed',
                top: 0,
                left: 0,
                width: '100vw',
                height: '100vh',
                background: 'rgba(255,255,255,0.7)',
                zIndex: 9998, // Mask below tooltip
                cursor: 'pointer',
            });
            tooltipMask_{{ str_replace('-', '_', $span->id) }}.addEventListener('mousedown', function(e) {
                tooltipLocked_{{ str_replace('-', '_', $span->id) }} = false;
                hideCombinedTooltip_{{ str_replace('-', '_', $span->id) }}();
                tooltip.style('pointer-events', 'none');
                if (tooltipMask_{{ str_replace('-', '_', $span->id) }}) {
                    document.body.removeChild(tooltipMask_{{ str_replace('-', '_', $span->id) }});
                    tooltipMask_{{ str_replace('-', '_', $span->id) }} = null;
                }
                if (tooltipEscapeListener_{{ str_replace('-', '_', $span->id) }}) {
                    document.removeEventListener('keydown', tooltipEscapeListener_{{ str_replace('-', '_', $span->id) }});
                    tooltipEscapeListener_{{ str_replace('-', '_', $span->id) }} = null;
                }
                if (unlockListener_{{ str_replace('-', '_', $span->id) }}) {
                    document.removeEventListener('mousedown', unlockListener_{{ str_replace('-', '_', $span->id) }});
                    unlockListener_{{ str_replace('-', '_', $span->id) }} = null;
                }
            });
        }
        document.body.appendChild(tooltipMask_{{ str_replace('-', '_', $span->id) }});
        // Add Escape key listener
        if (!tooltipEscapeListener_{{ str_replace('-', '_', $span->id) }}) {
            tooltipEscapeListener_{{ str_replace('-', '_', $span->id) }} = function(e) {
                if (e.key === 'Escape') {
                    tooltipLocked_{{ str_replace('-', '_', $span->id) }} = false;
                    hideCombinedTooltip_{{ str_replace('-', '_', $span->id) }}();
                    tooltip.style('pointer-events', 'none');
                    if (tooltipMask_{{ str_replace('-', '_', $span->id) }}) {
                        document.body.removeChild(tooltipMask_{{ str_replace('-', '_', $span->id) }});
                        tooltipMask_{{ str_replace('-', '_', $span->id) }} = null;
                    }
                    document.removeEventListener('keydown', tooltipEscapeListener_{{ str_replace('-', '_', $span->id) }});
                    tooltipEscapeListener_{{ str_replace('-', '_', $span->id) }} = null;
                    if (unlockListener_{{ str_replace('-', '_', $span->id) }}) {
                        document.removeEventListener('mousedown', unlockListener_{{ str_replace('-', '_', $span->id) }});
                        unlockListener_{{ str_replace('-', '_', $span->id) }} = null;
                    }
                }
            };
            document.addEventListener('keydown', tooltipEscapeListener_{{ str_replace('-', '_', $span->id) }});
        }
        // Remove any previous listener
        if (unlockListener_{{ str_replace('-', '_', $span->id) }}) {
            document.removeEventListener('mousedown', unlockListener_{{ str_replace('-', '_', $span->id) }});
        }
        unlockListener_{{ str_replace('-', '_', $span->id) }} = function(e) {
            const svg = document.getElementById(`timeline-combined-container-${spanId}`);
            const tooltipNode = tooltip.node();
            // If mask is present, let it handle the click
            if (tooltipMask_{{ str_replace('-', '_', $span->id) }} && e.target === tooltipMask_{{ str_replace('-', '_', $span->id) }}) {
                return;
            }
            if (
                tooltipNode && !tooltipNode.contains(e.target) &&
                svg && !svg.contains(e.target)
            ) {
                tooltipLocked_{{ str_replace('-', '_', $span->id) }} = false;
                hideCombinedTooltip_{{ str_replace('-', '_', $span->id) }}();
                tooltip.style('pointer-events', 'none');
                if (tooltipMask_{{ str_replace('-', '_', $span->id) }}) {
                    document.body.removeChild(tooltipMask_{{ str_replace('-', '_', $span->id) }});
                    tooltipMask_{{ str_replace('-', '_', $span->id) }} = null;
                }
                document.removeEventListener('mousedown', unlockListener_{{ str_replace('-', '_', $span->id) }});
                unlockListener_{{ str_replace('-', '_', $span->id) }} = null;
                if (tooltipEscapeListener_{{ str_replace('-', '_', $span->id) }}) {
                    document.removeEventListener('keydown', tooltipEscapeListener_{{ str_replace('-', '_', $span->id) }});
                    tooltipEscapeListener_{{ str_replace('-', '_', $span->id) }} = null;
                }
            }
        };
        document.addEventListener('mousedown', unlockListener_{{ str_replace('-', '_', $span->id) }});
    }

    function showCombinedTooltip_{{ str_replace('-', '_', $span->id) }}(event, connections, timelineName, isCurrentSpan, hoverYear, mode = 'absolute', isDuring = false) {
        tooltip.transition().duration(200).style('opacity', 1);
        let tooltipContent = '';
        
        console.log('showCombinedTooltip called with hoverYear:', hoverYear, 'timelineName:', timelineName, 'isDuring:', isDuring);
        
        if (mode === 'relative') {
            // In relative mode, show "At age X" as the main header
            tooltipContent = `<strong>At age ${hoverYear}</strong>`;
        } else {
            // In absolute mode, show "In {year}" as the main header
            tooltipContent = `<strong>In ${hoverYear}</strong>`;
        }
        
        // Add connection details
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
            
            // Add time information
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
        
        // Add what others were doing at the specific hover time
        const concurrentActivities = findActivitiesAtTime_{{ str_replace('-', '_', $span->id) }}(hoverYear, timelineData, timelineName, mode);
        console.log('Found concurrent activities:', concurrentActivities.length);
        if (concurrentActivities.length > 0) {
            // In both modes, just show the activities directly since header already shows the time context
            tooltipContent += `<br/><br/>`;
            tooltipContent += formatActivitiesWithDividers_{{ str_replace('-', '_', $span->id) }}(concurrentActivities, mode, hoverYear);
        }
        
        tooltip.html(tooltipContent)
            .style('left', (event.pageX - 50) + 'px')
            .style('top', (event.pageY + 20) + 'px');
    }

    function findActivitiesAtTime_{{ str_replace('-', '_', $span->id) }}(year, timelineData, currentTimelineName, mode = 'absolute') {
        const activities = [];
        
        if (mode === 'absolute') {
            timelineData.forEach(timeline => {
                if (timeline.timeline.connections) {
                    filterConnectionsDeep(timeline.timeline.connections)
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
                                nested_connections: otherConnection.nested_connections || [],
                                span_id: timeline.id, // Add span_id
                                target_id: otherConnection.target_id // Add target_id
                            });
                        }
                    });
                }
            });
        } else {
            // Relative mode: always include all group members and their life span
            timelineData.forEach(timeline => {
                let found = false;
                let lifeSpanIncluded = false;
                let timelineSpan = timeline.timeline.span;
                let lifeStartAge = 0;
                let lifeEndAge = null;
                if (timelineSpan && timelineSpan.start_year) {
                    lifeEndAge = (timelineSpan.end_year ? timelineSpan.end_year : new Date().getFullYear()) - timelineSpan.start_year;
                }
                // Check if alive at this age
                let alive = (lifeEndAge !== null && year >= lifeStartAge && year <= lifeEndAge);
                // Add life span entry
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
                        alive: alive,
                        span_id: timeline.id, // Add span_id
                        target_id: null // Add target_id
                    });
                    lifeSpanIncluded = true;
                }
                // Add subspans if any
                if (timeline.timeline.connections) {
                    filterConnectionsDeep(timeline.timeline.connections)
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
                                    nested_connections: otherConnection.nested_connections || [],
                                    span_id: timeline.id, // Add span_id
                                    target_id: otherConnection.target_id // Add target_id
                                });
                                found = true;
                            }
                        }
                    });
                }
                if (!lifeSpanIncluded) {
                    // No life span info, show not alive
                    activities.push({
                        timelineName: timeline.name,
                        notAlive: true,
                        isCurrentSpan: timeline.isCurrentSpan,
                        span_id: timeline.id, // Add span_id
                        target_id: null // Add target_id
                    });
                } else if (!alive) {
                    // Not alive at this age
                    activities.push({
                        timelineName: timeline.name,
                        notAlive: true,
                        isCurrentSpan: timeline.isCurrentSpan,
                        span_id: timeline.id, // Add span_id
                        target_id: null // Add target_id
                    });
                } else if (!found) {
                    // Alive but no subspans at this age
                    activities.push({
                        timelineName: timeline.name,
                        noActivity: true,
                        isCurrentSpan: timeline.isCurrentSpan,
                        span_id: timeline.id, // Add span_id
                        target_id: null // Add target_id
                    });
                }
            });
        }
        // Preserve the original order from timelineData to match swimlane order
        return activities.sort((a, b) => {
            // Find the original indices in timelineData
            const aIndex = timelineData.findIndex(t => t.name === a.timelineName);
            const bIndex = timelineData.findIndex(t => t.name === b.timelineName);
            return aIndex - bIndex;
        });
    }

    function formatActivitiesWithDividers_{{ str_replace('-', '_', $span->id) }}(activities, mode = 'absolute', hoverYear = null) {
        if (activities.length === 0) return '';
        // Group activities by timelineName
        const grouped = {};
        activities.forEach(activity => {
            if (!grouped[activity.timelineName]) grouped[activity.timelineName] = [];
            grouped[activity.timelineName].push(activity);
        });
        let formattedContent = '';
        Object.entries(grouped).forEach(([timelineName, acts], idx) => {
            if (idx > 0) {
                formattedContent += '<hr style="margin: 4px 0; border: none; border-top: 1px solid white;">';
            }
            // Find the first activity with a spanId (assume all activities for a timeline have the same spanId)
            const firstActivity = acts.find(a => a.span_id);
            let nameHtml = timelineName;
            if (tooltipLocked_{{ str_replace('-', '_', $span->id) }} && firstActivity && firstActivity.span_id) {
                // Use Laravel route: /spans/{spanId}
                nameHtml = `<a href="/spans/${firstActivity.span_id}" style="color: #fff; text-decoration: underline;">${timelineName}</a>`;
            }
            formattedContent += `<div><strong>${nameHtml}</strong></div>`;
            acts.forEach(activity => {
                if (activity.notAlive) {
                    formattedContent += `<div style='color:#aaa; margin-left: 1.5em;'>Not alive at this age</div>`;
                } else if (activity.noActivity) {
                    formattedContent += `<div style='color:#aaa; margin-left: 1.5em;'>No span at this age</div>`;
                } else if (activity.isLifeSpan && mode === 'absolute') {
                    let endDisplay = (activity.end_year === null || typeof activity.end_year === 'undefined') ? 'now' : activity.end_year;
                    let timeInfo = ` (${activity.start_year} - ${endDisplay})`;
                    formattedContent += `<div style='margin-left: 1.5em;'>• <strong>Life span</strong>${timeInfo}</div>`;
                } else {
                    let timeInfo = '';
                    if (mode === 'absolute') {
                        let endDisplay = (activity.end_year === null || typeof activity.end_year === 'undefined') ? 'now' : activity.end_year;
                        timeInfo = ` (${activity.start_year} - ${endDisplay})`;
                    } else if (typeof activity.start_age !== 'undefined' && typeof activity.end_age !== 'undefined') {
                        let endDisplay = (activity.end_age === null || typeof activity.end_age === 'undefined') ? 'now' : activity.end_age;
                        timeInfo = ` (Age ${activity.start_age} - ${endDisplay})`;
                    }
                    let targetHtml = activity.target_name;
                    if (tooltipLocked_{{ str_replace('-', '_', $span->id) }} && activity.target_id) {
                        targetHtml = `<a href="/spans/${activity.target_id}" style="color: #fff; text-decoration: underline;">${activity.target_name}</a>`;
                    }
                    formattedContent += `<div style='margin-left: 1.5em;'>- ${activity.type_name} ${targetHtml}${timeInfo}</div>`;
                }
                // Nested connections (phases)
                if (activity.nested_connections && activity.nested_connections.length > 0) {
                    activity.nested_connections.forEach(nestedConnection => {
                        const nestedStart = nestedConnection.start_year;
                        const nestedEnd = nestedConnection.end_year || new Date().getFullYear();
                        if (nestedStart <= hoverYear && nestedEnd >= hoverYear) {
                            let nestedTimeInfo = '';
                            if (mode === 'absolute') {
                                let nestedEndDisplay = (nestedConnection.end_year === null || typeof nestedConnection.end_year === 'undefined') ? 'now' : nestedConnection.end_year || 'now';
                                nestedTimeInfo = ` (${nestedConnection.start_year} - ${nestedEndDisplay})`;
                            } else {
                                const timelineSpan = timelineData.find(t => t.name === activity.timelineName)?.timeline?.span;
                                if (timelineSpan && timelineSpan.start_year) {
                                    const nestedStartAge = nestedConnection.start_year - timelineSpan.start_year;
                                    let nestedEndAge = (nestedConnection.end_year === null || typeof nestedConnection.end_year === 'undefined')
                                        ? 'now'
                                        : nestedConnection.end_year - timelineSpan.start_year;
                                    nestedTimeInfo = ` (Age ${nestedStartAge} - ${nestedEndAge})`;
                                }
                            }
                            const nestedBulletColor = getConnectionColor(nestedConnection.type_id);
                            formattedContent += `<div style='margin-left: 3em; color: ${nestedBulletColor};'>└─ ${nestedConnection.type_name} ${nestedConnection.target_name}${nestedTimeInfo}</div>`;
                        }
                    });
                }
            });
        });
        return formattedContent;
    }

    function findConcurrentActivities_{{ str_replace('-', '_', $span->id) }}(connection, timelineData, currentTimelineName) {
        const activities = [];
        const connectionStart = connection.start_year;
        const connectionEnd = connection.end_year || new Date().getFullYear();
        
        timelineData.forEach(timeline => {
            if (timeline.name === currentTimelineName) return; // Skip the current timeline
            
            if (timeline.timeline.connections) {
                filterConnectionsDeep(timeline.timeline.connections)
                    .forEach(otherConnection => {
                    const otherStart = otherConnection.start_year;
                    const otherEnd = otherConnection.end_year || new Date().getFullYear();
                    
                    // Check if the connections overlap in time
                    if (otherStart <= connectionEnd && otherEnd >= connectionStart) {
                        activities.push({
                            timelineName: timeline.name,
                            type_name: otherConnection.type_name,
                            type_id: otherConnection.type_id,
                            target_name: otherConnection.target_name,
                            start_year: otherStart,
                            end_year: otherEnd,
                            isCurrentSpan: timeline.isCurrentSpan
                        });
                    }
                });
            }
        });
        
        // Sort by timeline name for consistent ordering
        return activities.sort((a, b) => a.timelineName.localeCompare(b.timelineName));
    }

    function updateLifeSpanTooltip_{{ str_replace('-', '_', $span->id) }}(event, span, timelineData, timelineName, isCurrentSpan, mode = 'absolute') {
        const svgRect = svg.node().getBoundingClientRect();
        const mouseX = event.clientX - svgRect.left;
        const hoverYear = Math.round(xScale.invert(mouseX));
        console.log('Life span mousemove - mouseX:', mouseX, 'hoverYear:', hoverYear, 'mode:', mode);
        
        tooltip.transition().duration(100).style('opacity', 1);
        
        // Use consistent headers like connection tooltips
        let tooltipContent = '';
        if (mode === 'relative') {
            tooltipContent = `<strong>At age ${hoverYear}</strong>`;
        } else {
            tooltipContent = `<strong>In ${hoverYear}</strong>`;
        }
        
        // Add what others were doing at the specific hover time
        const concurrentActivities = findActivitiesAtTime_{{ str_replace('-', '_', $span->id) }}(hoverYear, timelineData, timelineName, mode);
        if (concurrentActivities.length > 0) {
            tooltipContent += `<br/><br/>`;
            tooltipContent += formatActivitiesWithDividers_{{ str_replace('-', '_', $span->id) }}(concurrentActivities, mode);
        }
        
        tooltip.html(tooltipContent)
            .style('left', (event.pageX - 50) + 'px')
            .style('top', (event.pageY + 20) + 'px');
    }

    function updateConnectionTooltip_{{ str_replace('-', '_', $span->id) }}(event, connections, timelineName, isCurrentSpan, mode = 'absolute', isDuring = false) {
        const svgRect = svg.node().getBoundingClientRect();
        const mouseX = event.clientX - svgRect.left;
        const hoverYear = Math.round(xScale.invert(mouseX));
        console.log('Connection mousemove - mouseX:', mouseX, 'hoverYear:', hoverYear, 'isDuring:', isDuring);
        // Filter connections for tooltip
        const filteredConnections = filterConnectionsDeep(connections);
        showCombinedTooltip_{{ str_replace('-', '_', $span->id) }}(event, filteredConnections, timelineName, isCurrentSpan, hoverYear, mode, isDuring);
    }

    function showCombinedLifeSpanTooltip_{{ str_replace('-', '_', $span->id) }}(event, span, timelineData, timelineName, isCurrentSpan, hoverYear, mode = 'absolute') {
        tooltip.transition().duration(200).style('opacity', 1);
        
        // Use consistent headers like connection tooltips
        let tooltipContent = '';
        if (mode === 'relative') {
            tooltipContent = `<strong>At age ${hoverYear}</strong>`;
        } else {
            tooltipContent = `<strong>In ${hoverYear}</strong>`;
        }
        
        // Add what others were doing at the specific hover time
        const concurrentActivities = findActivitiesAtTime_{{ str_replace('-', '_', $span->id) }}(hoverYear, timelineData, timelineName, mode);
        if (concurrentActivities.length > 0) {
            tooltipContent += `<br/><br/>`;
            tooltipContent += formatActivitiesWithDividers_{{ str_replace('-', '_', $span->id) }}(concurrentActivities, mode, hoverYear);
        }
        
        tooltip.html(tooltipContent)
            .style('left', (event.pageX - 50) + 'px')
            .style('top', (event.pageY + 20) + 'px');
    }

    function hideCombinedTooltip_{{ str_replace('-', '_', $span->id) }}() {
        tooltip.transition().duration(500).style('opacity', 0);
    }
    
    // Set up mode toggle after rendering
    setupModeToggle_{{ str_replace('-', '_', $span->id) }}(timelineData, currentSpan, currentUserSpanId);
}

function calculateCombinedTimeRange_{{ str_replace('-', '_', $span->id) }}(timelineData, currentSpan) {
        const currentYear = new Date().getFullYear();
        let start = currentSpan.start_year || 1900;
        let end = currentYear; // Always extend to current year

        // Extend range to include all timelines and their connections
        timelineData.forEach(timeline => {
            const timelineSpan = timeline.timeline.span;
            if (timelineSpan && timelineSpan.start_year && timelineSpan.start_year < start) {
                start = timelineSpan.start_year;
            }
            // Note: We don't check timelineSpan.end_year here since we always want to go to current year

            if (timeline.timeline.connections) {
                timeline.timeline.connections.forEach(connection => {
                    if (connection.start_year && connection.start_year < start) {
                        start = connection.start_year;
                    }
                    // Note: We don't check connection.end_year here since we always want to go to current year
                });
            }
            
            // Include during connections in time range calculation
            if (timeline.duringConnections) {
                timeline.duringConnections.forEach(connection => {
                    if (connection.start_year && connection.start_year < start) {
                        start = connection.start_year;
                    }
                    // Note: We don't check connection.end_year here since we always want to go to current year
                });
            }
        });

        const padding = Math.max(5, Math.floor((end - start) * 0.1));
        return { start: start - padding, end: end + padding };
    }

    function calculateRelativeTimeRange_{{ str_replace('-', '_', $span->id) }}(timelineData, currentSpan) {
        const currentYear = new Date().getFullYear();
        // Calculate ages for all timelines and their connections
        const allAges = [];
        
        // Add ages for each timeline
        timelineData.forEach(timeline => {
            const timelineSpan = timeline.timeline.span;
            if (timelineSpan && timelineSpan.start_year) {
                // Add life span ages - always extend to current year for living people
                const lifeEndAge = timelineSpan.end_year 
                    ? timelineSpan.end_year - timelineSpan.start_year 
                    : currentYear - timelineSpan.start_year;
                allAges.push(0, lifeEndAge);
                
                // Add connection ages
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
                
                // Add during connection ages
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

        // Add some padding
        const padding = Math.max(2, Math.floor((maxAge - minAge) * 0.1));
        return {
            start: Math.max(0, minAge - padding),
            end: maxAge + padding
        };
    }

function setupModeToggle_{{ str_replace('-', '_', $span->id) }}(timelineData, currentSpan, currentUserSpanId = null) {
    const spanId = '{{ $span->id }}';
    
    console.log('Setting up mode toggle for span:', spanId);
    const radioButtons = document.querySelectorAll(`input[name="timeline-mode-${spanId}"]`);
    console.log('Found radio buttons:', radioButtons.length);
    
    // Remove existing event listeners to prevent duplicates
    radioButtons.forEach(radio => {
        radio.removeEventListener('change', radio._modeToggleHandler);
    });
    
    // Handle mode toggle
    radioButtons.forEach(radio => {
        const handler = function() {
            const selectedMode = this.value;
            console.log('Mode changed to:', selectedMode);
            
            if (this.checked) {
                // Re-render timeline with new mode
                renderCombinedTimeline_{{ str_replace('-', '_', $span->id) }}(timelineData, currentSpan, selectedMode, currentUserSpanId);
            }
        };
        
        // Store the handler reference so we can remove it later
        radio._modeToggleHandler = handler;
        radio.addEventListener('change', handler);
    });
}

// Helper function to filter out unwanted connections (created/photos/sets) recursively
function filterConnectionsDeep(connections) {
    return connections
        .filter(conn => {
            if (conn.target_type === 'connection') return false;
            if (
                conn.type_id === 'created' &&
                conn.target_type === 'thing' &&
                (conn.target_metadata?.subtype === 'photo' || conn.target_metadata?.subtype === 'set')
            ) return false;
            return true;
        })
        .map(conn => {
            // Recursively filter nested_connections if present
            if (Array.isArray(conn.nested_connections)) {
                return {
                    ...conn,
                    nested_connections: filterConnectionsDeep(conn.nested_connections)
                };
            }
            return conn;
        });
}
</script>
@endpush 