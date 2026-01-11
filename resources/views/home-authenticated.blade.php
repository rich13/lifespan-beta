@extends('layouts.app')

@php
    // Get the user's personal span for use throughout the template
    $personalSpan = auth()->user()->personalSpan ?? null;
    
    // Load connections once for all components to avoid duplicate queries
    $userConnectionsAsSubject = collect();
    $userConnectionsAsObject = collect();
    $allUserConnections = collect();
    
    if ($personalSpan) {
        // Load connections as subject (outgoing) with eager loading
        $userConnectionsAsSubject = $personalSpan->connectionsAsSubject()
            ->whereNotNull('connection_span_id')
            ->whereHas('connectionSpan', function($query) {
                $query->whereNotNull('start_year');
            })
            ->where('child_id', '!=', $personalSpan->id)
            ->with(['connectionSpan', 'child', 'type'])
            ->get();
        
        // Load connections as object (incoming) with eager loading
        $userConnectionsAsObject = $personalSpan->connectionsAsObject()
            ->whereNotNull('connection_span_id')
            ->whereHas('connectionSpan', function($query) {
                $query->whereNotNull('start_year');
            })
            ->where('parent_id', '!=', $personalSpan->id)
            ->with(['connectionSpan', 'parent', 'type'])
            ->get();
        
        // Combine for components that need all connections
        $allUserConnections = $userConnectionsAsSubject->concat($userConnectionsAsObject);
    }
@endphp

@section('page_title')
    @php
        $currentDate = \App\Helpers\DateHelper::getCurrentDate();
    @endphp
    @if(request()->cookie('time_travel_date'))
        Time Travel: {{ $currentDate->format('F j, Y') }}
    @else
        Today is {{ $currentDate->format('F j, Y') }}
    @endif
@endsection

<x-shared.interactive-card-styles />

@section('page_filters')
    <!-- Homepage-specific filters can go here if needed -->
@endsection

@section('scripts')
<style>
    /* Comparison input dropdown styles for missing connections prompt */
    .comparison-input-container {
        position: relative;
    }
    
    .comparison-input-dropdown {
        position: absolute !important;
        top: 100% !important;
        left: 0 !important;
        right: 0 !important;
        z-index: 1000;
        max-height: 200px;
        overflow-y: auto;
        width: 100%;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        border: 1px solid rgba(0, 0, 0, 0.125);
    }
    
    .comparison-input-dropdown .dropdown-item {
        padding: 0.5rem 1rem;
        border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    }
    
    .comparison-input-dropdown .dropdown-item:last-child {
        border-bottom: none;
    }
    
    .comparison-input-dropdown .dropdown-item:hover {
        background-color: #f8f9fa;
    }
    
    /* Connection preview styles */
    .connection-preview {
        background-color: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 0.375rem;
        padding: 1rem;
        margin-top: 1rem;
    }
    
    .connection-preview .preview-header {
        display: flex;
        justify-content: between;
        align-items: center;
        margin-bottom: 0.5rem;
    }
    
    .connection-preview .preview-content {
        font-size: 0.875rem;
        color: #6c757d;
    }
    
    /* Question card styles */
    .question-card {
        transition: all 0.3s ease;
        border: 2px solid transparent;
    }
    
    .question-card:hover {
        border-color: #0d6efd;
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    }
    
    .question-card.answered {
        border-color: #198754;
        background-color: #f8fff9;
    }
    
    .question-card.answered:hover {
        border-color: #146c43;
    }
    
    /* Answer input styles */
    .answer-input {
        border: 1px solid #dee2e6;
        border-radius: 0.375rem;
        padding: 0.5rem;
        font-size: 0.875rem;
        width: 100%;
        transition: border-color 0.15s ease-in-out;
    }
    
    .answer-input:focus {
        border-color: #0d6efd;
        outline: 0;
        box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
    }
    
    /* Submit button styles */
    .submit-answer-btn {
        background-color: #0d6efd;
        color: white;
        border: none;
        border-radius: 0.375rem;
        padding: 0.5rem 1rem;
        font-size: 0.875rem;
        cursor: pointer;
        transition: background-color 0.15s ease-in-out;
    }
    
    .submit-answer-btn:hover {
        background-color: #0b5ed7;
    }
    
    .submit-answer-btn:disabled {
        background-color: #6c757d;
        cursor: not-allowed;
    }
    
    /* Loading spinner */
    .loading-spinner {
        display: inline-block;
        width: 1rem;
        height: 1rem;
        border: 2px solid #f3f3f3;
        border-top: 2px solid #0d6efd;
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }
    
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
</style>

