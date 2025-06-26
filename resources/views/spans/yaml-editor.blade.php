@extends('layouts.app')

@section('page_title')
    YAML Editor - {{ $span->name }}
@endsection

@section('page_tools')
    <div class="d-flex gap-2">
        <a href="{{ route('spans.show', $span) }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Back to Span
        </a>
        <a href="{{ route('spans.edit', $span) }}" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-pencil me-1"></i>Form Editor
        </a>
    </div>
@endsection

@push('styles')
<style>
    .yaml-editor-container {
        height: calc(100vh - 250px);
        min-height: 600px;
    }
    
    .middle-column {
        height: calc(100vh - 250px);
        min-height: 600px;
        display: flex;
        flex-direction: column;
    }
    
    .middle-column .card {
        display: flex;
        flex-direction: column;
    }
    
    .middle-column .card:first-child {
        flex: 0 0 auto;
        max-height: 40%;
    }
    
    .middle-column .card:last-child {
        flex: 1;
    }
    
    .middle-column .card-body {
        flex: 1;
        overflow-y: auto;
    }
    
    .yaml-textarea {
        font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
        font-size: 14px;
        line-height: 1.5;
        border-radius: 8px;
        border: 2px solid #e9ecef;
        background-color: #f8f9fa;
        transition: border-color 0.15s ease-in-out;
    }
    
    .yaml-textarea:focus {
        border-color: #0d6efd;
        outline: 0;
        box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
    }
    
    .yaml-textarea.is-invalid {
        border-color: #dc3545;
    }
    
    .yaml-textarea.is-valid {
        border-color: #198754;
    }
    

    
    .error-list {
        margin: 0;
        padding-left: 1.5rem;
    }
    
    .error-list li {
        margin-bottom: 0.5rem;
    }
    
    .action-buttons {
        position: sticky;
        bottom: 0;
        background: white;
        border-top: 1px solid #e9ecef;
        padding: 1rem 0;
        margin-top: 1rem;
    }
    
    .spinner-border-sm {
        width: 1rem;
        height: 1rem;
    }
</style>
@endpush

