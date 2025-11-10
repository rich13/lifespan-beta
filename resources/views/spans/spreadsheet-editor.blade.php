@extends('layouts.app')

@section('title', ($span ? 'Edit' : 'Create') . ' Span - Spreadsheet Editor')

@section('page_title')
    <x-breadcrumb :items="[
        [
            'text' => $span ? $span->name : 'New Span',
            'url' => $span ? route('spans.show', $span) : null,
            'icon' => 'view',
            'icon_category' => 'action'
        ],
        [
            'text' => 'Edit',
            'icon' => $span ? $span->type_id : 'plus',
            'icon_category' => 'span'
        ]
    ]" />
@endsection

@section('page_tools')
    <div class="d-flex gap-2">
        <button type="button" id="validate-btn" class="btn btn-sm btn-outline-warning">
            <i class="bi bi-check-circle me-1"></i><span class="d-none d-sm-inline">Validate & Preview</span><span class="d-sm-none">Validate</span>
        </button>
        <button type="button" id="save-btn" class="btn btn-sm btn-success" disabled>
            <i class="bi bi-cloud-upload me-1"></i><span class="d-none d-sm-inline">{{ $span ? 'Save Changes' : 'Create Span' }}</span><span class="d-sm-none">Save</span>
        </button>
    </div>
@endsection




@section('content')
<style>
    /* Connection tabs styling */
    #connectionTabs .nav-item .nav-link {
        font-size: 0.8rem !important;
        padding: 0.4rem 0.6rem !important;
        background-color: #f8f9fa !important;
        border: 1px solid #dee2e6 !important;
        margin-right: 2px !important;
        color: #6c757d !important;
    }
    
    #connectionTabs .nav-item .nav-link:hover {
        background-color: #e9ecef !important;
        color: #495057 !important;
    }
    
    #connectionTabs .nav-item .nav-link.active {
        background-color: #fff !important;
        color: #495057 !important;
        border-bottom-color: #fff !important;
        font-weight: 500 !important;
    }

    #connection-details-card .connection-suggestions {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        z-index: 1055;
        max-height: 240px;
        overflow-y: auto;
        display: none;
    }

    #connection-details-card .connection-suggestions .list-group-item {
        cursor: pointer;
        font-size: 0.9rem;
    }

    #connection-details-card .connection-suggestions .list-group-item:hover,
    #connection-details-card .connection-suggestions .list-group-item.active {
        background-color: #e9ecef;
    }
</style>

<div class="container-fluid">
    <!-- Validation Messages Display -->
    <div id="validation-errors-container" class="row mb-3" style="display: none;">
        <div class="col-12">
            <div class="alert alert-danger" role="alert">
                <h6 class="alert-heading">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    Validation Errors
                </h6>
                <ul id="validation-errors-list" class="mb-0">
                    <!-- Validation errors will be populated here -->
                </ul>
            </div>
        </div>
    </div>
    
    <div id="validation-success-container" class="row mb-3" style="display: none;">
        <div class="col-12">
            <div class="alert alert-success" role="alert">
                <h6 class="alert-heading">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    Success
                </h6>
                <ul id="validation-success-list" class="mb-0">
                    <!-- Success messages will be added here -->
                </ul>
            </div>
        </div>
    </div>
    
    <!-- Preview Results -->
    <div id="preview-container" class="row mb-3" style="display: none;">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="card-title mb-0">
                        <i class="bi bi-eye me-2"></i>Preview Changes
                    </h6>
                    <button type="button" class="btn-close" id="close-preview"></button>
                </div>
                <div class="card-body">
                    <div id="preview-content">
                        <!-- Preview content will be populated here -->
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Left column: System Info (1/4 width) -->
        <div class="col-lg-3">
            <div class="card">
                <div class="card-header">
                    <h6 class="card-title mb-0">
                        <i class="bi bi-clock-history me-2"></i>System Info
                    </h6>
                </div>
                <div class="card-body p-0">
                    <div id="system-spreadsheet" class="spreadsheet-editor-container"></div>
                </div>
            </div>
        </div>
        
        <!-- Middle column: Connections (1/2 width) -->
        <div class="col-lg-6">
            <div id="connection-details-card" class="card mb-3 d-none">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="card-title mb-0">
                        <i class="bi bi-link-45deg me-2"></i>Connection Span Details
                    </h6>
                    <span class="badge bg-light text-muted border" id="connection-details-type-badge" style="display: none;"></span>
                </div>
                <div class="card-body">
            <div class="row g-3 align-items-start">
                        <div class="col-lg-4">
                            <label class="form-label fw-medium">Subject</label>
                            <input type="text" class="form-control form-control-sm bg-light" id="connection-subject-input" readonly>
                            <input type="hidden" id="connection-subject-id">
                            <small class="text-muted d-block mt-2" id="connection-subject-summary"></small>
                            <small class="text-muted d-block" id="connection-subject-type"></small>
                        </div>
                        <div class="col-lg-4">
                            <label for="connection-type-select" class="form-label fw-medium">Predicate</label>
                            <select class="form-select form-select-sm" id="connection-type-select">
                                <option value="">Select connection type</option>
                                @foreach($connectionTypes as $type)
                                    <option value="{{ $type->type }}">
                                        {{ $type->type }} &mdash; {{ $type->forward_predicate }}
                                    </option>
                                @endforeach
                            </select>
                            <small class="text-muted d-block mt-2" id="connection-type-description"></small>
                            <div class="mt-2 d-none" id="connection-type-allowed">
                                <span class="badge bg-primary-subtle text-primary-emphasis border me-1" id="allowed-subject-types-badge"></span>
                                <span class="badge bg-secondary-subtle text-secondary-emphasis border" id="allowed-object-types-badge"></span>
                            </div>
                        </div>
                        <div class="col-lg-4 position-relative">
                            <label for="connection-object-input" class="form-label fw-medium">Object</label>
                            <input type="text" class="form-control form-control-sm" id="connection-object-input" placeholder="Search..." autocomplete="off" disabled>
                            <input type="hidden" id="connection-object-id">
                            <div class="connection-suggestions list-group shadow-sm" id="connection-object-suggestions"></div>
                            <small class="text-muted d-block mt-2" id="connection-object-summary"></small>
                            <small class="text-muted d-block" id="connection-object-type"></small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="card-title mb-0">
                        <i class="bi bi-diagram-3 me-2"></i>Connections
                    </h6>
                    <button type="button" class="btn btn-sm btn-outline-primary" 
                            data-bs-toggle="modal" data-bs-target="#addConnectionModal"
                            data-span-id="{{ $span->id }}" data-span-name="{{ $span->name }}" data-span-type="{{ $span->type_id }}">
                        <i class="bi bi-plus"></i>
                    </button>
                </div>
                <div class="card-body p-0">
                    <!-- Connection Type Tabs -->
                    <ul class="nav nav-tabs" id="connectionTabs" role="tablist">
                        <!-- Tabs will be generated dynamically -->
                    </ul>
                    
                    <!-- Tab Content -->
                    <div class="tab-content" id="connectionTabContent">
                        <!-- Tab panes will be generated dynamically -->
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Right column: Core Fields & Metadata (1/4 width) -->
        <div class="col-lg-3">
            <div class="card">
                <div class="card-header">
                    <h6 class="card-title mb-0">
                        <i class="bi bi-info-circle me-2"></i>Core Fields & Metadata
                    </h6>
                </div>
                <div class="card-body p-0">
                    <!-- Core Fields Section -->
                    <div class="border-bottom">
                        <div class="px-3 py-2 bg-light">
                            <small class="text-muted fw-bold">Core Fields</small>
                        </div>
                        <div id="core-spreadsheet" class="spreadsheet-editor-container"></div>
                    </div>
                    
                    <!-- Metadata Section -->
                    <div>
                        <div class="px-3 py-2 bg-light d-flex justify-content-between align-items-center">
                            <small class="text-muted fw-bold">Metadata</small>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="addMetadataRow()">
                                <i class="bi bi-plus"></i>
                            </button>
                        </div>
                        <div id="metadata-spreadsheet" class="spreadsheet-editor-container"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    

</div>
@endsection

