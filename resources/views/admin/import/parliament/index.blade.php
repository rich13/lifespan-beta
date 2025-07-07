@extends('layouts.app')

@section('title', 'UK Parliament Explorer')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">
        <i class="bi bi-building me-2"></i>
        UK Parliament Explorer
    </h1>
    <a href="https://members-api.parliament.uk/index.html" target="_blank" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-box-arrow-up-right me-1"></i>
        API Docs
    </a>
</div>

<!-- Tabs -->
<ul class="nav nav-tabs mb-3" id="parliamentTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="api-tab" data-bs-toggle="tab" data-bs-target="#apiExplorer" type="button" role="tab" aria-controls="apiExplorer" aria-selected="true">API Explorer</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="sparql-tab" data-bs-toggle="tab" data-bs-target="#sparqlExplorer" type="button" role="tab" aria-controls="sparqlExplorer" aria-selected="false">SPARQL Explorer</button>
    </li>
</ul>

<div class="tab-content" id="parliamentTabsContent">
    <!-- API Explorer Tab -->
    <div class="tab-pane fade show active" id="apiExplorer" role="tabpanel" aria-labelledby="api-tab">
        <div class="row">
            <!-- Search Panel -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-search me-2"></i>
                            Search Members
                        </h5>
                    </div>
                    <div class="card-body">
                        <form id="searchForm">
                            <div class="mb-3">
                                <label for="memberName" class="form-label">Member Name</label>
                                <input type="text" class="form-control" id="memberName" name="name" 
                                       placeholder="e.g., starmer, churchill, thatcher" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="house" class="form-label">House</label>
                                <select class="form-select" id="house" name="house">
                                    <option value="1">House of Commons</option>
                                    <option value="2">House of Lords</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="resultsCount" class="form-label">Results Count</label>
                                <select class="form-select" id="resultsCount" name="take">
                                    <option value="10">10</option>
                                    <option value="20" selected>20</option>
                                    <option value="50">50</option>
                                </select>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-search me-1"></i>
                                Search
                            </button>
                        </form>
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

            <!-- Member Details Panel -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-person me-2"></i>
                            Member Details
                        </h5>
                    </div>
                    <div class="card-body">
                        <div id="memberDetails" class="text-center py-5">
                            <i class="bi bi-search text-muted mb-3" style="font-size: 3rem;"></i>
                            <p class="text-muted">Search for a member to view their details</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- SPARQL Explorer Tab -->
    <div class="tab-pane fade" id="sparqlExplorer" role="tabpanel" aria-labelledby="sparql-tab">
        <div class="row">
            <div class="col-12">
                <div class="card mb-3">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-terminal me-2"></i>SPARQL Explorer
                        </h5>
                        <small class="text-muted">Query the UK Parliament SPARQL endpoint</small>
                    </div>
                    <div class="card-body">
                        <form id="sparqlForm">
                            <div class="mb-3">
                                <label for="sparqlQuery" class="form-label">SPARQL Query</label>
                                <textarea class="form-control font-monospace" id="sparqlQuery" name="query" rows="8" placeholder="SELECT ?person ?givenName ?familyName WHERE {
  ?person a <https://id.parliament.uk/schema/Person> .
  ?person <https://id.parliament.uk/schema/personGivenName> ?givenName .
  ?person <https://id.parliament.uk/schema/personFamilyName> ?familyName .
  FILTER(CONTAINS(LCASE(?givenName), 'keir') || CONTAINS(LCASE(?familyName), 'starmer'))
}
LIMIT 10" required></textarea>
                            </div>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-success">
                                    <i class="bi bi-play-circle me-1"></i>Run Query
                                </button>
                                <button type="button" class="btn btn-secondary" id="sparqlClearResults">
                                    <i class="bi bi-trash me-1"></i>Clear Results
                                </button>
                                <div class="btn-group" role="group">
                                    <button type="button" class="btn btn-outline-info" onclick="loadExampleQuery('basic')">
                                        <i class="bi bi-lightbulb me-1"></i>Basic
                                    </button>
                                    <button type="button" class="btn btn-outline-info" onclick="loadExampleQuery('government')">
                                        <i class="bi bi-lightbulb me-1"></i>Government
                                    </button>
                                    <button type="button" class="btn btn-outline-info" onclick="loadExampleQuery('positions')">
                                        <i class="bi bi-lightbulb me-1"></i>Positions
                                    </button>
                                    <button type="button" class="btn btn-outline-info" onclick="loadExampleQuery('parties')">
                                        <i class="bi bi-lightbulb me-1"></i>Parties
                                    </button>
                                    <button type="button" class="btn btn-outline-warning" onclick="loadExampleQuery('test')">
                                        <i class="bi bi-bug me-1"></i>Test
                                    </button>
                                </div>
                                <button type="button" class="btn btn-outline-secondary" onclick="showMemberIdHelper()">
                                    <i class="bi bi-question-circle me-1"></i>Find Member ID
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <div class="card" id="sparqlResultsCard" style="display:none;">
                    <div class="card-header">
                        <h6 class="card-title mb-0">
                            <i class="bi bi-table me-2"></i>SPARQL Results
                        </h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                            <table class="table table-sm table-bordered table-hover mb-0" id="sparqlResultsTable">
                                <thead class="table-light sticky-top bg-light"></thead>
                                <tbody></tbody>
                            </table>
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
                <p class="mb-0" id="loadingMessage">Searching...</p>
            </div>
        </div>
    </div>
