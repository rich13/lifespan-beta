@extends('layouts.app')

@section('page_title')
    Import Legacy YAML Files
@endsection

@section('scripts')
@parent
<script>
$(document).ready(function() {
    console.log('Import page ready');
    
    // Track import queue and results
    let importQueue = [];
    let importResults = [];
    let isImporting = false;
    let currentRequest = null;

    // Add cleanup handlers
    $(window).on('beforeunload', function(e) {
        if (isImporting) {
            e.preventDefault();
            return '';
        }
    });

    $(window).on('unload', function() {
        if (isImporting && currentRequest) {
            currentRequest.abort();
            isImporting = false;
        }
    });

    function formatErrorDetails(response) {
        if (!response) return 'Unknown error';
        
        // Handle different error formats
        if (response.error && typeof response.error === 'object') {
            // SQL/Database errors
            if (response.error.error) {
                return `Database error: ${response.error.error}`;
            }
            return JSON.stringify(response.error, null, 2);
        }
        
        if (Array.isArray(response.errors)) {
            return response.errors.map(e => `${e.type}: ${e.message}`).join('<br>');
        }
        
        if (response.message) {
            return response.message;
        }
        
        return JSON.stringify(response, null, 2);
    }

    function createDetailsCell(details) {
        const cell = $('<td>');
        
        // Create an expandable section for long error messages
        const shortText = $('<div>').addClass('short-text').text(details.substring(0, 100) + (details.length > 100 ? '...' : ''));
        const fullText = $('<div>').addClass('full-text d-none').html(details);
        
        if (details.length > 100) {
            const toggleBtn = $('<button>')
                .addClass('btn btn-link btn-sm')
                .text('Show more')
                .on('click', function() {
                    const isExpanded = fullText.is(':visible');
                    shortText.toggleClass('d-none');
                    fullText.toggleClass('d-none');
                    $(this).text(isExpanded ? 'Show more' : 'Show less');
                });
            
            cell.append(shortText, fullText, toggleBtn);
        } else {
            cell.html(details);
        }
        
        return cell;
    }

    $('#bulkImportButton').on('click', function() {
        if (isImporting) {
            return; // Prevent multiple concurrent imports
        }

        console.log('Bulk import button clicked');
        const button = $(this);
        const progress = $('#bulkImportProgress');
        const progressBar = progress.find('.progress-bar');
        const progressText = $('#bulkProgressText');
        const results = $('#bulkImportResults');
        const resultsBody = $('#bulkResultsBody');

        // Collect all import buttons
        importQueue = $('.import-button').map(function() {
            return {
                button: $(this),
                filename: $(this).data('filename'),
                url: $(this).data('url')
            };
        }).get();

        if (importQueue.length === 0) {
            alert('No files found to import');
            return;
        }

        // Reset state
        isImporting = true;
        importResults = [];
        button.prop('disabled', true);
        progress.removeClass('d-none');
        progressBar.css('width', '0%');
        results.removeClass('d-none');
        resultsBody.empty();
        
        // Show progress
        progressText.html(`Starting import of ${importQueue.length} files...`);

        // Process files sequentially
        processNextFile();

        function processNextFile() {
            if (importQueue.length === 0) {
                // All done
                isImporting = false;
                button.prop('disabled', false);
                progressBar.css('width', '100%').removeClass('progress-bar-animated');
                const successful = importResults.filter(r => r.status === 'success').length;
                progressText.html(`Import completed: ${successful} successful, ${importResults.length - successful} failed`);
                return;
            }

            const current = importQueue.shift();
            const currentIndex = importResults.length;
            const totalFiles = importResults.length + importQueue.length + 1;
            
            progressText.html(`Importing ${current.filename} (${currentIndex + 1}/${totalFiles})...`);
            progressBar.css('width', `${(currentIndex / totalFiles) * 100}%`);

            console.log('Processing file:', current.filename, 'URL:', current.url);

            // Add a row for this file
            const row = $('<tr>');
            row.append($('<td>').text(current.filename));
            const statusCell = $('<td>').html('<span class="badge bg-warning">Processing...</span>');
            row.append(statusCell);
            const detailsCell = $('<td>').text('Processing...');
            row.append(detailsCell);
            resultsBody.append(row);

            // Store the current request so it can be aborted if needed
            currentRequest = $.ajax({
                url: current.url,
                method: 'POST',
                timeout: 30000, // 30 second timeout
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                    'Accept': 'application/json'
                },
                success: function(response) {
                    console.log('Import success for', current.filename, response);
                    importResults.push({
                        filename: current.filename,
                        status: 'success',
                        details: response
                    });

                    // Update row
                    statusCell.html('<span class="badge bg-success">Success</span>');
                    
                    // Format success details
                    let details = [];
                    if (response.created) details.push(`Created: ${response.created}`);
                    if (response.updated) details.push(`Updated: ${response.updated}`);
                    if (response.skipped) details.push(`Skipped: ${response.skipped}`);
                    if (response.message) details.push(response.message);
                    
                    detailsCell.replaceWith(createDetailsCell(details.join('<br>')));
                },
                error: function(xhr, status, error) {
                    console.error('Import failed for', current.filename, {
                        status: status,
                        error: error,
                        response: xhr.responseJSON
                    });
                    
                    importResults.push({
                        filename: current.filename,
                        status: 'error',
                        error: xhr.responseJSON
                    });

                    // Update row
                    statusCell.html('<span class="badge bg-danger">Failed</span>');
                    detailsCell.replaceWith(createDetailsCell(formatErrorDetails(xhr.responseJSON)));
                },
                complete: function() {
                    currentRequest = null;
                    // Wait a short moment before processing next file
                    setTimeout(processNextFile, 500);
                }
            });
        }
    });
});
</script>

