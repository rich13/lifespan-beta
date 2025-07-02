// TimelineManager class for coordinating timeline visualizations

class TimelineManager {
    constructor() {
        this.sections = new Map();
        this.globalTimelineData = null;
        this.init();
    }
    
    init() {
        // Make TimelineManager globally available
        window.TimelineManager = this;
        
        // Dispatch ready event
        document.dispatchEvent(new Event('timelineManagerReady'));
    }
    
    initializeSection(containerId, spans) {
        console.log(`Initializing timeline section: ${containerId} with ${spans.length} spans`);
        
        // Store section data
        this.sections.set(containerId, {
            spans: spans,
            globalTimelineData: null
        });
        
        // Calculate global bounds for this section
        this.calculateGlobalBounds(containerId);
        
        // Trigger event for card timelines to initialize first
        document.dispatchEvent(new Event('globalTimelineReady'));
        
        // Wait for card timelines to be rendered, then initialize global timescale with correct width
        setTimeout(() => {
            console.log(`About to initialize global timescale for ${containerId}`);
            this.initializeGlobalTimescale(containerId, spans);
        }, 200);
    }
    
    calculateGlobalBounds(containerId) {
        const section = this.sections.get(containerId);
        if (!section) return;
        
        const spans = section.spans;
        let globalStart = null;
        let globalEnd = null;
        
        // Find the earliest and latest years across all spans
        spans.forEach(span => {
            if (span.start_year) {
                if (globalStart === null || span.start_year < globalStart) {
                    globalStart = span.start_year;
                }
            }
            const endYear = span.end_year || new Date().getFullYear();
            if (globalEnd === null || endYear > globalEnd) {
                globalEnd = endYear;
            }
        });
        
        // Always include today's year as the latest date in the scale
        const thisYear = new Date().getFullYear();
        if (globalEnd === null || globalEnd < thisYear) {
            globalEnd = thisYear;
        }
        
        // Set default bounds if no spans have dates
        if (globalStart === null) {
            globalStart = 1900;
            globalEnd = new Date().getFullYear();
        }
        
        // Add some padding
        const padding = Math.max(5, Math.floor((globalEnd - globalStart) * 0.1));
        globalStart = globalStart - padding;
        globalEnd = globalEnd + padding;
        
        // Store global timeline data for this section
        section.globalTimelineData = {
            start: globalStart,
            end: globalEnd
        };
        
        // Also store globally for backward compatibility
        window.globalTimelineData = section.globalTimelineData;
        
        console.log(`Global timeline bounds for ${containerId}: ${globalStart} to ${globalEnd}`);
    }
    
    initializeGlobalTimescale(containerId, spans) {
        console.log(`initializeGlobalTimescale called for ${containerId}`);
        const section = this.sections.get(containerId);
        if (!section || !section.globalTimelineData) {
            console.log(`No section or globalTimelineData for ${containerId}`);
            return;
        }
        
        // Render global timescale with proper width measurement
        console.log(`About to render global timescale for ${containerId}`);
        this.renderGlobalTimescale(containerId);
    }
    