</div>

<!-- Member ID Helper Modal -->
<div class="modal fade" id="memberIdHelperModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Find Member ID for SPARQL Queries</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-3">Search for a member to get their Parliament ID for use in SPARQL queries.</p>
                
                <div class="input-group mb-3">
                    <input type="text" class="form-control" id="memberIdSearch" placeholder="Enter member name (e.g., Keir Starmer)">
                    <button class="btn btn-primary" type="button" onclick="searchForMemberId()">
                        <i class="bi bi-search me-1"></i>Search
                    </button>
                </div>
                
                <div id="memberIdResults" class="mt-3">
                    <!-- Results will appear here -->
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@push('styles')
<style>
.sparql-uri-link {
    color: #007bff;
    text-decoration: none;
    border-bottom: 1px dotted #007bff;
}
.sparql-uri-link:hover {
    color: #0056b3;
    text-decoration: underline;
}

.sparql-class-link {
    color: #28a745;
    text-decoration: none;
    border-bottom: 1px dotted #28a745;
    font-weight: 500;
}
.sparql-class-link:hover {
    color: #1e7e34;
    text-decoration: underline;
}

.sparql-property-link {
    color: #fd7e14;
    text-decoration: none;
    border-bottom: 1px dotted #fd7e14;
    font-weight: 500;
}
.sparql-property-link:hover {
    color: #e55a00;
    text-decoration: underline;
}

#queryHistory .badge {
    font-size: 0.75rem;
    transition: all 0.2s ease;
}
#queryHistory .badge:hover {
    transform: scale(1.05);
}
</style>
@endpush

@push('scripts')
<script>
// Load example queries function (defined outside document ready)
// Query history for drill-down navigation
let queryHistory = [];
let currentHistoryIndex = -1;

function buildDrillDownQuery(uri, currentQuery) {
    console.log('🔧 Building drill-down query for URI:', uri);
    
    // Check if this is a schema/ontology URI (class or property)
    if (uri.includes('/schema/')) {
        if (uri.includes('schema/Person') || uri.includes('schema/Member') || uri.includes('schema/Incumbency')) {
            // It's a class - show instances
            return `SELECT ?instance ?name WHERE { 
  ?instance a <${uri}> .
  OPTIONAL { ?instance <https://id.parliament.uk/schema/personGivenName> ?name }
} LIMIT 20`;
        } else {
            // It's likely a property - show usage
            return `SELECT ?subject ?object WHERE { 
  ?subject <${uri}> ?object 
} LIMIT 20`;
        }
    }
    
    // For regular resources, show their properties
    return `SELECT ?property ?value WHERE { 
  <${uri}> ?property ?value 
} ORDER BY ?property LIMIT 50`;
}

function addToQueryHistory(uri, query, type = 'resource') {
    // Remove any history after current position (if we're not at the end)
    if (currentHistoryIndex < queryHistory.length - 1) {
        queryHistory = queryHistory.slice(0, currentHistoryIndex + 1);
    }
    
    // Add new entry
    const historyEntry = {
        uri: uri,
        query: query,
        type: type,
        timestamp: new Date().toLocaleTimeString()
    };
    
    queryHistory.push(historyEntry);
    currentHistoryIndex = queryHistory.length - 1;
    
    console.log('📚 Added to query history:', historyEntry);
    updateQueryHistoryDisplay();
}

function updateQueryHistoryDisplay() {
    const historyContainer = $('#queryHistory');
    if (historyContainer.length === 0) {
        // Create history container if it doesn't exist
        $('#sparqlQuery').after(`
            <div class="mt-2">
                <small class="text-muted">Query History:</small>
                <div id="queryHistory" class="mt-1"></div>
            </div>
        `);
    }
    
    const container = $('#queryHistory');
    container.empty();
    
    if (queryHistory.length === 0) return;
    
    queryHistory.forEach((entry, index) => {
        const isCurrent = index === currentHistoryIndex;
        const badgeClass = isCurrent ? 'bg-primary' : 'bg-secondary';
        const badgeText = entry.type === 'class' ? 'Class' : 
                         entry.type === 'property' ? 'Property' : 'Resource';
        
        const historyItem = $(`
            <span class="badge ${badgeClass} me-1 mb-1" style="cursor: pointer;" 
                  title="${entry.query.substring(0, 100)}..." 
                  onclick="restoreQueryFromHistory(${index})">
                ${badgeText}: ${entry.uri.split('/').pop()}
                <small class="ms-1">${entry.timestamp}</small>
            </span>
        `);
        container.append(historyItem);
    });
}

function restoreQueryFromHistory(index) {
    if (index >= 0 && index < queryHistory.length) {
        const entry = queryHistory[index];
        console.log('🔄 Restoring query from history:', entry);
        $('#sparqlQuery').val(entry.query);
        currentHistoryIndex = index;
        updateQueryHistoryDisplay();
    }
}

function loadExampleQuery(type) {
    console.log('📝 loadExampleQuery called with type:', type);
    let query = '';
    
    switch(type) {
        case 'basic':
            query = `SELECT ?person ?givenName ?familyName ?mnisId WHERE {
  ?person a <https://id.parliament.uk/schema/Person> .
  ?person <https://id.parliament.uk/schema/personGivenName> ?givenName .
  ?person <https://id.parliament.uk/schema/personFamilyName> ?familyName .
  OPTIONAL { ?person <https://id.parliament.uk/schema/mnisId> ?mnisId }
}
ORDER BY ?familyName
LIMIT 10`;
            break;
            
        case 'government':
            query = `SELECT ?person ?roleName ?startDate ?endDate ?givenName ?familyName WHERE {
  ?incumbency a <https://id.parliament.uk/schema/GovernmentIncumbency> .
  ?incumbency <https://id.parliament.uk/schema/incumbencyHasMember> ?person .
  ?incumbency <https://id.parliament.uk/schema/governmentIncumbencyHasPosition> ?position .
  ?incumbency <https://id.parliament.uk/schema/incumbencyStartDate> ?startDate .
  OPTIONAL { ?incumbency <https://id.parliament.uk/schema/incumbencyEndDate> ?endDate }
  ?position <https://id.parliament.uk/schema/positionName> ?roleName .
  ?person <https://id.parliament.uk/schema/personGivenName> ?givenName .
  ?person <https://id.parliament.uk/schema/personFamilyName> ?familyName .
}
ORDER BY DESC(?startDate)
LIMIT 10`;
            break;
            
        case 'positions':
            query = `SELECT ?person ?positionName ?startDate ?endDate ?givenName ?familyName WHERE {
  ?incumbency a <https://id.parliament.uk/schema/Incumbency> .
  ?incumbency <https://id.parliament.uk/schema/incumbencyHasMember> ?person .
  ?incumbency <https://id.parliament.uk/schema/incumbencyHasPosition> ?position .
  ?incumbency <https://id.parliament.uk/schema/incumbencyStartDate> ?startDate .
  OPTIONAL { ?incumbency <https://id.parliament.uk/schema/incumbencyEndDate> ?endDate }
  ?position <https://id.parliament.uk/schema/positionName> ?positionName .
  ?person <https://id.parliament.uk/schema/personGivenName> ?givenName .
  ?person <https://id.parliament.uk/schema/personFamilyName> ?familyName .
}
ORDER BY DESC(?startDate)
LIMIT 10`;
            break;
            
        case 'parties':
            query = `SELECT ?person ?partyName ?startDate ?endDate ?givenName ?familyName WHERE {
  ?membership a <https://id.parliament.uk/schema/PartyMembership> .
  ?membership <https://id.parliament.uk/schema/partyMembershipHasMember> ?person .
  ?membership <https://id.parliament.uk/schema/partyMembershipHasParty> ?party .
  ?membership <https://id.parliament.uk/schema/membershipStartDate> ?startDate .
  OPTIONAL { ?membership <https://id.parliament.uk/schema/membershipEndDate> ?endDate }
  ?party <https://id.parliament.uk/schema/partyName> ?partyName .
  ?person <https://id.parliament.uk/schema/personGivenName> ?givenName .
  ?person <https://id.parliament.uk/schema/personFamilyName> ?familyName .
}
ORDER BY DESC(?startDate)
LIMIT 10`;
            break;
            
        case 'test':
            query = `SELECT ?person ?name WHERE {
  ?person a <https://id.parliament.uk/schema/Person> .
  ?person <https://id.parliament.uk/schema/personGivenName> ?name .
}
LIMIT 5`;
            break;
    }
    
    $('#sparqlQuery').val(query);
    console.log(`📝 Loaded ${type} example query`);
    console.log('📝 Query length:', query.length);
    console.log('📝 Textarea value set:', $('#sparqlQuery').val().substring(0, 100) + '...');
}

