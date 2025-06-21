@extends('layouts.app')

@section('title', 'Friends & Relationships')

@section('content')
<div class="container-fluid">
    <div class="row">
        <!-- Main Content Area -->
        <div class="col-md-9">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h1 class="h3 mb-0">
                    <i class="bi bi-people-fill text-primary me-2"></i>
                    Friends & Relationships
                </h1>
                <div class="btn-group" role="group">
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="toggleNodeType('current-user')" data-type="current-user">
                        <i class="bi bi-person-fill"></i> You
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="toggleNodeType('friend')" data-type="friend">
                        <i class="bi bi-person"></i> Friends
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="toggleNodeType('relationship')" data-type="relationship">
                        <i class="bi bi-heart"></i> Relationships
                    </button>
                </div>
            </div>

            <!-- Network Visualization -->
            <div class="card">
                <div class="card-body p-0">
                    <div id="friends-network" style="width: 100%; height: 600px; position: relative;">
                        <!-- D3.js visualization will be rendered here -->
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-md-3">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-info-circle me-2"></i>
                        Person Details
                    </h5>
                </div>
                <div class="card-body" id="info-panel">
                    <div class="text-center text-muted mt-4">
                        <svg class="mx-auto h-12 w-12 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                        <h3 class="mt-2 text-sm font-medium">No person selected</h3>
                        <p class="mt-1 text-sm">Click on a person to see their details.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Person Modal -->
<div class="modal fade" id="addPersonModal" tabindex="-1" aria-labelledby="addPersonModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addPersonModalLabel">
                    <i class="bi bi-person-plus me-2"></i>
                    Add Person
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addPersonForm">
                    <!-- Search Section -->
                    <div class="mb-4">
                        <label for="personSearch" class="form-label">Search for existing person</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="personSearch" placeholder="Type to search for existing people...">
                            <button class="btn btn-outline-secondary" type="button" onclick="searchPeople()">
                                <i class="bi bi-search"></i>
                            </button>
                        </div>
                        <div id="searchResults" class="mt-2" style="display: none;">
                            <!-- Search results will be populated here -->
                        </div>
                    </div>

                    <!-- Divider -->
                    <div class="text-center mb-3">
                        <span class="text-muted">- or -</span>
                    </div>

                    <!-- Create New Person Section -->
                    <div id="createNewPersonSection">
                        <h6 class="mb-3">Create New Person</h6>
                        <div class="mb-3">
                            <label for="personName" class="form-label">Person's Name</label>
                            <input type="text" class="form-control" id="personName" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Connection Type</label>
                            <div class="btn-group w-100" role="group">
                                <input type="radio" class="btn-check" name="connectionType" id="friend" value="friend" checked>
                                <label class="btn btn-outline-primary" for="friend">
                                    <i class="bi bi-person me-1"></i>Friend
                                </label>
                                
                                <input type="radio" class="btn-check" name="connectionType" id="relationship" value="relationship">
                                <label class="btn btn-outline-danger" for="relationship">
                                    <i class="bi bi-heart me-1"></i>Relationship
                                </label>
                            </div>
                        </div>
                        <div class="mb-3" id="relationshipTypeGroup" style="display: none;">
                            <label class="form-label">Relationship Type</label>
                            <div class="btn-group w-100" role="group">
                                <input type="radio" class="btn-check" name="relationshipType" id="partner" value="partner" checked>
                                <label class="btn btn-outline-danger" for="partner">
                                    <i class="bi bi-heart-fill me-1"></i>Partner
                                </label>
                                
                                <input type="radio" class="btn-check" name="relationshipType" id="spouse" value="spouse">
                                <label class="btn btn-outline-danger" for="spouse">
                                    <i class="bi bi-people-fill me-1"></i>Spouse
                                </label>
                                
                                <input type="radio" class="btn-check" name="relationshipType" id="dating" value="dating">
                                <label class="btn btn-outline-warning" for="dating">
                                    <i class="bi bi-heart me-1"></i>Dating
                                </label>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="createPerson()">
                    <i class="bi bi-plus"></i> Add Person
                </button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script src="https://d3js.org/d3.v7.min.js"></script>
