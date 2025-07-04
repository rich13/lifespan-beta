@extends('layouts.app')

@section('page_title')
    Wikipedia Cache Prewarm
@endsection

@section('content')
<div class="py-4">
    <div class="row">
        <div class="col-12 d-flex justify-content-between align-items-center mb-4">
            <div>
                <a href="{{ route('admin.tools.index') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i>Back to Admin Tools
                </a>
            </div>
        </div>
    </div>

    <!-- Start Button -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-lightning-charge"></i>
                        Wikipedia "On This Day" Cache Prewarm
                    </h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">
                        This tool will pre-populate the Wikipedia "On This Day" cache for all 366 days of the year. 
                        This improves performance by ensuring data is available immediately when users visit date pages.
                    </p>
                    
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-1"></i>
                        <strong>Note:</strong> This operation may take several minutes as it needs to fetch data for each day of the year.
                    </div>
                    
                    <button id="startPrewarm" class="btn btn-warning btn-lg">
                        <i class="bi bi-lightning-charge me-1"></i>
                        Start Prewarm Operation
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Progress Section -->
    <div id="progressSection" class="row" style="display: none;">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-activity"></i>
                        Prewarm Progress
                    </h5>
                </div>
                <div class="card-body">
                    <!-- Summary Stats -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="text-center">
                                <h4 id="totalDays" class="text-primary mb-1">0</h4>
                                <small class="text-muted">Total Days</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center">
                                <h4 id="successDays" class="text-success mb-1">0</h4>
                                <small class="text-muted">Successful</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center">
                                <h4 id="errorDays" class="text-danger mb-1">0</h4>
                                <small class="text-muted">Errors</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center">
                                <h4 id="progressPercent" class="text-info mb-1">0%</h4>
                                <small class="text-muted">Progress</small>
                            </div>
                        </div>
                    </div>

                    <!-- Progress Bar -->
                    <div class="progress mb-4" style="height: 25px;">
                        <div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated" 
                             role="progressbar" style="width: 0%">0%</div>
                    </div>

                    <!-- Current Status -->
                    <div id="currentStatus" class="alert alert-info mb-4">
                        <i class="bi bi-hourglass-split me-1"></i>
                        Ready to start prewarm operation...
                    </div>
                    
                    <!-- Processing Info -->
                    <div id="processingInfo" class="row mb-4" style="display: none;">
                        <div class="col-md-6">
                            <div class="text-center">
                                <h6 class="text-muted">Processing Speed</h6>
                                <h5 id="processingSpeed" class="text-info">0 days/min</h5>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-center">
                                <h6 class="text-muted">Estimated Time Remaining</h6>
                                <h5 id="timeRemaining" class="text-warning">--</h5>
                            </div>
                        </div>
                    </div>

                    <!-- Progress Summary -->
                    <div class="row">
                        <div class="col-12">
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-1"></i>
                                <strong>Processing Progress:</strong> Each day is being cached with a 2-second delay to avoid overwhelming the Wikipedia API.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Results Section -->
    <div id="resultsSection" class="row" style="display: none;">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-check-circle"></i>
                        Prewarm Complete
                    </h5>
                </div>
                <div class="card-body">
                    <div id="resultsContent">
                        <!-- Results will be populated here -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
