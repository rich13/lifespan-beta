@props(['span'])

@php
    // Only show for person spans
    if ($span->type_id !== 'person') {
        return;
    }

    $educationConnections = $span->connectionsAsSubject()
        ->whereHas('type', function($q) { $q->where('type', 'education'); })
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

    // Batch-fetch all "during" phase connections for education connection spans (avoids N+1)
    $connectionSpanIds = $educationConnections->map(fn($c) => $c->connectionSpan?->id)->filter()->unique()->values()->all();
    $duringBySubject = collect();
    $duringByObject = collect();
    if (!empty($connectionSpanIds)) {
        $allDuring = \App\Models\Connection::where(function($q) use ($connectionSpanIds) {
            $q->whereIn('parent_id', $connectionSpanIds)->orWhereIn('child_id', $connectionSpanIds);
        })
            ->whereHas('type', function($q) { $q->where('type', 'during'); })
            ->with(['child', 'parent'])
            ->get();
        $duringBySubject = $allDuring->groupBy('parent_id');
        $duringByObject = $allDuring->groupBy('child_id');
    }
@endphp
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="card-title mb-0">
        <i class="bi bi-mortarboard me-2"></i>
            <a href="{{ url('/spans/' . $span->id . '/studied-at') }}" class="text-decoration-none">
                Education
            </a>
        </h6>
        @auth
            @if(auth()->user()->can('update', $span))
                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#quickAddEducationModal" data-person-id="{{ $span->id }}">
                    <i class="bi bi-plus-circle me-1"></i> Add
                </button>
            @endif
        @endauth
    </div>
    <div class="card-body p-2">
        @if($educationConnections->isEmpty())
        @else
        <div class="d-grid gap-2">
            @foreach($educationConnections as $connection)
                @php
                    $org = $connection->child;
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
                <div class="card">
                    <div class="card-body py-2">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <x-span-link :span="$org" class="text-decoration-none fw-semibold" />
                            </div>
                            @if($dateText)
                                <div class="text-muted small ms-3">
                                    <i class="bi bi-calendar me-1"></i>{{ $dateText }}
                                </div>
                            @endif
                        </div>
                        @if(isset($org->metadata['location']) && $org->metadata['location'])
                            <div class="text-muted small mt-1">
                                <i class="bi bi-geo-alt me-1"></i>{{ $org->metadata['location'] }}
                            </div>
                        @endif

                        @php
                            // Use pre-fetched during connections (batched above) to avoid N+1
                            $phaseChips = [];
                            if ($dates) {
                                $subjectDuring = $duringBySubject->get($dates->id, collect());
                                $objectDuring = $duringByObject->get($dates->id, collect());
                                foreach ($subjectDuring as $c) {
                                    $phaseSpan = $c->child;
                                    if (!$phaseSpan) continue;
                                    $parts = $c->getEffectiveSortDate();
                                    $y = $parts[0] ?? PHP_INT_MAX; $m = $parts[1] ?? PHP_INT_MAX; $d = $parts[2] ?? PHP_INT_MAX;
                                    $range = ($phaseSpan->start_year || $phaseSpan->end_year)
                                        ? trim(($phaseSpan->start_year ?? '') . '–' . ($phaseSpan->end_year ?? ''))
                                        : null;
                                    $phaseChips[$phaseSpan->id] = [
                                        'id' => $phaseSpan->id,
                                        'target' => $c->connection_span_id,
                                        'label' => $phaseSpan->name ?? 'Phase',
                                        'range' => $range,
                                        'sort' => sprintf('%08d-%02d-%02d', $y, $m, $d)
                                    ];
                                }
                                foreach ($objectDuring as $c) {
                                    $phaseSpan = $c->parent;
                                    if (!$phaseSpan) continue;
                                    $parts = $c->getEffectiveSortDate();
                                    $y = $parts[0] ?? PHP_INT_MAX; $m = $parts[1] ?? PHP_INT_MAX; $d = $parts[2] ?? PHP_INT_MAX;
                                    $range = ($phaseSpan->start_year || $phaseSpan->end_year)
                                        ? trim(($phaseSpan->start_year ?? '') . '–' . ($phaseSpan->end_year ?? ''))
                                        : null;
                                    $phaseChips[$phaseSpan->id] = [
                                        'id' => $phaseSpan->id,
                                        'target' => $c->connection_span_id,
                                        'label' => $phaseSpan->name ?? 'Phase',
                                        'range' => $range,
                                        'sort' => sprintf('%08d-%02d-%02d', $y, $m, $d)
                                    ];
                                }
                                usort($phaseChips, function($a, $b) {
                                    return strcmp($a['sort'], $b['sort']);
                                });
                            }
                        @endphp
                        @if(!empty($phaseChips))
                            <div class="d-flex flex-wrap gap-1 mt-2">
                                @foreach($phaseChips as $i => $chip)
                                    @php $tooltip = trim(($chip['label'] ?? 'Phase') . (!empty($chip['range']) ? ' (' . $chip['range'] . ')' : '')); @endphp
                                    <a href="{{ route('spans.show', $chip['target'] ?? $chip['id']) }}" class="text-decoration-none">
                                        <div class="border rounded px-2 py-1 small bg-light" title="{{ $tooltip }}" data-bs-toggle="tooltip" data-bs-placement="top">
                                            Year {{ $i + 1 }}
                                        </div>
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
        @endif
    </div>