<script>
// Initialize the page
document.addEventListener('DOMContentLoaded', function() {
    loadFriendsData();
    addFloatingActionButton();
    
    // Add event listeners for connection type radio buttons
    document.querySelectorAll('input[name="connectionType"]').forEach(radio => {
        radio.addEventListener('change', function() {
            const relationshipGroup = document.getElementById('relationshipTypeGroup');
            if (this.value === 'relationship') {
                relationshipGroup.style.display = 'block';
            } else {
                relationshipGroup.style.display = 'none';
            }
        });
    });
    
    // Add live search functionality
    const searchInput = document.getElementById('personSearch');
    let searchTimeout;
    
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        const query = this.value.trim();
        
        if (query.length >= 2) {
            searchTimeout = setTimeout(() => {
                searchPeople();
            }, 300);
        } else {
            hideSearchResults();
        }
    });
});

// Global variables
let friendsData = null;
let selectedPersonId = null;
let selectedExistingPerson = null;
let currentUserId = '{{ Auth::user()->personalSpan->id ?? "" }}';

// Load friends data from the server
async function loadFriendsData() {
    try {
        const response = await fetch('/friends/data');
        if (!response.ok) {
            throw new Error('Failed to load friends data');
        }
        
        friendsData = await response.json();
        renderFriendsNetwork(friendsData);
        
        // Automatically select the current user
        if (currentUserId && friendsData.nodes) {
            const currentUser = friendsData.nodes.find(node => node.id === currentUserId);
            if (currentUser) {
                showPersonInfo(currentUser);
                selectedPersonId = currentUserId;
            }
        }
        
    } catch (error) {
        console.error('Error loading friends data:', error);
        alert('Failed to load friends data: ' + error.message);
    }
}

// Render the friends network visualization
function renderFriendsNetwork(data) {
    const container = document.getElementById('friends-network');
    container.innerHTML = '';
    
    const width = container.clientWidth;
    const height = container.clientHeight;
    
    // Create SVG
    const svg = d3.select('#friends-network')
        .append('svg')
        .attr('width', width)
        .attr('height', height);
    
    // Add zoom behavior
    const zoom = d3.zoom()
        .scaleExtent([0.1, 3])
        .on('zoom', (event) => {
            svg.select('g').attr('transform', event.transform);
        });
    
    svg.call(zoom);
    
    // Create main group
    const g = svg.append('g');
    
    // Create force simulation
    const simulation = d3.forceSimulation(data.nodes)
        .force('link', d3.forceLink(data.links).id(d => d.id).distance(150))
        .force('charge', d3.forceManyBody().strength(-300))
        .force('center', d3.forceCenter(width / 2, height / 2))
        .force('collision', d3.forceCollide().radius(50));
    
    // Create links
    const link = g.append('g')
        .selectAll('line')
        .data(data.links)
        .enter().append('line')
        .attr('stroke', d => getLinkColor(d.type))
        .attr('stroke-opacity', 0.6)
        .attr('stroke-width', d => getLinkWidth(d.type));
    
    // Create nodes
    const node = g.append('g')
        .selectAll('g')
        .data(data.nodes)
        .enter().append('g')
        .attr('class', 'node')
        .call(d3.drag()
            .on('start', dragstarted)
            .on('drag', dragged)
            .on('end', dragended));
    
    // Add circles to nodes
    node.append('circle')
        .attr('r', d => getNodeRadius(d))
        .attr('fill', d => getNodeColor(d))
        .attr('stroke', '#fff')
        .attr('stroke-width', 2);
    
    // Add labels to nodes
    node.append('text')
        .text(d => d.name)
        .attr('text-anchor', 'middle')
        .attr('dy', '.35em')
        .attr('font-size', '12px')
        .attr('fill', '#333')
        .style('pointer-events', 'none');
    
    // Add click handlers
    node.on('click', function(event, d) {
        showPersonInfo(d);
    });
    
    // Highlight current user's node
    if (currentUserId) {
        const currentUserNode = node.filter(d => d.id === currentUserId);
        currentUserNode.select('circle')
            .attr('stroke', '#000')
            .attr('stroke-width', 3);
    }
    
    // Update positions on simulation tick
    simulation.on('tick', () => {
        link
            .attr('x1', d => d.source.x)
            .attr('y1', d => d.source.y)
            .attr('x2', d => d.target.x)
            .attr('y2', d => d.target.y);
        
        node
            .attr('transform', d => `translate(${d.x},${d.y})`);
    });
    
    // Drag functions
    function dragstarted(event, d) {
        if (!event.active) simulation.alphaTarget(0.3).restart();
        d.fx = d.x;
        d.fy = d.y;
    }
    
    function dragged(event, d) {
        d.fx = event.x;
        d.fy = event.y;
    }
    
    function dragended(event, d) {
        if (!event.active) simulation.alphaTarget(0);
        d.fx = null;
        d.fy = null;
    }
}

// Get node radius based on type
function getNodeRadius(d) {
    switch(d.type) {
        case 'current-user': return 25;
        case 'friend': return 20;
        case 'relationship': return 20;
        default: return 15;
    }
}

// Get node color based on type
function getNodeColor(d) {
    switch(d.type) {
        case 'current-user': return '#007bff';
        case 'friend': return '#28a745';
        case 'relationship': return '#dc3545';
        default: return '#6c757d';
    }
}

// Get link color based on type
function getLinkColor(type) {
    switch(type) {
        case 'friend': return '#28a745';
        case 'relationship': return '#dc3545';
        default: return '#999';
    }
}

// Get link width based on type
function getLinkWidth(type) {
    switch(type) {
        case 'friend': return 2;
        case 'relationship': return 3;
        default: return 2;
    }
}

// Show person information in the sidebar
function showPersonInfo(personData) {
    selectedPersonId = personData.id;
    
    const infoPanel = document.getElementById('info-panel');
    
    // Get person relationships from the current graph data
    const familyData = window.familyData || friendsData;
    
    // Find connections of this person
    const connections = familyData.links
        .filter(link => {
            const sourceId = typeof link.source === 'object' ? link.source.id : link.source;
            const targetId = typeof link.target === 'object' ? link.target.id : link.target;
            return (sourceId === personData.id || targetId === personData.id);
        })
        .map(link => {
            const sourceId = typeof link.source === 'object' ? link.source.id : link.source;
            const targetId = typeof link.target === 'object' ? link.target.id : link.target;
            const otherId = sourceId === personData.id ? targetId : sourceId;
            const otherPerson = familyData.nodes.find(node => node.id === otherId);
            return {
                person: otherPerson,
                type: link.type
            };
        })
        .filter(connection => connection.person);
    
    // Group connections by type
    const friends = connections.filter(c => c.type === 'friend').map(c => c.person);
    const relationships = connections.filter(c => c.type === 'relationship').map(c => c.person);
    
    let connectionsHtml = '';
    
    if (friends.length > 0) {
        connectionsHtml += `
            <div class="card mb-3">
                <div class="card-body">
                    <h6 class="card-title">
                        <i class="bi bi-people-fill text-success me-1"></i>
                        Friends (${friends.length})
                    </h6>
                    <ul class="list-unstyled mb-0">
                        ${friends.map(friend => `
                            <li class="mb-2">
                                <a href="/spans/${friend.id}" class="text-decoration-none d-inline-flex align-items-center gap-1">
                                    <i class="bi bi-person-fill"></i>
                                    <strong>${friend.name}</strong>
                                </a>
                            </li>
                        `).join('')}
                    </ul>
                </div>
            </div>
        `;
    }
    
    if (relationships.length > 0) {
        connectionsHtml += `
            <div class="card mb-3">
                <div class="card-body">
                    <h6 class="card-title">
                        <i class="bi bi-heart-fill text-danger me-1"></i>
                        Relationships (${relationships.length})
                    </h6>
                    <ul class="list-unstyled mb-0">
                        ${relationships.map(relationship => `
                            <li class="mb-2">
                                <a href="/spans/${relationship.id}" class="text-decoration-none d-inline-flex align-items-center gap-1">
                                    <i class="bi bi-heart"></i>
                                    <strong>${relationship.name}</strong>
                                </a>
                            </li>
                        `).join('')}
                    </ul>
                </div>
            </div>
        `;
    }
    
    if (connections.length === 0) {
        connectionsHtml = `
            <div class="card mb-3">
                <div class="card-body">
                    <h6 class="card-title">
                        <i class="bi bi-people-fill text-muted me-1"></i>
                        No connections yet
                    </h6>
                    <p class="text-muted small">This person doesn't have any friends or relationships yet.</p>
                </div>
            </div>
        `;
    }
    
    infoPanel.innerHTML = `
        <div class="text-center mb-3">
            <div class="rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                <i class="bi bi-person-fill fs-4"></i>
            </div>
            <h5 class="mt-2 mb-1">${personData.name}</h5>
            <span class="badge bg-${getBadgeColor(personData.type)}">${getTypeLabel(personData.type)}</span>
        </div>
        
        ${connectionsHtml}
        
        <div class="d-grid gap-2">
            <button type="button" class="btn btn-outline-primary btn-sm" onclick="showAddPersonModal()">
                <i class="bi bi-person-plus me-1"></i>Add Person
            </button>
            <a href="/spans/${personData.id}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-eye me-1"></i>View Profile
            </a>
        </div>
    `;
}

// Get badge color for person type
function getBadgeColor(type) {
    switch(type) {
        case 'current-user': return 'primary';
        case 'friend': return 'success';
        case 'relationship': return 'danger';
        default: return 'secondary';
    }
}

// Get type label
function getTypeLabel(type) {
    switch(type) {
        case 'current-user': return 'You';
        case 'friend': return 'Friend';
        case 'relationship': return 'Relationship';
        default: return 'Unknown';
    }
}

// Add floating action button
function addFloatingActionButton() {
    const fab = document.createElement('button');
    fab.className = 'btn btn-primary rounded-circle position-fixed';
    fab.style.cssText = 'bottom: 20px; right: 20px; width: 60px; height: 60px; z-index: 1000; box-shadow: 0 4px 12px rgba(0,0,0,0.15);';
    fab.innerHTML = '<i class="bi bi-person-plus fs-4"></i>';
    fab.onclick = showAddPersonModal;
    fab.title = 'Add Person';
    
    document.body.appendChild(fab);
}

// Show add person modal
function showAddPersonModal() {
    const modal = new bootstrap.Modal(document.getElementById('addPersonModal'));
    modal.show();
    
    // Reset form and state
    document.getElementById('addPersonForm').reset();
    document.getElementById('relationshipTypeGroup').style.display = 'none';
    document.getElementById('personSearch').value = '';
    hideSearchResults();
    selectedExistingPerson = null;
}

// Search for people
async function searchPeople() {
    const query = document.getElementById('personSearch').value.trim();
    
    if (query.length < 2) {
        hideSearchResults();
        return;
    }
    
    console.log('Searching for:', query);
    
    try {
        const response = await fetch(`/spans/api/search?q=${encodeURIComponent(query)}&type=person&exclude_connected=true`);
        console.log('Search response status:', response.status);
        
        if (!response.ok) {
            throw new Error(`Search failed: ${response.status}`);
        }
        
        const results = await response.json();
        console.log('Search results:', results);
        displaySearchResults(results);
        
    } catch (error) {
        console.error('Search error:', error);
        hideSearchResults();
        
        // Show error message to user
        const resultsContainer = document.getElementById('searchResults');
        resultsContainer.innerHTML = `
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle me-2"></i>
                Search failed: ${error.message}
            </div>
        `;
        resultsContainer.style.display = 'block';
    }
}

// Display search results
function displaySearchResults(results) {
    const resultsContainer = document.getElementById('searchResults');
    
    if (results.length === 0) {
        resultsContainer.innerHTML = `
            <div class="alert alert-info">
                <i class="bi bi-info-circle me-2"></i>
                No existing people found. You can create a new person below.
            </div>
        `;
        resultsContainer.style.display = 'block';
        return;
    }
    
    let html = '<div class="list-group">';
    
    results.forEach(person => {
        // Don't show the current user or already connected people
        const currentUserId = '{{ Auth::user()->personalSpan->id ?? "" }}';
        if (person.id === currentUserId) {
            return;
        }
        
        html += `
            <button type="button" class="list-group-item list-group-item-action" 
                    onclick="selectPerson('${person.id}', '${person.name.replace(/'/g, "\\'")}')">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <i class="bi bi-person-fill me-2"></i>
                        <strong>${person.name}</strong>
                    </div>
                    <span class="badge bg-secondary">${person.type_name}</span>
                </div>
            </button>
        `;
    });
    
    html += '</div>';
    resultsContainer.innerHTML = html;
    resultsContainer.style.display = 'block';
}

// Select an existing person
function selectPerson(personId, personName) {
    selectedExistingPerson = { id: personId, name: personName };
    
    // Update the search input to show the selected person
    document.getElementById('personSearch').value = personName;
    
    // Hide search results
    hideSearchResults();
    
    // Show a success message
    const resultsContainer = document.getElementById('searchResults');
    resultsContainer.innerHTML = `
        <div class="alert alert-success">
            <i class="bi bi-check-circle me-2"></i>
            Selected: <strong>${personName}</strong>
        </div>
    `;
    resultsContainer.style.display = 'block';
}

// Hide search results
function hideSearchResults() {
    document.getElementById('searchResults').style.display = 'none';
}

// Create a new person or connect to existing person
async function createPerson() {
    const connectionType = document.querySelector('input[name="connectionType"]:checked').value;
    const relationshipType = document.querySelector('input[name="relationshipType"]:checked')?.value || 'partner';
    
    let personId;
    let personName;
    
    // Check if we're connecting to an existing person
    if (selectedExistingPerson) {
        personId = selectedExistingPerson.id;
        personName = selectedExistingPerson.name;
    } else {
        // Creating a new person
        const name = document.getElementById('personName').value.trim();
        if (!name) {
            alert('Please enter a name for the person or select an existing person');
            return;
        }
        
        try {
            // Create the person span
            const spanData = {
                name: name,
                type_id: 'person',
                state: 'placeholder',
                metadata: {}
            };
            
            const spanResponse = await fetch('/spans', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify(spanData)
            });
            
            if (!spanResponse.ok) {
                throw new Error('Failed to create person span');
            }
            
            const personSpan = await spanResponse.json();
            personId = personSpan.id;
            personName = name;
            
        } catch (error) {
            console.error('Error creating person:', error);
            alert('Error creating person: ' + error.message);
            return;
        }
    }
    
    try {
        // Create the connection
        const connectionData = {
            person1_id: selectedPersonId || '{{ Auth::user()->personalSpan->id ?? "" }}',
            person2_id: personId,
            connection_type: connectionType,
            relationship_type: connectionType === 'relationship' ? relationshipType : null
        };
        
        const connectionResponse = await fetch('/api/friends/connections', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify(connectionData)
        });
        
        if (!connectionResponse.ok) {
            throw new Error('Failed to create connection');
        }
        
        // Close modal and refresh
        bootstrap.Modal.getInstance(document.getElementById('addPersonModal')).hide();
        document.getElementById('addPersonForm').reset();
        document.getElementById('relationshipTypeGroup').style.display = 'none';
        
        // Reload friends data
        await loadFriendsData();
        
        // Show success message
        const action = selectedExistingPerson ? 'connected to' : 'added';
        alert(`Successfully ${action} ${personName}!`);
        
    } catch (error) {
        console.error('Error creating connection:', error);
        alert('Error creating connection: ' + error.message);
    }
}

// Toggle node visibility by type
function toggleNodeType(type) {
    const button = document.querySelector(`[data-type="${type}"]`);
    button.classList.toggle('active');
    button.classList.toggle('btn-outline-secondary');
    button.classList.toggle('btn-secondary');
    
    console.log('Toggling node type:', type);
}
</script>
@endpush

@push('styles')
<style>
.btn-group .btn.active {
    background-color: #6c757d;
    border-color: #6c757d;
    color: white;
}

.node {
    cursor: pointer;
}

.node:hover circle {
    stroke-width: 3px;
    stroke: #000;
}

#friends-network {
    background: #f8f9fa;
    border-radius: 0.375rem;
}

/* Search results styling */
#searchResults {
    max-height: 300px;
    overflow-y: auto;
    border: 1px solid #dee2e6;
    border-radius: 0.375rem;
}

#searchResults .list-group-item {
    border-left: none;
    border-right: none;
    border-radius: 0;
}

#searchResults .list-group-item:first-child {
    border-top: none;
}

#searchResults .list-group-item:last-child {
    border-bottom: none;
}

#searchResults .list-group-item:hover {
    background-color: #f8f9fa;
}

/* Modal improvements */
.modal-lg {
    max-width: 600px;
}

#createNewPersonSection {
    border-top: 1px solid #dee2e6;
    padding-top: 1rem;
}
</style>
@endpush 