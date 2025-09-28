@props(['span'])

@php
    $user = Auth::user();
    $personalSpan = $user->personalSpan;
    
    if ($personalSpan && $span->id !== $personalSpan->id) {
        // Calculate various date comparisons
        $personalStartYear = $personalSpan->start_year;
        $personalEndYear = $personalSpan->end_year;
        $spanStartYear = $span->start_year;
        $spanEndYear = $span->end_year;
        
        $comparisons = [];
        
        // When one was born relative to the other
        if ($personalStartYear && $spanStartYear) {
            $yearDiff = $spanStartYear - $personalStartYear;
            
            // Check if the older person was still alive when the younger was born
            if ($yearDiff > 0) {
                // The span person was born after you
                // Check if you were still alive when they were born
                if (!$personalEndYear || $personalEndYear >= $spanStartYear) {
                    $comparisons[] = "You were {$yearDiff} years old when {$span->name} was born.";
                }
            } elseif ($yearDiff < 0) {
                // You were born after the span person
                $yearDiff = abs($yearDiff);
                // Check if they were still alive when you were born
                if (!$spanEndYear || $spanEndYear >= $personalStartYear) {
                    $comparisons[] = "{$span->name} was {$yearDiff} years old when you were born.";
                } else {
                    // They had already passed away
                    $yearsSinceDeath = $personalStartYear - $spanEndYear;
                    $comparisons[] = "{$span->name} died {$yearsSinceDeath} years before you were born.";
                }
            }
        }
        
        // Overlapping lifetimes
        if ($personalStartYear && $spanStartYear) {
            $overlapStart = max($personalStartYear, $spanStartYear);
            $overlapEnd = min(
                $personalEndYear ?? date('Y'),
                $spanEndYear ?? date('Y')
            );
            
            if ($overlapEnd >= $overlapStart) {
                $overlapYears = $overlapEnd - $overlapStart;
                if ($overlapYears > 0) {
                    // Calculate user's current age
                    $currentYear = date('Y');
                    $userCurrentAge = $personalEndYear ? ($personalEndYear - $personalStartYear) : ($currentYear - $personalStartYear);
                    
                    // Calculate the other person's current age
                    $otherPersonAge = $spanEndYear ? ($spanEndYear - $spanStartYear) : ($currentYear - $spanStartYear);
                    
                    // Only show overlap message if it's not the same as either person's current age
                    if (!$personalEndYear && !$spanEndYear) {
                        // Both still alive - only show if overlap is less than both people's current ages
                        if ($overlapYears < $userCurrentAge && $overlapYears < $otherPersonAge) {
                            $comparisons[] = "Your lives have overlapped for {$overlapYears} years so far.";
                        }
                    } elseif (!$personalEndYear || !$spanEndYear) {
                        $comparisons[] = "Your lives overlapped for {$overlapYears} years.";
                    } else {
                        $comparisons[] = "Your lives overlapped for {$overlapYears} years.";
                    }
                }
            }
        }
        
        // Age at other's death
        if ($personalStartYear && $spanEndYear) {
            if ($spanEndYear >= $personalStartYear) {
                $ageAtDeath = $spanEndYear - $personalStartYear;
                if ($ageAtDeath > 0) {
                    $comparisons[] = "You were {$ageAtDeath} years old when {$span->name} died.";
                }
            }
        } elseif ($spanStartYear && $personalEndYear) {
            if ($personalEndYear >= $spanStartYear) {
                $ageAtDeath = $personalEndYear - $spanStartYear;
                if ($ageAtDeath > 0) {
                    $comparisons[] = "{$span->name} was {$ageAtDeath} years old when you died.";
                }
            }
        }
        
        // Compare total lifespan lengths (only for completed lives)
        if ($personalStartYear && $spanStartYear && $personalEndYear && $spanEndYear) {
            $personalLifespan = $personalEndYear - $personalStartYear;
            $spanLifespan = $spanEndYear - $spanStartYear;
            $lifespanDiff = abs($personalLifespan - $spanLifespan);
            
            if ($personalLifespan > $spanLifespan) {
                $comparisons[] = "You lived {$lifespanDiff} years longer.";
            } elseif ($spanLifespan > $personalLifespan) {
                $comparisons[] = "{$span->name} lived {$lifespanDiff} years longer.";
            }
        }
    }
