@extends('layouts.app')

@section('page_title')
    Data Import
@endsection

@section('page_tools')
    <div class="d-flex gap-2">
        <a href="{{ route('admin.tools.index') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Back to Tools
        </a>
        <a href="{{ route('admin.data-export.index') }}" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-download me-1"></i>Data Export
        </a>
    </div>
@endsection

@section('content')
<div class="py-4">
    <div class="row">
        <div class="col-12">
            <div class="alert alert-info">
                <i class="bi bi-info-circle me-2"></i>
                <strong>Data Import System</strong> - Import spans from YAML files or ZIP archives containing YAML files.
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Import Form -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-upload me-2"></i>Import Files
                    </h5>
                </div>
                <div class="card-body">
                    <form id="importForm" action="{{ route('admin.data-import.import') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        
                        <!-- File Upload -->
                        <div class="mb-4">
                            <label class="form-label fw-bold">Select Files:</label>
                            <div class="input-group">
                                <input type="file" class="form-control" id="importFiles" name="import_files[]" 
                                       multiple accept=".yaml,.yml,.zip" required>
                                <button class="btn btn-outline-secondary" type="button" id="clearFiles">
                                    <i class="bi bi-x"></i> Clear
                                </button>
                            </div>
                            <div class="form-text">
                                Supported formats: YAML (.yaml, .yml) and ZIP archives containing YAML files. Max 10MB per file.
                            </div>
                        </div>

                        <!-- Import Mode -->
                        <div class="mb-4">
                            <label class="form-label fw-bold">Import Mode:</label>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="import_mode" id="mode-individual" value="individual" checked>
                                        <label class="form-check-label" for="mode-individual">
                                            <strong>Individual</strong>
                                            <br>
                                            <small class="text-muted">Create new spans only</small>
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="import_mode" id="mode-bulk" value="bulk">
                                        <label class="form-check-label" for="mode-bulk">
                                            <strong>Bulk</strong>
                                            <br>
                                            <small class="text-muted">Import all spans at once</small>
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="import_mode" id="mode-merge" value="merge">
                                        <label class="form-check-label" for="mode-merge">
                                            <strong>Merge</strong>
                                            <br>
                                            <small class="text-muted">Update existing spans</small>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- User Assignment -->
                        <div class="mb-4">
                            <label class="form-label fw-bold">Assign to User:</label>
                            <select class="form-select" name="user_id" id="userId">
                                <option value="">Current User ({{ auth()->user()->name }})</option>
                                @foreach(\App\Models\User::with('personalSpan')->get()->sortBy('name') as $user)
                                    <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})</option>
                                @endforeach
                            </select>
                            <div class="form-text">
                                Select which user should own the imported spans
                            </div>
                        </div>

                        <!-- Import Button -->
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg" id="importBtn" disabled>
                                <i class="bi bi-upload me-2"></i>
                                Import Files
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- File Preview -->
            <div class="card mt-4" id="previewCard" style="display: none;">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-eye me-2"></i>File Preview
                    </h5>
                </div>
                <div class="card-body">
                    <div id="previewContent">
                        <!-- Preview content will be loaded here -->
                    </div>
                </div>
            </div>

            <!-- Import Results -->
            <div class="card mt-4" id="resultsCard" style="display: none;">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-check-circle me-2"></i>Import Results
                    </h5>
                </div>
                <div class="card-body">
                    <div id="resultsContent">
                        <!-- Results will be shown here -->
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics and Help -->
        <div class="col-lg-4">
            <!-- Import Statistics -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-graph-up me-2"></i>Import Statistics
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
                                <h4 class="text-success mb-1">{{ number_format($stats['recent_imports']) }}</h4>
                                <small class="text-muted">Recent Imports</small>
                            </div>
                        </div>
                    </div>

                    @if($stats['import_errors'] > 0)
                        <div class="alert alert-warning py-2">
                            <i class="bi bi-exclamation-triangle me-1"></i>
                            {{ $stats['import_errors'] }} import errors
                        </div>
                    @endif
                </div>
            </div>

            <!-- Import Help -->
            <div class="card mt-3">
                <div class="card-header">
                    <h6 class="card-title mb-0">
                        <i class="bi bi-question-circle me-2"></i>Import Help
                    </h6>
                </div>
                <div class="card-body">
                    <h6>Supported Formats:</h6>
                    <ul class="list-unstyled">
                        <li><i class="bi bi-check text-success me-1"></i>Individual YAML files</li>
                        <li><i class="bi bi-check text-success me-1"></i>ZIP archives with YAML files</li>
                        <li><i class="bi bi-check text-success me-1"></i>Bulk export files</li>
                    </ul>

                    <h6>Import Modes:</h6>
                    <ul class="list-unstyled">
                        <li><strong>Individual:</strong> Creates new spans only</li>
                        <li><strong>Bulk:</strong> Imports all spans at once</li>
                        <li><strong>Merge:</strong> Updates existing spans by ID</li>
                    </ul>

                    <div class="alert alert-info py-2 mt-3">
                        <small>
                            <i class="bi bi-info-circle me-1"></i>
                            Large imports may take several minutes to complete.
                        </small>
                    </div>
                </div>
            </div>

            <!-- Recent Imports -->
            <div class="card mt-3">
                <div class="card-header">
                    <h6 class="card-title mb-0">
                        <i class="bi bi-clock-history me-2"></i>Recent Imports
                    </h6>
                </div>
                <div class="card-body">
                    @if(count($recentImports) > 0)
                        <div class="list-group list-group-flush">
                            @foreach($recentImports as $import)
                                <div class="list-group-item d-flex justify-content-between align-items-center py-2">
                                    <div>
                                        <div class="fw-bold">{{ $import['filename'] }}</div>
                                        <small class="text-muted">{{ $import['status'] }}</small>
                                    </div>
                                    <small class="text-muted">{{ $import['date'] }}</small>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-muted text-center py-3">
                            <i class="bi bi-info-circle me-1"></i>
                            No recent imports
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Import Progress Modal -->
<div class="modal fade" id="importProgressModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-upload me-2"></i>Importing Data
                </h5>
            </div>
            <div class="modal-body text-center">
                <div class="spinner-border text-primary mb-3" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mb-0">Processing your import files...</p>
                <small class="text-muted">This may take several minutes for large files</small>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