</div>
@push('modals')
<!-- Quick Add Education Modal -->
<div class="modal fade" id="quickAddEducationModal" tabindex="-1" aria-labelledby="quickAddEducationLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="quickAddEducationLabel">Add Education</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <!-- Tab Navigation -->
        <ul class="nav nav-tabs mb-3" id="educationModalTabs" role="tablist">
          <li class="nav-item" role="presentation">
            <button class="nav-link active" id="new-education-tab" data-bs-toggle="tab" data-bs-target="#new-education" type="button" role="tab">
              Add New Education
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="add-phases-tab" data-bs-toggle="tab" data-bs-target="#add-phases" type="button" role="tab">
              Add Phases to Existing
            </button>
          </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content" id="educationModalTabContent">
          <!-- New Education Tab -->
          <div class="tab-pane fade show active" id="new-education" role="tabpanel">
            <form id="quickAddEducationForm">
              <input type="hidden" name="person_id" value="{{ $span->id }}">
              <div class="mb-3 position-relative">
                <label class="form-label">Organisation</label>
                <input type="text" class="form-control" name="organisation_name" id="qaEduOrgInput" placeholder="School or University name" autocomplete="off" required>
                <input type="hidden" name="organisation_id" id="qaEduOrgId">
                <div id="qaEduSearchResults" class="border bg-white position-absolute w-100" style="z-index: 1060; display: none;"></div>
                <div class="form-text">Search existing organisations or create a new one if none match.</div>
              </div>
              <div class="row g-3 mb-2">
                <div class="col">
                  <label class="form-label">Start year</label>
                  <input type="number" class="form-control" name="start_year" min="1800" max="2100" required>
                </div>
                <div class="col">
                  <label class="form-label">End year</label>
                  <input type="number" class="form-control" name="end_year" min="1800" max="2100" required>
                </div>
              </div>
              <div class="form-text">Phases will be auto-created per academic year (Sep–Jul).</div>
            </form>
          </div>

          <!-- Add Phases Tab -->
          <div class="tab-pane fade" id="add-phases" role="tabpanel">
            <div id="existingEducationList">
              @php
                // Get education connections that don't have phases
                $educationConnectionsWithoutPhases = [];
                foreach($educationConnections as $connection) {
                  $dates = $connection->connectionSpan;
                  $hasPhases = false;
                  if ($dates) {
                    // Check for during connections
                    $subjectDuring = $dates->connectionsAsSubject()
                      ->whereHas('type', function($q){ $q->where('type','during'); })
                      ->count();
                    $objectDuring = $dates->connectionsAsObject()
                      ->whereHas('type', function($q){ $q->where('type','during'); })
                      ->count();
                    $hasPhases = ($subjectDuring + $objectDuring) > 0;
                  }
                  if (!$hasPhases) {
                    $educationConnectionsWithoutPhases[] = $connection;
                  }
                }
              @endphp
              
              @if(empty($educationConnectionsWithoutPhases))
                <div class="text-center text-muted py-3">
                  <i class="bi bi-check-circle me-2"></i>All education connections already have phases.
                </div>
              @else
                <div class="mb-3">
                  <h6>Education connections without phases:</h6>
                </div>
                @foreach($educationConnectionsWithoutPhases as $connection)
                  @php
                    $org = $connection->child;
                    $dates = $connection->connectionSpan;
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
                  @endphp
                  <div class="card mb-2">
                    <div class="card-body py-2">
                      <div class="d-flex justify-content-between align-items-center">
                        <div>
                          <div class="fw-semibold">{{ $org->name }}</div>
                          @if($dateText)
                            <div class="text-muted small">{{ $dateText }}</div>
                          @endif
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary add-phases-btn" 
                                data-connection-span-id="{{ $connection->connectionSpan->id }}">
                          <i class="bi bi-plus-circle me-1"></i>Add Phases
                        </button>
                      </div>
                    </div>
                  </div>
                @endforeach
              @endif
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="quickAddEducationSubmit">Add</button>
      </div>
    </div>
  </div>
  </div>
@endpush

