@extends('layouts.app')

@section('page_title')
    Import from Science Museum Group
@endsection

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">
        <i class="bi bi-museum me-2"></i>
        Import from Science Museum Group
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
                    Search Objects
                </h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label for="searchQuery" class="form-label">Search by object name or description</label>
                    <input type="text" class="form-control" id="searchQuery" placeholder="e.g., televisor, computer, engine">
                </div>
                <button class="btn btn-primary w-100" onclick="searchObjects()">
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
                <div class="p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <button class="btn btn-outline-secondary btn-sm" id="prevPage" onclick="changePage(-1)" style="display: none;">
                            <i class="bi bi-chevron-left"></i> Previous
                        </button>
                        <span id="pageInfo" class="text-muted small"></span>
                        <button class="btn btn-outline-secondary btn-sm" id="nextPage" onclick="changePage(1)" style="display: none;">
                            Next <i class="bi bi-chevron-right"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Import Panel -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-download me-2"></i>
                    Import Object
                </h5>
            </div>
            <div class="card-body">
                <div id="importForm" style="display: none;">
                    <!-- Object Details -->
                    <div class="mb-4">
                        <h6>Object Details</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Title</label>
                                    <input type="text" class="form-control" id="objectTitle" readonly>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">SMG ID</label>
                                    <input type="text" class="form-control" id="objectId" readonly>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Creation Date</label>
                                    <input type="text" class="form-control" id="objectDate" readonly>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Description</label>
                                    <textarea class="form-control" id="objectDescription" rows="4" readonly></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Related Data -->
                    <div class="mb-4">
                        <h6>Related Data</h6>
                        
                        <!-- Makers -->
                        <div class="mb-3">
                            <label class="form-label">Makers/Creators</label>
                            <div id="makersList">
                                <!-- Makers will be populated here -->
                            </div>
                        </div>

                        <!-- Places -->
                        <div class="mb-3">
                            <label class="form-label">Places</label>
                            <div id="placesList">
                                <!-- Places will be populated here -->
                            </div>
                        </div>

                        <!-- Images -->
                        <div class="mb-3">
                            <label class="form-label">Images</label>
                            <div id="imagesList">
                                <!-- Images will be populated here -->
                            </div>
                        </div>
                    </div>

                    <!-- Import Options -->
                    <div class="mb-4">
                        <h6>Import Options</h6>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="importObject" checked>
                            <label class="form-check-label" for="importObject">
                                Import object as thing span
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="importMakers" checked>
                            <label class="form-check-label" for="importMakers">
                                Import makers as person spans
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="importPlaces" checked>
                            <label class="form-check-label" for="importPlaces">
                                Import places as place spans
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="importImages" checked>
                            <label class="form-check-label" for="importImages">
                                Import images as photo spans
                            </label>
                        </div>
                    </div>

                    <hr>

                    <div class="d-flex justify-content-between">
                        <button class="btn btn-outline-primary" onclick="previewImport()">
                            <i class="bi bi-eye me-1"></i>
                            Preview Import
                        </button>
                        <button class="btn btn-warning" onclick="quickImport()">
                            <i class="bi bi-lightning me-1"></i>
                            Quick Import
                        </button>
                        <button class="btn btn-success" onclick="performImport()">
                            <i class="bi bi-download me-1"></i>
                            Import Object
                        </button>
                    </div>
                </div>

                <!-- Preview Results -->
                <div id="previewResults" style="display: none;">
                    <h6>Import Preview</h6>
                    <div id="previewContent">
                        <!-- Preview content will be populated here -->
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between">
                        <button class="btn btn-outline-secondary" onclick="hidePreview()">
                            <i class="bi bi-arrow-left me-1"></i>
                            Back to Import
                        </button>
                        <button class="btn btn-success" onclick="performImport()">
                            <i class="bi bi-download me-1"></i>
                            Proceed with Import
                        </button>
                    </div>
                </div>

                <!-- Import Results -->
                <div id="importResults" style="display: none;">
                    <h6>Import Results</h6>
                    <div id="importContent">
                        <!-- Import results will be populated here -->
                    </div>
                    <hr>
                    <button class="btn btn-outline-secondary" onclick="resetForm()">
                        <i class="bi bi-arrow-clockwise me-1"></i>
                        Import Another Object
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Loading Modal -->
<div class="modal fade" id="loadingModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-body text-center">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2 mb-0" id="loadingMessage">Loading...</p>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
let currentPage = 1;
let totalPages = 1;
let currentSearchQuery = '';
let selectedObjectId = null;
let loadingModal = null;

