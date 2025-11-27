@extends('layouts.app')

@section('title', 'Places')

@section('page_title')
    <x-breadcrumb :items="[
        [
            'text' => 'Places',
            'icon' => 'geo-alt',
            'icon_category' => 'bootstrap'
        ]
    ]" />
@endsection

@section('content')
<div class="container-fluid p-0">
    <div class="row g-0" style="height: calc(100vh - 56px);">
        <!-- Places List Column -->
        <div class="col-lg-3 col-md-4 d-none d-md-flex border-end" id="placesListColumn" style="height: calc(100vh - 56px);">
            <div class="h-100 d-flex flex-column" style="min-height: 0;">
                <!-- Header -->
                <div class="card border-0 border-bottom rounded-0 flex-shrink-0">
                    <div class="card-body">
                        <h5 class="card-title mb-3">
                            <i class="bi bi-list-ul me-2"></i>
                            Places
                        </h5>
                        <!-- Subtype Filters -->
                        <div class="mb-3">
                            <label class="form-label small fw-bold mb-2">Filter by Type:</label>
                            <div id="subtypeFilters" class="d-flex flex-wrap gap-2">
                                <!-- Filters will be populated by JavaScript -->
                            </div>
                            <div class="mt-2">
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="selectAllSubtypes">All</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="deselectAllSubtypes">None</button>
                            </div>
                        </div>
                        <!-- Search Box -->
                        <div class="input-group input-group-sm mb-2">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="text" class="form-control" id="placeSearch" placeholder="Search places...">
                        </div>
                        <p class="text-muted small mb-0">
                            <strong id="placeCount">0</strong> places visible
                            <span id="loadingSpinner" class="ms-2 d-none">
                                <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                                <span class="visually-hidden">Loading...</span>
                            </span>
                        </p>
                    </div>
                </div>
                
                <!-- Places List - Scrollable Container -->
                <div class="flex-grow-1 overflow-y-auto overflow-x-hidden" style="min-height: 0;">
                    <div id="placesList" class="list-group list-group-flush">
                        <!-- Places will be populated by JavaScript -->
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Map Column -->
        <div class="col-lg-6 col-md-8">
            <div class="position-relative h-100">
                <!-- Map Container -->
                <div id="map" style="height: 100%; width: 100%;"></div>
            </div>
        </div>
        
        <!-- Details Column -->
        <div class="col-lg-3 d-none d-lg-block border-start" id="detailsColumn">
            <div class="h-100 d-flex flex-column">
                <!-- Header -->
                <div class="card border-0 border-bottom rounded-0 flex-shrink-0">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-info-circle me-2"></i>
                            Place Details
                        </h5>
                    </div>
                </div>
                
                <!-- Details Content -->
                <div class="flex-grow-1 overflow-auto">
                    <!-- Place Tools (Admin Only) -->
                    <div id="placeTools" class="p-3 border-bottom d-none">
                        <h6 class="mb-3">
                            <i class="bi bi-tools me-2"></i>
                            Place Tools
                        </h6>
                        <button type="button" class="btn btn-sm btn-outline-primary w-100" id="getMapDataBtn">
                            <i class="bi bi-download me-1"></i>Get Map Data
                        </button>
                    </div>
                    
                    <div id="placeDetails" class="p-3">
                        <div class="text-center text-muted py-5">
                            <i class="bi bi-geo-alt display-4 mb-3"></i>
                            <h6>Select a place</h6>
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
    console.log('Places map initializing - boundaries disabled');
    
    // Initialize the map centered on UK
    // Try SVG renderer for better fill rendering with complex polygons
    // SVG handles fills more reliably than Canvas for complex geometries
    const map = L.map('map', {
        renderer: L.svg({ padding: 0.5 })
    }).setView([51.505, -0.09], 6);
    
    // Add OpenStreetMap tiles
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);
    
    // Store markers
    const markers = new Map(); // Map of place ID to marker
    const listItems = new Map(); // Map of place ID to list item element
    let currentPlaces = []; // Array of currently visible places
    let selectedPlaceId = null;
    let selectedMarker = null;
    let selectedListItem = null;
    let loadingBounds = null;
    let loadTimeout = null;
    let enabledSubtypes = new Set(); // Set of enabled subtype filters
    let isProgrammaticMove = false; // Flag to prevent reload when programmatically moving map
    let lastLoadedBounds = null; // Track last loaded bounds to avoid unnecessary reloads
    
    // Place subtypes from schema
    const placeSubtypes = [
        'country',
        'state_region',
        'county_province',
        'city_district',
        'suburb_area',
        'neighbourhood',
        'sub_neighbourhood',
        'building_property'
    ];
    
    // Initialize all subtypes as enabled by default
    placeSubtypes.forEach(subtype => enabledSubtypes.add(subtype));
    
    // Function to escape HTML
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Function to initialize subtype filters
    function initializeSubtypeFilters() {
        const filtersContainer = document.getElementById('subtypeFilters');
        filtersContainer.innerHTML = '';
        
        placeSubtypes.forEach(subtype => {
            const filterBtn = document.createElement('button');
            filterBtn.type = 'button';
            filterBtn.className = 'btn btn-sm btn-outline-primary subtype-filter';
            filterBtn.dataset.subtype = subtype;
            filterBtn.textContent = subtype.replace(/_/g, ' ');
            filterBtn.classList.add('active'); // All enabled by default
            
            filterBtn.addEventListener('click', function() {
                if (enabledSubtypes.has(subtype)) {
                    enabledSubtypes.delete(subtype);
                    filterBtn.classList.remove('active');
                } else {
                    enabledSubtypes.add(subtype);
                    filterBtn.classList.add('active');
                }
                applyFilters();
            });
            
            filtersContainer.appendChild(filterBtn);
        });
        
        // Select all button
        document.getElementById('selectAllSubtypes').addEventListener('click', function() {
            placeSubtypes.forEach(subtype => enabledSubtypes.add(subtype));
            document.querySelectorAll('.subtype-filter').forEach(btn => {
                btn.classList.add('active');
            });
            applyFilters();
        });
        
        // Deselect all button
        document.getElementById('deselectAllSubtypes').addEventListener('click', function() {
            enabledSubtypes.clear();
            document.querySelectorAll('.subtype-filter').forEach(btn => {
                btn.classList.remove('active');
            });
            applyFilters();
        });
    }
    
    // Boundary loading removed - boundaries are too complex to render reliably
    
    // Function to apply filters (subtype and search)
    function applyFilters() {
        const searchTerm = document.getElementById('placeSearch').value.toLowerCase();
        
        // Filter places based on subtype and search
        const filteredPlaces = currentPlaces.filter(place => {
            // Check subtype filter
            const subtype = place.subtype || '';
            if (!enabledSubtypes.has(subtype)) {
                return false;
            }
            
            // Check search filter
            if (searchTerm) {
                const matches = place.name.toLowerCase().includes(searchTerm) ||
                    (place.subtype && place.subtype.toLowerCase().includes(searchTerm)) ||
                    (place.description && place.description.toLowerCase().includes(searchTerm));
                if (!matches) {
                    return false;
                }
            }
            
            return true;
        });
        
        // Update map markers visibility
        markers.forEach((marker, placeId) => {
            const place = currentPlaces.find(p => p.id === placeId);
            if (place) {
                const subtype = place.subtype || '';
                const matchesSubtype = enabledSubtypes.has(subtype);
                const matchesSearch = !searchTerm || 
                    place.name.toLowerCase().includes(searchTerm) ||
                    (place.subtype && place.subtype.toLowerCase().includes(searchTerm)) ||
                    (place.description && place.description.toLowerCase().includes(searchTerm));
                
                if (matchesSubtype && matchesSearch) {
                    if (!map.hasLayer(marker)) {
                        marker.addTo(map);
                    }
                } else {
                    if (map.hasLayer(marker)) {
                        map.removeLayer(marker);
                    }
                }
            }
        });
        
        // Boundary rendering removed - no longer showing boundaries
        
        // Update list
        updatePlacesList(filteredPlaces);
        
        // Update count
        document.getElementById('placeCount').textContent = filteredPlaces.length;
    }
    
    // Function to load places for current map bounds
    function loadPlacesInBounds() {
        // Skip if this is a programmatic move (e.g., selecting a place)
        if (isProgrammaticMove) {
            return;
        }
        
        const bounds = map.getBounds();
        const zoom = map.getZoom();
        
        // Check if bounds have changed significantly (avoid reloading for tiny movements)
        if (lastLoadedBounds) {
            const boundsChanged = 
                Math.abs(bounds.getNorth() - lastLoadedBounds.north) > 0.01 ||
                Math.abs(bounds.getSouth() - lastLoadedBounds.south) > 0.01 ||
                Math.abs(bounds.getEast() - lastLoadedBounds.east) > 0.01 ||
                Math.abs(bounds.getWest() - lastLoadedBounds.west) > 0.01 ||
                zoom !== lastLoadedBounds.zoom;
            
            if (!boundsChanged) {
                return; // Bounds haven't changed significantly, skip reload
            }
        }
        
        // Cancel any pending load
        if (loadTimeout) {
            clearTimeout(loadTimeout);
        }
        
        // Debounce the load to avoid too many requests (increased from 300ms to 500ms)
        loadTimeout = setTimeout(() => {
            const params = new URLSearchParams({
                north: bounds.getNorth(),
                south: bounds.getSouth(),
                east: bounds.getEast(),
                west: bounds.getWest(),
                zoom: zoom
            });
            
            // Show loading spinner
            const spinner = document.getElementById('loadingSpinner');
            const countElement = document.getElementById('placeCount');
            spinner.classList.remove('d-none');
            
            fetch(`/api/places?${params}`)
                    .then(response => {
                        if (!response.ok) {
                            if (response.status === 429) {
                                throw new Error('Too many requests. Please wait a moment.');
                            }
                            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                        }
                        const contentType = response.headers.get('content-type');
                        if (!contentType || !contentType.includes('application/json')) {
                            throw new Error('Invalid response format');
                        }
                        return response.json();
                    })
                    .then(data => {
                        // Hide spinner
                        spinner.classList.add('d-none');
                        
                        if (data && data.success) {
                            currentPlaces = data.places || [];
                            updatePlacesOnMap(currentPlaces);
                            applyFilters(); // This will update list and apply filters
                            
                            // Check for hash and select place if needed (only on first load)
                            // Only select from hash on initial page load, not on subsequent loads
                            if (!lastLoadedBounds && window.location.hash) {
                                setTimeout(() => {
                                    // Only process hash once on initial load
                                    const hash = window.location.hash;
                                    if (hash && hash.startsWith('#place-')) {
                                        const placeId = hash.substring(7);
                                        const place = currentPlaces.find(p => p.id === placeId);
                                        if (place) {
                                            // On initial load, allow zoom change to show the place
                                            highlightPlace(placeId, place, { preserveZoom: false, updateHash: false });
                                            lastProcessedHash = hash;
                                        }
                                    }
                                }, 100);
                            }
                            
                            // Store last loaded bounds
                            lastLoadedBounds = {
                                north: bounds.getNorth(),
                                south: bounds.getSouth(),
                                east: bounds.getEast(),
                                west: bounds.getWest(),
                                zoom: zoom
                            };
                        } else {
                            console.error('Failed to load places:', data?.message || 'Unknown error');
                            countElement.textContent = 'Error';
                            currentPlaces = [];
                            updatePlacesList([]);
                        }
                    })
                    .catch(error => {
                        // Hide spinner on error
                        spinner.classList.add('d-none');
                        console.error('Error loading places:', error);
                        countElement.textContent = error.message.includes('429') ? 'Too many requests' : 'Error';
                        currentPlaces = [];
                        updatePlacesList([]);
                    });
            }, 300); // 300ms debounce
        }
    
    // Default marker icon
    const defaultIcon = L.icon({
        iconUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
        iconSize: [25, 41],
        iconAnchor: [12, 41],
        popupAnchor: [1, -34]
    });
    
    // Selected marker icon (red)
    const selectedIcon = L.icon({
        iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-red.png',
        iconSize: [25, 41],
        iconAnchor: [12, 41],
        popupAnchor: [1, -34]
    });
    
    // Function to highlight a place (marker and list item)
    function highlightPlace(placeId, place, options = {}) {
        const { 
            preserveZoom = false,  // If true, don't change zoom level
            updateHash = true       // If true, update URL hash
        } = options;
        
        console.log('[HIGHLIGHT] highlightPlace() called', {
            placeId: placeId,
            placeName: place.name,
            preserveZoom: preserveZoom,
            updateHash: updateHash,
            currentZoom: map.getZoom(),
            stack: new Error().stack.split('\n').slice(1, 4).join(' -> ')
        });
        
        // Remove previous selection
        if (selectedMarker) {
            selectedMarker.setIcon(defaultIcon);
        }
        if (selectedListItem) {
            selectedListItem.classList.remove('active');
        }
        
        // Set new selection
        selectedPlaceId = placeId;
        selectedMarker = markers.get(placeId);
        selectedListItem = listItems.get(placeId);
        
        if (selectedMarker) {
            // Use red icon for selected marker
            selectedMarker.setIcon(selectedIcon);
            
            // Set flag to prevent reload during programmatic move
            isProgrammaticMove = true;
            
            // Pan to place - only change zoom if not preserving it
            if (preserveZoom) {
                console.log('[HIGHLIGHT] Panning to place (preserving zoom)', place.latitude, place.longitude);
                map.panTo([place.latitude, place.longitude]);
            } else {
                // Pan to place if zoomed out too far
                const currentZoom = map.getZoom();
                if (currentZoom < 13) {
                    console.log('[HIGHLIGHT] Setting view with zoom 13 (was', currentZoom, ')');
                    map.setView([place.latitude, place.longitude], 13);
                } else {
                    console.log('[HIGHLIGHT] Panning to place (zoom', currentZoom, ')');
                    map.panTo([place.latitude, place.longitude]);
                }
            }
            
            // Clear flag after a short delay to allow map to settle
            setTimeout(() => {
                isProgrammaticMove = false;
                console.log('[HIGHLIGHT] Cleared isProgrammaticMove flag');
            }, 1000);
        }
        
        if (selectedListItem) {
            selectedListItem.classList.add('active');
            selectedListItem.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
        
        // Load and display place details
        loadPlaceDetails(placeId, place);
        
        // Update URL hash for direct linking (only if requested)
        if (updateHash) {
            const newHash = `#place-${placeId}`;
            console.log('[HASH] Updating hash to:', newHash);
            // Set flag to prevent hash handler from processing our own update
            isUpdatingHash = true;
            // Update last processed hash to prevent re-processing
            lastProcessedHash = newHash;
            if (history.pushState) {
                history.pushState(null, null, newHash);
            } else {
                window.location.hash = `place-${placeId}`;
            }
            // Clear flag after a short delay
            setTimeout(() => {
                isUpdatingHash = false;
                console.log('[HASH] Cleared isUpdatingHash flag');
            }, 100);
        }
    }
    
    // Function to load and display place details
    function loadPlaceDetails(placeId, place) {
        const detailsContainer = document.getElementById('placeDetails');
        
        // Show loading state
        detailsContainer.innerHTML = `
            <div class="text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-3 text-muted">Loading place details...</p>
            </div>
        `;
        
        // Fetch full place span data
        fetch(`/api/places/${placeId}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data && data.success && data.span) {
                    displayPlaceDetails(data.span, place, placeId);
                } else {
                    // Fallback to basic info if API doesn't return full data
                    displayPlaceDetailsBasic(place, placeId);
                }
            })
            .catch(error => {
                console.error('Error loading place details:', error);
                // Fallback to basic info
                displayPlaceDetailsBasic(place, placeId);
            });
    }
    
    // Function to display place details from API
    function displayPlaceDetails(span, place, placeId) {
        const detailsContainer = document.getElementById('placeDetails');
        const toolsSection = document.getElementById('placeTools');
        const isAdmin = {{ auth()->check() && auth()->user()->getEffectiveAdminStatus() ? 'true' : 'false' }};
        const hasOsmData = (span.metadata?.external_refs?.osm || span.metadata?.osm_data) ? true : false;
        const osmData = span.metadata?.external_refs?.osm || span.metadata?.osm_data || null;
        
        // Show/hide tools section for admins
        if (isAdmin) {
            toolsSection.classList.remove('d-none');
            const getMapDataBtn = document.getElementById('getMapDataBtn');
            
            // Update button state based on whether OSM data exists
            if (hasOsmData) {
                // Has OSM data and boundary (or doesn't need one)
                getMapDataBtn.disabled = false;
                getMapDataBtn.innerHTML = '<i class="bi bi-arrow-clockwise me-1"></i>Update Map Data';
                getMapDataBtn.classList.remove('btn-outline-success', 'btn-outline-warning');
                getMapDataBtn.classList.add('btn-outline-primary');
                
                // Set up click handler
                getMapDataBtn.onclick = function() {
                    fetchMapData(place.id, getMapDataBtn);
                };
            } else {
                // No OSM data
                getMapDataBtn.disabled = false;
                getMapDataBtn.innerHTML = '<i class="bi bi-download me-1"></i>Get Map Data';
                getMapDataBtn.classList.remove('btn-outline-success', 'btn-outline-warning');
                getMapDataBtn.classList.add('btn-outline-primary');
                
                // Set up click handler
                getMapDataBtn.onclick = function() {
                    fetchMapData(place.id, getMapDataBtn);
                };
            }
        } else {
            toolsSection.classList.add('d-none');
        }
        
        let html = `
            <div class="mb-3">
                <h4 class="mb-2">${escapeHtml(span.name || place.name)}</h4>
                ${span.subtype ? `<span class="badge bg-primary mb-2">${escapeHtml(span.subtype.replace(/_/g, ' '))}</span>` : ''}
            </div>
        `;
        
        // Description
        if (span.description) {
            html += `
                <div class="mb-3">
                    <h6 class="text-muted small mb-2">Description</h6>
                    <p>${escapeHtml(span.description)}</p>
                </div>
            `;
        }
        
        // Dates
        if (span.start_year || span.end_year) {
            html += `
                <div class="mb-3">
                    <h6 class="text-muted small mb-2">Dates</h6>
                    <p class="mb-0">
                        ${span.start_year ? `From ${span.start_year}${span.start_month ? `-${String(span.start_month).padStart(2, '0')}` : ''}${span.start_day ? `-${String(span.start_day).padStart(2, '0')}` : ''}` : 'Unknown start'}
                        ${span.end_year ? ` to ${span.end_year}${span.end_month ? `-${String(span.end_month).padStart(2, '0')}` : ''}${span.end_day ? `-${String(span.end_day).padStart(2, '0')}` : ''}` : span.start_year ? ' (ongoing)' : ''}
                    </p>
                </div>
            `;
        }
        
        // Coordinates
        if (place.latitude && place.longitude) {
            html += `
                <div class="mb-3">
                    <h6 class="text-muted small mb-2">Coordinates</h6>
                    <p class="mb-0 small">
                        ${place.latitude.toFixed(6)}, ${place.longitude.toFixed(6)}
                    </p>
                </div>
            `;
        }
        
        // Metadata
        if (span.metadata) {
            const metadata = span.metadata;
            
            // Country
            if (metadata.country) {
                html += `
                    <div class="mb-3">
                        <h6 class="text-muted small mb-2">Country</h6>
                        <p class="mb-0">${escapeHtml(metadata.country)}</p>
                    </div>
                `;
            }
            
            // External references
            if (metadata.external_refs) {
                const refs = metadata.external_refs;
                
                if (refs.wikidata && refs.wikidata.id) {
                    html += `
                        <div class="mb-3">
                            <h6 class="text-muted small mb-2">Wikidata</h6>
                            <p class="mb-0">
                                <a href="https://www.wikidata.org/wiki/${escapeHtml(refs.wikidata.id)}" target="_blank" class="text-decoration-none">
                                    ${escapeHtml(refs.wikidata.id)}
                                    <i class="bi bi-box-arrow-up-right ms-1"></i>
                                </a>
                            </p>
                        </div>
                    `;
                }
            }
        }
        
        // View full details link
        html += `
            <div id="viewFullDetailsSection" class="mt-4">
                <a href="${place.url}" class="btn btn-primary w-100">
                    View Full Details
                    <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>
        `;
        
        detailsContainer.innerHTML = html;
        
        // Load lived-here-card asynchronously
        loadLivedHereCard(placeId);
    }
    
    // Function to load and display lived-here-card
    function loadLivedHereCard(placeId) {
        console.log('[LIVED-HERE] Loading lived-here-card for place:', placeId);
        fetch(`/api/places/${placeId}/lived-here-card`)
            .then(response => {
                console.log('[LIVED-HERE] Response status:', response.status);
                if (!response.ok) {
                    // If 404 or other error, just don't show the card
                    return null;
                }
                return response.json();
            })
            .then(data => {
                console.log('[LIVED-HERE] Response data:', data);
                if (data && data.success && data.html && data.html.trim() !== '') {
                    // Insert the lived-here-card HTML before the "View Full Details" section
                    const detailsContainer = document.getElementById('placeDetails');
                    const viewFullDetailsSection = document.getElementById('viewFullDetailsSection');
                    
                    if (detailsContainer && viewFullDetailsSection) {
                        // Create a container for the lived-here-card
                        const cardContainer = document.createElement('div');
                        cardContainer.innerHTML = data.html;
                        
                        // Insert the card before the "View Full Details" section
                        if (cardContainer.firstElementChild) {
                            const insertedCard = cardContainer.firstElementChild;
                            detailsContainer.insertBefore(
                                insertedCard,
                                viewFullDetailsSection
                            );
                            console.log('[LIVED-HERE] Card inserted successfully');
                            
                            // Initialize toggle buttons after insertion (fallback for dynamic loading)
                            // The component's own script should handle it, but this ensures it works
                            setTimeout(() => {
                                initPlaceCardToggle(insertedCard);
                            }, 50);
                        }
                    } else {
                        console.warn('[LIVED-HERE] Could not find placeDetails or viewFullDetailsSection');
                    }
                } else {
                    console.log('[LIVED-HERE] No card HTML to display (no residents)');
                }
            })
            .catch(error => {
                // Log error for debugging
                console.error('Could not load lived-here-card:', error);
            });
    }
    
    // Global function to initialize toggle buttons for place card (works in both contexts)
    if (typeof window.initPlaceCardToggle === 'undefined') {
        window.initPlaceCardToggle = function(cardElement) {
            // Find the toggle buttons and views within the card
            const livedToggle = cardElement.querySelector('input[id^="lived-toggle-"]');
            const locatedToggle = cardElement.querySelector('input[id^="located-toggle-"]');
            const livedView = cardElement.querySelector('div[id^="lived-view-"]');
            const locatedView = cardElement.querySelector('div[id^="located-view-"]');
            
            if (livedToggle && locatedToggle && livedView && locatedView) {
                // Check if already initialized
                if (livedToggle.dataset.initialized === 'true') {
                    return true; // Already initialized
                }
                
                // Set up event listeners directly (no need to clone)
                livedToggle.addEventListener('change', function() {
                    if (this.checked) {
                        livedView.style.display = 'block';
                        locatedView.style.display = 'none';
                    }
                });
                
                locatedToggle.addEventListener('change', function() {
                    if (this.checked) {
                        livedView.style.display = 'none';
                        locatedView.style.display = 'block';
                    }
                });
                
                // Mark as initialized
                livedToggle.dataset.initialized = 'true';
                locatedToggle.dataset.initialized = 'true';
                
                console.log('[LIVED-HERE] Toggle buttons initialized');
                return true;
            }
            return false;
        };
    }
    
    // Initialize any existing cards on page load (for server-rendered content)
    // This runs on all pages, not just the places page
    function initializeAllPlaceCards() {
        document.querySelectorAll('.place-residence-card').forEach(function(card) {
            // Check if already initialized by looking for data attribute
            if (!card.dataset.toggleInitialized) {
                if (window.initPlaceCardToggle(card)) {
                    card.dataset.toggleInitialized = 'true';
                }
            }
        });
    }
    
    // Run on DOMContentLoaded
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeAllPlaceCards);
    } else {
        // DOM already loaded
        initializeAllPlaceCards();
    }
    
    // Also run after a short delay to catch dynamically loaded content
    setTimeout(initializeAllPlaceCards, 500);
    
    // Function to fetch map data for a place
    function fetchMapData(placeId, button) {
        const originalText = button.innerHTML;
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Fetching...';
        button.disabled = true;
        
        // Get CSRF token
        const csrfToken = document.querySelector('meta[name="csrf-token"]');
        const headers = {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        };
        
        if (csrfToken) {
            headers['X-CSRF-TOKEN'] = csrfToken.getAttribute('content');
        }
        
        fetch(`/admin/places/${placeId}/fetch-osm-data`, {
            method: 'POST',
            headers: headers,
            credentials: 'same-origin'
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                button.innerHTML = '<i class="bi bi-check-circle me-1"></i>Success!';
                button.classList.remove('btn-outline-primary', 'btn-outline-warning');
                button.classList.add('btn-outline-success');
                
                // Reload place details to show updated data
                setTimeout(() => {
                    const place = currentPlaces.find(p => p.id === placeId);
                    if (place) {
                        loadPlaceDetails(placeId, place);
                    }
                }, 1000);
            } else {
                throw new Error(data.message || 'Failed to fetch map data');
            }
        })
        .catch(error => {
            console.error('Error fetching map data:', error);
            button.innerHTML = '<i class="bi bi-exclamation-triangle me-1"></i>Failed';
            button.classList.remove('btn-outline-primary', 'btn-outline-warning');
            button.classList.add('btn-outline-danger');
            setTimeout(() => {
                // Restore original button state based on what it was before
                const place = currentPlaces.find(p => p.id === placeId);
                if (place) {
                    loadPlaceDetails(placeId, place); // This will restore the correct button state
                } else {
                    button.innerHTML = originalText;
                    button.disabled = false;
                    button.classList.remove('btn-outline-danger');
                    button.classList.add('btn-outline-primary');
                }
            }, 2000);
        });
    }
    
    // Function to display basic place details (fallback)
    function displayPlaceDetailsBasic(place, placeId) {
        const detailsContainer = document.getElementById('placeDetails');
        const toolsSection = document.getElementById('placeTools');
        const isAdmin = {{ auth()->check() && auth()->user()->getEffectiveAdminStatus() ? 'true' : 'false' }};
        
        // Show/hide tools section for admins
        if (isAdmin) {
            toolsSection.classList.remove('d-none');
            const getMapDataBtn = document.getElementById('getMapDataBtn');
            getMapDataBtn.disabled = false;
            getMapDataBtn.innerHTML = '<i class="bi bi-download me-1"></i>Get Map Data';
            getMapDataBtn.classList.remove('btn-outline-success', 'btn-outline-warning', 'btn-outline-danger');
            getMapDataBtn.classList.add('btn-outline-primary');
            getMapDataBtn.onclick = function() {
                fetchMapData(place.id, getMapDataBtn);
            };
        } else {
            toolsSection.classList.add('d-none');
        }
        
        let html = `
            <div class="mb-3">
                <h4 class="mb-2">${escapeHtml(place.name)}</h4>
                ${place.subtype ? `<span class="badge bg-primary mb-2">${escapeHtml(place.subtype.replace(/_/g, ' '))}</span>` : ''}
            </div>
        `;
        
        if (place.description) {
            html += `
                <div class="mb-3">
                    <h6 class="text-muted small mb-2">Description</h6>
                    <p>${escapeHtml(place.description)}</p>
                </div>
            `;
        }
        
        if (place.latitude && place.longitude) {
            html += `
                <div class="mb-3">
                    <h6 class="text-muted small mb-2">Coordinates</h6>
                    <p class="mb-0 small">
                        ${place.latitude.toFixed(6)}, ${place.longitude.toFixed(6)}
                    </p>
                </div>
            `;
        }
        
        html += `
            <div id="viewFullDetailsSection" class="mt-4">
                <a href="${place.url}" class="btn btn-primary w-100">
                    View Full Details
                    <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>
        `;
        
        detailsContainer.innerHTML = html;
        
        // Load lived-here-card asynchronously
        if (placeId) {
            loadLivedHereCard(placeId);
        }
    }
    
    // Function to update places list
    function updatePlacesList(places) {
        const listContainer = document.getElementById('placesList');
        const currentPlaceIds = new Set(places.map(p => p.id));
        const existingPlaceIds = new Set(listItems.keys());
        
        // Remove list items for places no longer in view
        existingPlaceIds.forEach(placeId => {
            if (!currentPlaceIds.has(placeId)) {
                const listItem = listItems.get(placeId);
                if (listItem) {
                    listItem.remove();
                    listItems.delete(placeId);
                }
            }
        });
        
        // Clear and rebuild list
        listContainer.innerHTML = '';
        
        // Sort places by name
        const sortedPlaces = [...places].sort((a, b) => a.name.localeCompare(b.name));
        
        sortedPlaces.forEach(place => {
            const listItem = document.createElement('a');
            listItem.href = '#';
            listItem.className = 'list-group-item list-group-item-action';
            listItem.dataset.placeId = place.id;
            
            listItem.innerHTML = `
                <div class="d-flex w-100 justify-content-between">
                    <h6 class="mb-1">${escapeHtml(place.name)}</h6>
                </div>
                ${place.subtype ? `<p class="mb-1 small text-muted">${escapeHtml(place.subtype)}</p>` : ''}
                ${place.description ? `<p class="mb-0 small text-muted">${escapeHtml(place.description.substring(0, 80))}${place.description.length > 80 ? '...' : ''}</p>` : ''}
            `;
            
            listItem.addEventListener('click', function(e) {
                e.preventDefault();
                highlightPlace(place.id, place);
            });
            
            listContainer.appendChild(listItem);
            listItems.set(place.id, listItem);
        });
        
        // If a place was selected and it's still in the list, maintain selection
        // But don't re-highlight it (which would reset zoom) - just update the UI elements
        if (selectedPlaceId && currentPlaceIds.has(selectedPlaceId)) {
            const selectedPlace = places.find(p => p.id === selectedPlaceId);
            if (selectedPlace) {
                // Just update the marker and list item, don't call highlightPlace which would reset zoom
                selectedMarker = markers.get(selectedPlaceId);
                selectedListItem = listItems.get(selectedPlaceId);
                if (selectedMarker) {
                    selectedMarker.setIcon(selectedIcon);
                }
                if (selectedListItem) {
                    selectedListItem.classList.add('active');
                }
                // Don't call highlightPlace() here - it would reset zoom and pan
            }
        } else {
            // Clear selection if selected place is no longer visible
            selectedPlaceId = null;
            selectedMarker = null;
            selectedListItem = null;
        }
    }
    
    // Search input handler
    const searchInput = document.getElementById('placeSearch');
    if (searchInput) {
        searchInput.addEventListener('input', function(e) {
            applyFilters();
        });
    }
    
    // Track the last processed hash to avoid re-processing
    let lastProcessedHash = window.location.hash;
    // Track if we're updating hash ourselves to prevent loops
    let isUpdatingHash = false;
    
    // Function to select place from hash
    function selectPlaceFromHash() {
        console.log('[HASH] selectPlaceFromHash() called', {
            currentHash: window.location.hash,
            lastProcessedHash: lastProcessedHash,
            isUpdatingHash: isUpdatingHash,
            selectedPlaceId: selectedPlaceId,
            stack: new Error().stack.split('\n').slice(1, 4).join(' -> ')
        });
        
        // Don't process if we're updating the hash ourselves
        if (isUpdatingHash) {
            console.log('[HASH] Skipping - we are updating hash ourselves');
            return false;
        }
        
        const hash = window.location.hash;
        
        // Only process if hash actually changed
        if (hash === lastProcessedHash) {
            console.log('[HASH] Skipping - hash unchanged');
            return false;
        }
        
        console.log('[HASH] Processing hash change:', hash, '->', lastProcessedHash);
        lastProcessedHash = hash;
        
        if (hash && hash.startsWith('#place-')) {
            const placeId = hash.substring(7); // Remove '#place-' prefix
            
            // Try to find the place in current places
            const place = currentPlaces.find(p => p.id === placeId);
            if (place) {
                console.log('[HASH] Selecting place from hash:', placeId, 'preserving zoom');
                // When selecting from hash (browser navigation), preserve current zoom level
                // and don't update hash again to prevent loops
                highlightPlace(placeId, place, { preserveZoom: true, updateHash: false });
                return true;
            } else {
                console.log('[HASH] Place not found in current places:', placeId);
            }
        } else if (!hash || hash === '') {
            console.log('[HASH] Hash cleared, deselecting');
            // Hash was cleared - deselect current place (but don't clear hash again to prevent loop)
            if (selectedPlaceId) {
                if (selectedMarker) {
                    selectedMarker.setIcon(defaultIcon);
                }
                if (selectedListItem) {
                    selectedListItem.classList.remove('active');
                }
                selectedPlaceId = null;
                selectedMarker = null;
                selectedListItem = null;
                
                const rightColumn = document.getElementById('placeDetailsColumn');
                if (rightColumn) {
                    rightColumn.innerHTML = '<div class="p-3"><p class="text-muted">Select a place to view details</p></div>';
                }
            }
        }
        return false;
    }
    
    // Function to clear place selection and hash
    function clearPlaceSelection() {
        if (selectedMarker) {
            selectedMarker.setIcon(defaultIcon);
        }
        if (selectedListItem) {
            selectedListItem.classList.remove('active');
        }
        selectedPlaceId = null;
        selectedMarker = null;
        selectedListItem = null;
        
        // Clear the right column
        const rightColumn = document.getElementById('placeDetailsColumn');
        if (rightColumn) {
            rightColumn.innerHTML = '<div class="p-3"><p class="text-muted">Select a place to view details</p></div>';
        }
        
        // Clear hash without triggering hashchange
        lastProcessedHash = '';
        if (history.pushState) {
            history.pushState(null, null, window.location.pathname);
        } else {
            window.location.hash = '';
        }
    }
    
    // Handle hash changes (browser back/forward) - but only when hash actually changes
    window.addEventListener('hashchange', function(e) {
        console.log('[HASH] hashchange event fired', {
            oldURL: e.oldURL,
            newURL: e.newURL,
            currentHash: window.location.hash,
            isUpdatingHash: isUpdatingHash
        });
        // Only process if this is a real hash change (user navigation), not our own updates
        // This will preserve zoom when navigating via browser back/forward
        selectPlaceFromHash();
    });
    
    // Allow clicking on map background to clear selection
    let mapClickTimeout = null;
    map.on('click', function(e) {
        // Clear any pending timeout
        if (mapClickTimeout) {
            clearTimeout(mapClickTimeout);
        }
        
        // Check if click was on the map container itself (not on a marker or other layer)
        const target = e.originalEvent?.target;
        if (target && (
            target.classList.contains('leaflet-container') ||
            target.classList.contains('leaflet-pane') ||
            (target.tagName === 'DIV' && target.closest('.leaflet-container'))
        )) {
            // Small delay to ensure this isn't a click that will trigger a marker click
            mapClickTimeout = setTimeout(() => {
                clearPlaceSelection();
            }, 200);
        }
    });
    
    // Function to update places on the map
    function updatePlacesOnMap(places) {
        const currentPlaceIds = new Set(places.map(p => p.id));
        const existingPlaceIds = new Set(markers.keys());
        
        // Remove markers for places no longer in view
        existingPlaceIds.forEach(placeId => {
            if (!currentPlaceIds.has(placeId)) {
                const marker = markers.get(placeId);
                if (marker) {
                    map.removeLayer(marker);
                    markers.delete(placeId);
                }
                
                // Boundary removal removed - no longer tracking boundaries
            }
        });
        
        // Add or update markers for places in view
        places.forEach(place => {
            // Skip if already exists (to avoid duplicate markers)
            if (markers.has(place.id)) {
                return;
            }
            
            // Create marker with default icon
            const marker = L.marker([place.latitude, place.longitude], { icon: defaultIcon })
                .addTo(map)
                .on('click', function() {
                    highlightPlace(place.id, place);
                });
            
            markers.set(place.id, marker);
            
            // Boundary loading removed - no longer showing boundaries
        });
    }
    
    // Initialize subtype filters
    initializeSubtypeFilters();
    
    // Load places when map moves or zooms (but not during programmatic moves)
    map.on('moveend', function() {
        if (!isProgrammaticMove) {
            loadPlacesInBounds();
        }
    });
    map.on('zoomend', function() {
        if (!isProgrammaticMove) {
            loadPlacesInBounds();
        }
    });
    
    // Initial load
    loadPlacesInBounds();
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

/* Places list styles */
#placesListColumn {
    height: calc(100vh - 56px);
    overflow: hidden;
}

#placesList .list-group-item {
    cursor: pointer;
    border-left: 3px solid transparent;
}

#placesList .list-group-item:hover {
    background-color: #f8f9fa;
    border-left-color: #0d6efd;
}

#placesList .list-group-item.active {
    background-color: #e7f1ff;
    border-left-color: #0d6efd;
    font-weight: 500;
}

#placesList .list-group-item h6 {
    margin-bottom: 0.25rem;
    font-size: 0.95rem;
}

#placesList .list-group-item p {
    margin-bottom: 0.25rem;
}

/* Subtype filter styles */
.subtype-filter {
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
    text-transform: capitalize;
}

.subtype-filter.active {
    background-color: #0d6efd;
    border-color: #0d6efd;
    color: white;
}
</style>
@endsection

