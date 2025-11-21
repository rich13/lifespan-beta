@props(['span'])

@php
    // Only show for person spans
    if ($span->type_id !== 'person') {
        return;
    }

    $residenceConnections = $span->connectionsAsSubject()
        ->whereHas('type', function($q) { $q->where('type', 'residence'); })
        ->with(['child', 'connectionSpan'])
        ->get()
        ->sortBy(function($conn) {
            // Use effective sort date helper to build a sortable key
            $parts = $conn->getEffectiveSortDate();
            // Normalise very large values to push unknowns to the end
            $y = $parts[0] ?? PHP_INT_MAX;
            $m = $parts[1] ?? PHP_INT_MAX;
            $d = $parts[2] ?? PHP_INT_MAX;
            return sprintf('%08d-%02d-%02d', $y, $m, $d);
        })
        ->values();
    
    // Don't show the card if there are no residences
    if ($residenceConnections->isEmpty()) {
        return;
    }
    
    // Prepare place data for map view
    $placesForMap = $residenceConnections->map(function($conn) {
        $place = $conn->child;
        $coords = $place->getCoordinates();
        $dates = $conn->connectionSpan;
        $dateText = null;
        if ($dates && ($dates->start_year || $dates->end_year)) {
            if ($dates->start_year && $dates->end_year) {
                $dateText = ($dates->formatted_start_date ?? $dates->start_year) . ' – ' . ($dates->formatted_end_date ?? $dates->end_year);
            } elseif ($dates->start_year) {
                $dateText = 'from ' . ($dates->formatted_start_date ?? $dates->start_year);
            } elseif ($dates->end_year) {
                $dateText = 'until ' . ($dates->formatted_end_date ?? $dates->end_year);
            }
        }
        return [
            'id' => $place->id,
            'name' => $place->name,
            'coordinates' => $coords,
            'dates' => $dateText,
            'url' => route('spans.show', $place)
        ];
    });
@endphp

<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="card-title mb-0">
            <i class="bi bi-house me-2"></i>
            <a href="{{ url('/spans/' . $span->id . '/lived-in') }}" class="text-decoration-none">
                Places Lived
            </a>
        </h6>
        <div class="d-flex gap-2 align-items-center">
            <div class="btn-group btn-group-sm" role="group" aria-label="View toggle">
                <button type="button" class="btn btn-outline-secondary" id="places-list-toggle">
                    <i class="bi bi-list-ul"></i> List
                </button>
                <button type="button" class="btn btn-outline-secondary active" id="places-map-toggle">
                    <i class="bi bi-map"></i> Map
                </button>
            </div>
            @auth
                @if(auth()->user()->can('update', $span))
                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#quickAddResidenceModal" data-person-id="{{ $span->id }}">
                        <i class="bi bi-plus-circle me-1"></i> Add
                    </button>
                @endif
            @endauth
        </div>
    </div>
    <div class="card-body p-2">
        <div id="places-list-view" class="list-group list-group-flush" style="display: none;">
            @foreach($residenceConnections as $connection)
                @php
                    $place = $connection->child;
                    $dates = $connection->connectionSpan;
                    $hasDates = $dates && ($dates->start_year || $dates->end_year);
                    $dateText = null;
                    if ($hasDates) {
                        if ($dates->start_year && $dates->end_year) {
                            $dateText = ($dates->formatted_start_date ?? $dates->start_year) . ' – ' . ($dates->formatted_end_date ?? $dates->end_year);
                        } elseif ($dates->start_year) {
                            $dateText = 'from ' . ($dates->formatted_start_date ?? $dates->start_year);
                        } elseif ($dates->end_year) {
                            $dateText = 'until ' . ($dates->formatted_end_date ?? $dates->end_year);
                        }
                    }
                @endphp
                <div class="list-group-item px-0 py-2 border-0 border-bottom">
                    <div class="d-flex align-items-center">
                        <!-- Place name and dates -->
                        <div class="flex-grow-1">
                            <a href="{{ route('spans.show', $place) }}" 
                               class="text-decoration-none fw-semibold">
                                {{ $place->name }}
                            </a>
                            @if($dateText)
                                <div class="text-muted small">
                                    <i class="bi bi-calendar me-1"></i>{{ $dateText }}
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        
        {{-- Map View (default view) --}}
        <div id="places-map-view" style="height: 400px; position: relative;">
            <div id="places-map-container" style="width: 100%; height: 100%;"></div>
        </div>
    </div>