function showLoading(message = 'Loading...') {
    console.log('showLoading called with message:', message);
    document.getElementById('loadingMessage').textContent = message;
    
    // Create new modal instance or get existing one
    const modalElement = document.getElementById('loadingModal');
    loadingModal = new bootstrap.Modal(modalElement);
    loadingModal.show();
}

function hideLoading() {
    console.log('hideLoading called');
    
    // Try multiple approaches to hide the modal
    if (loadingModal) {
        loadingModal.hide();
    }
    
    // Also try getting the instance
    const modalElement = document.getElementById('loadingModal');
    const modalInstance = bootstrap.Modal.getInstance(modalElement);
    if (modalInstance) {
        modalInstance.hide();
    }
    
    // Fallback: hide directly with jQuery if available
    if (typeof $ !== 'undefined') {
        $('#loadingModal').modal('hide');
        $('#loadingModal').hide();
    }
    
    // Comprehensive cleanup to fix scrolling issues
    modalElement.style.display = 'none';
    modalElement.classList.remove('show');
    
    // Remove all modal-related classes from body
    document.body.classList.remove('modal-open');
    document.body.style.overflow = '';
    document.body.style.paddingRight = '';
    
    // Remove all modal backdrops
    const backdrops = document.querySelectorAll('.modal-backdrop');
    backdrops.forEach(backdrop => backdrop.remove());
    
    // Remove any inline styles that might prevent scrolling
    document.body.style.position = '';
    document.body.style.top = '';
    
    // Force a reflow to ensure cleanup
    document.body.offsetHeight;
}

function searchObjects() {
    const query = document.getElementById('searchQuery').value.trim();
    if (!query) {
        alert('Please enter a search term');
        return;
    }

    currentSearchQuery = query;
    currentPage = 1;
    performSearch();
}

function performSearch() {
    showLoading('Searching objects...');
    
    fetch('{{ route("admin.import.science-museum-group.search") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({
            query: currentSearchQuery,
            page: currentPage,
            per_page: 20
        })
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        if (data.success) {
            displaySearchResults(data.data);
        } else {
            alert('Search failed: ' + data.message);
        }
    })
    .catch(error => {
        hideLoading();
        console.error('Search error:', error);
        alert('Search failed. Please try again.');
    });
}

function fixScrolling() {
    // Ensure body is scrollable
    document.body.style.overflow = 'auto';
    document.body.style.position = 'static';
    document.body.style.top = '';
    document.body.style.paddingRight = '';
    
    // Remove any remaining modal classes
    document.body.classList.remove('modal-open');
    
    // Remove any remaining backdrops
    const backdrops = document.querySelectorAll('.modal-backdrop');
    backdrops.forEach(backdrop => backdrop.remove());
}

function displaySearchResults(data) {
    const resultsContainer = document.getElementById('searchResults');
    const resultsCard = document.getElementById('searchResultsCard');
    
    if (!data.data || data.data.length === 0) {
        resultsContainer.innerHTML = '<div class="p-3 text-muted">No objects found</div>';
        resultsCard.style.display = 'block';
        fixScrolling();
        return;
    }

    resultsContainer.innerHTML = data.data.map(item => {
        const title = item.attributes.summary?.title || item.attributes.title?.[0]?.value || 'Untitled';
        
        // Extract identifier properly - look for primary identifier or first one
        let identifier = 'No identifier';
        if (item.attributes.identifier && Array.isArray(item.attributes.identifier)) {
            const primaryId = item.attributes.identifier.find(id => id.primary === true);
            const firstId = item.attributes.identifier[0];
            const idObj = primaryId || firstId;
            if (idObj && idObj.value) {
                identifier = `${idObj.type || 'ID'}: ${idObj.value}`;
            }
        }
        
        // Extract image from multimedia field
        let figure = null;
        if (item.attributes.multimedia && Array.isArray(item.attributes.multimedia)) {
            const firstImage = item.attributes.multimedia.find(media => media['@type'] === 'image');
            if (firstImage && firstImage['@processed'] && firstImage['@processed']['large_thumbnail']) {
                figure = firstImage['@processed']['large_thumbnail']['location'];
                // Ensure URL is complete
                if (figure && !figure.startsWith('http')) {
                    figure = `https://coimages.sciencemuseumgroup.org.uk/${figure}`;
                }
            }
        }
        
        return `
            <div class="list-group-item list-group-item-action" onclick="console.log('Clicked on object:', '${item.id}'); selectObject('${item.id}', '${title.replace(/'/g, "\\'")}')">
                <div class="d-flex w-100 justify-content-between">
                    <h6 class="mb-1">${title}</h6>
                    <small class="text-muted">${item.id}</small>
                </div>
                <p class="mb-1 small text-muted">${identifier}</p>
                ${figure ? `<img src="${figure}" class="mt-2" style="max-width: 100px; max-height: 100px;">` : ''}
            </div>
        `;
    }).join('');

    resultsCard.style.display = 'block';
    
    // Update pagination
    totalPages = Math.ceil((data.meta?.total || 0) / 20);
    updatePagination();
    
    // Fix scrolling after displaying results
    fixScrolling();
}

function updatePagination() {
    const prevBtn = document.getElementById('prevPage');
    const nextBtn = document.getElementById('nextPage');
    const pageInfo = document.getElementById('pageInfo');
    
    prevBtn.style.display = currentPage > 1 ? 'block' : 'none';
    nextBtn.style.display = currentPage < totalPages ? 'block' : 'none';
    pageInfo.textContent = `Page ${currentPage} of ${totalPages}`;
}

function changePage(delta) {
    currentPage += delta;
    if (currentPage < 1) currentPage = 1;
    if (currentPage > totalPages) currentPage = totalPages;
    performSearch();
}

function selectObject(objectId, title) {
    console.log('selectObject function called with:', objectId, title);
    selectedObjectId = objectId;
    showLoading('Loading object details...');
    
    console.log('Fetching object data for:', objectId);
    
    fetch('{{ route("admin.import.science-museum-group.get-object-data") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({
            object_id: objectId
        })
    })
    .then(response => {
        console.log('Response status:', response.status);
        console.log('Response headers:', response.headers);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.text().then(text => {
            console.log('Response text:', text);
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('Failed to parse JSON:', e);
                throw new Error('Invalid JSON response');
            }
        });
    })
    .then(data => {
        console.log('Response data:', data);
        hideLoading();
        if (data.success) {
            displayObjectData(data.data);
        } else {
            alert('Failed to load object data: ' + data.message);
        }
    })
    .catch(error => {
        hideLoading();
        console.error('Error loading object data:', error);
        alert('Failed to load object data: ' + error.message);
    });
}

function displayObjectData(data) {
    const object = data.object;
    
    // Populate object details
    document.getElementById('objectTitle').value = object.title;
    document.getElementById('objectId').value = object.id;
    document.getElementById('objectDescription').value = object.description;
    
    const creationDate = object.creation_date;
    if (creationDate) {
        document.getElementById('objectDate').value = creationDate.value || `${creationDate.from || ''} - ${creationDate.to || ''}`;
    } else {
        document.getElementById('objectDate').value = 'Unknown';
    }

    // Populate makers with detailed information
    const makersList = document.getElementById('makersList');
    if (data.makers && data.makers.length > 0) {
        makersList.innerHTML = data.makers.map(maker => {
            let details = `<strong>${maker.name}</strong>`;
            
            // Add birth/death dates if available
            if (maker.birth_date || maker.death_date) {
                const birthYear = maker.birth_date?.value || maker.birth_date?.from || '';
                const deathYear = maker.death_date?.value || maker.death_date?.to || '';
                if (birthYear || deathYear) {
                    details += ` (${birthYear}${deathYear ? ' - ' + deathYear : ''})`;
                }
            }
            
            // Add nationality if available
            if (maker.nationality) {
                details += `<br><small class="text-muted">Nationality: ${maker.nationality}</small>`;
            }
            
            // Add occupation if available
            if (maker.occupation) {
                details += `<br><small class="text-muted">Occupation: ${maker.occupation}</small>`;
            }
            
            // Add biography if available (truncated)
            if (maker.biography) {
                const shortBio = maker.biography.length > 200 ? maker.biography.substring(0, 200) + '...' : maker.biography;
                details += `<br><small class="text-muted">${shortBio}</small>`;
            }
            
            details += `<br><small class="text-muted">SMG ID: ${maker.id}</small>`;
            
            return `
                <div class="card mb-2">
                    <div class="card-body p-2">
                        ${details}
                    </div>
                </div>
            `;
        }).join('');
    } else {
        makersList.innerHTML = '<p class="text-muted">No makers found</p>';
    }

    // Populate places with detailed information
    const placesList = document.getElementById('placesList');
    if (data.places && data.places.length > 0) {
        placesList.innerHTML = data.places.map(place => {
            let details = `<strong>${place.name}</strong>`;
            
            // Add description if available
            if (place.description) {
                const shortDesc = place.description.length > 200 ? place.description.substring(0, 200) + '...' : place.description;
                details += `<br><small class="text-muted">${shortDesc}</small>`;
            }
            
            details += `<br><small class="text-muted">SMG ID: ${place.id}</small>`;
            
            return `
                <div class="card mb-2">
                    <div class="card-body p-2">
                        ${details}
                    </div>
                </div>
            `;
        }).join('');
    } else {
        placesList.innerHTML = '<p class="text-muted">No places found</p>';
    }

    // Populate images
    const imagesList = document.getElementById('imagesList');
    if (data.images && data.images.length > 0) {
        const firstImage = data.images[0];
        imagesList.innerHTML = `
            <div class="card mb-2">
                <div class="card-body p-2">
                    <img src="${firstImage.url}" class="img-thumbnail" style="max-width: 100px; max-height: 100px;">
                    <br><small class="text-muted">${firstImage.credit || 'No credit'}</small>
                    ${data.images.length > 1 ? `<br><small class="text-warning">Note: Only the first image will be imported (${data.images.length} total available)</small>` : ''}
                </div>
            </div>
        `;
    } else {
        imagesList.innerHTML = '<p class="text-muted">No images found</p>';
    }

    // Automatically generate and show preview
    showLoading('Generating import preview...');
    
    fetch('{{ route("admin.import.science-museum-group.preview") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({
            object_id: selectedObjectId
        })
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        if (data.success) {
            displayPreview(data.data);
        } else {
            alert('Failed to generate preview: ' + data.message);
            // Fallback to showing import form if preview fails
            document.getElementById('importForm').style.display = 'block';
            document.getElementById('previewResults').style.display = 'none';
            document.getElementById('importResults').style.display = 'none';
        }
    })
    .catch(error => {
        hideLoading();
        console.error('Preview error:', error);
        alert('Failed to generate preview. Please try again.');
        // Fallback to showing import form if preview fails
        document.getElementById('importForm').style.display = 'block';
        document.getElementById('previewResults').style.display = 'none';
        document.getElementById('importResults').style.display = 'none';
    });
}

