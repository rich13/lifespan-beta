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
                                                    <small class="text-muted">English Heritage blue plaques (~3,635 plaques)</small>
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

                    <!-- Import Controls -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Import Controls</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <button type="button" class="btn btn-info w-100 mb-2" id="previewBtn">
                                                <i class="bi bi-eye me-2"></i>
                                                Preview Data
                                            </button>
                                        </div>
                                        <div class="col-md-6">
                                            <button type="button" class="btn btn-success w-100 mb-2" id="importAllBtn">
                                                <i class="bi bi-play-fill me-2"></i>
                                                Import All Plaques
                                            </button>
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
                                    <h5 class="card-title mb-0">
                                        <i class="bi bi-arrow-repeat me-2" id="progressSpinner"></i>
                                        Import Progress
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <!-- Progress Bar -->
                                    <div class="progress mb-3" style="height: 25px;">
                                        <div class="progress-bar progress-bar-striped progress-bar-animated" id="progressBar" role="progressbar" style="width: 0%">
                                            <span id="progressText">0%</span>
                                        </div>
                                    </div>
                                    
                                    <!-- Status -->
                                    <div class="alert alert-info mb-3">
                                        <i class="bi bi-info-circle me-2"></i>
                                        <span id="statusText">Preparing import...</span>
                                        <br><small class="text-muted">Processing 2 plaques per batch to avoid timeouts. Each batch takes about 5-15 seconds.</small>
                                    </div>
                                    
                                    <!-- Progress Stats -->
                                    <div class="row mb-3">
                                        <div class="col-md-3">
                                            <div class="text-center">
                                                <div class="h4 text-primary" id="processedCount">0</div>
                                                <small class="text-muted">Processed</small>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="text-center">
                                                <div class="h4 text-success" id="createdCount">0</div>
                                                <small class="text-muted">Processed</small>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="text-center">
                                                <div class="h4 text-warning" id="skippedCount">0</div>
                                                <small class="text-muted">Skipped</small>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="text-center">
                                                <div class="h4 text-info" id="totalCount">0</div>
                                                <small class="text-muted">Total</small>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="text-center">
                                                <div class="h4 text-danger" id="errorCount">0</div>
                                                <small class="text-muted">Errors</small>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Cancel Button -->
                                    <div class="text-center">
                                        <button type="button" class="btn btn-danger" id="cancelImportBtn">
                                            <i class="bi bi-x-circle me-2"></i>Cancel Import
                                        </button>
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
@endsection

@push('scripts')
<style>
@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
</style>
<script>
// Simple import system - just import all data and show progress
let isProcessing = false;
let currentOffset = 0;
let totalPlaques = 0;
let cumulativeProcessed = 0;
let cumulativeCreated = 0;
let cumulativeSkipped = 0;
let cumulativeErrors = 0;

$(document).ready(function() {
    // Set up CSRF token for all AJAX requests
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    // Preview Data
    $('#previewBtn').click(function() {
        const plaqueType = $('input[name="plaqueType"]:checked').val();
        
        $(this).prop('disabled', true).html('<i class="bi bi-hourglass-split me-2"></i>Loading...');
        
        $.post('{{ route("admin.import.blue-plaques.preview") }}', {
            plaque_type: plaqueType,
            limit: 1,
            start_index: 0
        })
        .done(function(response) {
            if (response.success) {
                showPreview(response.data);
            } else {
                showError('Preview failed: ' + response.message);
            }
        })
        .fail(function(xhr) {
            showError('Preview failed: ' + (xhr.responseJSON?.message || 'Unknown error'));
        })
        .always(function() {
            $('#previewBtn').prop('disabled', false).html('<i class="bi bi-eye me-2"></i>Preview Data');
        });
    });

    // Import All Plaques
    $('#importAllBtn').click(function() {
        if (isProcessing) return;
        
        const plaqueType = $('input[name="plaqueType"]:checked').val();
        
        if (!confirm(`This will import all ${plaqueType === 'london_blue' ? '3,635' : 'plaques'} from the selected data source. Continue?`)) {
            return;
        }
        
        startImport(plaqueType);
    });

    // Cancel Import
    $('#cancelImportBtn').click(function() {
        if (confirm('Are you sure you want to cancel the import?')) {
            isProcessing = false;
            $('#progressSpinner').removeClass('bi-arrow-clockwise').addClass('bi-exclamation-triangle-fill').css('animation', 'none');
            $('#statusText').text('Import cancelled');
        }
    });

    // Load initial statistics
    loadStats();
});

