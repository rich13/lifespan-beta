@props(['spans', 'containerId' => 'global-timescale'])

<div class="global-timescale">
    <div id="{{ $containerId }}" style="height: 100%; width: 100%; margin-left: 0;">
        <!-- D3 timeline will be rendered here -->
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize global timeline when this component loads
    if (window.TimelineManager) {
        window.TimelineManager.initializeGlobalTimescale('{{ $containerId }}', @json($spans));
    } else {
        // Wait for TimelineManager to be available
        document.addEventListener('timelineManagerReady', function() {
            window.TimelineManager.initializeGlobalTimescale('{{ $containerId }}', @json($spans));
        });
    }
});
</script>
@endpush 