    renderGlobalTimescale(containerId) {
        const section = this.sections.get(containerId);
        if (!section || !section.globalTimelineData) return;

        // Wait for card timelines to be ready and measurable
        const cardTimelines = document.querySelectorAll(`[data-container-id='${containerId}'] [id^="card-timeline-"]`);
        console.log(`Looking for card timelines in container ${containerId}:`, cardTimelines.length, 'found');
        console.log('Card timeline elements:', cardTimelines);
        
        if (!cardTimelines.length) {
            setTimeout(() => this.renderGlobalTimescale(containerId), 50);
            return;
        }
        
        // Measure the actual container width that card timelines use
        // The card timelines are nested inside extra containers, so we need to measure
        // the same container structure that the global timescale has
        const globalContainer = document.getElementById(`global-timeline-${containerId}`);
        if (!globalContainer) {
            setTimeout(() => this.renderGlobalTimescale(containerId), 50);
            return;
        }
        
        // Get the width of the global timescale container (same structure as card timeline containers)
        const width = globalContainer.clientWidth;
        if (!width || width < 100) {
            setTimeout(() => this.renderGlobalTimescale(containerId), 50);
            return;
        }
        const height = 60; // Force taller height for better visibility
        const margin = { left: 0, right: 2, top: 8, bottom: 8 }; // Use same left/right margins as card timelines

        // Find the global timescale container
        const container = document.getElementById(`global-timeline-${containerId}`);
        if (!container) return;

        // Clear container
        container.innerHTML = '';
        
        // Debug: Log width information for alignment
        console.log(`Global timescale debug:`);
        console.log('Global timescale container width:', width);
        console.log('Scale range:', [margin.left, width - margin.right]);

        // Create SVG
        const svg = d3.select(container)
            .append('svg')
            .attr('width', width)
            .attr('height', height);

        // Create time scale
        const xScale = d3.scaleLinear()
            .domain([section.globalTimelineData.start, section.globalTimelineData.end])
            .range([margin.left, width - margin.right]);
            
        // Debug: Log current year positioning
        console.log('Current year:', new Date().getFullYear());
        console.log('Current year X position:', xScale(new Date().getFullYear()));

        // Generate all year ticks (lighter) and decade ticks (darker)
        const allYearTicks = [];
        const decadeTicks = [];
        const startYear = Math.floor(section.globalTimelineData.start);
        const endYear = Math.ceil(section.globalTimelineData.end);
        for (let year = startYear; year <= endYear; year++) {
            if (year >= section.globalTimelineData.start && year <= section.globalTimelineData.end) {
                allYearTicks.push(year);
                if (year % 10 === 0) {
                    decadeTicks.push(year);
                }
            }
        }

        // Add year ticks (lighter, smaller)
        svg.selectAll('.year-tick')
            .data(allYearTicks)
            .enter()
            .append('line')
            .attr('class', 'year-tick')
            .attr('x1', d => xScale(d))
            .attr('x2', d => xScale(d))
            .attr('y1', height - margin.bottom - 4)
            .attr('y2', height - margin.bottom)
            .attr('stroke', '#ccc')
            .attr('stroke-width', 0.5);

        // Add decade ticks (darker, longer)
        svg.selectAll('.decade-tick')
            .data(decadeTicks)
            .enter()
            .append('line')
            .attr('class', 'decade-tick')
            .attr('x1', d => xScale(d))
            .attr('x2', d => xScale(d))
            .attr('y1', height - margin.bottom - 16)
            .attr('y2', height - margin.bottom)
            .attr('stroke', '#666')
            .attr('stroke-width', 1);

        // Add decade year labels
        svg.selectAll('.tick-label')
            .data(decadeTicks)
            .enter()
            .append('text')
            .attr('class', 'tick-label')
            .attr('x', d => xScale(d))
            .attr('y', height - margin.bottom - 20)
            .attr('text-anchor', 'middle')
            .attr('font-size', '10px')
            .attr('fill', '#666')
            .text(d => d);

        // Draw current year indicator ("Now" marker)
        const currentYear = new Date().getFullYear();
        if (currentYear >= section.globalTimelineData.start && currentYear <= section.globalTimelineData.end) {
            svg.append('line')
                .attr('class', 'current-year-indicator')
                .attr('x1', xScale(currentYear))
                .attr('x2', xScale(currentYear))
                .attr('y1', margin.top)
                .attr('y2', height - margin.bottom)
                .attr('stroke', '#dc3545')
                .attr('stroke-width', 2)
                .style('opacity', 0.8);
            svg.append('text')
                .attr('class', 'current-year-label')
                .attr('x', xScale(currentYear))
                .attr('y', margin.top + 12)
                .attr('text-anchor', 'middle')
                .attr('font-size', '9px')
                .attr('fill', '#dc3545')
                .attr('font-weight', 'bold')
                .text('Now');
        }
    }
}

// Initialize TimelineManager when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    new TimelineManager();
}); 