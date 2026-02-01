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

                    <!-- Import Progress Status -->
                    <div class="row mb-4" id="importStatusSection">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Import Status</h5>
                                </div>
                                <div class="card-body">
                                    <div id="importStatusContent">
                                        <div class="text-center">
                                            <div class="spinner-border text-primary" role="status">
                                                <span class="visually-hidden">Loading...</span>
                                            </div>
                                            <p class="mt-2 text-muted">Loading import status...</p>
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
                                        <div class="col-md-3">
                                            <button type="button" class="btn btn-info w-100 mb-2" id="previewBtn">
                                                <i class="bi bi-eye me-2"></i>
                                                Preview Data
                                            </button>
                                        </div>
                                        <div class="col-md-3">
                                            <button type="button" class="btn btn-success w-100 mb-2" id="importAllBtn">
                                                <i class="bi bi-play-fill me-2"></i>
                                                Import All Plaques
                                            </button>
                                        </div>
                                        <div class="col-md-3">
                                            <button type="button" class="btn btn-outline-success w-100 mb-2" id="importBackgroundBtn" title="Faster: runs in background, no browser timeout">
                                                <i class="bi bi-cloud-upload me-2"></i>
                                                Import in Background
                                            </button>
                                        </div>
                                        <div class="col-md-3">
                                            <button type="button" class="btn btn-primary w-100 mb-2" id="resumeImportBtn" style="display: none;">
                                                <i class="bi bi-arrow-clockwise me-2"></i>
                                                Resume Import
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Progress Section -->
                    <div class="row mb-4" id="progressSection" style="display: none;">
                        <!-- Left Column: Progress -->
                        <div class="col-md-8">
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
                                        <div class="col-md-4">
                                            <div class="text-center">
                                                <div class="h4 text-primary" id="processedCount">0</div>
                                                <small class="text-muted">Processed</small>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="text-center">
                                                <div class="h4 text-success" id="createdCount">0</div>
                                                <small class="text-muted">Created</small>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="text-center">
                                                <div class="h4 text-warning" id="skippedCount">0</div>
                                                <small class="text-muted">Skipped</small>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="text-center">
                                                <div class="h4 text-info" id="totalCount">0</div>
                                                <small class="text-muted">Total</small>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
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
                        
                        <!-- Right Column: Created Spans Log -->
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="card-title mb-0">
                                        <i class="bi bi-list-ul me-2"></i>
                                        Created Spans
                                    </h5>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="clearLogBtn" title="Clear log">
                                        <i class="bi bi-x-circle"></i>
                                    </button>
                                </div>
                                <div class="card-body" style="height: 600px; overflow-y: auto; padding: 0;">
                                    <div id="createdSpansLog" class="list-group list-group-flush" style="max-height: 600px;">
                                        <div class="list-group-item text-muted text-center">
                                            <small>No spans created yet</small>
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
@endsection

@push('scripts')
<style>
@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

#createdSpansLog {
    max-height: 600px;
    overflow-y: auto;
}

#createdSpansLog .list-group-item {
    border-left: 3px solid transparent;
    transition: all 0.2s ease;
}

#createdSpansLog .list-group-item:hover {
    background-color: #f8f9fa;
    border-left-color: #0d6efd;
}

#createdSpansLog .list-group-item a {
    color: #212529;
    transition: color 0.2s ease;
}

#createdSpansLog .list-group-item a:hover {
    color: #0d6efd;
}

/* Type-specific border colors */
#createdSpansLog .list-group-item[data-type="plaque"] {
    border-left-color: #0d6efd;
}

#createdSpansLog .list-group-item[data-type="person"] {
    border-left-color: #198754;
}

#createdSpansLog .list-group-item[data-type="location"] {
    border-left-color: #0dcaf0;
}

#createdSpansLog .list-group-item[data-type="photo"],
#createdSpansLog .list-group-item[data-type="person_photo"] {
    border-left-color: #ffc107;
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
let createdSpansLog = [];

$(document).ready(function() {
    // Set up CSRF token for all AJAX requests
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    // Load import status on page load and when plaque type changes
    loadImportStatus();
    $('input[name="plaqueType"]').change(function() {
        loadImportStatus();
        loadStats();
        $('#importBackgroundBtn').toggle($('input[name="plaqueType"]:checked').val() !== 'custom');
    });
    $('#importBackgroundBtn').toggle($('input[name="plaqueType"]:checked').val() !== 'custom');

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
        
        if (!confirm(`This will import all plaques from the selected data source starting from the beginning. Continue?`)) {
            return;
        }
        
        startImport(plaqueType, 0);
    });

    // Import in Background (faster, no browser timeout)
    $('#importBackgroundBtn').click(function() {
        if (isProcessing) return;

        const plaqueType = $('input[name="plaqueType"]:checked').val();
        if (plaqueType === 'custom') {
            alert('Background import is not available for custom plaque imports.');
            return;
        }

        if (!confirm('Start import in background? This is faster and avoids browser timeouts. You can leave this page and check back later.')) {
            return;
        }

        $(this).prop('disabled', true).html('<i class="bi bi-hourglass-split me-2"></i>Starting...');

        startBackgroundJobPolling();

        $.post('{{ route("admin.import.blue-plaques.import-background") }}', {
            plaque_type: plaqueType
        })
        .done(function(response) {
            if (!response.success) {
                alert(response.message || 'Failed to start import');
            }
        })
        .fail(function(xhr) {
            if (xhr.status === 0) {
                alert('Request was interrupted (e.g. page refresh). The import may have started – check the status above.');
                loadImportStatus();
            } else {
                alert('Failed to start import: ' + (xhr.responseJSON?.message || 'Unknown error'));
            }
        })
        .always(function() {
            $('#importBackgroundBtn').prop('disabled', false).html('<i class="bi bi-cloud-upload me-2"></i>Import in Background');
        });
    });

    // Cancel Background Import (delegated - button is dynamically added)
    $(document).on('click', '#cancelBackgroundBtn', function() {
        const plaqueType = $('input[name="plaqueType"]:checked').val();
        if (plaqueType === 'custom') return;
        if (!confirm('Cancel the background import? It will stop after the current batch.')) return;
        const $btn = $(this);
        $btn.prop('disabled', true).html('<i class="bi bi-hourglass-split me-1"></i>Cancelling...');

        // Show optimistic feedback – Cancel POST may block if import is using the worker (sync queue)
        const $statusArea = $('#importStatusContent');
        const cancelMsg = '<p class="mb-0 small text-warning"><i class="bi bi-info-circle me-1"></i>Cancel requested – job will stop after the current batch. Polling for status…</p>';
        $statusArea.find('.alert').first().append(cancelMsg);

        const request = $.post('{{ route("admin.import.blue-plaques.cancel-background") }}', { plaque_type: plaqueType });
        request.timeout(8000);
        request.done(function(response) {
            if (response.success) {
                loadImportStatus(true);
            }
        });
        request.fail(function(xhr, textStatus) {
            if (textStatus === 'timeout' || xhr.status === 0) {
                $statusArea.find('.text-warning').last().html('<i class="bi bi-info-circle me-1"></i>Cancel request sent (server may be busy). Status will update when the job stops. Refresh if needed.');
                loadImportStatus(true);
            }
        });
        request.always(function() {
            $btn.prop('disabled', false).html('<i class="bi bi-x-circle me-1"></i>Cancel');
        });
    });

    // Resume Import
    $('#resumeImportBtn').click(function() {
        if (isProcessing) return;
        
        const plaqueType = $('input[name="plaqueType"]:checked').val();
        const resumeOffset = $(this).data('resume-offset');
        
        if (!confirm(`This will resume importing from plaque ${resumeOffset + 1}. Continue?`)) {
            return;
        }
        
        startImport(plaqueType, resumeOffset);
    });

    // Cancel Import
    $('#cancelImportBtn').click(function() {
        if (confirm('Are you sure you want to cancel the import?')) {
            isProcessing = false;
            $('#progressSpinner').removeClass('bi-arrow-clockwise').addClass('bi-exclamation-triangle-fill').css('animation', 'none');
            $('#statusText').text('Import cancelled');
        }
    });

    // Clear Log
    $('#clearLogBtn').click(function() {
        createdSpansLog = [];
        updateCreatedSpansLog();
    });

    // Load initial statistics
    loadStats();
});

