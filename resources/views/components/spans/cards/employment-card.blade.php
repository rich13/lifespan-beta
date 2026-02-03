@props(['span'])

@php
    // Only show for person spans
    if ($span->type_id !== 'person') {
        return;
    }

    // Get employment connections (direct person -> organisation)
    $employmentConnections = $span->connectionsAsSubject()
        ->whereHas('type', function($q) { $q->where('type', 'employment'); })
        ->with(['child', 'connectionSpan'])
        ->get();

    // Get has_role connections with nested at_organisation connections
    $roleConnections = $span->connectionsAsSubject()
        ->whereHas('type', function($q) { $q->where('type', 'has_role'); })
        ->with(['child', 'connectionSpan.connectionsAsSubject.child', 'connectionSpan.connectionsAsSubject.parent', 'connectionSpan.connectionsAsSubject.type', 'connectionSpan.connectionsAsSubject.connectionSpan'])
        ->get();

    // Combine both types into a unified collection with sorting info
    $allEmployment = collect();
    
    // Add employment connections
    foreach ($employmentConnections as $connection) {
        $allEmployment->push([
            'type' => 'employment',
            'connection' => $connection,
            'sort_key' => $connection->getEffectiveSortDate()
        ]);
    }
    
    // Add role connections
    foreach ($roleConnections as $connection) {
        $allEmployment->push([
            'type' => 'role',
            'connection' => $connection,
            'sort_key' => $connection->getEffectiveSortDate()
        ]);
    }
    
    // Sort all employment by date
    $allEmployment = $allEmployment->sortBy(function($item) {
        $parts = $item['sort_key'];
        $y = $parts[0] ?? PHP_INT_MAX;
        $m = $parts[1] ?? PHP_INT_MAX;
        $d = $parts[2] ?? PHP_INT_MAX;
        return sprintf('%08d-%02d-%02d', $y, $m, $d);
    })->values();
@endphp

<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="card-title mb-0">
            <i class="bi bi-briefcase me-2"></i>
            <a href="{{ url('/spans/' . $span->id . '/has-role') }}" class="text-decoration-none">
                Employment
            </a>
        </h6>
        @auth
            @if(auth()->user()->can('update', $span))
                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#quickAddEmploymentModal" data-person-id="{{ $span->id }}">
                    <i class="bi bi-plus-circle me-1"></i> Add
                </button>
            @endif
        @endauth
    </div>
    <div class="card-body p-2">
        @if($allEmployment->isEmpty())
        @else
        <div class="d-grid gap-2">
            @foreach($allEmployment as $item)
                @php
                    $connection = $item['connection'];
                    $connectionType = $item['type'];
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
                    
                    // Determine what to display based on connection type
                    $role = null;
                    $organisation = null;
                    $linkSpan = null; // The span to link to (connection span if available, otherwise role)
                    
                    if ($connectionType === 'role') {
                        // has_role connection: child is the role
                        $role = $connection->child;
                        // Find the at_organisation connection
                        if ($dates) {
                            foreach ($dates->connectionsAsSubject as $nestedConnection) {
                                if ($nestedConnection->type_id === 'at_organisation' && $nestedConnection->child) {
                                    $organisation = $nestedConnection->child;
                                    // Link to the connection span of the at_organisation connection
                                    // This is the fully combined connection span (e.g., "Person has role Role at Organisation")
                                    if ($nestedConnection->connectionSpan) {
                                        $linkSpan = $nestedConnection->connectionSpan;
                                    }
                                    break;
                                }
                            }
                        }
                        // If no at_organisation connection found, link to the has_role connection span
                        if (!$linkSpan && $dates) {
                            $linkSpan = $dates;
                        }
                    } else {
                        // employment connection: child is the organisation directly
                        $organisation = $connection->child;
                        // For employment connections, link to the connection span
                        if ($dates) {
                            $linkSpan = $dates;
                        }
                    }
                @endphp
                <div class="card">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                @if($role)
                                    <h6 class="mb-1">
                                        <x-span-link :span="$linkSpan ?: $role" class="text-decoration-none" />
                                    </h6>
                                @endif
                                @if($organisation)
                                    <div class="text-muted small{{ $role ? '' : ' fw-semibold' }}">
                                        <i class="bi bi-building me-1"></i>
                                        <x-span-link :span="$organisation" class="text-decoration-none text-muted" />
                                    </div>
                                @endif
                            </div>
                            @if($dateText)
                                <div class="text-muted small text-end">
                                    <i class="bi bi-calendar me-1"></i>{{ $dateText }}
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        @endif
    </div>
</div>

