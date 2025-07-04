@props(['span'])

<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="bi bi-diagram-3-fill me-2"></i>
            Combined Timeline
        </h5>
    </div>
    <div class="card-body">
        <div id="timeline-combined-container-{{ $span->id }}" style="height: 300px; width: 100%; cursor: crosshair;">
            <!-- D3 combined timeline will be rendered here -->
        </div>
    </div>
</div>

@push('scripts')
<script src="https://d3js.org/d3.v7.min.js"></script>
<script>
// Use a unique identifier to avoid conflicts with other timeline components
const combinedTimelineId = 'combined_{{ str_replace('-', '_', $span->id) }}';

document.addEventListener('DOMContentLoaded', function() {
    // Add a small delay to ensure DOM is fully ready
    setTimeout(() => {
        initializeCombinedTimeline_{{ str_replace('-', '_', $span->id) }}();
    }, 100);
});

function initializeCombinedTimeline_{{ str_replace('-', '_', $span->id) }}() {
    const spanId = '{{ $span->id }}';
    const container = document.getElementById(`timeline-combined-container-${spanId}`);
    
    // Check if container exists
    if (!container) {
        console.error('Combined timeline container not found:', `timeline-combined-container-${spanId}`);
        return;
    }
    
    console.log('Initializing combined timeline for span:', spanId);
    
    // Fetch both the current span's timeline and object connections
    Promise.all([
        fetch(`/spans/${spanId}/timeline`).then(response => response.json()),
        fetch(`/spans/${spanId}/timeline-object-connections`).then(response => response.json())
    ])
    .then(([currentSpanData, objectConnectionsData]) => {
        // Extract unique subjects from the object connections
        const subjects = [...new Set(objectConnectionsData.connections.map(conn => conn.target_id))];
        
        // Start with the current span
        const timelineData = [{
            id: spanId,
            name: currentSpanData.span.name,
            timeline: currentSpanData,
            isCurrentSpan: true
        }];
        
        if (subjects.length > 0) {
            // Fetch timeline data for each subject
            const subjectPromises = subjects.map(subjectId => 
                fetch(`/spans/${subjectId}/timeline`)
                    .then(response => response.json())
                    .then(subjectData => ({
                        id: subjectId,
                        name: objectConnectionsData.connections.find(conn => conn.target_id === subjectId)?.target_name || 'Unknown',
                        timeline: subjectData,
                        isCurrentSpan: false
                    }))
                    .catch(error => {
                        console.error(`Error loading timeline for subject ${subjectId}:`, error);
                        return null;
                    })
            );
            
            Promise.all(subjectPromises)
                .then(subjectData => {
                    const validSubjects = subjectData.filter(subject => subject !== null);
                    timelineData.push(...validSubjects);
                    renderCombinedTimeline_{{ str_replace('-', '_', $span->id) }}(timelineData, currentSpanData.span);
                })
                .catch(error => {
                    console.error('Error loading subject timelines:', error);
                    // Still render with just the current span
                    renderCombinedTimeline_{{ str_replace('-', '_', $span->id) }}(timelineData, currentSpanData.span);
                });
        } else {
            // No subjects, just render current span
            renderCombinedTimeline_{{ str_replace('-', '_', $span->id) }}(timelineData, currentSpanData.span);
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

function renderCombinedTimeline_{{ str_replace('-', '_', $span->id) }}(timelineData, currentSpan) {
    const spanId = '{{ $span->id }}';
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

    // Calculate global time range across all timelines
    const timeRange = calculateCombinedTimeRange_{{ str_replace('-', '_', $span->id) }}(timelineData, currentSpan);
    
    const xScale = d3.scaleLinear()
        .domain([timeRange.start, timeRange.end])
        .range([margin.left, width - margin.right]);

    const xAxis = d3.axisBottom(xScale)
        .tickFormat(d3.format('d'))
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
        
        // Draw swimlane background with special styling for current span
        svg.append('rect')
            .attr('x', margin.left)
            .attr('y', swimlaneY)
            .attr('width', width - margin.left - margin.right)
            .attr('height', swimlaneHeight)
            .attr('fill', isCurrentSpan ? '#e3f2fd' : '#f8f9fa')
            .attr('stroke', isCurrentSpan ? '#dee2e6' : '#dee2e6')
            .attr('stroke-width', isCurrentSpan ? 1 : 1)
            .attr('rx', 4)
            .attr('ry', 4);

        // Add life span bar for this timeline
        const timelineSpan = timeline.timeline.span;
        if (timelineSpan && timelineSpan.start_year) {
            const lifeStartYear = timelineSpan.start_year;
            const lifeEndYear = timelineSpan.end_year || new Date().getFullYear();
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
                    d3.select(this).style('opacity', 0.9);
                    updateLifeSpanTooltip_{{ str_replace('-', '_', $span->id) }}(event, timelineSpan, timelineData, timeline.name, isCurrentSpan);
                })
                .on('mousemove', function(event) {
                    updateLifeSpanTooltip_{{ str_replace('-', '_', $span->id) }}(event, timelineSpan, timelineData, timeline.name, isCurrentSpan);
                })
                .on('mouseout', function() {
                    d3.select(this).style('opacity', hasConnections ? 0.3 : 0.7);
                    hideCombinedTooltip_{{ str_replace('-', '_', $span->id) }}();
                });
        }

        // Add connections for this timeline
        if (timeline.timeline.connections) {
            timeline.timeline.connections.forEach(connection => {
                const connectionType = connection.type_id;
                
                if (connectionType === 'created') {
                    const x = xScale(connection.start_year);
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
                            updateConnectionTooltip_{{ str_replace('-', '_', $span->id) }}(event, [connection], timeline.name, isCurrentSpan);
                        })
                        .on('mousemove', function(event) {
                            updateConnectionTooltip_{{ str_replace('-', '_', $span->id) }}(event, [connection], timeline.name, isCurrentSpan);
                        })
                        .on('mouseout', function() {
                            d3.select(this).style('opacity', 0.8);
                            hideCombinedTooltip_{{ str_replace('-', '_', $span->id) }}();
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
                            updateConnectionTooltip_{{ str_replace('-', '_', $span->id) }}(event, [connection], timeline.name, isCurrentSpan);
                        })
                        .on('mousemove', function(event) {
                            updateConnectionTooltip_{{ str_replace('-', '_', $span->id) }}(event, [connection], timeline.name, isCurrentSpan);
                        })
                        .on('mouseout', function() {
                            d3.select(this).style('opacity', 0.9);
                            hideCombinedTooltip_{{ str_replace('-', '_', $span->id) }}();
                        });
                } else {
                    const endYear = connection.end_year || new Date().getFullYear();
                    const connectionWidth = xScale(endYear) - xScale(connection.start_year);
                    
                    svg.append('rect')
                        .attr('class', 'timeline-bar')
                        .attr('x', xScale(connection.start_year))
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
                            updateConnectionTooltip_{{ str_replace('-', '_', $span->id) }}(event, [connection], timeline.name, isCurrentSpan);
                        })
                        .on('mousemove', function(event) {
                            updateConnectionTooltip_{{ str_replace('-', '_', $span->id) }}(event, [connection], timeline.name, isCurrentSpan);
                        })
                        .on('mouseout', function() {
                            d3.select(this).style('opacity', 0.6);
                            hideCombinedTooltip_{{ str_replace('-', '_', $span->id) }}();
                        });
                }
            });
        }
    });

    // Add "now" line (current year) - drawn last so it appears on top
    const currentYear = new Date().getFullYear();
    const nowX = xScale(currentYear);
    
    // Only show the "now" line if it's within the visible time range
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
        
        // Add "NOW" label
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
        .style('opacity', 0);

    function showCombinedTooltip_{{ str_replace('-', '_', $span->id) }}(event, connections, timelineName, isCurrentSpan, hoverYear) {
        tooltip.transition().duration(200).style('opacity', 1);
        let tooltipContent = '';
        
        console.log('showCombinedTooltip called with hoverYear:', hoverYear, 'timelineName:', timelineName);
        
        if (connections.length === 1) {
            const d = connections[0];
            const endYear = d.end_year || 'Present';
            tooltipContent = `<strong>${timelineName}: ${d.type_name} ${d.target_name}</strong><br/>${d.start_year} - ${endYear}`;
            
            // Add what others were doing at the specific hover time
            const concurrentActivities = findActivitiesAtTime_{{ str_replace('-', '_', $span->id) }}(hoverYear, timelineData, timelineName);
            console.log('Found concurrent activities:', concurrentActivities.length);
            if (concurrentActivities.length > 0) {
                tooltipContent += `<br/><br/><strong>Others in ${hoverYear}:</strong><br/>`;
                tooltipContent += formatActivitiesWithDividers_{{ str_replace('-', '_', $span->id) }}(concurrentActivities);
            }
        } else {
            tooltipContent = `<strong>${timelineName} - ${connections.length} overlapping connections:</strong><br/>`;
            connections.forEach((d, index) => {
                const endYear = d.end_year || 'Present';
                const bulletColor = getConnectionColor(d.type_id);
                tooltipContent += `<span style="color: ${bulletColor};">●</span> <strong>${d.type_name} ${d.target_name}</strong><br/>&nbsp;&nbsp;&nbsp;&nbsp;${d.start_year} - ${endYear}<br/>`;
            });
            
            // Add what others were doing at the specific hover time for overlapping connections too
            const concurrentActivities = findActivitiesAtTime_{{ str_replace('-', '_', $span->id) }}(hoverYear, timelineData, timelineName);
            if (concurrentActivities.length > 0) {
                tooltipContent += `<br/><strong>Others in ${hoverYear}:</strong><br/>`;
                tooltipContent += formatActivitiesWithDividers_{{ str_replace('-', '_', $span->id) }}(concurrentActivities);
            }
        }
        
        tooltip.html(tooltipContent)
            .style('left', (event.pageX - 50) + 'px')
            .style('top', (event.pageY + 20) + 'px');
    }

    function findActivitiesAtTime_{{ str_replace('-', '_', $span->id) }}(year, timelineData, currentTimelineName) {
        const activities = [];
        
        console.log('findActivitiesAtTime called with year:', year, 'currentTimelineName:', currentTimelineName);
        console.log('timelineData length:', timelineData.length);
        
        timelineData.forEach(timeline => {
            console.log('Checking timeline:', timeline.name, 'connections:', timeline.timeline.connections ? timeline.timeline.connections.length : 0);
            
            if (timeline.timeline.connections) {
                timeline.timeline.connections.forEach(otherConnection => {
                    const otherStart = otherConnection.start_year;
                    const otherEnd = otherConnection.end_year || new Date().getFullYear();
                    
                    console.log('Checking connection:', otherConnection.type_name, otherStart, '-', otherEnd, 'against year:', year);
                    
                    // Check if the connection was active at the specific year
                    if (otherStart <= year && otherEnd >= year) {
                        console.log('Found matching activity:', timeline.name, otherConnection.type_name);
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
        
        console.log('Total activities found:', activities.length);
        
        // Sort by timeline name for consistent ordering, with main span first
        return activities.sort((a, b) => {
            if (a.isCurrentSpan && !b.isCurrentSpan) return -1;
            if (!a.isCurrentSpan && b.isCurrentSpan) return 1;
            return a.timelineName.localeCompare(b.timelineName);
        });
    }

    function formatActivitiesWithDividers_{{ str_replace('-', '_', $span->id) }}(activities) {
        if (activities.length === 0) return '';
        
        let formattedContent = '';
        let currentTimeline = '';
        
        activities.forEach((activity, index) => {
            // Add divider if we're switching to a new timeline
            if (activity.timelineName !== currentTimeline) {
                if (currentTimeline !== '') {
                    formattedContent += '<hr style="margin: 4px 0; border: none; border-top: 1px solid white;">';
                }
                currentTimeline = activity.timelineName;
            }
            
            const bulletColor = getConnectionColor(activity.type_id);
            formattedContent += `<span style="color: ${bulletColor};">●</span> <em>${activity.timelineName}</em>: ${activity.type_name} ${activity.target_name}<br/>`;
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
                timeline.timeline.connections.forEach(otherConnection => {
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

    function updateLifeSpanTooltip_{{ str_replace('-', '_', $span->id) }}(event, span, timelineData, timelineName, isCurrentSpan) {
        const svgRect = svg.node().getBoundingClientRect();
        const mouseX = event.clientX - svgRect.left;
        const hoverYear = Math.round(xScale.invert(mouseX));
        console.log('Life span mousemove - mouseX:', mouseX, 'hoverYear:', hoverYear);
        
        tooltip.transition().duration(100).style('opacity', 1);
        const endYear = span.end_year || 'Present';
        let tooltipContent = `<strong>${timelineName}: Life ${span.name}</strong><br/>${span.start_year} - ${endYear}`;
        
        // Add what others were doing at the specific hover time
        const concurrentActivities = findActivitiesAtTime_{{ str_replace('-', '_', $span->id) }}(hoverYear, timelineData, timelineName);
        if (concurrentActivities.length > 0) {
            tooltipContent += `<br/><br/><strong>Others in ${hoverYear}:</strong><br/>`;
            tooltipContent += formatActivitiesWithDividers_{{ str_replace('-', '_', $span->id) }}(concurrentActivities);
        }
        
        tooltip.html(tooltipContent)
            .style('left', (event.pageX - 50) + 'px')
            .style('top', (event.pageY + 20) + 'px');
    }

    function updateConnectionTooltip_{{ str_replace('-', '_', $span->id) }}(event, connections, timelineName, isCurrentSpan) {
        const svgRect = svg.node().getBoundingClientRect();
        const mouseX = event.clientX - svgRect.left;
        const hoverYear = Math.round(xScale.invert(mouseX));
        console.log('Connection mousemove - mouseX:', mouseX, 'hoverYear:', hoverYear);
        
        showCombinedTooltip_{{ str_replace('-', '_', $span->id) }}(event, connections, timelineName, isCurrentSpan, hoverYear);
    }

    function showCombinedLifeSpanTooltip_{{ str_replace('-', '_', $span->id) }}(event, span, timelineData, timelineName, isCurrentSpan, hoverYear) {
        tooltip.transition().duration(200).style('opacity', 1);
        const endYear = span.end_year || 'Present';
        let tooltipContent = `<strong>${timelineName}: Life ${span.name}</strong><br/>${span.start_year} - ${endYear}`;
        
        // Add what others were doing at the specific hover time
        const concurrentActivities = findActivitiesAtTime_{{ str_replace('-', '_', $span->id) }}(hoverYear, timelineData, timelineName);
        if (concurrentActivities.length > 0) {
            tooltipContent += `<br/><br/><strong>Others in ${hoverYear}:</strong><br/>`;
            tooltipContent += formatActivitiesWithDividers_{{ str_replace('-', '_', $span->id) }}(concurrentActivities);
        }
        
        tooltip.html(tooltipContent)
            .style('left', (event.pageX - 50) + 'px')
            .style('top', (event.pageY + 20) + 'px');
    }

    function hideCombinedTooltip_{{ str_replace('-', '_', $span->id) }}() {
        tooltip.transition().duration(500).style('opacity', 0);
    }

    function calculateCombinedTimeRange_{{ str_replace('-', '_', $span->id) }}(timelineData, currentSpan) {
        let start = currentSpan.start_year || 1900;
        let end = currentSpan.end_year || new Date().getFullYear();

        // Extend range to include all timelines and their connections
        timelineData.forEach(timeline => {
            const timelineSpan = timeline.timeline.span;
            if (timelineSpan && timelineSpan.start_year && timelineSpan.start_year < start) {
                start = timelineSpan.start_year;
            }
            if (timelineSpan && timelineSpan.end_year && timelineSpan.end_year > end) {
                end = timelineSpan.end_year;
            }

            if (timeline.timeline.connections) {
                timeline.timeline.connections.forEach(connection => {
                    if (connection.start_year && connection.start_year < start) {
                        start = connection.start_year;
                    }
                    if (connection.end_year && connection.end_year > end) {
                        end = connection.end_year;
                    }
                });
            }
        });

        const padding = Math.max(5, Math.floor((end - start) * 0.1));
        return { start: start - padding, end: end + padding };
    }
}
</script>
@endpush 