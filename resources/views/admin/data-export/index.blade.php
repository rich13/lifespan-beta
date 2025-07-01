@extends('layouts.app')

@section('page_title')
    Data Export
@endsection

@section('page_tools')
    <div class="d-flex gap-2">
        <a href="{{ route('admin.tools.index') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Back to Tools
        </a>
        <a href="{{ route('admin.data-import.index') }}" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-upload me-1"></i>Data Import
        </a>
    </div>
@endsection

@section('content')
<div class="py-4">
    <div class="row">
        <div class="col-12">
            <div class="alert alert-info">
                <i class="bi bi-info-circle me-2"></i>
                <strong>Data Export System</strong> - Export all spans as YAML files for backup, migration, or sharing.
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Export Options -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-download me-2"></i>Export Options
                    </h5>
                </div>
                <div class="card-body">
                    <form id="exportForm" action="{{ route('admin.data-export.export-all') }}" method="GET">
                        <!-- Export Format -->
                        <div class="mb-4">
                            <label class="form-label fw-bold">Export Format:</label>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="format" id="format-individual" value="individual" checked>
                                        <label class="form-check-label" for="format-individual">
                                            <strong>Individual Files (ZIP)</strong>
                                            <br>
                                            <small class="text-muted">Each span as a separate YAML file, packaged in a ZIP archive</small>
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="format" id="format-single" value="single">
                                        <label class="form-check-label" for="format-single">
                                            <strong>Single File</strong>
                                            <br>
                                            <small class="text-muted">All spans in one YAML file</small>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Export Options -->
                        <div class="mb-4">
                            <label class="form-label fw-bold">Export Options:</label>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="include_metadata" id="include-metadata" value="1" checked>
                                        <label class="form-check-label" for="include-metadata">
                                            Include metadata
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="include_connections" id="include-connections" value="1" checked>
                                        <label class="form-check-label" for="include-connections">
                                            Include connections
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Export Button -->
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg" id="exportBtn">
                                <i class="bi bi-download me-2"></i>
                                Export All Spans ({{ number_format($stats['total_spans']) }} spans)
                            </button>
                        </div>

                        <div class="mt-3 text-center">
                            <small class="text-muted">
                                <i class="bi bi-clock me-1"></i>
                                Export may take a few moments depending on the number of spans
                            </small>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Selective Export -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-funnel me-2"></i>Selective Export
                    </h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">Export specific spans by selecting them from the list below.</p>
                    
                    <div class="mb-3">
                        <div class="input-group">
                            <input type="text" class="form-control" id="spanSearch" placeholder="Search spans...">
                            <button class="btn btn-outline-secondary" type="button" id="clearSearch">
                                <i class="bi bi-x"></i>
                            </button>
                        </div>
                    </div>

                    <div class="mb-3">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="selectAll">
                            <i class="bi bi-check-all me-1"></i>Select All
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="deselectAll">
                            <i class="bi bi-x-circle me-1"></i>Deselect All
                        </button>
                        <span class="ms-3 text-muted">
                            <span id="selectedCount">0</span> spans selected
                        </span>
                    </div>

                    <div id="spansList" class="border rounded" style="max-height: 400px; overflow-y: auto;">
                        <!-- Spans will be loaded here -->
                    </div>

                    <div class="mt-3">
                        <button type="button" class="btn btn-success" id="exportSelectedBtn" disabled>
                            <i class="bi bi-download me-1"></i>
                            Export Selected Spans
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics -->
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-graph-up me-2"></i>Export Statistics
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-6 mb-3">
                            <div class="border rounded p-3">
                                <h4 class="text-primary mb-1">{{ number_format($stats['total_spans']) }}</h4>
                                <small class="text-muted">Total Spans</small>
                            </div>
                        </div>
                        <div class="col-6 mb-3">
                            <div class="border rounded p-3">
                                <h4 class="text-success mb-1">{{ number_format($stats['total_users']) }}</h4>
                                <small class="text-muted">Total Users</small>
                            </div>
                        </div>
                    </div>

                    <h6 class="mt-3">Span Types:</h6>
                    <div class="list-group list-group-flush">
                        @foreach($stats['span_types'] as $type)
                            <div class="list-group-item d-flex justify-content-between align-items-center py-2">
                                <span class="badge bg-secondary">{{ $type->type_id }}</span>
                                <span class="fw-bold">{{ number_format($type->count) }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Recent Spans -->
            <div class="card mt-3">
                <div class="card-header">
                    <h6 class="card-title mb-0">
                        <i class="bi bi-clock-history me-2"></i>Recent Spans
                    </h6>
                </div>
                <div class="card-body">
                    <div class="list-group list-group-flush">
                        @foreach($stats['recent_spans'] as $span)
                            <div class="list-group-item d-flex justify-content-between align-items-center py-2">
                                <div>
                                    <div class="fw-bold">{{ $span->name }}</div>
                                    <small class="text-muted">{{ $span->type->type_id ?? 'unknown' }}</small>
                                </div>
                                <small class="text-muted">{{ $span->created_at->diffForHumans() }}</small>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Export History -->
            <div class="card mt-3">
                <div class="card-header">
                    <h6 class="card-title mb-0">
                        <i class="bi bi-calendar-check me-2"></i>Export History
                    </h6>
                </div>
                <div class="card-body">
                    <div class="text-muted text-center py-3">
                        <i class="bi bi-info-circle me-1"></i>
                        Export history will be tracked here
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Export Progress Modal -->
<div class="modal fade" id="exportProgressModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-download me-2"></i>Exporting Data
                </h5>
            </div>
            <div class="modal-body text-center">
                <div class="spinner-border text-primary mb-3" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mb-0">Preparing your export file...</p>
                <small class="text-muted">This may take a few moments</small>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
$(document).ready(function() {
    let allSpans = [];
    let filteredSpans = [];
    let selectedSpans = new Set();

    // Load spans for selective export
    function loadSpans() {
        $.get('{{ route("admin.data-export.get-stats") }}')
            .done(function(data) {
                // This would need to be implemented to return span list
                // For now, we'll show a placeholder
                $('#spansList').html(`
                    <div class="p-3 text-center text-muted">
                        <i class="bi bi-info-circle me-1"></i>
                        Span list loading functionality will be implemented
                    </div>
                `);
            })
            .fail(function() {
                $('#spansList').html(`
                    <div class="p-3 text-center text-danger">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        Failed to load spans
                    </div>
                `);
            });
    }

    // Handle export form submission
    $('#exportForm').on('submit', function(e) {
        e.preventDefault();
        
        // Show progress modal
        $('#exportProgressModal').modal('show');
        
        // Submit the form
        const form = $(this);
        const formData = new FormData(form[0]);
        
        // Convert to URL parameters for GET request
        const params = new URLSearchParams();
        for (let [key, value] of formData.entries()) {
            params.append(key, value);
        }
        
        // Redirect to download
        window.location.href = form.attr('action') + '?' + params.toString();
        
        // Hide modal after a delay
        setTimeout(function() {
            $('#exportProgressModal').modal('hide');
        }, 2000);
    });

    // Handle selective export
    $('#exportSelectedBtn').on('click', function() {
        if (selectedSpans.size === 0) {
            alert('Please select at least one span to export');
            return;
        }
        
        const spanIds = Array.from(selectedSpans);
        const format = $('input[name="format"]:checked').val();
        
        // Show progress modal
        $('#exportProgressModal').modal('show');
        
        // Submit export request
        $.post('{{ route("admin.data-export.export-selected") }}', {
            span_ids: spanIds,
            format: format,
            _token: $('meta[name="csrf-token"]').attr('content')
        })
        .done(function(response) {
            if (response.success) {
                // Handle successful export
                alert('Export completed successfully!');
            } else {
                alert('Export failed: ' + response.message);
            }
        })
        .fail(function() {
            alert('Export failed. Please try again.');
        })
        .always(function() {
            $('#exportProgressModal').modal('hide');
        });
    });

    // Search functionality
    $('#spanSearch').on('input', function() {
        const searchTerm = $(this).val().toLowerCase();
        filterSpans(searchTerm);
    });

    $('#clearSearch').on('click', function() {
        $('#spanSearch').val('');
        filterSpans('');
    });

    function filterSpans(searchTerm) {
        // This would filter the spans list
        // Implementation depends on how spans are loaded
    }

    // Select all/none functionality
    $('#selectAll').on('click', function() {
        // Implementation for selecting all spans
    });

    $('#deselectAll').on('click', function() {
        selectedSpans.clear();
        updateSelectedCount();
        $('#exportSelectedBtn').prop('disabled', true);
    });

    function updateSelectedCount() {
        $('#selectedCount').text(selectedSpans.size);
        $('#exportSelectedBtn').prop('disabled', selectedSpans.size === 0);
    }

    // Initialize
    loadSpans();
});
</script>
@endpush 