@push('styles')
<link href="https://cdn.jsdelivr.net/npm/handsontable/dist/handsontable.full.min.css" rel="stylesheet">
<style>
    .spreadsheet-editor-container {
        min-height: 200px;
        margin: 8px;
    }
    
    /* Connection type tabs styling */
    .connection-tab-pane {
        min-height: 400px;
    }
    
    .connection-tab-pane .spreadsheet-editor-container {
        min-height: 350px;
    }
    
    /* Cards with natural height */
    .col-lg-3 .card,
    .col-lg-6 .card {
        min-height: 400px;
    }
    
    /* Spreadsheets with natural height */
    #system-spreadsheet,
    #core-spreadsheet,
    #metadata-spreadsheet {
        min-height: 150px;
    }
    
    /* Handsontable custom styling */
    .handsontable {
        font-size: 13px;
        width: 100% !important;
    }
    
    /* Ensure Handsontable fills its container */
    .spreadsheet-editor-container .handsontable {
        width: 100% !important;
        max-width: 100% !important;
    }
    
    /* Force Handsontable to use full width */
    .handsontable .wtHolder {
        width: 100% !important;
    }
    
    .handsontable .wtHider {
        width: 100% !important;
    }
    
    .handsontable .wtSpreader {
        width: 100% !important;
    }
    
    .handsontable .htCore th {
        background-color: #f8f9fa;
        font-weight: 600;
        color: #495057;
    }
    
    .handsontable .htCore td {
        padding: 4px 8px;
    }
    
    .handsontable .htCore .htDimmed {
        background-color: #f8f9fa;
        color: #6c757d;
    }
    
    /* Validation styling */
    .validation-error {
        background-color: #f8d7da !important;
        color: #721c24 !important;
    }
    
    .validation-warning {
        background-color: #fff3cd !important;
        color: #856404 !important;
    }
    
    .validation-success {
        background-color: #d1edff !important;
        color: #0c5460 !important;
    }
    
    /* Button spinner styling */
    .btn .spinner-border-sm {
        width: 0.875rem;
        height: 0.875rem;
        border-width: 0.125em;
    }
    
    /* Ensure spinner alignment in buttons */
    .btn .spinner-border {
        vertical-align: middle;
    }
    
    /* Disabled button styling during loading */
    .btn:disabled {
        cursor: not-allowed;
        opacity: 0.65;
    }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/handsontable/dist/handsontable.full.min.js"></script>
<script>
let coreSpreadsheet;
let metadataSpreadsheet;
let systemSpreadsheet;
let connectionSpreadsheets = {}; // Object to store spreadsheets by connection type
let spanData = {};
let hasChanges = false;
let validationResults = {};
let originalValues = {}; // Track original values for highlighting changes
let connectionDetailsInitialised = false;
let objectSearchTimeout = null;

// Connection types for dropdown
const connectionTypes = @json($connectionTypes->pluck('type'));
const spanTypes = @json($spanTypes->pluck('type_id'));
const spanTypeMetadata = @json($spanTypeMetadata);
const connectionTypeDetails = @json($connectionTypeMetadata);
const spanSearchUrl = '{{ route('spans.api.search') }}';

$(document).ready(function() {
    console.log('=== DOCUMENT READY - SPREADSHEET EDITOR INITIALIZATION ===');
    console.log('Console logging test - if you see this, console.log is working');
    
    // Initialize span data
    @if($span)
        spanData = @json($spanData);
    @else
        spanData = {
            name: '',
            type: '',
            state: 'placeholder',
            start: '',
            end: '',
            description: '',
            notes: '',
            access_level: 'private',
            metadata: {},
            connections: [],
            connection_details: null
        };
    @endif
    
    console.log('Span data loaded:', spanData);
    console.log('Span metadata type:', typeof spanData.metadata);
    console.log('Span metadata value:', spanData.metadata);
    console.log('Connections data:', spanData.connections);
    
    // Initialize spreadsheets
    initializeSpreadsheets();
    
    // Set up event handlers
    setupEventHandlers();
    
    // Load data into spreadsheets
    loadSpreadsheetData();
    
    initializeConnectionDetails();
});

function formatDateForDisplay(year, month, day) {
    if (!year) return '';
    let date = year.toString();
    if (month) {
        date += '-' + month.toString().padStart(2, '0');
        if (day) {
            date += '-' + day.toString().padStart(2, '0');
        }
    }
    return date;
}

function parseDateFromDisplay(dateString) {
    if (!dateString || dateString.trim() === '') return { year: null, month: null, day: null };
    
    const parts = dateString.split('-');
    if (parts.length === 1) {
        return { year: parseInt(parts[0]), month: null, day: null };
    } else if (parts.length === 2) {
        return { year: parseInt(parts[0]), month: parseInt(parts[1]), day: null };
    } else if (parts.length === 3) {
        return { year: parseInt(parts[0]), month: parseInt(parts[1]), day: parseInt(parts[2]) };
    }
    return { year: null, month: null, day: null };
}

function storeOriginalValues() {
    // Store original values for core fields from the actual spreadsheet data
    originalValues.core = {};
    const coreData = coreSpreadsheet.getData();
    coreData.forEach(row => {
        if (row[0] && row[1] !== undefined) {
            originalValues.core[row[0]] = row[1];
        }
    });
    
    // Store original metadata values from the actual span data (not spreadsheet cells)
    originalValues.metadata = {};
    if (spanData.metadata && typeof spanData.metadata === 'object') {
        Object.keys(spanData.metadata).forEach(key => {
            originalValues.metadata[key] = spanData.metadata[key];
        });
    }
    
    // Store original system values from the actual spreadsheet data
    originalValues.system = {};
    const systemData = systemSpreadsheet.getData();
    systemData.forEach(row => {
        if (row[0] && row[1] !== undefined) {
            originalValues.system[row[0]] = row[1];
        }
    });
    
    // Store original connections from the actual span data (not spreadsheet cells)
    originalValues.connections = [];
    if (spanData.connections && Array.isArray(spanData.connections)) {
        spanData.connections.forEach(conn => {
            originalValues.connections.push({
                subject: conn.subject || '',
                subject_id: conn.subject_id || undefined,
                predicate: conn.predicate || '',
                object: conn.object || '',
                object_id: conn.object_id || undefined,
                direction: conn.direction || 'outgoing',
                start_year: conn.start_year || null,
                start_month: conn.start_month || null,
                start_day: conn.start_day || null,
                end_year: conn.end_year || null,
                end_month: conn.end_month || null,
                end_day: conn.end_day || null,
                metadata: conn.metadata || {}
            });
        });
    }

    originalValues.connection_details = spanData.connection_details
        ? { ...spanData.connection_details }
        : null;
    
    console.log('Original values stored:', originalValues);
}

function hasValueChanged(fieldName, currentValue, tableType = 'core') {
    if (!originalValues) return false;
    
    if (tableType === 'core') {
        if (!originalValues.core) return false;
        const originalValue = originalValues.core[fieldName];
        return originalValue !== currentValue;
    } else if (tableType === 'metadata') {
        if (!originalValues.metadata) return false;
        const originalValue = originalValues.metadata[fieldName];
        return originalValue !== currentValue;
    } else if (tableType === 'system') {
        if (!originalValues.system) return false;
        const originalValue = originalValues.system[fieldName];
        return originalValue !== currentValue;
    }
    return false;
}

function hasConnectionChanged(rowIndex, colIndex, currentValue, predicate) {
    if (!originalValues || !originalValues.connections || !originalValues.connections[rowIndex]) {
        return false;
    }
    
    const originalConn = originalValues.connections[rowIndex];
    if (originalConn.predicate !== predicate) {
        return false; // Different connection type
    }
    
    switch (colIndex) {
        case 1: // Predicate
            return originalConn.predicate !== currentValue;
        case 3: // Start Date
            const originalStart = formatDateForDisplay(originalConn.start_year, originalConn.start_month, originalConn.start_day);
            return originalStart !== currentValue;
        case 4: // End Date
            const originalEnd = formatDateForDisplay(originalConn.end_year, originalConn.end_month, originalConn.end_day);
            return originalEnd !== currentValue;
        case 5: // Metadata
            const originalMetadata = originalConn.metadata ? JSON.stringify(originalConn.metadata) : '';
            return originalMetadata !== currentValue;
        default:
            return false;
    }
}

