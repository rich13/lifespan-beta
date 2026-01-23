@props([
    'subject',           // The span whose connections are being displayed
    'timelineData',      // Array of swimlane objects: [{type: 'life'|'connection', label: string, y: number, connection?: object, connectionType?: string}]
    'containerId',       // Unique container ID for this timeline instance
    'subjectStartYear',  // Subject's start year
    'subjectEndYear',    // Subject's end year
    'timeRange'          // {start: number, end: number} time range for the timeline
])

<div class="card-body" style="overflow-x: auto;">
    <div id="{{ $containerId }}" style="height: auto; min-height: 200px; width: 100%;">
        <!-- D3 timeline will be rendered here -->
    </div>
</div>

@push('styles')
<style>
    /* Basic styling for timeline rows and bars to support JS filtering */
    .timeline-row {
        transition: transform 250ms cubic-bezier(.4, 0, .2, 1), opacity 200ms ease-in-out;
    }

    .timeline-row--hidden {
        opacity: 0;
        pointer-events: none;
    }

    .timeline-bar {
        transition: opacity 200ms ease-in-out;
    }

    .timeline-bg {
        transition: opacity 200ms ease-in-out;
    }

    #{{ $containerId }} svg {
        width: 100%;
        max-width: 100%;
    }
</style>
@endpush

