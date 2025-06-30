@extends('layouts.app')

@section('page_title')
    @guest
        Welcome to Lifespan
    @else
        {{ \Carbon\Carbon::now()->format('j F Y') }}
    @endguest
@endsection

@section('page_filters')
    @auth
        <div class="d-flex align-items-center gap-3">
            <div class="home-search-container position-relative" style="width: 400px;">
                <div class="d-flex align-items-center position-relative">
                    <i class="bi bi-search position-absolute ms-2 text-muted z-index-1"></i>
                    <input type="text" id="home-search" class="form-control form-control-sm ps-4" placeholder="Search spans..." autocomplete="off">
                </div>
            </div>
        </div>
    @endauth
@endsection

@section('scripts')
@guest
<!-- Add Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
    .container-fluid.px-0 {
        overflow-x: hidden; /* Prevent horizontal scrollbar */
        background: linear-gradient(to bottom, #f8f9fa, #ffffff);
        position: relative;
        z-index: 0; /* Base layer */
    }
    
    /* Shared carousel styles */
    .card-carousel-container {
        position: relative;
        overflow: hidden;
        width: 100%;
        margin: 0; /* Remove vertical margins */
        isolation: isolate;
        z-index: 100;
    }
    
    .main-carousel-container {
        min-height: 360px;
        display: flex;
        align-items: center;
        padding: 40px 0;
        position: relative;
    }
    
    .secondary-carousel-container {
        min-height: 180px;
        display: flex;
        align-items: center;
        padding: 40px 0;
        position: relative;
    }
    
    .card-carousel {
        position: relative;
        width: max-content;
        display: flex;
        gap: 24px;
        padding: 0 calc(15% + 40px); /* Increased padding to match gradient width plus extra space */
        animation: scroll 60s linear infinite;
    }
    
    .secondary-carousel-container .card-carousel {
        gap: 16px;
        animation: scroll 45s linear infinite;
    }
    
    /* Pause animation on hover */
    .card-carousel:hover {
        animation-play-state: paused;
    }
    
    /* Double the content for seamless loop */
    .card-carousel::after {
        content: '';
        display: block;
        position: absolute;
        top: 0;
        left: 100%;
        width: 100%;
        height: 100%;
    }
    
    @keyframes scroll {
        0% {
            transform: translateX(0);
        }
        100% {
            transform: translateX(calc(-50% - 12px)); /* Half width plus half gap */
        }
    }
    
    /* Card dimensions and positioning */
    .carousel-card {
        position: relative;
        flex: 0 0 auto;
        width: 400px;
        height: 300px;
        transition: transform 0.3s ease;
        cursor: pointer;
        z-index: 101;
    }
    
    .small-carousel-card {
        position: relative;
        flex: 0 0 auto;
        width: 200px;
        height: 150px;
        transition: transform 0.3s ease;
        cursor: pointer;
        z-index: 101;
    }
    
    /* Enhanced hover effect */
    .carousel-card:hover,
    .small-carousel-card:hover {
        transform: translateY(-10px);
    }
    
    /* Edge gradients */
    .card-carousel-container::before, 
    .card-carousel-container::after {
        content: '';
        position: absolute;
        top: 0;
        bottom: 0;
        width: 25%; /* Wider gradient area */
        z-index: 30;
        pointer-events: none;
    }
    
    .card-carousel-container::before {
        left: 0;
        background: linear-gradient(to right, 
            rgba(255,255,255,1) 0%,
            rgba(255,255,255,1) 40%, /* Solid white extended */
            rgba(255,255,255,0) 100%
        );
    }
    
    .card-carousel-container::after {
        right: 0;
        background: linear-gradient(to left, 
            rgba(255,255,255,1) 0%,
            rgba(255,255,255,1) 40%, /* Solid white extended */
            rgba(255,255,255,0) 100%
        );
    }
    
    /* Timeline styles */
    .timeline-divider {
        position: relative;
        height: 53px; /* Increased to accommodate thicker line (40px + 13px) */
        margin: 0;
        padding: 20px 0;
        display: flex;
        align-items: center;
        justify-content: center;
        background: transparent;
        z-index: 99;
    }
    
    .timeline-line {
        position: absolute;
        width: 100%;
        height: 13px; /* Increased from 2px to 13px */
        background: linear-gradient(
            to right,
            rgba(13, 110, 253, 0) 0%,
            rgba(13, 110, 253, 0.5) 10%,
            rgba(13, 110, 253, 1) 20%,
            rgba(13, 110, 253, 1) 80%,
            rgba(13, 110, 253, 0.5) 90%,
            rgba(13, 110, 253, 0) 100%
        );
    }
    
    /* Hero section */
    .hero-section {
        position: relative;
        z-index: 1;
        padding: 60px 0;
        margin: 0; /* Remove margin */
    }
    
    /* Responsive adjustments */
    @media (max-width: 768px) {
        .carousel-card {
            width: 320px;
            height: 240px;
        }
        
        .small-carousel-card {
            width: 160px;
            height: 120px;
        }
        
        .main-carousel-container {
            min-height: 280px;
            padding: 30px 0;
        }
        
        .secondary-carousel-container {
            min-height: 140px;
            padding: 30px 0;
        }
        
        .hero-section {
            padding: 40px 0;
        }
        
        .timeline-divider {
            height: 43px; /* Adjusted for mobile (30px + 13px) */
        }
        
        .card-carousel {
            gap: 12px;
            padding: 0 calc(10% + 20px);
        }
        
        .secondary-carousel-container .card-carousel {
            gap: 12px;
        }
        
        @keyframes scroll {
            0% {
                transform: translateX(0);
            }
            100% {
                transform: translateX(calc(-100% - 20px));
            }
        }
    }
    
    /* Ensure smooth animations */
    .card-carousel {
        -webkit-font-smoothing: antialiased;
        backface-visibility: hidden;
        transform: translateZ(0);
        will-change: transform;
    }

    /* Card content styling */
    .card-body {
        text-align: center;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        height: 100%;
    }

    .card-content-top {
        flex-grow: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
    }

    .card-content-bottom {
        margin-top: auto;
    }

    .card-title {
        margin-bottom: 0.75rem;
    }

    .card-text {
        margin-bottom: 0.5rem;
    }

    /* Ensure icons are centered */
    .card-content-top .bi {
        margin: 0 auto;
        display: block;
    }

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

<!-- Duplicate the cards in the carousels for seamless looping -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        function setupCarousel(container) {
            const carousel = container.querySelector('.card-carousel');
            if (!carousel) return;

            // Get original cards
            const cards = Array.from(carousel.children);
            
            // Clone entire set of cards
            cards.forEach(card => {
                const clone = card.cloneNode(true);
                // Ensure unique IDs if any
                clone.removeAttribute('id');
                carousel.appendChild(clone);
            });

            // Optional: Clone again if needed for smoother transition
            cards.forEach(card => {
                const clone = card.cloneNode(true);
                clone.removeAttribute('id');
                carousel.appendChild(clone);
            });
        }

        // Setup both carousels
        setupCarousel(document.querySelector('.main-carousel-container'));
        setupCarousel(document.querySelector('.secondary-carousel-container'));
    });