function initializeSpreadsheets() {

    
    // Core Fields Spreadsheet
    const coreElement = document.getElementById('core-spreadsheet');
    coreSpreadsheet = new Handsontable(coreElement, {
        data: [],
        colHeaders: ['Field', 'Value', '?'],
        columns: [
            { data: 0, readOnly: true, width: '25%' },
            { 
                data: 1, 
                width: '65%',
                renderer: function(instance, td, row, col, prop, value, cellProperties) {
                    const fieldName = instance.getDataAtRowProp(row, 0);
                    
                    // Check if value has changed and highlight in yellow
                    if (hasValueChanged(fieldName, value, 'core')) {
                        td.style.backgroundColor = '#fff3cd'; // Light yellow background
                    }
                    
                    // For dropdown cells, let Handsontable handle the rendering
                    if (cellProperties.type === 'dropdown') {
                        return td; // Let Handsontable handle dropdown rendering
                    }
                    
                    // For other cells, set the innerHTML
                    td.innerHTML = value || '';
                    return td;
                }
            },
            { data: 2, readOnly: true, width: '10%', renderer: function(instance, td, row, col, prop, value) {
                const fieldName = instance.getDataAtRowProp(row, 0);
                const fieldValue = instance.getDataAtRowProp(row, 1);
                const isRequired = instance.getDataAtRowProp(row, 3) === true;
                
                let isValid = true;
                let errorMessage = '';
                
                // Validate based on field type and requirements
                if (isRequired && (!fieldValue || fieldValue.trim() === '')) {
                    isValid = false;
                    errorMessage = 'Required field is empty';
                } else if (fieldName === 'state' && fieldValue) {
                    const validStates = ['placeholder', 'draft', 'complete', 'published'];
                    if (!validStates.includes(fieldValue)) {
                        isValid = false;
                        errorMessage = 'Invalid state. Must be: ' + validStates.join(', ');
                    }
                } else if (fieldName === 'access_level' && fieldValue) {
                    const validAccessLevels = ['public', 'private', 'shared'];
                    if (!validAccessLevels.includes(fieldValue)) {
                        isValid = false;
                        errorMessage = 'Invalid access level. Must be: ' + validAccessLevels.join(', ');
                    }
                } else if (fieldName === 'type' && fieldValue) {
                    const validTypes = ['organisation', 'person', 'place', 'event', 'thing', 'band', 'phase', 'connection', 'set', 'role'];
                    if (!validTypes.includes(fieldValue)) {
                        isValid = false;
                        errorMessage = 'Invalid type. Must be: ' + validTypes.join(', ');
                    }
                } else if (fieldName === 'start' && fieldValue) {
                    const startDate = parseDateFromDisplay(fieldValue);
                    if (startDate.year && (startDate.year < 1 || startDate.year > 9999)) {
                        isValid = false;
                        errorMessage = 'Invalid year';
                    }
                } else if (fieldName === 'end' && fieldValue) {
                    const endDate = parseDateFromDisplay(fieldValue);
                    if (endDate.year && (endDate.year < 1 || endDate.year > 9999)) {
                        isValid = false;
                        errorMessage = 'Invalid year';
                    }
                }
                
                // Store validation result for tooltip
                td.setAttribute('data-validation-error', errorMessage);
                
                if (isValid) {
                    td.innerHTML = '<i class="bi bi-check-circle-fill text-success"></i>';
                    td.title = 'Valid';
                } else {
                    td.innerHTML = '<i class="bi bi-x-circle-fill text-danger"></i>';
                    td.title = errorMessage;
                }
                return td;
            }}
        ],
        rowHeaders: false,
        height: 'auto',
        stretchH: 'all',
        licenseKey: 'non-commercial-and-evaluation',
        afterChange: function(changes, source) {
            if (source === 'edit') {
                console.log('Core fields changed:', changes);
                updateSpanData();
                // Trigger re-render to update validation status
                setTimeout(() => coreSpreadsheet.render(), 100);
            }
        }
    });
    
    // Metadata Spreadsheet
    const metadataElement = document.getElementById('metadata-spreadsheet');
    metadataSpreadsheet = new Handsontable(metadataElement, {
        data: [],
        colHeaders: ['Key', 'Value', '?'],
        columns: [
            { data: 0, width: '25%' },
            { 
                data: 1, 
                width: '65%',
                renderer: function(instance, td, row, col, prop, value, cellProperties) {
                    const key = instance.getDataAtRowProp(row, 0);
                    
                    // Check if value has changed and highlight in yellow
                    if (hasValueChanged(key, value, 'metadata')) {
                        td.style.backgroundColor = '#fff3cd'; // Light yellow background
                    }
                    
                    // For other cells, set the innerHTML
                    td.innerHTML = value || '';
                    return td;
                }
            },
            { data: 2, readOnly: true, width: '10%', renderer: function(instance, td, row, col, prop, value) {
                const key = instance.getDataAtRowProp(row, 0);
                const keyValue = instance.getDataAtRowProp(row, 1);
                const keyType = instance.getDataAtRowProp(row, 2);
                
                let isValid = true;
                let errorMessage = '';
                
                // Validate metadata
                if (!key || key.trim() === '') {
                    isValid = false;
                    errorMessage = 'Key is required';
                } else if (keyType === 'number' && keyValue && isNaN(parseFloat(keyValue))) {
                    isValid = false;
                    errorMessage = 'Invalid number';
                } else if (keyType === 'boolean' && keyValue && !['true', 'false', '1', '0'].includes(keyValue.toLowerCase())) {
                    isValid = false;
                    errorMessage = 'Invalid boolean';
                }
                
                if (isValid) {
                    td.innerHTML = '<i class="bi bi-check-circle-fill text-success"></i>';
                    td.title = 'Valid';
                } else {
                    td.innerHTML = '<i class="bi bi-x-circle-fill text-danger"></i>';
                    td.title = errorMessage;
                }
                return td;
            }}
        ],
        rowHeaders: false,
        height: 'auto',
        stretchH: 'all',
        licenseKey: 'non-commercial-and-evaluation',
        afterChange: function(changes, source) {
            if (source === 'edit') {
                updateSpanData();
                // Trigger re-render to update validation status
                setTimeout(() => metadataSpreadsheet.render(), 100);
            }
        },
        afterCreateRow: function(index, amount, source) {
            updateSpanData();
            setTimeout(() => metadataSpreadsheet.render(), 100);
        },
        afterRemoveRow: function(index, amount, source) {
            updateSpanData();
            setTimeout(() => metadataSpreadsheet.render(), 100);
        }
    });
    
    // System Info Spreadsheet
    const systemElement = document.getElementById('system-spreadsheet');
    systemSpreadsheet = new Handsontable(systemElement, {
        data: [],
        colHeaders: ['Field', 'Value'],
        columns: [
            { data: 0, readOnly: true, width: 150 },
            { 
                data: 1, 
                width: 250,
                readOnly: true,
                renderer: function(instance, td, row, col, prop, value, cellProperties) {
                    // All system fields are read-only with light blue background
                    td.style.backgroundColor = '#e3f2fd';
                    td.style.fontWeight = 'bold';
                    td.innerHTML = value || '';
                    return td;
                }
            }
        ],
        rowHeaders: false,
        height: 'auto',
        stretchH: 'all',
        licenseKey: 'non-commercial-and-evaluation'
    });
    
    // Add dropdown functionality to core fields after creation
    coreSpreadsheet.updateSettings({
        afterOnCellMouseDown: function(event, coords) {
            const row = coords.row;
            const col = coords.col;
            if (col === 1) { // Value column
                const fieldName = coreSpreadsheet.getDataAtRowProp(row, 0);
                if (fieldName === 'state') {
                    coreSpreadsheet.setCellMeta(row, col, 'type', 'dropdown');
                    coreSpreadsheet.setCellMeta(row, col, 'source', ['placeholder', 'draft', 'complete', 'published']);
                } else if (fieldName === 'access_level') {
                    coreSpreadsheet.setCellMeta(row, col, 'type', 'dropdown');
                    coreSpreadsheet.setCellMeta(row, col, 'source', ['public', 'private', 'shared']);
                } else if (fieldName === 'type') {
                    coreSpreadsheet.setCellMeta(row, col, 'type', 'dropdown');
                    coreSpreadsheet.setCellMeta(row, col, 'source', ['organisation', 'person', 'place', 'event', 'thing', 'band', 'phase', 'connection', 'set', 'role']);
                }
            }
        }
    });
    
    // Initialize connection type tabs and spreadsheets
    initializeConnectionTabs();
    

}

function setupEventHandlers() {
    // Validate & Preview button
    $('#validate-btn').on('click', validateData);
    
    // Preview close functionality
    $('#close-preview').on('click', function() {
        $('#preview-container').hide();
    });
    
    // Save button
    $('#save-btn').on('click', saveSpan);
    console.log('Save button click handler attached');
}