@push('scripts')
<script src="https://d3js.org/d3.v7.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('{{ $containerId }}');
    if (!container) {
        console.error('Timeline container not found: {{ $containerId }}');
        return;
    }

    // Prevent double-rendering - if SVG already exists, skip
    if (container.querySelector('svg')) {
        console.log('Timeline already rendered, skipping duplicate render');
        return;
    }

    // Minimal margins - no labels, so we can use full width
    // Bottom margin needs extra space for axis ticks and labels
    // Right margin is 0 to fill the container completely
    const margin = { top: 10, right: 0, bottom: 100, left: 10 };
    const swimlaneHeight = 20;
    const swimlaneSpacing = 8;
    const overallSwimlaneY = margin.top + 10;

    // Timeline data from props
    const timelineData = @json($timelineData);
    const subjectStartYear = {{ $subjectStartYear ?? 'null' }};
    const subjectEndYear = {{ $subjectEndYear ?? 'null' }};
    const timeRange = @json($timeRange);

    const totalSwimlanes = timelineData.length;
    const height = margin.top + (totalSwimlanes * swimlaneHeight) + ((totalSwimlanes - 1) * swimlaneSpacing) + margin.bottom;

    // Update container height
    container.style.height = height + 'px';

    // Clear container
    container.innerHTML = '';

    // Get container width - SVG will be set to 100% so this is the width we'll use
    const width = container.offsetWidth || container.getBoundingClientRect().width;

    // Create SVG - set to 100% width so it fills container, use viewBox for scaling
    const svg = d3.select(container)
        .append('svg')
        .style('width', '100%')
        .attr('height', height)
        .attr('viewBox', `0 0 ${width} ${height}`)
        .attr('preserveAspectRatio', 'none');

    // Create scales - use full width
    const xScale = d3.scaleLinear()
        .domain([timeRange.start, timeRange.end])
        .range([margin.left, width]);

    // Create axis with ticks every year, labels every 5 years
    const allYears = [];
    for (let year = timeRange.start; year <= timeRange.end; year++) {
        allYears.push(year);
    }
    
    const xAxis = d3.axisBottom(xScale)
        .tickValues(allYears)
        .tickFormat(function(d) {
            // Only show labels every 5 years
            if (d % 5 === 0) {
                return d3.format('d')(d);
            }
            return '';
        })
        .tickSize(4); // Default tick size for minor ticks

    const axisOffsetFromRows = 20; // Extra gap between last swimlane and axis

    const axisGroup = svg.append('g')
        .attr('class', 'timeline-axis')
        .attr('transform', `translate(0, ${height - margin.bottom + axisOffsetFromRows})`)
        .call(xAxis);
    
    // Style the ticks - major ticks (every 5 years) get larger size and darker color
    axisGroup.selectAll('.tick')
        .each(function(d) {
            const tick = d3.select(this);
            if (d % 5 === 0) {
                // Major tick: larger and darker
                tick.select('line')
                    .attr('y2', 6)
                    .attr('stroke', '#666')
                    .attr('stroke-width', 1);
            } else {
                // Minor tick: smaller and lighter
                tick.select('line')
                    .attr('y2', 4)
                    .attr('stroke', '#ccc')
                    .attr('stroke-width', 0.5);
            }
        });

    // Add axis label (positioned below the axis ticks)
    svg.append('text')
        .attr('x', width / 2)
        .attr('y', height - (margin.bottom - 50) + axisOffsetFromRows)
        .attr('text-anchor', 'middle')
        .style('font-size', '12px')
        .style('fill', '#666')
        .text('Year');

    // Get connection color function
    function getConnectionColor(typeId) {
        return getComputedStyle(document.documentElement)
            .getPropertyValue(`--connection-${typeId}-color`) || '#007bff';
    }

    // Convert a date (year, month, day) to fractional years for positioning
    function dateToFractionalYear(year, month, day) {
        if (!year) return null;
        if (!month || month === 0) {
            // Year precision: use middle of year
            return year + 0.5;
        }
        if (!day || day === 0) {
            // Month precision: use middle of month
            const daysInMonth = new Date(year, month, 0).getDate();
            const dayOfYear = new Date(year, month - 1, 1).getTime();
            const startOfYear = new Date(year, 0, 1).getTime();
            const daysFromStart = (dayOfYear - startOfYear) / (1000 * 60 * 60 * 24);
            const monthDays = daysInMonth / 2;
            return year + (daysFromStart + monthDays) / 365.25;
        }
        // Day precision: use exact day
        const date = new Date(year, month - 1, day);
        const startOfYear = new Date(year, 0, 1);
        const daysFromStart = (date - startOfYear) / (1000 * 60 * 60 * 24);
        return year + daysFromStart / 365.25;
    }

    // Calculate duration in days between two dates
    function calculateDurationInDays(startYear, startMonth, startDay, endYear, endMonth, endDay) {
        if (!startYear || !endYear) return null;
        
        // Default to first/last of year if month/day not specified
        const start = new Date(
            startYear,
            (startMonth || 1) - 1,
            startDay || 1
        );
        const end = new Date(
            endYear,
            endMonth ? (endMonth - 1) : 11,
            endDay || (endMonth ? new Date(endYear, endMonth, 0).getDate() : 31)
        );
        
        const diffTime = end - start;
        return Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    }

    // Format date for display
    function formatDate(year, month, day) {
        if (!year) return '';
        if (!month || month === 0) {
            return year.toString();
        }
        if (!day || day === 0) {
            const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            return `${monthNames[month - 1]} ${year}`;
        }
        const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        return `${monthNames[month - 1]} ${day}, ${year}`;
    }

    // Render each swimlane
    let rowIndex = 0;
    timelineData.forEach((swimlane) => {
        const baseY = overallSwimlaneY + (rowIndex * (swimlaneHeight + swimlaneSpacing));
        rowIndex++;

        // Group all elements for this swimlane so we can toggle visibility as a unit
        const rowGroup = svg.append('g')
            .attr('class', 'timeline-row')
            .attr('data-connection-type', swimlane.type === 'life'
                ? 'life'
                : (swimlane.connectionType || '')
            )
            .attr('transform', `translate(0, ${baseY})`);

        // Add swimlane background
        rowGroup.append('rect')
            .attr('class', 'timeline-bg')
            .attr('x', 0)
            .attr('y', 0)
            .attr('width', width)
            .attr('height', swimlaneHeight)
            .attr('fill', '#f8f9fa')
            .attr('stroke', '#dee2e6')
            .attr('stroke-width', 1);

        if (swimlane.type === 'life') {
            // Add life span bar - styled like connection bars but in black
            if (subjectStartYear) {
                const lifeStart = xScale(subjectStartYear);
                const lifeEnd = subjectEndYear ? xScale(subjectEndYear) : xScale(new Date().getFullYear());
                
                const lifeBar = rowGroup.append('rect')
                    .attr('class', 'timeline-bar')
                    .attr('data-connection-type', 'life')
                    .attr('x', lifeStart)
                    .attr('y', 2)
                    .attr('width', Math.max(lifeEnd - lifeStart, 2))
                    .attr('height', swimlaneHeight - 4)
                    .attr('fill', '#000000')
                    .attr('stroke', 'white')
                    .attr('stroke-width', 1)
                    .attr('rx', 2)
                    .attr('ry', 2)
                    .style('opacity', 0.9)
                    .style('cursor', 'pointer');
                
                // Add tooltip for life span
                const lifeTooltip = d3.select('body').append('div')
                    .attr('class', 'tooltip')
                    .style('position', 'absolute')
                    .style('background', 'rgba(0,0,0,0.8)')
                    .style('color', 'white')
                    .style('padding', '8px')
                    .style('border-radius', '4px')
                    .style('font-size', '12px')
                    .style('pointer-events', 'none')
                    .style('z-index', '1000')
                    .style('opacity', 0);
                
                const subjectName = swimlane.label || 'Life';
                const startDateStr = formatDate(subjectStartYear, null, null);
                const endDateStr = subjectEndYear ? formatDate(subjectEndYear, null, null) : 'ongoing';
                
                lifeBar.on('mouseover', function(event) {
                    lifeBar.style('opacity', 0.8);
                    lifeTooltip.transition()
                        .duration(200)
                        .style('opacity', 1);
                    
                    lifeTooltip.html(`
                        <strong>${subjectName}</strong><br/>
                        ${startDateStr} - ${endDateStr}
                    `)
                    .style('left', (event.pageX + 10) + 'px')
                    .style('top', (event.pageY - 10) + 'px');
                })
                .on('mouseout', function() {
                    lifeBar.style('opacity', 1);
                    lifeTooltip.transition()
                        .duration(500)
                        .style('opacity', 0);
                });
            }
        } else {
            // Add individual connection bar
            const connection = swimlane.connection;
            // Handle both serialized objects and Eloquent models
            let connectionSpan = connection.connection_span || connection.connectionSpan;
            // If connectionSpan is an object with a 'data' property (JSON serialized relationship), extract it
            if (connectionSpan && connectionSpan.data) {
                connectionSpan = connectionSpan.data;
            }
                const hasDates = connectionSpan && connectionSpan.start_year;
            
            let barX, barWidth, barColor, barOpacity, isClickable;
            
            if (hasDates) {
                const startYear = connectionSpan.start_year;
                const startMonth = connectionSpan.start_month;
                const startDay = connectionSpan.start_day;
                const currentYear = new Date().getFullYear();
                const currentMonth = new Date().getMonth() + 1;
                const currentDay = new Date().getDate();
                
                // Cap end date at "now" (current date)
                let endYear = connectionSpan.end_year || currentYear;
                let endMonth = connectionSpan.end_month || currentMonth;
                let endDay = connectionSpan.end_day || currentDay;
                
                // If end date is in the future, cap it at current date
                if (endYear > currentYear || 
                    (endYear === currentYear && endMonth > currentMonth) ||
                    (endYear === currentYear && endMonth === currentMonth && endDay > currentDay)) {
                    endYear = currentYear;
                    endMonth = currentMonth;
                    endDay = currentDay;
                }
                
                // Convert to fractional years for accurate positioning
                const startFractional = dateToFractionalYear(startYear, startMonth, startDay);
                const endFractional = dateToFractionalYear(endYear, endMonth, endDay);
                
                barX = xScale(startFractional);
                barWidth = xScale(endFractional) - xScale(startFractional);
                barColor = getConnectionColor(swimlane.connectionType);
                barOpacity = 0.7;
                isClickable = true;
            } else {
                barX = xScale(timeRange.start);
                barWidth = xScale(timeRange.end) - xScale(timeRange.start);
                barColor = '#6c757d';
                barOpacity = 0.4;
                isClickable = false;
            }

            const bar = rowGroup.append('rect')
                .attr('class', 'timeline-bar')
                .attr('data-connection-type', swimlane.connectionType || '')
                .attr('x', barX)
                .attr('y', 2)
                .attr('width', Math.max(barWidth, 2))
                .attr('height', swimlaneHeight - 4)
                .attr('fill', barColor)
                .attr('stroke', 'white')
                .attr('stroke-width', 1)
                .attr('rx', 2)
                .attr('ry', 2)
                .style('opacity', barOpacity)
                .style('cursor', isClickable ? 'pointer' : 'default');

            // Add tooltip
            const tooltip = d3.select('body').append('div')
                .attr('class', 'tooltip')
                .style('position', 'absolute')
                .style('background', 'rgba(0,0,0,0.8)')
                .style('color', 'white')
                .style('padding', '8px')
                .style('border-radius', '4px')
                .style('font-size', '12px')
                .style('pointer-events', 'none')
                .style('z-index', '1000')
                .style('opacity', 0);

            if (isClickable) {
                bar.on('mouseover', function(event) {
                    bar.style('opacity', 0.9);
                    tooltip.transition()
                        .duration(200)
                        .style('opacity', 1);
                    
                    const otherSpan = connection.other_span || connection.otherSpan;
                    const predicate = connection.predicate || '';
                    const startYear = connectionSpan.start_year;
                    const startMonth = connectionSpan.start_month;
                    const startDay = connectionSpan.start_day;
                    const currentYear = new Date().getFullYear();
                    const currentMonth = new Date().getMonth() + 1;
                    const currentDay = new Date().getDate();
                    
                    // Cap end date at "now" (current date) for display
                    let endYear = connectionSpan.end_year || currentYear;
                    let endMonth = connectionSpan.end_month || currentMonth;
                    let endDay = connectionSpan.end_day || currentDay;
                    
                    // If end date is in the future, cap it at current date
                    if (endYear > currentYear || 
                        (endYear === currentYear && endMonth > currentMonth) ||
                        (endYear === currentYear && endMonth === currentMonth && endDay > currentDay)) {
                        endYear = currentYear;
                        endMonth = currentMonth;
                        endDay = currentDay;
                    }
                    
                    const isOngoing = !connectionSpan.end_year;
                    
                    // Format dates for display
                    const startDateStr = formatDate(startYear, startMonth, startDay);
                    const endDateStr = isOngoing ? 'ongoing' : formatDate(endYear, endMonth, endDay);
                    
                    // Calculate actual duration
                    const durationDays = calculateDurationInDays(startYear, startMonth, startDay, endYear, endMonth, endDay);
                    let durationStr = '';
                    if (durationDays !== null) {
                        if (durationDays < 30) {
                            durationStr = `(${durationDays} ${durationDays === 1 ? 'day' : 'days'})`;
                        } else if (durationDays < 365) {
                            const months = Math.round(durationDays / 30);
                            durationStr = `(~${months} ${months === 1 ? 'month' : 'months'})`;
                        } else {
                            const years = (durationDays / 365.25).toFixed(1);
                            durationStr = `(~${years} ${years === '1.0' ? 'year' : 'years'})`;
                        }
                    }
                    
                    // Build tooltip text with predicate
                    const tooltipTitle = predicate ? `${predicate} ${otherSpan.name}` : otherSpan.name;
                    
                    tooltip.html(`
                        <strong>${tooltipTitle}</strong><br/>
                        ${startDateStr}${isOngoing ? ' (ongoing)' : ` - ${endDateStr}`}<br/>
                        ${durationStr}
                    `)
                    .style('left', (event.pageX + 10) + 'px')
                    .style('top', (event.pageY - 10) + 'px');
                })
                .on('mouseout', function() {
                    bar.style('opacity', barOpacity);
                    tooltip.transition()
                        .duration(500)
                        .style('opacity', 0);
                })
                .on('click', function() {
                    const connectionSpanSlug = connectionSpan.slug;
                    window.location.href = `/spans/${connectionSpanSlug}`;
                });
            } else {
                bar.on('mouseover', function(event) {
                    bar.style('opacity', 0.6);
                    tooltip.transition()
                        .duration(200)
                        .style('opacity', 1);
                    
                    const otherSpan = connection.other_span || connection.otherSpan;
                    const predicate = connection.predicate || '';
                    const tooltipTitle = predicate ? `${predicate} ${otherSpan.name}` : otherSpan.name;
                    
                    tooltip.html(`
                        <strong>${tooltipTitle}</strong><br/>
                        <em>Dates unknown</em>
                    `)
                    .style('left', (event.pageX + 10) + 'px')
                    .style('top', (event.pageY - 10) + 'px');
                })
                .on('mouseout', function() {
                    bar.style('opacity', barOpacity);
                    tooltip.transition()
                        .duration(500)
                        .style('opacity', 0);
                });
            }
        }
    });
    
    // Add "now" vertical line after all swimlanes so it appears on top
    // Only add if current year is within the visible time range
    const currentYear = new Date().getFullYear();
    const nowX = xScale(currentYear);
    
    if (nowX >= 0 && nowX <= width) {
        // Draw vertical line from top margin to just above the axis
        svg.append('line')
            .attr('class', 'now-line')
            .attr('x1', nowX)
            .attr('x2', nowX)
            .attr('y1', margin.top)
            .attr('y2', height - margin.bottom + axisOffsetFromRows)
            .attr('stroke', '#dc3545')
            .attr('stroke-width', 1)
            .style('opacity', 0.8);
        
        // Add "NOW" label at the top
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
});
</script>
@endpush
