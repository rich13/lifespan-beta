@extends('layouts.app')

@section('page_title')
    Welcome to Lifespan
@endsection

@section('scripts')
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
        transform: scale(0.97);
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
@endsection

@section('content')
<div class="container-fluid px-0">
    <!-- Hero / Jumbotron Section -->
    <div class="row mx-0 hero-section">
        <div class="col-12 text-center">
            <h1 class="display-3 mb-0">Lifespan</h1>
            <p class="lead">It's time</p>
        </div>
    </div>

    <!-- Main carousel container -->
    <div class="main-carousel-container">
        <div class="card-carousel-container">
            <div class="card-carousel">

            <div class="carousel-card">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body">
                            <div class="card-content-top">
                                <div class="mb-3">
                                    <i class="bi bi-cloud-rain" style="font-size: 2.5rem;"></i>
                                </div>
                                <h3 class="card-title">All these moments</h3>
                                <p class="card-text">
                                    will be lost in time<br>
                                    like tears...in... rain
                                </p>
                            </div>
                            <div class="card-content-bottom">
                            <p class="card-text">Roy Batty</p>
                            <!-- <p class="card-text"><a href="/spans/roy-batty">Roy Batty</a></p> -->
                                <p class="card-text mb-0">2016 - 2019</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="carousel-card">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body">
                            <div class="card-content-top">
                                <div class="mb-3">
                                    <i class="bi bi-calendar-heart" style="font-size: 2.5rem;"></i>
                                </div>
                                <h3 class="card-title">Look through time</h3>
                                <p class="card-text">
                                and find your life,<br>
                                tell us what you find
                                </p>
                            </div>
                            <div class="card-content-bottom">
                                <p class="card-text"><a href="/spans/nick-drake">Nick Drake</a></p>
                                <!--<p class="card-text">Nick Drake</p> -->
                                <p class="card-text mb-0">1948 - 1974</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="carousel-card">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body">
                            <div class="card-content-top">
                                <div class="mb-3">
                                    <i class="bi bi-clock-history" style="font-size: 2.5rem;"></i>
                                </div>
                                <h3 class="card-title">Time is an illusion.</h3>
                                <p class="card-text">
                                Lunchtime doubly so.
                                </p>
                            </div>
                            <div class="card-content-bottom">
                                <p class="card-text"><a href="/spans/douglas-adams">Douglas Adams</a></p>
                                <!--<p class="card-text">Nick Drake</p> -->
                                <p class="card-text mb-0">1952 - 2001</p>
                            </div>
                        </div>
                    </div>
                </div>


                <!-- <div class="carousel-card">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body">
                            <div class="card-content-top">
                                <div class="mb-3">
                                    <i class="bi bi-car-front" style="font-size: 2.5rem;"></i>
                                </div>
                                <h3 class="card-title">Roads?</h3>
                                <p class="card-text">
                                    Where we're going<br>
                                    we don't need... roads.
                                </p>
                            </div>
                            <div class="card-content-bottom">
                                <p class="card-text"><a href="/spans/dr-emmett-brown">Dr. Emmett Brown</a></p>
                                <p class="card-text">Dr. Emmett Brown</p>
                                <p class="card-text mb-0">1920 - unknown</p>
                            </div>
                        </div>
                    </div>
                </div> -->

                <div class="carousel-card">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body">
                            <div class="card-content-top">
                                <div class="mb-3">
                                    <i class="bi bi-infinity" style="font-size: 2.5rem;"></i>
                                </div>
                                <h3 class="card-title">All moments</h3>
                                <p class="card-text">
                                past, present and future,<br>
                                always have existed,<br>
                                always will exist.
                                </p>
                            </div>
                            <div class="card-content-bottom">
                                <p class="card-text"><a href="/spans/kurt-vonnegut">Kurt Vonnegut</a></p>
                                <!-- <p class="card-text">Kurt Vonnegut</p> -->
                                <p class="card-text mb-0">1922 - 2007</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="carousel-card">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body">
                            <div class="card-content-top">
                                <div class="mb-3">
                                    <i class="bi bi-airplane" style="font-size: 2.5rem;"></i>
                                </div>
                                <h3 class="card-title">This is the time</h3>
                                <p class="card-text">
                                    and this is the record<br>
                                    of the time.
                                </p>
                            </div>
                            <div class="card-content-bottom">
                                <p class="card-text"><a href="/spans/laurie-anderson">Laurie Anderson</a></p>
                                <!-- <p class="card-text">Laurie Anderson</p> -->
                                <p class="card-text mb-0">1947 - present</p>
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
                                <h3 class="card-title">Something</h3>
                                <p class="card-text">Something</p>
                            </div>
                            <div class="card-content-bottom">
                                <p class="card-text mb-0">Something</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Small Card B -->
                <div class="small-carousel-card">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body">
                            <div class="card-content-top">
                                <h3 class="card-title">Something</h3>
                                <p class="card-text">Something</p>
                            </div>
                            <div class="card-content-bottom">
                                <p class="card-text mb-0">Something</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Small Card C -->
                <div class="small-carousel-card">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body">
                            <div class="card-content-top">
                                <h3 class="card-title">Something</h3>
                                <p class="card-text">Something</p>
                            </div>
                            <div class="card-content-bottom">
                                <p class="card-text mb-0">Something</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Small Card D -->
                <div class="small-carousel-card">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body">
                            <div class="card-content-top">
                                <h3 class="card-title">Something</h3>
                                <p class="card-text">Something</p>
                            </div>
                            <div class="card-content-bottom">
                                <p class="card-text mb-0">Something</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Small Card E -->
                <div class="small-carousel-card">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body">
                            <div class="card-content-top">
                                <h3 class="card-title">Something</h3>
                                <p class="card-text">Something</p>
                            </div>
                            <div class="card-content-bottom">
                                <p class="card-text mb-0">Something</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Small Card F -->
                <div class="small-carousel-card">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body">
                            <div class="card-content-top">
                                <h3 class="card-title">Something</h3>
                                <p class="card-text">Something</p>
                            </div>
                            <div class="card-content-bottom">
                                <p class="card-text mb-0">Something</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection 