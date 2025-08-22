@extends('layouts.app')

@section('page_title')
    Import Prime Ministers
@endsection

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">
        <i class="bi bi-person-badge me-2"></i>
        Import Prime Ministers
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
                                Search Prime Ministers
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="searchTerm" class="form-label">Search by name</label>
                                <input type="text" class="form-control" id="searchTerm" placeholder="e.g., Churchill, Thatcher">
                            </div>
                            <button class="btn btn-primary w-100" onclick="searchPrimeMinisters()">
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
                        </div>
                    </div>
                </div>

                <!-- Import Panel -->
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-download me-2"></i>
                                Import Prime Minister
                            </h5>
                        </div>
                        <div class="card-body">
                            <div id="importForm" style="display: none;">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Name</label>
                                            <input type="text" class="form-control" id="pmName" readonly>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Party</label>
                                            <input type="text" class="form-control" id="pmParty" readonly>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Constituency</label>
                                            <input type="text" class="form-control" id="pmConstituency" readonly>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Parliament ID</label>
                                            <input type="text" class="form-control" id="pmParliamentId" readonly>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Gender</label>
                                            <input type="text" class="form-control" id="pmGender" readonly>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Description</label>
                                            <textarea class="form-control" id="pmDescription" rows="3" readonly></textarea>
                                        </div>
                                    </div>
                                </div>

                                <hr>

                                <div class="mb-3">
                                    <label class="form-label">Prime Ministership Periods</label>
                                    <div id="primeMinisterships">
                                        <div class="prime-ministership-entry border rounded p-3 mb-2">
                                            <div class="row">
                                                <div class="col-md-4">
                                                    <label class="form-label">Start Date</label>
                                                    <input type="date" class="form-control prime-ministership-start" required>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label">End Date</label>
                                                    <input type="date" class="form-control prime-ministership-end">
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label">Ongoing</label>
                                                    <div class="form-check mt-2">
                                                        <input class="form-check-input prime-ministership-ongoing" type="checkbox" id="ongoing1">
                                                        <label class="form-check-label" for="ongoing1">
                                                            Still in office
                                                        </label>
                                                    </div>
                                                </div>
                                                <div class="col-md-1">
                                                    <button type="button" class="btn btn-outline-danger btn-sm mt-4" onclick="removePrimeMinistership(this)">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="addPrimeMinistership()">
                                        <i class="bi bi-plus-circle me-1"></i>
                                        Add Period
                                    </button>
                                </div>

                                <div class="d-flex gap-2">
                                    <button class="btn btn-outline-secondary" onclick="previewImport()">
                                        <i class="bi bi-eye me-1"></i>
                                        Preview
                                    </button>
                                    <button class="btn btn-success" onclick="importPrimeMinister()">
                                        <i class="bi bi-download me-1"></i>
                                        Import
                                    </button>
                                </div>
                            </div>

                            <div id="noSelection" class="text-center py-5">
                                <i class="bi bi-search text-muted mb-3" style="font-size: 3rem;"></i>
                                <p class="text-muted">Search for a Prime Minister to begin import</p>
                            </div>
                        </div>
                    </div>

                    <!-- Preview Panel -->
                    <div class="card mt-3" id="previewCard" style="display: none;">
                        <div class="card-header">
                            <h6 class="card-title mb-0">Import Preview</h6>
                        </div>
                        <div class="card-body">
                            <div id="previewContent">
                                <!-- Preview content will be populated here -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Imports -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-clock-history me-2"></i>
                        Recent Imports
                    </h5>
                </div>
                <div class="card-body">
                    <div id="recentImports">
                        <div class="text-center py-3">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Loading Modal -->
<div class="modal fade" id="loadingModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-body text-center py-4">
                <div class="spinner-border text-primary mb-3" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mb-0" id="loadingMessage">Processing...</p>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
let selectedPrimeMinister = null;
let primeMinistershipCounter = 1;

$(document).ready(function() {
    loadRecentImports();
    
    // Handle ongoing checkbox changes
    $(document).on('change', '.prime-ministership-ongoing', function() {
        const endDateInput = $(this).closest('.prime-ministership-entry').find('.prime-ministership-end');
        if (this.checked) {
            endDateInput.prop('disabled', true).val('');
        } else {
            endDateInput.prop('disabled', false);
        }
    });
});

