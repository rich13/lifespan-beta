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
                <h6 class="card-title mb-0">
                    <i class="bi bi-geo-alt me-2"></i>
                    Location
                </h6>
                @auth
                    @if(auth()->user()->is_admin && $needsOsmData)
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
                                    
                                    console.log('Making API call to:', '/api/places/' + placeId + '/fetch-osm-data');
                                    const response = await fetch('/api/places/' + placeId + '/fetch-osm-data', {
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
            
            document.addEventListener('DOMContentLoaded', function() {
                
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
    @endif
@endif
