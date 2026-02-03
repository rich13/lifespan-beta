@props(['photo'])

@php
    // Collect available EXIF data
    $exifData = [];
    $reverseGeocodedLocation = null;
    $nearbyPlaces = collect();
    $photoLatitude = null;
    $photoLongitude = null;
    
    if ($photo->metadata['camera_make'] ?? null) {
        $exifData['Camera Make'] = $photo->metadata['camera_make'];
    }
    
    if ($photo->metadata['camera_model'] ?? null) {
        $exifData['Camera Model'] = $photo->metadata['camera_model'];
    }
    
    if ($photo->metadata['software'] ?? null) {
        $exifData['Software'] = $photo->metadata['software'];
    }
    
    if ($photo->metadata['date_taken'] ?? null) {
        $exifData['Date Taken'] = \Carbon\Carbon::parse($photo->metadata['date_taken'])->format('M d, Y H:i');
    }
    
    if ($photo->metadata['coordinates'] ?? null) {
        $exifData['Coordinates'] = $photo->metadata['coordinates'];
        
        // Try to reverse geocode the coordinates
        $coordString = $photo->metadata['coordinates'];
        // Parse coordinates like "51.5074, -0.1278" or "51.5074,-0.1278"
        if (preg_match('/^(-?[\d.]+)\s*,\s*(-?[\d.]+)$/', trim($coordString), $matches)) {
            $photoLatitude = (float) $matches[1];
            $photoLongitude = (float) $matches[2];
            
            try {
                $osmService = app(\App\Services\OSMGeocodingService::class);
                $reverseGeocodedLocation = $osmService->reverseGeocode($photoLatitude, $photoLongitude);
            } catch (\Exception $e) {
                // Silently fail - don't break the page if geocoding fails
            }
            
            // Find nearby place spans that already exist in the database
            try {
                $locationService = app(\App\Services\PlaceLocationService::class);
                // Find places at this location (containing the point or very close)
                $nearbyPlaces = $locationService->findPlacesAtLocation($photoLatitude, $photoLongitude, 50.0, 10, 1.0);
                
                // Exclude places that are already connected to this photo via located
                $existingLocationIds = $photo->connectionsAsSubject()
                    ->where('type_id', 'located')
                    ->pluck('child_id')
                    ->toArray();
                
                $nearbyPlaces = $nearbyPlaces->reject(function ($place) use ($existingLocationIds) {
                    return in_array($place->id, $existingLocationIds);
                });
            } catch (\Exception $e) {
                // Silently fail
            }
        }
    }
    
    if ($photo->metadata['coordinate_source'] ?? null) {
        $exifData['Coordinate Source'] = $photo->metadata['coordinate_source'];
    }
    
    if ($photo->metadata['image_description'] ?? null) {
        $exifData['Description'] = $photo->metadata['image_description'];
    }
@endphp

@if($exifData)
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0">
                <i class="bi bi-camera me-2"></i>EXIF Data
            </h5>
        </div>
        <div class="card-body">
            <dl class="row mb-0">
                @foreach($exifData as $label => $value)
                    <dt class="col-sm-5 text-truncate" title="{{ $label }}">{{ $label }}:</dt>
                    <dd class="col-sm-7">
                        @if($label === 'Coordinates')
                            <a href="https://maps.google.com/?q={{ urlencode($value) }}" 
                               target="_blank" 
                               class="text-decoration-none">
                                {{ $value }}
                                <i class="bi bi-box-arrow-up-right ms-1" style="font-size: 0.75rem;"></i>
                            </a>
                            @if($reverseGeocodedLocation)
                                <div class="mt-2 small">
                                    <i class="bi bi-geo-alt text-info me-1"></i>
                                    <span class="text-muted">{{ $reverseGeocodedLocation['display_name'] }}</span>
                                    @can('update', $photo)
                                        <button type="button" 
                                                class="btn btn-sm btn-outline-primary ms-2 create-place-from-coords-btn"
                                                data-photo-id="{{ $photo->id }}"
                                                data-csrf="{{ csrf_token() }}"
                                                title="Create a place span from these coordinates and link it to this photo">
                                            <i class="bi bi-plus-circle me-1"></i>Create Place
                                        </button>
                                    @endcan
                                </div>
                            @endif
                        @else
                            <span class="text-break">{{ $value }}</span>
                        @endif
                    </dd>
                @endforeach
            </dl>
            
            @if($nearbyPlaces->isNotEmpty())
                <hr class="my-3">
                <div>
                    <strong class="small text-muted d-block mb-2">
                        <i class="bi bi-pin-map me-1"></i>Nearby Places in Database
                    </strong>
                    <div class="d-flex flex-wrap gap-1">
                        @foreach($nearbyPlaces->take(8) as $place)
                            @php
                                $placeLevel = $place->metadata['osm_data']['admin_level'] ?? null;
                                $levelLabel = $placeLevel ? "Level {$placeLevel}" : '';
                            @endphp
                            @can('update', $photo)
                                <button type="button"
                                        class="btn btn-sm btn-outline-info link-place-btn"
                                        data-photo-id="{{ $photo->id }}"
                                        data-place-id="{{ $place->id }}"
                                        data-place-name="{{ $place->name }}"
                                        data-csrf="{{ csrf_token() }}"
                                        title="Link this photo to {{ $place->name }}">
                                    <i class="bi bi-plus me-1"></i>{{ Str::limit($place->name, 20) }}
                                </button>
                            @else
                                <a href="{{ route('spans.show', $place) }}"
                                   class="badge bg-info text-decoration-none"
                                   title="{{ $place->name }}{{ $levelLabel ? ' (' . $levelLabel . ')' : '' }}">
                                    <i class="bi bi-geo-alt me-1"></i>{{ Str::limit($place->name, 20) }}
                                </a>
                            @endcan
                        @endforeach
                        @if($nearbyPlaces->count() > 8)
                            <span class="badge bg-secondary">+{{ $nearbyPlaces->count() - 8 }} more</span>
                        @endif
                    </div>
                    @can('update', $photo)
                        <div class="mt-2 small text-muted">
                            <i class="bi bi-info-circle me-1"></i>
                            Click + to link this photo to a place.
                        </div>
                    @endcan
                </div>
            @endif
        </div>
    </div>