function startImport(plaqueType, startOffset = 0) {
    console.log('Starting import for plaque type:', plaqueType, 'from offset:', startOffset);
    
    isProcessing = true;
    currentOffset = startOffset;
    
    // Reset cumulative totals
    cumulativeProcessed = 0;
    cumulativeCreated = 0;
    cumulativeSkipped = 0;
    cumulativeErrors = 0;
    createdSpansLog = []; // Clear the log when starting a new import
    
    console.log('Initial values:', {
        isProcessing: isProcessing,
        currentOffset: currentOffset,
        cumulativeProcessed: cumulativeProcessed,
        cumulativeCreated: cumulativeCreated,
        cumulativeSkipped: cumulativeSkipped,
        cumulativeErrors: cumulativeErrors
    });
    
    showProgress();
    updateCreatedSpansLog(); // Clear the log display
    
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
            
            // Add created spans to log
            if (response.data.created_spans && response.data.created_spans.length > 0) {
                createdSpansLog.push(...response.data.created_spans);
                updateCreatedSpansLog();
            }
            
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
    updateCreatedSpansLog(); // Initialize the log display
}

function updateCreatedSpansLog() {
    const logContainer = $('#createdSpansLog');
    const maxItems = 500; // Limit to last 500 items for performance
    
    if (createdSpansLog.length === 0) {
        logContainer.html('<div class="list-group-item text-muted text-center"><small>No spans created yet</small></div>');
        return;
    }
    
    // Clear existing content
    logContainer.empty();
    
    // Get the last N items (newest first) for performance
    const displayLog = createdSpansLog.slice(-maxItems).reverse();
    
    displayLog.forEach(function(span) {
        const typeIcon = getTypeIcon(span.type);
        const typeClass = getTypeClass(span.type);
        const typeLabel = getTypeLabel(span.type);
        
        const logItem = $(`
            <div class="list-group-item list-group-item-action" data-type="${span.type}">
                <div class="d-flex w-100 justify-content-between align-items-start">
                    <div class="flex-grow-1">
                        <div class="d-flex align-items-center mb-1">
                            <i class="bi bi-${typeIcon} me-2 text-${typeClass}"></i>
                            <small class="text-muted">${typeLabel}</small>
                        </div>
                        <h6 class="mb-1">
                            <a href="${span.url}" target="_blank" class="text-decoration-none">
                                ${escapeHtml(span.name)}
                            </a>
                        </h6>
                        <small class="text-muted">ID: ${span.id.substring(0, 8)}...</small>
                    </div>
                </div>
            </div>
        `);
        
        logContainer.append(logItem);
    });
    
    // Show count if there are more items
    if (createdSpansLog.length > maxItems) {
        const moreCount = createdSpansLog.length - maxItems;
        logContainer.prepend(`
            <div class="list-group-item text-muted text-center bg-light">
                <small>Showing last ${maxItems} of ${createdSpansLog.length} created spans</small>
            </div>
        `);
    }
    
    // Auto-scroll to top (newest items)
    const logBody = logContainer.parent();
    logBody.scrollTop(0);
}

function getTypeIcon(type) {
    const icons = {
        'plaque': 'geo-alt-fill',
        'person': 'person-fill',
        'location': 'geo-alt',
        'photo': 'image',
        'person_photo': 'image-fill'
    };
    return icons[type] || 'circle';
}

function getTypeClass(type) {
    const classes = {
        'plaque': 'primary',
        'person': 'success',
        'location': 'info',
        'photo': 'warning',
        'person_photo': 'warning'
    };
    return classes[type] || 'secondary';
}

