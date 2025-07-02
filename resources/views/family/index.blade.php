@extends('layouts.app')

@section('page_title')
    Family Tree
@endsection

@section('page_tools')
    <div class="d-flex gap-2">
        <button class="btn btn-sm btn-outline-primary" onclick="resetZoom()">
            <i class="bi bi-arrows-fullscreen me-1"></i>Reset View
        </button>
    </div>
@endsection

@push('styles')
<style>
    .node {
        cursor: pointer;
        transition: all 0.3s ease;
        stroke-width: 1px; /* Thinner borders like Bootstrap buttons */
    }

    .node:hover {
        stroke-width: 2px;
        filter: brightness(1.1);
        transform: scale(1.02);
    }

    .node.selected {
        stroke-width: 2px;
        stroke: #000;
        filter: brightness(1.15);
        transform: scale(1.05);
        box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25); /* Bootstrap focus ring */
    }

    .node.active {
        stroke-width: 2px;
        stroke: #000;
        filter: brightness(1.1);
    }

    .node.inactive {
        stroke-width: 7px; /* Thicker white border for inactive nodes */
        stroke: #fff;
        filter: brightness(0.7); /* Dim the node to show it's inactive */
        opacity: 0.6;
    }

    .link {
        stroke: #6c757d;
        stroke-width: 2px;
        fill: none;
        opacity: 0.7;
    }

    .tooltip {
        position: absolute;
        background: rgba(0, 0, 0, 0.8);
        color: white;
        padding: 8px 12px;
        border-radius: 4px;
        font-size: 12px;
        pointer-events: none;
        z-index: 1000;
        max-width: 200px;
    }

    .no-family-message {
        text-align: center;
        padding: 2rem;
        color: #6c757d;
    }

    .loading {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        color: #6c757d;
    }

    .node-text {
        font-size: 11px;
        font-weight: 500;
        text-anchor: middle;
        dominant-baseline: middle;
        pointer-events: none;
        fill: #fff;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3); /* Better text readability */
    }

    /* Family tree specific styles */
    .family-tree-container {
        height: calc(100vh - 200px);
        min-height: 500px;
        position: relative;
    }

    .family-tree-svg {
        width: 100%;
        height: 100%;
    }

    .info-panel {
        height: calc(100vh - 200px);
        min-height: 500px;
        overflow-y: auto;
        padding: 1rem;
    }

    .info-panel .card {
        margin-bottom: 1rem;
    }

    .info-panel .card:last-child {
        margin-bottom: 0;
    }

    /* Toggle badge styles */
    .toggle-badge {
        transition: all 0.3s ease;
        user-select: none;
    }

    .toggle-badge:hover {
        opacity: 0.8;
        transform: scale(1.05);
    }

    .toggle-badge.inactive {
        background-color: #6c757d !important;
        opacity: 0.6;
    }
</style>
@endpush

@section('content')
<div class="row">
    <!-- Left column: Family Tree -->
    <div class="col-lg-8">
        @if($message)
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-yellow-800">No Family Relationships Found</h3>
                        <div class="mt-2 text-sm text-yellow-700">
                            <p>{{ $message }}</p>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        @if(!$familyData || empty($familyData['nodes']))
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-6">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-blue-800">No Family Relationships Found</h3>
                        <div class="mt-2 text-sm text-blue-700">
                            <p>No family relationships have been added for {{ $familyData['name'] ?? 'you' }} yet.</p>
                            <p class="mt-1">To see your family tree, you'll need to add family relationships through the Spans section.</p>
                        </div>
                    </div>
                </div>
            </div>
        @else
            <div class="bg-white rounded border family-tree-container">
                <div id="family-tree" class="w-100 h-100"></div>
            </div>
        @endif
    </div>

    <!-- Right column: Information Panel -->
    <div class="col-lg-4">
        <div class="bg-light rounded border p-4 info-panel">
            <!-- Color Key Section -->
            <div class="mb-4">
                <h5 class="card-title mb-3">Family Tree Key</h5>
                <div class="d-flex flex-wrap gap-2">
                    @php
                        $existingTypes = collect($familyData['nodes'])->pluck('type')->unique();
                    @endphp
                    
                    @if($existingTypes->contains('current-user'))
                        <span class="badge toggle-badge active" data-type="current-user" style="background-color: #3B82F6; color: white; cursor: pointer;">Current User</span>
                    @endif
                    
                    @if($existingTypes->contains('parent'))
                        <span class="badge toggle-badge active" data-type="parent" style="background-color: #10B981; color: white; cursor: pointer;">Parent</span>
                    @endif
                    
                    @if($existingTypes->contains('grandparent'))
                        <span class="badge toggle-badge active" data-type="grandparent" style="background-color: #8B5CF6; color: white; cursor: pointer;">Grandparent</span>
                    @endif
                    
                    @if($existingTypes->contains('great-grandparent'))
                        <span class="badge toggle-badge active" data-type="great-grandparent" style="background-color: #6B21A8; color: white; cursor: pointer;">Great-Grandparent</span>
                    @endif
                    
                    @if($existingTypes->contains('sibling'))
                        <span class="badge toggle-badge active" data-type="sibling" style="background-color: #F59E0B; color: white; cursor: pointer;">Sibling</span>
                    @endif
                    
                    @if($existingTypes->contains('uncle-aunt'))
                        <span class="badge toggle-badge active" data-type="uncle-aunt" style="background-color: #F97316; color: white; cursor: pointer;">Uncle/Aunt</span>
                    @endif
                    
                    @if($existingTypes->contains('cousin'))
                        <span class="badge toggle-badge active" data-type="cousin" style="background-color: #EAB308; color: white; cursor: pointer;">Cousin</span>
                    @endif
                    
                    @if($existingTypes->contains('niece-nephew'))
                        <span class="badge toggle-badge active" data-type="niece-nephew" style="background-color: #FCD34D; color: white; cursor: pointer;">Niece/Nephew</span>
                    @endif
                    
                    @if($existingTypes->contains('child'))
                        <span class="badge toggle-badge active" data-type="child" style="background-color: #EF4444; color: white; cursor: pointer;">Child</span>
                    @endif
                    
                    @if($existingTypes->contains('grandchild'))
                        <span class="badge toggle-badge active" data-type="grandchild" style="background-color: #EC4899; color: white; cursor: pointer;">Grandchild</span>
                    @endif
                    
                    @if($existingTypes->contains('great-grandchild'))
                        <span class="badge toggle-badge active" data-type="great-grandchild" style="background-color: #BE185D; color: white; cursor: pointer;">Great-Grandchild</span>
                    @endif
                    
                    @if($existingTypes->contains('ancestor'))
                        <span class="badge toggle-badge active" data-type="ancestor" style="background-color: #7C3AED; color: white; cursor: pointer;">Ancestor</span>
                    @endif
                    
                    @if($existingTypes->contains('descendant'))
                        <span class="badge toggle-badge active" data-type="descendant" style="background-color: #FB7185; color: white; cursor: pointer;">Descendant</span>
                    @endif
                </div>
            </div>
            
            <hr class="my-4">
            
            <!-- Node Info Section -->
            <div id="info-panel" class="h-100">
                @if($familyData && !empty($familyData['nodes']))
                    @php
                        $currentUser = $familyData['nodes'][0]; // Assuming current user is first in the array
                    @endphp
                    <div class="space-y-4">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bi bi-person-fill"></i>
                                    <a href="/spans/{{ $currentUser['id'] }}" class="text-decoration-none">
                                        <strong>{{ $currentUser['name'] }}</strong>
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Family Relationships</h5>
                                <p class="text-muted small">Click on a family member to see their relationships.</p>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="text-center text-muted mt-4">
                        <svg class="mx-auto h-12 w-12 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                        <h3 class="mt-2 text-sm font-medium">No person selected</h3>
                        <p class="mt-1 text-sm">Click on a family member to see their details.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

