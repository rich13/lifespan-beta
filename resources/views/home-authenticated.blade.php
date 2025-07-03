@extends('layouts.app')

@php
    // Get the user's personal span for use throughout the template
    $personalSpan = auth()->user()->personalSpan ?? null;
@endphp

@section('page_title')
    Today is {{ \Carbon\Carbon::now()->format('F j, Y') }}
@endsection

<x-shared.interactive-card-styles />

@section('page_filters')
    <div class="d-flex align-items-center gap-3">
        <div class="home-search-container position-relative" style="width: 400px;">
            <div class="d-flex align-items-center position-relative">
                <i class="bi bi-search position-absolute ms-2 text-muted z-index-1"></i>
                <input type="text" id="home-search" class="form-control form-control-sm ps-4" placeholder="Search spans..." autocomplete="off">
            </div>
        </div>
    </div>
@endsection

@section('scripts')
<style>
    /* Search dropdown positioning */
    .home-search-container {
        position: relative;
        min-width: 250px;
        flex: 1;
        max-width: 500px;
    }
    
    #home-search {
        width: 100%;
        min-width: 250px;
    }
    
    #search-dropdown {
        position: absolute !important;
        right: 0 !important;
        left: auto !important;
        min-width: 300px;
        max-width: 500px;
        z-index: 1050;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        border: 1px solid rgba(0, 0, 0, 0.125);
        /* Prevent overflow */
        max-width: calc(100vw - 2rem);
        right: 0 !important;
    }
    
    /* Responsive adjustments for search dropdown */
    @media (max-width: 768px) {
        .home-search-container {
            min-width: 200px;
            max-width: 300px;
        }
        
        #home-search {
            min-width: 200px;
        }
        
        #search-dropdown {
            right: 0 !important;
            left: 0 !important;
            min-width: auto;
            max-width: none;
            width: 100%;
        }
    }
    
    /* Additional responsive adjustments for very small screens */
    @media (max-width: 576px) {
        .home-search-container {
            min-width: 150px;
            max-width: 250px;
        }
        
        #home-search {
            min-width: 150px;
        }
    }
    
    /* Ensure dropdown items are properly styled */
    #search-dropdown .dropdown-item {
        padding: 0.5rem 1rem;
        border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    }
    
    #search-dropdown .dropdown-item:last-child {
        border-bottom: none;
    }
    
    #search-dropdown .dropdown-item:hover,
    #search-dropdown .dropdown-item.active {
        background-color: #f8f9fa;
    }
    
    /* Comparison input dropdown styles for missing connections prompt */
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
    
    /* Ensure dropdown appears below the entire button group */
    .comparison-input-container {
        position: relative;
    }
    
    .comparison-input-container .btn-group {
        position: relative;
    }
    
    .comparison-input-container .dropdown-menu {
        margin-top: 0;
        border-top-left-radius: 0;
        border-top-right-radius: 0;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        border: 1px solid rgba(0, 0, 0, 0.125);
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        z-index: 1000;
    }
    
    .comparison-input-container .dropdown-item {
        padding: 0.5rem 1rem;
        border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        cursor: pointer;
    }
    
    .comparison-input-container .dropdown-item:hover {
        background-color: #f8f9fa;
    }
    
    .comparison-input-container .dropdown-item:last-child {
        border-bottom: none;
    }
</style>

<script>
    $(document).ready(function() {
        // Home search functionality
        let searchTimeout;
        const searchInput = $('#home-search');
        const searchDropdown = $('#search-dropdown');
        
        searchInput.on('input', function() {
            const query = $(this).val().trim();
            
            clearTimeout(searchTimeout);
            
            if (query.length < 2) {
                searchDropdown.removeClass('show');
                return;
            }
            
            searchTimeout = setTimeout(function() {
                $.ajax({
                    url: '{{ route("spans.search") }}',
                    method: 'GET',
                    data: { q: query },
                    success: function(data) {
                        if (data.spans && data.spans.length > 0) {
                            let html = '';
                            data.spans.forEach(function(span) {
                                html += `<a class="dropdown-item" href="/spans/${span.slug}">${span.name}</a>`;
                            });
                            searchDropdown.find('.dropdown-menu').html(html);
                            searchDropdown.addClass('show');
                        } else {
                            searchDropdown.removeClass('show');
                        }
                    },
                    error: function() {
                        searchDropdown.removeClass('show');
                    }
                });
            }, 300);
        });
        
        // Hide dropdown when clicking outside
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.home-search-container').length) {
                searchDropdown.removeClass('show');
            }
        });
    });
</script>
@endsection