$(document).ready(function() {
    // Handle search form submission
    $('#searchForm').on('submit', function(e) {
        e.preventDefault();
        searchMembers();
    });

    // SPARQL Explorer logic
    $('#sparqlForm').on('submit', function(e) {
        e.preventDefault();
        runSparqlQuery($('#sparqlQuery').val());
    });
    $('#sparqlClearResults').on('click', function() {
        $('#sparqlResultsCard').hide();
        $('#sparqlResultsTable thead').empty();
        $('#sparqlResultsTable tbody').empty();
    });
    // Enhanced drill-down navigation for URIs in results
    $(document).on('click', '.sparql-uri-link', function(e) {
        e.preventDefault();
        const uri = $(this).data('uri');
        const currentQuery = $('#sparqlQuery').val();
        const drillDownQuery = buildDrillDownQuery(uri, currentQuery);
        
        console.log('🔍 Drilling down on URI:', uri);
        console.log('🔍 Current query:', currentQuery);
        console.log('🔍 New drill-down query:', drillDownQuery);
        
        $('#sparqlQuery').val(drillDownQuery);
        runSparqlQuery(drillDownQuery);
        
        // Add to query history
        addToQueryHistory(uri, drillDownQuery);
        
        // Switch to SPARQL tab
        $('#sparql-tab').tab('show');
    });
    
    // Handle property clicks (predicates) - show what uses this property
    $(document).on('click', '.sparql-property-link', function(e) {
        e.preventDefault();
        const property = $(this).data('uri');
        const query = `SELECT ?subject ?object WHERE { ?subject <${property}> ?object } LIMIT 20`;
        
        console.log('🔍 Exploring property usage:', property);
        $('#sparqlQuery').val(query);
        runSparqlQuery(query);
        addToQueryHistory(property, query, 'property');
        $('#sparql-tab').tab('show');
    });
    
    // Handle class/type clicks - show instances of this class
    $(document).on('click', '.sparql-class-link', function(e) {
        e.preventDefault();
        const classUri = $(this).data('uri');
        const query = `SELECT ?instance WHERE { ?instance a <${classUri}> } LIMIT 20`;
        
        console.log('🔍 Exploring class instances:', classUri);
        $('#sparqlQuery').val(query);
        runSparqlQuery(query);
        addToQueryHistory(classUri, query, 'class');
        $('#sparql-tab').tab('show');
    });
    
    // Test dropdown functionality
    $('#sparqlExampleQuery').on('click', function() {
        console.log('🔍 Load Example button clicked');
        console.log('🔍 Dropdown menu exists:', $('.dropdown-menu').length);
        console.log('🔍 Dropdown items found:', $('.dropdown-item[data-query-type]').length);
        $('.dropdown-item[data-query-type]').each(function(index) {
            console.log(`🔍 Dropdown item ${index}:`, $(this).text(), 'data-type:', $(this).data('query-type'));
        });
    });
    
    // Handle example query dropdown clicks
    $(document).on('click', '.dropdown-item[data-query-type]', function(e) {
        e.preventDefault();
        console.log('🔍 Dropdown item clicked:', $(this).text());
        console.log('🔍 Data query type:', $(this).data('query-type'));
        const type = $(this).data('query-type');
        console.log('🔍 Calling loadExampleQuery with type:', type);
        loadExampleQuery(type);
    });
    
    // Alternative approach - direct click handlers
    $(document).on('click', 'a[data-query-type="basic"]', function(e) {
        e.preventDefault();
        console.log('🔍 Basic query clicked directly');
        loadExampleQuery('basic');
    });
    
    $(document).on('click', 'a[data-query-type="government"]', function(e) {
        e.preventDefault();
        console.log('🔍 Government query clicked directly');
        loadExampleQuery('government');
    });
    
    // Ensure Bootstrap tabs work properly
    $('button[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
        console.log('Tab switched to:', e.target.id);
    });
});

function searchMembers() {
    const formData = {
        name: $('#memberName').val(),
        house: $('#house').val(),
        take: $('#resultsCount').val(),
        skip: 0,
        _token: '{{ csrf_token() }}'
    };
    
    console.log('🔍 Starting member search with data:', formData);
    showLoading('Searching members...');
    
    $.ajax({
        url: '{{ route("admin.import.parliament.search") }}',
        method: 'POST',
        data: formData,
        timeout: 30000, // 30 second timeout
        beforeSend: function() {
            console.log('📤 Sending search request...');
        },
        success: function(response) {
            console.log('✅ Search response received:', response);
            console.log('🔍 Response type:', typeof response);
            console.log('🔍 Response keys:', Object.keys(response));
            console.log('🔍 Response.success:', response.success);
            console.log('🔍 Response.data:', response.data);
            
            hideLoading();
            
            if (response.success) {
                console.log('📋 Displaying search results:', response.data.items);
                displaySearchResults(response.data.items);
            } else {
                console.error('❌ Search failed:', response.message);
                showAlert('Error: ' + (response.message || 'Unknown error occurred'), 'danger');
            }
        },
        error: function(xhr, textStatus, errorThrown) {
            console.error('❌ Search error:', {
                status: xhr.status,
                statusText: xhr.statusText,
                textStatus: textStatus,
                errorThrown: errorThrown,
                responseText: xhr.responseText,
                responseJSON: xhr.responseJSON
            });
            
            hideLoading();
            let errorMessage = 'Failed to search members';
            
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
            console.log('🏁 Search request completed');
            // Ensure loading is hidden even if success/error callbacks fail
            hideLoading();
        }
    });
}

function displaySearchResults(results) {
    console.log('📋 Displaying search results:', results);
    console.log('🔍 Results type:', typeof results);
    console.log('🔍 Results length:', results ? results.length : 'undefined');
    console.log('🔍 Container exists:', $('#searchResults').length > 0);
    
    const container = $('#searchResults');
    container.empty();
    
    if (!results || results.length === 0) {
        console.log('📭 No results found');
        container.append('<div class="list-group-item text-center text-muted">No members found</div>');
    } else {
        console.log(`📊 Found ${results.length} results`);
        results.forEach(function(member, index) {
            console.log(`🔍 Processing member ${index}:`, member);
            const value = member.value;
            console.log(`👤 Result ${index + 1}:`, value.nameDisplayAs, '(ID:', value.id, ')');
            const item = $(`
                <div class="list-group-item list-group-item-action" onclick="getMemberDetails(${value.id})">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="flex-grow-1">
                            <h6 class="mb-1">${value.nameDisplayAs}</h6>
                            <p class="mb-1 small text-muted">${value.nameFullTitle}</p>
                            <small class="text-muted">
                                ${value.latestParty?.name || 'Unknown Party'} • 
                                ${value.latestHouseMembership?.membershipFrom || 'Unknown Constituency'}
                            </small>
                        </div>
                        <small class="text-muted ms-2">ID: ${value.id}</small>
                    </div>
                </div>
            `);
            container.append(item);
        });
    }
    
    console.log('🔍 Showing search results card');
    $('#searchResultsCard').show();
    console.log('🔍 Search results card visible:', $('#searchResultsCard').is(':visible'));
}

function getMemberDetails(memberId) {
    console.log('👤 Getting member details for ID:', memberId);
    showLoading('Loading member details...');
    
    $.ajax({
        url: '{{ route("admin.import.parliament.get-member") }}',
        method: 'POST',
        data: {
            member_id: memberId,
            _token: '{{ csrf_token() }}'
        },
        timeout: 30000, // 30 second timeout
        beforeSend: function() {
            console.log('📤 Sending member details request...');
        },
        success: function(response) {
            console.log('✅ Member details response received:', response);
            console.log('🔍 Response type:', typeof response);
            console.log('🔍 Response keys:', Object.keys(response));
            console.log('🔍 Response.success:', response.success);
            console.log('🔍 Response.data:', response.data);
            
            hideLoading();
            
            if (response.success) {
                console.log('👤 Displaying member details:', response.data);
                displayMemberDetails(response.data);
            } else {
                console.error('❌ Member details failed:', response.message);
                showAlert('Error: ' + (response.message || 'Unknown error occurred'), 'danger');
            }
        },
        error: function(xhr, textStatus, errorThrown) {
            console.error('❌ Member details error:', {
                status: xhr.status,
                statusText: xhr.statusText,
                textStatus: textStatus,
                errorThrown: errorThrown,
                responseText: xhr.responseText,
                responseJSON: xhr.responseJSON
            });
            
            hideLoading();
            let errorMessage = 'Failed to load member details';
            
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
                errorMessage = 'Member not found';
            }
            
            showAlert(errorMessage, 'danger');
        },
        complete: function() {
            console.log('🏁 Member details request completed');
            // Ensure loading is hidden even if success/error callbacks fail
            hideLoading();
        }
    });
}

