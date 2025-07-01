@extends('layouts.app')

@section('page_title')
    YAML Editor - {{ $span ? $span->name : 'New Span' }}
@endsection

@section('page_tools')
    <div class="d-flex gap-2">
        @if($span)
            <a href="{{ route('spans.show', $span) }}" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i>Back to Span
            </a>
            <a href="{{ route('spans.edit', $span) }}" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-pencil me-1"></i>Form Editor
            </a>
        @else
            <a href="{{ route('spans.index') }}" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i>Back to Spans
            </a>
            <a href="{{ route('spans.create') }}" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-plus me-1"></i>Create New Span
            </a>
        @endif
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
    

    
    .spinner-border-sm {
        width: 1rem;
        height: 1rem;
    }
    
    /* Enhanced Visual Translation Styling */
    #visual-translation .badge {
        font-size: 0.9rem;
        padding: 0.4rem 0.6rem;
        margin-right: 0.3rem;
        margin-bottom: 0.3rem;
    }

    /* Interactive Button Styling */
    #visual-translation .interactive-entity,
    #visual-translation .interactive-predicate,
    #visual-translation .interactive-date {
        font-size: 0.85rem;
        padding: 0.3rem 0.6rem;
        margin: 0.1rem 0.2rem;
        border-radius: 0.375rem;
        transition: all 0.2s ease-in-out;
        cursor: pointer;
        white-space: nowrap;
        display: inline-block;
        text-decoration: none;
        border: 1px solid transparent;
    }

    #visual-translation .interactive-entity:hover,
    #visual-translation .interactive-predicate:hover,
    #visual-translation .interactive-date:hover {
        transform: translateY(-1px);
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        border-color: rgba(0,0,0,0.1);
    }

    #visual-translation .interactive-entity:active,
    #visual-translation .interactive-predicate:active,
    #visual-translation .interactive-date:active {
        transform: translateY(0);
        box-shadow: 0 1px 2px rgba(0,0,0,0.1);
    }

    /* Entity button colors */
    #visual-translation .interactive-entity.btn-primary {
        background-color: #0d6efd;
        color: white;
    }

    #visual-translation .interactive-entity.btn-success {
        background-color: #198754;
        color: white;
    }

    #visual-translation .interactive-entity.btn-warning {
        background-color: #ffc107;
        color: #000;
    }

    #visual-translation .interactive-entity.btn-danger {
        background-color: #dc3545;
        color: white;
    }

    #visual-translation .interactive-entity.btn-info {
        background-color: #0dcaf0;
        color: #000;
    }

    #visual-translation .interactive-entity.btn-dark {
        background-color: #212529;
        color: white;
    }

    #visual-translation .interactive-entity.btn-secondary {
        background-color: #6c757d;
        color: white;
    }

    /* Predicate button styling */
    #visual-translation .interactive-predicate {
        background-color: #f8f9fa;
        color: #495057;
        border-color: #dee2e6;
    }

    #visual-translation .interactive-predicate:hover {
        background-color: #e9ecef;
        border-color: #adb5bd;
    }

    /* Date button styling */
    #visual-translation .interactive-date {
        background-color: #e7f3ff;
        color: #0c63e4;
        border-color: #b6d4fe;
    }

    #visual-translation .interactive-date:hover {
        background-color: #d1ecf1;
        border-color: #9bd3d6;
    }

    /* Connector button styling */
    #visual-translation .interactive-connector {
        background-color: #f8f9fa;
        color: #6c757d;
        border-color: #dee2e6;
        font-weight: 500;
    }

    #visual-translation .interactive-connector:hover {
        background-color: #e9ecef;
        border-color: #adb5bd;
        color: #495057;
    }

    /* Inactive connector button styling */
    #visual-translation .btn.inactive {
        background-color: #e9ecef;
        color: #6c757d;
        border-color: #ced4da;
        font-weight: 500;
        opacity: 0.8;
        cursor: default;
    }

    #visual-translation .btn.inactive:hover {
        background-color: #e9ecef;
        color: #6c757d;
        border-color: #ced4da;
        opacity: 0.8;
    }

    /* Button group styling */
    #visual-translation .btn-group {
        margin: 0.2rem 0.3rem;
        display: inline-flex;
        vertical-align: middle;
    }

    #visual-translation .btn-group .btn {
        border-radius: 0;
        margin: 0;
        border-right-width: 0;
    }

    #visual-translation .btn-group .btn:first-child {
        border-top-left-radius: 0.375rem;
        border-bottom-left-radius: 0.375rem;
    }

    #visual-translation .btn-group .btn:last-child {
        border-top-right-radius: 0.375rem;
        border-bottom-right-radius: 0.375rem;
        border-right-width: 1px;
    }

    #visual-translation .btn-group .btn:only-child {
        border-radius: 0.375rem;
        border-right-width: 1px;
    }

    /* Sentence container styling */
    #visual-translation .sentence-container {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.2rem;
        margin-bottom: 0.5rem;
        padding: 0.5rem;
        background-color: #f8f9fa;
        border-radius: 0.5rem;
        border: 1px solid #e9ecef;
    }

    /* Text elements between buttons */
    #visual-translation .sentence-text {
        color: #6c757d;
        font-size: 0.9rem;
        margin: 0 0.2rem;
    }
</style>
@endpush

@section('content')
<!-- Create New Span Button -->
<div class="container-fluid mb-3">
    <div class="row">
        <div class="col-12">
            <div class="card border-primary">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-1">
                                <i class="bi bi-plus-circle me-2"></i>Create New Span
                            </h6>
                            <p class="card-text small text-muted mb-0">
                                Start with a blank YAML template and create a new span from scratch
                            </p>
                        </div>
                        <div class="d-flex gap-2 align-items-center">
                            <input type="text" class="form-control form-control-sm" id="new-span-name-input" 
                                   placeholder="Enter name..." style="width: 200px;">
                            <select class="form-select form-select-sm" id="new-span-type-selector" style="width: auto;">
                                <option value="">Select type...</option>
                                @foreach($spanTypes as $spanType)
                                    <option value="{{ $spanType->type_id }}">{{ ucfirst($spanType->type_id) }}</option>
                                @endforeach
                            </select>
                            <button type="button" id="create-new-span-btn" class="btn btn-primary btn-sm" disabled>
                                <i class="bi bi-plus-circle me-1"></i>Create New Span
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid">
    <div class="row">
        <!-- Left column: YAML Editor -->
        <div class="col-lg-3">
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
        <div class="col-lg-6">
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
                        
                        <div id="changes-summary" class="mt-3" style="display: none;">
                            <h6 class="text-primary mb-2">
                                <i class="bi bi-list-check me-1"></i>Changes Summary
                            </h6>
                            <div id="changes-list">
                                <!-- Changes will be listed here -->
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
                        <div id="visual-translation" style="font-size: 1rem; line-height: 1.5;">
                            <div class="text-muted">
                                <i class="bi bi-info-circle me-1"></i>
                                Visual translation will appear here when YAML is valid
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Right column: Tools & Information -->
        <div class="col-lg-3">
            
            <!-- YAML Tools -->
            <div class="card mb-3">
                <div class="card-header">
                    <h6 class="card-title mb-0">
                        <i class="bi bi-tools me-2"></i>YAML Tools
                    </h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <a href="{{ asset('docs/yaml-schema.yaml') }}" target="_blank" class="btn btn-outline-info btn-sm w-100 mb-3">
                            <i class="bi bi-file-text me-1"></i>View YAML Schema
                        </a>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label small mb-1">Add Connection:</label>
                        @if($span)
                        <div class="row g-2">
                            <div class="col-8">
                                <select class="form-select form-select-sm" id="connection-type-select">
                                    <option value="">Select type...</option>
                                    @if($span && $span->type_id === 'person')
                                        <option value="parents" data-allowed-types='["person"]'>Parents</option>
                                        <option value="children" data-allowed-types='["person"]'>Children</option>
                                    @endif
                                    @foreach($connectionTypes as $connectionType)
                                        @if($connectionType->type !== 'family')
                                            @php
                                                // Check if this span type can be the subject (parent) of this connection
                                                $canBeSubject = $span ? $connectionType->isSpanTypeAllowed($span->type_id, 'parent') : false;
                                                // Check if this span type can be the object (child) of this connection
                                                $canBeObject = $span ? $connectionType->isSpanTypeAllowed($span->type_id, 'child') : false;
                                            @endphp
                                            
                                            @if($canBeSubject)
                                                <option value="{{ $connectionType->type }}" data-allowed-types='{{ json_encode($connectionType->getAllowedObjectTypes()) }}'>
                                                    {{ ucfirst($connectionType->type) }}
                                                </option>
                                            @endif
                                        @endif
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-4">
                                <button class="btn btn-outline-primary btn-sm w-100" onclick="startConnectionFlow()">
                                    <i class="bi bi-plus"></i>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Connection Name Input (Hidden by default) -->
                        <div id="connection-name-input" class="mt-2" style="display: none;">
                            <div class="card border-primary">
                                <div class="card-body p-3">
                                    <h6 class="card-title mb-2" id="connection-title">Add Connection</h6>
                                    <div class="mb-2">
                                        <label class="form-label small mb-1">Name:</label>
                                        <input type="text" class="form-control form-control-sm" id="connection-name-field" 
                                               placeholder="Enter name..." autocomplete="off">
                                        <div class="form-text small">Start typing to search existing spans or create a new one</div>
                                    </div>
                                    
                                    <!-- Search Results -->
                                    <div id="connection-search-results" style="display: none;">
                                        <label class="form-label small mb-1">Existing spans:</label>
                                        <div class="list-group list-group-flush" id="search-results-list">
                                            <!-- Search results will appear here -->
                                        </div>
                                    </div>
                                    
                                    <!-- Create New Option -->
                                    <div id="connection-create-new" style="display: none;" class="mt-2">
                                        <div class="alert alert-info py-2 mb-2">
                                            <i class="bi bi-plus-circle me-1"></i>
                                            <small>No existing span found. Click below to create a new one.</small>
                                        </div>
                                        
                                        <!-- Type Selection (only shown if multiple types allowed) -->
                                        <div id="new-span-type-selection" style="display: none;" class="mb-2">
                                            <label class="form-label small mb-1">Span Type:</label>
                                            <select class="form-select form-select-sm" id="new-span-type-select">
                                                <!-- Options will be populated by JavaScript -->
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex gap-2 mt-3">
                                        <button class="btn btn-success btn-sm" id="create-new-span-btn" onclick="createNewSpanConnection()" style="display: none;">
                                            <i class="bi bi-plus-circle me-1"></i>Create New
                                        </button>
                                        <button class="btn btn-secondary btn-sm" onclick="cancelConnectionFlow()">
                                            <i class="bi bi-x me-1"></i>Cancel
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @else
                        <div class="alert alert-info py-2 mb-0">
                            <i class="bi bi-info-circle me-2"></i>
                            <small>Connection tools will be available after the span is created.</small>
                        </div>
                        @endif
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label small mb-1">Add Metadata Field:</label>
                        <div class="row g-2">
                            <div class="col-8">
                                <input type="text" class="form-control form-control-sm" id="metadata-key-input" placeholder="Field name...">
                            </div>
                            <div class="col-4">
                                <button class="btn btn-outline-secondary btn-sm w-100" onclick="addMetadataField()">
                                    <i class="bi bi-plus"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label small mb-1">Quick Actions:</label>
                        <div class="d-grid gap-1">
                            <button class="btn btn-outline-info btn-sm" onclick="addDateFields()">
                                <i class="bi bi-calendar-range me-1"></i>Add Start/End Dates
                            </button>
                            <button class="btn btn-outline-success btn-sm" onclick="addCustomFields()">
                                <i class="bi bi-gear me-1"></i>Add Custom Fields
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Information Panel -->
            @if($span)
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
            
            <!-- Incoming Connections Panel -->
            @if($span->connectionsAsObject->count() > 0)
            <div class="card mb-3">
                <div class="card-header">
                    <h6 class="card-title mb-0">
                        <i class="bi bi-arrow-down-circle me-2"></i>Incoming Connections
                    </h6>
                </div>
                <div class="card-body">
                    <p class="small text-muted mb-2">This span is referenced by other spans:</p>
                    <p class="small">
                        @php
                            $incomingLinks = [];
                            foreach($span->connectionsAsObject as $connection) {
                                $sourceSpan = $connection->parent;
                                $connectionType = $connection->type->type;
                                $incomingLinks[] = '<a href="' . route('spans.show', $sourceSpan) . '" class="text-decoration-none">' . 
                                    e($sourceSpan->name) . '</a> <span class="text-muted">(' . e($connectionType) . ')</span>';
                            }
                        @endphp
                        {!! implode(', ', $incomingLinks) !!}
                    </p>
                </div>
            </div>
            @endif
            @else
            <div class="card mb-3">
                <div class="card-header">
                    <h6 class="card-title mb-0">
                        <i class="bi bi-info-circle me-2"></i>New Span Information
                    </h6>
                </div>
                <div class="card-body">
                    <div class="alert alert-info mb-0">
                        <i class="bi bi-lightbulb me-2"></i>
                        <strong>Creating a new span</strong><br>
                        <small>Fill in the YAML content and validate to create a new span. The span will be created when you apply the changes.</small>
                    </div>
                </div>
            </div>
            @endif
            
            <!-- YAML Format Help (Collapsible) -->
            <div class="card mb-3">
                <div class="card-header">
                    <h6 class="mb-0">
                        <button class="btn btn-link p-0 text-decoration-none w-100 text-start" type="button" data-bs-toggle="collapse" data-bs-target="#help-content" aria-expanded="false" aria-controls="help-content">
                            <i class="bi bi-question-circle me-2"></i>YAML Format Help
                            <i class="bi bi-chevron-down float-end mt-1"></i>
                        </button>
                    </h6>
                </div>
                <div class="collapse" id="help-content">
                    <div class="card-body small text-muted">
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
                        
                        <h6>Connection Example:</h6>
                        <pre class="small bg-light p-2 rounded mb-0">employment:
  - name: 'Company Name'
    id: ''  # Leave empty for new
    type: organisation
    start_date: '2020-01'
    end_date: '2023-12'
    metadata:
      role: 'Developer'</pre>
                    </div>
                </div>
            </div>
            
            <!-- Apply Changes Section -->
            <div class="card">
                <div class="card-header">
                    <h6 class="card-title mb-0">
                        <i class="bi bi-cloud-upload me-2"></i>Apply Changes
                    </h6>
                </div>
                <div class="card-body">
                    <div class="alert alert-warning d-flex align-items-center py-2 mb-3">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <small>{{ $span ? 'Changes are not saved until you apply them to the database.' : 'The span will be created when you apply the YAML to the database.' }}</small>
                    </div>
                    <div class="d-grid">
                        <button type="button" id="apply-btn" class="btn btn-success" disabled>
                            <i class="bi bi-cloud-upload me-2"></i>{{ $span ? 'Apply Changes to Database' : 'Create Span from YAML' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/js-yaml/4.1.0/js-yaml.min.js"></script>
<script>
let yamlTextarea;
let activeDropdown = null; // Track the currently active dropdown

$(document).ready(function() {
    const yamlTextarea = $('#yaml-content');
    const validateBtn = $('#validate-btn');
    const applyBtn = $('#apply-btn');
    const validationStatus = $('#validation-status');
    const validationResults = $('#validation-results');
    const charCount = $('#char-count');
    
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
    
    // Update apply button text based on state
    function updateApplyButtonText() {
        const isNewSpan = {{ $span ? 'false' : 'true' }};
        const hasChanges = yamlTextarea.val() !== originalContent;
        const isValid = lastValidationResult && lastValidationResult.success;
        
        if (isNewSpan) {
            // For new spans, the button should be enabled if YAML is valid
            if (!isValid) {
                applyBtn.html('<i class="bi bi-cloud-upload me-2"></i>Validate YAML First');
            } else {
                applyBtn.html('<i class="bi bi-cloud-upload me-2"></i>Create Span from YAML');
            }
        } else {
            // For existing spans, check for changes
            if (!hasChanges) {
                applyBtn.html('<i class="bi bi-cloud-upload me-2"></i>No Changes to Apply');
            } else if (!isValid) {
                applyBtn.html('<i class="bi bi-cloud-upload me-2"></i>Validate YAML First');
            } else {
                applyBtn.html('<i class="bi bi-cloud-upload me-2"></i>Apply Changes to Database');
            }
        }
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
                        {{ $span ? 'Ready to apply changes to the database.' : 'Ready to create span from YAML.' }}
                    </div>
                </div>
            `;
            yamlTextarea.removeClass('is-invalid').addClass('is-valid');
            
            // Only enable apply button if there are changes (for existing spans) or if it's a new span
            const isNewSpan = {{ $span ? 'false' : 'true' }};
            const hasChanges = yamlTextarea.val() !== originalContent;
            applyBtn.prop('disabled', !isNewSpan && !hasChanges);
            updateApplyButtonText();
            
            updateValidationStatus('Valid', 'success');
            
            // Show visual translation and impact analysis
            showVisualTranslation(result.visual || []);
            showImpactAnalysis(result.impacts || []);
            showChangesSummary(result.data || {});
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
            updateApplyButtonText();
            updateValidationStatus('Invalid', 'danger');
            
            // Hide visual translation and impact analysis on error  
            $('#visual-translation').html(`
                <div class="text-muted">
                    <i class="bi bi-info-circle me-1"></i>
                    Visual translation will appear here when YAML is valid
                </div>
            `);
            $('#impact-results').hide();
            $('#changes-summary').hide();
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
            
            html += `<div class="mb-2">${translation.text}</div>`;
        });

        if (currentSection !== '') {
            html += '</div>';
        }

        $('#visual-translation').html(html);
        
        // Initialize interactive buttons after a short delay to ensure DOM is ready
        setTimeout(() => {
            initializeInteractiveButtons();
        }, 100);
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
                        <div class="small ${impact.indent ? 'ms-4' : ''}">${impact.message}</div>
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
            url: '{{ $span ? route("spans.yaml-validate", $span) : route("spans.yaml-validate-new") }}',
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
            url: '{{ $span ? route("spans.yaml-apply", $span) : route("spans.yaml-apply-new") }}',
            method: 'POST',
            data: {
                yaml_content: content,
                _token: $('meta[name="csrf-token"]').attr('content')
            },
            success: function(result) {
                if (result.success) {
                    // Show success message
                    const successTitle = '{{ $span ? "Changes Applied Successfully" : "Span Created Successfully" }}';
                    validationResults.html(`
                        <div class="text-success">
                            <i class="bi bi-check-circle-fill me-2"></i>
                            <strong>${successTitle}</strong>
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
                // After successful application, disable the button since there are no changes
                applyBtn.prop('disabled', true);
                updateApplyButtonText();
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
            updateApplyButtonText();
            
            // Hide analysis sections while editing
            $('#changes-summary').hide();
            $('#impact-results').hide();
        } else {
            // No changes - disable apply button and reset validation status
            applyBtn.prop('disabled', true);
            updateApplyButtonText();
            updateValidationStatus('Ready', 'secondary');
        }
    });
    
    // Validate when user focuses away from the editor
    yamlTextarea.on('blur', function() {
        const hasChanges = yamlTextarea.val() !== originalContent;
        if (hasChanges) {
            validateYaml();
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
    
    // Ensure apply button starts disabled
    applyBtn.prop('disabled', true);
    updateApplyButtonText();
    
    // Auto-validate on load if content exists
    if (yamlTextarea.val().trim()) {
        // Initialize visual translation for existing content
        validateYaml();
    }
    
    // Connection search functionality
    $('#connection-name-field').on('input', function() {
        const searchTerm = $(this).val().trim();
        
        // Clear existing timeout
        if (searchTimeout) {
            clearTimeout(searchTimeout);
        }
        
        if (searchTerm.length < 2) {
            $('#connection-search-results').hide();
            $('#connection-create-new').hide();
            $('#create-new-span-btn').hide();
            return;
        }
        
        // Debounce search
        searchTimeout = setTimeout(() => {
            searchForSpans(searchTerm);
        }, 300);
    });
    
    // YAML Tools Functions - Enhanced Connection Flow
    let currentConnectionType = null;
    let currentAllowedTypes = null;
    let searchTimeout = null;
    
    window.startConnectionFlow = function() {
        const selectElement = $('#connection-type-select');
        const connectionType = selectElement.val();
        
        if (!connectionType) {
            alert('Please select a connection type');
            return;
        }
        
        // Get allowed types from the selected option
        const selectedOption = selectElement.find('option:selected');
        const allowedTypesJson = selectedOption.data('allowed-types');
        
        console.log('startConnectionFlow - connectionType:', connectionType);
        console.log('startConnectionFlow - allowedTypesJson:', allowedTypesJson);
        console.log('startConnectionFlow - allowedTypesJson type:', typeof allowedTypesJson);
        
        // Store current connection details
        currentConnectionType = connectionType;
        
        // Handle the case where allowedTypesJson might be a string that needs parsing
        if (typeof allowedTypesJson === 'string') {
            try {
                currentAllowedTypes = JSON.parse(allowedTypesJson);
            } catch (e) {
                console.error('Failed to parse allowed types JSON:', e);
                currentAllowedTypes = [];
            }
        } else if (Array.isArray(allowedTypesJson)) {
            currentAllowedTypes = allowedTypesJson;
        } else {
            console.error('allowedTypesJson is neither string nor array:', allowedTypesJson);
            currentAllowedTypes = [];
        }
        
        console.log('startConnectionFlow - final currentAllowedTypes:', currentAllowedTypes);
        
        // Update UI
        const connectionTitle = $('#connection-title');
        const typeDisplay = connectionType.replace('_incoming', ' (incoming)');
        connectionTitle.text(`Add ${typeDisplay.charAt(0).toUpperCase() + typeDisplay.slice(1)} Connection`);
        
        // Show the connection input form
        $('#connection-name-input').slideDown();
        $('#connection-name-field').focus();
        
        // Clear previous state
        $('#connection-name-field').val('');
        $('#connection-search-results').hide();
        $('#connection-create-new').hide();
        $('#create-new-span-btn').hide();
        $('#search-results-list').empty();
    };
    
    window.cancelConnectionFlow = function() {
        $('#connection-name-input').slideUp();
        $('#connection-type-select').val('');
        currentConnectionType = null;
        currentAllowedTypes = null;
    };
    
    window.createNewSpanConnection = function() {
        const name = $('#connection-name-field').val().trim();
        if (!name) {
            alert('Please enter a name');
            return;
        }
        
        // Determine the span type - use selected type if available, otherwise default
        let spanType;
        const typeSelect = $('#new-span-type-select');
        if (typeSelect.is(':visible') && typeSelect.val()) {
            spanType = typeSelect.val();
        } else {
            spanType = currentAllowedTypes && currentAllowedTypes.length > 0 ? currentAllowedTypes[0] : 'person';
        }
        
        // Create the connection YAML with placeholder for new span
        addConnectionWithNewSpan(name, spanType);
    };
    
    window.addConnectionWithExistingSpan = function(spanId, spanName, spanType) {
        // Create the connection YAML with existing span details
        addConnectionWithSpan(spanId, spanName, spanType);
    };
    
    function addConnectionWithSpan(spanId, spanName, spanType) {
        // Don't include empty date fields to avoid validation issues
        const connectionTemplate = `
${currentConnectionType}:
  - name: '${spanName}'
    id: '${spanId}'
    type: ${spanType}
    metadata: {}`;
        
        insertYamlSection('connections', connectionTemplate);
        cancelConnectionFlow();
    }
    
    function addConnectionWithNewSpan(spanName, spanType) {
        // Generate a new UUID for the span
        const newUuid = generateUUID();
        
        // For new spans, don't include empty date fields to avoid validation issues
        const connectionTemplate = `
${currentConnectionType}:
  - name: '${spanName}'
    id: '${newUuid}'
    type: ${spanType}
    metadata: {}`;
        
        insertYamlSection('connections', connectionTemplate);
        cancelConnectionFlow();
    }
    
    // Simple UUID generator (v4)
    function generateUUID() {
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
            const r = Math.random() * 16 | 0;
            const v = c == 'x' ? r : (r & 0x3 | 0x8);
            return v.toString(16);
        });
    }
    
    // Legacy function for backwards compatibility
    window.addConnection = function() {
        startConnectionFlow();
    };
    
    // Make the create form function globally accessible
    window.showCreateNewForm = showCreateNewForm;
    
    // Search for existing spans
    function searchForSpans(searchTerm) {
        console.log('searchForSpans called with:', searchTerm);
        console.log('currentAllowedTypes:', currentAllowedTypes);
        
        if (!currentAllowedTypes || currentAllowedTypes.length === 0) {
            console.log('No allowed types, exiting search');
            return;
        }
        
        // Build search parameters
        const searchParams = new URLSearchParams({
            q: searchTerm,
            types: currentAllowedTypes.join(','),
            limit: 5
        });
        
        console.log('Search URL:', `/api/spans/search?${searchParams.toString()}`);
        
        // Make AJAX request to search for spans
        fetch(`/spans/search?${searchParams.toString()}`, {
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        })
        .then(response => {
            console.log('Search response status:', response.status);
            return response.json();
        })
        .then(data => {
            console.log('Search response data:', data);
            displaySearchResults(data.spans || [], searchTerm);
        })
        .catch(error => {
            console.error('Search error:', error);
            displaySearchResults([], searchTerm);
        });
    }
    
    function displaySearchResults(spans, searchTerm) {
        const resultsContainer = $('#search-results-list');
        const searchResults = $('#connection-search-results');
        const createNew = $('#connection-create-new');
        const createNewBtn = $('#create-new-span-btn');
        const typeSelection = $('#new-span-type-selection');
        const typeSelect = $('#new-span-type-select');
        
        resultsContainer.empty();
        
        if (spans.length > 0) {
            // Show existing spans
            searchResults.show();
            createNew.hide();
            createNewBtn.hide();
            typeSelection.hide();
            
            spans.forEach(span => {
                const resultItem = $(`
                    <button type="button" class="list-group-item list-group-item-action" 
                            onclick="addConnectionWithExistingSpan('${span.id}', '${span.name}', '${span.type_id}')">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong>${span.name}</strong>
                                <small class="text-muted d-block">${span.type_id} • ${span.state}</small>
                            </div>
                            <span class="badge bg-primary">Existing</span>
                        </div>
                    </button>
                `);
                resultsContainer.append(resultItem);
            });
            
            // Also show create new option
            const defaultType = currentAllowedTypes && currentAllowedTypes.length > 0 ? currentAllowedTypes[0] : 'person';
            const createNewItem = $(`
                <button type="button" class="list-group-item list-group-item-action list-group-item-light" 
                        onclick="showCreateNewForm('${searchTerm}')">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <strong>Create: "${searchTerm}"</strong>
                            <small class="text-muted d-block">New ${defaultType} span</small>
                        </div>
                        <span class="badge bg-success">New</span>
                    </div>
                </button>
            `);
            resultsContainer.append(createNewItem);
        } else {
            // No existing spans found, show create new option
            searchResults.hide();
            showCreateNewForm(searchTerm);
        }
    }
    
    function showCreateNewForm(searchTerm) {
        const createNew = $('#connection-create-new');
        const createNewBtn = $('#create-new-span-btn');
        const typeSelection = $('#new-span-type-selection');
        const typeSelect = $('#new-span-type-select');
        
        createNew.show();
        createNewBtn.show();
        createNewBtn.html(`<i class="bi bi-plus-circle me-1"></i>Create "${searchTerm}"`);
        
        // Set up type selection if multiple types are allowed
        if (currentAllowedTypes && currentAllowedTypes.length > 1) {
            typeSelection.show();
            typeSelect.empty();
            
            currentAllowedTypes.forEach(type => {
                typeSelect.append(`<option value="${type}">${type.charAt(0).toUpperCase() + type.slice(1)}</option>`);
            });
        } else {
            typeSelection.hide();
        }
    }
    
    window.addMetadataField = function() {
        const fieldName = $('#metadata-key-input').val().trim();
        if (!fieldName) {
            alert('Please enter a field name');
            return;
        }
        
        const metadataTemplate = `
${fieldName}: ''`;
        
        insertYamlSection('metadata', metadataTemplate);
        $('#metadata-key-input').val('');
    };
    
    window.addDateFields = function() {
        const dateTemplate = `
start: ''
end: ''`;
        
        insertYamlAtLevel('root', dateTemplate);
    };
    
    window.addCustomFields = function() {
        const customTemplate = `
metadata:
  custom_field: 'value'`;
        
        insertYamlAtLevel('root', customTemplate);
    };
    
    // Helper function to insert YAML sections
    function insertYamlSection(sectionName, content) {
        const currentYaml = yamlTextarea.val();
        const lines = currentYaml.split('\n');
        
        // Find the section or create it
        let sectionStartIndex = -1;
        let sectionEndIndex = -1;
        let indentLevel = 0;
        
        for (let i = 0; i < lines.length; i++) {
            const line = lines[i];
            if (line.trim().startsWith(sectionName + ':')) {
                sectionStartIndex = i;
                indentLevel = line.length - line.trimStart().length;
                
                // Find the end of this section
                for (let j = i + 1; j < lines.length; j++) {
                    const nextLine = lines[j];
                    if (nextLine.trim() === '' || nextLine.startsWith(' ')) {
                        continue; // Still in the section
                    } else if (nextLine.length - nextLine.trimStart().length <= indentLevel) {
                        sectionEndIndex = j - 1;
                        break;
                    }
                }
                
                if (sectionEndIndex === -1) {
                    sectionEndIndex = lines.length - 1;
                }
                break;
            }
        }
        
        if (sectionStartIndex === -1) {
            // Section doesn't exist, add it at the end
            const newSection = `${sectionName}:${content}`;
            const updatedYaml = currentYaml + (currentYaml.endsWith('\n') ? '' : '\n') + newSection + '\n';
            yamlTextarea.val(updatedYaml);
        } else {
            // Section exists, add to it
            const beforeSection = lines.slice(0, sectionEndIndex + 1);
            const afterSection = lines.slice(sectionEndIndex + 1);
            const indentedContent = content.split('\n').map(line => 
                line.trim() ? '  ' + line : line
            ).join('\n');
            
            const updatedLines = [...beforeSection, indentedContent, ...afterSection];
            yamlTextarea.val(updatedLines.join('\n'));
        }
        
        // Trigger validation
        yamlTextarea.trigger('input');
        
        // Focus and scroll to the added content
        yamlTextarea.focus();
        const textArea = yamlTextarea[0];
        textArea.scrollTop = textArea.scrollHeight;
    }
    
    // Helper function to insert YAML at root level
    function insertYamlAtLevel(level, content) {
        const currentYaml = yamlTextarea.val();
        const lines = currentYaml.split('\n');
        
        // Find a good place to insert (after existing root-level fields)
        let insertIndex = -1;
        
        for (let i = lines.length - 1; i >= 0; i--) {
            const line = lines[i];
            if (line.trim() && !line.startsWith(' ')) {
                insertIndex = i + 1;
                break;
            }
        }
        
        if (insertIndex === -1) {
            insertIndex = lines.length;
        }
        
        const beforeInsert = lines.slice(0, insertIndex);
        const afterInsert = lines.slice(insertIndex);
        
        const updatedLines = [...beforeInsert, ...content.split('\n'), ...afterInsert];
        yamlTextarea.val(updatedLines.join('\n'));
        
        // Trigger validation
        yamlTextarea.trigger('input');
        
        // Focus and scroll to the added content
        yamlTextarea.focus();
        const textArea = yamlTextarea[0];
        textArea.scrollTop = textArea.scrollHeight;
    }

    // Initialize interactive buttons after visual translation is shown
    function initializeInteractiveButtons() {
        // Initialize tooltips
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });

        // Handle entity button clicks
        $(document).on('click', '.interactive-entity', function(e) {
            e.preventDefault();
            const entityName = $(this).data('entity-name');
            const entityType = $(this).data('entity-type');
            const role = $(this).data('role');
            
            console.log('Entity clicked:', { entityName, entityType, role });
            
            // Show edit dialog for entity
            showEntityEditDialog(entityName, entityType, role);
        });

        // Handle predicate button clicks
        $(document).on('click', '.interactive-predicate', function(e) {
            e.preventDefault();
            const connectionType = $(this).data('connection-type');
            const isIncoming = $(this).data('is-incoming') === 'true';
            
            console.log('Predicate clicked:', { connectionType, isIncoming });
            
            // Show relationship type selector
            showRelationshipTypeSelector(connectionType, isIncoming);
        });

        // Handle date button clicks
        $(document).on('click', '.interactive-date', function(e) {
            e.preventDefault();
            const date = $(this).data('date');
            const dateType = $(this).data('date-type');
            
            console.log('Date clicked:', { date, dateType });
            
            // Show date picker
            showDatePicker(date, dateType);
        });

        // Handle connector button clicks
        $(document).on('click', '.interactive-connector', function(e) {
            e.preventDefault();
            const connectorText = $(this).data('connector-text');
            
            console.log('Connector clicked:', { connectorText });
            
            // Show connector edit dialog
            showConnectorEditDialog(connectorText);
        });
    }

    // Show entity edit dialog
    function showEntityEditDialog(entityName, entityType, role) {
        // Close any existing dropdown
        if (activeDropdown) {
            activeDropdown.remove();
            activeDropdown = null;
        }
        
        // Create dropdown menu with searchable filter
        const dropdownId = 'entityDropdown_' + Date.now();
        const dropdown = $(`
            <div class="dropdown-menu pt-0 mx-0 rounded-3 shadow overflow-hidden w-280px" 
                 id="${dropdownId}" data-bs-theme="light" style="z-index: 9999; display: block;">
                <form class="p-2 mb-2 bg-body-tertiary border-bottom">
                    <input type="search" class="form-control" autocomplete="false" placeholder="Type to filter...">
                </form>
                <ul class="list-unstyled mb-0">
                    <li><a class="dropdown-item d-flex align-items-center gap-2 py-2" href="#" data-value="${entityName}">
                        <span class="d-inline-block bg-primary rounded-circle p-1"></span>
                        ${entityName} (current)
                    </a></li>
                    <li><a class="dropdown-item d-flex align-items-center gap-2 py-2" href="#" data-value="New Name 1">
                        <span class="d-inline-block bg-success rounded-circle p-1"></span>
                        New Name 1
                    </a></li>
                    <li><a class="dropdown-item d-flex align-items-center gap-2 py-2" href="#" data-value="New Name 2">
                        <span class="d-inline-block bg-info rounded-circle p-1"></span>
                        New Name 2
                    </a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item d-flex align-items-center gap-2 py-2" href="#" data-value="custom">
                        <span class="d-inline-block bg-warning rounded-circle p-1"></span>
                        Enter custom name...
                    </a></li>
                </ul>
            </div>
        `);
        
        // Set as active dropdown
        activeDropdown = dropdown;
        
        // Position the dropdown near the clicked button
        const button = $(`.interactive-entity[data-entity-name="${entityName}"]`);
        const buttonPos = button.offset();
        const buttonWidth = button.outerWidth();
        const dropdownWidth = 280; // Width of the dropdown
        
        dropdown.css({
            position: 'absolute',
            top: buttonPos.top - 10, // Position above the button
            left: buttonPos.left + (buttonWidth / 2) - (dropdownWidth / 2), // Center over the button
            zIndex: 9999
        });
        
        $('body').append(dropdown);
        
        // Handle search filter
        dropdown.find('input[type="search"]').on('input', function() {
            const searchTerm = $(this).val().toLowerCase();
            dropdown.find('.dropdown-item').each(function() {
                const text = $(this).text().toLowerCase();
                $(this).toggle(text.includes(searchTerm));
            });
        });
        
        // Handle dropdown item clicks
        dropdown.find('.dropdown-item').on('click', function(e) {
            e.preventDefault();
            const value = $(this).data('value');
            
            if (value === 'custom') {
                const customName = prompt(`Enter custom ${role} name:`, entityName);
                if (customName && customName.trim() !== entityName) {
                    updateEntityInYaml(entityName, customName.trim(), entityType, role);
                }
            } else if (value !== entityName) {
                updateEntityInYaml(entityName, value, entityType, role);
            }
            
            dropdown.remove();
            activeDropdown = null;
        });
        
        // Close dropdown when clicking outside
        $(document).on('click.dropdown', function(e) {
            if (!dropdown.is(e.target) && dropdown.has(e.target).length === 0) {
                dropdown.remove();
                activeDropdown = null;
                $(document).off('click.dropdown');
            }
        });
        
        // Focus on search input
        setTimeout(() => dropdown.find('input[type="search"]').focus(), 100);
    }

    // Show relationship type selector
    function showRelationshipTypeSelector(currentType, isIncoming) {
        // Close any existing dropdown
        if (activeDropdown) {
            activeDropdown.remove();
            activeDropdown = null;
        }
        
        const relationshipTypes = [
            { value: 'education', label: 'Education', color: 'success' },
            { value: 'employment', label: 'Employment', color: 'primary' },
            { value: 'residence', label: 'Residence', color: 'info' },
            { value: 'membership', label: 'Membership', color: 'warning' },
            { value: 'created', label: 'Created', color: 'danger' },
            { value: 'friend', label: 'Friend', color: 'secondary' },
            { value: 'relationship', label: 'Relationship', color: 'dark' },
            { value: 'contains', label: 'Contains', color: 'success' },
            { value: 'travel', label: 'Travel', color: 'info' },
            { value: 'participation', label: 'Participation', color: 'warning' },
            { value: 'ownership', label: 'Ownership', color: 'danger' },
            { value: 'has_role', label: 'Has Role', color: 'primary' },
            { value: 'at_organisation', label: 'At Organisation', color: 'secondary' }
        ];
        
        const dropdownId = 'relationshipDropdown_' + Date.now();
        const dropdown = $(`
            <div class="dropdown-menu pt-0 mx-0 rounded-3 shadow overflow-hidden w-280px" 
                 id="${dropdownId}" data-bs-theme="light" style="z-index: 9999; display: block;">
                <form class="p-2 mb-2 bg-body-tertiary border-bottom">
                    <input type="search" class="form-control" autocomplete="false" placeholder="Type to filter...">
                </form>
                <ul class="list-unstyled mb-0">
                    ${relationshipTypes.map(type => `
                        <li><a class="dropdown-item d-flex align-items-center gap-2 py-2" href="#" data-value="${type.value}">
                            <span class="d-inline-block bg-${type.color} rounded-circle p-1"></span>
                            ${type.label}${type.value === currentType ? ' (current)' : ''}
                        </a></li>
                    `).join('')}
                </ul>
            </div>
        `);
        
        // Set as active dropdown
        activeDropdown = dropdown;
        
        // Position the dropdown near the clicked button
        const button = $(`.interactive-predicate[data-connection-type="${currentType}"]`);
        const buttonPos = button.offset();
        const buttonWidth = button.outerWidth();
        const dropdownWidth = 280; // Width of the dropdown
        
        dropdown.css({
            position: 'absolute',
            top: buttonPos.top - 10, // Position above the button
            left: buttonPos.left + (buttonWidth / 2) - (dropdownWidth / 2), // Center over the button
            zIndex: 9999
        });
        
        $('body').append(dropdown);
        
        // Handle search filter
        dropdown.find('input[type="search"]').on('input', function() {
            const searchTerm = $(this).val().toLowerCase();
            dropdown.find('.dropdown-item').each(function() {
                const text = $(this).text().toLowerCase();
                $(this).toggle(text.includes(searchTerm));
            });
        });
        
        // Handle dropdown item clicks
        dropdown.find('.dropdown-item').on('click', function(e) {
            e.preventDefault();
            const newType = $(this).data('value');
            if (newType !== currentType) {
                updateRelationshipType(currentType, isIncoming, newType);
            }
            dropdown.remove();
            activeDropdown = null;
        });
        
        // Close dropdown when clicking outside
        $(document).on('click.dropdown', function(e) {
            if (!dropdown.is(e.target) && dropdown.has(e.target).length === 0) {
                dropdown.remove();
                activeDropdown = null;
                $(document).off('click.dropdown');
            }
        });
        
        // Focus on search input
        setTimeout(() => dropdown.find('input[type="search"]').focus(), 100);
    }

    // Show date picker
    function showDatePicker(currentDate, dateType) {
        // Close any existing dropdown
        if (activeDropdown) {
            activeDropdown.remove();
            activeDropdown = null;
        }
        
        const dropdownId = 'dateDropdown_' + Date.now();
        const dropdown = $(`
            <div class="dropdown-menu pt-0 mx-0 rounded-3 shadow overflow-hidden w-280px" 
                 id="${dropdownId}" data-bs-theme="light" style="z-index: 9999; display: block;">
                <form class="p-2 mb-2 bg-body-tertiary border-bottom">
                    <input type="search" class="form-control" autocomplete="false" placeholder="Type to filter...">
                </form>
                <ul class="list-unstyled mb-0">
                    <li><a class="dropdown-item d-flex align-items-center gap-2 py-2" href="#" data-value="${currentDate}">
                        <span class="d-inline-block bg-primary rounded-circle p-1"></span>
                        ${currentDate} (current)
                    </a></li>
                    <li><a class="dropdown-item d-flex align-items-center gap-2 py-2" href="#" data-value="1990">
                        <span class="d-inline-block bg-success rounded-circle p-1"></span>
                        1990
                    </a></li>
                    <li><a class="dropdown-item d-flex align-items-center gap-2 py-2" href="#" data-value="1995">
                        <span class="d-inline-block bg-info rounded-circle p-1"></span>
                        1995
                    </a></li>
                    <li><a class="dropdown-item d-flex align-items-center gap-2 py-2" href="#" data-value="2000">
                        <span class="d-inline-block bg-warning rounded-circle p-1"></span>
                        2000
                    </a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item d-flex align-items-center gap-2 py-2" href="#" data-value="custom">
                        <span class="d-inline-block bg-secondary rounded-circle p-1"></span>
                        Enter custom date...
                    </a></li>
                </ul>
            </div>
        `);
        
        // Set as active dropdown
        activeDropdown = dropdown;
        
        // Position the dropdown near the clicked button
        const button = $(`.interactive-date[data-date="${currentDate}"]`);
        const buttonPos = button.offset();
        const buttonWidth = button.outerWidth();
        const dropdownWidth = 280; // Width of the dropdown
        
        dropdown.css({
            position: 'absolute',
            top: buttonPos.top - 10, // Position above the button
            left: buttonPos.left + (buttonWidth / 2) - (dropdownWidth / 2), // Center over the button
            zIndex: 9999
        });
        
        $('body').append(dropdown);
        
        // Handle search filter
        dropdown.find('input[type="search"]').on('input', function() {
            const searchTerm = $(this).val().toLowerCase();
            dropdown.find('.dropdown-item').each(function() {
                const text = $(this).text().toLowerCase();
                $(this).toggle(text.includes(searchTerm));
            });
        });
        
        // Handle dropdown item clicks
        dropdown.find('.dropdown-item').on('click', function(e) {
            e.preventDefault();
            const value = $(this).data('value');
            
            if (value === 'custom') {
                const customDate = prompt(`Edit ${dateType} date (YYYY-MM-DD):`, currentDate);
                if (customDate && customDate.trim() !== currentDate) {
                    updateDateInYaml(currentDate, customDate.trim(), dateType);
                }
            } else if (value !== currentDate) {
                updateDateInYaml(currentDate, value, dateType);
            }
            
            dropdown.remove();
            activeDropdown = null;
        });
        
        // Close dropdown when clicking outside
        $(document).on('click.dropdown', function(e) {
            if (!dropdown.is(e.target) && dropdown.has(e.target).length === 0) {
                dropdown.remove();
                activeDropdown = null;
                $(document).off('click.dropdown');
            }
        });
        
        // Focus on search input
        setTimeout(() => dropdown.find('input[type="search"]').focus(), 100);
    }

    // Show connector edit dialog
    function showConnectorEditDialog(currentConnector) {
        // Close any existing dropdown
        if (activeDropdown) {
            activeDropdown.remove();
            activeDropdown = null;
        }
        
        const connectorOptions = [
            { value: 'is the child of', label: 'Is the child of', color: 'primary' },
            { value: 'is the parent of', label: 'Is the parent of', color: 'success' },
            { value: 'was educated at', label: 'Was educated at', color: 'info' },
            { value: 'was employed by', label: 'Was employed by', color: 'warning' },
            { value: 'lived in', label: 'Lived in', color: 'danger' },
            { value: 'was a member of', label: 'Was a member of', color: 'secondary' },
            { value: 'created', label: 'Created', color: 'dark' },
            { value: 'was friends with', label: 'Was friends with', color: 'primary' },
            { value: 'had a relationship with', label: 'Had a relationship with', color: 'success' },
            { value: 'contains', label: 'Contains', color: 'info' },
            { value: 'traveled to', label: 'Traveled to', color: 'warning' },
            { value: 'participated in', label: 'Participated in', color: 'danger' },
            { value: 'owned', label: 'Owned', color: 'secondary' },
            { value: 'had role', label: 'Had role', color: 'dark' },
            { value: 'was at', label: 'Was at', color: 'primary' }
        ];
        
        const dropdownId = 'connectorDropdown_' + Date.now();
        const dropdown = $(`
            <div class="dropdown-menu pt-0 mx-0 rounded-3 shadow overflow-hidden w-280px" 
                 id="${dropdownId}" data-bs-theme="light" style="z-index: 9999; display: block;">
                <form class="p-2 mb-2 bg-body-tertiary border-bottom">
                    <input type="search" class="form-control" autocomplete="false" placeholder="Type to filter...">
                </form>
                <ul class="list-unstyled mb-0">
                    ${connectorOptions.map(option => `
                        <li><a class="dropdown-item d-flex align-items-center gap-2 py-2" href="#" data-value="${option.value}">
                            <span class="d-inline-block bg-${option.color} rounded-circle p-1"></span>
                            ${option.label}${option.value === currentConnector ? ' (current)' : ''}
                        </a></li>
                    `).join('')}
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item d-flex align-items-center gap-2 py-2" href="#" data-value="custom">
                        <span class="d-inline-block bg-secondary rounded-circle p-1"></span>
                        Enter custom connector...
                    </a></li>
                </ul>
            </div>
        `);
        
        // Set as active dropdown
        activeDropdown = dropdown;
        
        // Position the dropdown near the clicked button
        const button = $(`.interactive-connector[data-connector-text="${currentConnector}"]`);
        const buttonPos = button.offset();
        const buttonWidth = button.outerWidth();
        const dropdownWidth = 280; // Width of the dropdown
        
        dropdown.css({
            position: 'absolute',
            top: buttonPos.top - 10, // Position above the button
            left: buttonPos.left + (buttonWidth / 2) - (dropdownWidth / 2), // Center over the button
            zIndex: 9999
        });
        
        $('body').append(dropdown);
        
        // Handle search filter
        dropdown.find('input[type="search"]').on('input', function() {
            const searchTerm = $(this).val().toLowerCase();
            dropdown.find('.dropdown-item').each(function() {
                const text = $(this).text().toLowerCase();
                $(this).toggle(text.includes(searchTerm));
            });
        });
        
        // Handle dropdown item clicks
        dropdown.find('.dropdown-item').on('click', function(e) {
            e.preventDefault();
            const value = $(this).data('value');
            
            if (value === 'custom') {
                const customConnector = prompt(`Edit connector:`, currentConnector);
                if (customConnector && customConnector.trim() !== currentConnector) {
                    updateConnectorInYaml(currentConnector, customConnector.trim());
                }
            } else if (value !== currentConnector) {
                updateConnectorInYaml(currentConnector, value);
            }
            
            dropdown.remove();
            activeDropdown = null;
        });
        
        // Close dropdown when clicking outside
        $(document).on('click.dropdown', function(e) {
            if (!dropdown.is(e.target) && dropdown.has(e.target).length === 0) {
                dropdown.remove();
                activeDropdown = null;
                $(document).off('click.dropdown');
            }
        });
        
        // Focus on search input
        setTimeout(() => dropdown.find('input[type="search"]').focus(), 100);
    }

    // Update relationship type in YAML
    window.updateRelationshipType = function(oldType, isIncoming, newType) {
        const currentYaml = yamlTextarea.val();
        const oldTypeWithIncoming = isIncoming === 'true' ? `${oldType}_incoming` : oldType;
        const newTypeWithIncoming = isIncoming === 'true' ? `${newType}_incoming` : newType;
        
        const updatedYaml = currentYaml.replace(new RegExp(`\\b${oldTypeWithIncoming}\\b`, 'g'), newTypeWithIncoming);
        yamlTextarea.val(updatedYaml);
        yamlTextarea.trigger('input');
        
        // Show feedback
        showUpdateFeedback(`Updated relationship type from "${oldType}" to "${newType}"`);
    };

    // Update entity in YAML
    function updateEntityInYaml(oldName, newName, entityType, role) {
        const currentYaml = yamlTextarea.val();
        const updatedYaml = currentYaml.replace(new RegExp(`\\b${oldName}\\b`, 'g'), newName);
        yamlTextarea.val(updatedYaml);
        yamlTextarea.trigger('input');
        
        // Show feedback
        showUpdateFeedback(`Updated ${role} name from "${oldName}" to "${newName}"`);
    }

    // Show update feedback
    function showUpdateFeedback(message) {
        const feedback = $(`
            <div class="alert alert-success alert-dismissible fade show position-fixed" 
                 style="top: 20px; right: 20px; z-index: 9999; min-width: 300px;">
                <i class="bi bi-check-circle me-2"></i>${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `);
        
        $('body').append(feedback);
        
        // Auto-dismiss after 3 seconds
        setTimeout(() => {
            feedback.alert('close');
        }, 3000);
    }

    // Update date in YAML
    function updateDateInYaml(oldDate, newDate, dateType) {
        const currentYaml = yamlTextarea.val();
        const updatedYaml = currentYaml.replace(new RegExp(`\\b${oldDate}\\b`, 'g'), newDate);
        yamlTextarea.val(updatedYaml);
        yamlTextarea.trigger('input');
        
        // Show feedback
        showUpdateFeedback(`Updated ${dateType} date from "${oldDate}" to "${newDate}"`);
    }

    // Update connector in YAML
    function updateConnectorInYaml(oldConnector, newConnector) {
        const currentYaml = yamlTextarea.val();
        const updatedYaml = currentYaml.replace(new RegExp(`\\b${oldConnector}\\b`, 'g'), newConnector);
        yamlTextarea.val(updatedYaml);
        yamlTextarea.trigger('input');
        
        // Show feedback
        showUpdateFeedback(`Updated connector from "${oldConnector}" to "${newConnector}"`);
    }

    // Show changes summary
    function showChangesSummary(newData) {
        // For new spans, always show the summary since everything is a "change"
        const isNewSpan = {{ $span ? 'false' : 'true' }};
        
        console.log('showChangesSummary called:', { isNewSpan, newData });
        
        if (!newData || Object.keys(newData).length === 0) {
            console.log('No newData, hiding changes summary');
            $('#changes-summary').hide();
            return;
        }
        
        const changes = [];
        
        if (isNewSpan) {
            console.log('Processing new span changes');
            // For new spans, treat all fields as new additions
            const basicFields = ['name', 'type', 'state', 'description', 'notes', 'access_level'];
            basicFields.forEach(field => {
                if (newData[field] !== undefined && newData[field] !== '') {
                    changes.push({
                        field: field,
                        type: 'add',
                        oldValue: 'Not set',
                        newValue: newData[field],
                        category: 'basic'
                    });
                }
            });
            
            // Add dates if present
            if (newData.start_year !== undefined || newData.start_month !== undefined || newData.start_day !== undefined) {
                const startDate = formatDateForDisplay(newData.start_year, newData.start_month, newData.start_day);
                if (startDate !== 'Not set') {
                    changes.push({
                        field: 'start_date',
                        type: 'add',
                        oldValue: 'Not set',
                        newValue: startDate,
                        category: 'dates'
                    });
                }
            }
            
            if (newData.end_year !== undefined || newData.end_month !== undefined || newData.end_day !== undefined) {
                const endDate = formatDateForDisplay(newData.end_year, newData.end_month, newData.end_day);
                if (endDate !== 'Not set') {
                    changes.push({
                        field: 'end_date',
                        type: 'add',
                        oldValue: 'Not set',
                        newValue: endDate,
                        category: 'dates'
                    });
                }
            }
            
            // Add metadata if present
            if (newData.metadata && Object.keys(newData.metadata).length > 0) {
                Object.entries(newData.metadata).forEach(([key, value]) => {
                    changes.push({
                        field: `metadata.${key}`,
                        type: 'add',
                        oldValue: 'Not set',
                        newValue: value,
                        category: 'metadata'
                    });
                });
            }
            
            // Add connections if present
            if (newData.connections && Object.keys(newData.connections).length > 0) {
                Object.entries(newData.connections).forEach(([connectionType, connections]) => {
                    connections.forEach((connection, index) => {
                        changes.push({
                            field: `connections.${connectionType}[${index}]`,
                            type: 'add',
                            oldValue: 'Not present',
                            newValue: `${connection.name} (${connection.type})`,
                            category: 'connections'
                        });
                    });
                });
            }
        } else {
            console.log('Processing existing span changes');
            // For existing spans, use the original comparison logic
            // Only show changes if the YAML content has actually been modified
            const hasChanges = yamlTextarea.val() !== originalContent;
            console.log('Content comparison:', { 
                currentContent: yamlTextarea.val().substring(0, 100) + '...', 
                originalContent: originalContent.substring(0, 100) + '...',
                hasChanges 
            });
            
            if (!hasChanges) {
                console.log('No content changes detected, hiding changes summary');
                $('#changes-summary').hide();
                return;
            }
            
            // Compare basic fields
            const basicFields = ['name', 'type', 'state', 'description', 'notes', 'access_level'];
            basicFields.forEach(field => {
                if (newData[field] !== undefined) {
                    const currentValue = getCurrentSpanValue(field);
                    console.log(`Comparing ${field}:`, { currentValue, newValue: newData[field], equal: currentValue === newData[field] });
                    if (newData[field] !== currentValue) {
                        changes.push({
                            field: field,
                            type: 'update',
                            oldValue: currentValue,
                            newValue: newData[field],
                            category: 'basic'
                        });
                    }
                }
            });
            
            // Compare dates
            if (newData.start_year !== undefined || newData.start_month !== undefined || newData.start_day !== undefined) {
                const currentStart = getCurrentSpanDate('start');
                const newStart = formatDateForDisplay(newData.start_year, newData.start_month, newData.start_day);
                console.log('Comparing start dates:', { currentStart, newStart, equal: currentStart === newStart });
                if (currentStart !== newStart) {
                    changes.push({
                        field: 'start_date',
                        type: 'update',
                        oldValue: currentStart,
                        newValue: newStart,
                        category: 'dates'
                    });
                }
            }
            
            if (newData.end_year !== undefined || newData.end_month !== undefined || newData.end_day !== undefined) {
                const currentEnd = getCurrentSpanDate('end');
                const newEnd = formatDateForDisplay(newData.end_year, newData.end_month, newData.end_day);
                console.log('Comparing end dates:', { currentEnd, newEnd, equal: currentEnd === newEnd });
                if (currentEnd !== newEnd) {
                    changes.push({
                        field: 'end_date',
                        type: 'update',
                        oldValue: currentEnd,
                        newValue: newEnd,
                        category: 'dates'
                    });
                }
            }
            
            // Compare metadata
            if (newData.metadata && Object.keys(newData.metadata).length > 0) {
                const currentMetadata = getCurrentSpanValue('metadata') || {};
                const metadataChanges = compareMetadata(currentMetadata, newData.metadata);
                changes.push(...metadataChanges);
            }
            
            // Compare connections using YAML comparison
            if (newData.connections && Object.keys(newData.connections).length > 0) {
                const connectionChanges = compareConnections(newData.connections);
                changes.push(...connectionChanges);
            }
        }
        
        console.log('Total changes found:', changes.length, changes);
        
        // Display changes
        if (changes.length === 0) {
            console.log('No changes to display, hiding changes summary');
            $('#changes-summary').hide();
            return;
        }
        
        let html = '';
        const categories = {
            'basic': { title: 'Basic Information', icon: 'bi-info-circle' },
            'dates': { title: 'Dates', icon: 'bi-calendar-event' },
            'metadata': { title: 'Metadata', icon: 'bi-tags' },
            'connections': { title: 'Connections', icon: 'bi-diagram-3' }
        };
        
        // Group changes by category
        const groupedChanges = {};
        changes.forEach(change => {
            if (!groupedChanges[change.category]) {
                groupedChanges[change.category] = [];
            }
            groupedChanges[change.category].push(change);
        });
        
        Object.keys(groupedChanges).forEach(category => {
            const categoryInfo = categories[category] || { title: category, icon: 'bi-gear' };
            html += `
                <div class="mb-3">
                    <h6 class="text-muted mb-2">
                        <i class="${categoryInfo.icon} me-1"></i>${categoryInfo.title}
                    </h6>
            `;
            
            groupedChanges[category].forEach(change => {
                const changeIcon = change.type === 'add' ? 'bi-plus-circle text-success' : 
                                 change.type === 'remove' ? 'bi-dash-circle text-danger' : 
                                 'bi-arrow-right text-primary';
                
                html += `
                    <div class="small mb-1">
                        <i class="${changeIcon} me-1"></i>
                        <strong>${formatFieldName(change.field)}:</strong>
                        ${change.type === 'add' ? 
                            `<span class="text-success">${formatValue(change.newValue)}</span>` :
                            change.type === 'remove' ? 
                            `<span class="text-danger">${formatValue(change.oldValue)}</span> <i class="bi bi-arrow-right mx-1 text-muted"></i> <span class="text-muted">Removed</span>` :
                            `<span class="text-danger">${formatValue(change.oldValue)}</span> <i class="bi bi-arrow-right mx-1 text-muted"></i> <span class="text-success">${formatValue(change.newValue)}</span>`
                        }
                    </div>
                `;
            });
            
            html += '</div>';
        });
        
        $('#changes-list').html(html);
        $('#changes-summary').show();
        console.log('Changes summary displayed');
    }
    
    // Helper functions for changes summary
    function getCurrentSpanValue(field) {
        const isNewSpan = {{ $span ? 'false' : 'true' }};
        
        if (isNewSpan) {
            // Use the dynamic baseline that can be updated when creating new templates
            return currentBaseline[field] || '';
        } else {
            // For existing spans, get the value from the span data
            const span = @json($span);
            return span[field] || '';
        }
    }
    
    function getCurrentSpanDate(type) {
        const span = @json($span);
        if (type === 'start') {
            if (span.start_year) {
                let date = span.start_year.toString();
                if (span.start_month) {
                    date += '-' + span.start_month.toString().padStart(2, '0');
                    if (span.start_day) {
                        date += '-' + span.start_day.toString().padStart(2, '0');
                    }
                }
                return date;
            }
        } else if (type === 'end') {
            if (span.end_year) {
                let date = span.end_year.toString();
                if (span.end_month) {
                    date += '-' + span.end_month.toString().padStart(2, '0');
                    if (span.end_day) {
                        date += '-' + span.end_day.toString().padStart(2, '0');
                    }
                }
                return date;
            }
        }
        return 'Not set';
    }
    
    function formatDateForDisplay(year, month, day) {
        if (!year) return 'Not set';
        let date = year.toString();
        if (month) {
            date += '-' + month.toString().padStart(2, '0');
            if (day) {
                date += '-' + day.toString().padStart(2, '0');
            }
        }
        return date;
    }
    
    function formatFieldName(field) {
        const fieldNames = {
            'name': 'Name',
            'type': 'Type',
            'state': 'State',
            'description': 'Description',
            'notes': 'Notes',
            'access_level': 'Access Level',
            'start_date': 'Start Date',
            'end_date': 'End Date'
        };
        
        // Handle connection field names (e.g., "connections.Residence[0].start_date")
        if (field.includes('connections.')) {
            const parts = field.split('.');
            if (parts.length >= 3) {
                const connectionType = parts[1]; // e.g., "Residence"
                const fieldName = parts[2]; // e.g., "start_date"
                const fieldDisplayName = fieldNames[fieldName] || fieldName.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
                return `${connectionType} ${fieldDisplayName}`;
            }
        }
        
        return fieldNames[field] || field.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
    }
    
    function formatValue(value) {
        if (value === null || value === undefined) return 'Not set';
        if (typeof value === 'object') return JSON.stringify(value);
        return value.toString();
    }
    
    function compareMetadata(oldMetadata, newMetadata) {
        const changes = [];
        const allKeys = new Set([...Object.keys(oldMetadata), ...Object.keys(newMetadata)]);
        
        allKeys.forEach(key => {
            const oldValue = oldMetadata[key];
            const newValue = newMetadata[key];
            if (oldValue !== newValue) {
                changes.push({
                    field: `metadata.${key}`,
                    type: 'update',
                    oldValue: oldValue,
                    newValue: newValue,
                    category: 'metadata'
                });
            }
        });
        
        return changes;
    }
    
    function compareConnections(yamlConnections) {
        // Instead of trying to reconstruct database connections, let's compare YAML content
        // The current YAML content should represent the current state
        const currentYamlContent = originalContent;
        const newYamlContent = yamlTextarea.val();
        
        // If the content hasn't changed, no connection changes
        if (currentYamlContent === newYamlContent) {
            return [];
        }
        
        // Parse both YAML contents to compare
        let currentData, newData;
        
        try {
            currentData = jsyaml.load(currentYamlContent);
            newData = jsyaml.load(newYamlContent);
            
            // Debug logging
            console.log('Current YAML data:', currentData);
            console.log('New YAML data:', newData);
        } catch (e) {
            console.error('Failed to parse YAML for comparison:', e);
            return [];
        }
        
        const changes = [];
        
        // Compare connections if they exist in both
        const currentConnections = currentData.connections || {};
        const newConnections = newData.connections || {};
        
        // Get all connection types from both
        const allConnectionTypes = new Set([
            ...Object.keys(currentConnections),
            ...Object.keys(newConnections)
        ]);
        
        allConnectionTypes.forEach(connectionType => {
            const currentList = currentConnections[connectionType] || [];
            const newList = newConnections[connectionType] || [];
            
            // Debug logging for residence connections
            if (connectionType === 'residence') {
                console.log('Residence connections comparison:', {
                    current: currentList,
                    new: newList
                });
            }
            
            // Compare each connection in the new list
            newList.forEach((newConnection, index) => {
                // Try to find matching connection in current list - use connection_id as primary key
                const matchingCurrent = currentList.find(current => 
                    current.connection_id === newConnection.connection_id
                );
                
                if (!matchingCurrent) {
                    // This is a new connection
                    changes.push({
                        field: `connections.${connectionType}[${index}]`,
                        type: 'add',
                        oldValue: 'Not present',
                        newValue: `${newConnection.name} (${newConnection.type})`,
                        category: 'connections'
                    });
                } else {
                    // Check if there are changes to the existing connection
                    const connectionChanges = getConnectionFieldChanges(matchingCurrent, newConnection);
                    if (connectionChanges.length > 0) {
                        // Add each changed field as a separate change
                        connectionChanges.forEach(fieldChange => {
                            changes.push({
                                field: `connections.${connectionType}[${index}].${fieldChange.field}`,
                                type: 'update',
                                oldValue: fieldChange.oldValue,
                                newValue: fieldChange.newValue,
                                category: 'connections'
                            });
                        });
                    }
                }
            });
            
            // Check for removed connections
            currentList.forEach((currentConnection, index) => {
                const stillExists = newList.find(newConn => 
                    newConn.connection_id === currentConnection.connection_id
                );
                
                if (!stillExists) {
                    changes.push({
                        field: `connections.${connectionType}[${index}]`,
                        type: 'remove',
                        oldValue: `${currentConnection.name} (${currentConnection.type})`,
                        newValue: 'Removed',
                        category: 'connections'
                    });
                }
            });
        });
        
        return changes;
    }
    
    function getConnectionFieldChanges(existing, newConnection) {
        const changes = [];
        const fieldsToCompare = ['name', 'type', 'start_date', 'end_date'];
        
        for (const field of fieldsToCompare) {
            const existingValue = existing[field];
            const newValue = newConnection[field];
            
            // Debug logging
            console.log(`Comparing ${field}:`, { existing: existingValue, new: newValue, equal: existingValue === newValue });
            
            if (existingValue !== newValue) {
                changes.push({
                    field: field,
                    type: 'update',
                    oldValue: existingValue,
                    newValue: newValue
                });
            }
        }
        
        return changes;
    }
    
    // Create New Span functionality
    $(document).ready(function() {
        const newSpanNameInput = $('#new-span-name-input');
        const newSpanTypeSelector = $('#new-span-type-selector');
        const createNewSpanBtn = $('#create-new-span-btn');
        const yamlTextarea = $('#yaml-content');
        
        // Dynamic baseline for comparison - starts with current span data
        let currentBaseline = @if($span) {
            name: '{{ $span->name }}',
            slug: '{{ $span->slug ?? "" }}',
            type: '{{ $span->type_id }}',
            state: '{{ $span->state }}',
            description: '{{ $span->description ?? "" }}',
            notes: '{{ $span->notes ?? "" }}',
            access_level: '{{ $span->access_level }}',
            metadata: @json($span->metadata ?? [])
        } @else {
            name: '',
            slug: '',
            type: '',
            state: 'placeholder',
            description: '',
            notes: '',
            access_level: 'private',
            metadata: {}
        } @endif;
        
        // Enable/disable create button based on both name and type selection
        function updateCreateButton() {
            const hasName = newSpanNameInput.val().trim() !== '';
            const hasType = newSpanTypeSelector.val() !== '';
            createNewSpanBtn.prop('disabled', !hasName || !hasType);
        }
        
        newSpanNameInput.on('input', updateCreateButton);
        newSpanTypeSelector.on('change', updateCreateButton);
        
        // Handle create new span button click
        createNewSpanBtn.on('click', function() {
            const selectedType = newSpanTypeSelector.val();
            const spanName = newSpanNameInput.val().trim();
            
            if (!selectedType) {
                alert('Please select a span type first');
                return;
            }
            
            if (!spanName) {
                alert('Please enter a span name first');
                return;
            }
            
            // Generate basic YAML template with the provided name
            const yamlTemplate = generateNewSpanTemplate(spanName, selectedType);
            
            // Clear the editor and insert the template
            yamlTextarea.val(yamlTemplate);
            
            // Reset the baseline to the new template so future changes are compared against it
            originalContent = yamlTemplate;
            
            // Update the current baseline to match the new template
            currentBaseline = {
                name: spanName,
                slug: '',
                type: selectedType,
                state: 'placeholder',
                description: '',
                notes: '',
                access_level: 'private',
                metadata: {}
            };
            
            // Trigger validation to show the template
            validateYaml();
            
            // Reset the inputs
            newSpanNameInput.val('');
            newSpanTypeSelector.val('');
            createNewSpanBtn.prop('disabled', true);
            
            // Show success message
            showFeedback('New span template created! Fill in the details and apply changes to create the span.', 'success');
        });
        
        // Intercept Apply Changes button to handle new span creation
        $(document).on('click', '#apply-btn', function(e) {
            const yaml = yamlTextarea.val();
            let data;
            try {
                data = jsyaml.load(yaml);
            } catch (err) {
                showFeedback('Invalid YAML: ' + err.message, 'danger');
                return;
            }
            if (!data.id) {
                // No id: create new span
                $.ajax({
                    url: '/spans/yaml-create',
                    method: 'POST',
                    data: {
                        yaml_content: yaml,
                        _token: $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response) {
                        if (response.success && response.redirect) {
                            window.location.href = response.redirect;
                        } else {
                            showFeedback(response.message || 'Failed to create span', 'danger');
                        }
                    },
                    error: function(xhr) {
                        let msg = 'Failed to create span';
                        if (xhr.responseJSON && xhr.responseJSON.errors) {
                            msg = Object.values(xhr.responseJSON.errors).join('<br>');
                        } else if (xhr.responseJSON && xhr.responseJSON.message) {
                            msg = xhr.responseJSON.message;
                        }
                        showFeedback(msg, 'danger');
                    }
                });
                e.preventDefault();
            }
        });
        
        function generateNewSpanTemplate(spanName, spanType) {
            const template = `name: '${spanName}'
slug: ''
type: ${spanType}
state: placeholder
description: ''
notes: ''
metadata: {}
sources: []
access_level: private
`;
            return template;
        }
        
        function showFeedback(message, type = 'info') {
            // Create a temporary feedback element
            const feedback = $(`
                <div class="alert alert-${type === 'success' ? 'success' : 'info'} alert-dismissible fade show" role="alert">
                    <i class="bi bi-${type === 'success' ? 'check-circle' : 'info-circle'} me-2"></i>
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `);
            
            // Insert at the top of the validation results
            $('#validation-results').prepend(feedback);
            
            // Auto-dismiss after 5 seconds
            setTimeout(() => {
                feedback.alert('close');
            }, 5000);
        }
    });
});
</script>
@endpush 