@if($familyData && !empty($familyData['nodes']))
<script src="https://d3js.org/d3.v7.min.js"></script>
<script>
// Helper function to get node color
function getNodeColor(type) {
    switch(type) {
        case 'current-user': return "#3B82F6";
        case 'parent': return "#10B981";
        case 'grandparent': return "#8B5CF6";
        case 'great-grandparent': return "#6B21A8";
        case 'ancestor': return "#7C3AED";
        case 'sibling': return "#F59E0B";
        case 'uncle-aunt': return "#F97316";
        case 'cousin': return "#EAB308";
        case 'niece-nephew': return "#FCD34D";
        case 'child': return "#EF4444";
        case 'grandchild': return "#EC4899";
        case 'great-grandchild': return "#BE185D";
        case 'descendant': return "#FB7185";
        default: return "#6B7280";
    }
}

// Function to get gender icon
function getGenderIcon(gender) {
    switch(gender) {
        case 'male': return '♂'; // Male symbol
        case 'female': return '♀'; // Female symbol
        default: return ''; // No icon for unknown/other
    }
}

// Function to show node information in the right panel
function showNodeInfo(nodeData) {
    const infoPanel = document.getElementById('info-panel');
    
    // Get family relationships from the current graph data (not static JSON)
    const familyData = window.familyData || @json($familyData);
    
    console.log('=== FAMILY DATA DEBUG ===');
    console.log('Family data source:', window.familyData ? 'window.familyData' : 'static JSON');
    console.log('Family data structure:', familyData);
    console.log('Links structure:', familyData.links);
    console.log('Sample link:', familyData.links[0]);
    
    // Find parents of this person (nodes that have links TO this person as target)
    const parents = familyData.links
        .filter(link => {
            const targetId = typeof link.target === 'object' ? link.target.id : link.target;
            console.log('Checking parent link:', link.source, '->', targetId, 'vs', nodeData.id);
            return targetId === nodeData.id;
        })
        .map(link => {
            const sourceId = typeof link.source === 'object' ? link.source.id : link.source;
            return familyData.nodes.find(node => node.id === sourceId);
        })
        .filter(parent => parent);
    
    // Debug logging
    console.log('Node data:', nodeData);
    console.log('All family links:', familyData.links);
    console.log('Parents found:', parents);
    console.log('Parent genders:', parents.map(p => ({ name: p.name, gender: p.gender })));
    
    // Find children of this person (nodes that this person has links TO as source)
    const children = familyData.links
        .filter(link => {
            const sourceId = typeof link.source === 'object' ? link.source.id : link.source;
            console.log('Checking child link:', sourceId, '->', link.target, 'vs', nodeData.id);
            return sourceId === nodeData.id;
        })
        .map(link => {
            const targetId = typeof link.target === 'object' ? link.target.id : link.target;
            return familyData.nodes.find(node => node.id === targetId);
        })
        .filter(child => child);
    
    // Debug logging
    console.log('Children found:', children);
    console.log('Children genders:', children.map(c => ({ name: c.name, gender: c.gender })));
    
    // Create family relationships HTML
    let familyRelationships = '';
    
    console.log('=== PARENT DETECTION DEBUG ===');
    console.log('Total parents found:', parents.length);
    console.log('All parents:', parents);
    
    if (parents.length > 0) {
        // Separate parents by gender
        const mothers = parents.filter(parent => parent.gender === 'female');
        const fathers = parents.filter(parent => parent.gender === 'male');
        const otherParents = parents.filter(parent => parent.gender !== 'female' && parent.gender !== 'male');
        
        console.log('Mothers found:', mothers.length, mothers);
        console.log('Fathers found:', fathers.length, fathers);
        console.log('Other parents found:', otherParents.length, otherParents);
        
        familyRelationships += `
            <div class="row mb-3">
                <div class="col-6">
                    <div class="card h-100">
                        <div class="card-body">
                            <h6 class="card-title">
                                <i class="bi bi-gender-female text-danger me-1"></i>
                                Mother
                            </h6>
                            ${mothers.length > 0 ? `
                                <ul class="list-unstyled mb-0">
                                    ${mothers.map(mother => `
                                        <li class="mb-2">
                                            <a href="/spans/${mother.id}" class="text-decoration-none d-inline-flex align-items-center gap-1">
                                                <i class="bi bi-person-fill"></i>
                                                <strong>${mother.name}</strong>
                                            </a>
                                        </li>
                                    `).join('')}
                                </ul>
                            ` : `
                                <form data-parent-type="mother" data-child-id="${nodeData.id}" class="mt-2">
                                    <div class="input-group input-group-sm">
                                        <input type="text" class="form-control" data-field="name" placeholder="Mother's name" required>
                                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="createParent('mother', '${nodeData.id}')">
                                            <i class="bi bi-plus"></i> Create
                                        </button>
                                    </div>
                                </form>
                            `}
                        </div>
                    </div>
                </div>
                
                <div class="col-6">
                    <div class="card h-100">
                        <div class="card-body">
                            <h6 class="card-title">
                                <i class="bi bi-gender-male text-primary me-1"></i>
                                Father
                            </h6>
                            ${fathers.length > 0 ? `
                                <ul class="list-unstyled mb-0">
                                    ${fathers.map(father => `
                                        <li class="mb-2">
                                            <a href="/spans/${father.id}" class="text-decoration-none d-inline-flex align-items-center gap-1">
                                                <i class="bi bi-person-fill"></i>
                                                <strong>${father.name}</strong>
                                            </a>
                                        </li>
                                    `).join('')}
                                </ul>
                            ` : `
                                <form data-parent-type="father" data-child-id="${nodeData.id}" class="mt-2">
                                    <div class="input-group input-group-sm">
                                        <input type="text" class="form-control" data-field="name" placeholder="Father's name" required>
                                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="createParent('father', '${nodeData.id}')">
                                            <i class="bi bi-plus"></i> Create
                                        </button>
                                    </div>
                                </form>
                            `}
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // If we have other parents (non-binary, unknown, etc.), show them in a separate card
        if (otherParents.length > 0) {
            familyRelationships += `
                <div class="card mb-3">
                    <div class="card-body">
                        <h6 class="card-title">
                            <i class="bi bi-person-fill text-muted me-1"></i>
                            Other Parents
                        </h6>
                        <ul class="list-unstyled mb-0">
                            ${otherParents.map(parent => `
                                <li class="mb-2">
                                    <a href="/spans/${parent.id}" class="text-decoration-none d-inline-flex align-items-center gap-1">
                                        <i class="bi bi-person-fill"></i>
                                        <strong>${parent.name}</strong>
                                    </a>
                                </li>
                            `).join('')}
                        </ul>
                    </div>
                </div>
            `;
        }
    } else {
        // No parents at all - show both cards as unknown
        familyRelationships += `
            <div class="row mb-3">
                <div class="col-6">
                    <div class="card h-100">
                        <div class="card-body">
                            <h6 class="card-title">
                                <i class="bi bi-gender-female text-danger me-1"></i>
                                Mother
                            </h6>
                            <form data-parent-type="mother" data-child-id="${nodeData.id}" class="mt-2">
                                <div class="input-group input-group-sm">
                                    <input type="text" class="form-control" data-field="name" placeholder="Mother's name" required>
                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="createParent('mother', '${nodeData.id}')">
                                        <i class="bi bi-plus"></i> Create
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="col-6">
                    <div class="card h-100">
                        <div class="card-body">
                            <h6 class="card-title">
                                <i class="bi bi-gender-male text-primary me-1"></i>
                                Father
                            </h6>
                            <form data-parent-type="father" data-child-id="${nodeData.id}" class="mt-2">
                                <div class="input-group input-group-sm">
                                    <input type="text" class="form-control" data-field="name" placeholder="Father's name" required>
                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="createParent('father', '${nodeData.id}')">
                                        <i class="bi bi-plus"></i> Create
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }
    
    if (children.length > 0) {
        familyRelationships += `
            <div class="card mb-3">
                <div class="card-body">
                    <h6 class="card-title">
                        <i class="bi bi-people-fill text-success me-1"></i>
                        Children
                    </h6>
                    <ul class="list-unstyled mb-0">
                        ${children.map(child => `
                            <li class="mb-2">
                                <a href="/spans/${child.id}" class="text-decoration-none d-inline-flex align-items-center gap-1">
                                    <i class="bi bi-person-fill"></i>
                                    <strong>${child.name}</strong>
                                    ${child.gender === 'male' ? '♂' : child.gender === 'female' ? '♀' : ''}
                                </a>
                            </li>
                        `).join('')}
                    </ul>
                    <button type="button" class="btn btn-outline-success btn-sm mt-2" onclick="showAddChildForm('${nodeData.id}')">
                        <i class="bi bi-plus-circle me-1"></i>Add Child
                    </button>
                </div>
            </div>
        `;
    } else {
        // No children - show add child form
        familyRelationships += `
            <div class="card mb-3">
                <div class="card-body">
                    <h6 class="card-title">
                        <i class="bi bi-people-fill text-success me-1"></i>
                        Children
                    </h6>
                    <form data-child-form="true" data-parent-id="${nodeData.id}" class="mt-2">
                        <div class="mb-2">
                            <input type="text" class="form-control form-control-sm" data-field="name" placeholder="Child's name" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small mb-1">Gender:</label>
                            <div class="btn-group btn-group-sm w-100" role="group">
                                <input type="radio" class="btn-check" name="gender_${nodeData.id}" id="male_${nodeData.id}" value="male" checked>
                                <label class="btn btn-outline-primary" for="male_${nodeData.id}">
                                    <i class="bi bi-gender-male me-1"></i>Male
                                </label>
                                
                                <input type="radio" class="btn-check" name="gender_${nodeData.id}" id="female_${nodeData.id}" value="female">
                                <label class="btn btn-outline-danger" for="female_${nodeData.id}">
                                    <i class="bi bi-gender-female me-1"></i>Female
                                </label>
                            </div>
                        </div>
                        <button type="button" class="btn btn-outline-success btn-sm" onclick="createChild('${nodeData.id}')">
                            <i class="bi bi-plus"></i> Create
                        </button>
                    </form>
                </div>
            </div>
        `;
    }
    
    // Create the HTML for node information
    const html = `
        <div class="space-y-4">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-person-fill"></i>
                        <a href="/spans/${nodeData.id}" class="text-decoration-none">
                            <strong>${nodeData.name}</strong>
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Family Relationships</h5>
                    ${familyRelationships ? familyRelationships : `
                        <p class="text-muted small">No family relationships found in the graph.</p>
                        <button class="btn btn-outline-primary btn-sm w-100 mt-2" onclick="addParent('${nodeData.id}')">
                            <i class="bi bi-plus-circle me-1"></i>Add Parent
                        </button>
                    `}
                </div>
            </div>
        </div>
    `;
    
    infoPanel.innerHTML = html;
}