@push('modals')
<!-- Quick Add Employment Modal -->
<div class="modal fade" id="quickAddEmploymentModal" tabindex="-1" aria-labelledby="quickAddEmploymentLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="quickAddEmploymentLabel">Add Employment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Role Form -->
                <div id="employmentModalTabContent">
                    <form id="quickAddRoleForm">
                        <input type="hidden" name="person_id" value="{{ $span->id }}">
                        <div class="mb-3 position-relative">
                            <label class="form-label">Role Title</label>
                            <input type="text" class="form-control" name="role_title" id="qaRoleTitleInput" placeholder="e.g., CEO, Director, Senior Developer" autocomplete="off" required>
                            <input type="hidden" name="role_id" id="qaRoleId">
                            <div id="qaRoleSearchResults" class="border bg-white position-absolute w-100" style="z-index: 1060; display: none;"></div>
                            <div class="form-text">Search existing roles or create a new one if none match.</div>
                        </div>
                        <div class="mb-3 position-relative">
                            <label class="form-label">Organisation</label>
                            <input type="text" class="form-control" name="organisation_name" id="qaRoleOrgInput" placeholder="Company or organisation name" autocomplete="off" required>
                            <input type="hidden" name="organisation_id" id="qaRoleOrgId">
                            <div id="qaRoleOrgSearchResults" class="border bg-white position-absolute w-100" style="z-index: 1060; display: none;"></div>
                            <div class="form-text">Search existing organisations or create a new one if none match.</div>
                        </div>
                        <!-- Start Date -->
                        <div class="mb-3">
                            <label class="form-label fw-medium">Start Date</label>
                            <div class="row g-2">
                                <div class="col-4">
                                    <input type="number" class="form-control" name="start_year" 
                                           placeholder="YYYY" min="1000" max="2100">
                                    <div class="form-text">Year</div>
                                </div>
                                <div class="col-4">
                                    <input type="number" class="form-control" name="start_month" 
                                           placeholder="MM" min="1" max="12">
                                    <div class="form-text">Month</div>
                                </div>
                                <div class="col-4">
                                    <input type="number" class="form-control" name="start_day" 
                                           placeholder="DD" min="1" max="31">
                                    <div class="form-text">Day</div>
                                </div>
                            </div>
                            <div class="form-text">All date fields are optional - leave blank to create as placeholder</div>
                        </div>

                        <!-- End Date -->
                        <div class="mb-3">
                            <label class="form-label fw-medium">End Date</label>
                            <div class="row g-2">
                                <div class="col-4">
                                    <input type="number" class="form-control" name="end_year" 
                                           placeholder="YYYY" min="1000" max="2100">
                                    <div class="form-text">Year</div>
                                </div>
                                <div class="col-4">
                                    <input type="number" class="form-control" name="end_month" 
                                           placeholder="MM" min="1" max="12">
                                    <div class="form-text">Month</div>
                                </div>
                                <div class="col-4">
                                    <input type="number" class="form-control" name="end_day" 
                                           placeholder="DD" min="1" max="31">
                                    <div class="form-text">Day</div>
                                </div>
                            </div>
                            <div class="form-text">Optional - leave blank if role is ongoing</div>
                        </div>
                    </form>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="quickAddEmploymentSubmit">Add</button>
            </div>
        </div>
    </div>
</div>
@endpush

