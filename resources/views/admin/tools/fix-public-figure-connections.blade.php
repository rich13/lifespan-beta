@extends('layouts.app')

@section('page_title')
    Fix Public Figure Connections
@endsection

@section('content')
<div class="py-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">Fix Public Figure Connections</h1>
                <a href="{{ route('admin.tools.index') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Back to Tools
                </a>
            </div>
            
            @if (session('status'))
                <div class="alert alert-success">
                    {{ session('status') }}
                </div>
            @endif

            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Overview</h5>
                </div>
                <div class="card-body">
                    <p class="mb-3">
                        This tool ensures that all connections for public figures are public. 
                        This is important for timeline rendering and maintaining consistency in the prototype.
                    </p>
                    
                    <div class="row">
                        <div class="col-md-3">
                            <div class="text-center">
                                <h4 class="text-primary">{{ $stats['total_public_figures'] }}</h4>
                                <small class="text-muted">Total Public Figures</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center">
                                <h4 class="text-warning">{{ $stats['public_figures_with_private_connections'] }}</h4>
                                <small class="text-muted">Figures with Private Connections</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center">
                                <h4 class="text-danger">{{ $stats['total_private_connections'] }}</h4>
                                <small class="text-muted">Total Private Connections</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center">
                                <h4 class="text-success">{{ $stats['fixed_connections'] }}</h4>
                                <small class="text-muted">Connections Fixed</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Public Figures</h5>
                    @if($stats['public_figures_with_private_connections'] > 0)
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-primary" onclick="fixAllConnections()">
                                <i class="bi bi-check-circle"></i> Fix All Private Connections
                            </button>
                            <button type="button" class="btn btn-success" onclick="startBatchProcessing()">
                                <i class="bi bi-arrow-repeat"></i> Batch Process
                            </button>
                        </div>
                    @endif
                </div>
                <div class="card-body">
                    @if($publicFigures->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>
                                            <div class="form-check">
                                                <input type="checkbox" class="form-check-input" id="select-all">
                                            </div>
                                        </th>
                                        <th>Name</th>
                                        <th>Access Level</th>
                                        <th>Private Connections</th>
                                        <th>Owner</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($publicFigures as $figure)
                                        @php
                                            $privateConnections = app(\App\Http\Controllers\Admin\ToolsController::class)->getPrivateConnectionsForSpan($figure);
                                            $hasPrivateConnections = $privateConnections->count() > 0;
                                        @endphp
                                        <tr class="{{ $hasPrivateConnections ? 'table-warning' : '' }}">
                                            <td>
                                                <div class="form-check">
                                                    <input type="checkbox" class="form-check-input figure-checkbox" 
                                                           value="{{ $figure->id }}" 
                                                           {{ $hasPrivateConnections ? '' : 'disabled' }}>
                                                </div>
                                            </td>
                                            <td>
                                                <strong>{{ $figure->name }}</strong>
                                                @if($figure->description)
                                                    <br><small class="text-muted">{{ Str::limit($figure->description, 50) }}</small>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="badge bg-{{ $figure->access_level === 'public' ? 'success' : 'warning' }}">
                                                    {{ ucfirst($figure->access_level) }}
                                                </span>
                                            </td>
                                            <td>
                                                @if($hasPrivateConnections)
                                                    <span class="badge bg-danger">{{ $privateConnections->count() }} private</span>
                                                @else
                                                    <span class="badge bg-success">All public</span>
                                                @endif
                                            </td>
                                            <td>
                                                {{ $figure->owner->name ?? 'Unknown' }}
                                            </td>
                                            <td>
                                                @if($hasPrivateConnections)
                                                    <button type="button" class="btn btn-sm btn-warning" 
                                                            onclick="fixConnections('{{ $figure->id }}')">
                                                        <i class="bi bi-check-circle"></i> Fix
                                                    </button>
                                                @else
                                                    <span class="text-muted">No action needed</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-4">
                            <i class="bi bi-people text-muted" style="font-size: 3rem;"></i>
                            <p class="text-muted mt-2">No public figures found.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Hidden form for bulk actions -->
<form id="fix-connections-form" method="POST" action="{{ route('admin.tools.fix-public-figure-connections-action') }}" style="display: none;">
    @csrf
    <input type="hidden" name="figure_ids" id="figure-ids-input">
</form>

<!-- Batch Processing Modal -->
<div class="modal fade" id="batchProcessingModal" tabindex="-1" aria-labelledby="batchProcessingModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="batchProcessingModalLabel">Batch Processing Public Figure Connections</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="batch-setup" style="display: block;">
                    <p>Configure batch processing settings:</p>
                    <div class="mb-3">
                        <label for="batch-size" class="form-label">Batch Size</label>
                        <select class="form-select" id="batch-size">
                            <option value="5">5 figures per batch</option>
                            <option value="10" selected>10 figures per batch</option>
                            <option value="20">20 figures per batch</option>
                            <option value="50">50 figures per batch</option>
                        </select>
                        <div class="form-text">Smaller batches are safer but slower. Larger batches are faster but may timeout on very large datasets.</div>
                    </div>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i>
                        <strong>Batch Processing:</strong> This will process public figures in small batches to prevent timeouts and provide progress feedback.
                    </div>
                </div>
                
                <div id="batch-progress" style="display: none;">
                    <div class="text-center mb-3">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Processing...</span>
                        </div>
                        <h5 class="mt-2" id="progress-title">Processing Batch...</h5>
                    </div>
                    
                    <div class="progress mb-3">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" 
                             role="progressbar" 
                             id="progress-bar" 
                             style="width: 0%">
                            <span id="progress-text">0%</span>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-body text-center">
                                    <h6 class="card-title">Current Batch</h6>
                                    <h4 class="text-primary" id="current-batch">0</h4>
                                    <small class="text-muted">of <span id="total-batches">0</span></small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-body text-center">
                                    <h6 class="card-title">Processed Figures</h6>
                                    <h4 class="text-success" id="processed-figures">0</h4>
                                    <small class="text-muted">of <span id="total-figures">0</span></small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <h6>Recent Activity:</h6>
                        <div id="batch-log" class="border rounded p-2" style="max-height: 200px; overflow-y: auto; background-color: #f8f9fa;">
                            <small class="text-muted">Processing will begin...</small>
                        </div>
                    </div>
                </div>
                
                <div id="batch-complete" style="display: none;">
                    <div class="text-center mb-3">
                        <i class="bi bi-check-circle text-success" style="font-size: 3rem;"></i>
                        <h5 class="mt-2 text-success">Batch Processing Complete!</h5>
                    </div>
                    
                    <div class="alert alert-success">
                        <h6>Summary:</h6>
                        <ul class="mb-0">
                            <li>Total figures processed: <span id="final-processed-figures">0</span></li>
                            <li>Total connections fixed: <span id="final-fixed-connections">0</span></li>
                            <li>Total batches: <span id="final-total-batches">0</span></li>
                        </ul>
                    </div>
                    
                    <div id="batch-errors" style="display: none;">
                        <div class="alert alert-warning">
                            <h6>Errors encountered:</h6>
                            <div id="error-list" class="small"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" id="modal-close-btn">Close</button>
                <button type="button" class="btn btn-primary" id="start-batch-btn" onclick="startBatchProcessingConfirm()">Start Batch Processing</button>
                <button type="button" class="btn btn-warning" id="cancel-batch-btn" style="display: none;" onclick="cancelBatchProcessing()">Cancel</button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
let batchProcessingInterval = null;
let isBatchProcessing = false;

document.addEventListener('DOMContentLoaded', function() {
    // Handle select all checkbox
    const selectAllCheckbox = document.getElementById('select-all');
    const figureCheckboxes = document.querySelectorAll('.figure-checkbox:not(:disabled)');
    
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            figureCheckboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });
    }
    
    // Update select all when individual checkboxes change
    figureCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const checkedCount = document.querySelectorAll('.figure-checkbox:checked').length;
            const totalCount = figureCheckboxes.length;
            
            if (selectAllCheckbox) {
                selectAllCheckbox.checked = checkedCount === totalCount;
                selectAllCheckbox.indeterminate = checkedCount > 0 && checkedCount < totalCount;
            }
        });
    });
});

function fixConnections(figureId) {
    if (confirm('Are you sure you want to make all connections for this public figure public?')) {
        document.getElementById('figure-ids-input').value = figureId;
        document.getElementById('fix-connections-form').submit();
    }
}

function fixAllConnections() {
    const checkedBoxes = document.querySelectorAll('.figure-checkbox:checked');
    const figureIds = Array.from(checkedBoxes).map(cb => cb.value);
    
    if (figureIds.length === 0) {
        alert('Please select at least one public figure to fix.');
        return;
    }
    
    if (confirm(`Are you sure you want to make all connections public for ${figureIds.length} public figure(s)?`)) {
        document.getElementById('figure-ids-input').value = figureIds.join(',');
        document.getElementById('fix-connections-form').submit();
    }
}

function startBatchProcessing() {
    const checkedBoxes = document.querySelectorAll('.figure-checkbox:checked');
    const figureIds = Array.from(checkedBoxes).map(cb => cb.value);
    
    if (figureIds.length === 0) {
        alert('Please select at least one public figure to process.');
        return;
    }
    
    // Show the modal
    const modal = new bootstrap.Modal(document.getElementById('batchProcessingModal'));
    modal.show();
    
    // Store the figure IDs for later use
    window.batchFigureIds = figureIds;
}

function startBatchProcessingConfirm() {
    const batchSize = document.getElementById('batch-size').value;
    const figureIds = window.batchFigureIds;
    
    // Hide setup, show progress
    document.getElementById('batch-setup').style.display = 'none';
    document.getElementById('batch-progress').style.display = 'block';
    document.getElementById('start-batch-btn').style.display = 'none';
    document.getElementById('cancel-batch-btn').style.display = 'inline-block';
    
    // Initialize progress
    document.getElementById('total-figures').textContent = figureIds.length;
    document.getElementById('total-batches').textContent = Math.ceil(figureIds.length / batchSize);
    addBatchLog('Starting batch processing...');
    
    // Start the batch processing
    startBatchProcessingRequest(figureIds, batchSize);
}

