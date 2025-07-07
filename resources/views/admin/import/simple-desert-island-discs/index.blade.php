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
                    <h5 class="card-title mb-0">1. Upload CSV File</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="csv_file" class="form-label">
                            Upload CSV File (max 10MB)
                        </label>
                        <input 
                            type="file" 
                            id="csv_file" 
                            name="csv_file" 
                            class="form-control"
                            accept=".csv,.txt"
                        />
                        <div class="form-text">
                            Upload your CSV file with headers. The file should contain columns like: Castaway, Job, Book, Date first broadcast, Artist 1, Song 1, etc.
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <button type="button" class="btn btn-primary" onclick="uploadCsv()">
                            <i class="bi bi-upload me-2"></i>Upload CSV
                        </button>
                    </div>
                    
                    <div id="upload_status" class="d-none">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            <span id="upload_message">Uploading...</span>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="mb-3">
                        <label class="form-label">
                            Or paste CSV data (for small files only)
                        </label>
                        <textarea 
                            id="csv_data" 
                            name="csv_data" 
                            rows="5" 
                            class="form-control"
                            placeholder="Castaway,Job,Book,Date first broadcast,Artist 1,Song 1,Artist 2,Song 2,..."
                        >{{ $csvContent }}</textarea>
                        <div class="form-text">
                            <strong>Warning:</strong> For large CSV files, use the file upload above to avoid request size limits.
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <button type="button" class="btn btn-secondary" onclick="previewData()">
                            <i class="bi bi-eye me-2"></i>Preview (Text)
                        </button>
                    </div>
                </div>
            </div>

            <!-- File Upload Preview Section -->
            <div id="file_preview_section" class="card mb-4 d-none">
                <div class="card-header">
                    <h5 class="card-title mb-0">2. Data Preview (File Upload)</h5>
                </div>
                <div class="card-body">
                    <div id="file_info" class="mb-3"></div>
                    <div id="file_preview_content"></div>
                    <div class="mt-3">
                        <button type="button" class="btn btn-primary" onclick="loadNextChunk()" id="load_more_btn">
                            <i class="bi bi-arrow-down me-2"></i>Load More Rows
                        </button>
                    </div>
                </div>
            </div>

            <!-- Preview Section -->
            <div id="preview_section" class="card mb-4 d-none">
                <div class="card-header">
                    <h5 class="card-title mb-0">2. Data Preview (Text)</h5>
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

        <!-- Sidebar Column -->
        <div class="col-lg-4">
            <!-- Row Selection Section -->
            <div id="row_selection_section" class="card mb-4 d-none">
                <div class="card-header">
                    <h5 class="card-title mb-0">Row Selection</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="row_number" class="form-label">Row Number</label>
                        <input 
                            type="number" 
                            id="row_number" 
                            name="row_number" 
                            class="form-control" 
                            min="1" 
                            value="1"
                        />
                        <div class="form-text">Showing <span id="total_rows_display">of 0</span> total rows</div>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-info" onclick="dryRun()">
                            <i class="bi bi-search me-2"></i>Dry Run
                        </button>
                        <button type="button" class="btn btn-success" onclick="importRow()">
                            <i class="bi bi-download me-2"></i>Import Row
                        </button>
                    </div>
                </div>
            </div>

            <!-- File Upload Row Selection -->
            <div id="file_row_selection" class="card mb-4 d-none">
                <div class="card-header">
                    <h5 class="card-title mb-0">Row Selection (File Upload)</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="file_row_number" class="form-label">Row Number</label>
                        <input 
                            type="number" 
                            id="file_row_number" 
                            name="file_row_number" 
                            class="form-control" 
                            min="1" 
                            value="1"
                        />
                        <div class="form-text">Showing <span id="file_total_rows_display">of 0</span> total rows</div>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-info" onclick="dryRunFile()">
                            <i class="bi bi-search me-2"></i>Dry Run
                        </button>
                        <button type="button" class="btn btn-success" onclick="importFileRow()">
                            <i class="bi bi-download me-2"></i>Import Row
                        </button>
                    </div>
                </div>
            </div>

            <!-- Help Section -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Help</h5>
                </div>
                <div class="card-body">
                    <h6>CSV Format</h6>
                    <p class="small">Your CSV should have these columns:</p>
                    <ul class="small">
                        <li><strong>Castaway</strong> - Name of the person</li>
                        <li><strong>Job</strong> - Their profession</li>
                        <li><strong>Book</strong> - Their chosen book</li>
                        <li><strong>Date first broadcast</strong> - Broadcast date</li>
                        <li><strong>Artist 1-8</strong> - Artist names</li>
                        <li><strong>Song 1-8</strong> - Song titles</li>
                    </ul>
                    
                    <h6>Process</h6>
                    <ol class="small">
                        <li>Upload your CSV file</li>
                        <li>Preview the data</li>
                        <li>Select a row to import</li>
                        <li>Run a dry run to see what will be created</li>
                        <li>Import the row</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let csvData = '';