</div>

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
@endpush

@push('modals')
<!-- Quick Add Residence Modal -->
<div class="modal fade" id="quickAddResidenceModal" tabindex="-1" aria-labelledby="quickAddResidenceLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="quickAddResidenceLabel">Add Residence</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="quickAddResidenceForm">
          <input type="hidden" name="person_id" value="{{ $span->id }}">
          <div class="mb-3 position-relative">
            <label class="form-label">Place</label>
            <input type="text" class="form-control" name="place_name" id="qaResPlaceInput" placeholder="City, town, or address" autocomplete="off" required>
            <input type="hidden" name="place_id" id="qaResPlaceId">
            <div id="qaResSearchResults" class="border bg-white position-absolute w-100" style="z-index: 1060; display: none;"></div>
            <div class="form-text">Search existing places or create a new one if none match.</div>
          </div>
          <div class="row g-3 mb-2">
            <div class="col">
              <label class="form-label">Start year (optional)</label>
              <input type="number" class="form-control" name="start_year" min="1800" max="2100" placeholder="e.g. 1995">
            </div>
            <div class="col">
              <label class="form-label">End year (optional)</label>
              <input type="number" class="form-control" name="end_year" min="1800" max="2100" placeholder="e.g. 2005">
            </div>
          </div>
          <div class="form-text">Leave dates blank if you're not sure when the residence period was.</div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="quickAddResidenceSubmit">Add</button>
      </div>
    </div>
  </div>
