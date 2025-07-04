@props(['span'])

<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="bi bi-people-fill me-2"></i>
            Group Timeline
        </h5>
    </div>
    <div class="card-body">
        <div id="timeline-group-container-{{ $span->id }}" style="height: 300px; width: 100%;">
            <!-- D3 group timeline will be rendered here -->
        </div>
    </div>
</div>

@push('scripts')
<script src="https://d3js.org/d3.v7.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Add a small delay to ensure DOM is fully ready
    setTimeout(() => {
        initializeGroupTimeline_{{ str_replace('-', '_', $span->id) }}();
    }, 100);
});

function initializeGroupTimeline_{{ str_replace('-', '_', $span->id) }}() {
    const spanId = '{{ $span->id }}';
    const container = document.getElementById(`timeline-group-container-${spanId}`);
    
    // Check if container exists
    if (!container) {
        console.error('Group timeline container not found:', `timeline-group-container-${spanId}`);
        return;
    }
    
    console.log('Initializing group timeline for span:', spanId);
    
    // Fetch timeline data for object connections to get the subjects
    fetch(`/spans/${spanId}/timeline-object-connections`)
        .then(response => response.json())
        .then(data => {
            // Extract unique subjects from the object connections
            const subjects = [...new Set(data.connections.map(conn => conn.target_id))];
            
            if (subjects.length === 0) {
                container.innerHTML = '<div class="text-muted text-center py-4">No group connections found</div>';
                return;
            }
            
            // Fetch timeline data for each subject
            const subjectPromises = subjects.map(subjectId => 
                fetch(`/spans/${subjectId}/timeline`)
                    .then(response => response.json())
                    .then(subjectData => ({
                        id: subjectId,
                        name: data.connections.find(conn => conn.target_id === subjectId)?.target_name || 'Unknown',
                        timeline: subjectData
                    }))
                    .catch(error => {
                        console.error(`Error loading timeline for subject ${subjectId}:`, error);
                        return null;
                    })
            );
            
            Promise.all(subjectPromises)
                .then(subjectData => {
                    const validSubjects = subjectData.filter(subject => subject !== null);
                    renderGroupTimeline_{{ str_replace('-', '_', $span->id) }}(validSubjects, data.span);
                })
                .catch(error => {
                    console.error('Error loading group timeline data:', error);
                    container.innerHTML = '<div class="text-danger text-center py-4">Error loading group timeline data</div>';
                });
        })
        .catch(error => {
            console.error('Error loading object connections:', error);
            container.innerHTML = '<div class="text-danger text-center py-4">Error loading group timeline data</div>';
        });
}