@endif

@push('scripts')
<script>
$(document).ready(function() {
    $('.link-place-btn').on('click', function() {
        var $btn = $(this);
        var photoId = $btn.data('photo-id');
        var placeId = $btn.data('place-id');
        var placeName = $btn.data('place-name');
        var csrf = $btn.data('csrf');
        var originalHtml = $btn.html();

        $btn.prop('disabled', true).html('<i class="bi bi-hourglass-split me-1"></i>Linking...');

        $.ajax({
            url: '/api/photos/' + photoId + '/link-place/' + placeId,
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            },
            success: function(response) {
                if (response.success) {
                    $btn.removeClass('btn-outline-info').addClass('btn-success')
                        .html('<i class="bi bi-check-circle me-1"></i>Linked!');
                    setTimeout(function() { location.reload(); }, 1000);
                } else {
                    $btn.removeClass('btn-outline-info').addClass('btn-warning')
                        .html('<i class="bi bi-exclamation-triangle me-1"></i>' + response.message);
                    setTimeout(function() {
                        $btn.removeClass('btn-warning').addClass('btn-outline-info')
                            .prop('disabled', false).html(originalHtml);
                    }, 3000);
                }
            },
            error: function(xhr) {
                var message = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Error linking place';
                $btn.removeClass('btn-outline-info').addClass('btn-danger')
                    .html('<i class="bi bi-x-circle me-1"></i>' + message);
                setTimeout(function() {
                    $btn.removeClass('btn-danger').addClass('btn-outline-info')
                        .prop('disabled', false).html(originalHtml);
                }, 3000);
            }
        });
    });

    $('.create-place-from-coords-btn').on('click', function() {
        var $btn = $(this);
        var photoId = $btn.data('photo-id');
        var csrf = $btn.data('csrf');
        var originalHtml = $btn.html();
        
        // Disable button and show loading state
        $btn.prop('disabled', true).html('<i class="bi bi-hourglass-split me-1"></i>Creating...');
        
        $.ajax({
            url: '/api/photos/' + photoId + '/create-place-from-coordinates',
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            },
            success: function(response) {
                if (response.success) {
                    // Show success message
                    $btn.removeClass('btn-outline-primary').addClass('btn-success')
                        .html('<i class="bi bi-check-circle me-1"></i>' + (response.was_existing ? 'Linked!' : 'Created!'));
                    
                    // Add a link to the created/linked place
                    var placeLink = $('<a>')
                        .attr('href', response.place.url)
                        .addClass('badge bg-success text-decoration-none ms-2')
                        .html('<i class="bi bi-geo-alt me-1"></i>' + response.place.name);
                    $btn.after(placeLink);
                    
                    // Optionally reload the page after a short delay to update the UI
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    // Show error
                    $btn.removeClass('btn-outline-primary').addClass('btn-warning')
                        .html('<i class="bi bi-exclamation-triangle me-1"></i>' + response.message);
                    
                    // Reset after delay
                    setTimeout(function() {
                        $btn.removeClass('btn-warning').addClass('btn-outline-primary')
                            .prop('disabled', false).html(originalHtml);
                    }, 3000);
                }
            },
            error: function(xhr) {
                var message = 'Error creating place';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                }
                
                $btn.removeClass('btn-outline-primary').addClass('btn-danger')
                    .html('<i class="bi bi-x-circle me-1"></i>' + message);
                
                // Reset after delay
                setTimeout(function() {
                    $btn.removeClass('btn-danger').addClass('btn-outline-primary')
                        .prop('disabled', false).html(originalHtml);
                }, 3000);
            }
        });
    });
});
</script>
@endpush