@push('scripts')
<script>
$(function(){
  // Autocomplete for role title
  let qaRoleTitleTimeout = null;
  $('#qaRoleTitleInput').on('input', function(){
    const q = $(this).val().trim();
    const $results = $('#qaRoleSearchResults');
    $('#qaRoleId').val('');
    if (qaRoleTitleTimeout) clearTimeout(qaRoleTitleTimeout);
    if (!q) { $results.hide().empty(); return; }
    qaRoleTitleTimeout = setTimeout(function(){
      const params = new URLSearchParams({ q, types: 'role' });
      $.get(`/api/spans/search?${params.toString()}`, function(resp){
        const spans = Array.isArray(resp) ? resp : (resp.spans || []);
        $results.empty();
        if (spans.length > 0) {
          spans.forEach(function(s){
            if (!s.id) return;
            const $item = $(`<div class="p-2 border-bottom search-result-item" data-id="${s.id}" data-name="${s.name}">
                <div class="fw-bold">${s.name}</div>
                <div class="text-muted small">${s.type_id}</div>
              </div>`);
            $item.on('click', function(){
              $('#qaRoleTitleInput').val($(this).data('name'));
              $('#qaRoleId').val($(this).data('id'));
              $results.hide().empty();
            });
            $results.append($item);
          });
          $results.show();
        } else {
          const $create = $(`<div class="p-2 text-center">
              <button type="button" class="btn btn-outline-primary btn-sm" id="qaRoleCreateBtn">
                <i class=\"bi bi-plus-circle me-1\"></i>Create role: \"${q}\"
              </button>
            </div>`);
          $create.find('#qaRoleCreateBtn').on('click', function(){
            $.ajax({
              url: '/api/spans/create',
              method: 'POST',
              headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                'Content-Type': 'application/json',
                'Accept': 'application/json'
              },
              data: JSON.stringify({ name: q, type_id: 'role', state: 'placeholder' }),
              success: function(res){
                $('#qaRoleTitleInput').val(res.name);
                $('#qaRoleId').val(res.id);
                $results.hide().empty();
              },
              error: function(){ alert('Failed to create role'); }
            });
          });
          $results.append($create).show();
        }
      });
    }, 250);
  });

  // Autocomplete for organisation (role tab)
  let qaRoleTimeout = null;
  $('#qaRoleOrgInput').on('input', function(){
    const q = $(this).val().trim();
    const $results = $('#qaRoleOrgSearchResults');
    $('#qaRoleOrgId').val('');
    if (qaRoleTimeout) clearTimeout(qaRoleTimeout);
    if (!q) { $results.hide().empty(); return; }
    qaRoleTimeout = setTimeout(function(){
      const params = new URLSearchParams({ q, types: 'organisation' });
      $.get(`/api/spans/search?${params.toString()}`, function(resp){
        const spans = Array.isArray(resp) ? resp : (resp.spans || []);
        $results.empty();
        if (spans.length > 0) {
          spans.forEach(function(s){
            if (!s.id) return;
            const $item = $(`<div class="p-2 border-bottom search-result-item" data-id="${s.id}" data-name="${s.name}">
                <div class="fw-bold">${s.name}</div>
                <div class="text-muted small">${s.type_id}${s.start_year ? ' • ' + s.start_year : ''}</div>
              </div>`);
            $item.on('click', function(){
              $('#qaRoleOrgInput').val($(this).data('name'));
              $('#qaRoleOrgId').val($(this).data('id'));
              $results.hide().empty();
            });
            $results.append($item);
          });
          $results.show();
        } else {
          const $create = $(`<div class="p-2 text-center">
              <button type="button" class="btn btn-outline-primary btn-sm" id="qaRoleCreateOrgBtn">
                <i class=\"bi bi-plus-circle me-1\"></i>Create organisation: \"${q}\"
              </button>
            </div>`);
          $create.find('#qaRoleCreateOrgBtn').on('click', function(){
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
                $('#qaRoleOrgInput').val(res.name);
                $('#qaRoleOrgId').val(res.id);
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

  $('#quickAddEmploymentSubmit').on('click', function(){
    const $btn = $(this);
    
    // Handle role form
    const $form = $('#quickAddRoleForm');
    const payload = {
      person_id: $form.find('[name="person_id"]').val(),
      organisation_name: $form.find('[name="organisation_name"]').val(),
      organisation_id: $form.find('[name="organisation_id"]').val() || null,
      role_title: $form.find('[name="role_title"]').val(),
      role_id: $form.find('[name="role_id"]').val() || null,
      start_year: $form.find('[name="start_year"]').val() ? parseInt($form.find('[name="start_year"]').val(), 10) : null,
      start_month: $form.find('[name="start_month"]').val() ? parseInt($form.find('[name="start_month"]').val(), 10) : null,
      start_day: $form.find('[name="start_day"]').val() ? parseInt($form.find('[name="start_day"]').val(), 10) : null,
      end_year: $form.find('[name="end_year"]').val() ? parseInt($form.find('[name="end_year"]').val(), 10) : null,
      end_month: $form.find('[name="end_month"]').val() ? parseInt($form.find('[name="end_month"]').val(), 10) : null,
      end_day: $form.find('[name="end_day"]').val() ? parseInt($form.find('[name="end_day"]').val(), 10) : null,
      state: 'placeholder' // Default to placeholder if no dates provided
    };
    
    if (!payload.organisation_name || !payload.role_title) {
      return alert('Please complete all required fields (organisation and role title)');
    }
    
    $btn.prop('disabled', true).text('Adding...');
    
    // For now, just show a message that this feature is coming soon
    // TODO: Implement proper role creation endpoint
    setTimeout(function() {
      $btn.prop('disabled', false).text('Add');
      alert('Role creation feature is coming soon! For now, please use the main connection form.');
      const modalEl = document.getElementById('quickAddEmploymentModal');
      const modal = bootstrap.Modal.getInstance(modalEl);
      modal.hide();
    }, 1000);
  });
});
</script>
@endpush