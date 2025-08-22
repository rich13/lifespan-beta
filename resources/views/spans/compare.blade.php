@extends('layouts.app')

@section('page_title')
    You and {{ $span->name }}
@endsection

<x-shared.interactive-card-styles />

@push('styles')
<link rel="stylesheet" href="{{ asset('css/comparison.css') }}">
<style>
    .comparison-input-dropdown {
        position: absolute;
        z-index: 9999;
        background: white;
        border: 1px solid #dee2e6;
        border-radius: 0.375rem;
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        max-height: 300px;
        overflow-y: auto;
        min-width: 250px;
    }
    
    .comparison-input-dropdown .dropdown-item {
        padding: 0.5rem 1rem;
        border-bottom: 1px solid #f8f9fa;
        cursor: pointer;
    }
    
    .comparison-input-dropdown .dropdown-item:hover {
        background-color: #f8f9fa;
    }
    
    .comparison-input-dropdown .dropdown-item:last-child {
        border-bottom: none;
    }
    
    .comparison-input-container {
        position: relative;
    }
    
    /* Remove left border radius from input fields in button groups */
    .btn-group .comparison-input-field {
        border-top-left-radius: 0;
        border-bottom-left-radius: 0;
        border-left: none;
    }
</style>
@endpush

@push('scripts')
<script src="{{ asset('js/comparison.js') }}"></script>
<script>
$(document).ready(function() {
    let searchTimeout = null;
    let activeDropdown = null;
    
    // Handle input interactions
    $(document).on('focus', '.comparison-input-field', function() {
        console.log('Focus event triggered on comparison input');
        const input = $(this);
        const connectionType = input.data('connection-type');
        const searchTerm = input.val().trim();
        
        console.log('Focus debug:', {
            input: input[0],
            connectionType: connectionType,
            searchTerm: searchTerm,
            inputClasses: input.attr('class')
        });
        
        // Show dropdown immediately when focused, even if empty
        if (searchTerm) {
            console.log('Search term exists, calling searchForSpans');
            searchForSpans(searchTerm, connectionType, input);
        } else {
            console.log('No search term, showing empty dropdown');
            // Show empty state or recent items
            showEmptyDropdown(input, connectionType);
        }
    });
    
    $(document).on('input', '.comparison-input-field', function() {
        console.log('Input event triggered on comparison input');
        const input = $(this);
        const connectionType = input.data('connection-type');
        const searchTerm = input.val().trim();
        
        console.log('Input debug:', {
            input: input[0],
            connectionType: connectionType,
            searchTerm: searchTerm,
            searchTermLength: searchTerm.length
        });
        
        // Clear any existing timeout
        if (searchTimeout) {
            clearTimeout(searchTimeout);
        }
        
        if (searchTerm.length > 0) {
            console.log('Setting up debounced search for:', searchTerm);
            // Debounce the search
            searchTimeout = setTimeout(() => {
                console.log('Executing debounced search for:', searchTerm);
                searchForSpans(searchTerm, connectionType, input);
            }, 300);
        } else {
            console.log('Search term empty, showing empty dropdown');
            // Show empty state when input is cleared
            showEmptyDropdown(input, connectionType);
        }
    });
    
    // Hide dropdown when clicking outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.comparison-input-container').length) {
            hideDropdown();
        }
    });
    
    function searchForSpans(searchTerm, connectionType, input) {
        console.log('searchForSpans called with:', { searchTerm, connectionType });
        
        // Determine allowed span types based on connection type
        const allowedTypes = getAllowedSpanTypes(connectionType);
        
        console.log('Allowed types for', connectionType, ':', allowedTypes);
        
        if (!allowedTypes || allowedTypes.length === 0) {
            console.log('No allowed types found, returning');
            return;
        }
        
        // Build search parameters
        const searchParams = new URLSearchParams({
            q: searchTerm,
            types: allowedTypes.join(',')
        });
        
        const searchUrl = `/spans/search?${searchParams.toString()}`;
        console.log('Making search request to:', searchUrl);
        console.log('Search parameters:', {
            q: searchTerm,
            types: allowedTypes.join(',')
        });
        
        // Make AJAX request to search for spans (using web route, not API)
        fetch(searchUrl, {
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        })
        .then(response => {
            console.log('Search response status:', response.status);
            return response.json();
        })
        .then(data => {
            console.log('Search response data:', data);
            displaySearchResults(data.spans || [], searchTerm, input, connectionType);
        })
        .catch(error => {
            console.error('Search error:', error);
            displaySearchResults([], searchTerm, input, connectionType);
        });
    }
    
    function displaySearchResults(spans, searchTerm, input, connectionType) {
        console.log('displaySearchResults called with:', { spans: spans.length, searchTerm, connectionType });
        
        hideDropdown();
        
        if (spans.length === 0) {
            console.log('No spans to display');
            return;
        }
        
        console.log('Creating dropdown for', spans.length, 'spans');
        
        const dropdown = $(`
            <div class="dropdown-menu show position-absolute" 
                 style="top: 100%; left: 0; right: 0; z-index: 1000; max-height: 200px; overflow-y: auto;">
            </div>
        `);
        
        spans.forEach((span, index) => {
            console.log('Processing span', index, ':', span);
            const itemClass = span.is_placeholder ? 'dropdown-item text-muted' : 'dropdown-item';
            const icon = span.is_placeholder ? 'bi-plus-circle' : 'bi-' + getTypeIcon(span.type_id);
            const badge = span.is_placeholder ? '<span class="badge bg-secondary ms-2">New</span>' : '';
            
            const item = $(`
                <a class="${itemClass}" href="#" data-span-id="${span.id || ''}" data-span-name="${span.name}" data-span-type="${span.type_id}">
                    <i class="bi ${icon} me-2"></i>
                    ${span.name}
                    <small class="text-muted">(${span.type_name})</small>
                    ${badge}
                </a>
            `);
            
            item.on('click', function(e) {
                e.preventDefault();
                console.log('Dropdown item clicked:', span);
                
                // Show preview with save/cancel buttons
                showConnectionPreview(input, span, connectionType);
                
                hideDropdown();
            });
            
            dropdown.append(item);
        });
        
        console.log('Appending dropdown to input parent');
        input.parent().append(dropdown);
        activeDropdown = dropdown;
    }
    
    function createConnectionWithExistingSpan(spanId, spanName, spanType, input, connectionType) {
        const parentSpanId = input.data('span-id');
        const age = input.data('age');
        
        console.log('Creating connection with existing span:', { spanId, spanName, spanType, parentSpanId, age, connectionType });
        
        // Create the connection via AJAX
        fetch('/spans/api/connections', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                parent_id: parentSpanId,
                child_id: spanId,
                type_id: connectionType,
                age: age
            })
        })
        .then(response => response.json())
        .then(data => {
            console.log('Connection creation response:', data);
            if (data.success) {
                // Show success message
                showFeedback('Connection created successfully!', 'success');
                
                // Reload the page to show the updated mirror sentences
                setTimeout(() => {
                    location.reload();
                }, 1000); // Small delay to show the success message
            } else {
                showFeedback('Failed to create connection: ' + (data.message || 'Unknown error'), 'error');
            }
        })
        .catch(error => {
            console.error('Error creating connection:', error);
            showFeedback('Error creating connection', 'error');
        });
    }
    
    function createConnectionWithNewSpan(spanName, spanType, input, connectionType) {
        const parentSpanId = input.data('span-id');
        const age = input.data('age');
        
        console.log('Creating connection with new span:', { spanName, spanType, parentSpanId, age, connectionType });
        
        // First create the new span, then create the connection
        fetch('/spans/api/spans', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                name: spanName,
                type_id: spanType,
                access_level: 'private'
            })
        })
        .then(response => response.json())
        .then(data => {
            console.log('Span creation response:', data);
            if (data.success) {
                // Now create the connection with the new span
                return createConnectionWithExistingSpan(data.span.id, spanName, spanType, input, connectionType);
                        } else {
                showFeedback('Failed to create span: ' + data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error creating span:', error);
            showFeedback('Error creating span', 'error');
        });
    }
    
    function replaceInputWithConnection(input, spanName, connectionType) {
        // Handle both jQuery objects and mock objects
        let container;
        let age;
        
        if (input.closest && typeof input.closest === 'function') {
            // It's a jQuery object
            container = input.closest('.comparison-input-container');
            age = input.data('age');
        } else {
            // It's a mock object, find the original input element
            const parentSpanId = input.data('span-id');
            const ageNum = input.data('age');
            
            // Find the input element that matches these data attributes
            const originalInput = $(`.comparison-input-field[data-span-id="${parentSpanId}"][data-age="${ageNum}"]`);
            if (originalInput.length > 0) {
                container = originalInput.closest('.comparison-input-container');
                age = ageNum;
            } else {
                console.error('Could not find original input element');
                return;
            }
        }
        
        // Create a placeholder connection display
        const connectionHtml = `
            <div class="interactive-card-base">
                <div class="btn-group btn-group-sm" role="group">
                    <button type="button" class="btn btn-outline-secondary disabled" style="min-width: 40px;">
                        <i class="bi bi-clock"></i>
                    </button>
                    <button type="button" class="btn btn-outline-info">
                        at age ${age}
                    </button>
                    <button type="button" class="btn inactive">
                        you ${getConnectionTypePredicate(connectionType)}
                    </button>
                    <a href="#" class="btn btn-primary">
                        ${spanName}
                    </a>
                    <button type="button" class="btn btn-outline-warning">
                        <small>Placeholder</small>
                    </button>
                </div>
            </div>
        `;
        
        container.html(connectionHtml);
    }
    
    function getTypeIcon(typeId) {
        const iconMap = {
            'person': 'person',
            'place': 'geo-alt',
            'organisation': 'building',
            'event': 'calendar-event',
            'thing': 'box',
            'band': 'music-note',
            'role': 'person-badge'
        };
        
        return iconMap[typeId] || 'question-circle';
    }
    
    function getAllowedSpanTypes(connectionType) {
        const typeMap = {
            'residence': ['place'],
            'employment': ['organisation'],
            'education': ['organisation'],
            'membership': ['organisation', 'band'],
            'has_role': ['organisation'],
            'at_organisation': ['organisation'],
            'travel': ['place'],
            'participation': ['event'],
            'ownership': ['thing'],
            'contains': ['thing'],
            'created': ['thing'],
            'family': ['person'],
            'relationship': ['person'],
            'friend': ['person'],
            'located': ['place']
        };
        
        return typeMap[connectionType] || ['person'];
    }
    
    function getConnectionTypePredicate(connectionType) {
        const predicateMap = {
            'residence': 'lived in',
            'employment': 'worked at',
            'education': 'studied at',
            'membership': 'member of',
            'has_role': 'has role',
            'at_organisation': 'at',
            'travel': 'traveled to',
            'participation': 'participated in',
            'ownership': 'owned',
            'contains': 'contains',
            'created': 'created',
            'family': 'related to',
            'relationship': 'has relationship with',
            'friend': 'is friend of',
            'located': 'located in'
        };
        
        return predicateMap[connectionType] || 'connected to';
    }
    
    function hideDropdown() {
        if (activeDropdown) {
            activeDropdown.remove();
            activeDropdown = null;
        }
    }
    
    function showFeedback(message, type) {
        const alertClass = type === 'success' ? 'alert-success' : 
                          type === 'error' ? 'alert-danger' : 
                          type === 'info' ? 'alert-info' : 'alert-secondary';
        const iconClass = type === 'success' ? 'check-circle' : 
                         type === 'error' ? 'exclamation-triangle' : 
                         type === 'info' ? 'info-circle' : 'question-circle';
        
        const feedback = $(`
            <div class="alert ${alertClass} alert-dismissible fade show position-fixed" 
                 style="top: 20px; right: 20px; z-index: 9999; min-width: 300px;">
                <i class="bi bi-${iconClass} me-2"></i>${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `);
        
        $('body').append(feedback);
        
        // Auto-dismiss after 3 seconds
        setTimeout(() => {
            feedback.alert('close');
        }, 3000);
    }
    
    function showEmptyDropdown(input, connectionType) {
        console.log('showEmptyDropdown called with:', { connectionType });
        
        hideDropdown();
        
        const allowedTypes = getAllowedSpanTypes(connectionType);
        const typeNames = allowedTypes.map(type => type.charAt(0).toUpperCase() + type.slice(1)).join(', ');
        
        console.log('Allowed types:', allowedTypes, 'Type names:', typeNames);
        
        const dropdown = $(`
            <div class="dropdown-menu show position-absolute" 
                 style="top: 100%; left: 0; right: 0; z-index: 1000;">
                <div class="dropdown-item text-muted">
                    <i class="bi bi-search me-2"></i>
                    Start typing to search for ${typeNames}...
                            </div>
                        </div>
        `);
        
        console.log('Appending empty dropdown to input parent');
        input.parent().append(dropdown);
        activeDropdown = dropdown;
    }
    
    function showConnectionPreview(input, span, connectionType) {
        const container = input.closest('.comparison-input-container');
        const age = input.data('age');
        const parentSpanId = input.data('span-id');
        
        // Create preview HTML
        const previewHtml = `
            <div class="interactive-card-base">
                <div class="btn-group btn-group-sm" role="group">
                    <button type="button" class="btn btn-outline-secondary disabled" style="min-width: 40px;">
                        <i class="bi bi-clock"></i>
                    </button>
                    <button type="button" class="btn btn-outline-info">
                        at age ${age}
                    </button>
                    <button type="button" class="btn inactive">
                        you ${getConnectionTypePredicate(connectionType)}
                    </button>
                    <a href="#" class="btn btn-primary">
                        ${span.name}
                    </a>
                    ${span.is_placeholder ? '<button type="button" class="btn btn-outline-warning"><small>New</small></button>' : ''}
                </div>
                <div class="mt-2">
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button" class="btn btn-success" onclick="saveConnection('${span.id || ''}', '${span.name}', '${span.type_id}', '${connectionType}', '${parentSpanId}', '${age}')">
                            <i class="bi bi-check me-1"></i>Save
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="cancelConnection()">
                            <i class="bi bi-x me-1"></i>Cancel
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        container.html(previewHtml);
    }
    
    function saveConnection(spanId, spanName, spanType, connectionType, parentSpanId, age) {
        console.log('Saving connection:', { spanId, spanName, spanType, connectionType, parentSpanId, age });
        
        // Convert age back to number
        const ageNum = parseInt(age);
        
        // Create a mock input object with the data method
        const mockInput = {
            data: function(key) {
                if (key === 'span-id') return parentSpanId;
                if (key === 'age') return ageNum;
                return null;
            }
        };
        
        if (spanId) {
            // Use existing span
            createConnectionWithExistingSpan(spanId, spanName, spanType, mockInput, connectionType);
        } else {
            // Create new span
            createConnectionWithNewSpan(spanName, spanType, mockInput, connectionType);
        }
    }
    
    function cancelConnection() {
        // Show cancellation message
        showFeedback('Connection creation cancelled', 'info');
        
        // Reload the page to reset the form
        setTimeout(() => {
            location.reload();
        }, 1000);
    }
    
    // Make functions globally accessible
    window.saveConnection = saveConnection;
    window.cancelConnection = cancelConnection;
});
</script>
@endpush

@section('content')
<div class="container-fluid py-4">
    <!-- Comparison Timeline -->
    <div class="row mb-4">
        <div class="col-12">
            <x-spans.comparison-timeline :span1="$span" :span2="$personalSpan" />
        </div>
    </div>

    <div class="row">
        <!-- First Column: Current Span's Connections (50%) -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h2 class="h5 mb-0">
                        <i class="bi bi-person-fill text-primary me-2"></i>
                        {{ $span->name }}'s Life
                    </h2>
                </div>
                        <div class="card-body">
                    <!-- Span details header -->
                    <div class="mb-4">
                        <x-spans.display.interactive-card :span="$span" />
                    </div>

                    <!-- Connections -->
                    <h3 class="h6 mb-3">Connections & Events</h3>
                    @php
                        // Get connections where this span is the subject (parent) and has temporal information
                        $spanConnections = $span->connectionsAsSubjectWithAccess()
                            ->whereNotNull('connection_span_id')
                            ->whereHas('connectionSpan', function($query) {
                                $query->whereNotNull('start_year'); // Only connections with dates
                            })
                            ->where('child_id', '!=', $span->id) // Exclude self-referential connections
                            ->with(['connectionSpan', 'child', 'type'])
                            ->get()
                            ->sortBy(function($connection) {
                                return $connection->connectionSpan->start_year;
                            });
                                    @endphp

                    @if($spanConnections->isEmpty())
                        <p class="text-muted">No temporal connections recorded yet.</p>
                    @else
                        @foreach($spanConnections as $connection)
                            <div class="mb-3">
                                <x-connections.interactive-card-age :connection="$connection" :span="$span" />
                            </div>
                        @endforeach
                    @endif
                        </div>
                    </div>
        </div>

        <!-- Second Column: User's Personal Span Connections (50%) -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h2 class="h5 mb-0">
                        <i class="bi bi-person-fill text-success me-2"></i>
                        Your Life
                    </h2>
                </div>
                <div class="card-body">
                    <!-- Personal span details header -->
                    <div class="mb-4">
                        <x-spans.display.interactive-card :span="$personalSpan" />
                    </div>

                    <!-- Mirror Connections -->
                    <h3 class="h6 mb-3">Your Life During the Same Periods</h3>
                    @php
                        // Get all user connections for overlap calculations
                        $userConnections = $personalSpan->connectionsAsSubject()
                            ->whereNotNull('connection_span_id')
                            ->whereHas('connectionSpan', function($query) {
                                $query->whereNotNull('start_year');
                            })
                            ->where('child_id', '!=', $personalSpan->id)
                            ->with(['connectionSpan', 'child', 'type'])
                            ->get();

                        // Create mirror sentences for each span connection
                        $mirrorSentences = [];
                        
                        foreach ($spanConnections as $spanConnection) {
                            $spanStartAge = $spanConnection->connectionSpan->start_year - $span->start_year;
                            $spanEndAge = $spanConnection->connectionSpan->end_year ? 
                                $spanConnection->connectionSpan->end_year - $span->start_year : null;
                            
                            // Calculate user's current age
                            $userCurrentAge = now()->year - $personalSpan->start_year;
                            
                            // Skip if this would be in the future for the user
                            if ($spanStartAge > $userCurrentAge) {
                                continue;
                            }
                            
                            // Find overlapping user connections of the same type
                            $matchingUserConnections = $userConnections->filter(function($userConn) use ($spanConnection, $personalSpan, $spanStartAge, $spanEndAge) {
                                // Check if it's the same connection type
                                if ($userConn->type_id !== $spanConnection->type_id) {
                                    return false;
                                }
                                
                                // Calculate user ages for this connection
                                $userStartAge = $userConn->connectionSpan->start_year - $personalSpan->start_year;
                                $userEndAge = $userConn->connectionSpan->end_year ? 
                                    $userConn->connectionSpan->end_year - $personalSpan->start_year : null;
                                
                                // Check for overlap using Allen interval algebra
                                if ($spanEndAge && $userEndAge) {
                                    // Both have end ages - check for overlap
                                    return max($spanStartAge, $userStartAge) <= min($spanEndAge, $userEndAge);
                                } elseif ($spanEndAge && !$userEndAge) {
                                    // Span has end age, user doesn't - check if user started before span ended
                                    return $userStartAge <= $spanEndAge;
                                } elseif (!$spanEndAge && $userEndAge) {
                                    // User has end age, span doesn't - check if span started before user ended
                                    return $spanStartAge <= $userEndAge;
                                } else {
                                    // Neither has end age - they overlap if both are ongoing
                                    return true;
                                }
                            });
                            
                            if ($matchingUserConnections->isNotEmpty()) {
                                // We have matching data - create mirror sentence
                                $mirrorSentences[] = [
                                    'type' => 'mirror',
                                    'connection' => $matchingUserConnections->first(),
                                    'spanConnection' => $spanConnection,
                                    'spanStartAge' => $spanStartAge,
                                    'spanEndAge' => $spanEndAge
                                ];
                            } else {
                                // No matching data - create input prompt
                                $mirrorSentences[] = [
                                    'type' => 'input',
                                    'spanConnection' => $spanConnection,
                                    'spanStartAge' => $spanStartAge,
                                    'spanEndAge' => $spanEndAge,
                                    'connectionType' => $spanConnection->type
                                ];
                            }
                        }
                    @endphp

                    @if(empty($mirrorSentences))
                        <p class="text-muted">No corresponding periods to compare.</p>
                    @else
                        @foreach($mirrorSentences as $mirror)
                            @if($mirror['type'] === 'mirror')
                                <!-- Mirror sentence with actual data -->
                                <div class="mb-3">
                                    <x-connections.interactive-card-age :connection="$mirror['connection']" :span="$personalSpan" />
                                </div>
                            @else
                                <!-- Input prompt for missing data -->
                                <div class="mb-3">
                                    <div class="comparison-input-container">
                                        <div class="interactive-card-base">
                                            <div class="btn-group btn-group-sm" role="group">
                                                <!-- Age prompt -->
                                                <button type="button" class="btn btn-outline-info">
                                                    at age {{ $mirror['spanStartAge'] }}
                                                </button>
                                                
                                                <!-- Question -->
                                                <button type="button" class="btn inactive">
                                                    you {{ $mirror['connectionType']->forward_predicate }}
                                                </button>
                                                
                                                <!-- Input field -->
                                                <input type="text" 
                                                       class="form-control form-control-sm comparison-input-field" 
                                                       placeholder="{{ in_array($mirror['connectionType']->type, ['family', 'relationship', 'friend']) ? 'who?' : (in_array($mirror['connectionType']->type, ['has_role', 'created', 'contains', 'membership']) ? 'what?' : 'where?') }}" 
                                                       style="width: 200px;"
                                                       data-connection-type="{{ $mirror['connectionType']->type }}"
                                                       data-span-id="{{ $personalSpan->id }}"
                                                       data-age="{{ $mirror['spanStartAge'] }}">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        @endforeach
                        @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection 