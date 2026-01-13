@props(['span'])

@php
    // Get located connections where this span is the subject (parent)
    $locatedConnections = $span->connectionsAsSubject()
        ->where('type_id', 'located')
        ->with(['child', 'connectionSpan'])
        ->get();
    
    // Determine what to show
    $isPlaceSpan = $span->type_id === 'place';
    $hasCoordinates = $span->getCoordinates();
    $hasOsmData = $span->getOsmData();
    $hasLocatedConnections = $locatedConnections->isNotEmpty();
    
    // Check if we should show the card
    $shouldShowCard = $isPlaceSpan || $hasLocatedConnections;
    
    // Determine the primary location for the map
    $primaryLocation = null;
    $mapHeight = 200; // Default height
    
    // Find nearby places if this is a place span with coordinates (load but hide by default)
    $nearbyPlaces = [];
    if ($isPlaceSpan && $hasCoordinates) {
        // If this span is a place with coordinates, use it as the primary location
        $primaryLocation = $span;
        // Keep default map height - will expand when nearby places are toggled on
        
        // Calculate appropriate radius based on place type (smaller for buildings, larger for cities)
        $searchRadius = $span->getRadiusForNearbyPlaces();
        
        // Find nearby places within calculated radius, limit to 20 places (loaded but hidden by default)
        $nearbyPlaces = $span->findNearbyPlaces($searchRadius, 20);
    } elseif ($hasLocatedConnections) {
        // Otherwise, use the first connected location with coordinates
        foreach($locatedConnections as $connection) {
            $locationSpan = $connection->child;
            if($locationSpan->type_id === 'place' && $locationSpan->getCoordinates()) {
                $primaryLocation = $locationSpan;
                break;
            }
        }
    }
    
    // Check if any places need OSM data
    $needsOsmData = false;
    $placesNeedingData = [];
    
    if ($isPlaceSpan && !$hasOsmData) {
        $needsOsmData = true;
        $placesNeedingData[] = $span;
    }
    
    foreach($locatedConnections as $connection) {
        $locationSpan = $connection->child;
        if($locationSpan->type_id === 'place' && !$locationSpan->getOsmData()) {
            $needsOsmData = true;
            $placesNeedingData[] = $locationSpan;
        }
    }
@endphp