</div>
@endpush

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
$(function(){
  let mapInitialized = false;
  let map = null;
  
  // Toggle between list and map view
  $('#places-list-toggle').on('click', function(e) {
    e.preventDefault();
    $('#places-list-view').css('display', '');
    $('#places-map-view').css('display', 'none');
    $('#places-list-toggle').addClass('active');
    $('#places-map-toggle').removeClass('active');
  });
  
  $('#places-map-toggle').on('click', function(e) {
    e.preventDefault();
    $('#places-list-view').css('display', 'none');
    $('#places-map-view').css('display', 'block');
    $('#places-list-toggle').removeClass('active');
    $('#places-map-toggle').addClass('active');
    
    // Initialize map on first view
    if (!mapInitialized) {
      initializeResidenceMap();
      mapInitialized = true;
    } else if (map) {
      // Invalidate size to fix any rendering issues
      setTimeout(function() {
        map.invalidateSize();
      }, 100);
    }
  });
  
  // Initialize map on page load since it's the default view
  initializeResidenceMap();
  mapInitialized = true;
  
  function initializeResidenceMap() {
    const places = @json($placesForMap);
    
    // Filter places with coordinates
    const placesWithCoords = places.filter(p => p.coordinates && p.coordinates.latitude && p.coordinates.longitude);
    
    if (placesWithCoords.length === 0) {
      $('#places-map-container').html('<div class="d-flex align-items-center justify-content-center h-100 text-muted"><i class="bi bi-geo-alt me-2"></i>No places have coordinates yet</div>');
      return;
    }
    
    // Initialize map centered on first place
    const firstPlace = placesWithCoords[0];
    map = L.map('places-map-container').setView([
      firstPlace.coordinates.latitude,
      firstPlace.coordinates.longitude
    ], 10);
    
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '© OpenStreetMap contributors'
    }).addTo(map);
    
    // Add markers for each place
    const markers = [];
    placesWithCoords.forEach(function(place, index) {
      const marker = L.marker([
        place.coordinates.latitude,
        place.coordinates.longitude
      ]).addTo(map);
      
      let popupContent = `<div><strong><a href="${place.url}" class="text-decoration-none">${place.name}</a></strong>`;
      if (place.dates) {
        popupContent += `<br><small class="text-muted"><i class="bi bi-calendar me-1"></i>${place.dates}</small>`;
      }
      popupContent += '</div>';
      
      marker.bindPopup(popupContent);
      markers.push(marker);
    });
    
    // Fit map to show all markers
    if (markers.length > 1) {
      const group = new L.featureGroup(markers);
      map.fitBounds(group.getBounds().pad(0.1));
    }
  }
  
  // Autocomplete for place (filter to place type)
  let qaResTimeout = null;
  $('#qaResPlaceInput').on('input', function(){
    const q = $(this).val().trim();
    const $results = $('#qaResSearchResults');
    $('#qaResPlaceId').val('');
    if (qaResTimeout) clearTimeout(qaResTimeout);
    if (!q) { $results.hide().empty(); return; }
    qaResTimeout = setTimeout(function(){
      const params = new URLSearchParams({ q, types: 'place' });
      $.get(`/api/spans/search?${params.toString()}`, function(resp){
        const spans = Array.isArray(resp) ? resp : (resp.spans || []);
        $results.empty();
        if (spans.length > 0) {
          spans.forEach(function(s){
            if (!s.id) return; // ignore placeholders here
            const $item = $(`<div class="p-2 border-bottom search-result-item" data-id="${s.id}" data-name="${s.name}" style="cursor: pointer;">
                <div class="fw-bold">${s.name}</div>
                <div class="text-muted small">${s.type_id}${s.start_year ? ' • ' + s.start_year : ''}</div>
              </div>`);
            $item.on('click', function(){
              $('#qaResPlaceInput').val($(this).data('name'));
              $('#qaResPlaceId').val($(this).data('id'));
              $results.hide().empty();
            });
            $results.append($item);
          });
          $results.show();
        } else {
          // Offer create new
          const $create = $(`<div class="p-2 text-center">
              <button type="button" class="btn btn-outline-primary btn-sm" id="qaResCreatePlaceBtn">
                <i class="bi bi-plus-circle me-1"></i>Create place: "${q}"
              </button>
            </div>`);
          $create.find('#qaResCreatePlaceBtn').on('click', function(){
            // Create placeholder place span
            $.ajax({
              url: '/api/spans/create',
              method: 'POST',
              headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                'Content-Type': 'application/json',
                'Accept': 'application/json'
              },
              data: JSON.stringify({ name: q, type_id: 'place', state: 'placeholder' }),
              success: function(res){
                $('#qaResPlaceInput').val(res.name);
                $('#qaResPlaceId').val(res.id);
                $results.hide().empty();
              },
              error: function(){ alert('Failed to create place'); }
            });
          });
          $results.append($create).show();
        }
      });
    }, 250);
  });

  $('#quickAddResidenceSubmit').on('click', function(){
    const $btn = $(this);
    const $form = $('#quickAddResidenceForm');
    const payload = {
      person_id: $form.find('[name="person_id"]').val(),
      place_name: $form.find('[name="place_name"]').val(),
      place_id: $form.find('[name="place_id"]').val() || null,
      start_year: $form.find('[name="start_year"]').val() ? parseInt($form.find('[name="start_year"]').val(), 10) : null,
      end_year: $form.find('[name="end_year"]').val() ? parseInt($form.find('[name="end_year"]').val(), 10) : null
    };
    if (!payload.place_name) {
      return alert('Please enter a place name');
    }
    $btn.prop('disabled', true).text('Adding...');
    $.ajax({
      url: '{{ route('spans.quick-residence.store') }}',
      method: 'POST',
      data: Object.assign({}, payload, { _token: '{{ csrf_token() }}' }),
      success: function(resp){
        $btn.prop('disabled', false).text('Add');
        const modalEl = document.getElementById('quickAddResidenceModal');
        const modal = bootstrap.Modal.getInstance(modalEl);
        modal.hide();
        // Simple approach: reload to show new residence
        location.reload();
      },
      error: function(xhr){
        $btn.prop('disabled', false).text('Add');
        alert('Failed to add residence: ' + (xhr.responseJSON?.message || 'Unknown error'));
      }
    });
  });
});
</script>
@endpush