function loadSpreadsheetData() {

    connectionDetailsInitialised = false;

    
    // Load core fields
    const coreFields = [
        ['slug', spanData.slug || '', ''],
        ['name', spanData.name || '', ''],
        ['type', spanData.type || '', '']
    ];
    
    // Add required metadata fields based on span type schema (insert after type)
    if (spanData.type && spanTypeMetadata[spanData.type]) {
        const schema = spanTypeMetadata[spanData.type].schema;
        Object.keys(schema).forEach(fieldName => {
            const fieldConfig = schema[fieldName];
            if (fieldConfig.required) {
                const currentValue = spanData[fieldName] || spanData.metadata?.[fieldName] || '';
                console.log('Loading field:', fieldName, 'with value:', currentValue);
                coreFields.push([fieldName, currentValue, '']);
            }
        });
    }
    
    // Add remaining core fields
    coreFields.push(
        ['state', spanData.state || 'placeholder', ''],
        ['start', formatDateForDisplay(spanData.start_year, spanData.start_month, spanData.start_day), ''],
        ['end', formatDateForDisplay(spanData.end_year, spanData.end_month, spanData.end_day), ''],
        ['description', spanData.description || '', ''],
        ['notes', spanData.notes || '', ''],
        ['access_level', spanData.access_level || 'private', '']
    );
        coreSpreadsheet.loadData(coreFields);
    
    // Set dropdown metadata for specific fields after data is loaded
    coreFields.forEach((row, index) => {
        const fieldName = row[0];
        if (fieldName === 'state') {
            coreSpreadsheet.setCellMeta(index, 1, 'type', 'dropdown');
            coreSpreadsheet.setCellMeta(index, 1, 'source', ['placeholder', 'draft', 'complete', 'published']);
        } else if (fieldName === 'access_level') {
            coreSpreadsheet.setCellMeta(index, 1, 'type', 'dropdown');
            coreSpreadsheet.setCellMeta(index, 1, 'source', ['public', 'private', 'shared']);
        } else if (fieldName === 'type') {
            coreSpreadsheet.setCellMeta(index, 1, 'type', 'dropdown');
            coreSpreadsheet.setCellMeta(index, 1, 'source', ['organisation', 'person', 'place', 'event', 'thing', 'band', 'phase', 'connection', 'set', 'role']);
        } else {
            // Check if this is a metadata field with dropdown options
            const currentType = spanData.type;
            if (currentType && spanTypeMetadata[currentType] && spanTypeMetadata[currentType].schema[fieldName]) {
                const fieldConfig = spanTypeMetadata[currentType].schema[fieldName];
                if (fieldConfig.type === 'select' && fieldConfig.options) {
                    console.log('Setting dropdown for', fieldName, 'with options:', fieldConfig.options);
                    coreSpreadsheet.setCellMeta(index, 1, 'type', 'dropdown');
                    coreSpreadsheet.setCellMeta(index, 1, 'source', fieldConfig.options);
                }
            }
        }
    });
    
    // Force a re-render to ensure dropdowns are properly initialized
    setTimeout(() => {
        coreSpreadsheet.render();
    }, 100);
    
    // Load metadata (excluding subtype which is handled in core fields)
    const metadata = spanData.metadata || {};
    const metadataRows = Object.entries(metadata)
        .filter(([key, value]) => key !== 'subtype') // Exclude subtype from metadata table
        .map(([key, value]) => [
            key,
            typeof value === 'object' ? JSON.stringify(value, null, 2) : String(value || ''),
            '' // Validation column will be populated by renderer
        ]);
    metadataSpreadsheet.loadData(metadataRows);
    
    // Load system info
    const systemFields = [
        ['Created', spanData.created_at ? new Date(spanData.created_at).toLocaleString() : ''],
        ['Last Updated', spanData.updated_at ? new Date(spanData.updated_at).toLocaleString() : ''],
        ['Last Modified By', spanData.updated_by || 'Not tracked'],
        ['Owner', spanData.owner || 'Not assigned'],
        ['UUID', spanData.id || '']
    ];
    systemSpreadsheet.loadData(systemFields);
    
    // Load connections into tabs
    loadConnectionData();
    syncConnectionDetailsVisibility();
    
    updateRowCount();
    
    // Store original values for change tracking AFTER all data is loaded and dynamic fields are added
    storeOriginalValues();
    
    // Update save button text to initial state
    updateSaveButtonText();
    
    // Listen for modal success to refresh connection data
    $(document).on('connectionAdded', function(event, connectionData) {
        console.log('Connection added via modal:', connectionData);
        // Reload the page to refresh all data
        window.location.reload();
    });

}

function updateSpanData() {

    
    // Update core fields
    const coreData = coreSpreadsheet.getData();
    coreData.forEach(row => {
        if (row[0] && row[1] !== undefined) {
            const fieldName = row[0];
            let value = row[1];
            
            // Handle date fields
            if (fieldName === 'start') {
                const startDate = parseDateFromDisplay(value);
                // Only set date components if they have valid values
                if (startDate.year) {
                    spanData.start_year = startDate.year;
                    spanData.start_month = startDate.month || null;
                    spanData.start_day = startDate.day || null;
                } else {
                    // Clear date fields if no year
                    delete spanData.start_year;
                    delete spanData.start_month;
                    delete spanData.start_day;
                }
            } else if (fieldName === 'end') {
                const endDate = parseDateFromDisplay(value);
                // Only set date components if they have valid values
                if (endDate.year) {
                    spanData.end_year = endDate.year;
                    spanData.end_month = endDate.month || null;
                    spanData.end_day = endDate.day || null;
                } else {
                    // Clear date fields if no year
                    delete spanData.end_year;
                    delete spanData.end_month;
                    delete spanData.end_day;
                }
            } else if (fieldName === 'type') {
                const oldType = spanData[fieldName];
                spanData[fieldName] = value;
                // When type changes, we need to reload the core fields to show/hide required metadata fields
                if (oldType !== value) {
                    setTimeout(() => {
                        loadSpreadsheetData();
                    }, 100);
                }
            } else {
                // Check if this is a metadata field that should be stored in metadata
                const currentType = spanData.type;
                if (currentType && spanTypeMetadata[currentType] && spanTypeMetadata[currentType].schema[fieldName]) {
                    // This is a metadata field, store it in metadata
                    if (!spanData.metadata) spanData.metadata = {};
                    spanData.metadata[fieldName] = value;
                } else {
                    // This is a core field
                    spanData[fieldName] = value;
                }
            }
        }
    });
    
    // Update metadata (preserving subtype from core fields)
    const metadataData = metadataSpreadsheet.getData();
    
    // Ensure metadata object exists (don't overwrite it if it already has data)
    if (!spanData.metadata) {
        spanData.metadata = {};
    }
    
    // Update metadata from the metadata table (excluding subtype which is handled in core fields)
    metadataData.forEach(row => {
        if (row[0] && row[0].trim() && row[0] !== 'subtype') {
            let value = row[1];
            
            // Try to parse JSON strings back to objects
            if (typeof value === 'string' && value.trim().startsWith('{') && value.trim().endsWith('}')) {
                try {
                    value = JSON.parse(value);
                } catch (e) {
                    // If parsing fails, keep as string
                    console.log('Failed to parse metadata JSON:', value, e);
                }
            } else if (row[2] === 'array' && value) {
                value = value.split(',').map(v => v.trim());
            } else if (row[2] === 'number' && value) {
                value = parseFloat(value);
            } else if (row[2] === 'boolean' && value) {
                value = value.toLowerCase() === 'true';
            }
            
            spanData.metadata[row[0]] = value;
        }
    });
    
    // Update connections from all tabs
    spanData.connections = [];
    
    Object.values(connectionSpreadsheets).forEach(spreadsheet => {
        const connectionsData = spreadsheet.getData();
        
        connectionsData.forEach(row => {
            if (row[0] && row[1] && row[2]) {
                const startDate = parseDateFromDisplay(row[3] || '');
                const endDate = parseDateFromDisplay(row[4] || '');
                
                const connection = {
                    subject: row[0],
                    subject_id: row.subject_id || undefined,
                    predicate: row[1],
                    object: row[2],
                    object_id: row.object_id || undefined,
                    direction: row.direction || 'outgoing'
                };
                
                // Only add date fields if they have valid values
                if (startDate.year) {
                    connection.start_year = startDate.year;
                    connection.start_month = startDate.month || null;
                    connection.start_day = startDate.day || null;
                }
                
                if (endDate.year) {
                    connection.end_year = endDate.year;
                    connection.end_month = endDate.month || null;
                    connection.end_day = endDate.day || null;
                }
                
                if (row[5]) {
                    try {
                        connection.metadata = JSON.parse(row[5]);
                    } catch (e) {
                        connection.metadata = { notes: row[5] };
                    }
                }
                
                spanData.connections.push(connection);
            }
        });
    });
    
    if (spanData.type === 'connection') {
        const details = spanData.connection_details || {};
        const typeSelect = $('#connection-type-select');
        const subjectInput = $('#connection-subject-input');
        const objectInput = $('#connection-object-input');

        details.type_id = typeSelect.val() || null;
        details.subject_id = $('#connection-subject-id').val() || null;
        details.subject_name = subjectInput.val() || '';
        details.subject_type = subjectInput.data('span-type') || null;
        details.subject_subtype = subjectInput.data('span-subtype') || spanData.connection_details?.subject_subtype || null;
        details.object_id = $('#connection-object-id').val() || null;
        details.object_name = objectInput.val() || '';
        details.object_type = objectInput.data('span-type') || null;
        details.object_subtype = objectInput.data('span-subtype') || spanData.connection_details?.object_subtype || null;

        spanData.connection_details = details;
    } else {
        spanData.connection_details = null;
    }
    
    hasChanges = true;
    updateRowCount();
    updateValidationStatus('Modified');
    

}

