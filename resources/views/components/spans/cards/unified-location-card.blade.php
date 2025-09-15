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
    
    if ($isPlaceSpan && $hasCoordinates) {
        // If this span is a place with coordinates, use it as the primary location
        $primaryLocation = $span;
        $mapHeight = 300; // Larger map for place spans
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
                <h5 class="card-title mb-0">
                    <i class="bi bi-geo-alt me-2"></i>
                    Location
                </h5>
                @auth
                    @if(auth()->user()->is_admin && $needsOsmData)
                        <button type="button" class="btn btn-sm btn-outline-primary" id="getMapDataBtn" title="Fetch OSM data for places without map data">
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

        @push('scripts')
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Handle Get Map Data button click
                const getMapDataBtn = document.getElementById('getMapDataBtn');
                console.log('Get Map Data button found:', getMapDataBtn);
                if (getMapDataBtn) {
                    console.log('Adding click event listener to Get Map Data button');
                    getMapDataBtn.addEventListener('click', async function() {
                        console.log('Get Map Data button clicked!');
                        const originalText = this.innerHTML;
                        this.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Fetching...';
                        this.disabled = true;
                        
                        try {
                            // Get all places that need OSM data
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
                            
                            console.log('Places needing OSM data:', placesNeedingData);
                            
                            if (placesNeedingData.length === 0) {
                                console.log('No places need OSM data');
                                this.innerHTML = '<i class="bi bi-info-circle me-1"></i>No data needed';
                                setTimeout(() => {
                                    this.innerHTML = originalText;
                                    this.disabled = false;
                                }, 2000);
                                return;
                            }
                            
                            let successCount = 0;
                            let errorCount = 0;
                            
                            // Fetch OSM data for each place
                            for (const placeId of placesNeedingData) {
                                try {
                                    console.log('Fetching OSM data for place:', placeId);
                                    
                                    const csrfToken = document.querySelector('meta[name="csrf-token"]');
                                    const headers = {
                                        'Content-Type': 'application/json',
                                        'Accept': 'application/json'
                                    };
                                    
                                    if (csrfToken) {
                                        headers['X-CSRF-TOKEN'] = csrfToken.getAttribute('content');
                                    }
                                    
                                    const response = await fetch(`/api/places/${placeId}/fetch-osm-data`, {
                                        method: 'POST',
                                        headers: headers
                                    });
                                    
                                    console.log('Response status:', response.status);
                                    
                                    if (!response.ok) {
                                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                                    }
                                    
                                    const result = await response.json();
                                    console.log('API result:', result);
                                    
                                    if (result.success) {
                                        successCount++;
                                        console.log('Successfully fetched OSM data for place:', placeId);
                                    } else {
                                        errorCount++;
                                        console.error('Failed to fetch OSM data for place', placeId, result.message);
                                    }
                                } catch (error) {
                                    errorCount++;
                                    console.error('Error fetching OSM data for place', placeId, error);
                                }
                            }
                            
                            if (successCount > 0) {
                                // Show success message and redirect to UUID (which will redirect to new slug)
                                this.innerHTML = '<i class="bi bi-check-circle me-1"></i>Success!';
                                setTimeout(() => {
                                    window.location.href = '{{ route("spans.show", $span->id) }}';
                                }, 1000);
                            } else {
                                // Show error message
                                this.innerHTML = '<i class="bi bi-exclamation-triangle me-1"></i>Failed';
                                setTimeout(() => {
                                    this.innerHTML = originalText;
                                    this.disabled = false;
                                }, 2000);
                            }
                            
                        } catch (error) {
                            console.error('Error in getMapData:', error);
                            this.innerHTML = '<i class="bi bi-exclamation-triangle me-1"></i>Error';
                            setTimeout(() => {
                                this.innerHTML = originalText;
                                this.disabled = false;
                            }, 2000);
                        }
                    });
                }
                
                // Initialize map
                const map = L.map('location-map-{{ $span->id }}').setView([
                    {{ $primaryLocation->getCoordinates()['latitude'] }}, 
                    {{ $primaryLocation->getCoordinates()['longitude'] }}
                ], 10);

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '© OpenStreetMap contributors'
                }).addTo(map);

                // Add marker for the location
                const marker = L.marker([
                    {{ $primaryLocation->getCoordinates()['latitude'] }}, 
                    {{ $primaryLocation->getCoordinates()['longitude'] }}
                ]).addTo(map)
                  .bindPopup('{{ $primaryLocation->name }}');

                @if($primaryLocation->getOsmData() && in_array($primaryLocation->getOsmData()['place_type'], ['country', 'state', 'region', 'province', 'administrative']))
                    // For administrative areas, try to fetch and display the boundary
                    const osmId = {{ $primaryLocation->getOsmData()['osm_id'] }};
                    const osmType = '{{ $primaryLocation->getOsmData()['osm_type'] }}';
                    
                    // Fetch boundary data from OSM Overpass API
                    console.log('Fetching boundary for:', osmType, osmId);
                    fetch(`https://overpass-api.de/api/interpreter?data=[out:json];${osmType}(${osmId});out geom;`)
                        .then(response => response.json())
                        .then(data => {
                            console.log('Overpass response:', data);
                            if (data.elements && data.elements.length > 0) {
                                const element = data.elements[0];
                                
                                if (element.geometry && element.geometry.length > 0) {
                                    // Create a polygon from the geometry
                                    const coordinates = element.geometry.map(point => [point.lat, point.lon]);
                                    console.log('Boundary coordinates:', coordinates.length, 'points');
                                    
                                    const polygon = L.polygon(coordinates, {
                                        color: '#007bff',
                                        weight: 2,
                                        fillColor: '#007bff',
                                        fillOpacity: 0.1
                                    }).addTo(map);
                                    
                                    // Fit map to show the entire boundary
                                    map.fitBounds(polygon.getBounds());
                                    
                                    // Add popup to the polygon
                                    polygon.bindPopup('{{ $primaryLocation->name }} boundary');
                                } else {
                                    console.log('No geometry found in element');
                                    marker.openPopup();
                                }
                            } else {
                                console.log('No elements found in response');
                                marker.openPopup();
                            }
                        })
                        .catch(error => {
                            console.log('Could not fetch boundary data:', error);
                            // Fallback: just show the marker and open its popup
                            marker.openPopup();
                        });
                @else
                    // For smaller places, just show the marker
                    marker.openPopup();
                @endif
            });
        </script>
        @endpush
    @endif
@endif
