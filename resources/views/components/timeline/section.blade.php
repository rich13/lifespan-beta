@props(['spans', 'containerId' => 'default'])

<div class="timeline-visualization" data-container-id="{{ $containerId }}">
    <!-- Global timescale with same container structure as card timelines -->
    <div class="interactive-card-base mb-3 position-relative" style="min-height: 70px;">
        <!-- Timeline background -->
        <div class="position-absolute w-100 h-100" style="top: 0; left: 0; z-index: 1;">
            <div class="card-timeline-container" style="height: 100%; width: 100%;">
                <div id="global-timeline-{{ $containerId }}" style="height: 100%; width: 100%;">
                    <!-- D3 timeline will be rendered here -->
                </div>
            </div>
        </div>
        
        <!-- Content positioned on top of the timeline -->
        <div class="position-relative" style="z-index: 2;">
            <div class="card-body p-0">
                <!-- Timeline content will be rendered here -->
            </div>
        </div>
    </div>
    
    <!-- Spans list with timelines -->
    <div class="spans-list">
        @foreach($spans as $span)
            <x-spans.display.interactive-card :span="$span" :container-id="$containerId" />
        @endforeach
    </div>
</div>

@push('scripts')
<script src="https://d3js.org/d3.v7.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Wait for D3 to be available
    function waitForD3() {
        if (typeof d3 !== 'undefined') {
            // Initialize timeline section
            if (window.TimelineManager) {
                window.TimelineManager.initializeSection('{{ $containerId }}', @json($spans));
            } else {
                // Wait for TimelineManager to be available
                document.addEventListener('timelineManagerReady', function() {
                    window.TimelineManager.initializeSection('{{ $containerId }}', @json($spans));
                });
            }
        } else {
            setTimeout(waitForD3, 50);
        }
    }
    
    waitForD3();
});
</script>
@endpush 