function startImport(plaqueType) {
    console.log('Starting import for plaque type:', plaqueType);
    
    isProcessing = true;
    currentOffset = 0;
    
    // Reset cumulative totals
    cumulativeProcessed = 0;
    cumulativeCreated = 0;
    cumulativeSkipped = 0;
    cumulativeErrors = 0;
    
    console.log('Initial values:', {
        isProcessing: isProcessing,
        currentOffset: currentOffset,
        cumulativeProcessed: cumulativeProcessed,
        cumulativeCreated: cumulativeCreated,
        cumulativeSkipped: cumulativeSkipped,
        cumulativeErrors: cumulativeErrors
    });
    
    showProgress();
    
    // Start the first batch
    processNextBatch(plaqueType);
}

function processNextBatch(plaqueType) {
    if (!isProcessing) return;
    
    const batchSize = 2; // Process 2 plaques per batch to avoid timeout
    
    console.log('Processing batch:', {
        plaqueType: plaqueType,
        currentOffset: currentOffset,
        batchSize: batchSize,
        totalPlaques: totalPlaques
    });
    
    // Update status to show we're processing
    const currentBatch = Math.floor(currentOffset / batchSize) + 1;
    const totalBatches = Math.ceil(totalPlaques / batchSize);
    const remainingPlaques = Math.max(0, totalPlaques - currentOffset);
    
    console.log('Batch info:', {
        currentBatch: currentBatch,
        totalBatches: totalBatches,
        remainingPlaques: remainingPlaques
    });
    
    $('#statusText').text(`Processing batch ${currentBatch} of ${totalBatches} (${remainingPlaques} plaques remaining)...`);
    
    $.ajax({
        url: '{{ route("admin.import.blue-plaques.process-batch") }}',
        method: 'POST',
        data: {
            plaque_type: plaqueType,
            batch_size: batchSize,
            offset: currentOffset
        },
        timeout: 30000, // 30 second timeout to be safe
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    })
    .done(function(response) {
        console.log('Batch response:', response);
        
        if (response.success) {
            console.log('Response data:', response.data);
            
            // Update cumulative totals from this batch
            cumulativeProcessed += response.data.processed;
            cumulativeCreated += response.data.created;
            cumulativeSkipped += response.data.skipped;
            cumulativeErrors += response.data.errors.length;
            
            console.log('Updated cumulative totals:', {
                cumulativeProcessed: cumulativeProcessed,
                cumulativeCreated: cumulativeCreated,
                cumulativeSkipped: cumulativeSkipped,
                cumulativeErrors: cumulativeErrors
            });
            
            updateProgress(response.data);
            
            if (!response.data.is_last_batch) {
                // Continue with next batch
                currentOffset = response.data.next_offset;
                console.log('Next offset:', currentOffset);
                setTimeout(function() {
                    processNextBatch(plaqueType);
                }, 500); // Reduced delay to 0.5 seconds for faster processing
            } else {
                // Import completed
                console.log('Import completed!');
                isProcessing = false;
                $('#progressSpinner').removeClass('bi-arrow-clockwise').addClass('bi-check-circle-fill').css('animation', 'none');
                $('#statusText').text('Import completed successfully!');
                completeImport();
                loadStats();
            }
        } else {
            console.error('Batch failed:', response.message);
            showError('Import failed: ' + response.message);
            isProcessing = false;
        }
    })
    .fail(function(xhr) {
        console.error('AJAX failed:', xhr);
        console.error('Response text:', xhr.responseText);
        console.error('Status:', xhr.status);
        console.error('Status text:', xhr.statusText);
        
        let errorMessage = 'Import failed: ' + (xhr.responseJSON?.message || 'Unknown error');
        
        // Handle specific timeout errors
        if (xhr.status === 504) {
            errorMessage = 'Request timed out. The batch may be too large. Try reducing the batch size.';
        } else if (xhr.status === 0) {
            errorMessage = 'Server timeout: The request took too long to complete (likely hit PHP 60-second limit). Try reducing the batch size or processing fewer plaques at once.';
        } else if (xhr.statusText === 'timeout') {
            errorMessage = 'Request timed out after 30 seconds. The batch is taking too long.';
        }
        
        console.error('Error message:', errorMessage);
        showError(errorMessage);
        isProcessing = false;
    });
}

function updateProgress(data) {
    console.log('Updating progress with data:', data);
    
    totalPlaques = data.total_plaques;
    const percentage = data.progress_percentage;
    
    console.log('Progress values:', {
        totalPlaques: totalPlaques,
        percentage: percentage,
        cumulativeProcessed: cumulativeProcessed,
        cumulativeCreated: cumulativeCreated,
        cumulativeSkipped: cumulativeSkipped,
        cumulativeErrors: cumulativeErrors
    });
    
    // Update progress bar
    $('#progressBar').css('width', percentage + '%');
    $('#progressText').text(percentage + '%');
    
    // Update stats
    $('#processedCount').text(cumulativeProcessed);
    $('#createdCount').text(cumulativeCreated);
    $('#skippedCount').text(cumulativeSkipped);
    $('#totalCount').text(totalPlaques);
    $('#errorCount').text(cumulativeErrors);
    
    // Update status
    const currentBatch = Math.floor(currentOffset / 2) + 1; // batchSize is 2
    $('#statusText').text(`Processing batch ${currentBatch}... ${cumulativeProcessed} of ${totalPlaques} plaques`);
}

