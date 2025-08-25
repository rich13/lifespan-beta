@extends('layouts.app')

@section('title', 'Blue Plaque Import')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-12">
            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb" class="mb-3">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.import.index') }}">Import</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Blue Plaques</li>
                </ol>
            </nav>
            
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="bi bi-geo-alt-fill me-2"></i>
                        London Blue Plaque Import
                    </h3>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <h5><i class="bi bi-info-circle me-2"></i>About Plaque Imports</h5>
                        <p class="mb-0">
                            This importer processes commemorative plaques and memorials. 
                            <strong>Currently filtering for person plaques only</strong> (where lead_subject_type is "man" or "woman").
                            It will create <strong>thing spans</strong> for the plaques, <strong>person spans</strong> for the commemorated individuals, 
                            and <strong>place spans</strong> for the locations, then connect them together.
                        </p>
                    </div>

                    <!-- Plaque Type Selection -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Plaque Type</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="plaqueType" id="londonBlue" value="london_blue" checked>
                                                <label class="form-check-label" for="londonBlue">
                                                    <strong>London Blue Plaques</strong><br>
                                                    <small class="text-muted">English Heritage blue plaques (3,635 plaques)</small>
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="plaqueType" id="londonGreen" value="london_green">
                                                <label class="form-check-label" for="londonGreen">
                                                    <strong>London Green Plaques</strong><br>
                                                    <small class="text-muted">Local authority green plaques</small>
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="plaqueType" id="custom" value="custom">
                                                <label class="form-check-label" for="custom">
                                                    <strong>Custom Import</strong><br>
                                                    <small class="text-muted">Upload your own CSV file</small>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Data Source Info -->
                    <div class="row mb-4" id="dataSourceInfo">
                        <div class="col-md-6">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h6 class="card-title">Data Source</h6>
                                    <div id="dataSourceContent">
                                        <p class="card-text small">
                                            <strong>Source:</strong> OpenPlaques London Dataset<br>
                                            <strong>URL:</strong> <a href="https://s3.eu-west-2.amazonaws.com/openplaques/open-plaques-london-2023-11-10.csv" target="_blank">CSV Download</a><br>
                                            <strong>Format:</strong> CSV with 3,635 plaques<br>
                                            <strong>Last Updated:</strong> November 2023
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h6 class="card-title">What Will Be Created</h6>
                                    <ul class="card-text small mb-0">
                                        <li><strong>Thing spans</strong> for each person plaque</li>
                                        <li><strong>Person spans</strong> for commemorated individuals (man/woman only)</li>
                                        <li><strong>Place spans</strong> for plaque locations</li>
                                        <li><strong>Connections</strong> linking people to plaques to places</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Import Controls -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Import Controls</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <button type="button" class="btn btn-info w-100 mb-2" id="previewBtn">
                                                <i class="bi bi-eye me-2"></i>
                                                Preview Data
                                            </button>
                                        </div>
                                        <div class="col-md-4">
                                            <button type="button" class="btn btn-warning w-100 mb-2" id="batchBtn">
                                                <i class="bi bi-arrow-repeat me-2"></i>
                                                Process Batch
                                            </button>
                                        </div>
                                        <div class="col-md-4">
                                            <button type="button" class="btn btn-success w-100 mb-2" id="processAllBtn">
                                                <i class="bi bi-play-fill me-2"></i>
                                                Process All
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <!-- Batch Settings -->
                                    <div class="row mt-3" id="batchSettings" style="display: none;">
                                        <div class="col-md-6">
                                            <label for="batchSize" class="form-label">Batch Size</label>
                                            <select class="form-select" id="batchSize">
                                                <option value="10">10 plaques</option>
                                                <option value="25">25 plaques</option>
                                                <option value="50" selected>50 plaques</option>
                                                <option value="100">100 plaques</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="batchOffset" class="form-label">Start From</label>
                                            <input type="number" class="form-control" id="batchOffset" value="0" min="0">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Progress Section -->
                    <div class="row mb-4" id="progressSection" style="display: none;">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Import Progress</h5>
                                </div>
                                <div class="card-body">
                                    <div class="progress mb-3">
                                        <div class="progress-bar" id="progressBar" role="progressbar" style="width: 0%"></div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-3">
                                            <small class="text-muted">Processed:</small>
                                            <div id="processedCount">0</div>
                                        </div>
                                        <div class="col-md-3">
                                            <small class="text-muted">Created:</small>
                                            <div id="createdCount">0</div>
                                        </div>
                                        <div class="col-md-3">
                                            <small class="text-muted">Errors:</small>
                                            <div id="errorCount">0</div>
                                        </div>
                                        <div class="col-md-3">
                                            <small class="text-muted">Remaining:</small>
                                            <div id="remainingCount">0</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Results Section -->
                    <div class="row mb-4" id="resultsSection" style="display: none;">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Import Results</h5>
                                </div>
                                <div class="card-body">
                                    <div id="resultsContent"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Statistics Section -->
                    <div class="row mb-4" id="statsSection" style="display: none;">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Current Statistics</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row" id="statsContent">
                                        <!-- Stats will be loaded here -->
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Preview Modal -->
<div class="modal fade" id="previewModal" tabindex="-1">
    <div class="modal-dialog modal-fullscreen-lg-down modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Blue Plaque Data Preview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="previewContent"></div>
            </div>
        </div>
    </div>
</div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
// Global variables
let currentOffset = 0;
let totalPlaques = 0;
let isProcessing = false;

// Global functions for onclick handlers
function importSinglePlaque(plaqueIndex) {
    const plaqueType = $('input[name="plaqueType"]:checked').val();
    
    if (!confirm(`Are you sure you want to import plaque ${plaqueIndex + 1}? This will create the spans and connections shown in the preview.`)) {
        return;
    }
    
    // Show spinner and disable button
    $('#importSingleBtn').prop('disabled', true).html('<i class="bi bi-hourglass-split me-2"></i>Importing...');
    
    // Show loading overlay
    showImportLoading();
    
    $.ajax({
        url: '{{ route("admin.import.blue-plaques.process-single") }}',
        method: 'POST',
        data: {
            plaque_type: plaqueType,
            plaque_index: plaqueIndex,
            _token: $('meta[name="csrf-token"]').attr('content')
        },
        success: function(response) {
            if (response.success) {
                showImportResults(response);
            } else {
                showImportError('Import failed: ' + response.message, response.details || {});
            }
        },
        error: function(xhr) {
            let errorMessage = 'Import failed: Unknown error';
            let errorDetails = {};
            
            if (xhr.responseJSON) {
                errorMessage = xhr.responseJSON.message || 'Import failed';
                errorDetails = xhr.responseJSON.details || {};
            } else if (xhr.status === 0) {
                errorMessage = 'Import failed: Network error - please check your connection';
            } else if (xhr.status === 500) {
                errorMessage = 'Import failed: Server error - check the logs for details';
            } else if (xhr.status === 422) {
                errorMessage = 'Import failed: Validation error';
                errorDetails = xhr.responseJSON?.errors || {};
            }
            
            showImportError(errorMessage, errorDetails);
        },
        complete: function() {
            // Hide loading overlay
            hideImportLoading();
            // Reset button
            $('#importSingleBtn').prop('disabled', false).html('<i class="bi bi-download me-2"></i>Import This Plaque');
        }
    });
}

function showImportLoading() {
    const loadingHtml = `
        <div class="text-center py-5">
            <div class="spinner-border text-primary mb-3" role="status">
                <span class="visually-hidden">Importing...</span>
            </div>
            <h5>Importing Plaque...</h5>
            <p class="text-muted">Creating spans and connections in the database</p>
        </div>
    `;
    $('#previewContent').html(loadingHtml);
}

function hideImportLoading() {
    // Loading is hidden when results are shown
}

function showImportError(message, details) {
    let detailsHtml = '';
    
    if (Object.keys(details).length > 0) {
        detailsHtml = `
            <div class="mt-3">
                <h6>Error Details:</h6>
                <pre class="bg-light p-2 rounded"><code>${JSON.stringify(details, null, 2)}</code></pre>
            </div>
        `;
    }
    
    const errorHtml = `
        <div class="alert alert-danger">
            <h6>❌ Import Failed</h6>
            <p><strong>Error:</strong> ${message}</p>
            ${detailsHtml}
        </div>
        
        <div class="mt-3">
            <button class="btn btn-primary" onclick="loadPreview(${parseInt($('#previewContent').data('current-start-index') || 0)})">
                <i class="bi bi-arrow-clockwise me-2"></i>Try Again
            </button>
            <button class="btn btn-secondary" onclick="loadPreview(${parseInt($('#previewContent').data('current-start-index') || 0) + 1})">
                Next Plaque →
            </button>
        </div>
    `;
    
    $('#previewContent').html(errorHtml);
}

function showImportResults(response) {
    const resultHtml = `
        <div class="alert alert-success">
            <h6>✅ Import Successful!</h6>
            <p><strong>Plaque:</strong> ${response.details.plaque_name}</p>
            <p><strong>Created:</strong> ${response.details.created_spans || 0} spans, ${response.details.created_connections || 0} connections</p>
            ${response.details.person_name ? `<p><strong>Person:</strong> ${response.details.person_name}</p>` : ''}
            ${response.details.location_name ? `<p><strong>Location:</strong> ${response.details.location_name}</p>` : ''}
            ${response.details.photo_name ? `<p><strong>Plaque Photo:</strong> ${response.details.photo_name}</p>` : ''}
            ${response.details.person_photo_name ? `<p><strong>Person Photo:</strong> ${response.details.person_photo_name}</p>` : ''}
        </div>
        
        <div class="mt-3">
            <button class="btn btn-primary" onclick="loadPreview(${parseInt($('#previewContent').data('current-start-index') || 0) + 1})">
                Next Plaque →
            </button>
            <button class="btn btn-secondary" onclick="$('#previewModal').modal('hide')">
                Close
            </button>
        </div>
    `;
    
    $('#previewContent').html(resultHtml);
}

function navigatePreview(direction) {
    const currentStartIndex = parseInt($('#previewContent').data('current-start-index') || 0);
    const newStartIndex = direction === 'next' ? currentStartIndex + 1 : currentStartIndex - 1;
    
    if (newStartIndex >= 0) {
        loadPreview(newStartIndex);
    }
}

function showPreview(data) {
    let html = `
        <div class="alert alert-info">
            <h5>Preview Summary</h5>
            <p><strong>Total plaques found:</strong> ${data.total_plaques}</p>
            <p><strong>Showing:</strong> Plaque ${data.current_start_index + 1} of ${data.total_plaques}</p>
            <p><strong>Plaque type:</strong> ${data.plaque_type}</p>
            <p><strong>Filter:</strong> Person plaques only (man/woman)</p>
        </div>
        
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <button class="btn btn-secondary" onclick="navigatePreview('prev')" ${data.current_start_index === 0 ? 'disabled' : ''}>
                    ← Previous
                </button>
                <span class="text-muted ms-2 me-2">Plaque ${data.current_start_index + 1} of ${data.total_plaques}</span>
                <button class="btn btn-secondary" onclick="navigatePreview('next')" ${!data.has_more ? 'disabled' : ''}>
                    Next →
                </button>
            </div>
            <div>
                <button class="btn btn-success" onclick="importSinglePlaque(${data.current_start_index})" id="importSingleBtn">
                    <i class="bi bi-download me-2"></i>Import This Plaque
                </button>
            </div>
        </div>
    `;
    
    data.plaques.forEach(function(item, index) {
        const overallStatus = item.validation.errors && item.validation.errors.length > 0 ? 'error' : 
                            item.validation.warnings && item.validation.warnings.length > 0 ? 'warning' : 'ready';
        const statusClass = overallStatus === 'error' ? 'danger' : 
                          overallStatus === 'warning' ? 'warning' : 'success';
        const statusText = overallStatus === 'error' ? 'Error' : 
                         overallStatus === 'warning' ? 'Warning' : 'Ready';
        
        html += `
            <div class="card mb-3 border-${statusClass}">
                <div class="card-header bg-${statusClass} text-white">
                    <h6 class="mb-0">
                        Plaque ${item.index + 1} - ${statusText}
                        <span class="badge bg-light text-dark ms-2">
                            ${item.validation.items ? item.validation.items.length : 0} items to create
                        </span>
                    </h6>
                </div>
                <div class="card-body">
                    <!-- Original Data -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <h6>Original Data</h6>
                            <p><strong>Title:</strong> ${item.plaque.title || 'N/A'}</p>
                            <p><strong>Person:</strong> ${item.plaque.lead_subject_name || 'N/A'}</p>
                            <p><strong>Address:</strong> ${item.plaque.address || 'N/A'}</p>
                            <p><strong>Inscription:</strong> ${item.plaque.inscription || 'N/A'}</p>
                        </div>
                        <div class="col-md-6">
                            <h6>Additional Data</h6>
                            <p><strong>Erected:</strong> ${item.plaque.erected || 'Unknown'}</p>
                            <p><strong>Born:</strong> ${item.plaque.lead_subject_born_in || 'Unknown'}</p>
                            <p><strong>Died:</strong> ${item.plaque.lead_subject_died_in || 'Unknown'}</p>
                            <p><strong>Photos:</strong> ${item.plaque.main_photo ? 'Plaque photo available' : 'No plaque photo'} | ${item.plaque.lead_subject_image ? 'Person photo available' : 'No person photo'}</p>
                        </div>
                    </div>
                    
                    <!-- Overall Validation Results -->
                    ${item.validation.errors && item.validation.errors.length > 0 ? `
                        <div class="alert alert-danger">
                            <h6>Overall Errors:</h6>
                            <ul>
                                ${item.validation.errors.map(error => `<li>${error}</li>`).join('')}
                            </ul>
                        </div>
                    ` : ''}
                    
                    ${item.validation.warnings && item.validation.warnings.length > 0 ? `
                        <div class="alert alert-warning">
                            <h6>Overall Warnings:</h6>
                            <ul>
                                ${item.validation.warnings.map(warning => `<li>${warning}</li>`).join('')}
                            </ul>
                        </div>
                    ` : ''}
                    
                    <!-- Unified Items Table -->
                    <div class="mb-3">
                        <h6>Items to Create (Spans & Connections):</h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th>Type</th>
                                        <th>Name/From</th>
                                        <th>To</th>
                                        <th>Subtype/Connection</th>
                                        <th>Start Year</th>
                                        <th>End Year</th>
                                        <th>Validation</th>
                                        <th>Details</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${(item.validation.items || []).map((item, itemIndex) => {
                                        const itemStatus = item.validation.status;
                                        const itemStatusClass = itemStatus === 'error' ? 'danger' : 
                                                              itemStatus === 'warning' ? 'warning' : 'success';
                                        const itemStatusText = itemStatus === 'error' ? 'Error' : 
                                                             itemStatus === 'warning' ? 'Warning' : 'Ready';
                                        
                                        let nameColumn, toColumn, typeColumn;
                                        
                                        if (item.type === 'span') {
                                            nameColumn = item.name;
                                            toColumn = '-';
                                            typeColumn = item.subtype || 'N/A';
                                        } else {
                                            nameColumn = item.from;
                                            toColumn = item.to;
                                            typeColumn = item.connection_type;
                                        }
                                        
                                        return `
                                            <tr class="table-${itemStatusClass}">
                                                <td>
                                                    <span class="badge bg-${item.type === 'span' ? 'primary' : 'secondary'}">
                                                        ${item.type === 'span' ? 'Span' : 'Connection'}
                                                    </span>
                                                    ${item.type === 'span' ? `<br><small>${item.span_type}</small>` : ''}
                                                </td>
                                                <td>${nameColumn}</td>
                                                <td>${toColumn}</td>
                                                <td>${typeColumn}</td>
                                                <td>${item.start_year || 'N/A'}</td>
                                                <td>${item.end_year || 'N/A'}</td>
                                                <td>
                                                    <span class="badge bg-${itemStatusClass}">${itemStatusText}</span>
                                                    ${item.validation.errors.length > 0 ? `
                                                        <br><small class="text-danger">
                                                            ${item.validation.errors.slice(0, 2).join(', ')}
                                                            ${item.validation.errors.length > 2 ? '...' : ''}
                                                        </small>
                                                    ` : ''}
                                                    ${item.validation.warnings.length > 0 ? `
                                                        <br><small class="text-warning">
                                                            ${item.validation.warnings.slice(0, 2).join(', ')}
                                                            ${item.validation.warnings.length > 2 ? '...' : ''}
                                                        </small>
                                                    ` : ''}
                                                </td>
                                                <td>
                                                    <small>
                                                        ${item.description || 'N/A'}
                                                        ${item.metadata ? `<br><strong>Metadata:</strong> ${JSON.stringify(item.metadata).substring(0, 100)}${JSON.stringify(item.metadata).length > 100 ? '...' : ''}` : ''}
                                                    </small>
                                                </td>
                                            </tr>
                                        `;
                                    }).join('')}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        `;
    });
    
    $('#previewContent').html(html);
    $('#previewModal').modal('show');
}

