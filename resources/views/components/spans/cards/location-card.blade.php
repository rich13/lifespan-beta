@props(['span'])

@php
    // Get located connections where this span is the subject (parent)
    $locatedConnections = $span->connectionsAsSubject()
        ->where('type_id', 'located')
        ->with(['child', 'connectionSpan'])
        ->get();
    
    // Check if any location has coordinates for map display
    $hasMapLocation = false;
    $mapLocation = null;
    foreach($locatedConnections as $connection) {
        $locationSpan = $connection->child;
        if($locationSpan->type_id === 'place' && $locationSpan->getCoordinates()) {
            $hasMapLocation = true;
            $mapLocation = $locationSpan;
            break; // Use the first location with coordinates
        }
    }
@endphp

@if($locatedConnections->isNotEmpty())
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0">
                <i class="bi bi-geo-alt me-2"></i>
                Location
            </h5>
        </div>
        <div class="card-body">
            @if($hasMapLocation && $mapLocation)
                <!-- Map Container -->
                <div id="location-map-{{ $span->id }}" class="mb-3" style="height: 200px; width: 100%; border-radius: 0.375rem;"></div>
            @endif
            
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
        </div>
    </div>
    
    @if($hasMapLocation && $mapLocation)
        @push('styles')
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
        @endpush

        @push('scripts')
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const map = L.map('location-map-{{ $span->id }}').setView([
                    {{ $mapLocation->getCoordinates()['latitude'] }}, 
                    {{ $mapLocation->getCoordinates()['longitude'] }}
                ], 10);

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '© OpenStreetMap contributors'
                }).addTo(map);

                // Add marker for the location
                const marker = L.marker([
                    {{ $mapLocation->getCoordinates()['latitude'] }}, 
                    {{ $mapLocation->getCoordinates()['longitude'] }}
                ]).addTo(map)
                  .bindPopup('{{ $mapLocation->name }}');

                @if($mapLocation->getOsmData() && in_array($mapLocation->getOsmData()['place_type'], ['country', 'state', 'region', 'province', 'administrative']))
                    // For administrative areas, try to fetch and display the boundary
                    const osmId = {{ $mapLocation->getOsmData()['osm_id'] }};
                    const osmType = '{{ $mapLocation->getOsmData()['osm_type'] }}';
                    
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
                                    
                                    // Fit map to polygon bounds
                                    map.fitBounds(polygon.getBounds());
                                }
                            }
                        })
                        .catch(error => {
                            console.error('Error fetching boundary:', error);
                        });
                @endif
            });
        </script>
        @endpush
    @endif
@endif