@section('content')
<div class="container-fluid">
    <div class="row">
        <!-- Left column: YAML Editor -->
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-code-square me-2"></i>YAML Editor
                    </h5>
                    <div class="d-flex gap-2">
                        <span id="validation-status" class="badge bg-secondary">Ready</span>
                        <span id="char-count" class="text-muted small">0 characters</span>
                    </div>
                </div>
                <div class="card-body p-0 yaml-editor-container">
                    <textarea 
                        id="yaml-content" 
                        class="form-control yaml-textarea h-100 border-0 rounded-0"
                        placeholder="Loading YAML content..."
                        style="resize: none; font-size: 12px;"
                    >{{ $yamlContent }}</textarea>
                </div>
            </div>
        </div>
        
        <!-- Middle column: Visual Translation -->
        <div class="col-lg-4">
            <div class="middle-column">
                <!-- Combined Analysis Card -->
                <div class="card mb-2">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h6 class="card-title mb-0">
                                <i class="bi bi-check-circle me-2"></i>Validation & Impact
                            </h6>
                            <div class="d-flex gap-2">
                                <button type="button" id="validate-btn" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-check-square me-1"></i>Validate
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-3">
                        <div id="validation-results">
                            <div class="text-muted">
                                <i class="bi bi-info-circle me-1"></i>
                                Make changes to see validation results
                            </div>
                        </div>
                        
                        <div id="impact-results" class="mt-3" style="display: none;">
                            <!-- Impact analysis will appear here -->
                        </div>
                    </div>
                </div>

                <!-- Visual Translation Card -->
                <div class="card" id="translation-card">
                    <div class="card-header">
                        <h6 class="card-title mb-0">
                            <i class="bi bi-eye me-2"></i>Visual Translation
                        </h6>
                    </div>
                    <div class="card-body p-3">
                        <div id="visual-translation">
                            <div class="text-muted">
                                <i class="bi bi-info-circle me-1"></i>
                                Visual translation will appear here when YAML is valid
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Right column: Information & Help -->
        <div class="col-lg-4">
            
            <!-- Information Panel -->
            <div class="card mb-3">
                <div class="card-header">
                    <h6 class="card-title mb-0">
                        <i class="bi bi-info-circle me-2"></i>Information
                    </h6>
                </div>
                <div class="card-body">
                    <dl class="row small">
                        <dt class="col-5">Span ID:</dt>
                        <dd class="col-7"><code>{{ $span->id }}</code></dd>
                        
                        <dt class="col-5">Current Type:</dt>
                        <dd class="col-7">{{ $span->type_id }}</dd>
                        
                        <dt class="col-5">State:</dt>
                        <dd class="col-7">
                            <span class="badge bg-{{ $span->state === 'complete' ? 'success' : ($span->state === 'placeholder' ? 'warning' : 'secondary') }}">
                                {{ ucfirst($span->state) }}
                            </span>
                        </dd>
                        
                        <dt class="col-5">Connections:</dt>
                        <dd class="col-7">{{ $span->connectionsAsSubject->count() + $span->connectionsAsObject->count() }}</dd>
                        
                        <dt class="col-5">Access Level:</dt>
                        <dd class="col-7">{{ ucfirst($span->access_level) }}</dd>
                    </dl>
                </div>
            </div>
            
            <!-- YAML Format Help -->
            <div class="card">
                <div class="card-header">
                    <h6 class="card-title mb-0">
                        <i class="bi bi-question-circle me-2"></i>YAML Format Help
                    </h6>
                </div>
                <div class="card-body">
                    <div class="small text-muted">
                        <h6>Required Fields:</h6>
                        <ul class="mb-3">
                            <li><code>name</code> - The span name</li>
                            <li><code>type</code> - The span type ({{ $spanTypes->pluck('type_id')->implode(', ') }})</li>
                        </ul>
                        
                        <h6>Date Format:</h6>
                        <ul class="mb-3">
                            <li><code>2023</code> - Year only</li>
                            <li><code>2023-06</code> - Year and month</li>
                            <li><code>2023-06-15</code> - Full date</li>
                        </ul>
                        
                        <h6>Connection Types:</h6>
                        <ul class="mb-3">
                            <li><code>parents</code> - Parent relationships</li>
                            <li><code>children</code> - Child relationships</li>
                            @foreach($connectionTypes as $connectionType)
                                @if($connectionType->type !== 'family')
                                    <li><code>{{ $connectionType->type }}</code> - {{ $connectionType->forward_description }}</li>
                                @endif
                            @endforeach
                        </ul>
                        
                        <h6>Connection Dates:</h6>
                        <p class="mb-2">Each connection can include date information:</p>
                        <pre class="small bg-light p-2 rounded mb-0">employment:
  - name: 'The University of Edinburgh'
    id: abc123...
    type: organisation
    start_date: '2005-01'
    end_date: '2006-02'
    metadata:
      role: 'Web Developer'</pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Action Buttons (Sticky) -->
    <div class="action-buttons">
        <div class="d-flex justify-content-between align-items-center">
            <div class="text-muted small">
                <i class="bi bi-exclamation-triangle-fill text-warning me-1"></i>
                Changes are not saved until you click "Apply Changes"
            </div>
            <div class="d-flex gap-2">
                <button type="button" id="apply-btn" class="btn btn-success" disabled>
                    <i class="bi bi-cloud-upload me-1"></i>Apply Changes
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    const yamlTextarea = $('#yaml-content');
    const validateBtn = $('#validate-btn');
    const applyBtn = $('#apply-btn');
    const validationStatus = $('#validation-status');
    const validationResults = $('#validation-results');
    const charCount = $('#char-count');
    
    let validationTimer;
    let lastValidationResult = null;
    let originalContent = yamlTextarea.val();
    
    // Update character count
    function updateCharCount() {
        const count = yamlTextarea.val().length;
        charCount.text(count.toLocaleString() + ' characters');
    }
    
    // Update validation status
    function updateValidationStatus(status, variant = 'secondary') {
        validationStatus
            .removeClass('bg-secondary bg-success bg-danger bg-warning')
            .addClass(`bg-${variant}`)
            .text(status);
    }
    
    // Show validation results
    function showValidationResults(result) {
        let html = '';
        
        if (result.success) {
            html = `
                <div class="text-success">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    <strong>Valid YAML</strong>
                    <div class="mt-2 small">
                        Ready to apply changes to the database.
                    </div>
                </div>
            `;
            yamlTextarea.removeClass('is-invalid').addClass('is-valid');
            applyBtn.prop('disabled', false);
            updateValidationStatus('Valid', 'success');
            
            // Show visual translation and impact analysis
            showVisualTranslation(result.visual || []);
            showImpactAnalysis(result.impacts || []);
        } else {
            html = `
                <div class="text-danger">
                    <i class="bi bi-exclamation-circle-fill me-2"></i>
                    <strong>Validation Errors</strong>
                    <ul class="error-list mt-2">
                        ${result.errors.map(error => `<li>${error}</li>`).join('')}
                    </ul>
                </div>
            `;
            yamlTextarea.removeClass('is-valid').addClass('is-invalid');
            applyBtn.prop('disabled', true);
            updateValidationStatus('Invalid', 'danger');
            
            // Hide visual translation and impact analysis on error  
            $('#visual-translation').html(`
                <div class="text-muted">
                    <i class="bi bi-info-circle me-1"></i>
                    Visual translation will appear here when YAML is valid
                </div>
            `);
            $('#impact-results').hide();
        }
        
        validationResults.html(html);
        lastValidationResult = result;
    }

    // Show visual translation
    function showVisualTranslation(translations) {
        if (!translations || translations.length === 0) {
            $('#visual-translation').html(`
                <div class="text-muted">
                    <i class="bi bi-info-circle me-1"></i>
                    No visual translations available
                </div>
            `);
            return;
        }

        let html = '';
        let currentSection = '';

        translations.forEach((translation, index) => {
            if (translation.section !== currentSection) {
                if (currentSection !== '') {
                    html += '</div>';
                }
                currentSection = translation.section;
                
                let sectionTitle = '';
                let sectionIcon = '';
                switch (translation.section) {
                    case 'identity':
                        sectionTitle = 'Identity';
                        sectionIcon = 'bi-person-badge';
                        break;
                    case 'dates':
                        sectionTitle = 'Timeline';
                        sectionIcon = 'bi-calendar-event';
                        break;
                    case 'connections':
                        sectionTitle = 'Connections';
                        sectionIcon = 'bi-diagram-3';
                        break;
                    default:
                        sectionTitle = translation.section;
                        sectionIcon = 'bi-info-circle';
                }
                
                html += `
                    <div class="mb-3">
                        <h6 class="text-muted mb-2">
                            <i class="${sectionIcon} me-1"></i>${sectionTitle}
                        </h6>
                `;
            }
            
            html += `<div class="small mb-1">${translation.text}</div>`;
        });

        if (currentSection !== '') {
            html += '</div>';
        }

        $('#visual-translation').html(html);
    }

    // Show impact analysis
    function showImpactAnalysis(impacts) {
        if (!impacts || impacts.length === 0) {
            $('#impact-results').hide();
            return;
        }

        let html = '';
        impacts.forEach((impact, index) => {
            let badgeClass = '';
            let icon = '';
            
            switch (impact.type) {
                case 'info':
                    badgeClass = 'bg-info';
                    icon = 'bi-info-circle';
                    break;
                case 'warning':
                    badgeClass = 'bg-warning';
                    icon = 'bi-exclamation-triangle';
                    break;
                case 'danger':
                    badgeClass = 'bg-danger';
                    icon = 'bi-exclamation-octagon';
                    break;
                default:
                    badgeClass = 'bg-secondary';
                    icon = 'bi-info-circle';
            }
            
            html += `
                <div class="impact-item mb-3">
                    <div class="d-flex align-items-start mb-2">
                        <span class="badge ${badgeClass} me-2 mt-1">
                            <i class="${icon}"></i>
                        </span>
                        <div class="small">${impact.message}</div>
                    </div>
            `;
            
            // Show action options for name conflicts
            if (impact.action_options) {
                html += `
                    <div class="ms-4 mt-2">
                        <div class="small text-muted mb-2">Choose how to handle this:</div>
                        <div class="btn-group-vertical w-100" role="group">
                `;
                
                Object.entries(impact.action_options).forEach(([key, description]) => {
                    let btnClass = 'btn-outline-secondary';
                    let btnIcon = 'bi-circle';
                    
                    if (key === 'update_span') {
                        btnClass = 'btn-outline-primary';
                        btnIcon = 'bi-arrow-repeat';
                    } else if (key === 'keep_reference') {
                        btnClass = 'btn-outline-success';
                        btnIcon = 'bi-shield-check';
                    } else if (key === 'ignore') {
                        btnClass = 'btn-outline-warning';
                        btnIcon = 'bi-exclamation-triangle';
                    }
                    
                    html += `
                        <button type="button" class="btn ${btnClass} btn-sm text-start name-conflict-action" 
                                data-impact-index="${index}" 
                                data-action="${key}"
                                data-span-id="${impact.span_id || ''}"
                                data-current-name="${impact.current_name || ''}"
                                data-yaml-name="${impact.yaml_name || ''}">
                            <i class="${btnIcon} me-2"></i>${description}
                        </button>
                    `;
                });
                
                html += `
                        </div>
                    </div>
                `;
            }
            
            html += `</div>`;
        });

        $('#impact-results').html(html).show();
        
        // Add click handlers for action buttons
        $('.name-conflict-action').on('click', function() {
            const action = $(this).data('action');
            const spanId = $(this).data('span-id');
            const currentName = $(this).data('current-name');
            const yamlName = $(this).data('yaml-name');
            const impactIndex = $(this).data('impact-index');
            
            handleNameConflictAction(action, spanId, currentName, yamlName, impactIndex);
                 });
    }
    
    // Handle name conflict action selection
    function handleNameConflictAction(action, spanId, currentName, yamlName, impactIndex) {
        console.log('Name conflict action:', { action, spanId, currentName, yamlName, impactIndex });
        
        // Visual feedback - highlight selected action
        $(`.name-conflict-action[data-impact-index="${impactIndex}"]`).removeClass('btn-outline-primary btn-outline-success btn-outline-warning active');
        $(`.name-conflict-action[data-impact-index="${impactIndex}"][data-action="${action}"]`).addClass('active');
        
        switch (action) {
            case 'update_span':
                if (confirm(`This will update "${currentName}" to "${yamlName}" everywhere it appears. Continue?`)) {
                    updateSpanName(spanId, yamlName, impactIndex);
                } else {
                    // User cancelled - remove the active state from the button
                    $(`.name-conflict-action[data-impact-index="${impactIndex}"][data-action="${action}"]`).removeClass('active');
                    return;
                }
                break;
                
            case 'keep_reference':
                updateYamlWithDatabaseName(spanId, currentName, impactIndex);
                break;
                
            case 'ignore':
                // Just mark as resolved, no action needed
                markConflictResolved(impactIndex, 'ignored');
                break;
        }
    }
    
    // Update span name in database
    function updateSpanName(spanId, newName, impactIndex) {
        console.log('Updating span name:', { spanId, newName, impactIndex });
        
        $.ajax({
            url: `/spans/${spanId}`,
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            data: {
                name: newName,
                state: 'placeholder', // Required field for validation
                _method: 'PUT'
            },
            success: function(result) {
                console.log('Span name updated successfully:', result);
                markConflictResolved(impactIndex, 'span_updated');
                // Re-validate to refresh impact analysis
                validateYaml();
            },
            error: function(xhr) {
                console.error('Failed to update span name:', xhr);
                let errorMessage = 'Unknown error';
                
                if (xhr.status === 403) {
                    errorMessage = 'You do not have permission to update this span';
                } else if (xhr.responseJSON?.message) {
                    errorMessage = xhr.responseJSON.message;
                } else if (xhr.responseJSON?.errors) {
                    // Handle validation errors
                    const errors = Object.values(xhr.responseJSON.errors).flat();
                    errorMessage = errors.join(', ');
                } else if (xhr.responseText) {
                    errorMessage = xhr.responseText;
                }
                
                alert('Failed to update span name: ' + errorMessage);
                
                // Remove the active state from the button since the update failed
                $(`.name-conflict-action[data-impact-index="${impactIndex}"]`).removeClass('active');
            }
        });
    }
    
    // Update YAML content to match database name
    function updateYamlWithDatabaseName(spanId, databaseName, impactIndex) {
        const currentYaml = yamlTextarea.val();
        // This is a simple approach - in production you might want more sophisticated YAML parsing
        const updatedYaml = currentYaml.replace(
            new RegExp(`id: ${spanId}[\\s\\S]*?name: '[^']*'`, 'g'),
            (match) => match.replace(/name: '[^']*'/, `name: '${databaseName}'`)
        );
        
        yamlTextarea.val(updatedYaml);
        markConflictResolved(impactIndex, 'yaml_updated');
        // Re-validate with updated YAML
        validateYaml();
    }
    
    // Mark conflict as resolved
    function markConflictResolved(impactIndex, resolution) {
        const impactItem = $(`.impact-item:eq(${impactIndex})`);
        let message = '';
        let badgeClass = '';
        
        switch (resolution) {
            case 'span_updated':
                message = 'Span name updated in database';
                badgeClass = 'bg-success';
                break;
            case 'yaml_updated':
                message = 'YAML updated to match database';
                badgeClass = 'bg-success';
                break;
            case 'ignored':
                message = 'Conflict ignored - will apply as-is';
                badgeClass = 'bg-warning';
                break;
        }
        
        impactItem.find('.badge').removeClass('bg-warning bg-danger').addClass(badgeClass);
        impactItem.find('.small').append(`<br><em class="text-success">${message}</em>`);
        impactItem.find('.btn-group-vertical').hide();
    }
    
    // Validate YAML content
    function validateYaml() {
        const content = yamlTextarea.val().trim();
        
        if (!content) {
            showValidationResults({
                success: false,
                errors: ['YAML content is required']
            });
            return;
        }
        
        updateValidationStatus('Validating...', 'warning');
        
        $.ajax({
            url: '{{ route("spans.yaml-validate", $span) }}',
            method: 'POST',
            data: {
                yaml_content: content,
                _token: $('meta[name="csrf-token"]').attr('content')
            },
            success: function(result) {
                showValidationResults(result);
            },
            error: function(xhr) {
                let errorMsg = 'Validation failed';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                }
                
                showValidationResults({
                    success: false,
                    errors: [errorMsg]
                });
            }
        });
    }
    
    // Apply YAML changes
    function applyChanges() {
        if (!lastValidationResult || !lastValidationResult.success) {
            alert('Please validate the YAML first');
            return;
        }
        
        const content = yamlTextarea.val();
        
        applyBtn.prop('disabled', true);
        const originalText = applyBtn.html();
        applyBtn.html('<span class="spinner-border spinner-border-sm me-1"></span>Applying...');
        
        $.ajax({
            url: '{{ route("spans.yaml-apply", $span) }}',
            method: 'POST',
            data: {
                yaml_content: content,
                _token: $('meta[name="csrf-token"]').attr('content')
            },
            success: function(result) {
                if (result.success) {
                    // Show success message
                    validationResults.html(`
                        <div class="text-success">
                            <i class="bi bi-check-circle-fill me-2"></i>
                            <strong>Changes Applied Successfully</strong>
                            <div class="mt-2 small">
                                ${result.message}
                            </div>
                        </div>
                    `);
                    
                    updateValidationStatus('Applied', 'success');
                    
                    // Update original content to prevent unsaved changes warning
                    originalContent = content;
                    
                    // Redirect after a delay
                    if (result.redirect) {
                        setTimeout(() => {
                            window.location.href = result.redirect;
                        }, 1500);
                    }
                } else {
                    throw new Error(result.message || 'Failed to apply changes');
                }
            },
            error: function(xhr) {
                let errorMsg = 'Failed to apply changes';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                }
                
                validationResults.html(`
                    <div class="text-danger">
                        <i class="bi bi-exclamation-circle-fill me-2"></i>
                        <strong>Error Applying Changes</strong>
                        <div class="mt-2 small">${errorMsg}</div>
                    </div>
                `);
                
                updateValidationStatus('Error', 'danger');
            },
            complete: function() {
                applyBtn.html(originalText);
                applyBtn.prop('disabled', lastValidationResult && !lastValidationResult.success);
            }
        });
    }
    
    // Auto-validate on content change (debounced)
    yamlTextarea.on('input', function() {
        updateCharCount();
        
        const hasChanges = yamlTextarea.val() !== originalContent;
        if (hasChanges) {
            yamlTextarea.removeClass('is-valid is-invalid');
            updateValidationStatus('Modified', 'warning');
            applyBtn.prop('disabled', true);
            
            // Clear existing timer
            clearTimeout(validationTimer);
            
            // Set new timer for auto-validation
            validationTimer = setTimeout(validateYaml, 1000);
        }
    });
    
    // Manual validation
    validateBtn.on('click', validateYaml);
    
    // Apply changes
    applyBtn.on('click', applyChanges);
    
    // Warn about unsaved changes
    $(window).on('beforeunload', function() {
        if (yamlTextarea.val() !== originalContent) {
            return 'You have unsaved changes. Are you sure you want to leave?';
        }
    });
    
    // Keyboard shortcuts
    yamlTextarea.on('keydown', function(e) {
        // Ctrl/Cmd + S to validate
        if ((e.ctrlKey || e.metaKey) && e.key === 's') {
            e.preventDefault();
            validateYaml();
        }
        
        // Ctrl/Cmd + Enter to apply (if valid)
        if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
            e.preventDefault();
            if (!applyBtn.prop('disabled')) {
                applyChanges();
            }
        }
    });
    
    // Initial setup
    updateCharCount();
    
    // Auto-validate on load if content exists
    if (yamlTextarea.val().trim()) {
        setTimeout(validateYaml, 500);
    }
});
</script>
@endpush 