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
        <!-- Plaques List Column -->
        <div class="col-lg-3 col-md-4 d-none d-md-flex border-end" id="plaquesListColumn" style="height: calc(100vh - 56px);">
            <div class="h-100 d-flex flex-column" style="min-height: 0;">
                <!-- Header -->
                <div class="card border-0 border-bottom rounded-0 flex-shrink-0">
                    <div class="card-body">
                        <h5 class="card-title mb-3">
                            <i class="bi bi-list-ul me-2"></i>
                            Plaques
                        </h5>
                        <!-- Search Box -->
                        <div class="input-group input-group-sm mb-2">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="text" class="form-control" id="plaqueSearch" placeholder="Search plaques...">
                        </div>
                        <p class="text-muted small mb-0">
                            <strong id="plaqueCount">{{ count($plaquesWithLocations) }}</strong> plaques found
                        </p>
                    </div>
                </div>
                
                <!-- Plaques List - Scrollable Container -->
                <div class="flex-grow-1 overflow-y-auto overflow-x-hidden" style="min-height: 0;">
                    <div id="plaquesList" class="list-group list-group-flush">
                        <!-- Plaques will be populated by JavaScript -->
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Map Column -->
        <div class="col-lg-6 col-md-8">
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
                                Click on markers or list items to view details
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- Mobile Toggle Buttons -->
                <div class="position-absolute top-0 end-0 m-3 d-md-none">
                    <div class="btn-group">
                        <button class="btn btn-primary btn-sm" onclick="toggleListPanel()">
                            <i class="bi bi-list"></i>
                        </button>
                        <button class="btn btn-primary btn-sm" onclick="toggleDetailsPanel()">
                            <i class="bi bi-info-circle"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Details Column -->
        <div class="col-lg-3 d-none d-lg-block border-start" id="detailsColumn">
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
                            <h6>Select a plaque</h6>
                            <p class="small">Click on any marker or list item to view detailed information</p>
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
    
    // Store markers and list items for highlighting
    const markers = new Map(); // Map of plaque ID to marker
    const listItems = new Map(); // Map of plaque ID to list item element
    let selectedPlaqueId = null;
    let selectedMarker = null;
    let selectedListItem = null;
    
    // Function to format date
    function formatDate(year, month, day) {
        if (!year) return 'Unknown date';
        
        const date = new Date(year, (month || 1) - 1, day || 1);
        const options = { year: 'numeric' };
        
        if (month) options.month = 'long';
        if (day) options.day = 'numeric';
        
        return date.toLocaleDateString('en-GB', options);
    }
    
    // Function to highlight a plaque (marker and list item)
    function highlightPlaque(plaqueId, plaque) {
        // Remove previous highlight
        if (selectedMarker) {
            selectedMarker.setIcon(defaultIcon);
        }
        if (selectedListItem) {
            selectedListItem.classList.remove('active');
            selectedListItem.classList.remove('bg-primary');
            selectedListItem.classList.remove('text-white');
        }
        
        // Set new selection
        selectedPlaqueId = plaqueId;
        selectedMarker = markers.get(plaqueId);
        selectedListItem = listItems.get(plaqueId);
        
        // Highlight marker with different icon
        if (selectedMarker) {
            // Create a highlighted icon
            const highlightedIcon = L.icon({
                iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-red.png',
                shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
                iconSize: [25, 41],
                iconAnchor: [12, 41],
                popupAnchor: [1, -34],
                shadowSize: [41, 41]
            });
            selectedMarker.setIcon(highlightedIcon);
            
            // Center map on marker and zoom in
            map.setView([plaque.latitude, plaque.longitude], 16);
            
            // Open popup briefly
            selectedMarker.openPopup();
            setTimeout(() => {
                if (selectedMarker) {
                    selectedMarker.closePopup();
                }
            }, 2000);
        }
        
        // Highlight list item
        if (selectedListItem) {
            selectedListItem.classList.add('active');
            selectedListItem.classList.add('bg-primary');
            selectedListItem.classList.add('text-white');
            
            // Scroll list item into view
            selectedListItem.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
        
        // Show details
        showPlaqueDetails(plaque);
    }
    
    // Default marker icon
    const defaultIcon = L.icon({
        iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-blue.png',
        shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
        iconSize: [25, 41],
        iconAnchor: [12, 41],
        popupAnchor: [1, -34],
        shadowSize: [41, 41]
    });
    L.Marker.prototype.options.icon = defaultIcon;
    
    // Function to populate plaques list
    function populatePlaquesList(filteredPlaques = null) {
        const plaquesList = document.getElementById('plaquesList');
        const displayPlaques = filteredPlaques || plaques;
        const plaqueCount = document.getElementById('plaqueCount');
        
        plaquesList.innerHTML = '';
        plaqueCount.textContent = displayPlaques.length;
        
        if (displayPlaques.length === 0) {
            plaquesList.innerHTML = '<div class="list-group-item text-muted text-center"><small>No plaques found</small></div>';
            return;
        }
        
        displayPlaques.forEach(function(plaque) {
            // Extract plaque ID - use top-level id (now added in controller)
            const plaqueId = plaque.id;
            if (!plaqueId) return; // Skip if no ID
            
            const listItem = document.createElement('a');
            listItem.href = '#';
            listItem.className = 'list-group-item list-group-item-action';
            listItem.dataset.plaqueId = plaqueId;
            
            // Get person name if available
            const personName = plaque.person_connections && plaque.person_connections.length > 0 
                ? plaque.person_connections[0].name 
                : null;
            
            listItem.innerHTML = `
                <div class="d-flex w-100 justify-content-between align-items-start">
                    <div class="flex-grow-1">
                        <h6 class="mb-1">${escapeHtml(plaque.name)}</h6>
                        ${personName ? `<p class="mb-1 small text-muted"><i class="bi bi-person me-1"></i>${escapeHtml(personName)}</p>` : ''}
                        ${plaque.location ? `<p class="mb-0 small text-muted"><i class="bi bi-geo-alt me-1"></i>${escapeHtml(plaque.location.name || 'Unknown location')}</p>` : ''}
                    </div>
                </div>
            `;
            
            listItem.addEventListener('click', function(e) {
                e.preventDefault();
                highlightPlaque(plaqueId, plaque);
            });
            
            plaquesList.appendChild(listItem);
            listItems.set(plaqueId, listItem);
        });
    }
    
    // Function to escape HTML
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Search functionality
    const plaqueSearch = document.getElementById('plaqueSearch');
    if (plaqueSearch) {
        plaqueSearch.addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase().trim();
            
            if (searchTerm === '') {
                populatePlaquesList();
                return;
            }
            
            const filtered = plaques.filter(function(plaque) {
                const nameMatch = plaque.name.toLowerCase().includes(searchTerm);
                const descriptionMatch = (plaque.description || '').toLowerCase().includes(searchTerm);
                const locationMatch = (plaque.location?.name || '').toLowerCase().includes(searchTerm);
                const personMatch = (plaque.person_connections || []).some(function(person) {
                    return person.name.toLowerCase().includes(searchTerm);
                });
                
                return nameMatch || descriptionMatch || locationMatch || personMatch;
            });
            
            populatePlaquesList(filtered);
        });
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
    
    // Function to toggle list panel on mobile/tablet
    window.toggleListPanel = function() {
        const listColumn = document.getElementById('plaquesListColumn');
        if (window.innerWidth < 992) { // Tablet and mobile
            listColumn.classList.toggle('show');
        }
    };
    
    // Create markers for each plaque
    plaques.forEach(function(plaque) {
        // Extract plaque ID - use top-level id (now added in controller)
        const plaqueId = plaque.id;
        if (!plaqueId) return; // Skip if no ID
        
        // Create marker with popup
        const marker = L.marker([plaque.latitude, plaque.longitude], { icon: defaultIcon })
            .addTo(map)
            .bindPopup(`
                <div>
                    <h6>${escapeHtml(plaque.name)}</h6>
                    ${plaque.location ? `<p class="mb-1 small">${escapeHtml(plaque.location.name || 'Unknown location')}</p>` : ''}
                    <button class="btn btn-sm btn-primary mt-2" onclick="window.selectPlaqueById('${plaqueId}')">
                        View Details
                    </button>
                </div>
            `)
            .on('click', function() {
                highlightPlaque(plaqueId, plaque);
            });
        
        markers.set(plaqueId, marker);
    });
    
    // Make selectPlaqueById available globally
    window.selectPlaqueById = function(plaqueId) {
        const plaque = plaques.find(function(p) {
            return p.id === plaqueId;
        });
        if (plaque) {
            highlightPlaque(plaqueId, plaque);
        }
    };
    
    // Populate the plaques list
    populatePlaquesList();
    
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

/* Plaques list styles */
#plaquesListColumn {
    height: calc(100vh - 56px);
    max-height: calc(100vh - 56px);
    overflow: hidden;
}

/* Ensure inner flex container respects parent height and allows scrolling */
#plaquesListColumn > div.h-100 {
    min-height: 0;
    height: 100%;
    display: flex;
    flex-direction: column;
}

/* Scrollable list container - takes remaining space and scrolls */
#plaquesListColumn .overflow-y-auto {
    /* Smooth scrolling on mobile */
    -webkit-overflow-scrolling: touch;
    /* Ensure it can shrink and scroll */
    min-height: 0;
    flex: 1 1 auto;
}

#plaquesList .list-group-item {
    cursor: pointer;
    transition: all 0.2s ease;
    border-left: 3px solid transparent;
}

#plaquesList .list-group-item:hover {
    background-color: #f8f9fa;
    border-left-color: #0d6efd;
}

#plaquesList .list-group-item.active {
    border-left-color: #0d6efd;
    border-left-width: 4px;
}

#plaquesList .list-group-item h6 {
    font-size: 0.9rem;
    font-weight: 600;
    margin-bottom: 0.25rem;
}

#plaquesList .list-group-item p {
    font-size: 0.8rem;
    margin-bottom: 0.25rem;
}

/* Mobile responsive styles */
@media (max-width: 991.98px) {
    #plaquesListColumn {
        position: fixed;
        top: 56px; /* Below navbar */
        left: 0;
        bottom: 0;
        width: 300px;
        z-index: 1040;
        background: white;
        box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        display: none !important; /* Hidden by default on tablet */
    }
    
    #plaquesListColumn.show {
        display: flex !important;
    }
    
    #detailsColumn {
        position: fixed;
        top: 56px; /* Below navbar */
        right: 0;
        bottom: 0;
        width: 100%;
        max-width: 400px;
        z-index: 1040;
        background: white;
        box-shadow: -2px 0 10px rgba(0,0,0,0.1);
    }
}

@media (max-width: 767.98px) {
    #plaquesListColumn {
        width: 100%;
        max-width: 100%;
    }
    
    #detailsColumn {
        width: 100%;
        max-width: 100%;
    }
}
</style>
@endsection