@push('scripts')
<script>
$(function(){
  // Autocomplete for organisation (filter to organisation type)
  let qaEduTimeout = null;
  $('#qaEduOrgInput').on('input', function(){
    const q = $(this).val().trim();
    const $results = $('#qaEduSearchResults');
    $('#qaEduOrgId').val('');
    if (qaEduTimeout) clearTimeout(qaEduTimeout);
    if (!q) { $results.hide().empty(); return; }
    qaEduTimeout = setTimeout(function(){
      const params = new URLSearchParams({ q, types: 'organisation' });
      $.get(`/api/spans/search?${params.toString()}`, function(resp){
        const spans = Array.isArray(resp) ? resp : (resp.spans || []);
        $results.empty();
        if (spans.length > 0) {
          spans.forEach(function(s){
            if (!s.id) return; // ignore placeholders here
            const $item = $(`<div class="p-2 border-bottom search-result-item" data-id="${s.id}" data-name="${s.name}">
                <div class="fw-bold">${s.name}</div>
                <div class="text-muted small">${s.type_id}${s.start_year ? ' • ' + s.start_year : ''}</div>
              </div>`);
            $item.on('click', function(){
              $('#qaEduOrgInput').val($(this).data('name'));
              $('#qaEduOrgId').val($(this).data('id'));
              $results.hide().empty();
            });
            $results.append($item);
          });
          $results.show();
        } else {
          // Offer create new
          const $create = $(`<div class="p-2 text-center">
              <button type="button" class="btn btn-outline-primary btn-sm" id="qaEduCreateOrgBtn">
                <i class=\"bi bi-plus-circle me-1\"></i>Create organisation: \"${q}\"
              </button>
            </div>`);
          $create.find('#qaEduCreateOrgBtn').on('click', function(){
            // Create placeholder organisation span
            $.ajax({
              url: '/api/spans/create',
              method: 'POST',
              headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                'Content-Type': 'application/json',
                'Accept': 'application/json'
              },
              data: JSON.stringify({ name: q, type_id: 'organisation', state: 'placeholder' }),
              success: function(res){
                $('#qaEduOrgInput').val(res.name);
                $('#qaEduOrgId').val(res.id);
                $results.hide().empty();
              },
              error: function(){ alert('Failed to create organisation'); }
            });
          });
          $results.append($create).show();
        }
      });
    }, 250);
  });

  // Add click handlers for "Add Phases" buttons (now rendered server-side)
  $(document).on('click', '.add-phases-btn', function() {
    const connectionSpanId = $(this).data('connection-span-id');
    addPhasesToExistingEducation(connectionSpanId, $(this));
  });

  function addPhasesToExistingEducation(connectionSpanId, $btn) {
    $btn.prop('disabled', true).html('<div class="spinner-border spinner-border-sm me-1"></div>Adding...');
    
    $.ajax({
      url: '{{ route('spans.quick-education.store') }}',
      method: 'POST',
      data: {
        _token: '{{ csrf_token() }}',
        action: 'add_phases_to_existing',
        connection_span_id: connectionSpanId
      },
      success: function(resp) {
        $btn.prop('disabled', false).html('<i class="bi bi-check-circle me-1"></i>Added');
        $btn.removeClass('btn-outline-primary').addClass('btn-success');
        // Reload the list to show updated state
        setTimeout(() => loadExistingEducationConnections(), 1000);
      },
      error: function(xhr) {
        $btn.prop('disabled', false).html('<i class="bi bi-plus-circle me-1"></i>Add Phases');
        alert('Failed to add phases: ' + (xhr.responseJSON?.message || 'Unknown error'));
      }
    });
  }

  $('#quickAddEducationSubmit').on('click', function(){
    const $btn = $(this);
    const $form = $('#quickAddEducationForm');
    const payload = {
      person_id: $form.find('[name="person_id"]').val(),
      organisation_name: $form.find('[name="organisation_name"]').val(),
      organisation_id: $form.find('[name="organisation_id"]').val() || null,
      start_year: parseInt($form.find('[name="start_year"]').val(), 10),
      end_year: parseInt($form.find('[name="end_year"]').val(), 10)
    };
    if (!payload.organisation_name || !payload.start_year || !payload.end_year) {
      return alert('Please complete all fields');
    }
    $btn.prop('disabled', true).text('Adding...');
    $.ajax({
      url: '{{ route('spans.quick-education.store') }}',
      method: 'POST',
      data: Object.assign({}, payload, { _token: '{{ csrf_token() }}' }),
      success: function(resp){
        $btn.prop('disabled', false).text('Add');
        const modalEl = document.getElementById('quickAddEducationModal');
        const modal = bootstrap.Modal.getInstance(modalEl);
        modal.hide();
        // Simple approach: reload to show new education + phases
        location.reload();
      },
      error: function(xhr){
        $btn.prop('disabled', false).text('Add');
        alert('Failed to add education: ' + (xhr.responseJSON?.message || 'Unknown error'));
      }
    });
  });
});
</script>
@endpush
