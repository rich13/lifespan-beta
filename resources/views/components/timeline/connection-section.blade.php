@props(['connections', 'containerId' => 'default', 'title' => 'Connections'])

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
                <h3 class="h6 mb-0">{{ $title }}</h3>
            </div>
        </div>
    </div>
    
    <!-- Connection spans list with timelines -->
    <div class="connection-spans-list">
        @foreach($connections as $connection)
            @if($connection->connectionSpan)
                <x-connections.timeline-card :connection="$connection" :container-id="$containerId" />
            @endif
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
            // Extract connection spans (which contain the temporal data) from connections
            const connectionSpans = @json($connections->pluck('connectionSpan')->filter());
            
            // Debug: Log the connection spans data
            console.log('Connection spans data:', connectionSpans);
            console.log('Number of connection spans:', connectionSpans.length);
            
            // Check if spans have temporal data
            connectionSpans.forEach((span, index) => {
                console.log(`Span ${index}:`, {
                    id: span.id,
                    name: span.name,
                    start_year: span.start_year,
                    end_year: span.end_year,
                    type_id: span.type_id
                });
            });
            
            // Initialize timeline section
            if (window.TimelineManager) {
                window.TimelineManager.initializeSection('{{ $containerId }}', connectionSpans);
            } else {
                // Wait for TimelineManager to be available
                document.addEventListener('timelineManagerReady', function() {
                    window.TimelineManager.initializeSection('{{ $containerId }}', connectionSpans);
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