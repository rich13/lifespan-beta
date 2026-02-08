@php
use Illuminate\Support\Facades\Route;

// Get sidebar state from cookie or default to expanded
$sidebarCollapsed = request()->cookie('sidebarCollapsed') === 'true';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Favicon and App Icons -->
        <link rel="icon" href="{{ asset('img/favicon.ico') }}" type="image/x-icon">
        <link rel="icon" href="{{ asset('img/favicon-16x16.png') }}" type="image/png" sizes="16x16">
        <link rel="icon" href="{{ asset('img/favicon-32x32.png') }}" type="image/png" sizes="32x32">
        <link rel="apple-touch-icon" href="{{ asset('img/apple-touch-icon.png') }}">
        <link rel="manifest" href="{{ asset('img/site.webmanifest') }}">

        <!-- jQuery -->
        <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

        <!-- Bootstrap -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

        <!-- Bootstrap Icons -->
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
        <!-- Fallback for Bootstrap Icons if local fonts fail -->
        <style>
            @font-face {
                font-display: block;
                font-family: "bootstrap-icons";
                src: url("https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/fonts/bootstrap-icons.woff2") format("woff2"),
                     url("https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/fonts/bootstrap-icons.woff") format("woff");
            }
        </style>
        
        <!-- Select2 -->
        <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
        <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
        <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

        <!-- Scripts and Styles (no React in this app, so @viteReactRefresh removed to avoid 404) -->
        @vite(['resources/scss/app.scss', 'resources/js/app.js', 'resources/js/routes.js'])
        
        <!-- Session Bridge: Enabled for seamless redeploy recovery -->
        
        <!-- Page-specific styles -->
        @stack('styles')
        
        <!-- Custom styles -->
        <style>
            /* Only add minimal custom styling, prefer Bootstrap classes */
            /* Sidebar nav links */
            .sidebar .nav-link {
                color: rgba(255, 255, 255, 0.8) !important;
            }
            .sidebar .nav-link:hover, .sidebar .nav-link.active {
                color: #fff !important;
                background-color: rgba(255, 255, 255, 0.1);
            }
            
            /* Bootstrap tabs - ensure proper contrast */
            .nav-tabs .nav-link {
                color: #495057 !important;
                background-color: transparent !important;
                border: 1px solid transparent !important;
            }
            .nav-tabs .nav-link:hover {
                color: #495057 !important;
                background-color: #e9ecef !important;
                border-color: #e9ecef #e9ecef #dee2e6 !important;
            }
            .nav-tabs .nav-link.active {
                color: #495057 !important;
                background-color: #fff !important;
                border-color: #dee2e6 #dee2e6 #fff !important;
            }
            
            /* Collapsible sidebar styles */
            :root {
                --sidebar-width-lg: 280px;
                --sidebar-width-md: 240px;
                --sidebar-width-collapsed: 60px;
                --sidebar-transition: 0.3s ease-in-out;
            }
            
            /* Prevent animations on initial load */
            .sidebar {
                transition: none !important;
                overflow: hidden;
                width: var(--sidebar-width-md);
                margin: 0;
                padding: 0;
                flex-shrink: 0;
                position: fixed;
                top: 0;
                left: 0;
                height: 100vh;
                z-index: 1000;
            }
            
            /* Responsive widths */
            @media (min-width: 992px) {
                .sidebar {
                    width: var(--sidebar-width-lg);
                }
            }
            
            /* Enable animations after page load */
            .sidebar.animated {
                transition: width var(--sidebar-transition) !important;
            }
            
            .sidebar.collapsed {
                width: var(--sidebar-width-collapsed) !important;
            }
            
            .sidebar.collapsed .nav-link {
                text-align: center;
                padding: 0.75rem 0.5rem;
            }
            
            .sidebar.collapsed .nav-link span {
                display: none;
            }
            
            .sidebar.collapsed .sidebar-heading {
                display: none;
            }
            
            .sidebar.collapsed .sidebar-footer {
                display: block;
            }
            
            
            
            .sidebar.collapsed .sidebar-brand span {
                display: none;
            }
            
            /* Hide text during animation */
            .sidebar.animating .nav-link span,
            .sidebar.animating .sidebar-brand span {
                display: none;
            }
            
            .sidebar.collapsed .nav-link i {
                margin-right: 0 !important;
                font-size: 1.1em;
            }
            
            .main-content {
                transition: none !important;
                flex: 1;
                margin-left: var(--sidebar-width-md);
                padding: 0;
                min-width: 0;
                display: flex;
                flex-direction: column;
                min-height: 100vh;
            }
            
            /* Mobile: sidebar is hidden (offcanvas), so no left margin */
            @media (max-width: 767.98px) {
                .main-content,
                .main-content.collapsed {
                    margin-left: 0 !important;
                }
                .sidebar-toggle-btn {
                    display: none !important;
                }
            }
            
            @media (min-width: 992px) {
                .main-content {
                    margin-left: var(--sidebar-width-lg);
                }
                
                .main-content.collapsed {
                    margin-left: var(--sidebar-width-collapsed);
                }
            }
            
            .main-content.collapsed {
                margin-left: var(--sidebar-width-collapsed);
            }
            
            /* Make page content area grow to push footer to bottom */
            .main-content > div.py-3.px-3 {
                flex: 1;
            }
            
            .main-content.animated {
                transition: margin-left var(--sidebar-transition) !important;
            }
            
            .sidebar-toggle {
                transition: none !important;
            }
            
            .sidebar-toggle.animated {
                transition: transform var(--sidebar-transition) !important;
            }
            
            .sidebar-toggle.collapsed {
                transform: rotate(180deg);
            }
            
            /* Subtle border toggle button */
            .sidebar-toggle-btn {
                position: fixed;
                bottom: 0;
                left: var(--sidebar-width-md);
                width: 20px;
                height: 56px;
                background: transparent;
                border: none;
                display: flex;
                align-items: center;
                justify-content: center;
                cursor: pointer;
                z-index: 1000;
                transition: none !important;
                color: #6c757d;
            }
            
            .sidebar-toggle-btn:hover {
                color: #495057;
            }
            
            .sidebar-toggle-btn.animated {
                transition: left var(--sidebar-transition) !important;
            }
            
            .sidebar-toggle-btn.collapsed {
                left: var(--sidebar-width-collapsed);
                transform: rotate(180deg);
            }
            
            /* Responsive positioning for toggle button */
            @media (min-width: 992px) {
                .sidebar-toggle-btn {
                    left: var(--sidebar-width-lg);
                }
                
                .sidebar-toggle-btn.collapsed {
                    left: var(--sidebar-width-collapsed);
                }
            }
            
            /* Tooltip positioning for collapsed sidebar */
            .sidebar.collapsed .nav-link[data-bs-toggle="tooltip"] {
                position: relative;
            }
            
            /* Mobile Navigation Styles */
            .offcanvas-body .sidebar-brand {
                display: none; /* Hide brand in offcanvas since it's in the header */
            }
            
            .offcanvas-body .nav-link {
                color: rgba(255, 255, 255, 0.8) !important;
                padding: 0.75rem 1rem;
                border-radius: 0;
            }
            
            .offcanvas-body .nav-link:hover,
            .offcanvas-body .nav-link.active {
                color: #fff !important;
                background-color: rgba(255, 255, 255, 0.1);
            }
            
            .offcanvas-body .sidebar-divider {
                border-color: rgba(255, 255, 255, 0.2);
                margin: 0.5rem 1rem;
            }
            
            .offcanvas-body .sidebar-footer {
                border-color: rgba(255, 255, 255, 0.2) !important;
                margin-top: auto;
            }
            
            .offcanvas-body .sidebar-footer a {
                color: rgba(255, 255, 255, 0.8) !important;
            }
            
            .offcanvas-body .sidebar-footer a:hover {
                color: #fff !important;
            }
            
            /* Remove any gaps from Bootstrap row/col system */
            .row {
                margin: 0;
            }
            
            .row > * {
                padding: 0;
            }
            
            /* Ensure sidebar and main content are side by side */
            .sidebar-row {
                display: flex;
                flex-direction: row;
            }
            
            /* Preserve Bootstrap spacing for main content area */
            .main-content .row {
                margin-left: -0.75rem;
                margin-right: -0.75rem;
            }
            
            .main-content .row > * {
                padding-left: 0.75rem;
                padding-right: 0.75rem;
            }
            
            /* Preserve Bootstrap spacing for card layouts */
            .spans-list .row,
            .card-grid .row {
                margin-left: -0.75rem;
                margin-right: -0.75rem;
            }
            
            .spans-list .row > *,
            .card-grid .row > * {
                padding-left: 0.75rem;
                padding-right: 0.75rem;
            }
            
            /* Guest content wrapper - add margins to cards */
            .guest-content-wrapper .card {
                margin-left: 0.5rem;
                margin-right: 0.5rem;
            }
            
            /* Preserve Bootstrap row/col spacing for guest layout (matches .main-content) */
            .guest-content-wrapper .row {
                margin-left: -0.75rem;
                margin-right: -0.75rem;
            }
            .guest-content-wrapper .row > * {
                padding-left: 0.75rem;
                padding-right: 0.75rem;
            }
            
            /* Ensure proper spacing for guest layout */
            .guest-content-wrapper .container-fluid {
                padding-left: 0;
                padding-right: 0;
            }
            
            /* Homepage mode switcher in top nav */
            .homepage-mode-switcher .btn {
                border-color: #6c757d;
                color: #495057;
                background-color: transparent;
            }
            
            .homepage-mode-switcher .btn:hover {
                background-color: #e9ecef;
                color: #212529;
            }
            
            .homepage-mode-switcher .btn.active {
                background-color: #000;
                border-color: #000;
                color: #fff;
            }
        </style>
        
        <!-- Page-specific scripts -->
        @yield('scripts')
        @stack('scripts')
        
        <!-- Global initialization for place residence cards (works on all pages) -->
        <script>
        (function() {
            // Global function to initialize toggle buttons for place card (works in both contexts)
            if (typeof window.initPlaceCardToggle === 'undefined') {
                window.initPlaceCardToggle = function(cardElement) {
                    // Find the toggle buttons and views within the card
                    const livedToggle = cardElement.querySelector('input[id^="lived-toggle-"]');
                    const locatedToggle = cardElement.querySelector('input[id^="located-toggle-"]');
                    const livedView = cardElement.querySelector('div[id^="lived-view-"]');
                    const locatedView = cardElement.querySelector('div[id^="located-view-"]');
                    
                    if (livedToggle && locatedToggle && livedView && locatedView) {
                        // Check if already initialized
                        if (livedToggle.dataset.initialized === 'true') {
                            return true; // Already initialized
                        }
                        
                        // Set up event listeners directly
                        livedToggle.addEventListener('change', function() {
                            if (this.checked) {
                                livedView.style.display = 'block';
                                locatedView.style.display = 'none';
                            }
                        });
                        
                        locatedToggle.addEventListener('change', function() {
                            if (this.checked) {
                                livedView.style.display = 'none';
                                locatedView.style.display = 'block';
                            }
                        });
                        
                        // Mark as initialized
                        livedToggle.dataset.initialized = 'true';
                        locatedToggle.dataset.initialized = 'true';
                        
                        return true;
                    }
                    return false;
                };
            }
            
            // Initialize any existing cards on page load (for server-rendered content)
            function initializeAllPlaceCards() {
                document.querySelectorAll('.place-residence-card').forEach(function(card) {
                    // Check if already initialized by looking for data attribute
                    if (!card.dataset.toggleInitialized) {
                        if (window.initPlaceCardToggle(card)) {
                            card.dataset.toggleInitialized = 'true';
                        }
                    }
                });
            }
            
            // Run on DOMContentLoaded
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initializeAllPlaceCards);
            } else {
                // DOM already loaded
                initializeAllPlaceCards();
            }
            
            // Also run after a short delay to catch any late-loading content
            setTimeout(initializeAllPlaceCards, 500);
        })();
        </script>
    </head>
    <body class="bg-light">
        <div class="row">
            @auth
                @if(request()->routeIs('profile.complete') || request()->routeIs('profile.complete.store'))
                    <!-- Profile completion uses guest-style layout (no nav) -->
                    <x-topnav.guest-topnav />
                    
                    <!-- Guest Content Area -->
                    <div class="row">
                        <div class="col-12 bg-light py-3 px-3">
                        <div class="header-section mb-4">
                            @yield('header')
                            @unless(request()->routeIs('password.request') || request()->routeIs('password.reset') || request()->routeIs('password.store') || request()->routeIs('auth.password') || request()->routeIs('auth.password.submit') || request()->routeIs('login') || request()->routeIs('register') || request()->routeIs('register.pending') || request()->routeIs('verification.notice') || request()->routeIs('verification.send'))
                                <x-flash-messages />
                            @endunless
                        </div>
                        <div class="guest-content-wrapper">
                            @yield('content')
                        </div>
                        </div>
                    </div>
                @else
                    <!-- Sidebar and Main Content -->
                    <div class="row sidebar-row">
                    <!-- Sidebar -->
                    <div id="sidebar" class="bg-dark border-end d-none d-md-block p-0 sidebar{{ $sidebarCollapsed ? ' collapsed' : '' }}">
                        <div class="sticky-top" style="top: 0; height: 100vh; overflow-y: auto;">
                            <x-sidebar.main-nav />
                            <x-sidebar.admin-nav />
                            <x-sidebar.footer />
                        </div>
                    </div>

                    <!-- Main Content Area with Top Navigation -->
                    <div id="main-content" class="bg-light main-content">
                        <!-- Top Navigation Bar -->
                        <x-topnav-container>
                            <!-- Mobile Menu Button -->
                            <button class="btn btn-link text-dark d-md-none me-2 p-0" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileNav" aria-controls="mobileNav">
                                <i class="bi bi-list fs-4"></i>
                            </button>

                            <x-topnav.page-title />
                            
                            @if(request()->routeIs('home') || request()->routeIs('me') || request()->routeIs('activity'))
                                <x-home.mode-switcher class="mx-3 d-none d-md-flex" />
                            @endif
                            
                            <!-- Page Filters -->
                            <x-topnav.page-filters class="d-none d-md-flex" />
                            <!-- Top Navigation Actions (Search, New, Improve) -->
                            <x-topnav.topnav-actions :span="$span ?? null" class="d-none d-md-flex" />
                            <!-- Page Tools (Edit, Delete, History) -->
                            <x-topnav.page-tools class="d-none d-md-flex" :group="!request()->routeIs('photos.index') && !request()->routeIs('photos.of') && !request()->routeIs('photos.of.in') && !request()->routeIs('photos.of.from') && !request()->routeIs('photos.of.from.to') && !request()->routeIs('photos.from') && !request()->routeIs('photos.from.to') && !request()->routeIs('photos.during') && !request()->routeIs('photos.in')" />
                            <!-- User Profile -->
                            <x-topnav.user-profile :span="$span ?? null" class="d-none d-md-flex" />
                            
                            <!-- Mobile Right Nav Button -->
                            <button class="btn btn-link text-dark d-md-none ms-auto p-0" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileRightNav" aria-controls="mobileRightNav">
                                <i class="bi bi-three-dots-vertical fs-4"></i>
                            </button>
                        </x-topnav-container>
                        
                        <!-- Page Content -->
                        <div class="py-3 px-3">
                        <div class="header-section mb-4">
                            @yield('header')
                            @unless(request()->routeIs('password.request') || request()->routeIs('password.reset') || request()->routeIs('password.store') || request()->routeIs('auth.password') || request()->routeIs('auth.password.submit') || request()->routeIs('login') || request()->routeIs('register') || request()->routeIs('register.pending') || request()->routeIs('verification.notice') || request()->routeIs('verification.send'))
                                <x-flash-messages />
                            @endunless
                        </div>
                        @yield('content')
                        </div>
                    </div>
                </div>
                
                <!-- Subtle Sidebar Toggle Button -->
                <button id="sidebar-toggle" class="sidebar-toggle-btn{{ $sidebarCollapsed ? ' collapsed' : '' }}" type="button" title="Toggle Sidebar">
                    <i class="bi bi-chevron-left"></i>
                </button>
                @endif
            @else
                <!-- Guest Top Navigation Bar -->
                <x-topnav.guest-topnav />
                
                <!-- Guest Content Area -->
                <div class="row">
                    <div class="col-12 bg-light py-3 px-3">
                        <div class="header-section mb-4">
                            @yield('header')
                            @unless(request()->routeIs('password.request') || request()->routeIs('password.reset') || request()->routeIs('password.store') || request()->routeIs('auth.password') || request()->routeIs('auth.password.submit') || request()->routeIs('login') || request()->routeIs('register') || request()->routeIs('register.pending') || request()->routeIs('verification.notice') || request()->routeIs('verification.send'))
                                <x-flash-messages />
                            @endunless
                        </div>
                        <div class="guest-content-wrapper">
                            @yield('content')
                        </div>
                    </div>
                </div>
            @endauth
        </div>
        
        @auth
        @if(!request()->routeIs('profile.complete') && !request()->routeIs('profile.complete.store'))
        <!-- Mobile Navigation Offcanvas -->
        <div class="offcanvas offcanvas-start" tabindex="-1" id="mobileNav" aria-labelledby="mobileNavLabel">
            <div class="offcanvas-header bg-dark text-white">
                <h5 class="offcanvas-title" id="mobileNavLabel">
                    <x-brand variant="white" size="h5" />
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
            </div>
            <div class="offcanvas-body bg-dark p-0">
                <x-sidebar.main-nav />
                <x-sidebar.admin-nav />
                <x-sidebar.footer />
            </div>
        </div>
        
        <!-- Mobile Right Navigation Offcanvas -->
        <x-mobile-right-nav :span="$span ?? null" />
        @endif
        @endauth
        
        <!-- Modals -->
        @stack('modals')
        
        <!-- About Lifespan Modal -->
        <x-modals.about-lifespan-modal />
        
        <!-- Footer Modal (generic for About, Privacy, Terms, Contact) -->
        <x-footer.footer-modal />
        
        <!-- Global Access Level Modal -->
        <x-modals.access-level-modal />
        
        <!-- Group Permissions Modal -->
        <x-modals.group-permissions-modal />
        
        <!-- Create Note Modal -->
        <x-modals.create-note-modal />
        
        <!-- Connect Note Modal -->
        <x-modals.connect-note-modal />
        
        <!-- Global Sets Modal -->
        <x-modals.sets-modal />
        
        <!-- New Span Modal -->
        <x-modals.new-span-modal />
        
        <!-- Add Connection Modal -->
        <x-modals.add-connection-modal />
        
        <!-- Time Travel Modal -->
        <x-modals.time-travel-modal />
        
        <!-- Sidebar Toggle Script -->
        @auth
        <script>
            $(document).ready(function() {
                // Store bridge token from session if available
                const bridgeToken = '{{ session()->get('bridge_token', '') }}';
                if (bridgeToken) {
                    SessionBridge.storeBridgeTokenFromServer(bridgeToken);
                }

                // Get stored sidebar state from cookie
                const sidebarCollapsed = document.cookie.split('; ').find(row => row.startsWith('sidebarCollapsed='))?.split('=')[1] === 'true';
                
                // Set initial state based on cookie (no animation)
                if (sidebarCollapsed) {
                    $('#sidebar').addClass('collapsed');
                    $('#sidebar-toggle').addClass('collapsed');
                    $('#main-content').addClass('collapsed');
                }
                
                // Enable animations after DOM is ready
                setTimeout(function() {
                    $('#sidebar').addClass('animated');
                    $('#main-content').addClass('animated');
                    $('#sidebar-toggle').addClass('animated');
                }, 50);
                
                // Sidebar toggle functionality
                $('#sidebar-toggle').on('click', function() {
                    const sidebar = $('#sidebar');
                    const toggle = $(this);
                    
                    // Add animating class to hide text during transition
                    sidebar.addClass('animating');
                    
                    sidebar.toggleClass('collapsed');
                    toggle.toggleClass('collapsed');
                    $('#main-content').toggleClass('collapsed');
                    
                    // Remove animating class after transition completes
                    setTimeout(function() {
                        sidebar.removeClass('animating');
                    }, 300); // Match the transition duration
                    
                    // Store state in cookie (expires in 1 year)
                    const isCollapsed = sidebar.hasClass('collapsed');
                    document.cookie = `sidebarCollapsed=${isCollapsed}; path=/; max-age=${365 * 24 * 60 * 60}`;
                    
                    // Update tooltips
                    if (sidebar.hasClass('collapsed')) {
                        // Enable tooltips for collapsed state
                        $('.sidebar .nav-link, .sidebar .sidebar-brand').each(function() {
                            const $link = $(this);
                            const text = $link.text().trim();
                            if (text) {
                                $link.attr('data-bs-toggle', 'tooltip');
                                $link.attr('data-bs-placement', 'right');
                                $link.attr('title', text);
                            }
                        });
                        $('[data-bs-toggle="tooltip"]').tooltip();
                    } else {
                        // Disable tooltips for expanded state
                        $('.sidebar .nav-link, .sidebar .sidebar-brand').tooltip('dispose');
                        $('.sidebar .nav-link, .sidebar .sidebar-brand').removeAttr('data-bs-toggle data-bs-placement title');
                    }
                });
                
                // Initialize tooltips if sidebar starts collapsed
                if (sidebarCollapsed) {
                    $('.sidebar .nav-link, .sidebar .sidebar-brand').each(function() {
                        const $link = $(this);
                        const text = $link.text().trim();
                        if (text) {
                            $link.attr('data-bs-toggle', 'tooltip');
                            $link.attr('data-bs-placement', 'right');
                            $link.attr('title', text);
                        }
                    });
                    $('[data-bs-toggle="tooltip"]').tooltip();
                }
                
                // Footer modal functionality
                const modalConfigs = {
                    'about': {
                        title: 'About',
                        icon: '<i class="bi bi-bar-chart-steps me-2"></i>',
                        contentUrl: '{{ route("footer.content", ["type" => "about"]) }}'
                    },
                    'privacy': {
                        title: 'Privacy',
                        icon: '<i class="bi bi-shield-lock me-2"></i>',
                        contentUrl: '{{ route("footer.content", ["type" => "privacy"]) }}'
                    },
                    'terms': {
                        title: 'Terms',
                        icon: '<i class="bi bi-file-text me-2"></i>',
                        contentUrl: '{{ route("footer.content", ["type" => "terms"]) }}'
                    },
                    'contact': {
                        title: 'Contact',
                        icon: '<i class="bi bi-envelope me-2"></i>',
                        contentUrl: '{{ route("footer.content", ["type" => "contact"]) }}'
                    }
                };

                // Handle footer link clicks
                $('a[data-footer-modal]').on('click', function(e) {
                    e.preventDefault();
                    const modalType = $(this).data('footer-modal');
                    const config = modalConfigs[modalType];
                    
                    if (!config) {
                        console.error('Unknown modal type:', modalType);
                        return;
                    }

                    // Update modal title and icon
                    $('#footerModalTitle').text(config.title);
                    $('#footerModalIcon').html(config.icon);
                    
                    // Load content
                    $('#footerModalBody').html('<div class="text-center py-4"><div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div></div>');
                    
                    // Show modal first
                    $('#footerModal').modal('show');
                    
                    // Load content via AJAX
                    $.get(config.contentUrl)
                        .done(function(data) {
                            $('#footerModalBody').html(data);
                        })
                        .fail(function() {
                            $('#footerModalBody').html('<div class="alert alert-danger">Failed to load content. Please try again later.</div>');
                        });
                });
            });
        </script>
        @endauth
        
        <!-- GoatCounter Analytics (privacy-preserving) -->
        <script data-goatcounter="https://ls-proto.goatcounter.com/count"
                async src="//gc.zgo.at/count.js"></script>
    </body>
</html>