@endphp

@if(isset($comparisons) && count($comparisons) > 0)
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="card-title mb-0">
            <i class="bi bi-arrow-left-right me-2"></i>
            Comparison
        </h6>
        <a href="{{ route('spans.compare', $span) }}" class="btn btn-sm btn-primary">
            Full Comparison
        </a>
    </div>

    <div class="card-body">
        
        <div class="comparison-timeline position-relative">
            @foreach($comparisons as $comparison)
                <div class="comparison-item d-flex align-items-center mb-2">
                    <div class="comparison-icon me-3">
                        <i class="bi bi-clock-history text-primary"></i>
                    </div>
                    <div class="comparison-text">
                        {{ $comparison }}
                    </div>
                </div>
            @endforeach
        </div>
        
        @if($personalStartYear && $spanStartYear)
            <div class="timeline-visualization mt-4">
                <div class="d-flex justify-content-between align-items-center small text-muted mb-1">
                    <span>{{ min($personalStartYear, $spanStartYear) }}</span>
                    <span>{{ max($personalEndYear ?? date('Y'), $spanEndYear ?? date('Y')) }}</span>
                </div>
                <div id="mini-comparison-timeline-{{ $span->id }}" class="position-relative" style="height: 100px;">
                    <!-- Mini D3 timeline will be rendered here -->
                </div>
            </div>
        @endif
    </div>
</div>

@push('scripts')
<script src="https://d3js.org/d3.v7.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    initializeMiniComparisonTimeline_{{ str_replace('-', '_', $span->id) }}();
});

function initializeMiniComparisonTimeline_{{ str_replace('-', '_', $span->id) }}() {
    const spanId = '{{ $span->id }}';
    const personalSpanId = '{{ $personalSpan->id }}';
    console.log('Initializing mini comparison timeline for spans:', spanId, personalSpanId);
    
    // Fetch timeline data for both spans
    Promise.all([
        fetch(`/api/spans/${spanId}`, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } }),
        fetch(`/api/spans/${personalSpanId}`, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
    ])
    .then(responses => Promise.all(responses.map(r => r.json())))
    .then(([data1, data2]) => {
        console.log('Mini timeline API response data:', data1, data2);
        renderMiniComparisonTimeline_{{ str_replace('-', '_', $span->id) }}(data1, data2);
    })
    .catch(error => {
        console.error('Error loading mini timeline data:', error);
        document.getElementById(`mini-comparison-timeline-${spanId}`).innerHTML = 
            '<div class="text-muted text-center py-4">No timeline data available</div>';
    });
}

