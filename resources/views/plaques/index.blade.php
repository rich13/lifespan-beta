@extends('layouts.app')

@section('title', 'London Plaques')

@section('content')
<div class="container-fluid p-0">
    <div class="row g-0">
        <div class="col-12">
            <div class="position-relative" style="height: calc(100vh - 56px);">
                <!-- Map Container -->
                <div id="map" style="height: 100%; width: 100%;"></div>
                
                <!-- Info Panel -->
                <div class="position-absolute top-0 start-0 m-3">
                    <div class="card shadow-sm">
                        <div class="card-header bg-primary text-white">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-geo-alt me-2"></i>
                                London Plaques
                            </h5>
                        </div>
                        <div class="card-body">
                            <p class="card-text mb-2">
                                <strong>{{ count($plaquesWithLocations) }}</strong> plaques found
                            </p>
                            <p class="card-text small text-muted mb-0">
                                Click on markers to view plaque details
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Leaflet CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

<!-- Leaflet JavaScript -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize the map centered on London
    const map = L.map('map').setView([51.505, -0.09], 10);
    
    // Add OpenStreetMap tiles
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);
    
    // Plaque data from the server
    const plaques = @json($plaquesWithLocations);
    
    // Create markers for each plaque
    plaques.forEach(function(plaque) {
        const marker = L.marker([plaque.latitude, plaque.longitude])
            .addTo(map)
            .bindPopup(`
                <div class="text-center">
                    <h6 class="mb-2">${plaque.name}</h6>
                    ${plaque.description ? `<p class="mb-2 small">${plaque.description}</p>` : ''}
                    <a href="${plaque.url}" class="btn btn-primary btn-sm">
                        View Details
                    </a>
                </div>
            `);
    });
    
    // Fit map to show all markers if there are any
    if (plaques.length > 0) {
        const group = new L.featureGroup(plaques.map(p => L.latLng(p.latitude, p.longitude)));
        map.fitBounds(group.getBounds().pad(0.1));
    }
});
</script>

<style>
/* Custom styles for the map */
.leaflet-popup-content {
    margin: 8px 12px;
    min-width: 200px;
}

.leaflet-popup-content h6 {
    color: #333;
    font-weight: 600;
}

.leaflet-popup-content p {
    color: #666;
    line-height: 1.4;
}

.leaflet-popup-content .btn {
    margin-top: 8px;
}
</style>
@endsection