@section('content')
<div class="container-fluid">
    <div class="row">
        <!-- Left Column: Personal Information -->
        <div class="col-md-4">
            <div class="mb-4">
                @if(auth()->user()->personalSpan)
                    @php
                        $personalSpan = auth()->user()->personalSpan;
                        $today = \Carbon\Carbon::now();
                        
                        // Calculate age
                        $birthDate = \Carbon\Carbon::createFromDate(
                            $personalSpan->start_year,
                            $personalSpan->start_month ?? 1,
                            $personalSpan->start_day ?? 1
                        );
                        
                        $age = $birthDate->diff($today);
                    @endphp

                    <div class="card mb-3">
                        <div class="card-header">
                            <h3 class="h6 mb-0">
                                <i class="bi bi-person-circle text-primary me-2"></i>
                                Your Age
                            </h3>
                        </div>
                        <div class="card-body">
                            <p class="mb-0">
                                You are {{ $age->y }} years, {{ $age->m }} months, and {{ $age->d }} days old
                            </p>
                        </div>
                    </div>



                    @php
                        // Get random person spans that the user can see (excluding the user themselves)
                        $randomSpans = \App\Models\Span::where('type_id', 'person')
                            ->where('id', '!=', $personalSpan->id) // Exclude the user
                            ->where('access_level', 'public') // Only public spans
                            ->where('state', 'complete') // Only complete spans (includes living and deceased)
                            ->whereNotNull('start_year') // Only spans with birth dates
                            ->whereNotNull('start_month')
                            ->whereNotNull('start_day')
                            ->where('start_year', '<', $personalSpan->start_year) // Only people older than the user
                            ->inRandomOrder()
                            ->limit(10) // Get more candidates to filter from
                            ->get();
                        
                        $randomComparisons = [];
                        foreach ($randomSpans as $randomSpan) {
                            $randomBirthDate = \Carbon\Carbon::createFromDate(
                                $randomSpan->start_year,
                                $randomSpan->start_month ?? 1,
                                $randomSpan->start_day ?? 1
                            );
                            
                            // Calculate the date when this person was the user's current age
                            $randomAgeDate = $randomBirthDate->copy()->addYears($age->y)
                                ->addMonths($age->m)
                                ->addDays($age->d);
                            
                            // Check if this person was already dead when they were the user's current age
                            $wasDeadAtUserAge = false;
                            if ($randomSpan->end_year && $randomSpan->end_month && $randomSpan->end_day) {
                                $deathDate = \Carbon\Carbon::createFromDate(
                                    $randomSpan->end_year,
                                    $randomSpan->end_month,
                                    $randomSpan->end_day
                                );
                                
                                // If they died before reaching the user's current age, exclude them
                                if ($deathDate->lt($randomAgeDate)) {
                                    $wasDeadAtUserAge = true;
                                }
                            }
                            
                            // Only include people who were alive when they were the user's current age
                            if (!$wasDeadAtUserAge) {
                                $randomComparisons[] = [
                                    'span' => $randomSpan,
                                    'date' => $randomAgeDate
                                ];
                            }
                            
                            // Stop once we have 3 valid comparisons
                            if (count($randomComparisons) >= 3) {
                                break;
                            }
                        }
                    @endphp

                    @if(!empty($randomComparisons))
                        <div class="card mb-3">
                            <div class="card-header">
                                <h3 class="h6 mb-0">
                                    <i class="bi bi-arrow-left-right text-primary me-2"></i>
                                    At your age...
                                </h3>
                            </div>
                            <div class="card-body">
                                
                                @foreach($randomComparisons as $comparison)
                                    <div class="mb-4">
                                        
                                        @php
                                            $comparisonDateObj = (object)[
                                                'year' => $comparison['date']->year,
                                                'month' => $comparison['date']->month,
                                                'day' => $comparison['date']->day,
                                            ];
                                            
                                            // Debug: Check if the date is in the future
                                            $isFutureDate = $comparison['date']->isFuture();
                                            $currentYear = now()->year;
                                        @endphp
                                        
                                        @if($isFutureDate)
                                            <p class="text-muted small mb-2">
                                                <i class="bi bi-info-circle me-1"></i>
                                                This date is in the future ({{ $comparisonDateObj->year }}), so no historical connections are available yet.
                                            </p>
                                        @else
                                            <h5 class="h6 text-muted mb-2">{{ $comparison['span']->name }}</h5>
                                            <x-spans.display.statement-card 
                                                :span="$comparison['span']" 
                                                eventType="custom"
                                                :eventDate="$comparison['date']->format('Y-m-d')"
                                                customEventText="was your age on" />

                                            <x-spans.display.connections-at-date 
                                                :span="$comparison['span']" 
                                                :date="$comparisonDateObj" />                                                
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @else
                    <div class="card">
                        <div class="card-body">
                            <p class="text-center text-muted my-5">No personal span found. Please update your profile.</p>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <!-- Middle Column: Today's Events -->
        <div class="col-md-4">
            <div class="mb-4">
                @php
                    $today = \Carbon\Carbon::now();
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

                @if($spansStartingOnDate->isNotEmpty())
                    <div class="card mb-3">
                        <div class="card-header">
                            <h3 class="h6 mb-0">
                                <i class="bi bi-calendar-plus text-success me-2"></i>
                                Started Today
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

                @if($spansEndingOnDate->isNotEmpty())
                    <div class="card mb-3">
                        <div class="card-header">
                            <h3 class="h6 mb-0">
                                <i class="bi bi-calendar-x text-danger me-2"></i>
                                Ended Today
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

                <x-upcoming-anniversaries />

                <x-this-month-in-history />
            </div>
        </div>

        <!-- Right Column: Placeholder Connections -->
        <div class="col-md-4">

            <!-- Missing Connections Prompt -->
            <x-home.missing-connections-prompt :personalSpan="$personalSpan" />

            <!-- Completion Modal (always available) -->
            @php
                // Check if we should show completion modal (exactly 8 connections and haven't shown it yet)
                $totalConnections = 0;
                if ($personalSpan) {
                    $userConnectionsAsSubject = $personalSpan->connectionsAsSubject()
                        ->whereNotNull('connection_span_id')
                        ->whereHas('connectionSpan', function($query) {
                            $query->whereNotNull('start_year');
                        })
                        ->where('child_id', '!=', $personalSpan->id)
                        ->count();
                    
                    $userConnectionsAsObject = $personalSpan->connectionsAsObject()
                        ->whereNotNull('connection_span_id')
                        ->whereHas('connectionSpan', function($query) {
                            $query->whereNotNull('start_year');
                        })
                        ->where('parent_id', '!=', $personalSpan->id)
                        ->count();
                    
                    $totalConnections = $userConnectionsAsSubject + $userConnectionsAsObject;
                }
                
                // Debug output
                echo "<!-- Debug: totalConnections = $totalConnections -->";
            @endphp

            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const totalConnections = {{ $totalConnections }};
                    const hasSeenCompletionModal = localStorage.getItem('hasSeenCompletionModal') === 'true';
                    const shouldShowCompletionModal = totalConnections === 8 && !hasSeenCompletionModal;
                    
                    console.log('Completion modal check:', {
                        totalConnections: totalConnections,
                        hasSeenCompletionModal: hasSeenCompletionModal,
                        shouldShowCompletionModal: shouldShowCompletionModal,
                        localStorageValue: localStorage.getItem('hasSeenCompletionModal')
                    });
                    
                    // Check if modal element exists
                    const modalElement = document.getElementById('completionModal');
                    console.log('Modal element found:', !!modalElement);
                    
                    if (shouldShowCompletionModal) {
                        console.log('Showing completion modal');
                        if (modalElement) {
                            const modal = new bootstrap.Modal(modalElement);
                            
                            // Set the flag only when the modal is dismissed
                            modalElement.addEventListener('hidden.bs.modal', function() {
                                localStorage.setItem('hasSeenCompletionModal', 'true');
                                console.log('Completion modal dismissed and flag set');
                            });
                            
                            modal.show();
                            console.log('Completion modal shown');
                        } else {
                            console.error('Completion modal element not found');
                        }
                    } else {
                        console.log('Not showing completion modal - reason:', {
                            totalConnections: totalConnections,
                            isEight: totalConnections === 8,
                            hasSeen: hasSeenCompletionModal,
                            localStorageValue: localStorage.getItem('hasSeenCompletionModal')
                        });
                    }
                });
            </script>

            <!-- Welcome Modal Logic -->
            @php
                // Check if we should show welcome modal (no connections yet)
                $hasAnyConnections = false;
                if ($personalSpan) {
                    $userConnectionsAsSubject = $personalSpan->connectionsAsSubject()
                        ->whereNotNull('connection_span_id')
                        ->whereHas('connectionSpan', function($query) {
                            $query->whereNotNull('start_year');
                        })
                        ->where('child_id', '!=', $personalSpan->id)
                        ->count();
                    
                    $userConnectionsAsObject = $personalSpan->connectionsAsObject()
                        ->whereNotNull('connection_span_id')
                        ->whereHas('connectionSpan', function($query) {
                            $query->whereNotNull('start_year');
                        })
                        ->where('parent_id', '!=', $personalSpan->id)
                        ->count();
                    
                    $hasAnyConnections = ($userConnectionsAsSubject + $userConnectionsAsObject) > 0;
                }
            @endphp

            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const hasAnyConnections = {{ $hasAnyConnections ? 'true' : 'false' }};
                    const hasSeenWelcomeModal = localStorage.getItem('hasSeenWelcomeModal') === 'true';
                    const shouldShowWelcomeModal = !hasAnyConnections && !hasSeenWelcomeModal;
                    
                    console.log('Welcome modal check:', {
                        hasAnyConnections: hasAnyConnections,
                        hasSeenWelcomeModal: hasSeenWelcomeModal,
                        shouldShowWelcomeModal: shouldShowWelcomeModal
                    });
                    
                    if (shouldShowWelcomeModal) {
                        console.log('Showing welcome modal');
                        const modalElement = document.getElementById('welcomeModal');
                        if (modalElement) {
                            const modal = new bootstrap.Modal(modalElement);
                            
                            // Set the flag only when the modal is dismissed
                            modalElement.addEventListener('hidden.bs.modal', function() {
                                localStorage.setItem('hasSeenWelcomeModal', 'true');
                                console.log('Welcome modal dismissed and flag set');
                            });
                            
                            modal.show();
                            console.log('Welcome modal shown');
                        }
                    } else {
                        console.log('Not showing welcome modal');
                    }
                });
            </script>

            <div class="mb-4">
                @php
                    // Get placeholder connections that are connected to the current user's personal span
                    $placeholderConnections = collect();
                    
                    if (auth()->user()->personalSpan) {
                        $personalSpan = auth()->user()->personalSpan;
                        
                        // Get connections where the user's personal span is either the parent or child
                        $placeholderConnections = \App\Models\Connection::where(function($query) use ($personalSpan) {
                                $query->where('parent_id', $personalSpan->id)
                                      ->orWhere('child_id', $personalSpan->id);
                            })
                            ->whereHas('connectionSpan', function($query) {
                                $query->where('state', 'placeholder');
                            })
                            ->with(['connectionSpan', 'parent', 'child', 'type'])
                            ->orderBy('created_at', 'desc')
                            ->limit(5)
                            ->get();
                    }
                @endphp

                @if($placeholderConnections->isEmpty())
                    <div class="card">
                        <div class="card-header">
                            <h3 class="h6 mb-0">
                                <i class="bi bi-patch-question text-warning me-2"></i>
                                Your Placeholders
                            </h3>
                        </div>
                        <div class="card-body">
                            <p class="text-center text-muted my-5">No placeholder connections found.</p>
                        </div>
                    </div>
                @else
                    <div class="card">
                        <div class="card-header">
                            <h3 class="h6 mb-0">
                                <i class="bi bi-patch-question text-warning me-2"></i>
                                Placeholder Connections
                            </h3>
                        </div>
                        <div class="card-body">
                            <div class="spans-list">
                                @foreach($placeholderConnections as $connection)
                                    
                                        <div class="flex-grow-1">
                                            <x-connections.interactive-card :connection="$connection" />
                                        </div>
                                    
                                @endforeach
                            </div>
                            
                            @if($placeholderConnections->count() >= 5)
                                <!-- TODO: Add a link to the placeholder connections page
                                <div class="text-center mt-3">
                                    <a href="#" class="btn btn-sm btn-outline-secondary">
                                        Work on this...
                                    </a>
                                </div> -->
                            @endif
                        </div>
                    </div>
                @endif
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
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="completionModalLabel">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    Great start! 🎉
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-8">
                        <h6 class="text-success mb-3">You've completed the initial setup!</h6>
                        <p class="mb-3">Now you can start building your timeline. Here's what you can do next:</p>
                        
                        <div class="list-group list-group-flush">
                            <div class="list-group-item border-0 px-0">
                                <div class="d-flex align-items-start">
                                    <i class="bi bi-pencil-square text-primary me-3 mt-1"></i>
                                    <div>
                                        <strong>Edit your placeholders</strong>
                                        <p class="text-muted small mb-0">Click on any span to add more details, dates, or descriptions</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="list-group-item border-0 px-0">
                                <div class="d-flex align-items-start">
                                    <i class="bi bi-plus-circle text-success me-3 mt-1"></i>
                                    <div>
                                        <strong>Add more connections</strong>
                                        <p class="text-muted small mb-0">Create new spans and connect them to build your story</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="list-group-item border-0 px-0">
                                <div class="d-flex align-items-start">
                                    <i class="bi bi-timeline text-info me-3 mt-1"></i>
                                    <div>
                                        <strong>Explore your timeline</strong>
                                        <p class="text-muted small mb-0">View your life events in chronological order</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card bg-light">
                            <div class="card-body text-center">
                                <i class="bi bi-lightbulb text-warning" style="font-size: 2rem;"></i>
                                <h6 class="mt-2">Pro Tip</h6>
                                <p class="text-muted small mb-0">Start with recent events and work backwards. It's often easier to remember recent details!</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Got it!</button>
            </div>
        </div>
    </div>
</div>

@endsection 