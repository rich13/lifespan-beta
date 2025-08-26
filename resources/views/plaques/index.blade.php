@extends('layouts.app')

@section('title', 'London Plaques')

@section('page_title')
    <x-breadcrumb :items="[
        [
            'text' => 'Explore',
            'icon' => 'view',
            'icon_category' => 'action',
            'url' => route('explore.index')
        ],
        [
            'text' => 'London Plaques',
            'icon' => 'geo-alt',
            'icon_category' => 'bootstrap'
        ]
    ]" />
@endsection

@section('content')
<div class="container-fluid p-0">
    <div class="row g-0" style="height: calc(100vh - 56px);">
        <!-- Map Column -->
        <div class="col-lg-8 col-md-7">
            <div class="position-relative h-100">
                <!-- Map Container -->
                <div id="map" style="height: 100%; width: 100%;"></div>
                
                <!-- Map Info Panel -->
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
                                Click on markers to view details
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- Mobile Toggle Button -->
                <div class="position-absolute top-0 end-0 m-3 d-md-none">
                    <button class="btn btn-primary" onclick="toggleDetailsPanel()">
                        <i class="bi bi-info-circle"></i>
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Details Column -->
        <div class="col-lg-4 col-md-5 d-none d-md-block" id="detailsColumn">
            <div class="h-100 d-flex flex-column">
                <!-- Header -->
                <div class="card border-0 border-bottom rounded-0">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-info-circle me-2"></i>
                            Plaque Details
                        </h5>
                        <button class="btn btn-sm btn-outline-secondary d-md-none" onclick="toggleDetailsPanel()">
                            <i class="bi bi-x"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Details Content -->
                <div class="flex-grow-1 overflow-auto">
                    <div id="plaqueDetails" class="p-3">
                        <div class="text-center text-muted py-5">
                            <i class="bi bi-geo-alt display-4 mb-3"></i>
                            <h6>Select a plaque on the map</h6>
                            <p class="small">Click on any marker to view detailed information about the plaque</p>
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
    
    // Function to format date
    function formatDate(year, month, day) {
        if (!year) return 'Unknown date';
        
        const date = new Date(year, (month || 1) - 1, day || 1);
        const options = { year: 'numeric' };
        
        if (month) options.month = 'long';
        if (day) options.day = 'numeric';
        
        return date.toLocaleDateString('en-GB', options);
    }
    
    // Function to show plaque details
    function showPlaqueDetails(plaque) {
        const detailsContainer = document.getElementById('plaqueDetails');
        
        // Show loading state briefly
        detailsContainer.innerHTML = `
            <div class="text-center py-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2 text-muted">Loading plaque details...</p>
            </div>
        `;
        
        // Show details after a brief delay for better UX
        setTimeout(() => {
            let html = `
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h6 class="card-title mb-0">
                            <i class="bi bi-geo-alt me-2"></i>
                            ${plaque.name}
                        </h6>
                    </div>
                    <div class="card-body">
            `;
        
        // Description
        if (plaque.description) {
            html += `
                <div class="mb-3">
                    <h6 class="text-muted mb-2">Description</h6>
                    <p class="mb-0">${plaque.description}</p>
                </div>
            `;
        }
        
        // Location information
        if (plaque.location) {
            html += `
                <div class="mb-3">
                    <h6 class="text-muted mb-2">Location</h6>
                    <p class="mb-1"><strong>Address:</strong> ${plaque.location.name || 'Unknown'}</p>
                    <p class="mb-0"><strong>Coordinates:</strong> ${plaque.latitude.toFixed(6)}, ${plaque.longitude.toFixed(6)}</p>
                </div>
            `;
        }
        
        // Plaque information
        if (plaque.plaque) {
            const plaqueData = plaque.plaque;
            
            // Dates
            if (plaqueData.start_year || plaqueData.end_year) {
                html += `
                    <div class="mb-3">
                        <h6 class="text-muted mb-2">Dates</h6>
                `;
                
                if (plaqueData.start_year) {
                    html += `<p class="mb-1"><strong>From:</strong> ${formatDate(plaqueData.start_year, plaqueData.start_month, plaqueData.start_day)}</p>`;
                }
                
                if (plaqueData.end_year) {
                    html += `<p class="mb-0"><strong>To:</strong> ${formatDate(plaqueData.end_year, plaqueData.end_month, plaqueData.end_day)}</p>`;
                }
                
                html += `</div>`;
            }
            
            // Metadata
            if (plaqueData.metadata) {
                const metadata = plaqueData.metadata;
                const metadataItems = [];
                
                if (metadata.subtype) metadataItems.push(`<strong>Type:</strong> ${metadata.subtype}`);
                if (metadata.colour) metadataItems.push(`<strong>Colour:</strong> ${metadata.colour}`);
                if (metadata.organisation) metadataItems.push(`<strong>Organisation:</strong> ${metadata.organisation}`);
                if (metadata.erected_by) metadataItems.push(`<strong>Erected by:</strong> ${metadata.erected_by}`);
                if (metadata.erected_date) metadataItems.push(`<strong>Erected:</strong> ${metadata.erected_date}`);
                
                if (metadataItems.length > 0) {
                    html += `
                        <div class="mb-3">
                            <h6 class="text-muted mb-2">Details</h6>
                            ${metadataItems.map(item => `<p class="mb-1">${item}</p>`).join('')}
                        </div>
                    `;
                }
            }
        }
        
        // Person connections
        if (plaque.person_connections && plaque.person_connections.length > 0) {
            html += `
                <div class="mb-3">
                    <h6 class="text-muted mb-2">People Featured</h6>
                    <div class="list-group list-group-flush">
            `;
            
            plaque.person_connections.forEach(function(person) {
                html += `
                    <a href="${person.url}" class="list-group-item list-group-item-action py-2">
                        <i class="bi bi-person me-2"></i>
                        ${person.name}
                    </a>
                `;
            });
            
            html += `
                    </div>
                </div>
            `;
        }
        
        // Organisation connections
        if (plaque.organisation_connections && plaque.organisation_connections.length > 0) {
            html += `
                <div class="mb-3">
                    <h6 class="text-muted mb-2">Organisations Featured</h6>
                    <div class="list-group list-group-flush">
            `;
            
            plaque.organisation_connections.forEach(function(org) {
                html += `
                    <a href="${org.url}" class="list-group-item list-group-item-action py-2">
                        <i class="bi bi-building me-2"></i>
                        ${org.name}
                    </a>
                `;
            });
            
            html += `
                    </div>
                </div>
            `;
        }
        
        // Action buttons
        html += `
                    <div class="mt-4">
                        <a href="${plaque.url}" class="btn btn-primary btn-sm me-2">
                            <i class="bi bi-eye me-1"></i>
                            View Full Details
                        </a>
                        <button class="btn btn-outline-secondary btn-sm" onclick="centerMapOnPlaque(${plaque.latitude}, ${plaque.longitude})">
                            <i class="bi bi-geo-alt me-1"></i>
                            Center Map
                        </button>
                    </div>
                </div>
            </div>
        `;
        
            detailsContainer.innerHTML = html;
        }, 100); // Brief delay for better UX
    }
    
    // Function to center map on plaque
    window.centerMapOnPlaque = function(lat, lng) {
        map.setView([lat, lng], 16);
    };
    
    // Function to toggle details panel on mobile
    window.toggleDetailsPanel = function() {
        const detailsColumn = document.getElementById('detailsColumn');
        if (window.innerWidth < 768) { // Mobile only
            detailsColumn.classList.toggle('d-none');
        }
    };
    
    // Create markers for each plaque
    plaques.forEach(function(plaque) {
        const marker = L.marker([plaque.latitude, plaque.longitude])
            .addTo(map)
            .on('click', function() {
                showPlaqueDetails(plaque);
            });
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

/* Mobile responsive styles */
@media (max-width: 767.98px) {
    #detailsColumn {
        position: fixed;
        top: 0;
        right: 0;
        bottom: 0;
        width: 100%;
        z-index: 1050;
        background: white;
        box-shadow: -2px 0 10px rgba(0,0,0,0.1);
    }
}
</style>
@endsection
