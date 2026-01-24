@props([
    'span',
    'versions',      // Collection of versions (ordered by version_number ascending)
    'selectedVersion' => null  // Currently selected version number (if any)
])

@php
    // Sort versions by version_number ascending (oldest first) for timeline
    $sortedVersions = $versions->sortBy('version_number')->values();
    
    $hasVersions = !$sortedVersions->isEmpty();
@endphp

@if($hasVersions)
@php
    // Calculate time range from first version to now
    $firstVersion = $sortedVersions->first();
    $firstDate = $firstVersion->created_at;
    $firstYear = $firstDate->year;
    $firstMonth = $firstDate->month;
    $firstDay = $firstDate->day;
    
    // Last version ends at "now"
    $now = now();
    $lastYear = $now->year;
    $lastMonth = $now->month;
    $lastDay = $now->day;
    
    // Add small padding before first version (5% of total range, minimum 1 day)
    $totalDays = $firstDate->diffInDays($now);
    $paddingDays = max(1, floor($totalDays * 0.05));
    $paddedStartDate = $firstDate->copy()->subDays($paddingDays);
    
    // Calculate fractional years for precise positioning
    // Use timestamps to calculate precise fractional years
    $startOfYear = $paddedStartDate->copy()->startOfYear();
    $daysIntoYear = $startOfYear->diffInDays($paddedStartDate);
    $daysInYear = $paddedStartDate->isLeapYear() ? 366 : 365;
    $paddedStartYear = $paddedStartDate->year + ($daysIntoYear / $daysInYear);
    
    $startOfNowYear = $now->copy()->startOfYear();
    $daysIntoNowYear = $startOfNowYear->diffInDays($now);
    $daysInNowYear = $now->isLeapYear() ? 366 : 365;
    $nowYear = $now->year + ($daysIntoNowYear / $daysInNowYear);
    
    // For axis display, use integer years
    $minYear = $paddedStartDate->year;
    $maxYear = $now->year;
    
    $timeRange = [
        'start' => $minYear, 
        'end' => $maxYear,
        'startFractional' => $paddedStartYear,
        'endFractional' => $nowYear
    ];
    
    // Prepare version data for timeline
    $versionData = [];
    foreach ($sortedVersions as $index => $version) {
        $startDate = $version->created_at;
        $startYear = $startDate->year;
        $startMonth = $startDate->month;
        $startDay = $startDate->day;
        
        // End date is either the next version's start, or now for the last version
        if ($index < $sortedVersions->count() - 1) {
            $nextVersion = $sortedVersions[$index + 1];
            $endDate = $nextVersion->created_at;
        } else {
            $endDate = $now;
        }
        $endYear = $endDate->year;
        $endMonth = $endDate->month;
        $endDay = $endDate->day;
        
        $versionData[] = [
            'version_number' => $version->version_number,
            'start_year' => $startYear,
            'start_month' => $startMonth,
            'start_day' => $startDay,
            'end_year' => $endYear,
            'end_month' => $endMonth,
            'end_day' => $endDay,
            'created_at' => $version->created_at->toIso8601String(),
            'is_selected' => $selectedVersion && $selectedVersion->version_number === $version->version_number,
            'change_summary' => $version->change_summary,
            'changed_by' => $version->changedBy?->name ?? 'Unknown',
        ];
    }
@endphp

<div class="card mb-4">
    <div class="card-header">
        <h6 class="mb-0">
            <i class="bi bi-clock-history me-2"></i>
            Version Timeline
        </h6>
    </div>
    <div class="card-body" style="overflow-x: auto;">
        <div id="versions-timeline-container-{{ $span->id }}" style="height: auto; min-height: 100px; width: 100%;">
            <!-- D3 timeline will be rendered here -->
        </div>
    </div>
</div>

