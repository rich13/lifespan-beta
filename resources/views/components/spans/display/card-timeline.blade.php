@props(['span'])

<div class="card-timeline-container" style="height: 100%; width: 100%;">
    <div id="card-timeline-{{ $span->id }}" style="height: 100%; width: 100%;">
        <!-- Background D3 timeline will be rendered here -->
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize card timeline when global timeline data is available
    if (window.globalTimelineData) {
        loadCardTimelineData_{{ str_replace('-', '_', $span->id) }}();
    } else {
        // Wait for global timeline data to be loaded
        document.addEventListener('globalTimelineReady', function() {
            loadCardTimelineData_{{ str_replace('-', '_', $span->id) }}();
        });
    }
});

function loadCardTimelineData_{{ str_replace('-', '_', $span->id) }}() {
    const spanId = '{{ $span->id }}';
    
    // Fetch timeline data for this span
    fetch(`/spans/${spanId}/timeline`)
        .then(response => response.json())
        .then(data => {
            renderCardTimeline_{{ str_replace('-', '_', $span->id) }}(data.connections || [], data.span);
        })
        .catch(error => {
            console.error('Error loading timeline data for span', spanId, ':', error);
            // Render timeline with just the span data if API fails
            renderCardTimeline_{{ str_replace('-', '_', $span->id) }}([], {
                id: '{{ $span->id }}',
                name: '{{ $span->name }}',
                type_id: '{{ $span->type_id }}',
                start_year: {{ $span->start_year ?? 'null' }},
                end_year: {{ $span->end_year ?? 'null' }}
            });
        });
}

function renderCardTimeline_{{ str_replace('-', '_', $span->id) }}(connections, span) {
    const spanId = '{{ $span->id }}';
    const container = document.getElementById(`card-timeline-${spanId}`);
    
    if (!container || !window.globalTimelineData) return;
    
    const width = container.clientWidth;
    const height = container.clientHeight;
    const margin = { left: 0, right: 2, top: 2, bottom: 2 }; // Remove left margin to start at edge

    // Clear container
    container.innerHTML = '';

    // Create SVG
    const svg = d3.select(container)
        .append('svg')
        .attr('width', width)
        .attr('height', height);

    // Use global time scale
    const xScale = d3.scaleLinear()
        .domain([window.globalTimelineData.start, window.globalTimelineData.end])
        .range([margin.left, width - margin.right]);

    // Debug: Log positioning information for this card timeline
    console.log(`Card timeline ${spanId} debug:`);
    console.log('- Span start year:', span.start_year);
    console.log('- Global timeline start:', window.globalTimelineData.start);
    console.log('- Span start X position:', span.start_year ? xScale(span.start_year) : 'N/A');
    console.log('- Scale domain:', [window.globalTimelineData.start, window.globalTimelineData.end]);
    console.log('- Scale range:', [margin.left, width - margin.right]);
    console.log('- Container width:', width);

    // Draw timeline background (subtle)
    svg.append('rect')
        .attr('x', margin.left)
        .attr('y', margin.top)
        .attr('width', width - margin.left - margin.right)
        .attr('height', height - margin.top - margin.bottom)
        .attr('fill', '#f8f9fa')
        .attr('stroke', '#dee2e6')
        .attr('stroke-width', 1)
        .attr('rx', 4)
        .attr('ry', 4)
        .style('opacity', 0.3);

    // Draw span timeline bar if it has temporal data (as background)
    if (span.start_year) {
        const startYear = span.start_year;
        const endYear = span.end_year || new Date().getFullYear();
        
        svg.append('rect')
            .attr('class', 'span-timeline-background')
            .attr('x', xScale(startYear))
            .attr('y', margin.top + 2)
            .attr('width', Math.max(2, xScale(endYear) - xScale(startYear))) // Minimum 2px width
            .attr('height', height - margin.top - margin.bottom - 4)
            .attr('fill', getSpanTypeColor(span.type_id))
            .attr('stroke', 'white')
            .attr('stroke-width', 1)
            .attr('rx', 2)
            .attr('ry', 2)
            .style('opacity', connections.length > 0 ? 0.2 : 0.4); // More transparent when there are connections
    }

    // Draw connection timeline bars (stripes)
    svg.selectAll('.connection-timeline')
        .data(connections)
        .enter()
        .append('rect')
        .attr('class', 'connection-timeline')
        .attr('x', d => xScale(d.start_year))
        .attr('y', margin.top + 2)
        .attr('width', d => {
            const endYear = d.end_year || new Date().getFullYear();
            return Math.max(1, xScale(endYear) - xScale(d.start_year)); // Minimum 1px width
        })
        .attr('height', height - margin.top - margin.bottom - 4)
        .attr('fill', d => getConnectionColor(d.type_id))
        .attr('stroke', 'white')
        .attr('stroke-width', 0.5)
        .attr('rx', 1)
        .attr('ry', 1)
        .style('opacity', 0.6);

    // Draw current year indicator (matching global timescale)
    const currentYear = new Date().getFullYear();
    if (currentYear >= window.globalTimelineData.start && currentYear <= window.globalTimelineData.end) {
        svg.append('line')
            .attr('class', 'current-year-indicator')
            .attr('x1', xScale(currentYear))
            .attr('x2', xScale(currentYear))
            .attr('y1', margin.top)
            .attr('y2', height - margin.bottom)
            .attr('stroke', '#dc3545')
            .attr('stroke-width', 1)
            .style('opacity', 0.6);
    }
}

function getSpanTypeColor(typeId) {
    const colors = {
        'person': '#3B82F6',
        'organisation': '#10B981',
        'thing': '#8B5CF6',
        'band': '#F59E0B',
        'role': '#EF4444',
        'connection': '#6B7280'
    };
    return colors[typeId] || '#6B7280';
}

function getConnectionColor(typeId) {
    // Try to get color from CSS custom property first
    const cssColor = getComputedStyle(document.documentElement)
        .getPropertyValue(`--connection-${typeId}-color`);
    
    if (cssColor && cssColor.trim() !== '') {
        return cssColor.trim();
    }
    
    // Fallback colors
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
        'life': '#000000'
    };
    
    return fallbackColors[typeId] || '#6c757d';
}
</script>
@endpush 