function updateRowCount() {
    const coreRows = coreSpreadsheet.countRows();
    const systemRows = systemSpreadsheet.countRows();
    const metadataRows = metadataSpreadsheet.countRows();
    const connectionRows = Object.values(connectionSpreadsheets).reduce((total, spreadsheet) => {
        return total + spreadsheet.countRows();
    }, 0);
    const total = coreRows + systemRows + metadataRows + connectionRows;
    $('#row-count').text(`${total} rows total`);
}

function validateConnectionRow(subject, predicate, object, startDate, endDate, metadata, td) {
    // Parse dates and metadata
    const start = parseDateFromDisplay(startDate || '');
    const end = parseDateFromDisplay(endDate || '');
    
    let metadataObj = {};
    if (metadata && metadata.trim()) {
        try {
            metadataObj = JSON.parse(metadata);
        } catch (e) {
            metadataObj = { notes: metadata };
        }
    }
    
    // Create connection data for validation
    const connectionData = {
        subject: subject,
        predicate: predicate,
        object: object,
        metadata: metadataObj
    };
    
    // Only add date fields if they have valid values
    if (start.year) {
        connectionData.start_year = start.year;
        connectionData.start_month = start.month || null;
        connectionData.start_day = start.day || null;
    }
    
    if (end.year) {
        connectionData.end_year = end.year;
        connectionData.end_month = end.month || null;
        connectionData.end_day = end.day || null;
    }
    
    // Send to server for validation
    $.ajax({
                        url: `/spans/${spanData.id}/spanner/validate-connection`,
        method: 'POST',
        data: {
            connection: connectionData,
            _token: $('meta[name="csrf-token"]').attr('content')
        },
        success: function(response) {
            if (response.success) {
                td.innerHTML = '<i class="bi bi-check-circle-fill text-success"></i>';
                td.title = 'Valid';
            } else {
                td.innerHTML = '<i class="bi bi-x-circle-fill text-danger"></i>';
                td.title = response.errors.join(', ');
            }
        },
        error: function() {
            td.innerHTML = '<i class="bi bi-exclamation-triangle-fill text-warning"></i>';
            td.title = 'Validation failed';
        }
    });
}

function updateValidationStatus(status, type = 'secondary') {
    const badge = $('#validation-status');
    badge.removeClass('bg-secondary bg-success bg-warning bg-danger').addClass(`bg-${type}`).text(status);
}

function updateSaveButtonText() {
    const saveBtn = $('#save-btn');
    const isDisabled = saveBtn.prop('disabled');
    
    if (isDisabled) {
        saveBtn.html('<i class="bi bi-cloud-upload me-1"></i>Saved');
    } else {
        saveBtn.html('<i class="bi bi-cloud-upload me-1"></i>Save Changes');
    }
}

function displayValidationErrors(errors) {
    const container = $('#validation-errors-container');
    const list = $('#validation-errors-list');
    
    // Clear existing errors
    list.empty();
    
    // Add each error to the list
    errors.forEach(function(error) {
        list.append(`<li>${error}</li>`);
    });
    
    // Show the container
    container.show();
    
    // Scroll to the top to show the errors
    $('html, body').animate({
        scrollTop: container.offset().top - 20
    }, 500);
}



function formatValueForDisplay(value) {
    if (value === null || value === undefined) {
        return 'empty';
    }
    if (typeof value === 'object') {
        const jsonString = JSON.stringify(value, null, 2);
        // If it's too long, truncate it
        if (jsonString.length > 200) {
            return jsonString.substring(0, 200) + '...';
        }
        return jsonString;
    }
    return String(value);
}

function displayPreviewResults(response) {
    const container = $('#preview-container');
    const content = $('#preview-content');
    
    let html = '<div class="row">';
    
    // Show impacts if any
    if (response.impacts && response.impacts.length > 0) {
        html += `
            <div class="col-md-6">
                <h6><i class="bi bi-exclamation-triangle me-2"></i>Potential Impacts</h6>
                <ul class="list-unstyled">
        `;
        response.impacts.forEach(impact => {
            html += `<li class="text-warning"><i class="bi bi-arrow-right me-1"></i>${impact}</li>`;
        });
        html += '</ul></div>';
    }
    
    // Show changes summary
    html += '<div class="col-md-6">';
    html += '<h6><i class="bi bi-list-check me-2"></i>Changes Summary</h6>';
    
    const diff = response.diff;
    let hasChanges = false;
    
    // Basic fields changes
    if (diff.basic_fields && diff.basic_fields.length > 0) {
        hasChanges = true;
        html += '<div class="mb-3"><strong>Basic Fields:</strong><ul class="list-unstyled ms-3">';
        diff.basic_fields.forEach(change => {
            const actionIcon = change.action === 'add' ? 'plus' : (change.action === 'remove' ? 'minus' : 'arrow-right');
            const actionColor = change.action === 'add' ? 'success' : (change.action === 'remove' ? 'danger' : 'primary');
            const currentValue = formatValueForDisplay(change.current);
            const newValue = formatValueForDisplay(change.new);
            html += `<li class="text-${actionColor}"><i class="bi bi-${actionIcon} me-1"></i>${change.field}: ${currentValue} → ${newValue}</li>`;
        });
        html += '</ul></div>';
    }
    
    // Metadata changes
    if (diff.metadata && diff.metadata.length > 0) {
        hasChanges = true;
        html += '<div class="mb-3"><strong>Metadata:</strong><ul class="list-unstyled ms-3">';
        diff.metadata.forEach(change => {
            const actionIcon = change.action === 'add' ? 'plus' : (change.action === 'remove' ? 'minus' : 'arrow-right');
            const actionColor = change.action === 'add' ? 'success' : (change.action === 'remove' ? 'danger' : 'primary');
            const currentValue = formatValueForDisplay(change.current);
            const newValue = formatValueForDisplay(change.new);
            html += `<li class="text-${actionColor}"><i class="bi bi-${actionIcon} me-1"></i>${change.key}: ${currentValue} → ${newValue}</li>`;
        });
        html += '</ul></div>';
    }

    if (diff.connection_details && diff.connection_details.length > 0) {
        hasChanges = true;
        html += '<div class="mb-3"><strong>Connection Details:</strong><ul class="list-unstyled ms-3">';
        diff.connection_details.forEach(change => {
            const actionIcon = change.action === 'add' ? 'plus' : (change.action === 'remove' ? 'minus' : 'arrow-right');
            const actionColor = change.action === 'add' ? 'success' : (change.action === 'remove' ? 'danger' : 'primary');
            const currentValue = formatValueForDisplay(change.current);
            const newValue = formatValueForDisplay(change.new);
            html += `<li class="text-${actionColor}"><i class="bi bi-${actionIcon} me-1"></i>${change.field}: ${currentValue} → ${newValue}</li>`;
        });
        html += '</ul></div>';
    }
    
    // Connections changes
    if (diff.connections && diff.connections.length > 0) {
        hasChanges = true;
        html += '<div class="mb-3"><strong>Connections:</strong><ul class="list-unstyled ms-3">';
        diff.connections.forEach(change => {
            if (change.added && change.added.length > 0) {
                html += `<li class="text-success"><i class="bi bi-plus me-1"></i>${change.type}: Add ${change.added.join(', ')}</li>`;
            }
            if (change.removed && change.removed.length > 0) {
                html += `<li class="text-danger"><i class="bi bi-minus me-1"></i>${change.type}: Remove ${change.removed.join(', ')}</li>`;
            }
            if (change.modified && change.modified.length > 0) {
                change.modified.forEach(modification => {
                    html += `<li class="text-primary"><i class="bi bi-arrow-right me-1"></i>${change.type}: Modify ${modification.object}</li>`;
                    // Show the specific changes
                    Object.entries(modification.changes).forEach(([field, values]) => {
                        const currentValue = formatValueForDisplay(values.current);
                        const newValue = formatValueForDisplay(values.new);
                        html += `<li class="text-muted ms-4"><i class="bi bi-dot me-1"></i>${field}: ${currentValue} → ${newValue}</li>`;
                    });
                });
            }
        });
        html += '</ul></div>';
    }
    
    if (!hasChanges) {
        html += '<p class="text-muted">No changes detected.</p>';
    }
    
    html += '</div></div>';
    
    content.html(html);
    container.show();
    
    // Scroll to the preview
    $('html, body').animate({
        scrollTop: container.offset().top - 20
    }, 500);
}

function addMetadataRow() {
    metadataSpreadsheet.alter('insert_row');
}