$(document).ready(function() {
    let isRunning = false;
    let processingInterval = null;
    let startTime = null;
    let processedCount = 0;
    
    $('#startPrewarm').on('click', function() {
        if (isRunning) return;
        
        isRunning = true;
        const $button = $(this);
        const $progressSection = $('#progressSection');
        const $resultsSection = $('#resultsSection');
        
        // Show progress section and hide results
        $progressSection.show();
        $resultsSection.hide();
        
        // Update button state
        $button.prop('disabled', true).html('<i class="bi bi-hourglass-split me-1"></i>Running...');
        
        // Update status
        $('#currentStatus').removeClass('alert-info alert-success alert-danger')
            .addClass('alert-info')
            .html('<i class="bi bi-hourglass-split me-1"></i>Initializing prewarm operation...');
        
        // Start the prewarm operation
        $.ajax({
            url: '{{ route("admin.tools.prewarm-wikipedia-cache") }}',
            method: 'POST',
            data: {
                action: 'start',
                _token: $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                if (response.success) {
                    // Initialize progress display
                    $('#totalDays').text(response.total_days);
                    $('#successDays').text('0');
                    $('#errorDays').text('0');
                    $('#progressPercent').text('0%');
                    $('#progressBar').css('width', '0%').text('0%');
                    
                    // Initialize processing tracking
                    startTime = new Date();
                    processedCount = 0;
                    $('#processingInfo').show();
                    
                    // Start processing days one by one
                    startProcessingDays();
                } else {
                    showError('Failed to start prewarm operation: ' + (response.message || 'Unknown error'));
                    resetButton();
                }
            },
            error: function(xhr) {
                showError('Failed to start prewarm operation. Please try again.');
                resetButton();
            }
        });
    });
    
    function startProcessingDays() {
        // Process one day every 2 seconds to avoid overwhelming the API
        processingInterval = setInterval(function() {
            processNextDay();
        }, 2000); // 2 second delay between each day
    }
    
    function processNextDay() {
        $.ajax({
            url: '{{ route("admin.tools.prewarm-wikipedia-cache") }}',
            method: 'POST',
            data: {
                action: 'process-day',
                _token: $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                if (response.success) {
                    if (response.is_complete) {
                        // All days processed
                        clearInterval(processingInterval);
                        displayResults(response.summary);
                        resetButton();
                    } else {
                        // Update progress for current day
                        updateProgress(response);
                    }
                } else {
                    showError('Failed to process day: ' + (response.message || 'Unknown error'));
                    clearInterval(processingInterval);
                    resetButton();
                }
            },
            error: function(xhr) {
                showError('Failed to process day. Please try again.');
                clearInterval(processingInterval);
                resetButton();
            }
        });
    }
    
    function updateProgress(response) {
        const summary = response.summary;
        const progressItem = response.progress_item;
        
        // Update summary stats
        $('#successDays').text(summary.success_days);
        $('#errorDays').text(summary.errors.length);
        
        // Calculate and update progress percentage
        const processed = summary.total_days - summary.remaining_days;
        const progressPercent = Math.round((processed / summary.total_days) * 100);
        $('#progressPercent').text(progressPercent + '%');
        $('#progressBar').css('width', progressPercent + '%').text(progressPercent + '%');
        
        // Update processing speed and time remaining
        processedCount = processed;
        updateProcessingStats();
        
        // Update status with current day being processed
        $('#currentStatus').removeClass('alert-info alert-success alert-danger')
            .addClass('alert-info')
            .html(`<i class="bi bi-hourglass-split me-1"></i>Processing ${response.current_label}... (${processed}/${summary.total_days})`);
        
        // Update summary stats with current totals
        updateSummaryStats(summary);
    }
    
    function updateProcessingStats() {
        if (!startTime || processedCount === 0) return;
        
        const now = new Date();
        const elapsedMinutes = (now - startTime) / 1000 / 60; // Convert to minutes
        const speed = processedCount / elapsedMinutes;
        
        $('#processingSpeed').text(speed.toFixed(1) + ' days/min');
        
        // Calculate estimated time remaining
        const remainingDays = $('#totalDays').text() - processedCount;
        const estimatedMinutes = remainingDays / speed;
        
        if (estimatedMinutes > 60) {
            const hours = Math.floor(estimatedMinutes / 60);
            const minutes = Math.round(estimatedMinutes % 60);
            $('#timeRemaining').text(`${hours}h ${minutes}m`);
        } else {
            $('#timeRemaining').text(Math.round(estimatedMinutes) + 'm');
        }
    }
    
    function updateSummaryStats(summary) {
        // Update the summary stats with current totals
        $('#successDays').text(summary.success_days);
        $('#errorDays').text(summary.errors.length);
        
        // Calculate and update progress percentage
        const processed = summary.total_days - summary.remaining_days;
        const progressPercent = Math.round((processed / summary.total_days) * 100);
        $('#progressPercent').text(progressPercent + '%');
        $('#progressBar').css('width', progressPercent + '%').text(progressPercent + '%');
    }
    
    function displayResults(summary) {
        // Update final stats
        $('#totalDays').text(summary.total_days);
        $('#successDays').text(summary.success_days);
        $('#errorDays').text(summary.errors.length);
        $('#progressPercent').text('100%');
        $('#progressBar').css('width', '100%').text('100%');
        
        // Update status
        $('#currentStatus').removeClass('alert-info alert-danger')
            .addClass('alert-success')
            .html('<i class="bi bi-check-circle me-1"></i>Prewarm operation completed successfully!');
        
        // Show results section
        $('#resultsSection').show();
        
        // Populate results content
        const $resultsContent = $('#resultsContent');
        let resultsHtml = `
            <div class="row">
                <div class="col-md-6">
                    <h6>Summary:</h6>
                    <ul class="list-unstyled">
                        <li><strong>Total days processed:</strong> ${summary.total_days}</li>
                        <li><strong>Days with data cached:</strong> ${summary.success_days}</li>
                        <li><strong>Days with errors:</strong> ${summary.errors.length}</li>
                    </ul>
                </div>
                <div class="col-md-6">
                    <h6>Success Rate:</h6>
                    <div class="progress mb-2">
                        <div class="progress-bar bg-success" style="width: ${(summary.success_days / summary.total_days * 100).toFixed(1)}%">
                            ${(summary.success_days / summary.total_days * 100).toFixed(1)}%
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        if (summary.errors.length > 0) {
            resultsHtml += `
                <div class="mt-3">
                    <h6>Errors:</h6>
                    <ul class="text-danger small">
                        ${summary.errors.map(error => `<li>${error}</li>`).join('')}
                    </ul>
                </div>
            `;
        }
        
        $resultsContent.html(resultsHtml);
    }
    
    function resetButton() {
        isRunning = false;
        $('#startPrewarm').prop('disabled', false).html('<i class="bi bi-lightning-charge me-1"></i>Start Prewarm Operation');
    }
    
    function showError(message) {
        $('#currentStatus').removeClass('alert-info alert-success')
            .addClass('alert-danger')
            .html('<i class="bi bi-exclamation-triangle me-1"></i>' + message);
    }
});
</script>
@endpush
@endsection 