@push('scripts')
<script src="https://d3js.org/d3.v7.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('versions-timeline-container-{{ $span->id }}');
    if (!container) {
        console.error('Versions timeline container not found');
        return;
    }

    // Prevent double-rendering
    if (container.querySelector('svg')) {
        console.log('Versions timeline already rendered, skipping duplicate render');
        return;
    }

    const margin = { top: 10, right: 10, bottom: 60, left: 10 };
    const swimlaneHeight = 40;
    const swimlaneSpacing = 0;
    const totalSwimlanes = 1; // Single swimlane for all versions
    const axisOffsetFromRows = 20; // Extra gap between swimlane and axis
    
    const height = margin.top + (totalSwimlanes * swimlaneHeight) + axisOffsetFromRows + margin.bottom;
    container.style.height = height + 'px';
    container.innerHTML = '';

    const width = container.offsetWidth || container.getBoundingClientRect().width;
    const timeRange = @json($timeRange);
    const versionData = @json($versionData);
    const spanId = '{{ $span->id }}';
    const spanRouteBase = '{{ route("spans.history", $span) }}';

    // Create SVG
    const svg = d3.select(container)
        .append('svg')
        .style('width', '100%')
        .attr('height', height)
        .attr('viewBox', `0 0 ${width} ${height}`)
        .attr('preserveAspectRatio', 'none');

    // Create scales - use fractional years for accurate positioning
    const scaleStart = timeRange.startFractional !== undefined ? timeRange.startFractional : timeRange.start;
    const scaleEnd = timeRange.endFractional !== undefined ? timeRange.endFractional : timeRange.end;
    const xScale = d3.scaleLinear()
        .domain([scaleStart, scaleEnd])
        .range([margin.left, width - margin.right]);

    // Create axis with monthly ticks
    const monthTicks = [];
    const yearRange = timeRange.end - timeRange.start;
    const startDate = new Date(timeRange.start, 0, 1);
    const endDate = new Date(timeRange.end + 1, 0, 1);
    
    // Generate monthly ticks
    let currentDate = new Date(startDate);
    while (currentDate <= endDate) {
        const year = currentDate.getFullYear();
        const month = currentDate.getMonth();
        // Calculate fractional year for this month
        const daysInYear = new Date(year, 11, 31).getTime() - new Date(year, 0, 1).getTime();
        const daysIntoYear = (currentDate.getTime() - new Date(year, 0, 1).getTime());
        const fractionalYear = year + (daysIntoYear / daysInYear);
        monthTicks.push({
            value: fractionalYear,
            year: year,
            month: month,
            date: new Date(currentDate)
        });
        // Move to next month
        currentDate.setMonth(currentDate.getMonth() + 1);
    }
    
    const xAxis = d3.axisBottom(xScale)
        .tickValues(monthTicks.map(t => t.value))
        .tickFormat(function(d) {
            // Find the tick data for this value
            const tick = monthTicks.find(t => Math.abs(t.value - d) < 0.01);
            if (!tick) return '';
            
            // Always show year at January (month 0)
            if (tick.month === 0) {
                // Show labels based on time range
                if (yearRange <= 1) {
                    // Less than 1 year: show "Jan YYYY"
                    return 'Jan ' + tick.year;
                } else if (yearRange <= 2) {
                    // 1-2 years: show "Jan YYYY"
                    return 'Jan ' + tick.year;
                } else if (yearRange <= 5) {
                    // 2-5 years: show "Jan YYYY"
                    return 'Jan ' + tick.year;
                } else {
                    // More than 5 years: show just year
                    return d3.format('d')(tick.year);
                }
            }
            
            // For non-January months, show month names based on time range
            if (yearRange <= 1) {
                // Less than 1 year: show month names for every month
                const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                return monthNames[tick.month];
            } else if (yearRange <= 2) {
                // 1-2 years: show month names for Apr, Jul, Oct (Jan already handled above)
                if (tick.month === 3 || tick.month === 6 || tick.month === 9) {
                    const monthNames = ['', '', '', 'Apr', '', '', 'Jul', '', '', 'Oct', '', ''];
                    return monthNames[tick.month];
                }
                return '';
            } else if (yearRange <= 5) {
                // 2-5 years: show "Jul YYYY" (Jan already handled above)
                if (tick.month === 6) {
                    return 'Jul ' + tick.year;
                }
                return '';
            } else {
                // More than 5 years: no labels for non-January months
                return '';
            }
        })
        .tickSize(4);

    const axisGroup = svg.append('g')
        .attr('class', 'timeline-axis')
        .attr('transform', `translate(0, ${margin.top + (totalSwimlanes * swimlaneHeight) + axisOffsetFromRows})`)
        .call(xAxis);
    
    // Style the ticks - major ticks (with labels) get larger, minor ticks smaller
    axisGroup.selectAll('.tick')
        .each(function(d) {
            const tick = d3.select(this);
            const tickData = monthTicks.find(t => Math.abs(t.value - d) < 0.01);
            if (!tickData) return;
            
            // Check if this tick has a label
            const hasLabel = tick.select('text').text() !== '';
            
            if (hasLabel) {
                // Major tick: larger and darker
                tick.select('line')
                    .attr('y2', 6)
                    .attr('stroke', '#666')
                    .attr('stroke-width', 1);
            } else {
                // Minor tick: smaller and lighter
                tick.select('line')
                    .attr('y2', 3)
                    .attr('stroke', '#ddd')
                    .attr('stroke-width', 0.5);
            }
        });


    // Helper function to convert date to fractional year
    function dateToFractionalYear(year, month, day) {
        if (!year) return null;
        if (!month || month === 0) {
            return year + 0.5;
        }
        if (!day || day === 0) {
            const daysInMonth = new Date(year, month, 0).getDate();
            const dayOfYear = new Date(year, month - 1, 1).getTime();
            const startOfYear = new Date(year, 0, 1).getTime();
            const daysFromStart = (dayOfYear - startOfYear) / (1000 * 60 * 60 * 24);
            const monthDays = daysInMonth / 2;
            return year + (daysFromStart + monthDays) / 365.25;
        }
        const date = new Date(year, month - 1, day);
        const startOfYear = new Date(year, 0, 1);
        const daysFromStart = (date - startOfYear) / (1000 * 60 * 60 * 24);
        return year + daysFromStart / 365.25;
    }

    // Draw swimlane background
    const swimlaneY = margin.top;
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

    // Create tooltip
    const tooltip = d3.select('body').append('div')
        .attr('class', 'tooltip')
        .style('opacity', 0)
        .style('position', 'absolute')
        .style('background', 'rgba(0, 0, 0, 0.8)')
        .style('color', 'white')
        .style('padding', '8px')
        .style('border-radius', '4px')
        .style('font-size', '12px')
        .style('pointer-events', 'none')
        .style('z-index', 1000);

    // Draw version bars
    versionData.forEach(function(version) {
        const startYear = dateToFractionalYear(version.start_year, version.start_month, version.start_day);
        const endYear = dateToFractionalYear(version.end_year, version.end_month, version.end_day);
        
        if (!startYear || !endYear) return;
        
        const x = xScale(startYear);
        const barWidth = xScale(endYear) - xScale(startYear);
        
        // Color: selected version gets primary color, others get muted
        const fillColor = version.is_selected ? '#0d6efd' : '#6c757d';
        const opacity = version.is_selected ? 0.8 : 0.5;
        
        const bar = svg.append('rect')
            .attr('class', 'version-bar')
            .attr('x', x)
            .attr('y', swimlaneY + 2)
            .attr('width', Math.max(barWidth, 2)) // Minimum width of 2px
            .attr('height', swimlaneHeight - 4)
            .attr('fill', fillColor)
            .attr('stroke', version.is_selected ? '#0a58ca' : '#495057')
            .attr('stroke-width', version.is_selected ? 2 : 1)
            .attr('rx', 2)
            .attr('ry', 2)
            .style('opacity', opacity)
            .style('cursor', 'pointer')
            .on('mouseover', function(event) {
                d3.select(this)
                    .style('opacity', 1)
                    .attr('stroke-width', 2);
                
                const startDate = new Date(version.start_year, version.start_month - 1, version.start_day);
                const endDate = new Date(version.end_year, version.end_month - 1, version.end_day);
                const startStr = startDate.toLocaleDateString('en-GB', { year: 'numeric', month: 'short', day: 'numeric' });
                const endStr = endDate.toLocaleDateString('en-GB', { year: 'numeric', month: 'short', day: 'numeric' });
                
                tooltip.transition()
                    .duration(200)
                    .style('opacity', 0.9);
                tooltip.html(`
                    <strong>Version ${version.version_number}</strong><br/>
                    ${startStr} - ${endStr}<br/>
                    ${version.change_summary ? '<small>' + version.change_summary + '</small><br/>' : ''}
                    <small>Changed by: ${version.changed_by}</small>
                `)
                    .style('left', (event.pageX + 10) + 'px')
                    .style('top', (event.pageY - 10) + 'px');
            })
            .on('mouseout', function() {
                d3.select(this)
                    .style('opacity', opacity)
                    .attr('stroke-width', version.is_selected ? 2 : 1);
                
                tooltip.transition()
                    .duration(200)
                    .style('opacity', 0);
            })
            .on('click', function() {
                // Navigate to this version
                const url = spanRouteBase + '/' + version.version_number;
                window.location.href = url;
            });
        
        // Add version number label if bar is wide enough
        if (barWidth > 30) {
            svg.append('text')
                .attr('x', x + barWidth / 2)
                .attr('y', swimlaneY + swimlaneHeight / 2)
                .attr('text-anchor', 'middle')
                .attr('dominant-baseline', 'middle')
                .style('font-size', '11px')
                .style('font-weight', version.is_selected ? 'bold' : 'normal')
                .style('fill', version.is_selected ? '#fff' : '#333')
                .style('pointer-events', 'none')
                .text('v' + version.version_number);
        }
    });
});
</script>
@endpush
@endif