@if($shouldShowCard)
    <div class="card mb-4">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <h6 class="card-title mb-0">
                    <i class="bi bi-geo-alt me-2"></i>
                    Location
                </h6>
                @if($isPlaceSpan && $hasCoordinates && !empty($nearbyPlaces))
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="show-nearby-{{ $span->id }}" 
                               onchange="toggleNearbyPlaces('{{ $span->id }}', this.checked)">
                        <label class="form-check-label small" for="show-nearby-{{ $span->id }}">
                            Show nearby
                        </label>
                    </div>
                @endif
                @auth
                    @if(auth()->user()->getEffectiveAdminStatus() && $needsOsmData)
                        <button type="button" class="btn btn-sm btn-outline-primary" id="getMapDataBtn" title="Fetch OSM data for places without map data" onclick="
                            console.log('Button clicked directly!');
                            const button = this;
                            const originalText = button.innerHTML;
                            button.innerHTML = '<span class=&quot;spinner-border spinner-border-sm me-1&quot; role=&quot;status&quot; aria-hidden=&quot;true&quot;></span>Fetching...';
                            button.disabled = true;
                            
                            // Get places needing data
                            const placesNeedingData = [];
                            @if($isPlaceSpan && !$hasOsmData)
                                placesNeedingData.push('{{ $span->id }}');
                            @endif
                            @foreach($locatedConnections as $connection)
                                @php $locationSpan = $connection->child; @endphp
                                @if($locationSpan->type_id === 'place' && !$locationSpan->getOsmData())
                                    placesNeedingData.push('{{ $locationSpan->id }}');
                                @endif
                            @endforeach
                            
                            console.log('Places needing data:', placesNeedingData);
                            console.log('Current span ID:', '{{ $span->id }}');
                            console.log('Current span slug:', '{{ $span->slug }}');
                            
                            if (placesNeedingData.length === 0) {
                                button.innerHTML = '<i class=&quot;bi bi-info-circle me-1&quot;></i>No data needed';
                                setTimeout(() => {
                                    button.innerHTML = originalText;
                                    button.disabled = false;
                                }, 2000);
                                return;
                            }
                            
                            // Fetch OSM data for each place
                            let successCount = 0;
                            let errorCount = 0;
                            
                            placesNeedingData.forEach(async (placeId) => {
                                try {
                                    const csrfToken = document.querySelector('meta[name=&quot;csrf-token&quot;]');
                                    const headers = {
                                        'Content-Type': 'application/json',
                                        'Accept': 'application/json'
                                    };
                                    
                                    if (csrfToken) {
                                        headers['X-CSRF-TOKEN'] = csrfToken.getAttribute('content');
                                    }
                                    
                                    console.log('Making API call to:', '/admin/places/' + placeId + '/fetch-osm-data');
                                    const response = await fetch('/admin/places/' + placeId + '/fetch-osm-data', {
                                        method: 'POST',
                                        headers: headers,
                                        credentials: 'same-origin'
                                    });
                                    console.log('API response status:', response.status);
                                    
                                    if (response.ok) {
                                        const result = await response.json();
                                        if (result.success) {
                                            successCount++;
                                        } else {
                                            errorCount++;
                                        }
                                    } else {
                                        errorCount++;
                                    }
                                } catch (error) {
                                    errorCount++;
                                    console.error('Error:', error);
                                }
                                
                                // Check if all requests are done
                                if (successCount + errorCount === placesNeedingData.length) {
                                    if (successCount > 0) {
                                        button.innerHTML = '<i class=&quot;bi bi-check-circle me-1&quot;></i>Success!';
                                        setTimeout(() => {
                                            const redirectUrl = '/spans/{{ $span->id }}?t=' + Date.now();
                                            console.log('Redirecting to:', redirectUrl);
                                            window.location.href = redirectUrl;
                                        }, 1000);
                                    } else {
                                        button.innerHTML = '<i class=&quot;bi bi-exclamation-triangle me-1&quot;></i>Failed';
                                        setTimeout(() => {
                                            button.innerHTML = originalText;
                                            button.disabled = false;
                                        }, 2000);
                                    }
                                }
                            });
                        ">
                            <i class="bi bi-download me-1"></i>Get Map Data
                        </button>
                    @endif
                @endauth
            </div>
        </div>
        <div class="card-body">
            @if($primaryLocation)
                <!-- Map Container -->
                <div id="location-map-{{ $span->id }}" class="mb-3" style="height: {{ $mapHeight }}px; width: 100%; border-radius: 0.375rem;"></div>
            @endif
            
            @if($isPlaceSpan && $hasOsmData && !empty($hasOsmData['hierarchy']))
                <!-- OSM Hierarchy Badges for Place Spans -->
                <div class="mt-3">
                    <div class="d-flex flex-wrap gap-1">
                        @foreach($hasOsmData['hierarchy'] as $level)
                            @php
                                // Check if this place exists in our system
                                $existingPlace = \App\Models\Span::where('name', $level['name'])
                                    ->where('type_id', 'place')
                                    ->first();
                            @endphp
                            
                            @if($existingPlace && $existingPlace->id !== $span->id)
                                <a href="{{ route('spans.show', $existingPlace) }}" 
                                   class="badge bg-primary text-decoration-none"
                                   title="Click to view {{ $level['name'] }}">
                                    {{ $level['name'] }}
                                </a>
                            @elseif($existingPlace && $existingPlace->id === $span->id)
                                <span class="badge bg-success" title="Current place">
                                    {{ $level['name'] }}
                                </span>
                            @else
                                <span class="badge bg-secondary" title="Place not in system">
                                    {{ $level['name'] }}
                                </span>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endif
            
            @if(!empty($nearbyPlaces))
                <!-- Nearby Places Section (hidden by default) -->
                <div class="mt-3" id="nearby-places-{{ $span->id }}" style="display: none;">
                    <h6 class="mb-2">
                        <i class="bi bi-geo-alt-fill me-2"></i>
                        Nearby Places
                    </h6>
                    <div class="list-group">
                        @foreach($nearbyPlaces as $placeData)
                            @php
                                $nearbyPlace = $placeData['span'];
                                $distance = $placeData['distance'];
                                $distanceText = $distance < 1 
                                    ? round($distance * 1000) . 'm away' 
                                    : number_format($distance, 1) . 'km away';
                            @endphp
                            <a href="{{ route('spans.show', $nearbyPlace) }}" 
                               class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                <div>
                                    <strong>{{ $nearbyPlace->name }}</strong>
                                    <br>
                                    <small class="text-muted">{{ $distanceText }}</small>
                                </div>
                                <i class="bi bi-arrow-right"></i>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
            
            @if($hasLocatedConnections)
                <!-- Connected Location Details -->
                @foreach($locatedConnections as $connection)
                    @php
                        $locationSpan = $connection->child;
                        $connectionSpan = $connection->connectionSpan;
                    @endphp
                    
                    <div class="d-flex align-items-start mb-3">
                        <div class="flex-grow-1">
                            <h6 class="mb-1">
                                <a href="{{ route('spans.show', $locationSpan) }}" 
                                   class="text-decoration-none">
                                    {{ $locationSpan->name }}
                                </a>
                            </h6>
                            
                            @if($connectionSpan && $connectionSpan->start_year)
                                <small class="text-muted">
                                    @if($connectionSpan->start_year && $connectionSpan->end_year)
                                        {{ $connectionSpan->start_year }} - {{ $connectionSpan->end_year }}
                                    @elseif($connectionSpan->start_year)
                                        Since {{ $connectionSpan->start_year }}
                                    @endif
                                </small>
                            @endif
                        </div>
                        
                        @if($locationSpan->type_id === 'place' && $locationSpan->getCoordinates())
                            <div class="ms-2">
                                <a href="https://www.openstreetmap.org/?mlat={{ $locationSpan->getCoordinates()['latitude'] }}&mlon={{ $locationSpan->getCoordinates()['longitude'] }}&zoom=12" 
                                   target="_blank" 
                                   class="btn btn-sm btn-outline-primary"
                                   title="View on OpenStreetMap">
                                    <i class="bi bi-map"></i>
                                </a>
                            </div>
                        @endif
                    </div>
                    
                    @if($connectionSpan && $connectionSpan->description)
                        <p class="text-muted small mb-3">
                            {{ $connectionSpan->description }}
                        </p>
                    @endif
                @endforeach
            @endif
        </div>
    </div>
    
    @if($primaryLocation)
        @push('styles')
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
        @endpush

        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
        <script>
            // Store map and markers in window scope for toggle function
            window.locationMapData = window.locationMapData || {};
            
            document.addEventListener('DOMContentLoaded', function() {
                const mapId = '{{ $span->id }}';
                
                // Initialize map centered on primary location
                const map = L.map('location-map-' + mapId).setView([
                    {{ $primaryLocation->getCoordinates()['latitude'] }}, 
                    {{ $primaryLocation->getCoordinates()['longitude'] }}
                ], 13); // Higher zoom level (13) to focus on the place itself

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '© OpenStreetMap contributors'
                }).addTo(map);

                // Add marker for the primary location (current place)
                const primaryMarker = L.marker([
                    {{ $primaryLocation->getCoordinates()['latitude'] }}, 
                    {{ $primaryLocation->getCoordinates()['longitude'] }}
                ], {
                    icon: L.icon({
                        iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-red.png',
                        shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
                        iconSize: [25, 41],
                        iconAnchor: [12, 41],
                        popupAnchor: [1, -34],
                        shadowSize: [41, 41]
                    })
                }).addTo(map)
                  .bindPopup('<strong>{{ $primaryLocation->name }}</strong>')
                  .openPopup();

                // Create markers for nearby places (but don't add to map by default)
                const nearbyPlaces = @json($nearbyPlaces);
                const nearbyMarkers = [];
                
                nearbyPlaces.forEach(function(placeData) {
                    const place = placeData.span;
                    const distance = placeData.distance;
                    
                    // Get coordinates from the place's metadata
                    const coords = place.metadata && place.metadata.coordinates 
                        ? place.metadata.coordinates 
                        : null;
                    
                    if (coords && coords.latitude && coords.longitude) {
                        const marker = L.marker([
                            parseFloat(coords.latitude),
                            parseFloat(coords.longitude)
                        ]); // Don't add to map yet
                        
                        // Format distance
                        let distanceText = '';
                        if (distance < 1) {
                            distanceText = Math.round(distance * 1000) + 'm away';
                        } else {
                            distanceText = parseFloat(distance).toFixed(1) + 'km away';
                        }
                        
                        // Create popup with link to the place
                        const popupContent = `
                            <div>
                                <strong><a href="/spans/${place.slug}" class="text-decoration-none">${place.name}</a></strong><br>
                                <small class="text-muted">${distanceText}</small>
                            </div>
                        `;
                        
                        marker.bindPopup(popupContent);
                        nearbyMarkers.push(marker);
                    }
                });
                
                // Store map, markers, and coordinates for toggle function
                window.locationMapData[mapId] = {
                    map: map,
                    primaryMarker: primaryMarker,
                    nearbyMarkers: nearbyMarkers,
                    primaryCoords: {
                        lat: {{ $primaryLocation->getCoordinates()['latitude'] }},
                        lng: {{ $primaryLocation->getCoordinates()['longitude'] }}
                    }
                };
                
                // By default, just show the primary location (no nearby markers)

                // Handle boundary display for larger administrative areas
                @php
                    $boundaryPlaceTypes = ['country', 'state', 'region', 'province', 'administrative', 'city'];
                @endphp
                @if($primaryLocation->getOsmData() && in_array($primaryLocation->getOsmData()['place_type'], $boundaryPlaceTypes))
                    $.ajax({
                        url: '{{ route('places.boundary', $primaryLocation) }}',
                        method: 'GET',
                        dataType: 'json'
                    }).done(function(response) {
                        if (response.success && response.geojson) {
                            try {
                                const boundaryLayer = L.geoJSON(response.geojson, {
                                    style: {
                                        color: '#007bff',
                                        weight: 2,
                                        fillColor: '#007bff',
                                        fillOpacity: 0.1
                                    }
                                }).addTo(map);

                                map.fitBounds(boundaryLayer.getBounds());
                                if (boundaryLayer.getLayers().length > 0) {
                                    boundaryLayer.getLayers()[0].bindPopup('{{ $primaryLocation->name }} boundary');
                                }
                            } catch (e) {
                                console.log('Error rendering boundary geojson:', e);
                                if (nearbyMarkers.length === 0) {
                                    primaryMarker.openPopup();
                                }
                            }
                        } else if (nearbyMarkers.length === 0) {
                            primaryMarker.openPopup();
                        }
                    }).fail(function(error) {
                        console.log('Boundary request failed:', error);
                        if (nearbyMarkers.length === 0) {
                            primaryMarker.openPopup();
                        }
                    });
                @else
                    // No boundary, already showing primary marker
                @endif
            });
            
            // Toggle function to show/hide nearby places (global function)
            window.toggleNearbyPlaces = function(mapId, show) {
                const mapData = window.locationMapData[mapId];
                if (!mapData) return;
                
                const { map, primaryMarker, nearbyMarkers, primaryCoords } = mapData;
                const nearbyPlacesSection = document.getElementById('nearby-places-' + mapId);
                const mapContainer = document.getElementById('location-map-' + mapId);
                
                if (show) {
                    // Show nearby places - add markers to map and fit bounds
                    nearbyMarkers.forEach(function(marker) {
                        marker.addTo(map);
                    });
                    
                    // Fit map to show all markers
                    if (nearbyMarkers.length > 0) {
                        const group = new L.featureGroup([primaryMarker, ...nearbyMarkers]);
                        map.fitBounds(group.getBounds().pad(0.1));
                    }
                    
                    // Show nearby places list
                    if (nearbyPlacesSection) {
                        nearbyPlacesSection.style.display = 'block';
                    }
                    
                    // Increase map height
                    if (mapContainer) {
                        mapContainer.style.height = '400px';
                        setTimeout(function() {
                            map.invalidateSize();
                        }, 100);
                    }
                } else {
                    // Hide nearby places - remove markers from map
                    nearbyMarkers.forEach(function(marker) {
                        map.removeLayer(marker);
                    });
                    
                    // Center map on primary location
                    map.setView([primaryCoords.lat, primaryCoords.lng], 13);
                    
                    // Hide nearby places list
                    if (nearbyPlacesSection) {
                        nearbyPlacesSection.style.display = 'none';
                    }
                    
                    // Reset map height
                    if (mapContainer) {
                        mapContainer.style.height = '200px';
                        setTimeout(function() {
                            map.invalidateSize();
                        }, 100);
                    }
                }
            };
        </script>
    @endif
@endif