function previewImport() {
    if (!selectedObjectId) {
        alert('Please select an object first');
        return;
    }

    showLoading('Generating import preview...');
    
    fetch('{{ route("admin.import.science-museum-group.preview") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({
            object_id: selectedObjectId
        })
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        if (data.success) {
            displayPreview(data.data);
        } else {
            alert('Failed to generate preview: ' + data.message);
        }
    })
    .catch(error => {
        hideLoading();
        console.error('Preview error:', error);
        alert('Failed to generate preview. Please try again.');
    });
}

function displayPreview(data) {
    const previewContent = document.getElementById('previewContent');
    const plan = data.import_plan;
    
    let previewHtml = `
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">📋 Complete Import Summary</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th>Type</th>
                                <th>Subtype</th>
                                <th>Name</th>
                                <th>Description</th>
                                <th>Dates</th>
                                <th>Action</th>
                                <th>Metadata</th>
                            </tr>
                        </thead>
                        <tbody>
    `;
    
    // Object row with subtype selection
    const object = data.object;
    const objectDates = object.creation_date ? 
        (object.creation_date.value || `${object.creation_date.from || ''} - ${object.creation_date.to || ''}`) : 
        'No date';
    
    const thingSubtypes = [
        'artifact', 'track', 'album', 'film', 'programme', 'play', 'book', 'poem', 
        'photo', 'sculpture', 'painting', 'performance', 'video', 'article', 'paper', 
        'product', 'vehicle', 'tool', 'device', 'other'
    ];
    
    const suggestedSubtype = object.suggested_subtype || 'artifact';
    const smgObjectType = object.object_type || 'Unknown';
    
    previewHtml += `
        <tr class="table-primary">
            <td><span class="badge bg-primary">thing</span></td>
            <td>
                <select id="thingSubtype" class="form-select form-select-sm" style="width: 120px;">
                    ${thingSubtypes.map(subtype => 
                        `<option value="${subtype}" ${subtype === suggestedSubtype ? 'selected' : ''}>${subtype}</option>`
                    ).join('')}
                </select>
            </td>
            <td><strong>${object.title}</strong></td>
            <td>${object.description ? (object.description.length > 100 ? object.description.substring(0, 100) + '...' : object.description) : 'No description'}</td>
            <td>${objectDates}</td>
            <td><span class="badge bg-${plan.object.action === 'create' ? 'success' : 'info'}">${plan.object.action}</span></td>
            <td>
                <small>SMG ID: ${object.id}</small><br>
                <small class="text-muted">SMG Type: ${smgObjectType}</small>
            </td>
        </tr>
    `;
    
    // Makers rows
    if (data.makers && data.makers.length > 0) {
        data.makers.forEach((maker, index) => {
            const planMaker = plan.makers[index] || {};
            const isOrg = maker.is_organization;
            const spanType = isOrg ? 'organisation' : 'person';
            const badgeClass = isOrg ? 'bg-warning text-dark' : 'bg-success';
            
            // Handle dates based on type
            const makerDates = [];
            if (isOrg) {
                if (maker.founding_date?.value || maker.founding_date?.from) {
                    makerDates.push(`Founded: ${maker.founding_date.value || maker.founding_date.from}`);
                }
                if (maker.dissolution_date?.value || maker.dissolution_date?.from) {
                    makerDates.push(`Dissolved: ${maker.dissolution_date.value || maker.dissolution_date.from}`);
                }
            } else {
                if (maker.birth_date?.value || maker.birth_date?.from) {
                    makerDates.push(`Birth: ${maker.birth_date.value || maker.birth_date.from}`);
                }
                if (maker.death_date?.value || maker.death_date?.from) {
                    makerDates.push(`Death: ${maker.death_date.value || maker.death_date.from}`);
                }
            }
            const datesStr = makerDates.length > 0 ? makerDates.join(', ') : 'No dates';
            
            previewHtml += `
                <tr class="table-success">
                    <td><span class="badge ${badgeClass}" id="maker-type-badge-${index}">${spanType}</span></td>
                    <td>
                        <div class="btn-group btn-group-sm" role="group">
                            <input type="radio" class="btn-check" name="maker-type-${index}" id="maker-person-${index}" value="person" ${!isOrg ? 'checked' : ''}>
                            <label class="btn btn-outline-success btn-sm" for="maker-person-${index}">Person</label>
                            <input type="radio" class="btn-check" name="maker-type-${index}" id="maker-org-${index}" value="organisation" ${isOrg ? 'checked' : ''}>
                            <label class="btn btn-outline-warning btn-sm" for="maker-org-${index}">Organisation</label>
                        </div>
                    </td>
                    <td><strong>${maker.name}</strong></td>
                    <td>${maker.description || maker.biography ? ((maker.description || maker.biography).length > 100 ? (maker.description || maker.biography).substring(0, 100) + '...' : (maker.description || maker.biography)) : 'No description'}</td>
                    <td>${datesStr}</td>
                    <td><span class="badge bg-${planMaker.action === 'create' ? 'success' : 'info'}">${planMaker.action || 'create'}</span></td>
                    <td><small>SMG ID: ${maker.id}${maker.nationality ? ', ' + maker.nationality : ''}</small></td>
                </tr>
            `;
        });
    }
    
    // Places rows
    if (data.places && data.places.length > 0) {
        data.places.forEach((place, index) => {
            const planPlace = plan.places[index] || {};
            previewHtml += `
                <tr class="table-warning">
                    <td><span class="badge bg-warning text-dark">place</span></td>
                    <td><span class="badge bg-secondary">-</span></td>
                    <td><strong>${place.name}</strong></td>
                    <td>${place.description ? (place.description.length > 100 ? place.description.substring(0, 100) + '...' : place.description) : 'No description'}</td>
                    <td>-</td>
                    <td><span class="badge bg-${planPlace.action === 'create' ? 'success' : 'info'}">${planPlace.action || 'create'}</span></td>
                    <td><small>SMG ID: ${place.id}</small></td>
                </tr>
            `;
        });
    }
    
    // Image row
    if (data.images && data.images.length > 0) {
        const firstImage = data.images[0];
        previewHtml += `
            <tr class="table-info">
                <td><span class="badge bg-info">thing</span></td>
                <td><span class="badge bg-info">photo</span></td>
                <td><strong>Image: ${object.title}</strong></td>
                <td>${firstImage.description || firstImage.credit || 'No description'}</td>
                <td>${firstImage.date || 'No date'}</td>
                <td><span class="badge bg-success">create</span></td>
                <td><small>${firstImage.photographer ? 'Photographer: ' + firstImage.photographer : 'No photographer'}</small></td>
            </tr>
        `;
    }
    
    // Connections section
    previewHtml += `
                                </tbody>
                            </table>
                        </div>
                        
                        <h6 class="mt-4 mb-3">🔗 Connections to be Created:</h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th>From</th>
                                        <th>Connection Type</th>
                                        <th>To</th>
                                    </tr>
                                </thead>
                                <tbody>
    `;
    
    // Object to Makers connections
    if (data.makers && data.makers.length > 0) {
        data.makers.forEach((maker, index) => {
            const isOrg = maker.is_organization;
            const spanType = isOrg ? 'organisation' : 'person';
            const badgeClass = isOrg ? 'bg-warning text-dark' : 'bg-success';
            
            previewHtml += `
                <tr>
                    <td><strong>${object.title}</strong> <span class="badge bg-primary">thing</span></td>
                    <td><span class="badge bg-secondary">created</span></td>
                    <td><strong>${maker.name}</strong> <span class="badge ${badgeClass}">${spanType}</span></td>
                </tr>
            `;
        });
    }
    
    // Object to Places connections
    if (data.places && data.places.length > 0) {
        data.places.forEach(place => {
            previewHtml += `
                <tr>
                    <td><strong>${object.title}</strong> <span class="badge bg-primary">thing</span></td>
                    <td><span class="badge bg-secondary">located</span></td>
                    <td><strong>${place.name}</strong> <span class="badge bg-warning text-dark">place</span></td>
                </tr>
            `;
        });
    }
    
    // Object to Image connection
    if (data.images && data.images.length > 0) {
        const firstImage = data.images[0];
        previewHtml += `
            <tr>
                <td><strong>${object.title}</strong> <span class="badge bg-primary">thing</span></td>
                <td><span class="badge bg-secondary">subject_of</span></td>
                <td><strong>${firstImage.title || 'Image'}</strong> <span class="badge bg-info">thing (photo)</span></td>
            </tr>
        `;
    }
    
    previewHtml += `
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
    `;
    
    previewContent.innerHTML = previewHtml;
    
    // Add event listeners for radio buttons
    document.querySelectorAll('input[name^="maker-type-"]').forEach(radio => {
        radio.addEventListener('change', function() {
            const index = this.name.replace('maker-type-', '');
            const badge = document.getElementById(`maker-type-badge-${index}`);
            const isOrg = this.value === 'organisation';
            
            badge.textContent = this.value;
            badge.className = `badge ${isOrg ? 'bg-warning text-dark' : 'bg-success'}`;
        });
    });
    
    // Add import buttons
    const buttonArea = document.querySelector('#previewResults .d-flex.justify-content-between');
    if (buttonArea) {
        buttonArea.innerHTML = `
            <button class="btn btn-outline-secondary" onclick="hidePreview()">
                <i class="bi bi-arrow-left me-1"></i>
                Back to Search
            </button>
            <button class="btn btn-primary" onclick="performImport()">
                <i class="bi bi-download me-1"></i>
                Import
            </button>
        `;
    }
    
    // Show preview
    document.getElementById('importForm').style.display = 'none';
    document.getElementById('previewResults').style.display = 'block';
    document.getElementById('importResults').style.display = 'none';
}