function displayMemberDetails(member) {
    console.log('👤 Displaying member details for:', member.name);
    const details = `
        <div class="row">
            <div class="col-md-8">
                <h4>${member.name}</h4>
                <p class="text-muted mb-3">${member.full_name}</p>
                
                <div class="row mb-3">
                    <div class="col-sm-6">
                        <strong>Party:</strong> ${member.party} (${member.party_abbreviation})
                    </div>
                    <div class="col-sm-6">
                        <strong>Gender:</strong> ${member.gender === 'M' ? 'Male' : 'Female'}
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-sm-6">
                        <strong>Constituency:</strong> ${member.constituency || 'N/A'}
                    </div>
                    <div class="col-sm-6">
                        <strong>Status:</strong> ${member.membership_status || 'N/A'}
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-sm-6">
                        <strong>Membership Start:</strong> ${formatDate(member.membership_start)}
                    </div>
                    <div class="col-sm-6">
                        <strong>Membership End:</strong> ${formatDate(member.membership_end)}
                    </div>
                </div>
            </div>
            <div class="col-md-4 text-center">
                ${member.thumbnail_url ? 
                    `<img src="${member.thumbnail_url}" alt="Member photo" class="img-fluid rounded mb-2" style="max-width: 150px;">` : 
                    '<div class="bg-light rounded p-4 mb-2"><i class="bi bi-person text-muted" style="font-size: 3rem;"></i></div>'
                }
                <small class="text-muted">Parliament ID: ${member.id}</small>
            </div>
        </div>
        
        <hr>
        
        <div class="mb-3">
            <h6>Biographical Synopsis</h6>
            <div class="bg-light p-3 rounded">
                ${member.synopsis ? member.synopsis.replace(/\n/g, '<br>') : 'No synopsis available'}
            </div>
        </div>
        
        <div class="mb-3">
            <h6>Raw API Data</h6>
            <details>
                <summary>Click to view raw JSON data</summary>
                <pre class="bg-light p-3 rounded mt-2" style="font-size: 0.8rem; max-height: 300px; overflow-y: auto;">${JSON.stringify(member.raw_data, null, 2)}</pre>
            </details>
        </div>
        
        <div class="d-flex gap-2">
            <button class="btn btn-outline-primary" onclick="importMember(${member.id})">
                <i class="bi bi-download me-1"></i>
                Import as Person
            </button>
            <button class="btn btn-outline-secondary" onclick="copyMemberData(${member.id})">
                <i class="bi bi-clipboard me-1"></i>
                Copy Data
            </button>
        </div>
    `;
    
    $('#memberDetails').html(details);
}