</script>
@endguest
@endsection

@section('content')
@guest
<div class="container-fluid px-0">
    <!-- Hero / Jumbotron Section -->
    <div class="row mx-0 hero-section">
        <div class="col-12 text-center">
            <h1 class="display-3 mb-0">Lifespan</h1>
            <p class="lead">It's about time</p>
        </div>
    </div>

    <!-- Main carousel container -->
    <div class="main-carousel-container">
        <div class="card-carousel-container">
            <div class="card-carousel">
                <!-- Card 1 -->
                <div class="carousel-card">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body">
                            <div class="card-content-top">
                                <div class="mb-3">
                                    <i class="bi bi-archive" style="font-size: 2.5rem;"></i>
                                </div>
                                <h3 class="card-title">All these moments</h3>
                                <p class="card-text">
                                    will be lost in time<br>
                                    like tears...in... rain
                                </p>
                            </div>
                            <div class="card-content-bottom">
                                <p class="card-text"><a href="/spans/roy-batty">Roy Batty</a></p>
                                <p class="card-text mb-0">8 January 2016 - 10 November 2019</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card 2 -->
                <div class="carousel-card">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body">
                            <div class="card-content-top">
                                <div class="mb-3">
                                    <i class="bi bi-globe2" style="font-size: 2.5rem;"></i>
                                </div>
                                <h3 class="card-title">Roads?</h3>
                                <p class="card-text">
                                    Where we're going<br>
                                    we don't need... roads.
                                </p>
                            </div>
                            <div class="card-content-bottom">
                                <p class="card-text"><a href="/spans/dr-emmett-brown">Dr. Emmett Brown</a></p>
                                <p class="card-text mb-0">11 August 1920 - unknown</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card 3 -->
                <div class="carousel-card">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body">
                            <div class="card-content-top">
                                <div class="mb-3">
                                    <i class="bi bi-search" style="font-size: 2.5rem;"></i>
                                </div>
                                <h3 class="card-title">All persons</h3>
                                <p class="card-text">
                                    ...living or dead,<br>
                                    are purely coincidental.
                                </p>
                            </div>
                            <div class="card-content-bottom">
                                <p class="card-text"><a href="/spans/kurt-vonnegut">Kurt Vonnegut</a></p>
                                <p class="card-text mb-0">1922 - 2007</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card 4 -->
                <div class="carousel-card">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body">
                            <div class="card-content-top">
                                <div class="mb-3">
                                    <i class="bi bi-journal-text" style="font-size: 2.5rem;"></i>
                                </div>
                                <h3 class="card-title">This is the time</h3>
                                <p class="card-text">
                                    and this is the record<br>
                                    of the time.
                                </p>
                            </div>
                            <div class="card-content-bottom">
                                <p class="card-text"><a href="/spans/laurie-anderson">Laurie Anderson</a></p>
                                <p class="card-text mb-0">1947 - present</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card 5 -->
                <div class="carousel-card">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body">
                            <div class="card-content-top">
                                <div class="mb-3">
                                    <i class="bi bi-people" style="font-size: 2.5rem;"></i>
                                </div>
                                <h3 class="card-title">Collaborate</h3>
                                <p class="card-text">
                                    Map time together.<br>
                                    Share family or group histories.
                                </p>
                            </div>
                            <div class="card-content-bottom">
                                <p class="card-text mb-0">Connect your stories with others</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card 6 -->
                <div class="carousel-card">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body">
                            <div class="card-content-top">
                                <div class="mb-3">
                                    <i class="bi bi-safe2" style="font-size: 2.5rem;"></i>
                                </div>
                                <h3 class="card-title">Preserve</h3>
                                <p class="card-text">
                                    Build a living archive.<br>
                                    Keep memories alive for future generations.
                                </p>
                            </div>
                            <div class="card-content-bottom">
                                <p class="card-text mb-0">Your history, preserved forever</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Timeline divider -->
    <div class="timeline-divider">
        <div class="timeline-line"></div>
        <div class="timeline-markers">
            <div class="timeline-marker"></div>
            <div class="timeline-marker"></div>
            <div class="timeline-marker"></div>
            <div class="timeline-marker"></div>
            <div class="timeline-marker"></div>
            <div class="timeline-marker"></div>
            <div class="timeline-marker"></div>
            <div class="timeline-marker"></div>
        </div>
    </div>

    <!-- Secondary carousel container -->
    <div class="secondary-carousel-container">
        <div class="card-carousel-container">
            <div class="card-carousel">
                <!-- Small Card A -->
                <div class="small-carousel-card">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body">
                            <div class="card-content-top">
                                <h3 class="card-title">Card A</h3>
                                <p class="card-text">Secondary carousel card</p>
                            </div>
                            <div class="card-content-bottom">
                                <p class="card-text mb-0">Example content</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Small Card B -->
                <div class="small-carousel-card">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body">
                            <div class="card-content-top">
                                <h3 class="card-title">Card B</h3>
                                <p class="card-text">Secondary carousel card</p>
                            </div>
                            <div class="card-content-bottom">
                                <p class="card-text mb-0">Example content</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Small Card C -->
                <div class="small-carousel-card">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body">
                            <div class="card-content-top">
                                <h3 class="card-title">Card C</h3>
                                <p class="card-text">Secondary carousel card</p>
                            </div>
                            <div class="card-content-bottom">
                                <p class="card-text mb-0">Example content</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Small Card D -->
                <div class="small-carousel-card">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body">
                            <div class="card-content-top">
                                <h3 class="card-title">Card D</h3>
                                <p class="card-text">Secondary carousel card</p>
                            </div>
                            <div class="card-content-bottom">
                                <p class="card-text mb-0">Example content</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Small Card E -->
                <div class="small-carousel-card">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body">
                            <div class="card-content-top">
                                <h3 class="card-title">Card E</h3>
                                <p class="card-text">Secondary carousel card</p>
                            </div>
                            <div class="card-content-bottom">
                                <p class="card-text mb-0">Example content</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Small Card F -->
                <div class="small-carousel-card">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body">
                            <div class="card-content-top">
                                <h3 class="card-title">Card F</h3>
                                <p class="card-text">Secondary carousel card</p>
                            </div>
                            <div class="card-content-bottom">
                                <p class="card-text mb-0">Example content</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Call to Action -->
    <div class="row mx-0 mt-5 pt-5 text-center">
        <div class="col-md-12">
            <p class="lead">
                <a class="btn btn-primary btn-lg ms-2" href="{{ route('login') }}" role="button">It's time to sign in</a>
            </p>
        </div>
    </div>
