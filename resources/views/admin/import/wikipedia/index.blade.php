@extends('layouts.app')

@section('title', 'Wikipedia Import')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <!-- Breadcrumb Navigation -->
            <nav aria-label="breadcrumb" class="mb-3">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Wikipedia Import</li>
                </ol>
            </nav>
            
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">
                    <i class="bi bi-wikipedia me-2"></i>
                    Wikipedia Import
                </h1>
                <div class="d-flex align-items-center gap-3">
                    <button id="autoImportBtn" class="btn btn-primary" onclick="startAutoImport()">
                        <i class="bi bi-magic me-2"></i>Auto Import All
                    </button>
                    <button class="btn btn-outline-secondary" onclick="refreshStats()">
                        <i class="bi bi-arrow-clockwise me-1"></i>
                        Refresh Stats
                    </button>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body text-center">
                            <h4 class="card-title text-primary" id="totalPublicFigures">{{ $totalPublicFigures }}</h4>
                            <p class="card-text text-muted">Total Public Figures</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body text-center">
                            <h4 class="card-title text-success" id="withDescriptions">{{ $publicFiguresWithDescriptions }}</h4>
                            <p class="card-text text-muted">With Descriptions</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body text-center">
                            <h4 class="card-title text-info" id="withWikipediaSources">{{ $publicFiguresWithWikipediaSources }}</h4>
                            <p class="card-text text-muted">With Wikipedia Sources</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body text-center">
                            <h4 class="card-title text-warning" id="withoutDescriptions">{{ $totalPublicFigures - $publicFiguresWithDescriptions }}</h4>
                            <p class="card-text text-muted">Without Descriptions</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Public Figures Table -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-table me-2"></i>
                        Public Figures Needing Improvement
                        <span class="badge bg-primary ms-2">{{ $publicFigures->total() }}</span>
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Description</th>
                                    <th>Sources</th>
                                    <th>Dates</th>
                                    <th>State</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($publicFigures as $person)
                                    <tr data-span-id="{{ $person->id }}">
                                        <td>
                                            <a href="{{ route('spans.show', $person) }}" target="_blank" class="text-decoration-none">
                                                {{ $person->name }}
                                            </a>
                                        </td>
                                        <td>
                                            @if($person->description)
                                                <span class="badge bg-success">
                                                    <i class="bi bi-check-circle me-1"></i>
                                                    Has Description
                                                </span>
                                            @else
                                                <span class="badge bg-warning">
                                                    <i class="bi bi-exclamation-triangle me-1"></i>
                                                    No Description
                                                </span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($person->sources && count($person->sources) > 0)
                                                @php
                                                    $hasWikipedia = false;
                                                    foreach($person->sources as $source) {
                                                        if (is_string($source) && strpos($source, 'wikipedia.org') !== false) {
                                                            $hasWikipedia = true;
                                                            break;
                                                        } elseif (is_array($source) && isset($source['url']) && strpos($source['url'], 'wikipedia.org') !== false) {
                                                            $hasWikipedia = true;
                                                            break;
                                                        }
                                                    }
                                                @endphp
                                                @if($hasWikipedia)
                                                    <span class="badge bg-success">
                                                        <i class="bi bi-wikipedia me-1"></i>
                                                        Has Wikipedia
                                                    </span>
                                                @else
                                                    <span class="badge bg-info">
                                                        <i class="bi bi-link-45deg me-1"></i>
                                                        Has Sources ({{ count($person->sources) }})
                                                    </span>
                                                @endif
                                            @else
                                                <span class="badge bg-secondary">
                                                    <i class="bi bi-x-circle me-1"></i>
                                                    No Sources
                                                </span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($person->start_year)
                                                @php
                                                    $has01_01Problem = ($person->start_month === 1 && $person->start_day === 1) || 
                                                                      ($person->end_month === 1 && $person->end_day === 1);
                                                @endphp
                                                <div class="small">
                                                    @if($has01_01Problem)
                                                        <span class="badge bg-warning mb-1">
                                                            <i class="bi bi-exclamation-triangle me-1"></i>
                                                            Needs Improvement
                                                        </span>
                                                    @endif
                                                    <div><strong>Born:</strong> {{ $person->formatted_start_date }}</div>
                                                    @if($person->start_precision && $person->start_precision !== 'year')
                                                        <small class="text-muted">({{ $person->start_precision }} precision)</small>
                                                    @endif
                                                    @if($person->end_year)
                                                        <div><strong>Died:</strong> {{ $person->formatted_end_date }}</div>
                                                        @if($person->end_precision && $person->end_precision !== 'year')
                                                            <small class="text-muted">({{ $person->end_precision }} precision)</small>
                                                        @endif
                                                    @else
                                                        <div class="text-success"><small>✓ Alive</small></div>
                                                    @endif
                                                </div>
                                            @else
                                                <span class="badge bg-warning">
                                                    <i class="bi bi-calendar-x me-1"></i>
                                                    No Dates
                                                </span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($person->state)
                                                <span class="badge bg-outline-secondary">{{ $person->state }}</span>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        <td>
                                            @php
                                                $has01_01Problem = ($person->start_month === 1 && $person->start_day === 1) || 
                                                                  ($person->end_month === 1 && $person->end_day === 1);
                                            @endphp
                                            @if(!$person->description)
                                                <button class="btn btn-sm btn-primary process-person-btn" 
                                                        onclick="processPerson('{{ $person->id }}', '{{ $person->name }}')"
                                                        data-span-id="{{ $person->id }}">
                                                    <i class="bi bi-cloud-download me-1"></i>
                                                    Import
                                                </button>
                                            @elseif($has01_01Problem)
                                                <button class="btn btn-sm btn-warning process-person-btn" 
                                                        onclick="processPerson('{{ $person->id }}', '{{ $person->name }}')"
                                                        data-span-id="{{ $person->id }}">
                                                    <i class="bi bi-calendar-check me-1"></i>
                                                    Improve Dates
                                                </button>
                                            @elseif($person->sources && count($person->sources) > 0)
                                                @php
                                                    $hasWikipedia = false;
                                                    foreach($person->sources as $source) {
                                                        if (is_string($source) && strpos($source, 'url') !== false) {
                                                            $hasWikipedia = true;
                                                            break;
                                                        } elseif (is_array($source) && isset($source['url']) && strpos($source['url'], 'wikipedia.org') !== false) {
                                                            $hasWikipedia = true;
                                                            break;
                                                        }
                                                    }
                                                @endphp
                                                @if(!$hasWikipedia)
                                                    <button class="btn btn-sm btn-info process-person-btn" 
                                                            onclick="processPerson('{{ $person->id }}', '{{ $person->name }}')"
                                                            data-span-id="{{ $person->id }}">
                                                        <i class="bi bi-link-45deg me-1"></i>
                                                        Add Source
                                                    </button>
                                                @else
                                                    <span class="text-muted small">Complete</span>
                                                @endif
                                            @else
                                                <button class="btn btn-sm btn-info process-person-btn" 
                                                        onclick="processPerson('{{ $person->id }}', '{{ $person->name }}')"
                                                        data-span-id="{{ $person->id }}">
                                                    <i class="bi bi-link-45deg me-1"></i>
                                                    Add Source
                                                </button>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <div class="d-flex justify-content-center mt-4">
                        <x-pagination :paginator="$publicFigures" :showInfo="true" itemName="public figures" />
                    </div>
                </div>
            </div>

            <!-- Processing Log -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-list-check me-2"></i>
                                Processing Log
                            </h5>
                        </div>
                        <div class="card-body">
                            <div id="processingLog" class="small" style="max-height: 300px; overflow-y: auto;">
                                <p class="text-muted">Processing log will appear here...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Processing Modal -->