// Function to handle adding a parent (placeholder for now)
function addParent(personId) {
    console.log('Add parent for person:', personId);
    // TODO: Implement add parent functionality
    alert('Add parent functionality will be implemented here');
}

// Function to create a new parent span and family connection
async function createParent(parentType, childId) {
    console.log('createParent called with:', { 
        parentType, 
        childId, 
        childIdType: typeof childId,
        parentTypeType: typeof parentType 
    });
    
    // Convert childId to string for comparison with data attributes
    const childIdStr = String(childId);
    
    // Debug: Log all forms with data-parent-type
    const allForms = document.querySelectorAll('[data-parent-type]');
    console.log('All forms with data-parent-type:', allForms);
    allForms.forEach(form => {
        console.log('Form:', {
            parentType: form.getAttribute('data-parent-type'),
            childId: form.getAttribute('data-child-id'),
            element: form
        });
    });
    
    // Try multiple selector approaches
    let form = document.querySelector(`[data-parent-type="${parentType}"][data-child-id="${childIdStr}"]`);
    console.log('Tried selector 1:', `[data-parent-type="${parentType}"][data-child-id="${childIdStr}"]`, 'Result:', form);
    
    // If not found, try with the original childId
    if (!form) {
        form = document.querySelector(`[data-parent-type="${parentType}"][data-child-id="${childId}"]`);
        console.log('Tried selector 2:', `[data-parent-type="${parentType}"][data-child-id="${childId}"]`, 'Result:', form);
    }
    
    // If still not found, try a more flexible approach
    if (!form) {
        const forms = document.querySelectorAll(`[data-parent-type="${parentType}"]`);
        console.log('Found forms with parentType:', parentType, 'Count:', forms.length);
        form = Array.from(forms).find(f => {
            const formChildId = f.getAttribute('data-child-id');
            const matches = formChildId == childId;
            console.log('Comparing form childId:', formChildId, 'with passed childId:', childId, 'Matches:', matches);
            return matches;
        });
        console.log('Flexible search result:', form);
    }
    
    console.log('Final form found:', form);
    
    if (!form) {
        console.error('Parent form not found');
        console.error('Available forms:', Array.from(document.querySelectorAll('[data-parent-type]')).map(f => ({
            parentType: f.getAttribute('data-parent-type'),
            childId: f.getAttribute('data-child-id')
        })));
        return;
    }
    
    const nameInput = form.querySelector('[data-field="name"]');
    const name = nameInput.value.trim();
    
    if (!name) {
        alert('Please enter a name for the parent');
        return;
    }
    
    // Show loading state
    const button = form.querySelector('button');
    const originalText = button.innerHTML;
    button.innerHTML = '<i class="bi bi-hourglass-split"></i> Creating...';
    button.disabled = true;
    
    try {
        // Create the parent span
        const spanData = {
            name: name,
            type_id: 'person',
            state: 'placeholder',
            metadata: {
                gender: parentType === 'mother' ? 'female' : 'male'
            }
        };
        
        console.log('Sending span data:', spanData);
        
        const spanResponse = await fetch('/spans', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify(spanData)
        });
        
        console.log('Span response status:', spanResponse.status);
        
        if (!spanResponse.ok) {
            const errorText = await spanResponse.text();
            console.error('Span creation error response:', errorText);
            throw new Error('Failed to create parent span: ' + errorText);
        }
        
        console.log('About to parse span response JSON...');
        const responseText = await spanResponse.text();
        console.log('Raw response text:', responseText);
        
        let parentSpan;
        try {
            parentSpan = JSON.parse(responseText);
            console.log('Successfully parsed parent span:', parentSpan);
        } catch (parseError) {
            console.error('JSON parse error:', parseError);
            console.error('Response text that failed to parse:', responseText);
            throw new Error('Failed to parse span response: ' + parseError.message);
        }
        
        // Create the family connection
        const connectionData = {
            parent_id: parentSpan.id,
            child_id: childId,
            relationship: parentType === 'mother' ? 'mother' : 'father'
        };
        
        console.log('Sending connection data:', connectionData);
        
        const connectionResponse = await fetch('/api/family/connections', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify(connectionData)
        });
        
        console.log('Connection response status:', connectionResponse.status);
        
        if (!connectionResponse.ok) {
            const errorText = await connectionResponse.text();
            console.error('Connection creation error response:', errorText);
            throw new Error('Failed to create family connection: ' + errorText);
        }
        
        // Refresh the family tree
        await refreshFamilyTree();
        
        // Clear the form
        nameInput.value = '';
        
        // Show success message
        button.innerHTML = '<i class="bi bi-check"></i> Created!';
        setTimeout(() => {
            button.innerHTML = originalText;
            button.disabled = false;
        }, 2000);
        
    } catch (error) {
        console.error('Error creating parent:', error);
        alert('Error creating parent: ' + error.message);
        
        // Reset button
        button.innerHTML = originalText;
        button.disabled = false;
    }
}

