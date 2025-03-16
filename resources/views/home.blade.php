@extends('layouts.app')

@section('page_title')
    @guest
        Welcome to Lifespan
    @else
        Home
    @endguest
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
            gap: 20px;
            padding: 0 calc(25% + 24px); /* Adjusted for mobile */
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
            <p class="lead">Map your journey through time</p>
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
                <a class="btn btn-primary btn-lg ms-2" href="{{ route('login') }}" role="button">It's time</a>
            </p>
        </div>
    </div>
</div>

@else
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <p>This is the magic page.</p>
                <!-- Timeline content will go here -->
            </div>
        </div>
    </div>
@endguest
@endsection 