<div class="modal fade" id="processingModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Processing Wikipedia Import</h5>
            </div>
            <div class="modal-body text-center">
                <div class="spinner-border text-primary mb-3" role="status">
                    <span class="visually-hidden">Processing...</span>
                </div>
                <p id="processingMessage">Processing <span id="processingPersonName"></span>...</p>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
let processingQueue = [];
let isProcessing = false;

function addToLog(message, type = 'info') {
    const log = document.getElementById('processingLog');
    const timestamp = new Date().toLocaleTimeString();
    const alertClass = type === 'success' ? 'alert-success' : type === 'error' ? 'alert-danger' : 'alert-info';
    
    const logEntry = document.createElement('div');
    logEntry.className = `alert ${alertClass} alert-sm mb-2`;
    logEntry.innerHTML = `<small>[${timestamp}] ${message}</small>`;
    
    log.appendChild(logEntry);
    log.scrollTop = log.scrollHeight;
}

function refreshStats() {
    fetch('/admin/import/wikipedia/stats')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('totalPublicFigures').textContent = data.stats.total_public_figures;
                document.getElementById('withDescriptions').textContent = data.stats.with_descriptions;
                document.getElementById('withWikipediaSources').textContent = data.stats.with_wikipedia_sources;
                document.getElementById('withoutDescriptions').textContent = data.stats.without_descriptions;
                
                addToLog('Stats refreshed successfully', 'success');
            }
        })
        .catch(error => {
            addToLog('Failed to refresh stats: ' + error.message, 'error');
        });
}

function processPerson(spanId, personName, button = null) {
    // If no button provided, find it
    if (!button) {
        button = document.querySelector(`button[data-span-id="${spanId}"].process-person-btn`);
    }
    
    // Check if this item is already being processed
    if (processingItems.has(spanId)) {
        addToLog(`⚠️ ${personName}: Already being processed, skipping...`, 'warning');
        return;
    }
    
    // Mark this item as being processed
    processingItems.add(spanId);
    
    // Disable the button
    if (button) {
        button.disabled = true;
        button.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Processing...';
    }
    
    // Only show modal if not in auto-import mode
    let modal = null;
    if (!isAutoImporting) {
        document.getElementById('processingPersonName').textContent = personName;
        modal = new bootstrap.Modal(document.getElementById('processingModal'));
        modal.show();
    }
    
    addToLog(`Processing ${personName}...`);
    addToLog(`🔍 Making request to: /admin/import/wikipedia/process-person`, 'info');
    
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    if (!csrfToken) {
        addToLog(`❌ ${personName}: CSRF token not found`, 'error');
        return;
    }
    
    addToLog(`🔍 CSRF token found: ${csrfToken.substring(0, 10)}...`, 'info');
    
    fetch('/admin/import/wikipedia/process-person', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify({ span_id: spanId })
    })
    .then(response => response.json())
    .then(data => {
        // Only hide modal if not in auto-import mode
        if (!isAutoImporting) {
            modal.hide();
        }
        
        if (data.success) {
            addToLog(`✅ ${personName}: ${data.message}`, 'success');
            
            // Remove from processing set
            processingItems.delete(spanId);
            
            // Remove the completed row from the table
            const tableRow = document.querySelector(`tr[data-span-id="${spanId}"]`);
            if (tableRow) {
                // Add a brief fade-out effect
                tableRow.style.transition = 'opacity 0.5s ease-out';
                tableRow.style.opacity = '0.5';
                
                setTimeout(() => {
                    tableRow.remove();
                    
                    // Check if table is now empty
                    const tbody = tableRow.closest('tbody');
                    if (tbody && tbody.children.length === 0) {
                        // Add a "All done!" message
                        const emptyMessage = document.createElement('tr');
                        emptyMessage.innerHTML = `
                            <td colspan="6" class="text-center py-4">
                                <div class="text-success">
                                    <i class="bi bi-check-circle-fill me-2"></i>
                                    All public figures have been enhanced!
                                </div>
                            </td>
                        `;
                        tbody.appendChild(emptyMessage);
                    }
                }, 500);
            }
            
            // Update counts
            refreshStats();
            
            // If in auto-import mode, continue with next item
            if (isAutoImporting) {
                setTimeout(() => {
                    processNextInQueue();
                }, 2000); // 2 second delay between items
            }
            
        } else {
            addToLog(`❌ ${personName}: ${data.message}`, 'error');
            
            // Remove from processing set
            processingItems.delete(spanId);
            
            // If in auto-import mode, automatically skip failed imports
            if (isAutoImporting) {
                addToLog(`🔄 Auto-skipping ${personName} due to import failure...`, 'info');
                // Automatically trigger skip after a short delay
                setTimeout(() => {
                    addToLog(`⏭️ Auto-skipping ${personName} now...`, 'info');
                    skipPerson(spanId, personName);
                    
                    // Continue with next item after skip completes
                    setTimeout(() => {
                        processNextInQueue();
                    }, 2000); // 2 second delay between items
                }, 1500);
            } else {
                // Show skip button after failed import (manual mode)
                const tableRow = document.querySelector(`tr[data-span-id="${spanId}"]`);
                if (tableRow) {
                    const actionsCell = tableRow.querySelector('td:nth-child(6)');
                    if (actionsCell) {
                        actionsCell.innerHTML = `
                            <div class="btn-group" role="group">
                                <button class="btn btn-sm btn-outline-danger" disabled>
                                    <i class="bi bi-exclamation-triangle me-1"></i>
                                    Import Failed
                                </button>
                                <button class="btn btn-sm btn-outline-secondary skip-person-btn" 
                                        onclick="skipPerson('${spanId}', '${personName}')"
                                        data-span-id="${spanId}"
                                        title="Skip this person (not found on Wikipedia)">
                                    <i class="bi bi-skip-forward me-1"></i>
                                    Skip
                                </button>
                            </div>
                        `;
                    }
                }
            }
        }
    })
    .catch(error => {
        // Remove from processing set
        processingItems.delete(spanId);
        
        // Only hide modal if not in auto-import mode and modal exists
        if (!isAutoImporting && modal) {
            modal.hide();
        }
        
        // Log more detailed error information
        console.error('Fetch error details:', error);
        addToLog(`❌ ${personName}: Network error - ${error.message}`, 'error');
        
        // Log the URL being requested
        addToLog(`🔍 Request URL: /admin/import/wikipedia/process-person`, 'info');
        
        // If it's a 500 error, the item might not exist anymore
        if (error.message.includes('500') || error.message.includes('Internal Server Error')) {
            addToLog(`⚠️ ${personName}: Server error - item may have been removed`, 'warning');
        }
        
        // If in auto-import mode, automatically skip failed imports
        if (isAutoImporting) {
            addToLog(`🔄 Auto-skipping ${personName} due to network error...`, 'info');
            // Automatically trigger skip after a short delay
            setTimeout(() => {
                addToLog(`⏭️ Auto-skipping ${personName} now...`, 'info');
                skipPerson(spanId, personName);
                
                // Continue with next item after skip completes
                setTimeout(() => {
                    processNextInQueue();
                }, 2000); // 2 second delay between items
            }, 1500);
        } else {
            // Re-enable the button (manual mode)
            if (button) {
                button.disabled = false;
                button.innerHTML = '<i class="bi bi-cloud-download me-1"></i>Import';
            }
        }
    });
}

function skipPerson(spanId, personName) {
    // Remove from processing set if it was being processed
    processingItems.delete(spanId);
    
    // Disable the button
    const button = document.querySelector(`button[data-span-id="${spanId}"].skip-person-btn`);
    if (button) {
        button.disabled = true;
        button.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Skipping...';
    }
    
    addToLog(`Skipping ${personName} (not found on Wikipedia)...`);
    
    fetch('/admin/import/wikipedia/skip-person', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({ span_id: spanId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            addToLog(`⏭️ ${personName}: Skipped (not found on Wikipedia)`, 'info');
            
            // Remove the skipped row from the table
            const tableRow = document.querySelector(`tr[data-span-id="${spanId}"]`);
            if (tableRow) {
                // Add a brief fade-out effect
                tableRow.style.transition = 'opacity 0.5s ease-out';
                tableRow.style.opacity = '0.5';
                
                setTimeout(() => {
                    tableRow.remove();
                    
                    // Check if table is now empty
                    const tbody = tableRow.closest('tbody');
                    if (tbody && tbody.children.length === 0) {
                        // Add a "All done!" message
                        const emptyMessage = document.createElement('tr');
                        emptyMessage.innerHTML = `
                            <td colspan="6" class="text-center py-4">
                                <div class="text-success">
                                    <i class="bi bi-check-circle-fill me-2"></i>
                                    All public figures have been processed!
                                </div>
                            </td>
                        `;
                        tbody.appendChild(emptyMessage);
                    }
                }, 500);
            }
            
            // Update counts
            refreshStats();
            
            // If in auto-import mode, continue with next item
            if (isAutoImporting) {
                setTimeout(() => {
                    processNextInQueue();
                }, 2000); // 2 second delay between items
            }
            
        } else {
            addToLog(`❌ ${personName}: Failed to skip - ${data.message}`, 'error');
            
            // Re-enable the button
            if (button) {
                button.disabled = false;
                button.innerHTML = '<i class="bi bi-skip-forward me-1"></i>Skip';
            }
        }
    })
    .catch(error => {
        addToLog(`❌ ${personName}: Network error - ${error.message}`, 'error');
        
        // Re-enable the button
        if (button) {
            button.disabled = false;
            button.innerHTML = '<i class="bi bi-skip-forward me-1"></i>Skip';
        }
    });
}

// Auto-refresh stats every 30 seconds
setInterval(refreshStats, 30000);

// Auto-import functionality
let isAutoImporting = false;
let autoImportQueue = [];
let processingItems = new Set(); // Track items currently being processed

function startAutoImport() {
    if (isAutoImporting) {
        addToLog('⚠️ Auto-import already running, ignoring start request', 'warning');
        return; // Already running
    }
    
    // Get all import buttons on the current page
    const importButtons = document.querySelectorAll('button[data-span-id].process-person-btn');
    
    if (importButtons.length === 0) {
        addToLog('No items to import on this page.', 'warning');
        return;
    }
    
    // Build queue of items to process
    autoImportQueue = Array.from(importButtons).map(button => ({
        spanId: button.getAttribute('data-span-id'),
        personName: button.closest('tr').querySelector('td:first-child').textContent.trim(),
        button: button
    }));
    
    // Log the queue for debugging
    addToLog(`🔍 Built queue with ${autoImportQueue.length} items:`, 'info');
    autoImportQueue.forEach((item, index) => {
        addToLog(`  ${index + 1}. ${item.personName} (${item.spanId})`, 'info');
    });
    
    isAutoImporting = true;
    const autoImportBtn = document.getElementById('autoImportBtn');
    autoImportBtn.disabled = true; // Disable immediately to prevent multiple clicks
    autoImportBtn.innerHTML = '<i class="bi bi-stop me-2"></i>Stop Auto Import';
    autoImportBtn.onclick = stopAutoImport;
    
    addToLog(`🚀 Starting auto-import of ${autoImportQueue.length} items...`, 'info');
    addToLog(`🔍 Auto-import state: isAutoImporting=${isAutoImporting}`, 'info');
    
    // Start processing the queue
    processNextInQueue();
}

function processNextInQueue() {
    if (autoImportQueue.length === 0) {
        // All done!
        isAutoImporting = false;
        const autoImportBtn = document.getElementById('autoImportBtn');
        autoImportBtn.disabled = false;
        autoImportBtn.innerHTML = '<i class="bi bi-magic me-2"></i>Auto Import All';
        autoImportBtn.onclick = startAutoImport;
        
        addToLog('✅ Auto-import completed!', 'success');
        return;
    }
    
    // Check if there are any remaining import buttons on the page
    const remainingButtons = document.querySelectorAll('button[data-span-id].process-person-btn');
    if (remainingButtons.length === 0) {
        // No more buttons on the page, we're done
        isAutoImporting = false;
        const autoImportBtn = document.getElementById('autoImportBtn');
        autoImportBtn.disabled = false;
        autoImportBtn.innerHTML = '<i class="bi bi-magic me-2"></i>Auto Import All';
        autoImportBtn.onclick = startAutoImport;
        
        addToLog('✅ Auto-import completed! (No more items on page)', 'success');
        return;
    }
    
    const item = autoImportQueue.shift();
    const { spanId, personName, button } = item;
    
    // Check if the button still exists (in case it was removed by a skip)
    const currentButton = document.querySelector(`button[data-span-id="${spanId}"].process-person-btn`);
    if (!currentButton) {
        // Button was removed (probably skipped), continue with next item
        addToLog(`⏭️ ${personName}: Already processed/skipped, continuing...`, 'info');
        setTimeout(() => {
            processNextInQueue();
        }, 500); // Shorter delay for skipped items
        return;
    }
    
    // Check if the table row still exists
    const tableRow = document.querySelector(`tr[data-span-id="${spanId}"]`);
    if (!tableRow) {
        // Row was removed, continue with next item
        addToLog(`⏭️ ${personName}: Row removed, continuing...`, 'info');
        setTimeout(() => {
            processNextInQueue();
        }, 500);
        return;
    }
    
    // Check if this item is already being processed (double-check)
    if (processingItems.has(spanId)) {
        addToLog(`⚠️ ${personName}: Already being processed, skipping...`, 'warning');
        setTimeout(() => {
            processNextInQueue();
        }, 500);
        return;
    }
    
    const totalItems = autoImportQueue.length + 1;
    const processedItems = totalItems - autoImportQueue.length;
    addToLog(`🔄 Processing ${personName} (${processedItems}/${totalItems}) - ID: ${spanId}`, 'info');
    
    // Update button text to show progress
    const autoImportBtn = document.getElementById('autoImportBtn');
    autoImportBtn.innerHTML = `<i class="bi bi-stop me-2"></i>Stop Auto Import (${processedItems}/${totalItems})`;
    
    // Simulate clicking the import button and wait for completion
    processPerson(spanId, personName, currentButton);
    
    // Note: processNextInQueue() will be called from within processPerson() 
    // after the request completes (either success or error)
}

function stopAutoImport() {
    isAutoImporting = false;
    autoImportQueue = [];
    processingItems.clear(); // Clear any items being processed
    
    const autoImportBtn = document.getElementById('autoImportBtn');
    autoImportBtn.disabled = false;
    autoImportBtn.innerHTML = '<i class="bi bi-magic me-2"></i>Auto Import All';
    autoImportBtn.onclick = startAutoImport;
    
    addToLog('⏹️ Auto-import stopped.', 'warning');
}
</script>
@endpush