function formatDate(dateString) {
    if (!dateString) return 'N/A';
    try {
        return new Date(dateString).toLocaleDateString('en-GB');
    } catch (e) {
        return dateString;
    }
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
    console.log(`🚨 Showing ${type} alert:`, message);
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

function runSparqlQuery(query) {
    console.log('🔍 Running SPARQL query:', query.substring(0, 100) + '...');
    showLoading('Running SPARQL query...');
    
    $.ajax({
        url: '{{ route('admin.import.parliament.sparql') }}',
        method: 'POST',
        data: {
            query: query,
            _token: '{{ csrf_token() }}'
        },
        timeout: 60000, // 60 second timeout for complex queries
        beforeSend: function() {
            console.log('📤 Sending SPARQL query...');
        },
        success: function(response) {
            console.log('✅ SPARQL response received:', response);
            hideLoading();
            if (response.success && response.data && response.data.results) {
                console.log('📊 Rendering SPARQL results:', response.data.results.bindings.length, 'results');
                renderSparqlResults(response.data);
            } else {
                console.error('❌ SPARQL error:', response.message);
                showAlert('SPARQL error: ' + (response.message || 'Unknown error'), 'danger');
            }
        },
        error: function(xhr, textStatus, errorThrown) {
            console.error('❌ SPARQL request error:', {
                status: xhr.status,
                statusText: xhr.statusText,
                textStatus: textStatus,
                errorThrown: errorThrown,
                responseText: xhr.responseText
            });
            
            hideLoading();
            let msg = 'SPARQL request failed';
            
            if (textStatus === 'timeout') {
                msg = 'SPARQL query timed out - try a more specific query';
            } else if (xhr.responseJSON && xhr.responseJSON.message) {
                msg = xhr.responseJSON.message;
            } else if (xhr.status === 400) {
                msg = 'Invalid SPARQL query syntax';
            } else if (xhr.status === 500) {
                msg = 'SPARQL endpoint server error';
            }
            
            showAlert(msg, 'danger');
        },
        complete: function() {
            console.log('🏁 SPARQL request completed');
            hideLoading();
        }
    });
}

function renderSparqlResults(data) {
    console.log('🔍 Rendering SPARQL results:', data);
    console.log('🔍 Data structure:', Object.keys(data));
    console.log('🔍 Head:', data.head);
    console.log('🔍 Results:', data.results);
    
    const vars = data.head.vars;
    const results = data.results.bindings;
    console.log('🔍 Variables:', vars);
    console.log('🔍 Results count:', results.length);
    console.log('🔍 First result:', results[0]);
    
    const thead = $('#sparqlResultsTable thead');
    const tbody = $('#sparqlResultsTable tbody');
    thead.empty();
    tbody.empty();
    
    if (!vars || vars.length === 0) {
        console.log('❌ No variables found');
        thead.append('<tr><th class="text-center text-muted">No variables in query</th></tr>');
        $('#sparqlResultsCard').show();
        return;
    }
    
    if (!results || results.length === 0) {
        console.log('❌ No results found');
        let headerRow = '<tr>';
        vars.forEach(v => headerRow += `<th>${v}</th>`);
        headerRow += '</tr>';
        thead.append(headerRow);
        tbody.append('<tr><td colspan="' + vars.length + '" class="text-center text-muted">No results found</td></tr>');
        $('#sparqlResultsCard').show();
        return;
    }
    
    // Header
    let headerRow = '<tr>';
    vars.forEach(v => headerRow += `<th>${v}</th>`);
    headerRow += '</tr>';
    thead.append(headerRow);
    console.log('🔍 Added header row');
    
    // Rows
    results.forEach((row, index) => {
        console.log(`🔍 Processing row ${index}:`, row);
        let rowHtml = '<tr>';
        vars.forEach(v => {
            // Debug date-related fields
            if (v.toLowerCase().includes('date') && row[v]) {
                console.log(`🔍 Date field '${v}':`, {
                    value: row[v].value,
                    type: row[v].type,
                    datatype: row[v].datatype,
                    language: row[v]['xml:lang']
                });
            }
            let val = '';
            if (row[v]) {
                if (row[v].type === 'uri') {
                    const uri = row[v].value;
                    let cssClass = 'sparql-uri-link';
                    let title = 'Click to explore this resource';
                    
                    // Determine the type of URI for better drill-down
                    if (uri.includes('/schema/')) {
                        if (uri.includes('schema/Person') || uri.includes('schema/Member') || 
                            uri.includes('schema/Incumbency') || uri.includes('schema/GovernmentIncumbency')) {
                            cssClass = 'sparql-class-link';
                            title = 'Click to see instances of this class';
                        } else {
                            cssClass = 'sparql-property-link';
                            title = 'Click to see usage of this property';
                        }
                    }
                    
                    // Create a more readable display name
                    let displayName = uri;
                    if (uri.includes('/schema/')) {
                        displayName = uri.split('/').pop();
                    } else if (uri.includes('id.parliament.uk/')) {
                        displayName = uri.split('/').pop();
                    }
                    
                    val = `<a href="#" class="${cssClass}" data-uri="${uri}" title="${title}">${displayName}</a>`;
                } else if (row[v].type === 'literal') {
                    val = row[v].value;
                    // Format dates nicely - check multiple date patterns
                    const isDate = row[v].datatype && (
                        row[v].datatype.includes('date') || 
                        row[v].datatype.includes('time') ||
                        row[v].datatype === 'http://www.w3.org/2001/XMLSchema#date' ||
                        row[v].datatype === 'http://www.w3.org/2001/XMLSchema#dateTime'
                    );
                    
                    // Also check if the value looks like a date (ISO format, etc.)
                    const looksLikeDate = /^\d{4}-\d{2}-\d{2}/.test(val) || 
                                         /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/.test(val);
                    
                    if (isDate || looksLikeDate) {
                        try {
                            console.log('🔍 Attempting to parse date:', val, 'datatype:', row[v].datatype);
                            const date = new Date(val);
                            if (!isNaN(date.getTime())) {
                                val = date.toLocaleDateString('en-GB');
                                console.log('✅ Date parsed successfully:', val);
                            } else {
                                console.log('❌ Date parsing failed for:', val);
                            }
                        } catch (e) {
                            console.log('❌ Date parsing error for:', val, 'Error:', e.message);
                            // Keep original value if date parsing fails
                        }
                    }
                } else {
                    val = row[v].value;
                }
            }
            rowHtml += `<td style="max-width:350px;word-break:break-all;font-size:0.9rem;">${val}</td>`;
        });
        rowHtml += '</tr>';
        tbody.append(rowHtml);
    });
    
    console.log('🔍 Showing results card');
    $('#sparqlResultsCard').show();
    console.log('🔍 Results card visible:', $('#sparqlResultsCard').is(':visible'));
}

function importMember(memberId) {
    console.log('📥 Importing member:', memberId);
    
    if (!confirm('Import this Parliament member as a person in Lifespan?')) {
        return;
    }
    
    showLoading('Importing member...');
    
    $.ajax({
        url: '{{ route("admin.import.parliament.import-member") }}',
        method: 'POST',
        data: {
            member_id: memberId,
            _token: '{{ csrf_token() }}'
        },
        timeout: 30000,
        success: function(response) {
            hideLoading();
            if (response.success) {
                showAlert('Member imported successfully!', 'success');
                console.log('✅ Import successful:', response.data);
            } else {
                showAlert('Import failed: ' + response.message, 'danger');
                console.error('❌ Import failed:', response.message);
            }
        },
        error: function(xhr, textStatus, errorThrown) {
            hideLoading();
            console.error('❌ Import error:', {
                status: xhr.status,
                statusText: xhr.statusText,
                textStatus: textStatus,
                errorThrown: errorThrown
            });
            
            let errorMessage = 'Failed to import member';
            if (xhr.responseJSON && xhr.responseJSON.message) {
                errorMessage = xhr.responseJSON.message;
            }
            
            showAlert(errorMessage, 'danger');
        }
    });
}

function copyMemberData(memberId) {
    console.log('📋 Copying member data:', memberId);
    
    // Get the current member data from the display
    const memberName = $('#memberDetails h4').text();
    const memberData = {
        name: memberName,
        type: 'person',
        description: $('#memberDetails .bg-light').text().trim(),
        metadata: {
            source: 'UK Parliament API',
            parliament_id: memberId
        }
    };
    
    const yamlData = `name: ${memberName}
type: person
description: ${memberData.description}
metadata:
  source: UK Parliament API
  parliament_id: ${memberId}`;
    
    // Copy to clipboard
    navigator.clipboard.writeText(yamlData).then(function() {
        showAlert('Member data copied to clipboard!', 'success');
        console.log('✅ Member data copied to clipboard');
    }).catch(function(err) {
        console.error('❌ Failed to copy to clipboard:', err);
        showAlert('Failed to copy to clipboard', 'danger');
    });
}

function showMemberIdHelper() {
    $('#memberIdHelperModal').modal('show');
}

function searchForMemberId() {
    const searchTerm = $('#memberIdSearch').val();
    if (!searchTerm) {
        showAlert('Please enter a member name', 'warning');
        return;
    }
    
    console.log('🔍 Searching for member ID:', searchTerm);
    
    $.ajax({
        url: '{{ route("admin.import.parliament.search") }}',
        method: 'POST',
        data: {
            name: searchTerm,
            house: '1',
            take: 10,
            skip: 0,
            _token: '{{ csrf_token() }}'
        },
        success: function(response) {
            if (response.success && response.data.items) {
                displayMemberIdResults(response.data.items);
            } else {
                $('#memberIdResults').html('<div class="alert alert-warning">No members found</div>');
            }
        },
        error: function() {
            $('#memberIdResults').html('<div class="alert alert-danger">Search failed</div>');
        }
    });
}

function displayMemberIdResults(members) {
    let html = '<div class="list-group">';
    
    members.forEach(function(member) {
        const value = member.value;
        const memberId = value.id;
        const parliamentUri = `https://id.parliament.uk/${memberId}`;
        
        html += `
            <div class="list-group-item">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-1">${value.nameDisplayAs}</h6>
                        <small class="text-muted">${value.nameFullTitle}</small>
                    </div>
                    <div class="text-end">
                        <small class="text-muted d-block">ID: ${memberId}</small>
                        <small class="text-muted d-block">URI: ${parliamentUri}</small>
                        <button class="btn btn-sm btn-outline-primary mt-1" onclick="copyMemberId('${memberId}', '${parliamentUri}')">
                            <i class="bi bi-clipboard me-1"></i>Copy ID
                        </button>
                    </div>
                </div>
            </div>
        `;
    });
    
    html += '</div>';
    $('#memberIdResults').html(html);
}

function copyMemberId(memberId, parliamentUri) {
    const textToCopy = `Member ID: ${memberId}\nParliament URI: ${parliamentUri}`;
    
    navigator.clipboard.writeText(textToCopy).then(function() {
        showAlert('Member ID copied to clipboard!', 'success');
        console.log('✅ Member ID copied:', memberId);
    }).catch(function(err) {
        console.error('❌ Failed to copy member ID:', err);
        showAlert('Failed to copy member ID', 'danger');
    });
}
</script>
@endpush 