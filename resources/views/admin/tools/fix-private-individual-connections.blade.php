@extends('layouts.app')

@section('page_title')
    Fix Private Individual Connections
@endsection

@section('content')
<div class="py-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">Fix Private Individual Connections</h1>
                <a href="{{ route('admin.tools.index') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Back to Tools
                </a>
            </div>
            
            @if (session('status'))
                <div class="alert alert-success">
                    {{ session('status') }}
                </div>
            @endif

            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Overview</h5>
                </div>
                <div class="card-body">
                    <p class="mb-3">
                        This tool sets all connections for private individuals to private by default. 
                        This establishes a clean baseline for privacy settings.
                    </p>
                    
                    <div class="row">
                        <div class="col-md-3">
                            <div class="text-center">
                                <h4 class="text-primary">{{ $stats['total_private_individuals'] }}</h4>
                                <small class="text-muted">Total Private Individuals</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center">
                                <h4 class="text-warning">{{ $stats['private_individuals_with_public_connections'] }}</h4>
                                <small class="text-muted">Individuals with Public Connections</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center">
                                <h4 class="text-danger">{{ $stats['total_public_connections'] }}</h4>
                                <small class="text-muted">Total Public Connections</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center">
                                <h4 class="text-success">{{ $stats['fixed_connections'] }}</h4>
                                <small class="text-muted">Connections Fixed</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Private Individuals</h5>
                    @if($stats['private_individuals_with_public_connections'] > 0)
                        <button type="button" class="btn btn-primary" onclick="fixAllConnections()">
                            <i class="bi bi-check-circle"></i> Fix All Public Connections
                        </button>
                    @endif
                </div>
                <div class="card-body">
                    @if($privateIndividuals->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>
                                            <div class="form-check">
                                                <input type="checkbox" class="form-check-input" id="select-all">
                                            </div>
                                        </th>
                                        <th>Name</th>
                                        <th>Access Level</th>
                                        <th>Public Connections</th>
                                        <th>Owner</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($privateIndividuals as $individual)
                                        @php
                                            $publicConnections = app(\App\Http\Controllers\Admin\ToolsController::class)->getPublicConnectionsForSpan($individual);
                                            $hasPublicConnections = $publicConnections->count() > 0;
                                        @endphp
                                        <tr class="{{ $hasPublicConnections ? 'table-warning' : '' }}">
                                            <td>
                                                <div class="form-check">
                                                    <input type="checkbox" class="form-check-input individual-checkbox" 
                                                           value="{{ $individual->id }}" 
                                                           {{ $hasPublicConnections ? '' : 'disabled' }}>
                                                </div>
                                            </td>
                                            <td>
                                                <strong>{{ $individual->name }}</strong>
                                                @if($individual->description)
                                                    <br><small class="text-muted">{{ Str::limit($individual->description, 50) }}</small>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="badge bg-{{ $individual->access_level === 'private' ? 'success' : 'warning' }}">
                                                    {{ ucfirst($individual->access_level) }}
                                                </span>
                                            </td>
                                            <td>
                                                @if($hasPublicConnections)
                                                    <span class="badge bg-danger">{{ $publicConnections->count() }} public</span>
                                                @else
                                                    <span class="badge bg-success">All private</span>
                                                @endif
                                            </td>
                                            <td>
                                                {{ $individual->owner->name ?? 'Unknown' }}
                                            </td>
                                            <td>
                                                @if($hasPublicConnections)
                                                    <button type="button" class="btn btn-sm btn-warning" 
                                                            onclick="fixConnections('{{ $individual->id }}')">
                                                        <i class="bi bi-check-circle"></i> Fix
                                                    </button>
                                                @else
                                                    <span class="text-muted">No action needed</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-4">
                            <i class="bi bi-people text-muted" style="font-size: 3rem;"></i>
                            <p class="text-muted mt-2">No private individuals found.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Hidden form for bulk actions -->
<form id="fix-connections-form" method="POST" action="{{ route('admin.tools.fix-private-individual-connections-action') }}" style="display: none;">
    @csrf
    <input type="hidden" name="individual_ids" id="individual-ids-input">
</form>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle select all checkbox
    const selectAllCheckbox = document.getElementById('select-all');
    const individualCheckboxes = document.querySelectorAll('.individual-checkbox:not(:disabled)');
    
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            individualCheckboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });
    }
    
    // Update select all when individual checkboxes change
    individualCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const checkedCount = document.querySelectorAll('.individual-checkbox:checked').length;
            const totalCount = individualCheckboxes.length;
            
            if (selectAllCheckbox) {
                selectAllCheckbox.checked = checkedCount === totalCount;
                selectAllCheckbox.indeterminate = checkedCount > 0 && checkedCount < totalCount;
            }
        });
    });
});

function fixConnections(individualId) {
    if (confirm('Are you sure you want to make all connections for this private individual private?')) {
        document.getElementById('individual-ids-input').value = individualId;
        document.getElementById('fix-connections-form').submit();
    }
}

function fixAllConnections() {
    const checkedBoxes = document.querySelectorAll('.individual-checkbox:checked');
    const individualIds = Array.from(checkedBoxes).map(cb => cb.value);
    
    if (individualIds.length === 0) {
        alert('Please select at least one private individual to fix.');
        return;
    }
    
    if (confirm(`Are you sure you want to make all connections private for ${individualIds.length} private individual(s)?`)) {
        document.getElementById('individual-ids-input').value = individualIds.join(',');
        document.getElementById('fix-connections-form').submit();
    }
}
</script>
@endpush
@endsection 