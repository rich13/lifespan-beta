@extends('layouts.app')

@section('page_title')
    Manage Person Subtypes
@endsection

@section('content')
<div class="py-4">
    <div class="row">
        <div class="col-12 d-flex justify-content-between align-items-center mb-4">
            <div>
                <a href="{{ route('admin.tools.index') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Back to Admin Tools
                </a>
            </div>
        </div>
    </div>

    <!-- Subtype Statistics -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <h5 class="card-title">Total People</h5>
                    <h3 class="text-primary">{{ $totalPeople }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <h5 class="card-title">Public Figures</h5>
                    <h3 class="text-success">{{ $subtypeCounts['public_figure'] ?? 0 }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <h5 class="card-title">Private Individuals</h5>
                    <h3 class="text-warning">{{ $subtypeCounts['private_individual'] ?? 0 }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <h5 class="card-title">Uncategorized</h5>
                    <h3 class="text-muted">{{ ($totalPeople - ($subtypeCounts['public_figure'] ?? 0) - ($subtypeCounts['private_individual'] ?? 0)) }}</h3>
                </div>
            </div>
        </div>
    </div>

    @if(session('status'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('status') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Person Subtypes</h5>
            <small class="text-muted">Set whether each person is a public figure (found on Wikipedia) or a private individual</small>
        </div>
        <div class="card-body">
            <!-- Filters and Bulk Actions -->
            <div class="row mb-3">
                <div class="col-md-4">
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-success btn-sm" onclick="bulkSetAndSubmit('public_figure')">
                            <i class="bi bi-check-all"></i> Set Selected as Public Figures
                        </button>
                        <button type="button" class="btn btn-warning btn-sm" onclick="bulkSetAndSubmit('private_individual')">
                            <i class="bi bi-check-all"></i> Set Selected as Private Individuals
                        </button>
                    </div>
                </div>
                <div class="col-md-4">
                    <form method="GET" action="{{ route('admin.tools.manage-person-subtypes') }}" class="d-flex gap-2">
                        <select class="form-select form-select-sm" name="filter_subtype" onchange="this.form.submit()">
                            <option value="">All Subtypes</option>
                            <option value="public_figure" {{ request('filter_subtype') === 'public_figure' ? 'selected' : '' }}>Public Figures</option>
                            <option value="private_individual" {{ request('filter_subtype') === 'private_individual' ? 'selected' : '' }}>Private Individuals</option>
                            <option value="uncategorized" {{ request('filter_subtype') === 'uncategorized' ? 'selected' : '' }}>Uncategorized</option>
                        </select>
                        <select class="form-select form-select-sm" name="filter_access" onchange="this.form.submit()">
                            <option value="">All Access Levels</option>
                            <option value="public" {{ request('filter_access') === 'public' ? 'selected' : '' }}>Public</option>
                            <option value="private" {{ request('filter_access') === 'private' ? 'selected' : '' }}>Private</option>
                            <option value="shared" {{ request('filter_access') === 'shared' ? 'selected' : '' }}>Shared</option>
                        </select>
                    </form>
                </div>
                <div class="col-md-4 text-end">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                        <label class="form-check-label" for="selectAll">
                            Select All Visible
                        </label>
                    </div>
                </div>
            </div>

            <form id="subtypeForm" method="POST" action="{{ route('admin.tools.update-person-subtypes') }}">
                @csrf
                <input type="hidden" id="selectedSubtypes" name="selected_subtypes" value="">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th width="40">
                                    <input type="checkbox" class="form-check-input" id="headerSelectAll" onchange="toggleSelectAll()">
                                </th>
                                <th>Name</th>
                                <th>Current Subtype</th>
                                <th>Access Level</th>
                                <th>Personal Span</th>
                                <th>Owner</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($people as $person)
                            <tr>
                                <td>
                                    <input type="checkbox" class="form-check-input person-checkbox" value="{{ $person->id }}" name="selected_people[]">
                                </td>
                                <td>
                                    <a href="{{ route('admin.spans.show', $person) }}" target="_blank">
                                        {{ $person->name }}
                                    </a>
                                </td>
                                <td>
                                    <span class="badge bg-{{ $person->subtype === 'public_figure' ? 'success' : ($person->subtype === 'private_individual' ? 'warning' : 'secondary') }}">
                                        {{ $person->subtype ? ucfirst(str_replace('_', ' ', $person->subtype)) : 'Uncategorized' }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-{{ $person->access_level === 'public' ? 'success' : ($person->access_level === 'private' ? 'warning' : 'info') }}">
                                        {{ ucfirst($person->access_level) }}
                                    </span>
                                </td>
                                <td>
                                    @if($person->is_personal_span)
                                        <span class="badge bg-info">Yes</span>
                                    @else
                                        <span class="text-muted">No</span>
                                    @endif
                                </td>
                                <td>
                                    @if($person->owner)
                                        <small>{{ $person->owner->name }}</small>
                                    @else
                                        <span class="text-muted">System</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-between align-items-center mt-3">
                    <div>
                        <button type="button" class="btn btn-outline-secondary" onclick="resetForm()">
                            <i class="bi bi-arrow-clockwise"></i> Reset
                        </button>
                    </div>
                    <div>
                        <x-pagination :paginator="$people" :showInfo="true" itemName="people" />
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Help Section -->
    <div class="card mt-4">
        <div class="card-header">
            <h6 class="mb-0">Help</h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h6>Public Figure</h6>
                    <ul class="small text-muted">
                        <li>People who can be found on Wikipedia</li>
                        <li>Well-known historical figures, celebrities, politicians</li>
                        <li>Access level will be set to "Public" automatically</li>
                        <li>Wikipedia lookups can be performed</li>
                    </ul>
                </div>
                <div class="col-md-6">
                    <h6>Private Individual</h6>
                    <ul class="small text-muted">
                        <li>Regular people, family members, friends</li>
                        <li>Not found on Wikipedia</li>
                        <li>Access level remains as set</li>
                        <li>No Wikipedia lookups</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function resetForm() {
    if (confirm('Are you sure you want to reset all changes?')) {
        document.getElementById('subtypeForm').reset();
        // Also uncheck all checkboxes
        document.querySelectorAll('.person-checkbox').forEach(checkbox => {
            checkbox.checked = false;
        });
        document.getElementById('selectAll').checked = false;
        document.getElementById('headerSelectAll').checked = false;
        // Clear selected subtypes
        selectedSubtypes = {};
        updateSelectedSubtypesInput();
    }
}

// Add event listeners when the page loads
document.addEventListener('DOMContentLoaded', function() {
    // Add change event listeners to checkboxes to update select all state
    document.querySelectorAll('.person-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', updateSelectAllState);
    });
});

function toggleSelectAll() {
    const selectAll = document.getElementById('selectAll').checked;
    const headerSelectAll = document.getElementById('headerSelectAll').checked;
    
    // Sync both select all checkboxes
    document.getElementById('selectAll').checked = selectAll || headerSelectAll;
    document.getElementById('headerSelectAll').checked = selectAll || headerSelectAll;
    
    // Check/uncheck all person checkboxes on the current page
    document.querySelectorAll('.person-checkbox').forEach(checkbox => {
        checkbox.checked = selectAll || headerSelectAll;
    });
}

function updateSelectAllState() {
    const allCheckboxes = document.querySelectorAll('.person-checkbox');
    const checkedCheckboxes = document.querySelectorAll('.person-checkbox:checked');
    
    const selectAll = document.getElementById('selectAll');
    const headerSelectAll = document.getElementById('headerSelectAll');
    
    if (checkedCheckboxes.length === 0) {
        selectAll.checked = false;
        headerSelectAll.checked = false;
    } else if (checkedCheckboxes.length === allCheckboxes.length && allCheckboxes.length > 0) {
        selectAll.checked = true;
        headerSelectAll.checked = true;
    } else {
        selectAll.checked = false;
        headerSelectAll.checked = false;
    }
}

// Track selected subtypes for each person
let selectedSubtypes = {};

function bulkSetAndSubmit(subtype) {
    const selectedCheckboxes = document.querySelectorAll('.person-checkbox:checked');
    
    if (selectedCheckboxes.length === 0) {
        alert('Please select at least one person to update.');
        return;
    }
    
    const subtypeLabel = subtype === 'public_figure' ? 'Public Figures' : 'Private Individuals';
    if (!confirm(`Are you sure you want to set ${selectedCheckboxes.length} selected people as ${subtypeLabel}?`)) {
        return;
    }
    
    // Set the subtype for all selected people
    selectedCheckboxes.forEach(checkbox => {
        const personId = checkbox.value;
        selectedSubtypes[personId] = subtype;
    });
    
    // Update the hidden input with the selected subtypes
    updateSelectedSubtypesInput();
    
    // Process updates via AJAX with batching
    processBulkUpdates(subtype);
}



function showTemporaryMessage(message, type = 'info') {
    // Remove any existing temporary messages
    const existingMessage = document.querySelector('.temporary-message');
    if (existingMessage) {
        existingMessage.remove();
    }
    
    // Create new message
    const messageDiv = document.createElement('div');
    messageDiv.className = `alert alert-${type} temporary-message`;
    messageDiv.innerHTML = `
        <i class="bi bi-info-circle"></i>
        ${message}
        <button type="button" class="btn-close" onclick="this.parentElement.remove()"></button>
    `;
    
    // Insert after the bulk actions
    const bulkActions = document.querySelector('.row.mb-3');
    bulkActions.parentNode.insertBefore(messageDiv, bulkActions.nextSibling);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        if (messageDiv.parentNode) {
            messageDiv.remove();
        }
    }, 5000);
}

function updateSelectedSubtypesInput() {
    const input = document.getElementById('selectedSubtypes');
    input.value = JSON.stringify(selectedSubtypes);
}

// Process bulk updates via AJAX with batching
function processBulkUpdates(subtype) {
    const selectedCheckboxes = document.querySelectorAll('.person-checkbox:checked');
    const batchSize = 10; // Process 10 people at a time
    const totalPeople = selectedCheckboxes.length;
    let processedCount = 0;
    let totalUpdated = 0;
    let allErrors = [];
    
    // Disable the bulk action buttons during processing
    const bulkButtons = document.querySelectorAll('.btn-success, .btn-warning');
    bulkButtons.forEach(btn => btn.disabled = true);
    
    // Show progress message
    showTemporaryMessage(`Processing ${totalPeople} people in batches of ${batchSize}...`, 'info');
    
    // Create progress bar
    const progressContainer = document.createElement('div');
    progressContainer.className = 'progress mt-3';
    progressContainer.innerHTML = `
        <div class="progress-bar" role="progressbar" style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
            0 / ${totalPeople}
        </div>
    `;
    
    // Insert progress bar after the bulk actions
    const bulkActions = document.querySelector('.row.mb-3');
    bulkActions.parentNode.insertBefore(progressContainer, bulkActions.nextSibling);
    
    const progressBar = progressContainer.querySelector('.progress-bar');
    
    // Process in batches
    function processBatch() {
        const batch = [];
        const startIndex = processedCount;
        const endIndex = Math.min(startIndex + batchSize, totalPeople);
        
        for (let i = startIndex; i < endIndex; i++) {
            const checkbox = selectedCheckboxes[i];
            batch.push({
                span_id: checkbox.value,
                subtype: subtype
            });
        }
        
        // Send AJAX request for this batch
        $.ajax({
            url: '{{ route("admin.tools.update-person-subtypes-ajax") }}',
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                updates: batch
            },
            success: function(response) {
                if (response.success) {
                    totalUpdated += response.updated;
                    if (response.errors && response.errors.length > 0) {
                        allErrors = allErrors.concat(response.errors);
                    }
                } else {
                    allErrors.push('Batch processing failed');
                }
                
                processedCount += batch.length;
                
                // Update progress bar
                const progress = Math.round((processedCount / totalPeople) * 100);
                progressBar.style.width = progress + '%';
                progressBar.setAttribute('aria-valuenow', progress);
                progressBar.textContent = `${processedCount} / ${totalPeople}`;
                
                // Check if there are more batches to process
                if (processedCount < totalPeople) {
                    // Add a small delay between batches to prevent overwhelming the server
                    setTimeout(processBatch, 500);
                } else {
                    // All batches processed
                    completeProcessing();
                }
            },
            error: function(xhr, status, error) {
                allErrors.push(`Batch processing error: ${error}`);
                processedCount += batch.length;
                
                // Update progress bar
                const progress = Math.round((processedCount / totalPeople) * 100);
                progressBar.style.width = progress + '%';
                progressBar.setAttribute('aria-valuenow', progress);
                progressBar.textContent = `${processedCount} / ${totalPeople}`;
                
                // Continue with next batch even if this one failed
                if (processedCount < totalPeople) {
                    setTimeout(processBatch, 500);
                } else {
                    completeProcessing();
                }
            }
        });
    }
    
    function completeProcessing() {
        // Remove progress bar
        progressContainer.remove();
        
        // Re-enable bulk action buttons
        bulkButtons.forEach(btn => btn.disabled = false);
        
        // Show completion message
        let message = `Successfully updated ${totalUpdated} out of ${totalPeople} people.`;
        if (allErrors.length > 0) {
            message += ` Errors: ${allErrors.slice(0, 5).join(', ')}${allErrors.length > 5 ? '...' : ''}`;
        }
        
        showTemporaryMessage(message, allErrors.length > 0 ? 'warning' : 'success');
        
        // Uncheck all checkboxes
        selectedCheckboxes.forEach(checkbox => {
            checkbox.checked = false;
        });
        
        // Reset select all checkboxes
        document.getElementById('selectAll').checked = false;
        document.getElementById('headerSelectAll').checked = false;
        
        // Clear selected subtypes
        selectedSubtypes = {};
        updateSelectedSubtypesInput();
        
        // Reload the page after a short delay to show updated data
        setTimeout(() => {
            window.location.reload();
        }, 2000);
    }
    
    // Start processing
    processBatch();
}



// Update select all checkbox when individual checkboxes change
document.addEventListener('change', function(e) {
    if (e.target.classList.contains('person-checkbox')) {
        updateSelectAllState();
    }
});
</script>
@endsection 