</div>

@else
    <div class="container-fluid">
        <div class="row">
            <!-- Left Column: Personal Info -->
            <div class="col-md-4">
                <div class="mb-4">
                    <h2 class="h5 mb-3">
                        <i class="bi bi-person text-primary me-2"></i>
                        Your Timeline
                    </h2>

                    @if(auth()->user()->personalSpan)
                        @php
                            $personalSpan = auth()->user()->personalSpan;
                            $now = \Carbon\Carbon::now();
                            $birthDate = \Carbon\Carbon::createFromDate(
                                $personalSpan->start_year,
                                $personalSpan->start_month ?? 1,
                                $personalSpan->start_day ?? 1
                            );
                            $age = \App\Helpers\DateDurationCalculator::calculateDuration(
                                (object)['year' => $birthDate->year, 'month' => $birthDate->month, 'day' => $birthDate->day],
                                (object)['year' => $now->year, 'month' => $now->month, 'day' => $now->day]
                            );
                        @endphp

                        <div class="card mb-3">
                            <div class="card-body">
                                <h3 class="h6 mb-3">
                                    <i class="bi bi-person-circle text-primary me-2"></i>
                                    Your Age
                                </h3>
                                <p class="mb-0">
                                    You are {{ $age['years'] }} years, {{ $age['months'] }} months, and {{ $age['days'] }} days old
                                </p>
                            </div>
                        </div>

                        <div class="card mb-3">
                            <div class="card-body">
                                <h3 class="h6 mb-3">
                                    <i class="bi bi-calendar-event text-primary me-2"></i>
                                    Your Timeline
                                </h3>
                                <x-spans.display.card :span="$personalSpan" />
                            </div>
                        </div>

                        @php
                            // Get parents using the parents() method
                            $parents = $personalSpan->parents()->get();
                            
                            $parentComparisons = [];
                            foreach ($parents as $parentSpan) {
                                $parentBirthDate = \Carbon\Carbon::createFromDate(
                                    $parentSpan->start_year,
                                    $parentSpan->start_month ?? 1,
                                    $parentSpan->start_day ?? 1
                                );
                                
                                // Calculate the date when parent was the user's current age
                                $parentAgeDate = $parentBirthDate->copy()->addYears($age['years'])
                                    ->addMonths($age['months'])
                                    ->addDays($age['days']);
                                
                                $parentComparisons[] = [
                                    'span' => $parentSpan,
                                    'date' => $parentAgeDate
                                ];
                            }
                        @endphp

                        @if(!empty($parentComparisons))
                            <div class="card mb-3">
                                <div class="card-body">
                                    <h3 class="h6 mb-3">
                                        <i class="bi bi-arrow-left-right text-primary me-2"></i>
                                        Age Comparison
                                    </h3>
                                    <p class="mb-0">
                                        When your parents were your current age:
                                    </p>
                                    <ul class="list-unstyled mt-2 mb-0">
                                        @foreach($parentComparisons as $comparison)
                                            <li>
                                                <x-spans.display.micro-card :span="$comparison['span']" /> was this age on 
                                                <a href="{{ route('date.explore', ['date' => $comparison['date']->format('Y-m-d')]) }}" class="text-muted text-dotted-underline">
                                                    {{ $comparison['date']->format('j F Y') }}
                                                </a>
                                            </li>
                                        @endforeach
                                    </ul>
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
                    <h2 class="h5 mb-3">
                        <i class="bi bi-calendar-check text-primary me-2"></i>
                        Today's Events
                    </h2>

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

                        // Get all person-type spans that the user can see
                        $personSpans = \App\Models\Span::where('type_id', 'person')
                            ->where(function($query) {
                                $query->where('access_level', 'public')
                                    ->orWhere('owner_id', auth()->id());
                            })
                            ->get();

                        // Calculate significant dates
                        $significantDates = [];
                        foreach ($personSpans as $span) {
                            if ($span->start_year && $span->start_month && $span->start_day) {
                                $birthDate = \Carbon\Carbon::createFromDate(
                                    $span->start_year,
                                    $span->start_month,
                                    $span->start_day
                                );
                                
                                // Check if person is deceased
                                $isDeceased = $span->end_year && $span->end_month && $span->end_day;
                                
                                if (!$isDeceased) {
                                    // Only calculate birthdays for living people
                                    $thisYearsBirthday = \Carbon\Carbon::createFromDate(
                                        $today->year,
                                        $span->start_month,
                                        $span->start_day
                                    );
                                    
                                    // Calculate next birthday
                                    $nextBirthday = $thisYearsBirthday->copy();
                                    if ($thisYearsBirthday->lt($today)) {
                                        $nextBirthday = $thisYearsBirthday->addYear();
                                    }
                                    
                                    // Calculate age at next birthday
                                    $ageAtNextBirthday = $nextBirthday->year - $span->start_year;
                                    
                                    // Calculate days until next birthday
                                    $daysUntilBirthday = $today->diffInDays($nextBirthday);
                                    
                                    // Add to significant dates if birthday is within next 30 days
                                    if ($daysUntilBirthday <= 30) {
                                        $significantDates[] = [
                                            'span' => $span,
                                            'type' => 'birthday',
                                            'date' => $nextBirthday,
                                            'age' => $ageAtNextBirthday,
                                            'days_until' => $daysUntilBirthday
                                        ];
                                    }
                                } else {
                                    // Calculate death anniversaries for deceased people
                                    $deathDate = \Carbon\Carbon::createFromDate(
                                        $span->end_year,
                                        $span->end_month,
                                        $span->end_day
                                    );
                                    
                                    $thisYearsDeathAnniversary = \Carbon\Carbon::createFromDate(
                                        $today->year,
                                        $span->end_month,
                                        $span->end_day
                                    );
                                    
                                    $nextDeathAnniversary = $thisYearsDeathAnniversary->copy();
                                    if ($thisYearsDeathAnniversary->lt($today)) {
                                        $nextDeathAnniversary = $thisYearsDeathAnniversary->addYear();
                                    }
                                    
                                    $yearsSinceDeath = $nextDeathAnniversary->year - $span->end_year;
                                    $daysUntilDeathAnniversary = $today->diffInDays($nextDeathAnniversary);
                                    
                                    // For death anniversaries, show if within next 30 days
                                    if ($daysUntilDeathAnniversary <= 30) {
                                        $significantDates[] = [
                                            'span' => $span,
                                            'type' => 'death_anniversary',
                                            'date' => $nextDeathAnniversary,
                                            'years' => $yearsSinceDeath,
                                            'days_until' => $daysUntilDeathAnniversary
                                        ];
                                    }
                                }
                            }
                        }
                        
                        // Sort significant dates by days until
                        usort($significantDates, function($a, $b) {
                            return $a['days_until'] <=> $b['days_until'];
                        });
                        
                        // Debug output for all significant dates
                        \Log::info('Significant dates:', array_map(function($date) {
                            return [
                                'name' => $date['span']->name,
                                'type' => $date['type'],
                                'days_until' => $date['days_until']
                            ];
                        }, $significantDates));
                    @endphp

                    @if($spansStartingOnDate->isEmpty() && $spansEndingOnDate->isEmpty() && empty($significantDates))
                        <div class="card">
                            <div class="card-body">
                                <p class="text-center text-muted my-5">No events found for today.</p>
                            </div>
                        </div>
                    @else
                        @if($spansStartingOnDate->isNotEmpty())
                            <div class="mb-4">
                                <h3 class="h6 mb-2">
                                    <i class="bi bi-play-circle text-success me-2"></i>
                                    Started Today
                                </h3>
                                <div class="spans-list">
                                    @foreach($spansStartingOnDate as $span)
                                        <x-spans.display.interactive-card :span="$span" />
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @if($spansEndingOnDate->isNotEmpty())
                            <div class="mb-4">
                                <h3 class="h6 mb-2">
                                    <i class="bi bi-stop-circle text-danger me-2"></i>
                                    Ended Today
                                </h3>
                                <div class="spans-list">
                                    @foreach($spansEndingOnDate as $span)
                                        <x-spans.display.interactive-card :span="$span" />
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @if(!empty($significantDates))
                            <div class="mb-4">
                                <h3 class="h6 mb-2">
                                    <i class="bi bi-calendar-heart text-primary me-2"></i>
                                    Upcoming Anniversaries
                                </h3>
                                <div class="spans-list">
                                    @foreach($significantDates as $event)
                                        <div class="card mb-3">
                                            <div class="card-body">
                                                <div class="d-flex align-items-center">
                                                    <div class="flex-grow-1">
                                                        @if($event['type'] === 'birthday')
                                                            @if($event['days_until'] === 0)
                                                                <p class="mb-0">
                                                                    <x-spans.display.micro-card :span="$event['span']" /> turns {{ $event['age'] }} today
                                                                </p>
                                                            @else
                                                                <p class="mb-0">
                                                                    <x-spans.display.micro-card :span="$event['span']" /> will be {{ $event['age'] }} in {{ $event['days_until'] }} days
                                                                </p>
                                                            @endif
                                                        @else
                                                            @if($event['days_until'] === 0)
                                                                <p class="mb-0">
                                                                    {{ $event['years'] }} years since <x-spans.display.micro-card :span="$event['span']" />'s death
                                                                </p>
                                                            @else
                                                                <p class="mb-0">
                                                                    {{ $event['years'] }} years since <x-spans.display.micro-card :span="$event['span']" />'s death in {{ $event['days_until'] }} days
                                                                </p>
                                                            @endif
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    @endif
                </div>
            </div>

            <!-- Right Column: Placeholder Connections -->
            <div class="col-md-4">
                <div class="mb-4">
                    <h2 class="h5 mb-3">
                        <i class="bi bi-patch-question text-warning me-2"></i>
                        Placeholder Connections
                    </h2>

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
                            <div class="card-body">
                                <p class="text-center text-muted my-5">No placeholder connections found.</p>
                            </div>
                        </div>
                    @else
                        <div class="card">
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
@endguest
@endsection 