<script>
    $(document).ready(function() {
        // Missing connections functionality
        let searchTimeout;
        
        // Handle focus events for comparison inputs
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
            
            // Always show dropdown when focused, even if empty
            if (searchTerm) {
                console.log('Search term exists, calling searchForSpans');
                searchForSpans(searchTerm, connectionType, input);
            } else {
                console.log('No search term, showing empty dropdown');
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
            
            // Search immediately when typing
            if (searchTerm.length > 0) {
                console.log('Search term exists, calling searchForSpans');
                searchForSpans(searchTerm, connectionType, input);
            } else {
                console.log('No search term, showing empty dropdown');
                showEmptyDropdown(input, connectionType);
            }
        });
        
        // Handle submit button clicks
        $(document).on('click', '.submit-answer-btn', function() {
            const btn = $(this);
            const card = btn.closest('.question-card');
            const input = card.find('.comparison-input-field');
            const connectionType = input.data('connection-type');
            const question = input.data('question');
            const selectedSpan = input.data('selected-span');
            
            if (!selectedSpan) {
                alert('Please select a span first');
                return;
            }
            
            // Show loading state
            btn.prop('disabled', true);
            btn.html('<span class="loading-spinner"></span> Submitting...');
            
            // Submit the connection
            $.ajax({
                url: '/connections',
                method: 'POST',
                data: {
                    subject_id: selectedSpan.id,
                    object_id: '{{ $personalSpan->id }}',
                    connection_type_id: connectionType,
                    _token: $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    // Mark card as answered
                    card.addClass('answered');
                    card.find('.card-body').html(`
                        <div class="text-success">
                            <i class="bi bi-check-circle-fill me-2"></i>
                            Connected to ${selectedSpan.name}
                        </div>
                    `);
                },
                error: function(xhr) {
                    console.error('Error creating connection:', xhr);
                    alert('Error creating connection. Please try again.');
                    
                    // Reset button
                    btn.prop('disabled', false);
                    btn.html('Submit');
                }
            });
        });
        
        // Hide dropdown when clicking outside
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.comparison-input-container').length) {
                hideDropdown();
            }
        });
        
        function searchForSpans(searchTerm, connectionType, input) {
            const allowedTypes = getAllowedSpanTypes(connectionType);
            
            if (!allowedTypes || allowedTypes.length === 0) {
                return;
            }
            
            const searchParams = new URLSearchParams({
                q: searchTerm,
                types: allowedTypes.join(',')
            });
            
            const searchUrl = `/spans/search?${searchParams.toString()}`;
            
            fetch(searchUrl, {
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            })
            .then(response => response.json())
            .then(data => {
                displaySearchResults(data || [], searchTerm, input, connectionType);
            })
            .catch(error => {
                console.error('Search error:', error);
                displaySearchResults([], searchTerm, input, connectionType);
            });
        }
        
        function displaySearchResults(spans, searchTerm, input, connectionType) {
            hideDropdown();
            
            const dropdown = $(`
                <div class="dropdown-menu show position-absolute" 
                     style="top: 100%; left: 0; right: 0; z-index: 1000; max-height: 200px; overflow-y: auto; width: 100%;">
                </div>
            `);
            
            // Always show existing results if any
            spans.forEach((span, index) => {
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
                    const question = input.data('question');
                    showConnectionPreview(input, span, connectionType, question);
                    hideDropdown();
                });
                
                dropdown.append(item);
            });
            
            // Always show the "Add new" option if we have a search term
            if (searchTerm.length > 0) {
                const addNewItem = $(`
                    <a class="dropdown-item text-primary" href="#" data-action="add-new" data-search-term="${searchTerm}">
                        <i class="bi bi-plus-circle me-2"></i>
                        Add "${searchTerm}" as new ${getTypeName(connectionType)}
                    </a>
                `);
                
                addNewItem.on('click', function(e) {
                    e.preventDefault();
                    const question = input.data('question');
                    const newSpan = {
                        id: null,
                        name: searchTerm,
                        type_id: getAllowedSpanTypes(connectionType)[0],
                        type_name: getTypeName(connectionType),
                        is_placeholder: true
                    };
                    showConnectionPreview(input, newSpan, connectionType, question);
                    hideDropdown();
                });
                
                dropdown.append(addNewItem);
            }
            
            input.closest('.comparison-input-container').append(dropdown);
        }
        
        function showEmptyDropdown(input, connectionType) {
            hideDropdown();
            
            const dropdown = $(`
                <div class="dropdown-menu show position-absolute" 
                     style="top: 100%; left: 0; right: 0; z-index: 1000; max-height: 200px; overflow-y: auto; width: 100%;">
                    <div class="dropdown-item text-muted">
                        Start typing to search for ${getTypeName(connectionType)}...
                    </div>
                </div>
            `);
            
            input.closest('.comparison-input-container').append(dropdown);
        }
        
        function showConnectionPreview(input, span, connectionType, question) {
            input.val(span.name);
            input.data('selected-span', span);
            
            const previewHtml = `
                <div class="connection-preview">
                    <div class="preview-header">
                        <strong>Preview:</strong>
                        <button type="button" class="btn-close" onclick="$(this).closest('.connection-preview').remove()"></button>
                    </div>
                    <div class="preview-content">
                        <strong>Question:</strong> ${question}<br>
                        <strong>Answer:</strong> ${span.name} (${span.type_name})
                    </div>
                    <button type="button" class="submit-answer-btn mt-2">
                        Submit Connection
                    </button>
                </div>
            `;
            
            input.closest('.question-card').find('.card-body').append(previewHtml);
        }
        
        function hideDropdown() {
            $('.comparison-input-dropdown').remove();
        }
        
        function getAllowedSpanTypes(connectionType) {
            const typeMap = {
                '1': ['person'], // Family
                '2': ['person'], // Friend
                '3': ['organisation'], // Work
                '4': ['place'], // Location
                '5': ['event'], // Event
                '6': ['thing'], // Thing
                '7': ['band'], // Band
                '8': ['person'] // Other
            };
            return typeMap[connectionType] || [];
        }
        
        function getTypeName(connectionType) {
            const nameMap = {
                '1': 'person',
                '2': 'person',
                '3': 'organisation',
                '4': 'place',
                '5': 'event',
                '6': 'thing',
                '7': 'band',
                '8': 'person'
            };
            return nameMap[connectionType] || 'span';
        }
        
        function getTypeIcon(typeId) {
            const icons = {
                'person': 'person-fill',
                'organisation': 'building',
                'place': 'geo-alt-fill',
                'event': 'calendar-event-fill',
                'connection': 'link-45deg',
                'band': 'cassette',
                'thing': 'box'
            };
            return icons[typeId] || 'box';
        }
    });