function showError(message) {
    $('#resultsContent').html(`
        <div class="alert alert-danger">
            <strong>Error:</strong> ${message}
        </div>
    `);
    $('#resultsSection').show();
}

function loadPreview(startIndex = 0) {
    const plaqueType = $('input[name="plaqueType"]:checked').val();
    
    $('#previewBtn').prop('disabled', true).html('<i class="bi bi-hourglass-split me-2"></i>Loading...');
    
    $.ajax({
        url: '{{ route("admin.import.blue-plaques.preview") }}',
        method: 'POST',
        data: {
            plaque_type: plaqueType,
            limit: 1,
            start_index: startIndex,
            _token: $('meta[name="csrf-token"]').attr('content')
        },
        success: function(response) {
            if (response.success) {
                $('#previewContent').data('current-start-index', startIndex);
                showPreview(response.data);
                totalPlaques = response.data.total_plaques;
                $('#remainingCount').text(totalPlaques);
            } else {
                showError('Preview failed: ' + response.message);
            }
        },
        error: function(xhr) {
            showError('Preview failed: ' + (xhr.responseJSON?.message || 'Unknown error'));
        },
        complete: function() {
            $('#previewBtn').prop('disabled', false).html('<i class="bi bi-eye me-2"></i>Preview Data');
        }
    });
}

