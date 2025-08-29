@extends('layouts.app')

@section('title', 'Timeline Viewer - Lifespan')

@section('page_title')
    Timeline Viewer (<i>very</i> experimental...)
@endsection

@section('page_filters')
    <!-- Timeline-specific filters can be added here -->
@endsection

@section('page_tools')
    <div class="btn-group" role="group">
        <button type="button" class="btn btn-outline-secondary btn-sm" id="zoomIn">
            <i class="bi bi-zoom-in"></i> Zoom In
        </button>
        <button type="button" class="btn btn-outline-secondary btn-sm" id="zoomOut">
            <i class="bi bi-zoom-out"></i> Zoom Out
        </button>
        <button type="button" class="btn btn-outline-secondary btn-sm" id="resetView">
            <i class="bi bi-arrow-clockwise"></i> Reset
        </button>
    </div>
@endsection

@section('content')
<div class="container-fluid">
    <!-- Viewport Info -->
    <div class="mb-3">
        <small class="text-muted">
            Viewport: <span id="viewportInfo">Loading...</span>
        </small>
    </div>

    <!-- Timeline Container -->
    <div class="card">
        <div class="card-body p-0" style="height: auto;">
            <div id="timelineContainer" style="min-height: 600px; position: relative;">
                <!-- Timeline will be rendered here -->
            </div>
        </div>
    </div>

    <!-- Span Details Modal -->
    <div class="modal fade" id="spanDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="spanModalTitle">Span Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="spanModalBody">
                    <!-- Span details will be loaded here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <a href="#" class="btn btn-primary" id="spanModalLink">View Full Details</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
.timeline-container {
    position: relative;
    width: 100%;
    overflow: hidden;
}

.timeline-ruler {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 40px;
    background: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
    z-index: 10;
}

.timeline-ruler-canvas {
    width: 100%;
    height: 100%;
}

.timeline-content {
    position: relative;
    top: 40px;
    left: 0;
    right: 0;
    overflow: visible;
    height: auto;
}

.timeline-swimlane {
    position: relative;
    height: 30px;
    border-bottom: 1px solid #e9ecef;
    background: #fff;
    transition: background-color 0.2s;
}

.timeline-swimlane:hover {
    background: #f8f9fa;
}

.timeline-swimlane-header {
    position: absolute;
    left: 0;
    top: 0;
    width: 200px;
    height: 100%;
    background: #f8f9fa;
    border-right: 1px solid #dee2e6;
    padding: 8px 12px;
    display: flex;
    align-items: center;
    z-index: 5;
}

.timeline-swimlane-content {
    position: absolute;
    left: 0;
    top: 0;
    right: 0;
    height: 100%;
}

.timeline-span {
    position: absolute;
    height: 20px;
    top: 5px;
    border-radius: 3px;
    cursor: pointer;
    transition: all 0.2s;
    border: 1px solid rgba(0,0,0,0.1);
    display: flex;
    align-items: center;
    padding: 0 6px;
    font-size: 10px;
    font-weight: 500;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.timeline-span.span-offscreen {
    background: rgba(255, 255, 255, 0.9) !important;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    z-index: 10;
}

.timeline-span:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
    z-index: 10;
}

.timeline-span.ongoing {
    border-right: 3px solid #dc3545;
}