</script>
@endsection

@section('content')
<div class="container-fluid">
    <div class="row">
        <!-- Column 1: Personal Information -->
        <div class="col-md-3">

            <!-- Lifespan Summary Card -->
            <x-home.lifespan-summary-card />
            
            <!-- Column 1: Missing Connections Prompt -->
            <x-home.missing-connections-prompt 
                :personalSpan="$personalSpan" 
                :userConnectionsAsSubject="$userConnectionsAsSubject"
                :userConnectionsAsObject="$userConnectionsAsObject"
                :allUserConnections="$allUserConnections"
            />
            
            <!-- Life Activity Heatmap -->
            <x-home.life-heatmap-card 
                :userConnectionsAsSubject="$userConnectionsAsSubject"
                :userConnectionsAsObject="$userConnectionsAsObject"
                :allUserConnections="$allUserConnections"
            />
            
            <!-- Lifespan Stats -->
            <x-home.lifespan-stats-card />
        </div>

        <!-- Column 2: Today's Events -->
        <div class="col-md-3">
            <!-- At Your Age Card -->
            <div class="mb-4">
                <x-home.at-your-age-card />
            </div>
            
            <div class="mb-4">
                @php
                    $today = \App\Helpers\DateHelper::getCurrentDate();
                    $spansStartingOnDate = \App\Models\Span::where('start_year', $today->year)
                        ->where('start_month', $today->month)
                        ->where('start_day', $today->day)
                        ->where(function($query) {
                            $query->where('access_level', 'public')
                                ->orWhere('owner_id', auth()->id());
                        })
                        ->get();

                    $spansEndingOnDate = \App\Models\Span::where('end_year', $today->year)
                        ->where('end_month', $today->month)
                        ->where('end_day', $today->day)
                        ->where(function($query) {
                            $query->where('access_level', 'public')
                                ->orWhere('owner_id', auth()->id());
                        })
                        ->get();


                @endphp

                {{-- Commented out: Started Today section
                @if($spansStartingOnDate->isNotEmpty())
                    
                        <div class="card-header">
                            <h3 class="h6 mb-0">
                                <i class="bi bi-calendar-plus text-success me-2"></i>
                                @if(request()->cookie('time_travel_date'))
                                    Started on {{ $today->format('F j') }}
                                @else
                                    Started Today
                                @endif
                            </h3>
                        </div>
                        <div class="card-body">
                            <div class="spans-list">
                                @foreach($spansStartingOnDate as $span)
                                    <x-spans.display.interactive-card :span="$span" />
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif
                --}}

                {{-- Commented out: Ended Today section
                @if($spansEndingOnDate->isNotEmpty())
                    <div class="card mb-3">
                        <div class="card-header">
                            <h3 class="h6 mb-0">
                                <i class="bi bi-calendar-x text-danger me-2"></i>
                                @if(request()->cookie('time_travel_date'))
                                    Ended on {{ $today->format('F j') }}
                                @else
                                    Ended Today
                                @endif
                            </h3>
                        </div>
                        <div class="card-body">
                            <div class="spans-list">
                                @foreach($spansEndingOnDate as $span)
                                    <x-spans.display.interactive-card :span="$span" />
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif
                --}}

                <x-upcoming-anniversaries />
            </div>
        </div>

        <!-- Column 3: Missing Connections -->
        <div class="col-md-3">
            <!-- Featured Person -->
            <x-home.random-person-card />

            <!-- Random Blue Plaque -->
            <x-home.random-blue-plaque-card />

            <!-- Wikipedia On This Day -->
            <div class="mb-4">
                <x-wikipedia-on-this-day />
            </div>

            <!-- Welcome Modal Logic -->
            @php
                // Check if we should show welcome modal (no connections yet)
                // Use pre-loaded connections to avoid duplicate queries
                $hasAnyConnections = $allUserConnections->count() > 0;
                
                // Check if we should show completion modal (all questions answered but haven't seen modal)
                $shouldShowCompletionModal = false;
                if ($personalSpan && $hasAnyConnections) {
                    // Define the questions that should be answered
                    $questions = [
                        'residence' => [
                            'Where were you born?',
                            'Where do you currently live?'
                        ],
                        'education' => [
                            'What primary school did you go to?',
                            'What secondary school did you go to?'
                        ],
                        'family' => [
                            'What\'s your mother\'s name?',
                            'What\'s your father\'s name?'
                        ]
                    ];
                    
                    // Calculate total questions and answered questions
                    // Use pre-loaded connections to avoid duplicate queries
                    $totalQuestions = 0;
                    $answeredQuestions = 0;
                    $connectionsByType = $allUserConnections->groupBy('type_id');
                    
                    foreach ($questions as $type => $typeQuestions) {
                        $totalQuestions += count($typeQuestions);
                        $currentCount = $connectionsByType->get($type, collect())->count();
                        $answeredQuestions += min($currentCount, count($typeQuestions));
                    }
                    
                    // Check if all questions are answered
                    $allQuestionsAnswered = $answeredQuestions >= $totalQuestions;
                    
                    // Check if completion modal has been seen (we'll check localStorage in JavaScript)
                    $shouldShowCompletionModal = $allQuestionsAnswered;
                    
                    echo "<!-- Debug: totalQuestions = $totalQuestions, answeredQuestions = $answeredQuestions, allQuestionsAnswered = " . ($allQuestionsAnswered ? 'true' : 'false') . ", shouldShowCompletionModal = " . ($shouldShowCompletionModal ? 'true' : 'false') . " -->";
                }
            @endphp

            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const hasAnyConnections = {{ $hasAnyConnections ? 'true' : 'false' }};
                    const userId = '{{ auth()->user()->id }}';
                    const welcomeModalKey = `hasSeenWelcomeModal_${userId}`;
                    const hasSeenWelcomeModal = localStorage.getItem(welcomeModalKey) === 'true';
                    const shouldShowWelcomeModal = !hasAnyConnections && !hasSeenWelcomeModal;
                    
                    console.log('Welcome modal check:', {
                        hasAnyConnections: hasAnyConnections,
                        userId: userId,
                        welcomeModalKey: welcomeModalKey,
                        hasSeenWelcomeModal: hasSeenWelcomeModal,
                        shouldShowWelcomeModal: shouldShowWelcomeModal,
                        localStorageValue: localStorage.getItem(welcomeModalKey)
                    });
                    
                    if (shouldShowWelcomeModal) {
                        console.log('Showing welcome modal');
                        const modalElement = document.getElementById('welcomeModal');
                        if (modalElement) {
                            const modal = new bootstrap.Modal(modalElement);
                            
                            // Set the flag only when the modal is dismissed
                            modalElement.addEventListener('hidden.bs.modal', function() {
                                localStorage.setItem(welcomeModalKey, 'true');
                                console.log('Welcome modal dismissed and flag set');
                            });
                            
                            modal.show();
                            console.log('Welcome modal shown');
                        }
                    } else {
                        console.log('Not showing welcome modal');
                    }
                    
                    // Check if we should show completion modal
                    const shouldShowCompletionModal = {{ $shouldShowCompletionModal ? 'true' : 'false' }};
                    const completionModalKey = `hasSeenCompletionModal_${userId}`;
                    const hasSeenCompletionModal = localStorage.getItem(completionModalKey) === 'true';
                    
                    console.log('Completion modal check:', {
                        shouldShowCompletionModal: shouldShowCompletionModal,
                        hasSeenCompletionModal: hasSeenCompletionModal,
                        completionModalKey: completionModalKey
                    });
                    
                    if (shouldShowCompletionModal && !hasSeenCompletionModal) {
                        console.log('Showing completion modal');
                        const modalElement = document.getElementById('completionModal');
                        console.log('Modal element found:', modalElement);
                        
                        if (modalElement) {
                            const modal = new bootstrap.Modal(modalElement);
                            console.log('Bootstrap modal created:', modal);
                            
                            // Set the flag and redirect when the modal is dismissed
                            modalElement.addEventListener('hidden.bs.modal', function() {
                                localStorage.setItem(completionModalKey, 'true');
                                console.log('Completion modal dismissed and flag set');
                                
                                // Redirect to user's personal span
                                const personalSpanUrl = '{{ route("spans.show", auth()->user()->personalSpan) }}';
                                console.log('Redirecting to personal span:', personalSpanUrl);
                                window.location.href = personalSpanUrl;
                            });
                            
                            // Add a small delay to ensure everything is ready
                            setTimeout(() => {
                                modal.show();
                                console.log('Completion modal show() called');
                            }, 100);
                        } else {
                            console.error('Completion modal element not found!');
                        }
                    } else {
                        console.log('Not showing completion modal');
                    }
                });
            </script>


            {{-- Recently Created Card --}}
            {{-- <div class="mb-4">
                @php
                    // Get recently created public spans
                    $recentlyCreatedSpans = \App\Models\Span::where('access_level', 'public')
                        ->where('state', '!=', 'placeholder')
                        ->where('type_id', '!=', 'connection')
                        ->orderBy('created_at', 'desc')
                        ->limit(5)
                        ->get();
                @endphp

                <div class="card">
                    <div class="card-header">
                        <h3 class="h6 mb-0">
                            <i class="bi bi-plus-circle text-success me-2"></i>
                            Recently Created
                        </h3>
                    </div>
                    <div class="card-body">
                        @if($recentlyCreatedSpans->isEmpty())
                            <p class="text-center text-muted my-3">No public spans created yet.</p>
                            <div class="text-center">
                                <a href="{{ route('spans.create') }}" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-plus-circle me-1"></i>Create Your First Span
                                </a>
                            </div>
                        @else
                            <div class="spans-list">
                                @foreach($recentlyCreatedSpans as $span)
                                    <x-spans.display.interactive-card :span="$span" />
                                @endforeach
                            </div>
                            
                            @if($recentlyCreatedSpans->count() >= 5)
                                <div class="text-center mt-3">
                                    <a href="{{ route('spans.index') }}" class="btn btn-sm btn-outline-secondary">
                                        View all public spans
                                    </a>
                                </div>
                            @endif
                        @endif
                    </div>
                </div>
            </div> --}}


        </div>

        <!-- Column 4: Spans of Type Cards -->
        <div class="col-md-3">
            <!-- Bands Card -->
            <div class="mb-4">
                <x-home.spans-of-type-card 
                    :type="'band'"
                    :title="'Bands'"
                    :icon="'cassette'"
                />
            </div>

            <!-- Museums Card -->
            <div class="mb-4">
                <x-home.spans-of-type-card 
                    :type="'organisation'"
                    :subtype="'museum'"
                    :title="'Museums'"
                    :icon="'building'"
                />
            </div>

            <!-- Books Card -->
            <div class="mb-4">
                <x-home.spans-of-type-card 
                    :type="'thing'"
                    :subtype="'book'"
                    :title="'Books'"
                    :icon="'book'"
                />
            </div>
            
            <!-- Films Card -->
            <div class="mb-4">
                <x-home.spans-of-type-card 
                    :type="'thing'"
                    :subtype="'film'"
                    :title="'Films'"
                    :icon="'film'"
                />
            </div>
            
            <!-- Events Card -->
            <div class="mb-4">
                <x-home.spans-of-type-card 
                    :type="'event'"
                    :title="'Events'"
                    :icon="'calendar-event'"
                />
            </div>
        </div>
    </div>
</div>

<!-- Welcome Modal -->
<div class="modal fade" id="welcomeModal" tabindex="-1" aria-labelledby="welcomeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="welcomeModalLabel">
                    <i class="bi bi-person-circle me-2"></i>
                    Welcome to Lifespan
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-12">
                        <h6 class="text-primary mb-3"><i class="bi bi-chat-left-text me-2"></i> <strong>Inevitable disclaimer</strong></h6>
                        <p class="mb-3">This is a <strong>conceptual prototype</strong>. You're the first users ever.</p>
                        <p class="mb-3">It's been built in a very <strong>exploratory</strong> way... so it's not <em>coherent</em> or <em>complete</em> or <em>consistent</em>...</p>
                        <p class="mb-3">It's been built to <strong>think about the underlying idea</strong>, and to find out how it could work.</p>
                        <p class="mb-3">...and as you're about to see, <strong>everything has to start somewhere</strong> <i class="bi bi-emoji-smile"></i></p>
                        
                        <div class="alert alert-info">
                            <i class="bi bi-cup-hot-fill me-2"></i>
                            <strong>Your mission</strong> is to look around, and <strong>see if you can work out what's going on</strong>.
                        </div>
                        
                        <div class="alert alert-warning">
                            <i class="bi bi-radioactive me-2"></i>
                            <strong>You may find bugs</strong> and things that don't work as expected. <strong>You know how this works.</strong>
                        </div>

                        <div class="alert alert-success">
                            <i class="bi bi-recycle me-2"></i>
                            <strong>There's <u>a lot more</u> to do...</strong> and that includes removing this modal.
                        </div>
                    </div>
                    
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">
                    <i class="bi bi-play-circle me-1"></i>
                    Alright, enough talk
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Completion Modal -->
<div class="modal fade" id="completionModal" tabindex="-1" aria-labelledby="completionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="completionModalLabel">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    OK, that worked!
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-3">
                    You've now got a span to work with!
                </p>
                <p class="mb-0">
                    You can now:
                </p>
                <ul class="mb-3">
                    <li>Explore generally...</li>
                    <li>Add more connections...</li>
                    <li>Edit and enhance your span...</li>
                    <li>...and dismiss this message <i class="bi bi-emoji-smile"></i></li>
                </ul>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-success" data-bs-dismiss="modal">
                    <i class="bi bi-check me-1"></i>
                    Go to your span...
                </button>
            </div>
        </div>
    </div>
</div>

@endsection 