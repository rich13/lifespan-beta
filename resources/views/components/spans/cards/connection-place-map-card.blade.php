@props(['span', 'connectionForSpan' => null])

@php
    $placeConnectionTypes = ['located', 'travel', 'residence'];
    $showConnectionPlaceMap = false;
    $place = null;
    $coordinates = null;
    $mapHeight = 200;

    if ($span->type_id === 'connection') {
        $currentConnection = $connectionForSpan ?? \App\Models\Connection::where('connection_span_id', $span->id)
            ->with(['parent', 'child'])
            ->first();

        if ($currentConnection && in_array($currentConnection->type_id, $placeConnectionTypes, true)) {
            $place = $currentConnection->child;
            if ($place && $place->type_id === 'place') {
                $coordinates = $place->getCoordinates();
                if ($coordinates && isset($coordinates['latitude'], $coordinates['longitude'])) {
                    $showConnectionPlaceMap = true;
                }
            }
        }
    }
@endphp

@if($showConnectionPlaceMap)
<div class="card mb-4">
    <div class="card-header">
        <h6 class="card-title mb-0">
            <i class="bi bi-geo-alt me-2"></i>
            Location
        </h6>
    </div>
    <div class="card-body">
        <div id="connection-place-map-{{ $span->id }}" class="mb-0" style="height: {{ $mapHeight }}px; width: 100%; border-radius: 0.375rem;"></div>
        <div class="mt-2 small text-muted">
            <x-span-link :span="$place" class="text-decoration-none" />
        </div>
    </div>
</div>

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
@endpush

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const mapEl = document.getElementById('connection-place-map-{{ $span->id }}');
    if (!mapEl) return;

    const lat = {{ $coordinates['latitude'] }};
    const lng = {{ $coordinates['longitude'] }};
    const map = L.map('connection-place-map-{{ $span->id }}').setView([lat, lng], 13);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);

    L.marker([lat, lng], {
        icon: L.icon({
            iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-red.png',
            shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
            iconSize: [25, 41],
            iconAnchor: [12, 41],
            popupAnchor: [1, -34],
            shadowSize: [41, 41]
        })
    }).addTo(map).bindPopup('<strong>{{ addslashes($place->name) }}</strong>');
});
</script>
@endif