$(document).ready(function() {
    let selectedFiles = [];

    // Handle file selection
    $('#importFiles').on('change', function() {
        const files = this.files;
        selectedFiles = Array.from(files);
        
        if (selectedFiles.length > 0) {
            $('#importBtn').prop('disabled', false);
            showFilePreview(selectedFiles[0]);
        } else {
            $('#importBtn').prop('disabled', true);
            hideFilePreview();
        }
    });

    // Clear files
    $('#clearFiles').on('click', function() {
        $('#importFiles').val('');
        selectedFiles = [];
        $('#importBtn').prop('disabled', true);
        hideFilePreview();
    });

    // Show file preview
    function showFilePreview(file) {
        const formData = new FormData();
        formData.append('import_file', file);
        formData.append('_token', $('meta[name="csrf-token"]').attr('content'));

        $('#previewCard').show();
        $('#previewContent').html(`
            <div class="text-center py-3">
                <div class="spinner-border spinner-border-sm text-primary me-2"></div>
                Analyzing file...
            </div>
        `);

        $.ajax({
            url: '{{ route("admin.data-import.preview") }}',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    displayPreview(response.preview);
                } else {
                    $('#previewContent').html(`
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle me-1"></i>
                            ${response.message}
                        </div>
                    `);
                }
            },
            error: function() {
                $('#previewContent').html(`
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        Failed to preview file
                    </div>
                `);
            }
        });
    }

    // Display file preview
    function displayPreview(preview) {
        let html = `
            <div class="row">
                <div class="col-md-6">
                    <strong>File:</strong> ${preview.filename}<br>
                    <strong>Size:</strong> ${formatFileSize(preview.file_size)}<br>
                    <strong>Spans Found:</strong> ${preview.spans_found}
                </div>
                <div class="col-md-6">
                    <strong>Sample Spans:</strong>
                    <ul class="list-unstyled mt-1">
        `;

        preview.sample_spans.forEach(span => {
            html += `<li><small>• ${span.name} (${span.type})</small></li>`;
        });

        html += `
                    </ul>
                </div>
            </div>
        `;

        if (preview.files_in_zip && preview.files_in_zip.length > 0) {
            html += `
                <div class="mt-3">
                    <strong>Files in ZIP:</strong>
                    <ul class="list-unstyled mt-1">
            `;
            preview.files_in_zip.slice(0, 5).forEach(file => {
                html += `<li><small>• ${file}</small></li>`;
            });
            if (preview.files_in_zip.length > 5) {
                html += `<li><small>• ... and ${preview.files_in_zip.length - 5} more</small></li>`;
            }
            html += `</ul></div>`;
        }

        if (preview.errors && preview.errors.length > 0) {
            html += `
                <div class="alert alert-warning mt-3">
                    <strong>Warnings:</strong>
                    <ul class="mb-0 mt-1">
            `;
            preview.errors.forEach(error => {
                html += `<li>${error}</li>`;
            });
            html += `</ul></div>`;
        }

        $('#previewContent').html(html);
    }

    // Hide file preview
    function hideFilePreview() {
        $('#previewCard').hide();
        $('#previewContent').empty();
    }

    // Format file size
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    // Handle import form submission
    $('#importForm').on('submit', function(e) {
        e.preventDefault();
        
        if (selectedFiles.length === 0) {
            alert('Please select at least one file to import');
            return;
        }

        // Show progress modal
        $('#importProgressModal').modal('show');
        
        // Submit the form
        const form = $(this);
        const formData = new FormData(form[0]);
        
        $.ajax({
            url: form.attr('action'),
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    displayResults(response);
                } else {
                    alert('Import failed: ' + response.message);
                }
            },
            error: function(xhr) {
                let errorMsg = 'Import failed';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                }
                alert(errorMsg);
            },
            complete: function() {
                $('#importProgressModal').modal('hide');
            }
        });
    });

    // Display import results
    function displayResults(response) {
        const summary = response.summary;
        const results = response.results;

        let html = `
            <div class="alert alert-success">
                <h6><i class="bi bi-check-circle me-1"></i>Import Summary</h6>
                <div class="row">
                    <div class="col-md-3">
                        <strong>Files:</strong> ${summary.total_files}
                    </div>
                    <div class="col-md-3">
                        <strong>Processed:</strong> ${summary.total_processed}
                    </div>
                    <div class="col-md-3">
                        <strong>Success:</strong> <span class="text-success">${summary.total_success}</span>
                    </div>
                    <div class="col-md-3">
                        <strong>Errors:</strong> <span class="text-danger">${summary.total_errors}</span>
                    </div>
                </div>
            </div>
        `;

        // Show detailed results for each file
        results.forEach(result => {
            html += `
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="card-title mb-0">${result.filename}</h6>
                    </div>
                    <div class="card-body">
                        <div class="row mb-2">
                            <div class="col-md-4">
                                <strong>Processed:</strong> ${result.processed}
                            </div>
                            <div class="col-md-4">
                                <strong>Success:</strong> <span class="text-success">${result.success}</span>
                            </div>
                            <div class="col-md-4">
                                <strong>Errors:</strong> <span class="text-danger">${result.errors}</span>
                            </div>
                        </div>
            `;

            if (result.details && result.details.length > 0) {
                html += `<h6>Details:</h6><ul class="list-unstyled">`;
                result.details.forEach(detail => {
                    const icon = detail.type === 'success' ? 'check-circle text-success' : 'exclamation-triangle text-danger';
                    html += `<li><i class="bi bi-${icon} me-1"></i>${detail.message}</li>`;
                });
                html += `</ul>`;
            }

            html += `</div></div>`;
        });

        $('#resultsCard').show();
        $('#resultsContent').html(html);
    }
});
</script>
@endpush 