function hidePreview() {
    document.getElementById('importForm').style.display = 'block';
    document.getElementById('previewResults').style.display = 'none';
    document.getElementById('importResults').style.display = 'none';
}



function performImport() {
    if (!selectedObjectId) {
        alert('Please select an object first');
        return;
    }

    // Get the selected thing subtype
    const thingSubtype = document.getElementById('thingSubtype')?.value || 'artifact';

    // Collect maker type choices
    const makerTypeChoices = {};
    document.querySelectorAll('input[name^="maker-type-"]:checked').forEach(radio => {
        const index = radio.name.replace('maker-type-', '');
        makerTypeChoices[index] = radio.value;
    });

    const importOptions = {
        import_object: document.getElementById('importObject').checked,
        import_makers: document.getElementById('importMakers').checked,
        import_places: document.getElementById('importPlaces').checked,
        import_images: document.getElementById('importImages').checked,
        thing_subtype: thingSubtype,
        maker_type_choices: makerTypeChoices
    };

    showLoading('Importing object...');
    
    fetch('{{ route("admin.import.science-museum-group.import") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({
            object_id: selectedObjectId,
            import_options: importOptions
        })
    })
    .then(response => {
        console.log('Import response status:', response.status);
        return response.text().then(text => {
            console.log('Import response text:', text);
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('Failed to parse import response JSON:', e);
                throw new Error('Invalid JSON response: ' + text);
            }
        });
    })
    .then(data => {
        console.log('Import response data:', data);
        hideLoading();
        if (data.success) {
            displayImportResults(data.data);
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

function displayImportResults(data) {
    // Update the existing preview table to show what actually happened
    const $previewContent = $('#previewContent');
    
    // Update the card header to show it's now results
    $previewContent.find('.card-header h6').html('✅ Import Results');
    
    // Update the action column in the table to show what actually happened
    const $actionCells = $previewContent.find('td:nth-child(6)'); // Action column
    
    // Update object action
    if (data.object) {
        $actionCells.eq(0).html('<span class="badge bg-success">✅ Created</span>');
    }
    
    // Update makers actions
    let cellIndex = 1; // Start after object
    if (data.makers && data.makers.length > 0) {
        data.makers.forEach((maker, index) => {
            const $cell = $actionCells.eq(cellIndex);
            if ($cell.length) {
                if (maker.id) {
                    $cell.html('<span class="badge bg-success">✅ Created</span>');
                } else {
                    $cell.html('<span class="badge bg-info">🔗 Connected</span>');
                }
            }
            cellIndex++;
        });
    }
    
    // Update places actions
    if (data.places && data.places.length > 0) {
        data.places.forEach((place, index) => {
            const $cell = $actionCells.eq(cellIndex);
            if ($cell.length) {
                if (place.id) {
                    $cell.html('<span class="badge bg-success">✅ Created</span>');
                } else {
                    $cell.html('<span class="badge bg-info">🔗 Connected</span>');
                }
            }
            cellIndex++;
        });
    }
    
    // Update image action
    if (data.images && data.images.length > 0) {
        const $cell = $actionCells.eq(cellIndex);
        if ($cell.length) {
            $cell.html('<span class="badge bg-success">✅ Created</span>');
        }
    }
    
    // Update connections table header
    $(previewContent).find('h6:contains("Connections to be Created")').html('✅ Connections Created:');
    
    // Add a summary at the top
    const totalSpans = (data.object ? 1 : 0) + (data.makers?.length || 0) + (data.places?.length || 0) + (data.images?.length || 0);
    const createdSpans = (data.object ? 1 : 0) + (data.makers?.filter(m => m.id).length || 0) + (data.places?.filter(p => p.id).length || 0) + (data.images?.length || 0);
    const connectedSpans = totalSpans - createdSpans;
    
    const summaryHtml = `
        <div class="alert alert-success mb-3">
            <h6 class="mb-2">🎉 Import Completed Successfully!</h6>
            <p class="mb-1"><strong>Total spans:</strong> ${totalSpans} (${createdSpans} created, ${connectedSpans} connected)</p>
            <p class="mb-1"><strong>Connections:</strong> ${data.connections?.length || 0} created</p>
            <p class="mb-0"><small>✅ = Created new span, 🔗 = Connected to existing span</small></p>
        </div>
    `;
    
    // Insert summary at the beginning of card body
    $previewContent.find('.card-body').prepend(summaryHtml);
    
    // Update the button area
    $('#previewResults .d-flex.justify-content-between').html(`
        <button class="btn btn-outline-secondary" onclick="resetForm()">
            <i class="bi bi-arrow-clockwise me-1"></i>
            Import Another Object
        </button>
        <button class="btn btn-outline-primary" onclick="viewImportedSpans()">
            <i class="bi bi-eye me-1"></i>
            View Imported Spans
        </button>
    `);
    
    // Keep showing the preview results (now updated with import results)
    document.getElementById('importForm').style.display = 'none';
    document.getElementById('previewResults').style.display = 'block';
    document.getElementById('importResults').style.display = 'none';
}

function viewImportedSpans() {
    // This could open the imported spans in new tabs or redirect to them
    // For now, just show an alert with the information
    alert('This feature would open the imported spans in new browser tabs. In a full implementation, this would navigate to the span detail pages.');
}

function resetForm() {
    selectedObjectId = null;
    document.getElementById('searchQuery').value = '';
    document.getElementById('searchResultsCard').style.display = 'none';
    document.getElementById('importForm').style.display = 'none';
    document.getElementById('previewResults').style.display = 'none';
    document.getElementById('importResults').style.display = 'none';
}

function clearCache() {
    showLoading('Clearing cache...');
    
    fetch('{{ route("admin.import.science-museum-group.clear-cache") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        }
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        if (data.success) {
            alert('Cache cleared successfully');
        } else {
            alert('Failed to clear cache: ' + data.message);
        }
    })
    .catch(error => {
        hideLoading();
        console.error('Cache clear error:', error);
        alert('Failed to clear cache. Please try again.');
    });
}

// Handle Enter key in search - wait for DOM to be ready
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchQuery');
    if (searchInput) {
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                searchObjects();
            }
        });
    }
});
</script>
@endpush
