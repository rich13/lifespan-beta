@extends('layouts.app')

@section('page_title')
    Import from Wikimedia Commons
@endsection

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">
        <i class="bi bi-images me-2"></i>
        Import from Wikimedia Commons
    </h1>
    <button class="btn btn-outline-secondary btn-sm" onclick="clearCache()">
        <i class="bi bi-arrow-clockwise me-1"></i>
        Clear Cache
    </button>
</div>

<div class="row">
    <!-- Search Panel -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-search me-2"></i>
                    Search Images
                </h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label for="searchQuery" class="form-label">Search by person name or keywords</label>
                    <input type="text" class="form-control" id="searchQuery" placeholder="e.g., Paul McCartney, Beatles, London" value="{{ $initialSearch }}">
                </div>
                
                <div class="mb-3">
                    <label for="searchYear" class="form-label">Year (optional)</label>
                    <input type="number" class="form-control" id="searchYear" placeholder="e.g., 1965" min="1800" max="{{ date('Y') + 1 }}">
                    <small class="text-muted">Leave empty to search all years</small>
                </div>
                
                <button class="btn btn-primary w-100" onclick="searchImages()">
                    <i class="bi bi-search me-1"></i>
                    Search
                </button>
            </div>
        </div>

        <!-- Search Results -->
        <div class="card mt-3" id="searchResultsCard" style="display: none;">
            <div class="card-header">
                <h6 class="card-title mb-0">Search Results</h6>
            </div>
            <div class="card-body p-0">
                <div id="searchResults" class="list-group list-group-flush">
                    <!-- Results will be populated here -->
                </div>
                <div id="searchPagination" class="card-footer">
                    <!-- Pagination will be populated here -->
                </div>
            </div>
        </div>
    </div>

                   <!-- Image Details Panel -->
               <div class="col-md-8">
                   <div class="card" id="imageDetailsCard" style="display: none;">
                       <div class="card-header">
                           <h5 class="card-title mb-0">
                               <i class="bi bi-image me-2"></i>
                               Image Details
                           </h5>
                       </div>
                       <div class="card-body">
                           <div id="imageDetails">
                               <!-- Image details will be populated here -->
                           </div>
                       </div>
                   </div>

                   <!-- Import Panel -->
                   <div class="card mt-3" id="importPanel" style="display: none;">
                       <div class="card-header">
                           <h5 class="card-title mb-0">
                               <i class="bi bi-download me-2"></i>
                               Import Image
                           </h5>
                       </div>
                       <div class="card-body">
                           <div class="mb-3">
                               <label for="targetSpanId" class="form-label">Connect to Span (Optional)</label>
                               <select class="form-select" id="targetSpanId">
                                   <option value="">No connection</option>
                               </select>
                               <small class="text-muted">This image will be connected to the selected span via a 'features' connection</small>
                           </div>
                           
                           <!-- Hidden input to store originating span details -->
                           <input type="hidden" id="originatingSpanId" value="{{ $originatingSpanId }}">
                           <input type="hidden" id="originatingSpanName" value="{{ $originatingSpanName }}">
                           <input type="hidden" id="originatingSpanUuid" value="{{ $originatingSpanUuid }}">
                           
                           <div id="importPreview" class="mb-3">
                               <!-- Import preview will be shown here -->
                           </div>
                           
                           <button class="btn btn-success" onclick="importImage()" id="importButton">
                               <i class="bi bi-download me-1"></i>
                               Import Image
                           </button>
                       </div>
                   </div>
               </div>
</div>

<!-- Loading Overlay -->
<div id="loadingOverlay" class="position-fixed top-0 start-0 w-100 h-100 d-none" style="background: rgba(0,0,0,0.5); z-index: 9999;">
    <div class="d-flex justify-content-center align-items-center h-100">
        <div class="bg-white rounded p-4 text-center">
            <div class="spinner-border text-primary mb-3" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mb-0" id="loadingMessage">Loading...</p>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
let currentSearchResults = [];
let currentImageData = null;
let currentPage = 1;
let totalPages = 1;

