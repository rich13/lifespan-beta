@extends('layouts.admin')

@section('title', 'Manage Person Subtypes')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>Manage Person Subtypes</h1>
                <a href="{{ route('admin.spans.index') }}" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Back to Spans
                </a>
            </div>

            <!-- Subtype Statistics -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body text-center">
                            <h5 class="card-title">Total People</h5>
                            <h3 class="text-primary">{{ $people->total() }}</h3>
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
                            <h3 class="text-muted">{{ ($people->total() - ($subtypeCounts['public_figure'] ?? 0) - ($subtypeCounts['private_individual'] ?? 0)) }}</h3>
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
                    <form id="subtypeForm" method="POST" action="{{ route('admin.spans.update-person-subtypes') }}">
                        @csrf
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Current Subtype</th>
                                        <th>Access Level</th>
                                        <th>Personal Span</th>
                                        <th>Owner</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($people as $person)
                                    <tr>
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
                                        <td>
                                            <select name="updates[{{ $person->id }}][subtype]" class="form-select form-select-sm" style="width: auto;">
                                                <option value="">-- Select --</option>
                                                <option value="public_figure" {{ $person->subtype === 'public_figure' ? 'selected' : '' }}>
                                                    Public Figure
                                                </option>
                                                <option value="private_individual" {{ $person->subtype === 'private_individual' ? 'selected' : '' }}>
                                                    Private Individual
                                                </option>
                                            </select>
                                            <input type="hidden" name="updates[{{ $person->id }}][span_id]" value="{{ $person->id }}">
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> Update Subtypes
                                </button>
                                <button type="button" class="btn btn-outline-secondary ms-2" onclick="resetForm()">
                                    <i class="bi bi-arrow-clockwise"></i> Reset
                                </button>
                            </div>
                        </div>
                    </form>
                    
                    <!-- Pagination -->
                    <div class="mt-3">
                        <x-pagination :paginator="$people" :showInfo="true" itemName="people" />
                    </div>
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
    </div>
</div>

<script>
function resetForm() {
    if (confirm('Are you sure you want to reset all changes?')) {
        document.getElementById('subtypeForm').reset();
    }
}

// Confirm before submitting
document.getElementById('subtypeForm').addEventListener('submit', function(e) {
    const selects = document.querySelectorAll('select[name*="[subtype]"]');
    let hasChanges = false;
    
    selects.forEach(select => {
        if (select.value !== '') {
            hasChanges = true;
        }
    });
    
    if (!hasChanges) {
        e.preventDefault();
        alert('No changes detected. Please select at least one subtype to update.');
        return;
    }
    
    if (!confirm('Are you sure you want to update the selected person subtypes?')) {
        e.preventDefault();
    }
});
</script>
@endsection 