$(document).ready(function() {
    // Set up CSRF token for all AJAX requests
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    // Preview Data
    $('#previewBtn').click(function() {
        if (isProcessing) return;
        
        const plaqueType = $('input[name="plaqueType"]:checked').val();
        
        if (!plaqueType) {
            showError('Please select a plaque type');
            return;
        }
        
        $(this).prop('disabled', true).html('<i class="bi bi-hourglass-split me-2"></i>Loading...');
        
        // Load first plaque preview
        loadPreview(0);
    });

    // Process Batch
    $('#batchBtn').click(function() {
        if (isProcessing) return;
        
        const batchSize = parseInt($('#batchSize').val());
        const offset = parseInt($('#batchOffset').val());
        const plaqueType = $('input[name="plaqueType"]:checked').val();
        
        processBatch(batchSize, offset, plaqueType);
    });

    // Process All
    $('#processAllBtn').click(function() {
        if (isProcessing) return;
        
        const plaqueType = $('input[name="plaqueType"]:checked').val();
        const plaqueTypeName = $('input[name="plaqueType"]:checked').next('label').find('strong').text();
        
        if (!confirm(`This will process all ${plaqueTypeName}. This may take a while. Continue?`)) {
            return;
        }
        
        processAll(plaqueType);
    });

    // Show/Hide Batch Settings
    $('#batchBtn').click(function() {
        $('#batchSettings').toggle();
    });

    // Load Statistics
    loadStats();
    
    function getColourClass(colour) {
        const colours = {
            'blue': 'primary',
            'green': 'success',
            'brown': 'warning',
            'grey': 'secondary',
            'red': 'danger',
            'pink': 'info',
            'brass': 'warning',
            'bronze': 'warning'
        };
        return colours[colour] || 'primary';
    }

    function processBatch(batchSize, offset, plaqueType) {
        if (isProcessing) return;
        
        isProcessing = true;
        showProgress();
        
        $.post('{{ route("admin.import.blue-plaques.process-batch") }}', {
            plaque_type: plaqueType,
            batch_size: batchSize,
            offset: offset
        })
        .done(function(response) {
            if (response.success) {
                updateProgress(response);
                showResults(response);
                
                if (!response.completed) {
                    // Continue with next batch
                    setTimeout(function() {
                        processBatch(batchSize, response.offset, plaqueType);
                    }, 1000);
                } else {
                    isProcessing = false;
                    loadStats(plaqueType);
                }
            } else {
                showError('Batch processing failed: ' + response.message);
                isProcessing = false;
            }
        })
        .fail(function(xhr) {
            showError('Batch processing failed: ' + (xhr.responseJSON?.message || 'Unknown error'));
            isProcessing = false;
        });
    }

    function processAll(plaqueType) {
        if (isProcessing) return;
        
        isProcessing = true;
        showProgress();
        
        $.post('{{ route("admin.import.blue-plaques.process-all") }}', {
            plaque_type: plaqueType
        })
        .done(function(response) {
            if (response.success) {
                updateProgress(response);
                showResults(response);
                isProcessing = false;
                loadStats(plaqueType);
            } else {
                showError('Full import failed: ' + response.message);
                isProcessing = false;
            }
        })
        .fail(function(xhr) {
            showError('Full import failed: ' + (xhr.responseJSON?.message || 'Unknown error'));
            isProcessing = false;
        });
    }

    function showProgress() {
        $('#progressSection').show();
        $('#progressBar').css('width', '0%');
        $('#processedCount').text('0');
        $('#createdCount').text('0');
        $('#errorCount').text('0');
    }

    function updateProgress(response) {
        const progress = (response.offset / response.total_plaques) * 100;
        $('#progressBar').css('width', progress + '%');
        $('#processedCount').text(response.processed);
        $('#createdCount').text(response.created);
        $('#errorCount').text(response.errors.length);
        $('#remainingCount').text(response.total_plaques - response.offset);
    }

    function showResults(response) {
        let html = `
            <div class="alert alert-success">
                <strong>Batch completed!</strong> Processed ${response.processed} plaques, created ${response.created} new items.
            </div>
        `;
        
        if (response.errors.length > 0) {
            html += `
                <div class="alert alert-warning">
                    <strong>${response.errors.length} errors occurred:</strong>
                    <ul class="mb-0">
                        ${response.errors.slice(0, 5).map(error => `<li>${error}</li>`).join('')}
                        ${response.errors.length > 5 ? `<li>... and ${response.errors.length - 5} more</li>` : ''}
                    </ul>
                </div>
            `;
        }
        
        if (response.details && response.details.length > 0) {
            html += `
                <h6>Sample Created Items:</h6>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Plaque</th>
                                <th>Person</th>
                                <th>Location</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${response.details.map(detail => `
                                <tr>
                                    <td>${detail.plaque_name}</td>
                                    <td>${detail.person_name || 'N/A'}</td>
                                    <td>${detail.location_name || 'N/A'}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            `;
        }
        
        $('#resultsContent').html(html);
        $('#resultsSection').show();
    }



    function loadStats() {
        $.get('{{ route("admin.import.blue-plaques.stats") }}')
        .done(function(response) {
            if (response.success) {
                const stats = response.stats;
                $('#statsContent').html(`
                    <div class="col-md-3">
                        <div class="text-center">
                            <h4 class="text-primary">${stats.total_plaques}</h4>
                            <small class="text-muted">Blue Plaques</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center">
                            <h4 class="text-success">${stats.total_people}</h4>
                            <small class="text-muted">People</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center">
                            <h4 class="text-info">${stats.total_locations}</h4>
                            <small class="text-muted">Locations</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center">
                            <h4 class="text-warning">${stats.total_connections}</h4>
                            <small class="text-muted">Connections</small>
                        </div>
                    </div>
                `);
                $('#statsSection').show();
            }
        });
    }
});
</script>
@endpush
