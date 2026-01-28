@props([
    'subject',              // The span whose connections are being displayed
    'timelineData',         // Array of swimlane objects: [{type: 'life'|'connection', label: string, y: number, connection?: object, connectionType?: string}]
    'containerId',          // Unique container ID for this timeline instance
    'subjectStartYear',     // Subject's start year
    'subjectEndYear',       // Subject's end year
    'timeRange',            // {start: number, end: number} time range for the timeline
    'showAddButton' => false,  // Whether to show the add connection button in the header
    'connectionType' => null,  // Optional connection type for this timeline instance ('all' for combined views)
])

@if($showAddButton && $subject)
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">Timeline</h5>
        @auth
            @if(auth()->user()->can('update', $subject))
                <button type="button" class="btn btn-sm btn-outline-primary" 
                        data-bs-toggle="modal" data-bs-target="#addConnectionModal"
                        data-span-id="{{ $subject->id }}" data-span-name="{{ $subject->name }}" data-span-type="{{ $subject->type_id }}">
                    <i class="bi bi-plus-lg"></i>
                </button>
            @endif
        @endauth
    </div>
@endif

<div class="card-body" style="overflow-x: auto;">
    <div
        id="{{ $containerId }}"
        style="height: auto; min-height: 200px; width: 100%;"
        data-span-id="{{ $subject->id ?? null }}"
        data-connection-type="{{ $connectionType ?? 'all' }}"
    >
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
        /* Opacity transitions handled via SVG attributes, not CSS */
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
    
    // Style the ticks - major ticks (every 5 years) get larger size, minor ticks same color
    axisGroup.selectAll('.tick')
        .each(function(d) {
            const tick = d3.select(this);
            if (d % 5 === 0) {
                // Major tick: larger
                tick.select('line')
                    .attr('y2', 6)
                    .attr('stroke', '#666')
                    .attr('stroke-width', 1);
            } else {
                // Minor tick: smaller but same color as major ticks for better visibility
                tick.select('line')
                    .attr('y2', 4)
                    .attr('stroke', '#666')
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

    // Helper function to check if a bar's row is visible (not filtered out)
    function isBarRowVisible(barElement) {
        // Get the SVG element and find the parent row group
        const svg = barElement.ownerSVGElement;
        if (!svg) return true;
        
        // Walk up the DOM to find the row group
        let current = barElement.parentNode;
        while (current && current !== svg) {
            if (current.classList && current.classList.contains('timeline-row')) {
                // Check if the row has the hidden class
                return !current.classList.contains('timeline-row--hidden');
            }
            current = current.parentNode;
        }
        
        // If we can't find the row, assume it's visible
        return true;
    }
    
    // Track all active tooltips for this timeline instance
    const activeTooltips = [];
    
    // Helper function to hide all other tooltips before showing a new one
    function hideAllTooltips(exceptTooltip) {
        activeTooltips.forEach(function(t) {
            if (t !== exceptTooltip && t.node()) {
                const opacity = parseFloat(t.style('opacity')) || 0;
                if (opacity > 0) {
                    t.transition()
                        .duration(200)
                        .style('opacity', 0)
                        .on('end', function() {
                            // Disable pointer events when fully hidden
                            d3.select(this).style('pointer-events', 'none');
                        });
                }
            }
        });
    }
    
    // Helper function to check if mouse is over any visible tooltip
    function isMouseOverTooltip(event) {
        const x = event.pageX;
        const y = event.pageY;
        
        return activeTooltips.some(function(t) {
            if (!t.node()) return false;
            const opacity = parseFloat(t.style('opacity')) || 0;
            // Only consider tooltips that are actually visible
            if (opacity === 0) return false;
            
            // Also check pointer-events to be sure
            const pointerEvents = t.style('pointer-events');
            if (pointerEvents === 'none') return false;
            
            const rect = t.node().getBoundingClientRect();
            return x >= rect.left && x <= rect.right && y >= rect.top && y <= rect.bottom;
        });
    }
    
    // Helper function to position tooltip with viewport overflow detection
    function positionTooltip(tooltip, event, offsetX = 10, offsetY = -10) {
        const tooltipNode = tooltip.node();
        if (!tooltipNode) return;
        
        const viewportWidth = window.innerWidth;
        const estimatedTooltipWidth = 250; // Estimate for tooltip width
        const margin = 10; // Margin from viewport edge
        
        // Check if tooltip would overflow on the right
        const cursorX = event.pageX;
        const wouldOverflow = (cursorX + offsetX + estimatedTooltipWidth) > (viewportWidth - margin);
        
        let finalLeft;
        if (wouldOverflow) {
            // Position to the left of cursor
            // First, set it temporarily to measure actual width
            tooltip.style('left', (cursorX + offsetX) + 'px')
                   .style('top', (event.pageY + offsetY) + 'px')
                   .style('visibility', 'hidden')
                   .style('opacity', '0');
            
            // Force layout to measure
            tooltipNode.offsetHeight;
            const actualWidth = tooltipNode.getBoundingClientRect().width || estimatedTooltipWidth;
            
            // Calculate position to the left
            const leftPosition = cursorX - actualWidth - offsetX;
            finalLeft = Math.max(margin, leftPosition);
        } else {
            // Normal position to the right of cursor
            finalLeft = cursorX + offsetX;
        }
        
        // Set final position
        tooltip.style('left', finalLeft + 'px')
               .style('top', (event.pageY + offsetY) + 'px')
               .style('visibility', 'visible');
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
            );
        
        // Add connection state attribute for placeholder filtering
        if (swimlane.type === 'connection' && swimlane.connection) {
            const connection = swimlane.connection;
            let state = null;
            
            // Try different ways to access the state - same logic as in tooltip
            let connectionSpanForState = connection.connection_span || connection.connectionSpan;
            if (connectionSpanForState && connectionSpanForState.data) {
                connectionSpanForState = connectionSpanForState.data;
            }
            
            if (connectionSpanForState && connectionSpanForState.state) {
                state = connectionSpanForState.state;
            } else if (connection.connection_span && connection.connection_span.state) {
                state = connection.connection_span.state;
            } else if (connection.connectionSpan && connection.connectionSpan.state) {
                state = connection.connectionSpan.state;
            } else if (connection.state) {
                state = connection.state;
            }
            
            // Default to placeholder if no state found
            if (!state) {
                state = 'placeholder';
            }
            
            rowGroup.attr('data-connection-state', state);
        }
        
        rowGroup.attr('transform', `translate(0, ${baseY})`);

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
                
                // Add to active tooltips list
                activeTooltips.push(lifeTooltip);
                
                const subjectName = swimlane.label || 'Life';
                const startDateStr = formatDate(subjectStartYear, null, null);
                const endDateStr = subjectEndYear ? formatDate(subjectEndYear, null, null) : 'ongoing';
                
                lifeBar.on('mouseover', function(event) {
                    // Check if the bar's row is visible before showing tooltip
                    if (!isBarRowVisible(this)) {
                        return;
                    }
                    
                    // Don't show tooltip if mouse is over another tooltip
                    if (isMouseOverTooltip(event)) {
                        return;
                    }
                    
                    // Hide all other tooltips before showing this one
                    hideAllTooltips(lifeTooltip);
                    
                    lifeBar.attr('fill-opacity', 0.8);
                    // Life tooltip doesn't need pointer-events (no edit button)
                    lifeTooltip.transition()
                        .duration(200)
                        .style('opacity', 1);
                    
                    lifeTooltip.html(`
                        <strong>${subjectName}</strong><br/>
                        ${startDateStr} - ${endDateStr}
                    `);
                    
                    // Position tooltip with overflow detection
                    positionTooltip(lifeTooltip, event, 10, -10);
                })
                .on('mouseout', function() {
                    lifeBar.attr('fill-opacity', 1);
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
            
            // Get connection state to determine if it's a placeholder
            let connectionState = 'placeholder'; // default
            if (connectionSpan && connectionSpan.state) {
                connectionState = connectionSpan.state;
            } else if (connection.connection_span && connection.connection_span.state) {
                connectionState = connection.connection_span.state;
            } else if (connection.connectionSpan && connection.connectionSpan.state) {
                connectionState = connection.connectionSpan.state;
            } else if (connection.state) {
                connectionState = connection.state;
            }
            
            const hasDates = connectionSpan && connectionSpan.start_year;
            const isPlaceholder = connectionState === 'placeholder';
            
            let barX, barWidth, barColor, barOpacity, isClickable;
            
            if (hasDates && !isPlaceholder) {
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
                // Placeholder connections: use connection type color with 25% opacity
                barX = xScale(timeRange.start);
                barWidth = xScale(timeRange.end) - xScale(timeRange.start);
                barColor = getConnectionColor(swimlane.connectionType);
                barOpacity = 0.25;
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
                .attr('fill-opacity', barOpacity)  // Use fill-opacity attribute for better control
                .attr('stroke', 'white')
                .attr('stroke-width', 1)
                .attr('rx', 2)
                .attr('ry', 2)
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
                .style('pointer-events', 'none') // Start with none, enable when shown
                .style('z-index', '1000')
                .style('opacity', 0);
            
            // Add to active tooltips list
            activeTooltips.push(tooltip);
            
            // Track tooltip visibility timeout
            let tooltipTimeout = null;

            if (isClickable) {
                bar.on('mouseover', function(event) {
                    // Check if the bar's row is visible before showing tooltip
                    if (!isBarRowVisible(this)) {
                        return;
                    }
                    
                    // Don't show tooltip if mouse is over another tooltip
                    if (isMouseOverTooltip(event)) {
                        return;
                    }
                    
                    // Hide all other tooltips before showing this one
                    hideAllTooltips(tooltip);
                    
                    bar.attr('fill-opacity', 0.9);
                    // Enable pointer events and show tooltip
                    tooltip.style('pointer-events', 'auto');
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
                    
                    // Get end date values - don't default to current date, use null if missing
                    let endYear = connectionSpan.end_year || null;
                    let endMonth = connectionSpan.end_month || null;
                    let endDay = connectionSpan.end_day || null;
                    
                    // If end date exists and is in the future, cap it at current date
                    if (endYear && (endYear > currentYear || 
                        (endYear === currentYear && endMonth && endMonth > currentMonth) ||
                        (endYear === currentYear && endMonth === currentMonth && endDay && endDay > currentDay))) {
                        endYear = currentYear;
                        endMonth = currentMonth;
                        endDay = currentDay;
                    }
                    
                    const isOngoing = !connectionSpan.end_year;
                    
                    // Format dates for display - pass null for missing month/day, not current values
                    const startDateStr = formatDate(startYear, startMonth, startDay);
                    const endDateStr = isOngoing ? 'ongoing' : formatDate(endYear, endMonth, endDay);
                    
                    // Build tooltip text with predicate
                    const tooltipTitle = predicate ? `${predicate} ${otherSpan.name}` : otherSpan.name;
                    
                    // Get connection ID if available
                    const connectionId = connection.id || null;
                    const subjectSpanId = '{{ $subject->id }}';
                    const subjectSpanName = '{{ addslashes($subject->name) }}';
                    const subjectSpanType = '{{ $subject->type_id }}';
                    const canEdit = {{ auth()->check() && auth()->user()->can('update', $subject) ? 'true' : 'false' }};
                    
                    // Get connection state - try multiple ways to access it
                    let connectionState = 'placeholder'; // default
                    if (connectionSpan && connectionSpan.state) {
                        connectionState = connectionSpan.state;
                    } else if (connection.connection_span && connection.connection_span.state) {
                        connectionState = connection.connection_span.state;
                    } else if (connection.connectionSpan && connection.connectionSpan.state) {
                        connectionState = connection.connectionSpan.state;
                    } else if (connection.state) {
                        connectionState = connection.state;
                    }
                    const stateLabel = connectionState.charAt(0).toUpperCase() + connectionState.slice(1);
                    
                    // Build footer with state and edit button
                    let footerHtml = '';
                    if (connectionId && canEdit) {
                        footerHtml = `
                            <div style="margin-top: 8px; padding-top: 8px; border-top: 1px solid rgba(255,255,255,0.3); display: flex; justify-content: space-between; align-items: center;">
                                <span style="font-size: 11px; opacity: 0.8;"><strong>${stateLabel}</strong></span>
                                <button type="button" 
                                        class="btn btn-sm btn-outline-light edit-connection-btn" 
                                        data-connection-id="${connectionId}"
                                        data-span-id="${subjectSpanId}"
                                        data-span-name="${subjectSpanName}"
                                        data-span-type="${subjectSpanType}"
                                        style="font-size: 11px; padding: 2px 8px;">
                                    <i class="bi bi-pencil me-1"></i>Edit
                                </button>
                            </div>
                        `;
                    } else {
                        // Show state even if no edit button
                        footerHtml = `
                            <div style="margin-top: 8px; padding-top: 8px; border-top: 1px solid rgba(255,255,255,0.3);">
                                <span style="font-size: 11px; opacity: 0.8;"><strong>${stateLabel}</strong></span>
                            </div>
                        `;
                    }
                    
                    tooltip.html(`
                        <strong>${tooltipTitle}</strong><br/>
                        ${startDateStr}${isOngoing ? ' (ongoing)' : ` - ${endDateStr}`}
                        ${footerHtml}
                    `);
                    
                    // Position tooltip with overflow detection
                    positionTooltip(tooltip, event, 10, -10);
                    
                    // Add click handler for edit button
                    if (connectionId && canEdit) {
                        tooltip.select('.edit-connection-btn').on('click', function(e) {
                            e.stopPropagation(); // Prevent bar click from firing
                            const btn = d3.select(this);
                            const connId = btn.attr('data-connection-id');
                            const spanId = btn.attr('data-span-id');
                            const spanName = btn.attr('data-span-name');
                            const spanType = btn.attr('data-span-type');
                            
                            // Trigger modal with edit mode
                            const modalButton = $('<button>')
                                .attr('type', 'button')
                                .attr('data-bs-toggle', 'modal')
                                .attr('data-bs-target', '#addConnectionModal')
                                .attr('data-span-id', spanId)
                                .attr('data-span-name', spanName)
                                .attr('data-span-type', spanType)
                                .attr('data-connection-id', connId)
                                .css('display', 'none')
                                .appendTo('body');
                            
                            modalButton.trigger('click');
                            modalButton.remove();
                            
                            // Hide tooltip
                            tooltip.transition()
                                .duration(200)
                                .style('opacity', 0)
                                .on('end', function() {
                                    // Disable pointer events when fully hidden
                                    d3.select(this).style('pointer-events', 'none');
                                });
                        });
                    }
                })
                .on('mouseout', function() {
                    bar.attr('fill-opacity', barOpacity);
                    // Delay hiding tooltip to allow mouse to move to tooltip
                    tooltipTimeout = setTimeout(function() {
                        tooltip.transition()
                            .duration(500)
                            .style('opacity', 0)
                            .on('end', function() {
                                // Disable pointer events when fully hidden
                                d3.select(this).style('pointer-events', 'none');
                            });
                    }, 200);
                })
                .on('click', function() {
                    const connectionSpanSlug = connectionSpan.slug;
                    window.location.href = `/spans/${connectionSpanSlug}`;
                });
            } else {
                bar.on('mouseover', function(event) {
                    // Check if the bar's row is visible before showing tooltip
                    if (!isBarRowVisible(this)) {
                        return;
                    }
                    
                    // Don't show tooltip if mouse is over another tooltip
                    if (isMouseOverTooltip(event)) {
                        return;
                    }
                    
                    // Hide all other tooltips before showing this one
                    hideAllTooltips(tooltip);
                    
                    bar.attr('fill-opacity', 0.6);
                    // Enable pointer events and show tooltip
                    tooltip.style('pointer-events', 'auto');
                    tooltip.transition()
                        .duration(200)
                        .style('opacity', 1);
                    
                    const otherSpan = connection.other_span || connection.otherSpan;
                    const predicate = connection.predicate || '';
                    const tooltipTitle = predicate ? `${predicate} ${otherSpan.name}` : otherSpan.name;
                    
                    // Get connection ID if available
                    const connectionId = connection.id || null;
                    const subjectSpanId = '{{ $subject->id }}';
                    const subjectSpanName = '{{ addslashes($subject->name) }}';
                    const subjectSpanType = '{{ $subject->type_id }}';
                    const canEdit = {{ auth()->check() && auth()->user()->can('update', $subject) ? 'true' : 'false' }};
                    
                    // Get connection state - try multiple ways to access it
                    // First, get the connectionSpan reference (same way as above)
                    let connectionSpanForState = connection.connection_span || connection.connectionSpan;
                    if (connectionSpanForState && connectionSpanForState.data) {
                        connectionSpanForState = connectionSpanForState.data;
                    }
                    
                    let connectionState = 'placeholder'; // default
                    if (connectionSpanForState && connectionSpanForState.state) {
                        connectionState = connectionSpanForState.state;
                    } else if (connection.connection_span && connection.connection_span.state) {
                        connectionState = connection.connection_span.state;
                    } else if (connection.connectionSpan && connection.connectionSpan.state) {
                        connectionState = connection.connectionSpan.state;
                    } else if (connection.state) {
                        connectionState = connection.state;
                    }
                    const stateLabel = connectionState.charAt(0).toUpperCase() + connectionState.slice(1);
                    
                    // Build footer with state and edit button
                    let footerHtml = '';
                    if (connectionId && canEdit) {
                        footerHtml = `
                            <div style="margin-top: 8px; padding-top: 8px; border-top: 1px solid rgba(255,255,255,0.3); display: flex; justify-content: space-between; align-items: center;">
                                <span style="font-size: 11px; opacity: 0.8;"><strong>${stateLabel}</strong></span>
                                <button type="button" 
                                        class="btn btn-sm btn-outline-light edit-connection-btn" 
                                        data-connection-id="${connectionId}"
                                        data-span-id="${subjectSpanId}"
                                        data-span-name="${subjectSpanName}"
                                        data-span-type="${subjectSpanType}"
                                        style="font-size: 11px; padding: 2px 8px;">
                                    <i class="bi bi-pencil me-1"></i>Edit
                                </button>
                            </div>
                        `;
                    } else {
                        // Show state even if no edit button
                        footerHtml = `
                            <div style="margin-top: 8px; padding-top: 8px; border-top: 1px solid rgba(255,255,255,0.3);">
                                <span style="font-size: 11px; opacity: 0.8;"><strong>${stateLabel}</strong></span>
                            </div>
                        `;
                    }
                    
                    tooltip.html(`
                        <strong>${tooltipTitle}</strong><br/>
                        <em>Dates unknown</em>
                        ${footerHtml}
                    `);
                    
                    // Position tooltip with overflow detection
                    positionTooltip(tooltip, event, 10, -10);
                    
                    // Add click handler for edit button
                    if (connectionId && canEdit) {
                        tooltip.select('.edit-connection-btn').on('click', function(e) {
                            e.stopPropagation(); // Prevent bar click from firing
                            const btn = d3.select(this);
                            const connId = btn.attr('data-connection-id');
                            const spanId = btn.attr('data-span-id');
                            const spanName = btn.attr('data-span-name');
                            const spanType = btn.attr('data-span-type');
                            
                            // Trigger modal with edit mode
                            const modalButton = $('<button>')
                                .attr('type', 'button')
                                .attr('data-bs-toggle', 'modal')
                                .attr('data-bs-target', '#addConnectionModal')
                                .attr('data-span-id', spanId)
                                .attr('data-span-name', spanName)
                                .attr('data-span-type', spanType)
                                .attr('data-connection-id', connId)
                                .css('display', 'none')
                                .appendTo('body');
                            
                            modalButton.trigger('click');
                            modalButton.remove();
                            
                            // Hide tooltip
                            tooltip.transition()
                                .duration(200)
                                .style('opacity', 0)
                                .on('end', function() {
                                    // Disable pointer events when fully hidden
                                    d3.select(this).style('pointer-events', 'none');
                                });
                        });
                    }
                })
                .on('mouseout', function() {
                    bar.attr('fill-opacity', barOpacity);
                    // Delay hiding tooltip to allow mouse to move to tooltip
                    tooltipTimeout = setTimeout(function() {
                        tooltip.transition()
                            .duration(500)
                            .style('opacity', 0)
                            .on('end', function() {
                                // Disable pointer events when fully hidden
                                d3.select(this).style('pointer-events', 'none');
                            });
                    }, 200);
                });
            }
            
            // Keep tooltip visible when hovering over it (applies to both clickable and non-clickable bars)
            tooltip.on('mouseover', function() {
                if (tooltipTimeout) {
                    clearTimeout(tooltipTimeout);
                    tooltipTimeout = null;
                }
                tooltip.transition()
                    .duration(200)
                    .style('opacity', 1);
            })
            .on('mouseout', function() {
                tooltip.transition()
                    .duration(500)
                    .style('opacity', 0)
                    .on('end', function() {
                        // Disable pointer events when fully hidden
                        d3.select(this).style('pointer-events', 'none');
                    });
            });
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