function validateData() {
    console.log('Validating and previewing data...');
    updateValidationStatus('Validating and previewing...', 'warning');
    
    // Update spanData from spreadsheets first
    updateSpanData();
    
    // Show loading state on validate button
    const validateBtn = $('#validate-btn');
    const originalValidateText = validateBtn.html();
    validateBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Validating & Previewing...');
    
    // Send data to server for validation
    console.log('Sending validation data:', spanData);
    console.log('Validation data connections count:', spanData.connections ? spanData.connections.length : 0);
    console.log('Validation data connections:', spanData.connections);
    console.log('About to make AJAX request to:', '{{ route("spans.spanner-validate", $span) }}');
    
    // First, validate the data
    $.ajax({
                        url: '{{ route("spans.spanner-validate", $span) }}',
        method: 'POST',
        data: {
            ...spanData,
            _token: '{{ csrf_token() }}'
        },
        success: function(validationResponse) {
            console.log('Validation AJAX success:', validationResponse);
            
            if (validationResponse.success) {
                // Validation passed, now generate preview
                console.log('Validation passed, generating preview...');
                updateValidationStatus('Generating preview...', 'warning');
                
                $.ajax({
                    url: '{{ route("spans.spanner-preview", $span) }}',
                    method: 'POST',
                    data: {
                        ...spanData,
                        _token: '{{ csrf_token() }}'
                    },
                    success: function(previewResponse) {
                        console.log('Preview AJAX success:', previewResponse);
                        
                        if (previewResponse.success) {
                            // Both validation and preview succeeded
                            updateValidationStatus('Valid', 'success');
                            $('#save-btn').prop('disabled', false);
                            updateSaveButtonText();
                            validationResults = { errors: [], warnings: [] };
                            
                            // Hide validation errors display and show success
                            $('#validation-errors-container').hide();
                            const successContainer = $('#validation-success-container');
                            const successList = $('#validation-success-list');
                            successList.empty();
                            successList.append('<li>All data is valid and ready to save.</li>');
                            successContainer.show();
                            
                            // Display the preview results
                            displayPreviewResults(previewResponse);
                            
                            // Auto-hide success message after 3 seconds
                            setTimeout(function() {
                                successContainer.fadeOut();
                            }, 3000);
                        } else {
                            // Preview failed
                            updateValidationStatus('Preview failed', 'danger');
                            $('#save-btn').prop('disabled', true);
                            updateSaveButtonText();
                            alert('Failed to generate preview: ' + (previewResponse.error || 'Unknown error'));
                        }
                    },
                    error: function(xhr) {
                        console.log('Preview AJAX error:', xhr);
                        updateValidationStatus('Preview failed', 'danger');
                        $('#save-btn').prop('disabled', true);
                        updateSaveButtonText();
                        alert('Failed to generate preview: ' + xhr.responseText);
                    }
                });
            } else {
                // Validation failed
                updateValidationStatus(`${validationResponse.errors.length} errors`, 'danger');
                $('#save-btn').prop('disabled', true);
                updateSaveButtonText();
                validationResults = { errors: validationResponse.errors, warnings: [] };
                
                // Hide success message and display validation errors in the UI
                $('#validation-success-container').hide();
                displayValidationErrors(validationResponse.errors);
                
                // Show validation errors in console for debugging
                console.log('Validation errors:', validationResponse.errors);
            }
        },
        error: function(xhr) {
            console.log('Validation AJAX error:', xhr);
            console.log('Validation error status:', xhr.status);
            console.log('Validation error response:', xhr.responseText);
            updateValidationStatus('Validation failed', 'danger');
            $('#save-btn').prop('disabled', true);
            updateSaveButtonText();
            console.error('Validation error:', xhr.responseText);
        },
        complete: function() {
            // Reset validate button state
            validateBtn.prop('disabled', false).html(originalValidateText);
        }
    });
}

function saveSpan() {
    console.log('=== SAVE SPAN PROCESS START ===');
    console.log('Current spanData:', spanData);
    console.log('Validation results:', validationResults);
    
    if (validationResults.errors && validationResults.errors.length > 0) {
        console.error('Cannot save - validation errors present:', validationResults.errors);
        alert('Please fix validation errors before saving');
        return;
    }
    
    updateValidationStatus('Saving...', 'warning');
    
    // Show loading state on save button
    const saveBtn = $('#save-btn');
    const originalSaveText = saveBtn.html();
    saveBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Saving...');
    
    // Prepare data for saving
    const saveData = {
        ...spanData,
        _token: '{{ csrf_token() }}'
    };
    
    console.log('Prepared saveData:', saveData);
    
    // Remove duplicate subtype field if it exists in metadata
    if (saveData.metadata && saveData.metadata.subtype && saveData.subtype) {
        console.log('Removing duplicate subtype field from top level');
        delete saveData.subtype;
    }
    
    @if($span)
        // Update existing span
        $.ajax({
                            url: '{{ route("spans.spanner-update", $span) }}',
            method: 'PUT',
            data: saveData,
            success: function(response, textStatus, xhr) {
                console.log('=== SAVE SUCCESS ===');
                console.log('Response status:', xhr.status);
                console.log('Response status text:', xhr.statusText);
                console.log('Text status:', textStatus);
                console.log('Response headers:', xhr.getAllResponseHeaders());
                console.log('Response data:', response);
                console.log('Response type:', typeof response);
                
                updateValidationStatus('Saved successfully!', 'success');
                hasChanges = false;
                $('#save-btn').prop('disabled', true);
                updateSaveButtonText();
                
                // Show success message
                const successContainer = $('#validation-success-container');
                const successList = $('#validation-success-list');
                successList.empty();
                successList.append(`<li>${response.message || 'Span updated successfully'}</li>`);
                successContainer.show();
                
                // Hide success message after 3 seconds
                setTimeout(() => {
                    successContainer.fadeOut();
                }, 3000);
                
                // Update original values to reflect the saved state
                storeOriginalValues();
                console.log('=== SAVE PROCESS COMPLETE ===');
            },
            error: function(xhr, textStatus, errorThrown) {
                console.log('=== SAVE ERROR ===');
                console.log('Error status:', xhr.status);
                console.log('Error status text:', xhr.statusText);
                console.log('Text status:', textStatus);
                console.log('Error thrown:', errorThrown);
                console.log('Response text:', xhr.responseText);
                console.log('Response JSON:', xhr.responseJSON);
                console.log('Response headers:', xhr.getAllResponseHeaders());
                console.log('Ready state:', xhr.readyState);
                
                updateValidationStatus('Save failed', 'danger');
                
                // Show error details
                let errorMessage = 'Failed to save changes. Please try again.';
                if (xhr.responseJSON && xhr.responseJSON.errors) {
                    if (Array.isArray(xhr.responseJSON.errors)) {
                        errorMessage = xhr.responseJSON.errors.join(', ');
                    } else {
                        errorMessage = Object.values(xhr.responseJSON.errors).flat().join(', ');
                    }
                } else if (xhr.responseJSON && xhr.responseJSON.error) {
                    errorMessage = xhr.responseJSON.error;
                } else if (xhr.responseText) {
                    // Try to parse response text as JSON
                    try {
                        const parsedResponse = JSON.parse(xhr.responseText);
                        if (parsedResponse.errors) {
                            errorMessage = Array.isArray(parsedResponse.errors) 
                                ? parsedResponse.errors.join(', ')
                                : Object.values(parsedResponse.errors).flat().join(', ');
                        } else if (parsedResponse.error) {
                            errorMessage = parsedResponse.error;
                        }
                    } catch (e) {
                        console.log('Could not parse response as JSON:', e);
                        errorMessage = xhr.responseText || 'Unknown error occurred';
                    }
                }
                
                console.log('Final error message:', errorMessage);
                
                // Display errors in the validation errors container
                const errors = Array.isArray(xhr.responseJSON?.errors) ? xhr.responseJSON.errors : [errorMessage];
                displayValidationErrors(errors);
                
                console.log('=== SAVE ERROR PROCESSING COMPLETE ===');
            },
            complete: function() {
                // Reset save button state if not successful (success case handles its own state)
                if (!saveBtn.prop('disabled')) {
                    saveBtn.prop('disabled', false).html(originalSaveText);
                }
            }
        });
    @else
        // Create new span
        console.log('Creating new span with data:', saveData);
        $.ajax({
            url: '{{ route("spans.store") }}',
            method: 'POST',
            data: saveData,
            success: function(response) {
                console.log('=== CREATE SUCCESS ===');
                console.log('Response:', response);
                updateValidationStatus('Created!', 'success');
                setTimeout(() => {
                    window.location.href = response.redirect_url || '{{ route("spans.index") }}';
                }, 1000);
            },
            error: function(xhr, textStatus, errorThrown) {
                console.log('=== CREATE ERROR ===');
                console.log('Error status:', xhr.status);
                console.log('Error status text:', xhr.statusText);
                console.log('Text status:', textStatus);
                console.log('Error thrown:', errorThrown);
                console.log('Response text:', xhr.responseText);
                console.log('Response JSON:', xhr.responseJSON);
                
                updateValidationStatus('Create failed', 'danger');
                console.error('Create error:', xhr.responseText);
                alert('Failed to create span. Please try again.');
            },
            complete: function() {
                // Reset save button state if not successful (success case redirects)
                if (!saveBtn.prop('disabled')) {
                    saveBtn.prop('disabled', false).html(originalSaveText);
                }
            }
        });
    @endif
}

