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
        <base href="{{ url()->current() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Favicon -->
        <link rel="icon" href="{{ asset('favicon.ico') }}" type="image/x-icon">

        <!-- jQuery -->
        <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

        <!-- Bootstrap -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

        <!-- Bootstrap Icons -->
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
        
        <!-- Select2 -->
        <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
        <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
        <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

        <!-- Scripts and Styles -->
        @viteReactRefresh
        @vite(['resources/scss/app.scss', 'resources/js/app.js', 'resources/js/routes.js'])

        <!-- Page-specific styles -->
        @stack('styles')
        
        <!-- Custom styles -->
        <style>
            /* Only add minimal custom styling, prefer Bootstrap classes */
            .nav-link {
                color: rgba(255, 255, 255, 0.8) !important;
            }
            .nav-link:hover, .nav-link.active {
                color: #fff !important;
                background-color: rgba(255, 255, 255, 0.1);
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
                display: none;
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
                margin: 0;
                padding: 0;
                min-width: 0;
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
                top: 0;
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
        </style>
        
        <!-- Page-specific scripts -->
        @yield('scripts')
        @stack('scripts')
    </head>
    <body class="bg-light">
        <div class="row">
            @auth
                <!-- Sidebar and Main Content -->
                <div class="row sidebar-row">
                    <!-- Sidebar -->
                    <div id="sidebar" class="bg-secondary border-end d-none d-md-block p-0 sidebar{{ $sidebarCollapsed ? ' collapsed' : '' }}">
                        <div class="sticky-top" style="top: 0; height: 100vh; overflow-y: auto;">
                            <x-sidebar.main-nav />
                            <x-sidebar.admin-nav />
                            <x-sidebar.footer />
                        </div>
                    </div>

                    <!-- Main Content Area with Top Navigation -->
                    <div id="main-content" class="bg-light main-content">
                        <!-- Top Navigation Bar -->
                        <div class="bg-light border-bottom shadow-sm" style="height: 56px;">
                            <div class="d-flex align-items-center h-100 px-3">
                                <x-topnav.page-title />
                                <x-topnav.page-filters />
                                <x-topnav.page-tools />
                                <x-topnav.user-profile />
                            </div>
                        </div>
                        
                        <!-- Page Content -->
                        <div class="py-3 px-3">
                            <div class="header-section mb-4">
                                @yield('header')
                                <x-flash-messages />
                            </div>
                            @yield('content')
                        </div>
                    </div>
                </div>
                
                <!-- Subtle Sidebar Toggle Button -->
                <button id="sidebar-toggle" class="sidebar-toggle-btn{{ $sidebarCollapsed ? ' collapsed' : '' }}" type="button" title="Toggle Sidebar">
                    <i class="bi bi-chevron-left"></i>
                </button>
            @else
                <!-- Guest Top Navigation Bar -->
                <x-topnav.guest-topnav />
                
                <!-- Guest Content Area -->
                <div class="row">
                    <div class="col-12 bg-light py-3">
                        <div class="header-section mb-4">
                            @yield('header')
                            <x-flash-messages />
                        </div>
                        @yield('content')
                    </div>
                </div>
            @endauth
        </div>
        
        <!-- Modals -->
        @stack('modals')
        
        <!-- Sidebar Toggle Script -->
        @auth
        <script>
            $(document).ready(function() {
                // Get stored sidebar state from cookie
                const sidebarCollapsed = document.cookie.split('; ').find(row => row.startsWith('sidebarCollapsed='))?.split('=')[1] === 'true';
                
                // Set initial state based on cookie (no animation)
                if (sidebarCollapsed) {
                    $('#sidebar').addClass('collapsed');
                    $('#sidebar-toggle').addClass('collapsed');
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
            });
        </script>
        @endauth
    </body>
</html>