let totalRows = 0;
let currentChunk = 1;
let chunkSize = 10;
let hasMoreChunks = true;

function scrollToSection(sectionId) {
    const section = document.getElementById(sectionId);
    if (section && !section.classList.contains('d-none')) {
        section.scrollIntoView({ behavior: 'smooth' });
    }
}

function uploadCsv() {
    const fileInput = document.getElementById('csv_file');
    const file = fileInput.files[0];
    
    if (!file) {
        alert('Please select a CSV file');
        return;
    }
    
    const formData = new FormData();
    formData.append('csv_file', file);
    
    // Show upload status
    document.getElementById('upload_status').classList.remove('d-none');
    document.getElementById('upload_message').textContent = 'Uploading...';
    
    fetch('/admin/import/simple-desert-island-discs/upload', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('upload_message').textContent = `Uploaded: ${data.filename} (${data.total_rows} rows)`;
            document.getElementById('upload_status').classList.remove('alert-info');
            document.getElementById('upload_status').classList.add('alert-success');
            
            // Load first chunk
            loadFirstChunk();
        } else {
            document.getElementById('upload_message').textContent = 'Error: ' + data.message;
            document.getElementById('upload_status').classList.remove('alert-info');
            document.getElementById('upload_status').classList.add('alert-danger');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        document.getElementById('upload_message').textContent = 'Upload failed';
        document.getElementById('upload_status').classList.remove('alert-info');
        document.getElementById('upload_status').classList.add('alert-danger');
    });
}

function loadFirstChunk() {
    currentChunk = 1;
    loadChunk();
}

function loadNextChunk() {
    currentChunk++;
    loadChunk();
}

function loadChunk() {
    const startRow = (currentChunk - 1) * chunkSize + 1;
    
    fetch('/admin/import/simple-desert-island-discs/preview-chunk', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({ 
            start_row: startRow,
            chunk_size: chunkSize
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayFileInfo(data);
            displayFilePreview(data.preview, data);
            totalRows = data.total_rows;
            hasMoreChunks = data.has_more;
            
            // Show/hide load more button
            const loadMoreBtn = document.getElementById('load_more_btn');
            if (hasMoreChunks) {
                loadMoreBtn.classList.remove('d-none');
                loadMoreBtn.textContent = `Load More Rows (${data.end_row + 1}-${Math.min(data.end_row + chunkSize, totalRows)})`;
            } else {
                loadMoreBtn.classList.add('d-none');
            }
            
            document.getElementById('file_preview_section').classList.remove('d-none');
            document.getElementById('file_row_selection').classList.remove('d-none');
            document.getElementById('file_total_rows_display').textContent = `of ${totalRows}`;
            
            // Scroll to preview section
            setTimeout(() => scrollToSection('file_preview_section'), 100);
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error loading preview');
    });
}

function displayFileInfo(data) {
    const infoDiv = document.getElementById('file_info');
    infoDiv.innerHTML = `
        <div class="alert alert-info">
            <strong>File:</strong> ${data.filename || 'Unknown'}<br>
            <strong>Total Rows:</strong> ${data.total_rows}<br>
            <strong>Showing:</strong> Rows ${data.start_row}-${data.end_row}
        </div>
    `;
}

function displayFilePreview(preview, data) {
    const content = document.getElementById('file_preview_content');
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

function dryRunFile() {
    const rowNumber = document.getElementById('file_row_number').value;
    if (!rowNumber || rowNumber < 1 || rowNumber > totalRows) {
        alert('Please enter a valid row number');
        return;
    }

    fetch('/admin/import/simple-desert-island-discs/dry-run-chunk', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({ 
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

function importFileRow() {
    const rowNumber = document.getElementById('file_row_number').value;
    if (!rowNumber || rowNumber < 1 || rowNumber > totalRows) {
        alert('Please enter a valid row number');
        return;
    }

    if (!confirm(`Are you sure you want to import row ${rowNumber}? This will create placeholders for all items.`)) {
        return;
    }

    fetch('/admin/import/simple-desert-island-discs/import-chunk', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({ 
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