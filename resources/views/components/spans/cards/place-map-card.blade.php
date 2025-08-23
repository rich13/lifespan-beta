@props(['span'])

@if($span->type_id === 'place' && $span->getCoordinates())
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0">
                <i class="bi bi-geo-alt me-2"></i>
                Location
            </h5>
        </div>
        <div class="card-body">
            <!-- Map Container -->
            <div id="map-{{ $span->id }}" class="mb-3" style="height: 300px; width: 100%; border-radius: 0.375rem;"></div>
            
            @if($span->getOsmData() && !empty($span->getOsmData()['hierarchy']))
                <div class="mt-3">
                    <div class="d-flex flex-wrap gap-1">
                        @foreach($span->getOsmData()['hierarchy'] as $level)
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
            

        </div>
    </div>

    @push('styles')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    @endpush

    @push('scripts')
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const map = L.map('map-{{ $span->id }}').setView([
                {{ $span->getCoordinates()['latitude'] }}, 
                {{ $span->getCoordinates()['longitude'] }}
            ], 10);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors'
            }).addTo(map);

            // Add marker for the place
            const marker = L.marker([
                {{ $span->getCoordinates()['latitude'] }}, 
                {{ $span->getCoordinates()['longitude'] }}
            ]).addTo(map)
              .bindPopup('{{ $span->name }}');

            @if($span->getOsmData() && in_array($span->getOsmData()['place_type'], ['country', 'state', 'region', 'province', 'administrative']))
                // For administrative areas, try to fetch and display the boundary
                const osmId = {{ $span->getOsmData()['osm_id'] }};
                const osmType = '{{ $span->getOsmData()['osm_type'] }}';
                
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
                                polygon.bindPopup('{{ $span->name }} boundary');
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