function showProgress() {
    $('#progressSection').show();
    $('#resultsSection').hide();
}

function completeImport() {
    isProcessing = false;
    
    $('#importAllBtn').prop('disabled', false).html('<i class="bi bi-play-circle me-2"></i>Import All Plaques');
    
    // Show final results
    $('#resultsSection').show();
    $('#resultsContent').html(`
        <div class="alert alert-success">
            <h5><i class="bi bi-check-circle me-2"></i>Import Completed!</h5>
            <p>Successfully processed all plaques.</p>
        </div>
        <div class="row">
            <div class="col-md-3">
                <div class="text-center">
                    <div class="h4 text-primary">${cumulativeProcessed}</div>
                    <small class="text-muted">Total Processed</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="text-center">
                    <div class="h4 text-success">${cumulativeCreated}</div>
                    <small class="text-muted">Created</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="text-center">
                    <div class="h4 text-warning">${cumulativeSkipped}</div>
                    <small class="text-muted">Skipped</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="text-center">
                    <div class="h4 text-danger">${cumulativeErrors}</div>
                    <small class="text-muted">Errors</small>
                </div>
            </div>
        </div>
    `);
    
    // Refresh stats
    loadStats();
}

function showPreview(data) {
    $('#previewSection').show();
    
    let html = `
        <div class="alert alert-info">
            <h5><i class="bi bi-info-circle me-2"></i>Data Preview</h5>
            <p>Showing ${data.preview_count} of ${data.total_plaques} plaques from ${data.plaque_type}.</p>
        </div>
    `;
    
    if (data.plaques && data.plaques.length > 0) {
        html += '<div class="row">';
        data.plaques.forEach(function(item) {
            const plaque = item.plaque;
            const validation = item.validation;
            const status = item.summary.status;
            
            let statusClass = 'success';
            let statusIcon = 'check-circle';
            if (status === 'error') {
                statusClass = 'danger';
                statusIcon = 'x-circle';
            } else if (status === 'warning') {
                statusClass = 'warning';
                statusIcon = 'exclamation-triangle';
            }
            
            html += `
                <div class="col-md-6 mb-3">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="card-title mb-0">
                                <i class="bi bi-${statusIcon} text-${statusClass} me-2"></i>
                                ${plaque.title || plaque.name || 'Untitled Plaque'}
                            </h6>
                        </div>
                        <div class="card-body">
                            <p><strong>Location:</strong> ${plaque.address || plaque.location || 'Unknown'}</p>
                            <p><strong>Year:</strong> ${plaque.erected_year || plaque.year || 'Unknown'}</p>
                            <p><strong>Status:</strong> <span class="badge bg-${statusClass}">${status}</span></p>
                        </div>
                    </div>
                </div>
            `;
        });
        html += '</div>';
    } else {
        html += '<div class="alert alert-warning">No plaques found in the preview.</div>';
    }
    
    $('#previewContent').html(html);
}

function loadStats() {
    $.get('{{ route("admin.import.blue-plaques.stats") }}')
    .done(function(response) {
        if (response.success) {
            const stats = response.stats;
            $('#statsSection').show();
            $('#statsContent').html(`
                <div class="row">
                    <div class="col-md-3">
                        <div class="text-center">
                            <div class="h4 text-primary">${stats.total_plaques}</div>
                            <small class="text-muted">Total Plaques</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center">
                            <div class="h4 text-success">${stats.blue_plaques}</div>
                            <small class="text-muted">Blue Plaques</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center">
                            <div class="h4 text-info">${stats.green_plaques}</div>
                            <small class="text-muted">Green Plaques</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center">
                            <div class="h4 text-warning">${stats.other_plaques}</div>
                            <small class="text-muted">Other Plaques</small>
                        </div>
                    </div>
                </div>
            `);
        }
    })
    .fail(function() {
        $('#statsSection').hide();
    });
}

function showSuccess(message) {
    const alert = `<div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle me-2"></i>${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>`;
    $('#alertsContainer').append(alert);
}

function showError(message) {
    const alert = `<div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-x-circle me-2"></i>${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>`;
    $('#alertsContainer').append(alert);
}

function showInfo(message) {
    const alert = `<div class="alert alert-info alert-dismissible fade show" role="alert">
        <i class="bi bi-info-circle me-2"></i>${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>`;
    $('#alertsContainer').append(alert);
}
</script>
@endpush