function searchPrimeMinisters() {
    const searchTerm = $('#searchTerm').val();
    
    console.log('🔍 Starting Prime Minister search with term:', searchTerm);
    showLoading('Searching Prime Ministers...');
    
    $.ajax({
        url: '{{ route("admin.prime-ministers.search") }}',
        method: 'POST',
        data: {
            search: searchTerm,
            skip: 0,
            take: 20,
            _token: '{{ csrf_token() }}'
        },
        timeout: 30000, // 30 second timeout
        beforeSend: function() {
            console.log('📤 Sending Prime Minister search request...');
        },
        success: function(response) {
            console.log('✅ Prime Minister search response received:', response);
            hideLoading();
            if (response.success) {
                console.log('📋 Displaying Prime Minister search results:', response.data.items);
                displaySearchResults(response.data.items);
            } else {
                console.error('❌ Prime Minister search failed:', response.message);
                showAlert('Error: ' + (response.message || 'Unknown error occurred'), 'danger');
            }
        },
        error: function(xhr, textStatus, errorThrown) {
            console.error('❌ Prime Minister search error:', {
                status: xhr.status,
                statusText: xhr.statusText,
                textStatus: textStatus,
                errorThrown: errorThrown,
                responseText: xhr.responseText,
                responseJSON: xhr.responseJSON
            });
            
            hideLoading();
            let errorMessage = 'Failed to search Prime Ministers';
            
            // Handle timeout specifically
            if (textStatus === 'timeout') {
                errorMessage = 'Request timed out - please try again';
            } else if (xhr.responseJSON && xhr.responseJSON.message) {
                errorMessage = xhr.responseJSON.message;
            } else if (xhr.status === 0) {
                errorMessage = 'Network error - please check your connection';
            } else if (xhr.status === 500) {
                errorMessage = 'Server error - please try again later';
            } else if (xhr.status === 404) {
                errorMessage = 'Search endpoint not found';
            }
            
            showAlert(errorMessage, 'danger');
        },
        complete: function() {
            console.log('🏁 Prime Minister search request completed');
            // Ensure loading is hidden even if success/error callbacks fail
            hideLoading();
        }
    });
}

function displaySearchResults(results) {
    const container = $('#searchResults');
    container.empty();
    
    if (results.length === 0) {
        container.append('<div class="list-group-item text-center text-muted">No Prime Ministers found</div>');
    } else {
        results.forEach(function(pm) {
            const item = $(`
                <div class="list-group-item list-group-item-action" onclick="selectPrimeMinister(${pm.parliament_id})">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-1">${pm.name}</h6>
                            <small class="text-muted">${pm.party}</small>
                        </div>
                        <small class="text-muted">ID: ${pm.parliament_id}</small>
                    </div>
                </div>
            `);
            container.append(item);
        });
    }
    
    $('#searchResultsCard').show();
}

function selectPrimeMinister(parliamentId) {
    console.log('👤 Getting Prime Minister data for ID:', parliamentId);
    showLoading('Loading Prime Minister data...');
    
    $.ajax({
        url: '{{ route("admin.prime-ministers.get-data") }}',
        method: 'POST',
        data: {
            parliament_id: parliamentId,
            _token: '{{ csrf_token() }}'
        },
        timeout: 30000, // 30 second timeout
        beforeSend: function() {
            console.log('📤 Sending Prime Minister data request...');
        },
        success: function(response) {
            console.log('✅ Prime Minister data response received:', response);
            hideLoading();
            if (response.success) {
                console.log('👤 Displaying Prime Minister data:', response.data);
                selectedPrimeMinister = response.data;
                populateImportForm(response.data);
                $('#importForm').show();
                $('#noSelection').hide();
                $('#previewCard').hide();
            } else {
                console.error('❌ Prime Minister data failed:', response.message);
                showAlert('Error: ' + (response.message || 'Unknown error occurred'), 'danger');
            }
        },
        error: function(xhr, textStatus, errorThrown) {
            console.error('❌ Prime Minister data error:', {
                status: xhr.status,
                statusText: xhr.statusText,
                textStatus: textStatus,
                errorThrown: errorThrown,
                responseText: xhr.responseText,
                responseJSON: xhr.responseJSON
            });
            
            hideLoading();
            let errorMessage = 'Failed to load Prime Minister data';
            
            // Handle timeout specifically
            if (textStatus === 'timeout') {
                errorMessage = 'Request timed out - please try again';
            } else if (xhr.responseJSON && xhr.responseJSON.message) {
                errorMessage = xhr.responseJSON.message;
            } else if (xhr.status === 0) {
                errorMessage = 'Network error - please check your connection';
            } else if (xhr.status === 500) {
                errorMessage = 'Server error - please try again later';
            } else if (xhr.status === 404) {
                errorMessage = 'Prime Minister not found';
            }
            
            showAlert(errorMessage, 'danger');
        },
        complete: function() {
            console.log('🏁 Prime Minister data request completed');
            // Ensure loading is hidden even if success/error callbacks fail
            hideLoading();
        }
    });
}

function populateImportForm(pmData) {
    $('#pmName').val(pmData.name);
    $('#pmParty').val(pmData.party);
    $('#pmConstituency').val(pmData.constituency);
    $('#pmParliamentId').val(pmData.parliament_id);
    $('#pmGender').val(pmData.gender === 'M' ? 'Male' : 'Female');
    $('#pmDescription').val(pmData.synopsis);
}

function addPrimeMinistership() {
    primeMinistershipCounter++;
    const template = `
        <div class="prime-ministership-entry border rounded p-3 mb-2">
            <div class="row">
                <div class="col-md-4">
                    <label class="form-label">Start Date</label>
                    <input type="date" class="form-control prime-ministership-start" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">End Date</label>
                    <input type="date" class="form-control prime-ministership-end">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Ongoing</label>
                    <div class="form-check mt-2">
                        <input class="form-check-input prime-ministership-ongoing" type="checkbox" id="ongoing${primeMinistershipCounter}">
                        <label class="form-check-label" for="ongoing${primeMinistershipCounter}">
                            Still in office
                        </label>
                    </div>
                </div>
                <div class="col-md-1">
                    <button type="button" class="btn btn-outline-danger btn-sm mt-4" onclick="removePrimeMinistership(this)">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>
        </div>
    `;
    $('#primeMinisterships').append(template);
}

function removePrimeMinistership(button) {
    $(button).closest('.prime-ministership-entry').remove();
}

function getPrimeMinisterships() {
    const periods = [];
    $('.prime-ministership-entry').each(function() {
        const startDate = $(this).find('.prime-ministership-start').val();
        const endDate = $(this).find('.prime-ministership-end').val();
        const ongoing = $(this).find('.prime-ministership-ongoing').is(':checked');
        
        if (startDate) {
            periods.push({
                start_date: startDate,
                end_date: ongoing ? null : endDate,
                ongoing: ongoing
            });
        }
    });
    return periods;
}

function previewImport() {
    if (!selectedPrimeMinister) {
        showAlert('Please select a Prime Minister first', 'warning');
        return;
    }
    
    const primeMinisterships = getPrimeMinisterships();
    if (primeMinisterships.length === 0) {
        showAlert('Please add at least one Prime Ministership period', 'warning');
        return;
    }
    
    showLoading('Generating preview...');
    
    $.ajax({
        url: '{{ route("admin.prime-ministers.preview") }}',
        method: 'POST',
        data: {
            parliament_id: selectedPrimeMinister.parliament_id,
            prime_ministerships: primeMinisterships,
            _token: '{{ csrf_token() }}'
        },
        success: function(response) {
            hideLoading();
            if (response.success) {
                displayPreview(response.data);
            } else {
                showAlert('Error: ' + response.message, 'danger');
            }
        },
        error: function(xhr) {
            hideLoading();
            showAlert('Failed to generate preview', 'danger');
        }
    });
}

function displayPreview(data) {
    const preview = data.preview;
    const content = `
        <div class="row">
            <div class="col-md-6">
                <h6>Basic Information</h6>
                <p><strong>Name:</strong> ${preview.name}</p>
                <p><strong>Party:</strong> ${preview.party}</p>
                <p><strong>Constituency:</strong> ${preview.constituency}</p>
            </div>
            <div class="col-md-6">
                <h6>Prime Ministerships</h6>
                ${preview.prime_ministerships.map(pm => `
                    <p><strong>${pm.start_date}</strong> to <strong>${pm.ongoing ? 'Present' : pm.end_date}</strong></p>
                `).join('')}
            </div>
        </div>
        <hr>
        <h6>Description</h6>
        <p class="text-muted">${preview.description}</p>
    `;
    
    $('#previewContent').html(content);
    $('#previewCard').show();
}

function importPrimeMinister() {
    if (!selectedPrimeMinister) {
        showAlert('Please select a Prime Minister first', 'warning');
        return;
    }
    
    const primeMinisterships = getPrimeMinisterships();
    if (primeMinisterships.length === 0) {
        showAlert('Please add at least one Prime Ministership period', 'warning');
        return;
    }
    
    if (!confirm('Are you sure you want to import this Prime Minister?')) {
        return;
    }
    
    showLoading('Importing Prime Minister...');
    
    $.ajax({
        url: '{{ route("admin.prime-ministers.import") }}',
        method: 'POST',
        data: {
            parliament_id: selectedPrimeMinister.parliament_id,
            prime_ministerships: primeMinisterships,
            _token: '{{ csrf_token() }}'
        },
        success: function(response) {
            hideLoading();
            if (response.success) {
                showAlert('Prime Minister imported successfully!', 'success');
                loadRecentImports();
                resetForm();
            } else {
                showAlert('Import failed: ' + response.message, 'danger');
            }
        },
        error: function(xhr) {
            hideLoading();
            showAlert('Failed to import Prime Minister', 'danger');
        }
    });
}

function loadRecentImports() {
    $.ajax({
        url: '{{ route("admin.prime-ministers.recent") }}',
        method: 'GET',
        success: function(response) {
            if (response.success) {
                displayRecentImports(response.data);
            }
        },
        error: function() {
            $('#recentImports').html('<div class="text-center text-muted">Failed to load recent imports</div>');
        }
    });
}

function displayRecentImports(imports) {
    if (imports.length === 0) {
        $('#recentImports').html('<div class="text-center text-muted">No recent imports</div>');
        return;
    }
    
    const content = imports.map(import => `
        <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
            <div>
                <h6 class="mb-1">${import.name}</h6>
                <small class="text-muted">${import.party} • ID: ${import.parliament_id}</small>
            </div>
            <small class="text-muted">${new Date(import.modified * 1000).toLocaleDateString()}</small>
        </div>
    `).join('');
    
    $('#recentImports').html(content);
}

function clearCache() {
    if (!confirm('Are you sure you want to clear the Parliament API cache?')) {
        return;
    }
    
    showLoading('Clearing cache...');
    
    $.ajax({
        url: '{{ route("admin.prime-ministers.clear-cache") }}',
        method: 'POST',
        data: {
            _token: '{{ csrf_token() }}'
        },
        success: function(response) {
            hideLoading();
            if (response.success) {
                showAlert('Cache cleared successfully', 'success');
            } else {
                showAlert('Failed to clear cache: ' + response.message, 'danger');
            }
        },
        error: function() {
            hideLoading();
            showAlert('Failed to clear cache', 'danger');
        }
    });
}

function resetForm() {
    selectedPrimeMinister = null;
    $('#importForm').hide();
    $('#noSelection').show();
    $('#previewCard').hide();
    $('#searchResultsCard').hide();
    $('#searchTerm').val('');
    $('#primeMinisterships').html(`
        <div class="prime-ministership-entry border rounded p-3 mb-2">
            <div class="row">
                <div class="col-md-4">
                    <label class="form-label">Start Date</label>
                    <input type="date" class="form-control prime-ministership-start" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">End Date</label>
                    <input type="date" class="form-control prime-ministership-end">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Ongoing</label>
                    <div class="form-check mt-2">
                        <input class="form-check-input prime-ministership-ongoing" type="checkbox" id="ongoing1">
                        <label class="form-check-label" for="ongoing1">
                            Still in office
                        </label>
                    </div>
                </div>
                <div class="col-md-1">
                    <button type="button" class="btn btn-outline-danger btn-sm mt-4" onclick="removePrimeMinistership(this)">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>
        </div>
    `);
    primeMinistershipCounter = 1;
}

function showLoading(message) {
    console.log('⏳ Showing loading modal:', message);
    $('#loadingMessage').text(message);
    $('#loadingModal').modal('show');
}

function hideLoading() {
    console.log('✅ Hiding loading modal');
    try {
        // Try Bootstrap 5 method first
        const modal = bootstrap.Modal.getInstance($('#loadingModal')[0]);
        if (modal) {
            console.log('🔧 Using Bootstrap 5 modal instance');
            modal.hide();
        } else {
            console.log('🔧 Using jQuery modal method');
            $('#loadingModal').modal('hide');
        }
    } catch (e) {
        console.error('❌ Error hiding modal with Bootstrap, using fallback:', e);
        // Fallback: remove modal backdrop and hide modal manually
        $('.modal-backdrop').remove();
        $('#loadingModal').hide();
        $('body').removeClass('modal-open');
        $('body').css('padding-right', ''); // Remove any padding added by Bootstrap
    }
    
    // Double-check and force hide if still visible
    setTimeout(() => {
        console.log('🔍 Checking modal visibility after hide attempt');
        console.log('🔍 Modal visible:', $('#loadingModal').is(':visible'));
        console.log('🔍 Modal display:', $('#loadingModal').css('display'));
        console.log('🔍 Modal backdrop exists:', $('.modal-backdrop').length > 0);
        console.log('🔍 Body has modal-open class:', $('body').hasClass('modal-open'));
        
        if ($('#loadingModal').is(':visible')) {
            console.log('🔧 Force hiding modal that is still visible');
            $('.modal-backdrop').remove();
            $('#loadingModal').hide();
            $('body').removeClass('modal-open');
            $('body').css('padding-right', '');
        }
    }, 100);
}

function showAlert(message, type) {
    // Create a Bootstrap alert
    const alert = $(`
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `);
    
    // Insert at the top of the page
    $('main').prepend(alert);
    
    // Auto-dismiss after 5 seconds
    setTimeout(() => {
        alert.alert('close');
    }, 5000);
}
</script>
@endpush 