// Function to refresh the family tree data and visualization
async function refreshFamilyTree() {
    try {
        // Fetch updated family data
        const response = await fetch('/family/data');
        if (!response.ok) {
            throw new Error('Failed to fetch updated family data');
        }
        
        const newFamilyData = await response.json();
        
        // Re-render the family tree with new data
        if (window.renderFamilyTree) {
            window.renderFamilyTree(newFamilyData);
        }
        
        // Update the info panel if a node is currently selected
        const selectedNode = document.querySelector('.node.selected');
        if (selectedNode) {
            const nodeData = newFamilyData.nodes.find(n => n.id === selectedNode.__data__.id);
            if (nodeData) {
                showNodeInfo(nodeData);
            }
        }
        
    } catch (error) {
        console.error('Error refreshing family tree:', error);
        alert('Failed to refresh family tree: ' + error.message);
    }
}

// Global variables for node visibility
let hiddenNodeTypes = new Set();
let nodeElements = null;
let linkElements = null;
let simulation = null;
let familyData = null;

// Function to toggle node visibility by type
function toggleNodeType(type) {
    const badge = document.querySelector(`[data-type="${type}"]`);
    
    if (hiddenNodeTypes.has(type)) {
        // Show nodes of this type
        hiddenNodeTypes.delete(type);
        badge.classList.remove('inactive');
        badge.classList.add('active');
    } else {
        // Hide nodes of this type
        hiddenNodeTypes.add(type);
        badge.classList.add('inactive');
        badge.classList.remove('active');
    }
    
    updateNodeVisibility();
}

// Make functions globally accessible
window.toggleNodeType = toggleNodeType;
window.updateNodeVisibility = updateNodeVisibility;
window.createParent = createParent;
window.showAddChildForm = showAddChildForm;
window.createChild = createChild;
window.refreshFamilyTree = refreshFamilyTree;