// Search for images
function searchImages() {
    const query = document.getElementById('searchQuery').value.trim();
    const year = document.getElementById('searchYear').value.trim();
    
    if (!query) {
        alert('Please enter a search query');
        return;
    }
    
    showLoading('Searching for images...');
    
    const searchData = {
        query: query,
        page: 1,
        per_page: 20
    };
    
    if (year) {
        searchData.year = parseInt(year);
    }
    
    const url = year ? '/admin/import/wikimedia-commons/search-by-year' : '/admin/import/wikimedia-commons/search';
    
    fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify(searchData)
    })
    .then(response => {
        console.log('Search response status:', response.status);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        console.log('Search response data:', data);
        console.log('About to call hideLoading()');
        hideLoading();
        console.log('hideLoading() called');
        
        if (data.success) {
            currentSearchResults = data.data.images;
            currentPage = data.data.page;
            totalPages = Math.ceil(data.data.total / data.data.per_page);
            
            displaySearchResults();
            showSearchResults();
        } else {
            alert('Search failed: ' + (data.message || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Search error:', error);
        hideLoading();
        alert('Search failed: ' + error.message);
    });
}

// Display search results
function displaySearchResults() {
    const container = document.getElementById('searchResults');
    container.innerHTML = '';
    
    if (currentSearchResults.length === 0) {
        container.innerHTML = '<div class="list-group-item text-center text-muted">No images found</div>';
        return;
    }
    
    currentSearchResults.forEach(image => {
        const item = document.createElement('div');
        item.className = 'list-group-item list-group-item-action';
        item.onclick = () => selectImage(image);
        
        // Extract image name from title
        const imageName = image.title.replace('File:', '').replace(/\.[^/.]+$/, '').replace(/_/g, ' ');
        
        item.innerHTML = `
            <div class="d-flex justify-content-between align-items-start">
                <div class="flex-grow-1">
                    <h6 class="mb-1">${imageName}</h6>
                    <small class="text-muted">${image.snippet}</small>
                </div>
            </div>
        `;
        
        container.appendChild(item);
    });
    
    // Add pagination
    displayPagination();
}

// Display pagination
function displayPagination() {
    const container = document.getElementById('searchPagination');
    
    if (totalPages <= 1) {
        container.innerHTML = '';
        return;
    }
    
    let paginationHtml = '<div class="d-flex justify-content-between align-items-center">';
    
    if (currentPage > 1) {
        paginationHtml += `<button class="btn btn-sm btn-outline-primary" onclick="changePage(${currentPage - 1})">Previous</button>`;
    } else {
        paginationHtml += '<button class="btn btn-sm btn-outline-secondary" disabled>Previous</button>';
    }
    
    paginationHtml += `<span class="text-muted">Page ${currentPage} of ${totalPages}</span>`;
    
    if (currentPage < totalPages) {
        paginationHtml += `<button class="btn btn-sm btn-outline-primary" onclick="changePage(${currentPage + 1})">Next</button>`;
    } else {
        paginationHtml += '<button class="btn btn-sm btn-outline-secondary" disabled>Next</button>';
    }
    
    paginationHtml += '</div>';
    container.innerHTML = paginationHtml;
}

// Change page
function changePage(page) {
    currentPage = page;
    searchImages();
}

// Select an image
function selectImage(image) {
    showLoading('Loading image details...');
    
    // Get CSRF token
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    if (!csrfToken) {
        hideLoading();
        alert('CSRF token not found. Please refresh the page and try again.');
        return;
    }
    
    const requestBody = {
        image_id: String(image.id)
    };
    
    fetch('/admin/import/wikimedia-commons/get-image-data', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(requestBody)
    })
    .then(response => {
        console.log('Response status:', response.status);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        console.log('Image data received:', data);
        hideLoading();
        
        if (data.success) {
            currentImageData = data.data;
            displayImageDetails();
            showImageDetails();
            previewImport();
        } else {
            alert('Failed to load image details: ' + (data.message || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error loading image details:', error);
        console.error('Error details:', {
            message: error.message,
            stack: error.stack
        });
        hideLoading();
        alert('Failed to load image details: ' + error.message);
    });
}

// Display image details
function displayImageDetails() {
    const container = document.getElementById('imageDetails');
    
    const imageName = currentImageData.title.replace('File:', '').replace(/\.[^/.]+$/, '').replace(/_/g, ' ');
    
    container.innerHTML = `
        <div class="row">
            <div class="col-md-6">
                <img src="${currentImageData.url}" class="img-fluid rounded" alt="${imageName}">
            </div>
            <div class="col-md-6">
                <h5>${imageName}</h5>
                <p class="text-muted">${currentImageData.metadata.description || 'No description available'}</p>
                
                <div class="mb-3">
                    <strong>Details:</strong>
                    <ul class="list-unstyled">
                        <li><strong>Size:</strong> ${currentImageData.width} × ${currentImageData.height}</li>
                        <li><strong>File size:</strong> ${formatFileSize(currentImageData.size)}</li>
                        <li><strong>Date:</strong> ${currentImageData.metadata.date || 'Unknown'}</li>
                        <li><strong>Author:</strong> ${currentImageData.metadata.author || 'Unknown'}</li>
                        <li><strong>License:</strong> ${currentImageData.metadata.license || 'Unknown'}</li>
                    </ul>
                </div>
                
                <div class="mb-3">
                    <strong>Source:</strong>
                    <br>
                    <a href="${currentImageData.description_url}" target="_blank" class="text-decoration-none">
                        <i class="bi bi-external-link me-1"></i>View on Wikimedia Commons
                    </a>
                </div>
            </div>
        </div>
    `;
}



// Preview import
function previewImport() {
    if (!currentImageData) return;
    
    showLoading('Previewing import...');
    
    fetch('/admin/import/wikimedia-commons/preview-import', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({
            image_id: currentImageData.id
        })
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        hideLoading();
        
        if (data.success) {
            displayImportPreview(data.data);
            loadPotentialSpans(data.data.potential_spans);
        } else {
            alert('Failed to preview import: ' + data.message);
        }
    })
    .catch(error => {
        hideLoading();
        console.error('Error previewing import:', error);
        alert('Failed to preview import: ' + error.message);
    });
}

// Display import preview
function displayImportPreview(preview) {
    const container = document.getElementById('importPreview');
    
    let html = '<div class="alert alert-info">';
    html += '<h6>Import Preview:</h6>';
    html += '<ul class="mb-0">';
    
    if (preview.will_create_image) {
        html += '<li>✅ Will create new image span</li>';
    } else {
        html += `<li>ℹ️ Will use existing image: ${preview.existing_image.name}</li>`;
    }
    
    const plan = preview.import_plan;
    html += `<li><strong>Name:</strong> ${plan.image_name}</li>`;
    html += `<li><strong>Description:</strong> ${plan.image_description}</li>`;
    if (plan.image_date.year) {
        html += `<li><strong>Date:</strong> ${plan.image_date.year}`;
        if (plan.image_date.month) html += `-${plan.image_date.month}`;
        if (plan.image_date.day) html += `-${plan.image_date.day}`;
        html += '</li>';
    }
    html += `<li><strong>Author:</strong> ${plan.image_author}</li>`;
    html += `<li><strong>License:</strong> ${plan.image_license}</li>`;
    
    html += '</ul></div>';
    
    container.innerHTML = html;
}

// Load potential spans into dropdown
function loadPotentialSpans(potentialSpans) {
    const select = document.getElementById('targetSpanId');
    const originatingSpanUuid = document.getElementById('originatingSpanUuid').value;
    const originatingSpanName = document.getElementById('originatingSpanName').value;
    select.innerHTML = '<option value="">No connection</option>';
    
    // Always add the originating span first if we have one
    if (originatingSpanUuid && originatingSpanName) {
        const originatingOption = document.createElement('option');
        originatingOption.value = originatingSpanUuid;
        originatingOption.textContent = `${originatingSpanName} (originating span)`;
        originatingOption.selected = true;
        select.appendChild(originatingOption);
    }
    
    // Add potential spans (excluding the originating span if it's already there)
    if (potentialSpans && potentialSpans.length > 0) {
        potentialSpans.forEach(span => {
            // Skip if this span matches the originating span name
            if (originatingSpanName && span.name === originatingSpanName) {
                return;
            }
            
            const option = document.createElement('option');
            option.value = span.id;
            option.textContent = `${span.name} (${span.type_id}) - Score: ${span.relevance_score}`;
            select.appendChild(option);
        });
    }
}

// Import image
function importImage() {
    if (!currentImageData) {
        alert('No image selected');
        return;
    }
    
    const targetSpanId = document.getElementById('targetSpanId').value;
    
    showLoading('Importing image...');
    
    fetch('/admin/import/wikimedia-commons/import-image', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({
            image_id: currentImageData.id,
            target_span_id: targetSpanId || null
        })
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        hideLoading();
        
        if (data.success) {
            alert('Image imported successfully!');
            
            // Reset form
            document.getElementById('targetSpanId').value = '';
            document.getElementById('importPreview').innerHTML = '';
            
            // Hide import panel
            document.getElementById('importPanel').style.display = 'none';
        } else {
            alert('Import failed: ' + data.message);
        }
    })
    .catch(error => {
        hideLoading();
        console.error('Import error:', error);
        alert('Import failed: ' + error.message);
    });
}

// Clear cache
function clearCache() {
    showLoading('Clearing cache...');
    
    fetch('/admin/import/wikimedia-commons/clear-cache', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        hideLoading();
        
        if (data.success) {
            alert('Cache cleared successfully');
        } else {
            alert('Failed to clear cache: ' + (data.message || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Cache clear error:', error);
        hideLoading();
        alert('Failed to clear cache: ' + error.message);
    });
}

// Show search results
function showSearchResults() {
    document.getElementById('searchResultsCard').style.display = 'block';
}

// Show image details
function showImageDetails() {
    document.getElementById('imageDetailsCard').style.display = 'block';
    document.getElementById('importPanel').style.display = 'block';
}

// Show loading overlay
function showLoading(message) {
    document.getElementById('loadingMessage').textContent = message;
    document.getElementById('loadingOverlay').classList.remove('d-none');
}

// Hide loading overlay
function hideLoading() {
    document.getElementById('loadingOverlay').classList.add('d-none');
}

// Format file size
function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

// Handle enter key in search
document.getElementById('searchQuery').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        searchImages();
    }
});

document.getElementById('searchYear').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        searchImages();
    }
});

// Auto-search if initial search value is provided
document.addEventListener('DOMContentLoaded', function() {
    const initialSearch = document.getElementById('searchQuery').value.trim();
    if (initialSearch) {
        searchImages();
    }
});
</script>
@endpush