.timeline-span.person { background: #e3f2fd; color: #1976d2; }
.timeline-span.organisation { background: #f3e5f5; color: #7b1fa2; }
.timeline-span.place { background: #e8f5e8; color: #388e3c; }
.timeline-span.event { background: #fff3e0; color: #f57c00; }
.timeline-span.band { background: #fce4ec; color: #c2185b; }
.timeline-span.thing { background: #f1f8e9; color: #689f38; }



.timeline-loading {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    text-align: center;
    color: #6c757d;
}

.timeline-empty {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    text-align: center;
    color: #6c757d;
}

.zoom-controls {
    position: absolute;
    top: 10px;
    right: 10px;
    z-index: 20;
}

.zoom-controls .btn {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    margin-bottom: 5px;
    display: flex;
    align-items: center;
    justify-content: center;
}
</style>
@endpush

@push('scripts')
<script>
$(document).ready(function() {
    // Initial viewport from server
    const initialViewport = @json($initialViewport);
    
    // Timeline state
    let timelineState = {
        viewport: { ...initialViewport },
        zoomLevel: 1,
        panOffset: 0,
        // Buffered working set across a wider range than viewport
        workingSet: [], // array of spans (deduped by id)
        buffer: null,   // { start_year, end_year }
        laneMap: {},    // spanId -> laneIndex (stable)
        loading: false,
        isPanning: false,
        lastViewportSize: initialViewport.end_year - initialViewport.start_year, // Track viewport size changes
        lastViewportStart: initialViewport.start_year, // Track viewport start position
        lastViewportEnd: initialViewport.end_year // Track viewport end position
    };

    // Global constraints
    const MIN_YEAR = 1000;
    const MAX_YEAR = 2200;

    // Timeline dimensions
    const timelineConfig = {
        swimlaneHeight: 30,
        headerWidth: 0,
        rulerHeight: 40,
        minZoom: 0.1,
        maxZoom: 10,
        zoomStep: 1.5,
        maxLanes: 50,
        bufferYears: 5,           // fetch this many years beyond viewport on each side
        safeZoneFraction: 0.25     // when viewport gets within this fraction of buffer edge, refetch
    };

    // Initialize timeline
    initTimeline();
    fetchViewport();

    // Event listeners
    $('#zoomIn').click(() => zoom(1));
    $('#zoomOut').click(() => zoom(-1));
    $('#resetView').click(resetView);

    function initTimeline() {
        const container = $('#timelineContainer');
        container.html(`
            <div class="timeline-container">
                <div class="timeline-ruler">
                    <svg id="timelineRulerSvg" class="w-100 h-100"></svg>
                </div>
                <div class="timeline-content" id="timelineContent">
                    <div class="timeline-loading">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <div class="mt-2">Loading timeline...</div>
                    </div>
                </div>
                <div class="zoom-controls">
                    <button class="btn btn-light btn-sm" onclick="zoom(1)" title="Zoom In">
                        <i class="bi bi-zoom-in"></i>
                    </button>
                    <button class="btn btn-light btn-sm" onclick="zoom(-1)" title="Zoom Out">
                        <i class="bi bi-zoom-out"></i>
                    </button>
                </div>
            </div>
        `);

        // Add pan functionality
        let isPanning = false;
        let panStartX = 0;
        let panBaseViewport = null;
        let didPan = false;

        $('#timelineContainer').on('mousedown', function(e) {
            // Start panning anywhere inside the timeline container
            isPanning = true;
            timelineState.isPanning = true;
            didPan = false;
            panStartX = e.clientX;
            $('#timelineContent').css('cursor', 'grabbing');
            panBaseViewport = { ...timelineState.viewport };
            e.preventDefault();
        });

        $(document).on('mousemove', function(e) {
            if (isPanning) {
                const deltaXTotal = e.clientX - panStartX;
                if (Math.abs(deltaXTotal) > 2) didPan = true;
                // Convert pixels to days based on current viewport scale (relative to base viewport)
                const contentEl = $('#timelineContent');
                const viewportStartDate = new Date(panBaseViewport.start_year, 0, 1);
                const viewportEndDate = new Date(panBaseViewport.end_year, 11, 31);
                const totalDays = (viewportEndDate - viewportStartDate) / (1000 * 60 * 60 * 24);
                const containerWidth = Math.max(1, contentEl.width() - timelineConfig.headerWidth);
                const pixelsPerDay = containerWidth / totalDays;

                const deltaDays = Math.round(deltaXTotal / Math.max(0.0001, pixelsPerDay));

                // Preview-pan: shift viewport by deltaDays (relative to base)
                const newStart = new Date(viewportStartDate);
                newStart.setDate(newStart.getDate() - deltaDays);
                const newEnd = new Date(viewportEndDate);
                newEnd.setDate(newEnd.getDate() - deltaDays);

                const clamped = clampViewportYears(newStart.getFullYear(), newEnd.getFullYear());
                timelineState.viewport.start_year = clamped.start_year;
                timelineState.viewport.end_year = clamped.end_year;
                // Always reassign lanes when viewport changes for consistency
                assignLanesForWorkingSet();
                renderTimeline();
                e.preventDefault();
            }
        });

        $(document).on('mouseup', function() {
            if (isPanning) {
                isPanning = false;
                timelineState.isPanning = false;
                $('#timelineContent').css('cursor', 'grab');
                panBaseViewport = null;
                // Suppress click after a drag
                window._timelineSuppressClick = didPan;
                setTimeout(() => { window._timelineSuppressClick = false; }, 0);
                // After pan ends, only fetch if buffer is low; otherwise just re-render with lane reassignment
                if (needsBufferRefill()) {
                    fetchViewport();
                } else {
                    // Always reassign lanes when viewport changes for consistency
                    assignLanesForWorkingSet();
                    renderTimeline();
                }
            }
        });

        $('#timelineContent').css('cursor', 'grab');
    }

    function fetchViewport() {
        const vpStart = Math.max(MIN_YEAR, timelineState.viewport.start_year);
        const vpEnd = Math.min(MAX_YEAR, timelineState.viewport.end_year);
        // Expand to buffered window for stability
        const start = Math.max(MIN_YEAR, vpStart - timelineConfig.bufferYears);
        const end = Math.min(MAX_YEAR, vpEnd + timelineConfig.bufferYears);
        timelineState.loading = true;
        updateViewportInfo();
        const limit = Math.max(50, Math.min(500, timelineConfig.maxLanes * 10)); // fetch more to fill buffer
        console.log('Sending request with limit:', limit, 'for buffer:', start, '-', end);
        $.get('/viewer/spans', { 
            start_year: start, 
            end_year: end, 
            limit, 
            balanced: false
        })
            .done(function(response) {
                const incoming = response.spans || [];
                console.log(`API returned ${incoming.length} spans for buffer ${start}-${end}`);
                console.log('Incoming spans:', incoming.map(s => `${s.name} (${s.start_year}-${s.end_year || 'ongoing'})`));
                
                // Debug: Check for spans that start in our target range (1970-2030)
                const targetSpans = incoming.filter(s => s.start_year >= 1970 && s.start_year <= 2030);
                const ongoingSpans = incoming.filter(s => !s.end_year);
                const historicalSpans = incoming.filter(s => s.start_year < 1970);
                
                console.log('=== SPAN ANALYSIS ===');
                console.log(`Target range (1970-2030): ${targetSpans.length} spans`);
                console.log(`Ongoing spans: ${ongoingSpans.length} spans`);
                console.log(`Historical spans (<1970): ${historicalSpans.length} spans`);
                
                if (targetSpans.length > 0) {
                    console.log('Target spans (1970-2030):', targetSpans.map(s => `${s.name} (${s.start_year}-${s.end_year || 'ongoing'})`));
                }
                if (ongoingSpans.length > 0) {
                    console.log('Ongoing spans:', ongoingSpans.map(s => `${s.name} (${s.start_year}-ongoing)`));
                }
                console.log('=== END ANALYSIS ===');
                
                // Keep existing spans that still intersect the new buffer
                const nextWorking = (timelineState.workingSet || []).filter(s => {
                    const sY = s.start_year;
                    const eY = s.end_year ?? 9999;
                    return eY >= start && sY <= end;
                });
                console.log(`Keeping ${nextWorking.length} existing spans in buffer`);
                
                // Merge by stable id
                const byId = new Map();
                nextWorking.forEach(s => byId.set(s.id, s));
                incoming.forEach(s => byId.set(s.id, s));
                timelineState.workingSet = Array.from(byId.values());
                timelineState.buffer = { start_year: start, end_year: end };
                
                console.log(`Working set now has ${timelineState.workingSet.length} spans`);
                
                // Re-assign lanes for all spans to ensure optimal distribution
                assignLanesForWorkingSet();
                timelineState.loading = false;
                renderTimeline();
            })
            .fail(function(xhr) {
                console.error('Failed to load spans:', xhr);
                timelineState.loading = false;
                showError('Failed to load timeline data');
            });
    }

    function needsBufferRefill() {
        const buf = timelineState.buffer;
        if (!buf) return true;
        
        // Check if viewport exceeds buffer boundaries
        const exceeds = timelineState.viewport.start_year < buf.start_year || timelineState.viewport.end_year > buf.end_year;
        if (exceeds) return true;
        
        // Check if we're near buffer edges
        const bufSize = Math.max(1, buf.end_year - buf.start_year);
        const leftGap = Math.max(0, timelineState.viewport.start_year - buf.start_year);
        const rightGap = Math.max(0, buf.end_year - timelineState.viewport.end_year);
        const nearLeft = leftGap / bufSize < timelineConfig.safeZoneFraction;
        const nearRight = rightGap / bufSize < timelineConfig.safeZoneFraction;
        
        // Check if viewport has changed significantly (for zoom/pan operations)
        const viewportSize = timelineState.viewport.end_year - timelineState.viewport.start_year;
        const viewportSizeChanged = timelineState.lastViewportSize !== viewportSize;
        
        // Check if viewport position has changed significantly (for pan operations)
        const viewportStartChanged = timelineState.lastViewportStart !== timelineState.viewport.start_year;
        const viewportEndChanged = timelineState.lastViewportEnd !== timelineState.viewport.end_year;
        
        // Store current viewport state for next comparison
        timelineState.lastViewportSize = viewportSize;
        timelineState.lastViewportStart = timelineState.viewport.start_year;
        timelineState.lastViewportEnd = timelineState.viewport.end_year;
        
        // Always refill when viewport changes (either size or position)
        return exceeds || nearLeft || nearRight || viewportSizeChanged || viewportStartChanged || viewportEndChanged;
    }

    function assignLanesForWorkingSet(newIds) {
        const laneEndByIndex = [];
        
        // If no newIds provided, we're re-assigning all spans
        const reassignAll = !newIds || newIds.length === 0;
        
        if (!reassignAll) {
            // Seed from existing assignments for new spans only
            Object.entries(timelineState.laneMap).forEach(([id, laneIdx]) => {
                const span = timelineState.workingSet.find(s => s.id === id);
                if (!span) return;
                const end = span.is_ongoing ? new Date() : new Date(span.end_year, (span.end_month || 12) - 1, span.end_day || 31);
                laneEndByIndex[laneIdx] = laneEndByIndex[laneIdx] && laneEndByIndex[laneIdx] > end ? laneEndByIndex[laneIdx] : end;
            });
        }
        
        // Get spans that need lane assignment
        const toAssign = reassignAll ? 
            timelineState.workingSet.sort((a, b) => {
                // Prioritize spans that start within the viewport
                const viewportStart = timelineState.viewport.start_year;
                const viewportEnd = timelineState.viewport.end_year;
                
                // Check if span starts within viewport
                const aStartsInViewport = a.start_year >= viewportStart && a.start_year <= viewportEnd;
                const bStartsInViewport = b.start_year >= viewportStart && b.start_year <= viewportEnd;
                
                // First priority: spans that start within viewport
                if (aStartsInViewport && !bStartsInViewport) return -1;
                if (!aStartsInViewport && bStartsInViewport) return 1;
                
                // Second priority: among spans that start in viewport, sort by start year
                if (aStartsInViewport && bStartsInViewport) {
                    return a.start_year - b.start_year;
                }
                
                // Third priority: among spans outside viewport, prefer those closer to viewport
                const aDistance = Math.min(Math.abs(a.start_year - viewportStart), Math.abs(a.start_year - viewportEnd));
                const bDistance = Math.min(Math.abs(b.start_year - viewportStart), Math.abs(b.start_year - viewportEnd));
                return aDistance - bDistance;
            }) :
            timelineState.workingSet
                .filter(s => !timelineState.laneMap[s.id] || (newIds && newIds.includes(s.id)))
                .sort((a, b) => {
                    // Prioritize spans that start within the viewport
                    const viewportStart = timelineState.viewport.start_year;
                    const viewportEnd = timelineState.viewport.end_year;
                    
                    // Check if span starts within viewport
                    const aStartsInViewport = a.start_year >= viewportStart && a.start_year <= viewportEnd;
                    const bStartsInViewport = b.start_year >= viewportStart && b.start_year <= viewportEnd;
                    
                    // First priority: spans that start within viewport
                    if (aStartsInViewport && !bStartsInViewport) return -1;
                    if (!aStartsInViewport && bStartsInViewport) return 1;
                    
                    // Second priority: among spans that start in viewport, sort by start year
                    if (aStartsInViewport && bStartsInViewport) {
                        return a.start_year - b.start_year;
                    }
                    
                    // Third priority: among spans outside viewport, prefer those closer to viewport
                    const aDistance = Math.min(Math.abs(a.start_year - viewportStart), Math.abs(a.start_year - viewportEnd));
                    const bDistance = Math.min(Math.abs(b.start_year - viewportStart), Math.abs(b.start_year - viewportEnd));
                    return aDistance - bDistance;
                });
        
        console.log('Assigning lanes for', toAssign.length, 'spans');
        
        // Clear existing lane assignments if re-assigning all
        if (reassignAll) {
            timelineState.laneMap = {};
        }
        
        let assignedCount = 0;
        let skippedCount = 0;
        
        toAssign.forEach(span => {
            const s = new Date(span.start_year, (span.start_month || 1) - 1, span.start_day || 1);
            const e = span.is_ongoing ? new Date() : new Date(span.end_year, (span.end_month || 12) - 1, span.end_day || 31);
            
            // Find the first lane where this span doesn't overlap
            let laneIdx = 0;
            let foundLane = false;
            
            // Try to find a lane without overlap
            for (; laneIdx < timelineConfig.maxLanes; laneIdx++) {
                const lastEnd = laneEndByIndex[laneIdx];
                if (!lastEnd || s >= lastEnd) {
                    foundLane = true;
                    break;
                }
            }
            
            // If we found a non-overlapping lane, assign the span
            if (foundLane) {
                timelineState.laneMap[span.id] = laneIdx;
                laneEndByIndex[laneIdx] = e;
                assignedCount++;
                console.log(`Assigned ${span.name} (${span.start_year}-${span.end_year || 'ongoing'}) to lane ${laneIdx + 1}`);
            } else {
                // Skip this span - no available lanes without overlap
                skippedCount++;
                console.log(`Skipped ${span.name} (${span.start_year}-${span.end_year || 'ongoing'}) - no available lanes`);
            }
        });
        
        console.log(`Lane assignment complete: ${assignedCount} assigned, ${skippedCount} skipped`);
    }

    function renderTimeline() {
        const content = $('#timelineContent');
        
        if (timelineState.loading) {
            content.html(`
                <div class="timeline-loading">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <div class="mt-2">Loading timeline...</div>
                </div>
            `);
            return;
        }

        // Filter visible spans from working set
        let visibleSpans = timelineState.workingSet.filter(s => {
            if (!s.start_year) return false;
            const sY = s.start_year;
            const eY = s.end_year ?? 9999;
            const intersects = eY >= timelineState.viewport.start_year && sY <= timelineState.viewport.end_year;
            if (!intersects) {
                console.log(`Filtered out ${s.name} (${sY}-${eY}) - outside viewport ${timelineState.viewport.start_year}-${timelineState.viewport.end_year}`);
            }
            return intersects;
        });
        
        console.log(`Working set: ${timelineState.workingSet.length} spans, Visible: ${visibleSpans.length} spans`);

        if (visibleSpans.length === 0) {
            content.html(`
                <div class="timeline-empty">
                    <i class="bi bi-calendar-x fs-1"></i>
                    <div class="mt-2">No spans found in this viewport</div>
                    <div class="mt-1">Try zooming out or adjusting the date range</div>
                </div>
            `);
            return;
        }

        // Calculate timeline dimensions
        const viewportStart = new Date(timelineState.viewport.start_year, 0, 1);
        const viewportEnd = new Date(timelineState.viewport.end_year, 11, 31);
        const totalDays = (viewportEnd - viewportStart) / (1000 * 60 * 60 * 24);
        const pixelsPerDay = (content.width() - timelineConfig.headerWidth) / totalDays;

        // Ensure stable lane assignments and deterministic ordering
        assignLanesForWorkingSet();
        visibleSpans.sort((a, b) => {
            const laneA = timelineState.laneMap[a.id] ?? 0;
            const laneB = timelineState.laneMap[b.id] ?? 0;
            if (laneA !== laneB) return laneA - laneB;
            const aStart = new Date(a.start_year, (a.start_month || 1) - 1, a.start_day || 1).getTime();
            const bStart = new Date(b.start_year, (b.start_month || 1) - 1, b.start_day || 1).getTime();
            if (aStart !== bStart) return aStart - bStart;
            if (a.id < b.id) return -1;
            if (a.id > b.id) return 1;
            return 0;
        });
        // Build lanes - only include spans that have been assigned to lanes
        const lanes = Array.from({ length: timelineConfig.maxLanes }, () => []);
        for (const span of visibleSpans) {
            const laneIdx = timelineState.laneMap[span.id];
            // Only include spans that have been assigned to a lane (not undefined)
            if (laneIdx !== undefined && laneIdx < timelineConfig.maxLanes) {
                lanes[laneIdx].push(span);
            }
        }

        // Render lanes
        let swimlaneHtml = '';
        lanes.forEach((laneSpans, idx) => {
            swimlaneHtml += `
                <div class="timeline-swimlane">
                    <div class="timeline-swimlane-content">
                        ${laneSpans.map(span => renderSpan(span, viewportStart, pixelsPerDay)).join('')}
                    </div>
                </div>
            `;
        });

        content.html(swimlaneHtml);
        renderRuler(viewportStart, viewportEnd, pixelsPerDay);
        // Debug: log spans and their positions
        if (!timelineState.isPanning) {
            try {
                const debugItems = visibleSpans.map(s => {
                    const sDate = new Date(s.start_year, (s.start_month || 1) - 1, s.start_day || 1);
                    const eDate = s.end_year ? new Date(s.end_year, (s.end_month || 12) - 1, s.end_day || 31) : new Date();
                    const left = ((sDate - viewportStart) / (1000 * 60 * 60 * 24)) * pixelsPerDay;
                    const width = ((eDate - sDate) / (1000 * 60 * 60 * 24)) * pixelsPerDay;
                    return {
                        id: s.id,
                        name: s.name,
                        lane: timelineState.laneMap[s.id] ?? 0,
                        start: `${s.start_year}-${s.start_month || 1}-${s.start_day || 1}`,
                        end: s.end_year ? `${s.end_year}-${s.end_month || 12}-${s.end_day || 31}` : 'present',
                        left: Math.round(left),
                        width: Math.max(1, Math.round(width))
                    };
                });
                console.log('Viewport', timelineState.viewport, 'Visible spans:', debugItems);
                
                // Enhanced debug: show spans per swimlane
                console.log('=== SWIMLANE DISTRIBUTION ===');
                lanes.forEach((laneSpans, laneIdx) => {
                    if (laneSpans.length > 0) {
                        console.log(`Lane ${laneIdx + 1} (${laneSpans.length} spans):`);
                        laneSpans.forEach(span => {
                            const startYear = span.start_year;
                            const endYear = span.end_year || 'ongoing';
                            console.log(`  - ${span.name} (${startYear}-${endYear}) [ID: ${span.id}]`);
                        });
                    }
                });
                
                // Show date range coverage
                const allYears = visibleSpans.flatMap(s => {
                    const years = [];
                    for (let y = s.start_year; y <= (s.end_year || new Date().getFullYear()); y++) {
                        years.push(y);
                    }
                    return years;
                });
                const uniqueYears = [...new Set(allYears)].sort((a, b) => a - b);
                console.log('Date range coverage:', {
                    viewport: `${timelineState.viewport.start_year}-${timelineState.viewport.end_year}`,
                    buffer: timelineState.buffer ? `${timelineState.buffer.start_year}-${timelineState.buffer.end_year}` : 'none',
                    spansWithYears: uniqueYears.length,
                    yearRange: uniqueYears.length > 0 ? `${uniqueYears[0]}-${uniqueYears[uniqueYears.length-1]}` : 'none',
                    totalSpans: visibleSpans.length,
                    workingSetSize: timelineState.workingSet.length
                });
            } catch (e) {
                console.error('Debug logging error:', e);
            }
        }
    }

    function renderSpan(span, viewportStart, pixelsPerDay) {
        const spanStart = new Date(span.start_year, (span.start_month || 1) - 1, span.start_day || 1);
        const spanEnd = span.is_ongoing ? 
            new Date() : 
            new Date(span.end_year, (span.end_month || 12) - 1, span.end_day || 31);
        
        const left = ((spanStart - viewportStart) / (1000 * 60 * 60 * 24)) * pixelsPerDay;
        const width = ((spanEnd - spanStart) / (1000 * 60 * 60 * 24)) * pixelsPerDay;
        
        const displayName = span.name.length > 20 ? span.name.substring(0, 17) + '...' : span.name;
        
        // If span starts offscreen to the left, adjust position to show label
        const spanStartsOffscreen = left < 0;
        const adjustedLeft = spanStartsOffscreen ? 0 : left;
        const adjustedWidth = spanStartsOffscreen ? Math.max(width + left, 20) : Math.max(width, 20);
        const labelClass = spanStartsOffscreen ? 'span-offscreen' : '';
        
        return `
            <div class="timeline-span ${span.type_id} ${span.is_ongoing ? 'ongoing' : ''} ${labelClass}" 
                 style="left: ${adjustedLeft}px; width: ${adjustedWidth}px;"
                 data-span-id="${span.id}"
                 title="${span.name} (${formatDate(spanStart)} - ${span.is_ongoing ? 'Present' : formatDate(spanEnd)})">
                ${displayName}
            </div>
        `;
    }

    function renderRuler(viewportStart, viewportEnd, pixelsPerDay) {
        const svg = document.getElementById('timelineRulerSvg');
        const width = svg.clientWidth || svg.parentElement.clientWidth;
        const height = svg.clientHeight || 40;
        svg.setAttribute('width', width);
        svg.setAttribute('height', height);

        // Clear existing
        while (svg.firstChild) svg.removeChild(svg.firstChild);

        // Compute tick step
        const startYear = viewportStart.getFullYear();
        const endYear = viewportEnd.getFullYear();
        const yearRange = Math.max(1, endYear - startYear + 1);
        // Use same horizontal origin as swimlane content: offset by header width
        const xOffset = timelineConfig.headerWidth;
        const contentWidth = Math.max(1, width - xOffset);
        const pixelsPerYear = contentWidth / yearRange;
        const minPixelsPerTick = 60;
        let tickStep = pixelsPerYear < minPixelsPerTick ? 10 : 1;
        const firstTickYear = Math.ceil(startYear / tickStep) * tickStep;

        // Axis baseline
        const baseline = document.createElementNS('http://www.w3.org/2000/svg', 'line');
        baseline.setAttribute('x1', String(xOffset));
        baseline.setAttribute('y1', String(height - 10));
        baseline.setAttribute('x2', String(width));
        baseline.setAttribute('y2', String(height - 10));
        baseline.setAttribute('stroke', '#dee2e6');
        svg.appendChild(baseline);

        for (let year = firstTickYear; year <= endYear; year += tickStep) {
            const yearStart = new Date(year, 0, 1);
            const x = xOffset + ((yearStart - viewportStart) / (1000 * 60 * 60 * 24)) * pixelsPerDay;
            if (x < xOffset || x > width) continue;

            const tick = document.createElementNS('http://www.w3.org/2000/svg', 'line');
            tick.setAttribute('x1', String(x));
            tick.setAttribute('y1', String(height - 15));
            tick.setAttribute('x2', String(x));
            tick.setAttribute('y2', String(height - 2));
            tick.setAttribute('stroke', '#adb5bd');
            svg.appendChild(tick);

            const label = document.createElementNS('http://www.w3.org/2000/svg', 'text');
            label.setAttribute('x', String(x));
            label.setAttribute('y', String(height - 20));
            label.setAttribute('text-anchor', 'middle');
            label.setAttribute('fill', '#495057');
            label.setAttribute('font-size', '12');
            label.textContent = `${year}`;
            svg.appendChild(label);
        }
    }

    function zoom(direction) {
        const oldZoom = timelineState.zoomLevel;
        const zoomFactor = direction > 0 ? timelineConfig.zoomStep : 1 / timelineConfig.zoomStep;
        
        timelineState.zoomLevel = Math.max(
            timelineConfig.minZoom,
            Math.min(timelineConfig.maxZoom, timelineState.zoomLevel * zoomFactor)
        );
        
        // Adjust viewport based on zoom
        const zoomRatio = timelineState.zoomLevel / oldZoom;
        const viewportRange = timelineState.viewport.end_year - timelineState.viewport.start_year;
        const newRange = viewportRange / zoomRatio;
        const centerYear = (timelineState.viewport.start_year + timelineState.viewport.end_year) / 2;
        
        const unclampedStart = Math.floor(centerYear - newRange / 2);
        const unclampedEnd = Math.ceil(centerYear + newRange / 2);
        const clamped = clampViewportYears(unclampedStart, unclampedEnd);
        timelineState.viewport.start_year = clamped.start_year;
        timelineState.viewport.end_year = clamped.end_year;
        
        if (needsBufferRefill()) {
            fetchViewport();
        } else {
            // Always reassign lanes when viewport changes for consistency
            assignLanesForWorkingSet();
            renderTimeline();
        }
    }

    function resetView() {
        timelineState.viewport = { ...initialViewport };
        timelineState.zoomLevel = 1;
        timelineState.panOffset = 0;
        fetchViewport();
    }

    function updateViewportInfo() {
        const info = `${timelineState.viewport.start_year} - ${timelineState.viewport.end_year}`;
        $('#viewportInfo').text(info);
    }

    function getTypeIcon(typeId) {
        const icons = {
            person: 'person-fill',
            organisation: 'building',
            place: 'geo-alt-fill',
            event: 'calendar-event-fill',
            band: 'cassette',
            thing: 'box'
        };
        return icons[typeId] || 'question-circle';
    }

    function formatDate(date) {
        return date.toLocaleDateString('en-GB', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
    }

    function showError(message) {
        $('#timelineContent').html(`
            <div class="timeline-empty">
                <i class="bi bi-exclamation-triangle fs-1 text-warning"></i>
                <div class="mt-2">${message}</div>
            </div>
        `);
    }

    // Span click handler
    $(document).on('click', '.timeline-span', function(e) {
        if (window._timelineSuppressClick) {
            e.preventDefault();
            return;
        }
        const spanId = $(this).data('span-id');
        const span = (timelineState.workingSet || []).find(s => s.id === spanId);
        
        if (span) {
            showSpanDetails(span);
        }
    });

    function showSpanDetails(span) {
        $('#spanModalTitle').text(span.name);
        $('#spanModalLink').attr('href', `/spans/${span.id}`);
        
        const startDate = formatDate(new Date(span.start_year, (span.start_month || 1) - 1, span.start_day || 1));
        const endDate = span.is_ongoing ? 'Present' : formatDate(new Date(span.end_year, (span.end_month || 12) - 1, span.end_day || 31));
        
        $('#spanModalBody').html(`
            <dl class="row">
                <dt class="col-sm-3">Type</dt>
                <dd class="col-sm-9">
                    <span class="badge bg-${span.type_id}">${span.type_name}</span>
                </dd>
                
                <dt class="col-sm-3">Period</dt>
                <dd class="col-sm-9">${startDate} - ${endDate}</dd>
                
                ${span.description ? `
                <dt class="col-sm-3">Description</dt>
                <dd class="col-sm-9">${span.description}</dd>
                ` : ''}
                
                <dt class="col-sm-3">Access</dt>
                <dd class="col-sm-9">
                    <span class="badge bg-${span.access_level === 'public' ? 'success' : span.access_level === 'shared' ? 'info' : 'secondary'}">
                        ${span.access_level}
                    </span>
                </dd>
            </dl>
        `);
        
        new bootstrap.Modal(document.getElementById('spanDetailsModal')).show();
    }

    // Make functions globally available for button clicks
    window.zoom = zoom;

    function clampViewportYears(startYear, endYear) {
        let s = Math.max(MIN_YEAR, startYear);
        let e = Math.min(MAX_YEAR, endYear);
        if (e <= s) {
            // ensure at least 1 year span
            e = Math.min(MAX_YEAR, s + 1);
        }
        return { start_year: s, end_year: e };
    }
});
</script>
@endpush