// Function to update node and link visibility
function updateNodeVisibility() {
    if (!nodeElements || !linkElements || !window.familyData) return;
    
    // Get visible nodes and links
    const visibleNodes = window.familyData.nodes.filter(node => !hiddenNodeTypes.has(node.type));
    const visibleLinks = window.familyData.links.filter(link => {
        const sourceId = typeof link.source === 'object' ? link.source.id : link.source;
        const targetId = typeof link.target === 'object' ? link.target.id : link.target;
        const sourceNode = window.familyData.nodes.find(n => n.id === sourceId);
        const targetNode = window.familyData.nodes.find(n => n.id === targetId);
        return sourceNode && targetNode && !hiddenNodeTypes.has(sourceNode.type) && !hiddenNodeTypes.has(targetNode.type);
    });
    
    console.log('Visible nodes:', visibleNodes.length, 'Visible links:', visibleLinks.length);
    console.log('Hidden types:', Array.from(hiddenNodeTypes));
    
    // Update node visibility - show all nodes but mark hidden ones as inactive
    nodeElements.each(function(d) {
        const node = d3.select(this);
        const shouldBeVisible = !hiddenNodeTypes.has(d.type);
        
        if (shouldBeVisible) {
            // Show active nodes
            node.style('display', 'block')
                .style('opacity', 1)
                .classed('inactive', false);
        } else {
            // Show inactive nodes with thicker white border
            node.style('display', 'block')
                .style('opacity', 1)
                .classed('inactive', true);
        }
    });
    
    // Update link visibility - only fade links that are changing state
    linkElements.each(function(d) {
        const link = d3.select(this);
        const sourceId = typeof d.source === 'object' ? d.source.id : d.source;
        const targetId = typeof d.target === 'object' ? d.target.id : d.target;
        const sourceNode = window.familyData.nodes.find(n => n.id === sourceId);
        const targetNode = window.familyData.nodes.find(n => n.id === targetId);
        const shouldBeVisible = sourceNode && targetNode && !hiddenNodeTypes.has(sourceNode.type) && !hiddenNodeTypes.has(targetNode.type);
        
        const isCurrentlyVisible = link.style('display') !== 'none';
        
        if (isCurrentlyVisible && !shouldBeVisible) {
            // Fade out links that are being hidden
            link.transition().duration(300)
                .style('opacity', 0)
                .on('end', function() {
                    d3.select(this).style('display', 'none');
                });
        } else if (!isCurrentlyVisible && shouldBeVisible) {
            // Fade in links that are being shown
            link.style('display', 'block')
                .style('opacity', 0)
                .transition().duration(300)
                .style('opacity', 1);
        }
        // Links that aren't changing state remain unchanged
    });
    
    // Update simulation with only visible nodes
    if (simulation) {
        // Update simulation data without stopping
        simulation.nodes(visibleNodes);
        simulation.force('link').links(visibleLinks);
        
        // Give a small energy boost for repositioning
        simulation.alpha(0.3).restart();
        
        // Gradually reduce alpha for stability
        setTimeout(() => {
            simulation.alpha(0.1);
        }, 500);
    }
}

// Function to show the add child form when there are existing children
function showAddChildForm(parentId) {
    const childrenCard = document.querySelector('.card:has(.btn-outline-success)');
    if (childrenCard) {
        const cardBody = childrenCard.querySelector('.card-body');
        const existingContent = cardBody.innerHTML;
        
        // Replace the content with the form
        cardBody.innerHTML = `
            <h6 class="card-title">
                <i class="bi bi-people-fill text-success me-1"></i>
                Children
            </h6>
            <form data-child-form="true" data-parent-id="${parentId}" class="mt-2">
                <div class="mb-2">
                    <input type="text" class="form-control form-control-sm" data-field="name" placeholder="Child's name" required>
                </div>
                <div class="mb-2">
                    <label class="form-label small mb-1">Gender:</label>
                    <div class="btn-group btn-group-sm w-100" role="group">
                        <input type="radio" class="btn-check" name="gender_${parentId}" id="male_${parentId}" value="male" checked>
                        <label class="btn btn-outline-primary" for="male_${parentId}">
                            <i class="bi bi-gender-male me-1"></i>Male
                        </label>
                        
                        <input type="radio" class="btn-check" name="gender_${parentId}" id="female_${parentId}" value="female">
                        <label class="btn btn-outline-danger" for="female_${parentId}">
                            <i class="bi bi-gender-female me-1"></i>Female
                        </label>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-success btn-sm" onclick="createChild('${parentId}')">
                        <i class="bi bi-plus"></i> Create
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="cancelAddChild()">
                        <i class="bi bi-x"></i> Cancel
                    </button>
                </div>
            </form>
        `;
        
        // Store the original content for restoration
        childrenCard.setAttribute('data-original-content', existingContent);
    }
}

// Function to cancel adding a child and restore the original content
function cancelAddChild() {
    const childrenCard = document.querySelector('.card:has([data-child-form="true"])');
    if (childrenCard) {
        const originalContent = childrenCard.getAttribute('data-original-content');
        if (originalContent) {
            childrenCard.querySelector('.card-body').innerHTML = originalContent;
            childrenCard.removeAttribute('data-original-content');
        }
    }
}

// Function to create a new child span and family connection
async function createChild(parentId) {
    console.log('createChild called with:', { parentId });
    
    const form = document.querySelector(`[data-child-form="true"][data-parent-id="${parentId}"]`);
    if (!form) {
        console.error('Child form not found');
        return;
    }
    
    const nameInput = form.querySelector('[data-field="name"]');
    const name = nameInput.value.trim();
    
    if (!name) {
        alert('Please enter a name for the child');
        return;
    }
    
    // Get the selected gender
    const selectedGender = form.querySelector(`input[name="gender_${parentId}"]:checked`);
    if (!selectedGender) {
        alert('Please select a gender');
        return;
    }
    
    const gender = selectedGender.value;
    
    // Show loading state
    const button = form.querySelector('button[onclick*="createChild"]');
    const originalText = button.innerHTML;
    button.innerHTML = '<i class="bi bi-hourglass-split"></i> Creating...';
    button.disabled = true;
    
    try {
        // Create the child span
        const spanData = {
            name: name,
            type_id: 'person',
            state: 'placeholder',
            metadata: {
                gender: gender
            }
        };
        
        console.log('Sending child span data:', spanData);
        
        const spanResponse = await fetch('/spans', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify(spanData)
        });
        
        console.log('Child span response status:', spanResponse.status);
        
        if (!spanResponse.ok) {
            const errorText = await spanResponse.text();
            console.error('Child span creation error response:', errorText);
            throw new Error('Failed to create child span: ' + errorText);
        }
        
        const responseText = await spanResponse.text();
        let childSpan;
        try {
            childSpan = JSON.parse(responseText);
            console.log('Successfully parsed child span:', childSpan);
        } catch (parseError) {
            console.error('JSON parse error:', parseError);
            throw new Error('Failed to parse child span response: ' + parseError.message);
        }
        
        // Create the family connection
        const connectionData = {
            parent_id: parentId,
            child_id: childSpan.id,
            relationship: 'parent'
        };
        
        console.log('Sending child connection data:', connectionData);
        
        const connectionResponse = await fetch('/api/family/connections', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify(connectionData)
        });
        
        console.log('Child connection response status:', connectionResponse.status);
        
        if (!connectionResponse.ok) {
            const errorText = await connectionResponse.text();
            console.error('Child connection creation error response:', errorText);
            throw new Error('Failed to create family connection: ' + errorText);
        }
        
        // Refresh the family tree
        await refreshFamilyTree();
        
        // Clear the form
        nameInput.value = '';
        
        // Show success message
        button.innerHTML = '<i class="bi bi-check"></i> Created!';
        setTimeout(() => {
            button.innerHTML = originalText;
            button.disabled = false;
        }, 2000);
        
    } catch (error) {
        console.error('Error creating child:', error);
        alert('Error creating child: ' + error.message);
        
        // Reset button
        button.innerHTML = originalText;
        button.disabled = false;
    }
}

document.addEventListener('DOMContentLoaded', function() {
    console.log('D3 script starting...');
    
    // Global function to render the family tree
    window.renderFamilyTree = function(familyData) {
        // Clear existing visualization
        const container = document.getElementById('family-tree');
        if (container) {
            container.innerHTML = '';
        }
        
        // Stop existing simulation if running
        if (window.simulation) {
            window.simulation.stop();
        }
        
        // Update global family data
        window.familyData = familyData;
        
        console.log('Rendering family tree with data:', familyData);
        console.log('Nodes count:', familyData.nodes.length);
        console.log('Links count:', familyData.links.length);
        
        if (!container) {
            console.error('Container not found!');
            return;
        }
        
        // Get the container dimensions
        let containerWidth = container.clientWidth || container.offsetWidth || container.getBoundingClientRect().width;
        let containerHeight = container.clientHeight || container.offsetHeight || container.getBoundingClientRect().height;
        
        console.log('Container dimensions:', { width: containerWidth, height: containerHeight });
        
        if (containerWidth === 0 || containerHeight === 0) {
            console.error('Container has zero dimensions!');
            console.log('Trying alternative approach...');
            
            // Try to get dimensions from parent
            const parent = container.parentElement;
            const parentWidth = parent.clientWidth || parent.offsetWidth;
            const parentHeight = parent.clientHeight || parent.offsetHeight;
            
            console.log('Parent dimensions:', { width: parentWidth, height: parentHeight });
            
            if (parentWidth > 0 && parentHeight > 0) {
                // Use parent dimensions
                container.style.width = parentWidth + 'px';
                container.style.height = parentHeight + 'px';
                
                // Update our variables
                containerWidth = parentWidth;
                containerHeight = parentHeight;
            } else {
                console.error('Parent also has zero dimensions!');
                console.log('Using fallback dimensions...');
                
                // Force reasonable dimensions
                container.style.width = '800px';
                container.style.height = '600px';
                containerWidth = 800;
                containerHeight = 600;
            }
        }
        
        // Set up the SVG
        const svg = d3.select("#family-tree")
            .append("svg")
            .attr("width", containerWidth)
            .attr("height", containerHeight)
            .attr("class", "family-tree-svg");
        
        console.log('SVG created');
        
        // Add zoom behavior
        const zoom = d3.zoom()
            .scaleExtent([0.1, 3])
            .on('zoom', (event) => {
                svg.select('g').attr('transform', event.transform);
            });
        
        svg.call(zoom);
        
        // Create the main group for the visualization
        const g = svg.append('g');
        
        // Add click handler to clear highlights when clicking on empty space
        svg.on("click", function(event) {
            if (event.target === svg.node()) {
                clearHighlights();
                // Clear the info panel
                document.getElementById('info-panel').innerHTML = `
                    <div class="text-center text-muted mt-4">
                        <svg class="mx-auto h-12 w-12 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                        <h3 class="mt-2 text-sm font-medium">No person selected</h3>
                        <p class="mt-1 text-sm">Click on a family member to see their details.</p>
                    </div>
                `;
            }
        });
        
        // Function to calculate link distance based on relationship type
        function calculateLinkDistance(link) {
            // All family links in our system are parent-child relationships
            // Make them shorter for a more compact family tree
            return 120;
        }
        
        // Create the force simulation
        window.simulation = d3.forceSimulation(familyData.nodes)
            .force("link", d3.forceLink(familyData.links).id(d => d.id).distance(d => calculateLinkDistance(d)))
            .force("charge", d3.forceManyBody().strength(-400))
            .force("collision", d3.forceCollide().radius(d => Math.max(50, d.name.length * 5) + 20))
            .force("x", d3.forceX(containerWidth / 2).strength(0.1))
            .force("y", d3.forceY(containerHeight / 2).strength(0.1));
        
        console.log('Force simulation created');
        
        // Function to calculate link opacity based on overlap
        function calculateLinkOpacity(link) {
            const overlappingLinks = familyData.links.filter(otherLink => {
                if (otherLink === link) return false;
                
                // Check if links share nodes
                const sharesSource = otherLink.source.id === link.source.id || otherLink.target.id === link.source.id;
                const sharesTarget = otherLink.source.id === link.target.id || otherLink.target.id === link.target.id;
                
                return sharesSource || sharesTarget;
            });
            
            // Reduce opacity based on number of overlapping connections
            const baseOpacity = 0.6;
            const overlapPenalty = Math.min(0.3, overlappingLinks.length * 0.1);
            return Math.max(0.2, baseOpacity - overlapPenalty);
        }
        
        // Function to highlight family relationships
        function highlightFamilyRelationships(nodeId) {
            console.log('Link data structure:', familyData.links);
            
            // Use the exact same logic as the sidebar
            // Find parents of this person (nodes that have links TO this person as target)
            const parents = familyData.links
                .filter(link => {
                    const targetId = typeof link.target === 'object' ? link.target.id : link.target;
                    console.log('Checking parent link:', link.source, '->', targetId, 'vs', nodeId);
                    return targetId === nodeId;
                })
                .map(link => {
                    const sourceId = typeof link.source === 'object' ? link.source.id : link.source;
                    return familyData.nodes.find(node => node.id === sourceId);
                })
                .filter(parent => parent);
            
            // Find children of this person (nodes that this person has links TO as source)
            const children = familyData.links
                .filter(link => {
                    const sourceId = typeof link.source === 'object' ? link.source.id : link.source;
                    console.log('Checking child link:', sourceId, '->', link.target, 'vs', nodeId);
                    return sourceId === nodeId;
                })
                .map(link => {
                    const targetId = typeof link.target === 'object' ? link.target.id : link.target;
                    return familyData.nodes.find(node => node.id === targetId);
                })
                .filter(child => child);
            
            console.log('Highlighting for node:', nodeId);
            console.log('Parents found:', parents.map(p => p.name));
            console.log('Children found:', children.map(c => c.name));
            
            // Highlight the hovered person
            const hoveredElement = node.filter(d => d.id === nodeId);
            console.log('Hovered element found:', hoveredElement.size());
            hoveredElement.select("rect")
                .style("stroke", "#000")
                .style("stroke-width", "2px")
                .style("filter", "brightness(1.2)")
                .classed("selected", true);
            
            // Highlight parent nodes
            parents.forEach(parent => {
                console.log('Highlighting parent:', parent.name, parent.id);
                const parentElement = node.filter(d => d.id === parent.id);
                console.log('Parent element found:', parentElement.size());
                parentElement.select("rect")
                    .style("stroke", "#000")
                    .style("stroke-width", "2px")
                    .style("filter", "brightness(1.2)")
                    .classed("active", true);
            });
            
            // Highlight child nodes
            children.forEach(child => {
                console.log('Highlighting child:', child.name, child.id);
                const childElement = node.filter(d => d.id === child.id);
                console.log('Child element found:', childElement.size());
                childElement.select("rect")
                    .style("stroke", "#000")
                    .style("stroke-width", "2px")
                    .style("filter", "brightness(1.2)")
                    .classed("active", true);
            });
            
            // Highlight connecting edges (both parent and child connections)
            const allRelatedNodes = [...parents, ...children];
            const connectingLinks = familyData.links.filter(link => {
                const sourceId = typeof link.source === 'object' ? link.source.id : link.source;
                const targetId = typeof link.target === 'object' ? link.target.id : link.target;
                
                return (sourceId === nodeId && allRelatedNodes.some(n => n.id === targetId)) ||
                       (targetId === nodeId && allRelatedNodes.some(n => n.id === sourceId));
            });
            
            console.log('Connecting links:', connectingLinks);
            
            // Highlight the connecting links
            link.style("stroke", d => {
                const isConnecting = connectingLinks.some(connectingLink => {
                    const linkSourceId = typeof connectingLink.source === 'object' ? connectingLink.source.id : connectingLink.source;
                    const linkTargetId = typeof connectingLink.target === 'object' ? connectingLink.target.id : connectingLink.target;
                    
                    return (d.source.id === linkSourceId && d.target.id === linkTargetId) ||
                           (d.source.id === linkTargetId && d.target.id === linkSourceId);
                });
                return isConnecting ? "#000" : "#999";
            })
            .style("stroke-width", d => {
                const isConnecting = connectingLinks.some(connectingLink => {
                    const linkSourceId = typeof connectingLink.source === 'object' ? connectingLink.source.id : connectingLink.source;
                    const linkTargetId = typeof connectingLink.target === 'object' ? connectingLink.target.id : connectingLink.target;
                    
                    return (d.source.id === linkSourceId && d.target.id === linkTargetId) ||
                           (d.source.id === linkTargetId && d.target.id === linkSourceId);
                });
                return isConnecting ? "3px" : "2px";
            })
            .style("stroke-opacity", d => {
                const isConnecting = connectingLinks.some(connectingLink => {
                    const linkSourceId = typeof connectingLink.source === 'object' ? connectingLink.source.id : connectingLink.source;
                    const linkTargetId = typeof connectingLink.target === 'object' ? connectingLink.target.id : connectingLink.target;
                    
                    return (d.source.id === linkSourceId && d.target.id === linkTargetId) ||
                           (d.source.id === linkTargetId && d.target.id === linkSourceId);
                });
                return isConnecting ? 1 : calculateLinkOpacity(d);
            });
        }
        
        // Function to clear highlights
        function clearHighlights() {
            node.select("rect")
                .style("stroke", "#fff")
                .style("stroke-width", "1px")
                .style("filter", "none")
                .style("stroke-opacity", "0.8")
                .classed("selected", false)
                .classed("active", false);
            
            link.style("stroke", "#999")
                .style("stroke-width", "2px")
                .style("stroke-opacity", d => calculateLinkOpacity(d));
        }
        
        // Create the links
        const link = g.append("g")
            .selectAll("line")
            .data(familyData.links)
            .enter().append("line")
            .attr("class", "link")
            .style("stroke", "#999")
            .style("stroke-width", "2px")
            .style("stroke-opacity", d => calculateLinkOpacity(d));
        
        // Store reference to link elements
        window.linkElements = link;
        
        console.log('Links created:', familyData.links.length);
        
        // Create the nodes
        const node = g.append("g")
            .selectAll("g")
            .data(familyData.nodes)
            .enter().append("g")
            .call(d3.drag()
                .on("start", dragstarted)
                .on("drag", dragged)
                .on("end", dragended))
            .on("click", function(event, d) {
                // Remove selection from all nodes
                node.select("rect").classed("selected", false).classed("active", false);
                // Add selection to clicked node
                d3.select(this).select("rect").classed("selected", true);
                
                // Clear any existing highlights
                clearHighlights();
                
                // Highlight family relationships (same as sidebar)
                highlightFamilyRelationships(d.id);
                
                // Show information in right panel
                showNodeInfo(d);
            })
            .on("mouseover", function(event, d) {
                // No mouseover highlighting - only on click
            })
            .on("mouseout", function() {
                // No mouseout clearing - only on click
            });
        
        // Store reference to node elements
        window.nodeElements = node;
        
        console.log('Nodes created:', familyData.nodes.length);
        
        // Add rounded rectangles for the nodes
        node.append("rect")
            .attr("rx", 6) // Slightly less rounded corners for Bootstrap-like appearance
            .attr("ry", 6)
            .attr("width", d => Math.max(80, d.name.length * 8)) // Dynamic width based on name length
            .attr("height", 28) // Slightly smaller height for more compact look
            .attr("x", d => -Math.max(80, d.name.length * 8) / 2) // Center the rectangle
            .attr("y", -14)
            .style("fill", d => {
                switch(d.type) {
                    case 'current-user': return "#3B82F6"; // Blue for current user
                    case 'parent': return "#10B981"; // Green for parents
                    case 'grandparent': return "#8B5CF6"; // Purple for grandparents
                    case 'great-grandparent': return "#6B21A8"; // Dark purple for great-grandparents
                    case 'ancestor': return "#7C3AED"; // Dark purple for ancestors
                    case 'sibling': return "#F59E0B"; // Orange for siblings
                    case 'uncle-aunt': return "#F97316"; // Orange for uncles/aunts
                    case 'cousin': return "#EAB308"; // Yellow for cousins
                    case 'niece-nephew': return "#FCD34D"; // Yellow for nieces/nephews
                    case 'child': return "#EF4444"; // Red for children
                    case 'grandchild': return "#EC4899"; // Pink for grandchildren
                    case 'great-grandchild': return "#BE185D"; // Pink for great-grandchildren
                    case 'descendant': return "#FB7185"; // Light red for descendants
                    default: return "#6B7280"; // Gray for unknown types
                }
            })
            .style("stroke", "#fff")
            .style("stroke-width", "1px") // Thinner borders like Bootstrap buttons
            .style("stroke-opacity", "0.8"); // Slightly transparent borders
        
        // Add labels for the nodes with gender icons
        node.append("text")
            .text(d => {
                const genderIcon = getGenderIcon(d.gender);
                return genderIcon ? `${d.name} ${genderIcon}` : d.name;
            })
            .attr("text-anchor", "middle")
            .attr("dy", "0.35em")
            .style("font-size", "11px")
            .style("font-weight", "600") // Slightly bolder for better readability
            .style("fill", "#fff")
            .style("pointer-events", "none")
            .style("text-shadow", "0 1px 2px rgba(0, 0, 0, 0.3)"); // Better text readability
        
        console.log('Node styling applied');
        
        // Add boundary constraints
        window.simulation.on("tick", () => {
            // Keep nodes within bounds
            familyData.nodes.forEach(d => {
                const nodeWidth = Math.max(80, d.name.length * 8);
                const nodeHeight = 30;
                const margin = Math.max(nodeWidth, nodeHeight) / 2 + 10;
                
                d.x = Math.max(margin, Math.min(containerWidth - margin, d.x));
                d.y = Math.max(margin, Math.min(containerHeight - margin, d.y));
            });
            
            // Update link positions
            link
                .attr("x1", d => d.source.x)
                .attr("y1", d => d.source.y)
                .attr("x2", d => d.target.x)
                .attr("y2", d => d.target.y);
            
            // Update node positions
            node
                .attr("transform", d => `translate(${d.x},${d.y})`);
        });
        
        console.log('Simulation tick handler set');
        
        // Drag functions
        function dragstarted(event, d) {
            if (!event.active) window.simulation.alphaTarget(0.3).restart();
            d.fx = d.x;
            d.fy = d.y;
        }
        
        function dragged(event, d) {
            d.fx = event.x;
            d.fy = event.y;
        }
        
        function dragended(event, d) {
            if (!event.active) window.simulation.alphaTarget(0);
            d.fx = null;
            d.fy = null;
        }
        
        // Handle window resize
        window.addEventListener('resize', function() {
            // Get new container dimensions
            const newContainerWidth = container.clientWidth;
            const newContainerHeight = container.clientHeight;
            
            // Update SVG size
            svg.attr("width", newContainerWidth)
               .attr("height", newContainerHeight);
            
            // Update force center
            window.simulation.force("center", d3.forceCenter(newContainerWidth / 2, newContainerHeight / 2));
            window.simulation.alpha(0.3).restart();
        });
        
        console.log('D3 visualization complete!');
        
        // Add click handlers to toggle badges
        document.querySelectorAll('.toggle-badge').forEach(badge => {
            badge.addEventListener('click', function() {
                const type = this.getAttribute('data-type');
                window.toggleNodeType(type);
            });
        });
        
        // Set initial zoom to fit all nodes
        setTimeout(() => {
            // Calculate bounds of all nodes
            const xExtent = d3.extent(familyData.nodes, d => d.x);
            const yExtent = d3.extent(familyData.nodes, d => d.y);
            
            const nodeWidth = xExtent[1] - xExtent[0];
            const nodeHeight = yExtent[1] - yExtent[0];
            
            // Add some padding
            const padding = 50;
            const scaleX = (containerWidth - padding) / nodeWidth;
            const scaleY = (containerHeight - padding) / nodeHeight;
            const scale = Math.min(scaleX, scaleY, 1); // Don't zoom in more than 1:1
            
            // Apply zoom
            const transform = d3.zoomIdentity
                .translate(containerWidth / 2, containerHeight / 2)
                .scale(scale)
                .translate(-(xExtent[0] + xExtent[1]) / 2, -(yExtent[0] + yExtent[1]) / 2);
            
            svg.call(zoom.transform, transform);
            
            // Auto-select the current user (first node)
            if (familyData.nodes.length > 0) {
                const currentUser = familyData.nodes[0];
                console.log('Auto-selecting current user:', currentUser.name);
                
                // Highlight the current user
                highlightFamilyRelationships(currentUser.id);
                
                // Show current user info in sidebar
                showNodeInfo(currentUser);
            }
        }, 1000); // Wait for simulation to settle
    };
    
    try {
        const familyData = @json($familyData);
        
        // Initial render
        window.renderFamilyTree(familyData);
        
    } catch (error) {
        console.error('Error in D3 script:', error);
    }
});

function resetZoom() {
    const svg = d3.select('#family-tree svg');
    svg.transition().duration(750).call(
        d3.zoom().transform,
        d3.zoomIdentity
    );
}
</script>
@endif
@endsection 