function renderGroupTimeline_{{ str_replace('-', '_', $span->id) }}(subjects, currentSpan) {
    const spanId = '{{ $span->id }}';
    const container = document.getElementById(`timeline-group-container-${spanId}`);
    
    // Check if container exists
    if (!container) {
        console.error('Group timeline container not found during render:', `timeline-group-container-${spanId}`);
        return;
    }
    
    const width = container.clientWidth;
    const margin = { top: 20, right: 20, bottom: 30, left: 20 };
    const swimlaneHeight = 20;
    const swimlaneSpacing = 10;
    const totalSwimlanes = subjects.length;
    const totalHeight = totalSwimlanes * (swimlaneHeight + swimlaneSpacing) - swimlaneSpacing;
    const adjustedHeight = totalHeight + margin.top + margin.bottom;
    
    // Set container height to fit content
    container.style.height = `${adjustedHeight}px`;

    container.innerHTML = '';
    const svg = d3.select(container)
        .append('svg')
        .attr('width', width)
        .attr('height', adjustedHeight);

    // Calculate global time range across all subjects
    const timeRange = calculateGroupTimeRange_{{ str_replace('-', '_', $span->id) }}(subjects, currentSpan);
    
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

    // Create swimlanes for each subject
    subjects.forEach((subject, index) => {
        const swimlaneY = margin.top + index * (swimlaneHeight + swimlaneSpacing);
        
        // Add subject label
        svg.append('text')
            .attr('x', margin.left - 5)
            .attr('y', swimlaneY + swimlaneHeight / 2)
            .attr('text-anchor', 'end')
            .attr('dominant-baseline', 'middle')
            .attr('font-size', '12px')
            .attr('fill', '#6c757d')
            .text(subject.name);

        // Draw swimlane background
        svg.append('rect')
            .attr('x', margin.left)
            .attr('y', swimlaneY)
            .attr('width', width - margin.left - margin.right)
            .attr('height', swimlaneHeight)
            .attr('fill', '#f8f9fa')
            .attr('stroke', '#dee2e6')
            .attr('stroke-width', 1)
            .attr('rx', 4)
            .attr('ry', 4);

        // Add life span bar for this subject
        const subjectSpan = subject.timeline.span;
        if (subjectSpan && subjectSpan.start_year) {
            const lifeStartYear = subjectSpan.start_year;
            const lifeEndYear = subjectSpan.end_year || new Date().getFullYear();
            const hasConnections = subject.timeline.connections && subject.timeline.connections.length > 0;
            
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
                .style('pointer-events', hasConnections ? 'none' : 'auto')
                .on('mouseover', function(event) {
                    if (!hasConnections) {
                        d3.select(this).style('opacity', 0.9);
                        showGroupLifeSpanTooltip_{{ str_replace('-', '_', $span->id) }}(event, subjectSpan);
                    }
                })
                .on('mouseout', function() {
                    if (!hasConnections) {
                        d3.select(this).style('opacity', 0.7);
                        hideGroupTooltip_{{ str_replace('-', '_', $span->id) }}();
                    }
                });
        }

        // Add connections for this subject
        if (subject.timeline.connections) {
            subject.timeline.connections.forEach(connection => {
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
                            showGroupTooltip_{{ str_replace('-', '_', $span->id) }}(event, [connection], subject.name);
                        })
                        .on('mouseout', function() {
                            d3.select(this).style('opacity', 0.8);
                            hideGroupTooltip_{{ str_replace('-', '_', $span->id) }}();
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
                            showGroupTooltip_{{ str_replace('-', '_', $span->id) }}(event, [connection], subject.name);
                        })
                        .on('mouseout', function() {
                            d3.select(this).style('opacity', 0.9);
                            hideGroupTooltip_{{ str_replace('-', '_', $span->id) }}();
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
                            showGroupTooltip_{{ str_replace('-', '_', $span->id) }}(event, [connection], subject.name);
                        })
                        .on('mouseout', function() {
                            d3.select(this).style('opacity', 0.6);
                            hideGroupTooltip_{{ str_replace('-', '_', $span->id) }}();
                        });
                }
            });
        }
    });

    // Create tooltip
    const tooltip = d3.select('body').append('div')
        .attr('class', `group-tooltip-${spanId}`)
        .style('position', 'absolute')
        .style('background', 'rgba(0, 0, 0, 0.8)')
        .style('color', 'white')
        .style('padding', '8px')
        .style('border-radius', '4px')
        .style('font-size', '12px')
        .style('pointer-events', 'none')
        .style('opacity', 0);

    function showGroupTooltip_{{ str_replace('-', '_', $span->id) }}(event, connections, subjectName) {
        tooltip.transition().duration(200).style('opacity', 1);
        let tooltipContent = '';
        
        if (connections.length === 1) {
            const d = connections[0];
            const endYear = d.end_year || 'Present';
            tooltipContent = `<strong>${subjectName}: ${d.type_name} ${d.target_name}</strong><br/>${d.start_year} - ${endYear}`;
        } else {
            tooltipContent = `<strong>${subjectName} - ${connections.length} overlapping connections:</strong><br/>`;
            connections.forEach((d, index) => {
                const endYear = d.end_year || 'Present';
                const bulletColor = getConnectionColor(d.type_id);
                tooltipContent += `<span style="color: ${bulletColor};">●</span> <strong>${d.type_name} ${d.target_name}</strong><br/>&nbsp;&nbsp;&nbsp;&nbsp;${d.start_year} - ${endYear}<br/>`;
            });
        }
        
        tooltip.html(tooltipContent)
            .style('left', (event.pageX - 50) + 'px')
            .style('top', (event.pageY + 20) + 'px');
    }

    function showGroupLifeSpanTooltip_{{ str_replace('-', '_', $span->id) }}(event, span) {
        tooltip.transition().duration(200).style('opacity', 1);
        const endYear = span.end_year || 'Present';
        const tooltipContent = `<strong>${span.name}'s Life</strong><br/>${span.start_year} - ${endYear}`;
        tooltip.html(tooltipContent)
            .style('left', (event.pageX - 50) + 'px')
            .style('top', (event.pageY + 20) + 'px');
    }

    function hideGroupTooltip_{{ str_replace('-', '_', $span->id) }}() {
        tooltip.transition().duration(500).style('opacity', 0);
    }

    function calculateGroupTimeRange_{{ str_replace('-', '_', $span->id) }}(subjects, currentSpan) {
        let start = currentSpan.start_year || 1900;
        let end = currentSpan.end_year || new Date().getFullYear();

        // Extend range to include all subjects and their connections
        subjects.forEach(subject => {
            const subjectSpan = subject.timeline.span;
            if (subjectSpan && subjectSpan.start_year && subjectSpan.start_year < start) {
                start = subjectSpan.start_year;
            }
            if (subjectSpan && subjectSpan.end_year && subjectSpan.end_year > end) {
                end = subjectSpan.end_year;
            }

            if (subject.timeline.connections) {
                subject.timeline.connections.forEach(connection => {
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