function renderMiniComparisonTimeline_{{ str_replace('-', '_', $span->id) }}(data1, data2) {
    const spanId = '{{ $span->id }}';
    const container = document.getElementById(`mini-comparison-timeline-${spanId}`);
    const width = container.clientWidth;
    const height = 100;
    const margin = { top: 5, right: 5, bottom: 20, left: 5 };
    const swimlaneHeight = 25;
    const swimlaneSpacing = 5;

    // Clear container
    container.innerHTML = '';

    // Create SVG
    const svg = d3.select(container)
        .append('svg')
        .attr('width', width)
        .attr('height', height);

    // Calculate combined time range for proportional scaling
    const timeRange = calculateMiniTimeRange_{{ str_replace('-', '_', $span->id) }}(data1, data2);
    
    // Create scales
    const xScale = d3.scaleLinear()
        .domain([timeRange.start, timeRange.end])
        .range([margin.left, width - margin.right]);

    // Create axis (simplified)
    const xAxis = d3.axisBottom(xScale)
        .tickFormat(d3.format('d'))
        .ticks(5);

    svg.append('g')
        .attr('transform', `translate(0, ${height - margin.bottom})`)
        .call(xAxis)
        .selectAll('text')
        .style('font-size', '10px');

    // Define colors for different connection types - now reading from CSS
    function getConnectionColor(typeId) {
        // Try to get color from CSS custom property first
        const cssColor = getComputedStyle(document.documentElement)
            .getPropertyValue(`--connection-${typeId}-color`);
        
        if (cssColor && cssColor.trim() !== '') {
            return cssColor.trim();
        }
        
        // Fallback to a function that reads from the existing CSS classes
        const testElement = document.createElement('div');
        testElement.className = `bg-${typeId}`;
        testElement.style.display = 'none';
        document.body.appendChild(testElement);
        
        const computedStyle = getComputedStyle(testElement);
        const backgroundColor = computedStyle.backgroundColor;
        
        document.body.removeChild(testElement);
        
        // If we got a valid color, return it
        if (backgroundColor && backgroundColor !== 'rgba(0, 0, 0, 0)' && backgroundColor !== 'transparent') {
            return backgroundColor;
        }
        
        // Final fallback colors
        const fallbackColors = {
            'residence': '#007bff',
            'employment': '#28a745',
            'education': '#ffc107',
            'membership': '#dc3545',
            'family': '#6f42c1',
            'relationship': '#fd7e14',
            'travel': '#20c997',
            'participation': '#e83e8c',
            'ownership': '#6c757d',
            'created': '#17a2b8',
            'contains': '#6610f2',
            'has_role': '#fd7e14',
            'at_organisation': '#20c997',
            'life': '#000000' // Black for the life span
        };
        
        return fallbackColors[typeId] || '#6c757d';
    }

    const connectionColors = {
        'life': '#000000' // Keep life span color as black
    };

    // Calculate swimlane positions
    const swimlane1Y = margin.top;
    const swimlane2Y = margin.top + swimlaneHeight + swimlaneSpacing;

    // Add life span bars
    if (data1.span.start_year) {
        const lifeStartYear = data1.span.start_year;
        const lifeEndYear = data1.span.end_year || new Date().getFullYear();
        
        svg.append('rect')
            .attr('class', 'life-span-1')
            .attr('x', xScale(lifeStartYear))
            .attr('y', swimlane1Y + 2)
            .attr('width', xScale(lifeEndYear) - xScale(lifeStartYear))
            .attr('height', swimlaneHeight - 4)
            .attr('fill', connectionColors.life)
            .attr('stroke', 'white')
            .attr('stroke-width', 1)
            .attr('rx', 1)
            .attr('ry', 1)
            .style('opacity', 0.3)
            .style('pointer-events', 'none');
    }

    if (data2.span.start_year) {
        const lifeStartYear = data2.span.start_year;
        const lifeEndYear = data2.span.end_year || new Date().getFullYear();
        
        svg.append('rect')
            .attr('class', 'life-span-2')
            .attr('x', xScale(lifeStartYear))
            .attr('y', swimlane2Y + 2)
            .attr('width', xScale(lifeEndYear) - xScale(lifeStartYear))
            .attr('height', swimlaneHeight - 4)
            .attr('fill', connectionColors.life)
            .attr('stroke', 'white')
            .attr('stroke-width', 1)
            .attr('rx', 1)
            .attr('ry', 1)
            .style('opacity', 0.3)
            .style('pointer-events', 'none');
    }

    // Create timeline bars for span 1 (the person being viewed)
    if (data1.connections && data1.connections.length > 0) {
        svg.selectAll('.mini-timeline-bar-1')
            .data(data1.connections)
            .enter()
            .each(function(d, i) {
                const connection = d;
                const connectionType = connection.type_id;
                
                if (connectionType === 'created') {
                    // For "created" connections, draw a vertical line with a circle
                    const x = xScale(connection.start_year);
                    const y1 = swimlane1Y;
                    const y2 = swimlane1Y + swimlaneHeight;
                    const circleY = (y1 + y2) / 2; // Center the circle vertically
                    const circleRadius = 2;
                    
                    // Draw vertical line
                    svg.append('line')
                        .attr('class', 'mini-timeline-moment-1')
                        .attr('x1', x)
                        .attr('x2', x)
                        .attr('y1', y1)
                        .attr('y2', y2)
                        .attr('stroke', getConnectionColor(connectionType))
                        .attr('stroke-width', 1.5)
                        .style('opacity', 0.8)
                        .style('pointer-events', 'none');
                    
                    // Draw circle in the middle
                    svg.append('circle')
                        .attr('class', 'mini-timeline-moment-circle-1')
                        .attr('cx', x)
                        .attr('cy', circleY)
                        .attr('r', circleRadius)
                        .attr('fill', getConnectionColor(connectionType))
                        .attr('stroke', 'white')
                        .attr('stroke-width', 0.5)
                        .style('opacity', 0.9)
                        .style('pointer-events', 'none');
                } else {
                    // For other connection types, draw horizontal bars as before
                    const endYear = connection.end_year || new Date().getFullYear();
                    const width = xScale(endYear) - xScale(connection.start_year);
                    
                    svg.append('rect')
                        .attr('class', 'mini-timeline-bar-1')
                        .attr('x', xScale(connection.start_year))
                        .attr('y', swimlane1Y + 2)
                        .attr('width', width)
                        .attr('height', swimlaneHeight - 4)
                        .attr('fill', getConnectionColor(connectionType))
                        .attr('stroke', 'white')
                        .attr('stroke-width', 0.5)
                        .attr('rx', 1)
                        .attr('ry', 1)
                        .style('opacity', 0.6)
                        .style('pointer-events', 'none');
                }
            });
    }

    // Create timeline bars for span 2 (the user)
    if (data2.connections && data2.connections.length > 0) {
        svg.selectAll('.mini-timeline-bar-2')
            .data(data2.connections)
            .enter()
            .each(function(d, i) {
                const connection = d;
                const connectionType = connection.type_id;
                
                if (connectionType === 'created') {
                    // For "created" connections, draw a vertical line with a circle
                    const x = xScale(connection.start_year);
                    const y1 = swimlane2Y;
                    const y2 = swimlane2Y + swimlaneHeight;
                    const circleY = (y1 + y2) / 2; // Center the circle vertically
                    const circleRadius = 2;
                    
                    // Draw vertical line
                    svg.append('line')
                        .attr('class', 'mini-timeline-moment-2')
                        .attr('x1', x)
                        .attr('x2', x)
                        .attr('y1', y1)
                        .attr('y2', y2)
                        .attr('stroke', getConnectionColor(connectionType))
                        .attr('stroke-width', 1.5)
                        .style('opacity', 0.8)
                        .style('pointer-events', 'none');
                    
                    // Draw circle in the middle
                    svg.append('circle')
                        .attr('class', 'mini-timeline-moment-circle-2')
                        .attr('cx', x)
                        .attr('cy', circleY)
                        .attr('r', circleRadius)
                        .attr('fill', getConnectionColor(connectionType))
                        .attr('stroke', 'white')
                        .attr('stroke-width', 0.5)
                        .style('opacity', 0.9)
                        .style('pointer-events', 'none');
                } else {
                    // For other connection types, draw horizontal bars as before
                    const endYear = connection.end_year || new Date().getFullYear();
                    const width = xScale(endYear) - xScale(connection.start_year);
                    
                    svg.append('rect')
                        .attr('class', 'mini-timeline-bar-2')
                        .attr('x', xScale(connection.start_year))
                        .attr('y', swimlane2Y + 2)
                        .attr('width', width)
                        .attr('height', swimlaneHeight - 4)
                        .attr('fill', getConnectionColor(connectionType))
                        .attr('stroke', 'white')
                        .attr('stroke-width', 0.5)
                        .attr('rx', 1)
                        .attr('ry', 1)
                        .style('opacity', 0.6)
                        .style('pointer-events', 'none');
                }
            });
    }
}

function calculateMiniTimeRange_{{ str_replace('-', '_', $span->id) }}(data1, data2) {
    let start = Math.min(
        data1.span.start_year || 1900,
        data2.span.start_year || 1900
    );
    let end = Math.max(
        data1.span.end_year || new Date().getFullYear(),
        data2.span.end_year || new Date().getFullYear()
    );

    // Extend range to include all connections from both spans
    const allConnections = [
        ...(data1.connections || []),
        ...(data2.connections || [])
    ];

    allConnections.forEach(connection => {
        if (connection.start_year && connection.start_year < start) {
            start = connection.start_year;
        }
        if (connection.end_year && connection.end_year > end) {
            end = connection.end_year;
        }
    });

    // Add some padding
    const padding = Math.max(2, Math.floor((end - start) * 0.05));
    return {
        start: start - padding,
        end: end + padding
    };
}
</script>
@endpush
@endif 