<style>
.short-text {
    margin-bottom: 0.5rem;
}
.full-text {
    white-space: pre-wrap;
    background: #f8f9fa;
    padding: 1rem;
    border-radius: 0.25rem;
    margin-bottom: 0.5rem;
}
</style>
@endsection

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Import YAML Files</span>
                    <button id="bulkImportButton" class="btn btn-primary">
                        <i class="fas fa-upload"></i> Import All Files
                    </button>
                </div>

                <div class="card-body">
                    <!-- Progress Section -->
                    <div id="bulkImportProgress" class="d-none mb-4">
                        <p id="bulkProgressText" class="mb-2">Starting import...</p>
                        <div class="progress">
                            <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%"></div>
                        </div>
                    </div>

                    <!-- Results Table -->
                    <div id="bulkImportResults" class="d-none mt-4">
                        <h5>Import Results</h5>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>File</th>
                                    <th>Status</th>
                                    <th>Details</th>
                                </tr>
                            </thead>
                            <tbody id="bulkResultsBody">
                            </tbody>
                        </table>
                    </div>

                    <!-- Files Table -->
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Contains</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($files as $file)
                            <tr>
                                <td>
                                    @if($file['existing_span'])
                                        <x-spans.display.micro-card :span="$file['existing_span']" />
                                        <br>
                                        <small class="text-muted">
                                            Created: {{ \Carbon\Carbon::parse($file['existing_span']['created_at'])->format('Y-m-d') }}
                                            <br>
                                            Last updated: {{ \Carbon\Carbon::parse($file['existing_span']['updated_at'])->format('Y-m-d') }}
                                        </small>
                                    @else
                                        {{ $file['name'] }}
                                    @endif
                                </td>
                                <td>{{ $file['type'] }}</td>
                                <td>
                                    <small>
                                        Modified: {{ \Carbon\Carbon::createFromTimestamp($file['modified'])->format('Y-m-d H:i:s') }}
                                        <br>
                                        Size: {{ number_format($file['size'] / 1024, 2) }} KB
                                    </small>
                                </td>
                                <td>
                                    @if($file['has_education'])
                                        <span class="badge bg-secondary">Education</span>
                                    @endif
                                    @if($file['has_work'])
                                        <span class="badge bg-secondary">Work</span>
                                    @endif
                                    @if($file['has_places'])
                                        <span class="badge bg-secondary">Places</span>
                                    @endif
                                    @if($file['has_relationships'])
                                        <span class="badge bg-secondary">Relationships</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="{{ route('admin.import.show', ['id' => $file['id']]) }}" 
                                           class="btn btn-sm btn-outline-primary">
                                            @if($file['existing_span'])
                                                <i class="bi bi-arrow-repeat"></i> Review & Re-import
                                            @else
                                                <i class="bi bi-eye"></i> Review & Import
                                            @endif
                                        </a>
                                        <button class="btn btn-sm btn-success import-button" 
                                                data-filename="{{ $file['filename'] }}"
                                                data-url="{{ route('admin.import.import', ['id' => $file['id']]) }}">
                                            <i class="fas fa-file-import"></i> Import
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection 