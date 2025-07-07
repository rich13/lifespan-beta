@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <h1 class="h3 mb-4">Simple Desert Island Discs Import</h1>
            
            <div class="alert alert-warning" role="alert">
                <strong>Note:</strong> This simplified importer creates placeholders only. No external lookups (MusicBrainz, Wikipedia) are performed.
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Main Content Column -->
        <div class="col-lg-8">
            <!-- CSV Upload Section -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">1. Upload CSV Data</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="csv_data" class="form-label">
                            Paste CSV Data (with headers)
                        </label>
                        <textarea 
                            id="csv_data" 
                            name="csv_data" 
                            rows="10" 
                            class="form-control"
                            placeholder="Castaway,Job,Book,Date first broadcast,Artist 1,Song 1,Artist 2,Song 2,..."
                        >{{ $csvContent }}</textarea>
                    </div>
                </div>
            </div>

            <!-- Preview Section -->
            <div id="preview_section" class="card mb-4 d-none">
                <div class="card-header">
                    <h5 class="card-title mb-0">2. Data Preview</h5>
                </div>
                <div class="card-body">
                    <div id="preview_content"></div>
                </div>
            </div>

            <!-- Dry Run Results -->
            <div id="dry_run_section" class="card mb-4 d-none">
                <div class="card-header">
                    <h5 class="card-title mb-0">3. Dry Run Results</h5>
                </div>
                <div class="card-body">
                    <div id="dry_run_content"></div>
                </div>
            </div>

            <!-- Import Results -->
            <div id="import_results_section" class="card mb-4 d-none">
                <div class="card-header">
                    <h5 class="card-title mb-0">4. Import Results</h5>
                </div>
                <div class="card-body">
                    <div id="import_results_content"></div>
                </div>
            </div>
        </div>

        <!-- Action Buttons Column -->
        <div class="col-lg-4">
            <div class="card sticky-top" style="top: 1rem;">
                <div class="card-header">
                    <h5 class="card-title mb-0">Actions</h5>
                </div>
                <div class="card-body">
                    <!-- Step 1: Preview -->
                    <div class="mb-4">
                        <h6 class="fw-bold mb-2">Step 1: Preview Data</h6>
                        <button 
                            id="preview_btn" 
                            class="btn btn-primary w-100"
                            onclick="previewData()"
                        >
                            <i class="bi bi-eye"></i> Preview Data
                        </button>
                    </div>

                    <!-- Step 2: Row Selection -->
                    <div id="row_selection_section" class="mb-4 d-none">
                        <h6 class="fw-bold mb-2">Step 2: Select Row</h6>
                        <div class="mb-3">
                            <label for="row_number" class="form-label small">
                                Row Number
                            </label>
                            <div class="d-flex align-items-center">
                                <input 
                                    type="number" 
                                    id="row_number" 
                                    name="row_number" 
                                    min="1" 
                                    value="1"
                                    class="form-control"
                                    style="width: 80px;"
                                >
                                <span id="total_rows_display" class="ms-2 text-muted small"></span>
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button 
                                id="dry_run_btn" 
                                class="btn btn-warning"
                                onclick="dryRun()"
                            >
                                <i class="bi bi-search"></i> Dry Run
                            </button>
                            <button 
                                id="import_btn" 
                                class="btn btn-success"
                                onclick="importRow()"
                            >
                                <i class="bi bi-upload"></i> Import Row
                            </button>
                        </div>
                    </div>

                    <!-- Quick Navigation -->
                    <div class="mt-4 pt-3 border-top">
                        <h6 class="fw-bold mb-2">Quick Navigation</h6>
                        <div class="d-grid gap-1">
                            <button 
                                class="btn btn-outline-secondary btn-sm"
                                onclick="scrollToSection('preview_section')"
                            >
                                Preview
                            </button>
                            <button 
                                class="btn btn-outline-secondary btn-sm"
                                onclick="scrollToSection('dry_run_section')"
                            >
                                Dry Run
                            </button>
                            <button 
                                class="btn btn-outline-secondary btn-sm"
                                onclick="scrollToSection('import_results_section')"
                            >
                                Results
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let csvData = '';
let totalRows = 0;

function scrollToSection(sectionId) {
    const section = document.getElementById(sectionId);
    if (section && !section.classList.contains('d-none')) {
        section.scrollIntoView({ behavior: 'smooth' });
    }
}

function previewData() {
    csvData = document.getElementById('csv_data').value.trim();
    if (!csvData) {
        alert('Please enter CSV data');
        return;
    }

    fetch('/admin/import/simple-desert-island-discs/preview', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({ csv_data: csvData })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayPreview(data.preview);
            totalRows = data.total_rows;
            document.getElementById('total_rows_display').textContent = `of ${totalRows}`;
            document.getElementById('row_selection_section').classList.remove('d-none');
            document.getElementById('preview_section').classList.remove('d-none');
            // Scroll to preview section
            setTimeout(() => scrollToSection('preview_section'), 100);
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error previewing data');
    });
}

function displayPreview(preview) {
    const content = document.getElementById('preview_content');
    let html = '<div class="table-responsive"><table class="table table-striped">';
    
    // Headers
    html += '<thead class="table-light">';
    html += '<tr>';
    html += '<th>Row</th>';
    html += '<th>Castaway</th>';
    html += '<th>Job</th>';
    html += '<th>Book</th>';
    html += '<th>Broadcast Date</th>';
    html += '<th>Songs</th>';
    html += '</tr>';
    html += '</thead><tbody>';
    
    preview.forEach(row => {
        html += '<tr>';
        html += `<td>${row.row_number}</td>`;
        html += `<td>${row.castaway}</td>`;
        html += `<td>${row.job}</td>`;
        html += `<td>${row.book}</td>`;
        html += `<td>${row.broadcast_date}</td>`;
        html += `<td>${row.songs_count}</td>`;
        html += '</tr>';
    });
    
    html += '</tbody></table></div>';
    content.innerHTML = html;
}

function dryRun() {
    const rowNumber = document.getElementById('row_number').value;
    if (!rowNumber || rowNumber < 1 || rowNumber > totalRows) {
        alert('Please enter a valid row number');
        return;
    }

    fetch('/admin/import/simple-desert-island-discs/dry-run', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({ 
            csv_data: csvData,
            row_number: parseInt(rowNumber)
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayDryRun(data.dry_run);
            document.getElementById('dry_run_section').classList.remove('d-none');
            // Scroll to dry run section
            setTimeout(() => scrollToSection('dry_run_section'), 100);
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error performing dry run');
    });
}

function displayDryRun(dryRun) {
    const content = document.getElementById('dry_run_content');
    let html = '<div class="row">';
    
    // Castaway
    html += '<div class="col-md-6 mb-3">';
    html += '<div class="border-start border-primary border-4 ps-3">';
    html += '<h6 class="fw-bold">Castaway</h6>';
    html += `<p class="mb-1"><strong>Name:</strong> ${dryRun.castaway.name}</p>`;
    html += `<p class="mb-1"><strong>Job:</strong> ${dryRun.castaway.job || 'No job specified'}</p>`;
    html += `<p class="mb-0"><strong>Action:</strong> <span class="text-primary">${dryRun.castaway.action}</span></p>`;
    html += '</div></div>';
    
    // Book
    if (dryRun.book) {
        html += '<div class="col-md-6 mb-3">';
        html += '<div class="border-start border-success border-4 ps-3">';
        html += '<h6 class="fw-bold">Book</h6>';
        html += `<p class="mb-1"><strong>Title:</strong> ${dryRun.book.title}</p>`;
        html += `<p class="mb-1"><strong>Book Action:</strong> <span class="text-success">${dryRun.book.action}</span></p>`;
        if (dryRun.book.author) {
            html += `<p class="mb-1"><strong>Author:</strong> ${dryRun.book.author}</p>`;
            html += `<p class="mb-0"><strong>Author Action:</strong> <span class="text-success">${dryRun.book.author_action}</span></p>`;
        }
        html += '</div></div>';
    }
    
    // Set
    html += '<div class="col-md-6 mb-3">';
    html += '<div class="border-start border-info border-4 ps-3">';
    html += '<h6 class="fw-bold">Desert Island Discs Set</h6>';
    html += `<p class="mb-1"><strong>Name:</strong> ${dryRun.set.name}</p>`;
    html += `<p class="mb-0"><strong>Action:</strong> <span class="text-info">${dryRun.set.action}</span></p>`;
    html += '</div></div>';
    
    html += '</div>';
    
    // Songs
    if (dryRun.songs.length > 0) {
        html += '<div class="mt-3">';
        html += '<h6 class="fw-bold">Songs</h6>';
        html += '<div class="row">';
        dryRun.songs.forEach(song => {
            html += '<div class="col-md-6 mb-2">';
            html += '<div class="bg-light p-3 rounded">';
            html += `<p class="mb-1"><strong>Position ${song.position}:</strong></p>`;
            html += `<p class="mb-1"><strong>Artist:</strong> ${song.artist.name} <span class="text-warning">(${song.artist.action})</span></p>`;
            html += `<p class="mb-0"><strong>Track:</strong> ${song.track.name} <span class="text-warning">(${song.track.action})</span></p>`;
            html += '</div></div>';
        });
        html += '</div></div>';
    }
    
    // Connections
    if (dryRun.connections && dryRun.connections.length > 0) {
        html += '<div class="mt-4">';
        html += '<h6 class="fw-bold">Connections to be created:</h6>';
        html += '<div class="table-responsive">';
        html += '<table class="table table-sm table-bordered">';
        html += '<thead class="table-light">';
        html += '<tr><th>From</th><th>Type</th><th>To</th><th>Description</th><th>Date</th></tr>';
        html += '</thead><tbody>';
        
        dryRun.connections.forEach(connection => {
            html += '<tr>';
            html += `<td>${connection.from}</td>`;
            html += `<td><span class="badge bg-primary">${connection.type}</span></td>`;
            html += `<td>${connection.to}</td>`;
            html += `<td>${connection.description}</td>`;
            html += `<td>${connection.date || '-'}</td>`;
            html += '</tr>';
        });
        
        html += '</tbody></table>';
        html += '</div></div>';
    }
    
    content.innerHTML = html;
}

function importRow() {
    const rowNumber = document.getElementById('row_number').value;
    if (!rowNumber || rowNumber < 1 || rowNumber > totalRows) {
        alert('Please enter a valid row number');
        return;
    }

    if (!confirm(`Are you sure you want to import row ${rowNumber}? This will create placeholders for all items.`)) {
        return;
    }

    fetch('/admin/import/simple-desert-island-discs/import', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({ 
            csv_data: csvData,
            row_number: parseInt(rowNumber)
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayImportResults(data);
            document.getElementById('import_results_section').classList.remove('d-none');
            // Scroll to results section
            setTimeout(() => scrollToSection('import_results_section'), 100);
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error importing row');
    });
}

function displayImportResults(data) {
    const content = document.getElementById('import_results_content');
    let html = '';
    
    html += '<div class="alert alert-success" role="alert">';
    html += `<strong>Success!</strong> ${data.message}`;
    html += '</div>';
    
    if (data.data) {
        html += '<div class="border-start border-success border-4 ps-3">';
        html += '<h6 class="fw-bold">Created Items:</h6>';
        
        if (data.data.castaway) {
            html += `<p class="mb-1"><strong>Castaway:</strong> ${data.data.castaway.name} (ID: ${data.data.castaway.id})</p>`;
        }
        
        if (data.data.book) {
            html += `<p class="mb-1"><strong>Book:</strong> ${data.data.book.name} (ID: ${data.data.book.id})</p>`;
        }
        
        if (data.data.set) {
            html += `<p class="mb-1"><strong>Set:</strong> ${data.data.set.name} (ID: ${data.data.set.id})</p>`;
        }
        
        if (data.data.songs && data.data.songs.length > 0) {
            html += '<p class="mb-1"><strong>Songs:</strong></p>';
            html += '<ul class="list-unstyled ms-3">';
            data.data.songs.forEach(song => {
                html += `<li>• ${song.artist.name} - ${song.track.name}</li>`;
            });
            html += '</ul>';
        }
        
        html += '</div>';
    }
    
    content.innerHTML = html;
}
</script>
@endsection 