function initializeConnectionTabs() {
    // Get all unique predicates from the data
    const connections = spanData.connections || [];
    const predicates = [...new Set(connections.map(conn => conn.predicate).filter(predicate => predicate))];
    
    // If no connections, create a default "All" tab
    if (predicates.length === 0) {
        predicates.push('All');
    }
    
    // Create tabs
    const tabsContainer = document.getElementById('connectionTabs');
    const tabContentContainer = document.getElementById('connectionTabContent');
    
    tabsContainer.innerHTML = '';
    tabContentContainer.innerHTML = '';
    
    predicates.forEach((predicate, index) => {
        const isActive = index === 0;
        
        // Create tab
        const tab = document.createElement('li');
        tab.className = 'nav-item';
        tab.innerHTML = `
            <a class="nav-link ${isActive ? 'active' : ''}" id="tab-${predicate}" data-bs-toggle="tab" href="#content-${predicate}" role="tab">
                ${predicate} (${connections.filter(conn => predicate === 'All' || conn.predicate === predicate).length})
            </a>
        `;
        tabsContainer.appendChild(tab);
        
        // Create tab content
        const tabContent = document.createElement('div');
        tabContent.className = `tab-pane fade ${isActive ? 'show active' : ''} connection-tab-pane`;
        tabContent.id = `content-${predicate}`;
        tabContent.innerHTML = `<div id="spreadsheet-${predicate}" class="spreadsheet-editor-container"></div>`;
        tabContentContainer.appendChild(tabContent);
        
        // Create Handsontable for this predicate
        const element = document.getElementById(`spreadsheet-${predicate}`);
        connectionSpreadsheets[predicate] = new Handsontable(element, {
            data: [],
            colHeaders: ['Subject', 'Predicate', 'Object', 'Start Date', 'End Date', 'Metadata', '?'],
            columns: [
                { 
                    data: 0, 
                    width: '15%', 
                    readOnly: true,
                    renderer: function(instance, td, row, col, prop, value) {
                        if (value === spanData.name) {
                            td.style.backgroundColor = '#e3f2fd';
                            td.style.fontWeight = 'bold';
                        }
                        td.innerHTML = value || '';
                        return td;
                    }
                },
                { 
                    data: 1, 
                    width: '12%', 
                    type: 'dropdown', 
                    source: connectionTypes
                },
                { 
                    data: 2, 
                    width: '15%', 
                    readOnly: true,
                    renderer: function(instance, td, row, col, prop, value) {
                        if (value === spanData.name) {
                            td.style.backgroundColor = '#e3f2fd';
                            td.style.fontWeight = 'bold';
                        }
                        td.innerHTML = value || '';
                        return td;
                    }
                },
                { 
                    data: 3, 
                    width: '10%',
                    renderer: function(instance, td, row, col, prop, value, cellProperties) {
                        const predicate = instance.getDataAtRowProp(row, 1);
                        // Check if value has changed and highlight in yellow
                        if (hasConnectionChanged(row, col, value, predicate)) {
                            td.style.backgroundColor = '#fff3cd'; // Light yellow background
                        }
                        td.innerHTML = value || '';
                        return td;
                    }
                },
                { 
                    data: 4, 
                    width: '10%',
                    renderer: function(instance, td, row, col, prop, value, cellProperties) {
                        const predicate = instance.getDataAtRowProp(row, 1);
                        // Check if value has changed and highlight in yellow
                        if (hasConnectionChanged(row, col, value, predicate)) {
                            td.style.backgroundColor = '#fff3cd'; // Light yellow background
                        }
                        td.innerHTML = value || '';
                        return td;
                    }
                },
                { 
                    data: 5, 
                    width: '28%', 
                    type: 'text',
                    renderer: function(instance, td, row, col, prop, value, cellProperties) {
                        const predicate = instance.getDataAtRowProp(row, 1);
                        // Check if value has changed and highlight in yellow
                        if (hasConnectionChanged(row, col, value, predicate)) {
                            td.style.backgroundColor = '#fff3cd'; // Light yellow background
                        }
                        
                        // For metadata, format as JSON if it's an object
                        if (value && typeof value === 'object') {
                            td.innerHTML = JSON.stringify(value, null, 2);
                            td.style.whiteSpace = 'pre-wrap';
                            td.style.fontFamily = 'monospace';
                        } else {
                            td.innerHTML = value || '';
                        }
                        return td;
                    }
                },
                { data: 6, readOnly: true, width: '10%', renderer: function(instance, td, row, col, prop, value) {
                    const subject = instance.getDataAtRowProp(row, 0);
                    const predicate = instance.getDataAtRowProp(row, 1);
                    const object = instance.getDataAtRowProp(row, 2);
                    const startDate = instance.getDataAtRowProp(row, 3);
                    const endDate = instance.getDataAtRowProp(row, 4);
                    const metadata = instance.getDataAtRowProp(row, 5);
                    
                    // Show loading state initially
                    td.innerHTML = '<i class="bi bi-hourglass-split text-secondary"></i>';
                    td.title = 'Validating...';
                    
                    // Validate this specific row using server-side validation
                    validateConnectionRow(subject, predicate, object, startDate, endDate, metadata, td);
                    
                    return td;
                }}
            ],
            rowHeaders: false,
            height: 'auto',
            stretchH: 'all',
            licenseKey: 'non-commercial-and-evaluation',
            afterChange: function(changes, source) {
                if (source === 'edit') {
                    updateSpanData();
                    // Trigger re-render to update validation status
                    setTimeout(() => this.render(), 100);
                }
            },
            afterCreateRow: function(index, amount, source) {
                updateSpanData();
                setTimeout(() => this.render(), 100);
            },
            afterRemoveRow: function(index, amount, source) {
                updateSpanData();
                setTimeout(() => this.render(), 100);
            }
        });
    });
}

function initializeConnectionDetails() {
    const card = $('#connection-details-card');
    if (!card.length) {
        return;
    }

    $('#connection-type-select').on('change', function() {
        const selectedType = $(this).val() || null;
        clearConnectionObjectSelection();
        updateConnectionTypeInfo(selectedType);
        spanData.connection_details = spanData.connection_details || {};
        spanData.connection_details.type_id = selectedType;
        updateSpanData();
    });

    setupConnectionLookup('object');

    $('#connection-object-clear').on('click', function() {
        clearConnectionObjectSelection();
        updateSpanData();
        if (!$('#connection-object-input').prop('disabled')) {
            $('#connection-object-input').focus();
        }
    });

    $(document).on('mousedown', function(event) {
        $('.connection-suggestions').each(function() {
            const container = $(this);
            if (!container.is(event.target) && container.has(event.target).length === 0 &&
                !container.prev().is(event.target) && container.prev().has(event.target).length === 0) {
                container.hide();
            }
        });
    });

    syncConnectionDetailsVisibility();
}

function syncConnectionDetailsVisibility() {
    const card = $('#connection-details-card');
    if (!card.length) {
        return;
    }

    if (spanData.type === 'connection') {
        card.removeClass('d-none');
        if (!connectionDetailsInitialised) {
            populateConnectionDetailsFields();
            connectionDetailsInitialised = true;
        } else {
            refreshConnectionDetailsSummary();
        }
        updateConnectionTypeInfo($('#connection-type-select').val() || spanData.connection_details?.type_id || null);
    } else {
        card.addClass('d-none');
    }
}

function populateConnectionDetailsFields() {
    const details = spanData.connection_details || {};

    if (details.type_id) {
        $('#connection-type-select').val(details.type_id);
    } else {
        $('#connection-type-select').val('');
    }

    details.subject_subtype = details.subject_subtype || spanData.connection_details?.subject_subtype || spanData.subtype || null;

    if (details.subject_name) {
        $('#connection-subject-input')
            .val(details.subject_name)
            .data('span-type', details.subject_type || null)
            .data('span-subtype', details.subject_subtype || null);
        $('#connection-subject-id').val(details.subject_id || '');
    } else {
        $('#connection-subject-input').val('').data('span-type', null).removeData('span-subtype');
        $('#connection-subject-id').val('');
    }

    if (details.object_name) {
        $('#connection-object-input')
            .val(details.object_name)
            .data('span-type', details.object_type || null)
            .data('span-subtype', details.object_subtype || null);
        $('#connection-object-id').val(details.object_id || '');
    } else {
        $('#connection-object-input').val('').removeData('span-type').removeData('span-subtype');
        $('#connection-object-id').val('');
    }

    refreshConnectionDetailsSummary();
    applyAllowedTypeHints(details.type_id || $('#connection-type-select').val() || null);
}

function refreshConnectionDetailsSummary() {
    const details = spanData.connection_details || {};
    $('#connection-subject-summary').text(connectionSummary(details.subject_name, details.subject_type, details.subject_subtype));
    $('#connection-object-summary').text(connectionSummary(details.object_name, details.object_type, details.object_subtype));
}

function connectionSummary(name, type, subtype) {
    if (!name) {
        return '';
    }
    const parts = [];
    if (type) {
        parts.push(type);
    }
    if (subtype) {
        parts.push(subtype);
    }
    return parts.length ? `${name} (${parts.join(' • ')})` : name;
}


function setupConnectionLookup(role) {
    const input = $(`#connection-${role}-input`);
    const hiddenId = $(`#connection-${role}-id`);
    const suggestions = $(`#connection-${role}-suggestions`);

    input.on('keyup', function(event) {
        const key = event.which || event.keyCode;
        if ([13, 27, 38, 40].includes(key)) {
            handleSuggestionNavigation(event, suggestions, input, hiddenId, role);
            return;
        }

        const query = $(this).val();
        if (!query) {
            suggestions.hide();
            if (role === 'object') {
                clearConnectionObjectSelection();
                updateSpanData();
            }
            return;
        }

        const selectedType = $('#connection-type-select').val() || spanData.connection_details?.type_id || null;
        const allowedTypes = getAllowedTypesForRole(selectedType, role);

        if (role === 'object' && objectSearchTimeout) {
            clearTimeout(objectSearchTimeout);
        }

        const timeoutId = setTimeout(() => {
            fetchSpanSuggestions(query, allowedTypes, function(results) {
                renderSuggestions(results, suggestions, input, hiddenId, role);
            });
        }, 250);

        objectSearchTimeout = timeoutId;
    });
}

function handleSuggestionNavigation(event, suggestions, input, hiddenId, role) {
    const items = suggestions.find('.list-group-item');
    if (!items.length) {
        return;
    }

    const key = event.which || event.keyCode;
    let currentIndex = items.index(suggestions.find('.list-group-item.active'));

    if (key === 40) {
        event.preventDefault();
        currentIndex = (currentIndex + 1) % items.length;
        items.removeClass('active').eq(currentIndex).addClass('active');
    } else if (key === 38) {
        event.preventDefault();
        currentIndex = (currentIndex - 1 + items.length) % items.length;
        items.removeClass('active').eq(currentIndex).addClass('active');
    } else if (key === 13) {
        event.preventDefault();
        if (currentIndex >= 0) {
            items.eq(currentIndex).trigger('mousedown');
        }
    } else if (key === 27) {
        suggestions.hide();
    }
}

function fetchSpanSuggestions(query, allowedTypes, callback) {
    const params = { q: query };
    const subjectId = spanData.connection_details?.subject_id;
    if (subjectId) {
        params.exclude = subjectId;
    }
    if (allowedTypes && allowedTypes.length) {
        params.types = allowedTypes.join(',');
    }

    $.ajax({
        url: spanSearchUrl,
        method: 'GET',
        data: params,
        success: function(response) {
            if (response && response.spans) {
                const filtered = response.spans.filter(span => span.id);
                callback(filtered);
            } else {
                callback([]);
            }
        },
        error: function() {
            callback([]);
        }
    });
}

function renderSuggestions(spans, container, input, hiddenId, role) {
    container.empty();

    if (!spans.length) {
        container.hide();
        return;
    }

    spans.forEach(span => {
        const item = $('<button type="button" class="list-group-item list-group-item-action"></button>');
        const subtypeValue = span.subtype || (span.metadata ? span.metadata.subtype : null);
        const subtypeLabel = subtypeValue ? ` • ${subtypeValue}` : '';
        item.text(`${span.name} (${span.type_id}${subtypeLabel})${span.is_placeholder ? ' [placeholder]' : ''}`);
        item.data('span-id', span.id);
        item.data('span-name', span.name);
        item.data('span-type', span.type_id);
        if (subtypeValue) {
            item.data('span-subtype', subtypeValue);
        }

        item.on('mousedown', function() {
            const selectedId = $(this).data('span-id');
            const selectedName = $(this).data('span-name');
            const selectedType = $(this).data('span-type');
            const selectedSubtype = $(this).data('span-subtype');

            hiddenId.val(selectedId);
            input.val(selectedName).data('span-type', selectedType);
            input.data('span-subtype', selectedSubtype || null);
            container.hide();

            if (role === 'object') {
                spanData.connection_details = spanData.connection_details || {};
                spanData.connection_details.object_id = selectedId;
                spanData.connection_details.object_name = selectedName;
                spanData.connection_details.object_type = selectedType;
                spanData.connection_details.object_subtype = selectedSubtype || null;
                updateSpanData();
                refreshConnectionDetailsSummary();
            }
        });

        container.append(item);
    });

    container.show();
}

function getAllowedTypesForRole(connectionType, role) {
    if (!connectionType || !connectionTypeDetails || !connectionTypeDetails[connectionType]) {
        return [];
    }
    const key = role === 'subject' ? 'parent' : 'child';
    return connectionTypeDetails[connectionType].allowed_span_types?.[key] || [];
}

function updateConnectionTypeInfo(connectionType) {
    const description = $('#connection-type-description');
    const typeBadge = $('#connection-details-type-badge');
    const objectInput = $('#connection-object-input');

    if (!connectionType || !connectionTypeDetails[connectionType]) {
        description.text('Select a connection type to see predicate details and allowed span types.');
        typeBadge.hide();
        objectInput.prop('disabled', true);
        clearConnectionObjectSelection();
        return;
    }

    const details = connectionTypeDetails[connectionType];
    description.html(
        `<strong>Forward:</strong> ${details.forward_predicate || '—'} &middot; ` +
        `<strong>Inverse:</strong> ${details.inverse_predicate || '—'}`
    );
    typeBadge.text(connectionType).show();
    applyAllowedTypeHints(connectionType);
    objectInput.prop('disabled', false);
}

function applyAllowedTypeHints(connectionType) {
    const container = $('#connection-type-allowed');
    const subjectBadge = $('#allowed-subject-types-badge');
    const objectBadge = $('#allowed-object-types-badge');
    const objectInput = $('#connection-object-input');

    if (!connectionType || !connectionTypeDetails[connectionType]) {
        container.addClass('d-none');
        subjectBadge.text('');
        objectBadge.text('');
        objectInput.prop('disabled', true);
        clearConnectionObjectSelection();
        return;
    }

    const details = connectionTypeDetails[connectionType];
    const subjectTypes = details.allowed_span_types?.parent || [];
    const objectTypes = details.allowed_span_types?.child || [];

    subjectBadge.text(subjectTypes.length ? `Subject: ${subjectTypes.join(', ')}` : 'Subject: Any type');
    objectBadge.text(objectTypes.length ? `Object: ${objectTypes.join(', ')}` : 'Object: Any type');
    container.removeClass('d-none');
    objectInput.prop('disabled', false);
}

function clearConnectionObjectSelection() {
    $('#connection-object-input').val('').removeData('span-type').removeData('span-subtype');
    $('#connection-object-id').val('');
    $('#connection-object-summary').text('');
    $('#connection-object-suggestions').hide();
    spanData.connection_details = spanData.connection_details || {};
    spanData.connection_details.object_id = null;
    spanData.connection_details.object_name = '';
    spanData.connection_details.object_type = null;
    spanData.connection_details.object_subtype = null;
    refreshConnectionDetailsSummary();
    updateSpanData();
}

function loadConnectionData() {
    const connections = spanData.connections || [];
    const predicates = Object.keys(connectionSpreadsheets);
    
    predicates.forEach(predicate => {
        const spreadsheet = connectionSpreadsheets[predicate];
        let filteredConnections;
        
        if (predicate === 'All') {
            filteredConnections = connections;
        } else {
            filteredConnections = connections.filter(conn => conn.predicate === predicate);
        }
        
        const connectionRows = filteredConnections.map(conn => {
            const metadataJson = conn.metadata && Object.keys(conn.metadata).length > 0 ? JSON.stringify(conn.metadata, null, 2) : '';
            
            // Store the row with IDs as hidden data
            const row = [
                conn.subject || '',
                conn.predicate || '',
                conn.object || '',
                formatDateForDisplay(conn.start_year, conn.start_month, conn.start_day),
                formatDateForDisplay(conn.end_year, conn.end_month, conn.end_day),
                metadataJson,
                '' // Validation column will be populated by renderer
            ];
            
            // Add IDs as hidden properties on the row
            row.subject_id = conn.subject_id;
            row.object_id = conn.object_id;
            row.direction = conn.direction;
            
            return row;
        });
        
        spreadsheet.loadData(connectionRows);
    });
}
</script>
@endpush