function startBatchProcessingRequest(figureIds, batchSize) {
    isBatchProcessing = true;
    
    fetch('{{ route("admin.tools.fix-public-figure-connections-batch-start") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({
            figure_ids: figureIds.join(','),
            batch_size: parseInt(batchSize)
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            addBatchLog(`Batch processing started. Processing ${data.total_figures} figures in ${data.total_batches} batches.`);
            processNextBatch();
        } else {
            addBatchLog(`Error: ${data.message}`, 'error');
            stopBatchProcessing();
        }
    })
    .catch(error => {
        addBatchLog(`Network error: ${error.message}`, 'error');
        stopBatchProcessing();
    });
}

function processNextBatch() {
    if (!isBatchProcessing) return;
    
    fetch('{{ route("admin.tools.fix-public-figure-connections-batch-process") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            updateProgress(data);
            
            if (data.completed) {
                completeBatchProcessing(data);
            } else {
                // Process next batch after a short delay
                setTimeout(processNextBatch, 500);
            }
        } else {
            addBatchLog(`Error: ${data.message}`, 'error');
            stopBatchProcessing();
        }
    })
    .catch(error => {
        addBatchLog(`Network error: ${error.message}`, 'error');
        stopBatchProcessing();
    });
}

function updateProgress(data) {
    // Update progress bar
    const progressBar = document.getElementById('progress-bar');
    const progressText = document.getElementById('progress-text');
    progressBar.style.width = data.progress_percentage + '%';
    progressText.textContent = data.progress_percentage + '%';
    
    // Update counters
    document.getElementById('current-batch').textContent = data.current_batch;
    document.getElementById('processed-figures').textContent = data.processed_figures;
    
    // Update progress title
    document.getElementById('progress-title').textContent = `Processing Batch ${data.current_batch} of ${data.total_batches}`;
    
    // Add log entry
    addBatchLog(`Batch ${data.current_batch}/${data.total_batches} completed. Fixed ${data.batch_fixed_connections} connections.`);
    
    if (data.batch_errors && data.batch_errors.length > 0) {
        data.batch_errors.forEach(error => {
            addBatchLog(`Error: ${error}`, 'error');
        });
    }
}

function completeBatchProcessing(data) {
    isBatchProcessing = false;
    
    // Hide progress, show completion
    document.getElementById('batch-progress').style.display = 'none';
    document.getElementById('batch-complete').style.display = 'block';
    document.getElementById('cancel-batch-btn').style.display = 'none';
    document.getElementById('modal-close-btn').textContent = 'Close & Refresh';
    
    // Update final stats
    document.getElementById('final-processed-figures').textContent = data.total_processed_figures;
    document.getElementById('final-fixed-connections').textContent = data.total_fixed_connections;
    document.getElementById('final-total-batches').textContent = data.total_batches || Math.ceil(window.batchFigureIds.length / parseInt(document.getElementById('batch-size').value));
    
    // Show errors if any
    if (data.errors && data.errors.length > 0) {
        document.getElementById('batch-errors').style.display = 'block';
        const errorList = document.getElementById('error-list');
        errorList.innerHTML = '';
        data.errors.forEach(error => {
            errorList.innerHTML += `<div class="text-danger">• ${error}</div>`;
        });
    }
    
    addBatchLog('Batch processing completed successfully!', 'success');
}

function stopBatchProcessing() {
    isBatchProcessing = false;
    document.getElementById('cancel-batch-btn').style.display = 'none';
    document.getElementById('modal-close-btn').textContent = 'Close';
}

function cancelBatchProcessing() {
    if (confirm('Are you sure you want to cancel batch processing?')) {
        isBatchProcessing = false;
        stopBatchProcessing();
        addBatchLog('Batch processing cancelled by user.', 'warning');
    }
}

function addBatchLog(message, type = 'info') {
    const log = document.getElementById('batch-log');
    const timestamp = new Date().toLocaleTimeString();
    const className = type === 'error' ? 'text-danger' : type === 'success' ? 'text-success' : type === 'warning' ? 'text-warning' : 'text-muted';
    
    log.innerHTML += `<div class="${className}"><small>[${timestamp}] ${message}</small></div>`;
    log.scrollTop = log.scrollHeight;
}

// Handle modal close
document.getElementById('batchProcessingModal').addEventListener('hidden.bs.modal', function () {
    // Reset modal state
    document.getElementById('batch-setup').style.display = 'block';
    document.getElementById('batch-progress').style.display = 'none';
    document.getElementById('batch-complete').style.display = 'none';
    document.getElementById('start-batch-btn').style.display = 'inline-block';
    document.getElementById('cancel-batch-btn').style.display = 'none';
    document.getElementById('modal-close-btn').textContent = 'Close';
    
    // Clear batch processing state
    isBatchProcessing = false;
    window.batchFigureIds = null;
    
    // Refresh page if processing was completed
    if (document.getElementById('batch-complete').style.display === 'block') {
        window.location.reload();
    }
});
</script>
@endpush
@endsection 