@extends('layouts.app')

@section('page_title')
    @php
        $breadcrumbItems = [
            [
                'text' => 'Settings',
                'url' => route('settings.index'),
                'icon' => 'gear',
                'icon_category' => 'action'
            ],
            [
                'text' => 'Import Settings',
                'url' => route('settings.import'),
                'icon' => 'upload',
                'icon_category' => 'action'
            ],
            [
                'text' => 'LinkedIn Import',
                'url' => route('settings.import.linkedin.index'),
                'icon' => 'linkedin',
                'icon_category' => 'brand'
            ]
        ];
    @endphp
    
    <x-breadcrumb :items="$breadcrumbItems" />
@endsection

@section('content')
    <div class="container-fluid">
        <div class="row">
            <!-- Left Sidebar Menu -->
            <div class="col-md-3">
                <x-settings-nav active="import" />
            </div>

            <!-- Main Content Area -->
            <div class="col-md-9">
                <!-- Import Instructions -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-info-circle me-2"></i>How to Import LinkedIn Data
                        </h5>
                    </div>
                    <div class="card-body">
                        <ol class="mb-0">
                            <li>Go to your LinkedIn account settings</li>
                            <li>Request a copy of your data (Data Export)</li>
                            <li>Download the ZIP file and extract it</li>
                            <li>Find the <code>Positions.csv</code> file in the extracted folder</li>
                            <li>Upload the CSV file below</li>
                        </ol>
                    </div>
                </div>

                <!-- File Upload -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-cloud-upload me-2"></i>Import LinkedIn Positions
                        </h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-3">
                            Upload your LinkedIn Positions.csv file to import your work history. 
                            This will create organisation spans, role spans, and the appropriate connections between them.
                        </p>

                        <form id="importForm" enctype="multipart/form-data">
                            <div class="row">
                                <div class="col-md-8 mb-3">
                                    <label for="csv_file" class="form-label">LinkedIn Positions CSV File</label>
                                    <input type="file" class="form-control" id="csv_file" name="csv_file" 
                                           accept=".csv,.txt" required>
                                    <div class="form-text">Select your Positions.csv file from LinkedIn data export</div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="update_existing" name="update_existing" value="1">
                                        <label class="form-check-label" for="update_existing">
                                            Update Existing Positions
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-outline-primary" id="previewBtn">
                                    <i class="bi bi-eye me-2"></i>Preview Import
                                </button>
                                <button type="submit" class="btn btn-success" id="importBtn" style="display: none;">
                                    <i class="bi bi-cloud-upload me-2"></i>Confirm Import
                                </button>
                            </div>
                        </form>

                        <!-- Preview Results -->
                        <div id="previewResult" class="mt-3" style="display: none;">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="card-title mb-0">
                                        <i class="bi bi-eye me-2"></i>Import Preview
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div id="previewContent"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Import Loading Overlay -->
                        <div id="importLoadingOverlay" class="mt-3" style="display: none;">
                            <div class="card">
                                <div class="card-body text-center py-4">
                                    <div class="spinner-border text-primary mb-3" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                    <h5 class="text-primary">Importing LinkedIn Data</h5>
                                    <p class="text-muted mb-3">Please wait while we process your positions...</p>
                                    <div class="progress" style="height: 20px;">
                                        <div id="importProgressBar" class="progress-bar progress-bar-striped progress-bar-animated" 
                                             role="progressbar" style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
                                            <span id="importProgressText">0%</span>
                                        </div>
                                    </div>
                                    <small class="text-muted mt-2" id="importProgressDetails">Initializing...</small>
                                </div>
                            </div>
                        </div>

                        <div id="importResult" class="mt-3" style="display: none;">
                            <div class="alert" id="importAlert">
                                <span id="importMessage"></span>
                            </div>
                            <div id="importDetails" class="mt-2" style="display: none;">
                                <h6>Import Details:</h6>
                                <div id="importDetailsContent"></div>
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
$(document).ready(function() {
    // Preview functionality
    $('#previewBtn').on('click', function() {
        const formData = new FormData($('#importForm')[0]);
        const previewBtn = $('#previewBtn');
        const importBtn = $('#importBtn');
        const previewResult = $('#previewResult');
        const previewContent = $('#previewContent');
        const importResult = $('#importResult');
        
        // Validate required fields
        if (!formData.get('csv_file').name) {
            alert('Please select a CSV file');
            return;
        }
        
        // Show loading state
        previewBtn.prop('disabled', true).html('<i class="bi bi-hourglass-split me-2"></i>Generating Preview...');
        previewResult.hide();
        importResult.hide();
        
        $.ajax({
            url: '{{ route("settings.import.linkedin.preview") }}',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                if (response.success) {
                    const preview = response.preview;
                    const importPreview = preview.import_preview;
                    
                    let previewHtml = '';
                    
                    // Person section
                    previewHtml += '<div class="mb-4">';
                    previewHtml += '<h6><i class="bi bi-person me-2"></i>Person</h6>';
                    if (importPreview.person.action === 'error') {
                        previewHtml += `<div class="alert alert-danger">
                            <strong>Error:</strong> Person "${importPreview.person.name}" not found. 
                            Please enable "Create Person if Not Found" to proceed.
                        </div>`;
                    } else {
                        const personBadge = importPreview.person.action === 'create' ? 
                            '<span class="badge bg-success">Will Create</span>' : 
                            '<span class="badge bg-info">Will Connect</span>';
                        previewHtml += `<p><strong>${importPreview.person.name}</strong> ${personBadge}</p>`;
                    }
                    previewHtml += '</div>';
                    
                    // Summary section
                    previewHtml += '<div class="mb-4">';
                    previewHtml += '<h6><i class="bi bi-list-ul me-2"></i>Summary</h6>';
                    previewHtml += `<div class="row">
                        <div class="col-md-3">
                            <div class="text-center">
                                <h5 class="text-primary">${importPreview.positions.total}</h5>
                                <small class="text-muted">Total Positions</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center">
                                <h5 class="text-success">${importPreview.positions.valid}</h5>
                                <small class="text-muted">Valid Positions</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center">
                                <h5 class="text-warning">${importPreview.organisations.total_new}</h5>
                                <small class="text-muted">New Organisations</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center">
                                <h5 class="text-info">${importPreview.roles.total_new}</h5>
                                <small class="text-muted">New Roles</small>
                            </div>
                        </div>
                    </div>`;
                    previewHtml += '</div>';
                    
                    // Organisations section
                    if (importPreview.organisations.will_create.length > 0 || importPreview.organisations.will_connect.length > 0) {
                        previewHtml += '<div class="mb-4">';
                        previewHtml += '<h6><i class="bi bi-building me-2"></i>Organisations</h6>';
                        
                        if (importPreview.organisations.will_create.length > 0) {
                            previewHtml += '<div class="mb-2">';
                            previewHtml += `<strong class="text-success">Will Create (${importPreview.organisations.will_create.length}):</strong>`;
                            previewHtml += '<ul class="list-unstyled ms-3">';
                            importPreview.organisations.will_create.forEach(function(org) {
                                previewHtml += `<li><i class="bi bi-plus-circle text-success me-1"></i>${org}</li>`;
                            });
                            previewHtml += '</ul></div>';
                        }
                        
                        if (importPreview.organisations.will_connect.length > 0) {
                            previewHtml += '<div class="mb-2">';
                            previewHtml += `<strong class="text-info">Will Connect (${importPreview.organisations.will_connect.length}):</strong>`;
                            previewHtml += '<ul class="list-unstyled ms-3">';
                            importPreview.organisations.will_connect.forEach(function(org) {
                                previewHtml += `<li><i class="bi bi-link text-info me-1"></i>${org}</li>`;
                            });
                            previewHtml += '</ul></div>';
                        }
                        
                        previewHtml += '</div>';
                    }
                    
                    // Roles section
                    if (importPreview.roles.will_create.length > 0 || importPreview.roles.will_connect.length > 0) {
                        previewHtml += '<div class="mb-4">';
                        previewHtml += '<h6><i class="bi bi-briefcase me-2"></i>Roles</h6>';
                        
                        if (importPreview.roles.will_create.length > 0) {
                            previewHtml += '<div class="mb-2">';
                            previewHtml += `<strong class="text-success">Will Create (${importPreview.roles.will_create.length}):</strong>`;
                            previewHtml += '<ul class="list-unstyled ms-3">';
                            importPreview.roles.will_create.forEach(function(role) {
                                previewHtml += `<li><i class="bi bi-plus-circle text-success me-1"></i>${role}</li>`;
                            });
                            previewHtml += '</ul></div>';
                        }
                        
                        if (importPreview.roles.will_connect.length > 0) {
                            previewHtml += '<div class="mb-2">';
                            previewHtml += `<strong class="text-info">Will Connect (${importPreview.roles.will_connect.length}):</strong>`;
                            previewHtml += '<ul class="list-unstyled ms-3">';
                            importPreview.roles.will_connect.forEach(function(role) {
                                previewHtml += `<li><i class="bi bi-link text-info me-1"></i>${role}</li>`;
                            });
                            previewHtml += '</ul></div>';
                        }
                        
                        previewHtml += '</div>';
                    }
                    
                    // Positions details
                    if (importPreview.positions.details.length > 0) {
                        previewHtml += '<div class="mb-4">';
                        previewHtml += '<h6><i class="bi bi-table me-2"></i>Position Details</h6>';
                        previewHtml += '<div class="table-responsive">';
                        previewHtml += '<table class="table table-sm">';
                        previewHtml += '<thead><tr><th>Row</th><th>Company</th><th>Title</th><th>Organisation</th><th>Role</th><th>Dates</th><th>Status</th></tr></thead><tbody>';
                        
                        importPreview.positions.details.forEach(function(detail) {
                            const rowClass = detail.valid ? '' : 'table-danger';
                            const statusBadge = detail.valid ? 
                                '<span class="badge bg-success">Valid</span>' : 
                                '<span class="badge bg-danger">Invalid</span>';
                            
                            const orgAction = detail.organisation_action === 'create' ? 
                                '<i class="bi bi-plus-circle text-success" title="Will Create"></i>' : 
                                '<i class="bi bi-link text-info" title="Will Connect"></i>';
                            
                            const roleAction = detail.role_action === 'create' ? 
                                '<i class="bi bi-plus-circle text-success" title="Will Create"></i>' : 
                                '<i class="bi bi-link text-info" title="Will Connect"></i>';
                            
                            previewHtml += `<tr class="${rowClass}">
                                <td>${detail.row}</td>
                                <td>${detail.company}</td>
                                <td>${detail.title}</td>
                                <td>${orgAction} ${detail.company}</td>
                                <td>${roleAction} ${detail.title}</td>
                                <td>${detail.start_date} - ${detail.end_date || 'ongoing'}</td>
                                <td>${statusBadge}</td>
                            </tr>`;
                        });
                        
                        previewHtml += '</tbody></table></div></div>';
                    }
                    
                    // Show errors if any
                    if (importPreview.positions.invalid > 0) {
                        previewHtml += '<div class="alert alert-warning">';
                        previewHtml += `<strong>Warning:</strong> ${importPreview.positions.invalid} positions have validation errors and will be skipped.`;
                        previewHtml += '</div>';
                    }
                    
                    // Show confirm button if person is valid
                    if (importPreview.person.action !== 'error') {
                        previewHtml += '<div class="text-center mt-3">';
                        previewHtml += '<button type="button" class="btn btn-success" id="confirmImportBtn">';
                        previewHtml += '<i class="bi bi-check-circle me-2"></i>Confirm Import';
                        previewHtml += '</button>';
                        previewHtml += '</div>';
                    }
                    
                    previewContent.html(previewHtml);
                    previewResult.show();
                    
                    // Show import button if preview is successful
                    if (importPreview.person.action !== 'error') {
                        importBtn.show();
                    }
                    
                } else {
                    alert('Preview failed: ' + response.message);
                }
            },
            error: function(xhr) {
                let errorMessage = 'Preview failed';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                }
                alert(errorMessage);
            },
            complete: function() {
                // Reset button state
                previewBtn.prop('disabled', false).html('<i class="bi bi-eye me-2"></i>Preview Import');
            }
        });
    });
    
    // Confirm import button
    $(document).on('click', '#confirmImportBtn', function() {
        $('#importBtn').click();
    });
    
    // Import form submission
    $('#importForm').on('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const importBtn = $('#importBtn');
        const importResult = $('#importResult');
        const importAlert = $('#importAlert');
        const importMessage = $('#importMessage');
        const importDetails = $('#importDetails');
        const importDetailsContent = $('#importDetailsContent');
        const importLoadingOverlay = $('#importLoadingOverlay');
        
        // Show loading state
        importBtn.prop('disabled', true).html('<i class="bi bi-hourglass-split me-2"></i>Importing...');
        importResult.hide();
        importLoadingOverlay.show();
        
        // Initialize progress bar
        const progressBar = $('#importProgressBar');
        const progressText = $('#importProgressText');
        const progressDetails = $('#importProgressDetails');
        let progress = 0;
        
        // Start progress animation
        const progressInterval = setInterval(function() {
            progress += Math.random() * 15; // Random increment to simulate progress
            if (progress > 90) progress = 90; // Don't go to 100% until complete
            
            progressBar.css('width', progress + '%').attr('aria-valuenow', progress);
            progressText.text(Math.round(progress) + '%');
            
            if (progress < 30) {
                progressDetails.text('Parsing CSV data...');
            } else if (progress < 60) {
                progressDetails.text('Creating spans and connections...');
            } else if (progress < 90) {
                progressDetails.text('Finalizing import...');
            }
        }, 200);
        
        $.ajax({
            url: '{{ route("settings.import.linkedin.import") }}',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                if (response.success) {
                    const result = response.result;
                    
                    // Show success message
                    importAlert.removeClass('alert-danger').addClass('alert-success');
                    importMessage.html(`
                        <strong>Import completed successfully!</strong><br>
                        Processed ${result.positions.processed} positions<br>
                        Created ${result.positions.created} new positions<br>
                        Created ${result.organisations.created} new organisations<br>
                        Created ${result.roles.created} new roles
                    `);
                    
                    // Show detailed results
                    if (result.positions.details.length > 0) {
                        let detailsHtml = '<div class="table-responsive"><table class="table table-sm">';
                        detailsHtml += '<thead><tr><th>Company</th><th>Title</th><th>Status</th><th>Dates</th></tr></thead><tbody>';
                        
                        result.positions.details.forEach(function(detail) {
                            if (detail.success) {
                                detailsHtml += `<tr>
                                    <td>${detail.company}</td>
                                    <td>${detail.title}</td>
                                    <td><span class="badge bg-${detail.action === 'created' ? 'success' : 'info'}">${detail.action}</span></td>
                                    <td>${detail.start_date} - ${detail.end_date || 'ongoing'}</td>
                                </tr>`;
                            } else {
                                detailsHtml += `<tr class="table-danger">
                                    <td>${detail.company || 'N/A'}</td>
                                    <td>${detail.title || 'N/A'}</td>
                                    <td><span class="badge bg-danger">error</span></td>
                                    <td>${detail.error}</td>
                                </tr>`;
                            }
                        });
                        
                        detailsHtml += '</tbody></table></div>';
                        importDetailsContent.html(detailsHtml);
                        importDetails.show();
                    }
                    
                } else {
                    // Show error message
                    importAlert.removeClass('alert-success').addClass('alert-danger');
                    importMessage.html(`<strong>Import failed:</strong> ${response.message}`);
                    importDetails.hide();
                }
                
                importResult.show();
                // Small delay to show completion message
                setTimeout(function() {
                    importLoadingOverlay.hide();
                }, 500);
                
            },
            error: function(xhr) {
                let errorMessage = 'Import failed';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                }
                
                importAlert.removeClass('alert-success').addClass('alert-danger');
                importMessage.html(`<strong>Import failed:</strong> ${errorMessage}`);
                importResult.show();
                importDetails.hide();
                // Small delay to show completion message
                setTimeout(function() {
                    importLoadingOverlay.hide();
                }, 500);
            },
            complete: function() {
                // Complete progress bar
                clearInterval(progressInterval);
                progressBar.css('width', '100%').attr('aria-valuenow', 100);
                progressText.text('100%');
                progressDetails.text('Import completed!');
                
                // Reset button state
                importBtn.prop('disabled', false).html('<i class="bi bi-cloud-upload me-2"></i>Confirm Import');
            }
        });
    });
    
    // Preview functionality (optional)
    $('#csv_file').on('change', function() {
        const file = this.files[0];
        if (file) {
            // You could add preview functionality here if needed
            console.log('File selected:', file.name);
        }
    });
});
</script>
@endpush 