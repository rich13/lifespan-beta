@extends('layouts.app')

@section('page_title')
    Today is {{ \Carbon\Carbon::now()->format('F j, Y') }}
@endsection

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
@endsection 