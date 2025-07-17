@props(['span'])

@php
// Collect all family members
$ancestors = $span->ancestors(3);
$descendants = $span->descendants(2);
$siblings = $span->siblings();
$unclesAndAunts = $span->unclesAndAunts();
$cousins = $span->cousins();
$nephewsAndNieces = $span->nephewsAndNieces();
$extraNephewsAndNieces = $span->extraNephewsAndNieces();

// Combine all family members into a single collection
$allFamilyMembers = collect();

// Add current person first
$allFamilyMembers->push($span);

// Add all other family members
$allFamilyMembers = $allFamilyMembers
    ->concat($ancestors->pluck('span'))
    ->concat($descendants->pluck('span'))
    ->concat($siblings)
    ->concat($unclesAndAunts)
    ->concat($cousins)
    ->concat($nephewsAndNieces)
    ->concat($extraNephewsAndNieces)
    ->unique('id')
    ->filter(function($member) {
        // Only include people with birth dates for the timeline
        return $member->start_year !== null;
    })
    ->sortBy('start_year');

// Check if we have enough family members to show a meaningful timeline
$hasTimelineData = $allFamilyMembers->count() > 1;

// Precompute the array for JS
$familyMembersArray = $allFamilyMembers->map(function($member) use ($span) {
    return [
        'id' => $member->id,
        'name' => $member->name,
        'start_year' => $member->start_year,
        'end_year' => $member->end_year,
        'is_current' => $member->id === $span->id
    ];
})->values()->toArray();
@endphp

@if($hasTimelineData)
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0">
                <i class="bi bi-clock-history me-2"></i>
                Family Timeline
            </h5>
        </div>
        <div class="card-body">
            <div id="family-timeline-container" style="height: {{ max(200, count($familyMembersArray) * 30) }}px; width: 100%;">
                <!-- D3 family timeline will be rendered here -->
            </div>
        </div>
    </div>

    @push('scripts')
    <script src="https://d3js.org/d3.v7.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(() => {
            initializeFamilyTimeline();
        }, 100);
    });

    function initializeFamilyTimeline() {
        const container = document.getElementById('family-timeline-container');
        
        if (!container) {
            console.error('Family timeline container not found');
            return;
        }

        // Family members data from PHP
        const familyMembers = @json($familyMembersArray);

        renderFamilyTimeline(familyMembers);
    }

    function renderFamilyTimeline(familyMembers) {
        const container = document.getElementById('family-timeline-container');
        const width = container.clientWidth;
        const height = container.clientHeight;
        const margin = { top: 20, right: 20, bottom: 30, left: 120 }; // Extra left margin for names
        const swimlaneHeight = 25;
        const swimlaneSpacing = 5;

        // Clear container
        container.innerHTML = '';

        // Create SVG
        const svg = d3.select(container)
            .append('svg')
            .attr('width', width)
            .attr('height', height);

        // Calculate time range
        const startYear = Math.min(...familyMembers.map(d => d.start_year));
        const endYear = Math.max(...familyMembers.map(d => d.end_year || new Date().getFullYear()));
        
        // Create scales
        const xScale = d3.scaleLinear()
            .domain([startYear, endYear])
            .range([margin.left, width - margin.right]);

        // Create axis
        const xAxis = d3.axisBottom(xScale)
            .tickFormat(d3.format('d'))
            .ticks(10);

        svg.append('g')
            .attr('transform', `translate(0, ${height - margin.bottom})`)
            .call(xAxis);

        // Create swimlanes for each family member
        familyMembers.forEach((member, index) => {
            const swimlaneY = margin.top + index * (swimlaneHeight + swimlaneSpacing);
            
            // Draw swimlane background
            svg.append('rect')
                .attr('x', margin.left)
                .attr('y', swimlaneY)
                .attr('width', width - margin.left - margin.right)
                .attr('height', swimlaneHeight)
                .attr('fill', member.is_current ? '#e3f2fd' : '#f8f9fa')
                .attr('stroke', member.is_current ? '#2196f3' : '#dee2e6')
                .attr('stroke-width', member.is_current ? 2 : 1)
                .attr('rx', 4)
                .attr('ry', 4);

            // Add name label
            svg.append('text')
                .attr('x', margin.left - 10)
                .attr('y', swimlaneY + swimlaneHeight / 2)
                .attr('text-anchor', 'end')
                .attr('dominant-baseline', 'middle')
                .attr('font-size', '12px')
                .attr('font-weight', member.is_current ? 'bold' : 'normal')
                .attr('fill', member.is_current ? '#2196f3' : '#495057')
                .text(member.name);

            // Draw life span bar
            const lifeStartX = xScale(member.start_year);
            const lifeEndX = xScale(member.end_year || new Date().getFullYear());
            
            svg.append('rect')
                .attr('class', 'life-span')
                .attr('x', lifeStartX)
                .attr('y', swimlaneY + 2)
                .attr('width', lifeEndX - lifeStartX)
                .attr('height', swimlaneHeight - 4)
                .attr('fill', member.is_current ? '#2196f3' : '#6c757d')
                .attr('stroke', 'white')
                .attr('stroke-width', 1)
                .attr('rx', 2)
                .attr('ry', 2)
                .style('opacity', 0.8)
                .on('mouseover', function(event) {
                    d3.select(this).style('opacity', 1);
                    showFamilyMemberTooltip(event, member);
                })
                .on('mouseout', function() {
                    d3.select(this).style('opacity', 0.8);
                    hideTooltip();
                });
        });
    }

    function showFamilyMemberTooltip(event, member) {
        const tooltip = d3.select('body').append('div')
            .attr('class', 'tooltip')
            .style('position', 'absolute')
            .style('background', 'rgba(0, 0, 0, 0.8)')
            .style('color', 'white')
            .style('padding', '8px 12px')
            .style('border-radius', '4px')
            .style('font-size', '12px')
            .style('pointer-events', 'none')
            .style('z-index', '1000');

        const endYear = member.end_year || 'Present';
        const content = `
            <strong>${member.name}</strong><br/>
            ${member.start_year} - ${endYear}
        `;

        tooltip.html(content)
            .style('left', (event.pageX + 10) + 'px')
            .style('top', (event.pageY - 10) + 'px');
    }

    function hideTooltip() {
        d3.selectAll('.tooltip').remove();
    }
    </script>
    @endpush
@endif 