function getTypeLabel(type) {
    const labels = {
        'plaque': 'Plaque',
        'person': 'Person',
        'location': 'Location',
        'photo': 'Photo',
        'person_photo': 'Person Photo'
    };
    return labels[type] || 'Span';
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
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
    
    // Refresh stats and import status
    loadStats();
    loadImportStatus();
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

let backgroundJobPollInterval = null;

function startBackgroundJobPolling() {
    if (backgroundJobPollInterval) clearInterval(backgroundJobPollInterval);
    loadImportStatus();
    backgroundJobPollInterval = setInterval(function() {
        loadImportStatus(true);
    }, 2000);
}

function stopBackgroundJobPolling() {
    if (backgroundJobPollInterval) {
        clearInterval(backgroundJobPollInterval);
        backgroundJobPollInterval = null;
    }
}

function loadImportStatus(isPolling = false) {
    const plaqueType = $('input[name="plaqueType"]:checked').val();
    if (isPolling) {
        console.log('[Plaque Import] Polling status...', { plaqueType });
    }
    
    $.get('{{ route("admin.import.blue-plaques.status") }}', {
        plaque_type: plaqueType
    })
    .done(function(response) {
        if (isPolling && response.success) {
            const jp = response.job_progress || {};
            console.log('[Plaque Import] Poll result:', {
                status: response.job_status,
                processed: jp.processed,
                total: jp.total,
                created: jp.created,
                skipped: jp.skipped,
                errors: jp.errors,
                current_plaque: jp.current_plaque
            });
        }
        if (response.success) {
            // Stop polling when background job completes, fails, or is cancelled
            if (response.background_job && response.job_status) {
                if (response.job_status === 'completed' || response.job_status === 'failed' || response.job_status === 'cancelled') {
                    stopBackgroundJobPolling();
                    if (response.job_status === 'completed' || response.job_status === 'cancelled') {
                        loadStats();
                    }
                }
                if (response.job_status === 'failed' && response.job_progress?.error) {
                    $('#importStatusContent').html(`
                        <div class="alert alert-danger mb-0">
                            <i class="bi bi-x-circle me-2"></i>
                            Background import failed: ${response.job_progress.error}
                        </div>
                    `);
                    return;
                }
                if (response.job_status === 'cancelled') {
                    const jp = response.job_progress || {};
                    $('#importStatusContent').html(`
                        <div class="alert alert-warning mb-0">
                            <i class="bi bi-slash-circle me-2"></i>
                            <strong>Import cancelled.</strong>
                            <p class="mb-0 mt-2">Stopped at ${jp.processed || 0} of ${jp.total || 0} plaques (Created: ${jp.created || 0}, Skipped: ${jp.skipped || 0}, Errors: ${jp.errors || 0})</p>
                        </div>
                    `);
                    loadStats();
                    return;
                }
            }

            const status = response;
            let statusHtml = '';
            
            // Show background job progress
            if (status.background_job && status.job_progress) {
                const jp = status.job_progress;
                const pct = jp.progress_percentage || 0;
                const statusLabel = status.job_status === 'running' ? '(in progress)' : status.job_status;
                const alertClass = status.job_status === 'running' ? 'info' : status.job_status === 'completed' ? 'success' : status.job_status === 'cancelled' ? 'warning' : 'warning';
                const currentPlaque = jp.current_plaque ? `Currently: ${jp.current_plaque}` : '';
                const lastActivity = jp.last_activity ? (() => {
                    const d = new Date(jp.last_activity);
                    const ageSec = Math.round((Date.now() - d) / 1000);
                    return ageSec > 15 ? `(last update ${ageSec}s ago – progress may be delayed)` : '';
                })() : '';
                statusHtml = `
                    <div class="alert alert-${alertClass} mb-3">
                        <div class="d-flex justify-content-between align-items-start">
                            <h5 class="mb-0"><i class="bi bi-cloud-upload me-2"></i>Background Import ${statusLabel}</h5>
                            ${status.job_status === 'running' ? '<button type="button" class="btn btn-sm btn-outline-danger" id="cancelBackgroundBtn"><i class="bi bi-x-circle me-1"></i>Cancel</button>' : ''}
                        </div>
                        <p class="mb-2 mt-2">
                            <strong>${jp.processed || 0}</strong> of <strong>${jp.total || 0}</strong> plaques
                            (${pct}% complete)
                        </p>
                        ${currentPlaque ? `<p class="mb-1 small text-muted"><em>${currentPlaque}</em> ${lastActivity}</p>` : lastActivity ? `<p class="mb-1 small text-warning">${lastActivity}</p>` : ''}
                        <p class="mb-0 small">
                            Created: ${jp.created || 0} | Skipped: ${jp.skipped || 0} | Errors: ${jp.errors || 0}
                        </p>
                    </div>
                    ${(jp.batch_size != null && jp.batch_size > 0) ? `
                    <p class="mb-1 small text-muted">Current batch: <strong>${jp.batch_progress ?? 0}</strong> of <strong>${jp.batch_size}</strong></p>
                    <div class="progress mb-2" style="height: 18px;">
                        <div class="progress-bar progress-bar-striped bg-secondary ${status.job_status === 'running' ? 'progress-bar-animated' : ''}" role="progressbar" style="width: ${Math.min(100, ((jp.batch_progress ?? 0) / jp.batch_size) * 100)}%">
                            ${jp.batch_progress ?? 0}/${jp.batch_size}
                        </div>
                    </div>
                    ` : ''}
                    <p class="mb-1 small text-muted">Overall: <strong>${jp.processed || 0}</strong> of <strong>${jp.total || 0}</strong> plaques</p>
                    <div class="progress mb-3" style="height: 25px;">
                        <div class="progress-bar progress-bar-striped ${status.job_status === 'running' ? 'progress-bar-animated' : ''}" role="progressbar" style="width: ${pct}%">
                            ${pct}%
                        </div>
                    </div>
                `;
            } else if (status.total_imported_plaques > 0) {
                // Show import progress
                const progressPercent = status.import_progress_percentage || 0;
                const remainingPlaques = status.remaining_plaques || 0;
                const totalAvailable = status.total_available_plaques || 0;
                const importedCount = status.total_imported_plaques || 0;
                
                statusHtml = `
                    <div class="alert alert-info mb-3">
                        <h5><i class="bi bi-info-circle me-2"></i>Import Progress</h5>
                        <p class="mb-2">
                            <strong>${importedCount}</strong> of <strong>${totalAvailable}</strong> plaques imported 
                            (${progressPercent}% complete)
                        </p>
                        <p class="mb-0">
                            <strong>${remainingPlaques}</strong> plaques remaining to import
                        </p>
                    </div>
                    <div class="progress mb-3" style="height: 25px;">
                        <div class="progress-bar progress-bar-striped" role="progressbar" style="width: ${progressPercent}%">
                            ${progressPercent}%
                        </div>
                    </div>
                `;
                
                // Show resume button if there are remaining plaques
                if (remainingPlaques > 0 && status.first_unimported_index !== undefined) {
                    $('#resumeImportBtn').data('resume-offset', status.first_unimported_index).show();
                } else {
                    $('#resumeImportBtn').hide();
                }
            } else {
                // No imports yet
                statusHtml = `
                    <div class="alert alert-secondary mb-0">
                        <i class="bi bi-info-circle me-2"></i>
                        No plaques have been imported yet. Use "Import All Plaques" to start.
                    </div>
                `;
                $('#resumeImportBtn').hide();
            }
            
            $('#importStatusContent').html(statusHtml);
        }
    })
    .fail(function(xhr) {
        $('#importStatusContent').html(`
            <div class="alert alert-warning mb-0">
                <i class="bi bi-exclamation-triangle me-2"></i>
                Could not load import status: ${xhr.responseJSON?.message || 'Unknown error'}
            </div>
        `);
    });
}

function loadStats() {
    const plaqueType = $('input[name="plaqueType"]:checked').val();
    $.get('{{ route("admin.import.blue-plaques.stats") }}', { plaque_type: plaqueType })
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
                            <div class="h4 text-success">${stats.total_people}</div>
                            <small class="text-muted">People</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center">
                            <div class="h4 text-info">${stats.total_locations}</div>
                            <small class="text-muted">Locations</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center">
                            <div class="h4 text-warning">${stats.total_connections}</div>
                            <small class="text-muted">Connections</small>
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
