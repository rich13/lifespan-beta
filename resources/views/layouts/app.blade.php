@php
use Illuminate\Support\Facades\Route;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <base href="{{ url()->current() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- jQuery -->
        <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

        <!-- Bootstrap -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

        <!-- Select2 -->
        <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
        <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
        <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

        <!-- Scripts and Styles -->
        @viteReactRefresh
        @vite(['resources/scss/app.scss', 'resources/js/app.js'])
        
        <!-- Page-specific styles -->
        @stack('styles')
        
        <!-- Page-specific scripts -->
        @yield('scripts')
        @stack('scripts')
        
        <style>
            /* Custom dropdown styles */
            .hover-bg-light:hover {
                background-color: #f8f9fa;
            }
            
            #customUserDropdownMenu {
                box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
                border: 1px solid rgba(0, 0, 0, 0.1);
            }
            
            #customUserDropdownMenu .fw-bold {
                font-size: 1rem;
            }
            
            #customUserDropdownMenu .small {
                font-size: 0.8rem;
            }
            
            .max-height-200 {
                max-height: 200px;
                overflow-y: auto;
            }
            
            .user-switch-form {
                margin-bottom: 2px;
            }
            
            .user-switch-form button {
                font-size: 0.9rem;
                text-overflow: ellipsis;
                overflow: hidden;
                white-space: nowrap;
            }
            
            /* Button group styling */
            .btn-group > .btn:not(:first-child):not(:last-child) {
                border-radius: 0;
            }
            
            .btn-group > .btn:first-child {
                border-top-right-radius: 0;
                border-bottom-right-radius: 0;
            }
            
            .btn-group > .btn:last-child {
                border-top-left-radius: 0;
                border-bottom-left-radius: 0;
            }
            
            .btn-group > .btn:only-child {
                border-radius: 0.25rem;
            }

            #customUserDropdownToggle .spans-micro-card {
                color: inherit;
            }

            #customUserDropdownToggle .spans-micro-card:hover {
                text-decoration: none;
                color: inherit;
            }

            #customUserDropdownMenu .spans-micro-card {
                color: inherit;
            }

            #customUserDropdownMenu .spans-micro-card:hover {
                color: var(--bs-primary);
            }
        </style>
    </head>
    <body class="bg-light">
        <div class="container-fluid">
            <div class="row">
                @auth
                    <!-- Top Navigation Bar -->
                    <div class="col-12 px-0">
                        <div class="d-flex align-items-center bg-light border-bottom navbar-height">
                            <!-- Brand -->
                            <div class="h-100 d-flex align-items-center border-end bg-secondary col-md-3 col-lg-2">
                                <a class="text-decoration-none px-3" href="{{ route('home') }}">
                                    <h5 class="mb-0 text-white">
                                        <i class="bi bi-bar-chart-steps me-1"></i> Lifespan &beta;
                                    </h5>
                                </a>
                            </div>
                            
                            <!-- Page Title -->
                            <div class="px-3 flex-grow-1">
                                <h2 class="mb-0 h5">@yield('page_title')</h2>
                            </div>
                            
                            <!-- Page Filters Section -->
                            <div class="px-3 d-flex align-items-center">
                                @yield('page_filters')
                            </div>
                            
                            <!-- Page Tools Section -->
                            <div class="px-3 d-flex align-items-center">
                                @if(trim($__env->yieldContent('page_tools')))
                                    <div class="btn-group">
                                        @yield('page_tools')
                                    </div>
                                @endif
                            </div>
                            
                            <!-- User Profile Section -->
                            <div class="px-3 d-flex align-items-center">
                                <!-- Custom User Dropdown -->
                                <div class="position-relative" id="customUserDropdown">
                                    <button class="btn btn-sm btn-secondary" type="button" id="customUserDropdownToggle">
                                        <i class="bi bi-person-circle me-1"></i>                     
                                        <i class="bi bi-caret-down-fill ms-1"></i>
                                    </button>
                                    <div class="position-absolute end-0 mt-1 bg-white shadow rounded d-none user-dropdown-menu" id="customUserDropdownMenu">
                                        <div class="p-2">
                                            <!-- User Info -->
                                            <div class="px-2 py-1 mb-2 border-bottom">
                                                @if(Auth::user()->personalSpan)
                                                    <x-spans.display.micro-card :span="Auth::user()->personalSpan" />
                                                @else
                                                    <div class="fw-bold">{{ Auth::user()->name }}</div>
                                                @endif
                                                <div class="small text-muted">{{ Auth::user()->email }}</div>
                                            </div>
                                            
                                            <!-- Menu Items -->
                                            <a href="{{ route('profile.edit') }}" class="d-block p-2 text-decoration-none text-dark rounded hover-bg-light">
                                                <i class="bi bi-person me-2"></i>Your Profile
                                            </a>
                                            
                                            @if(Auth::user()->is_admin)
                                                <div class="mt-2 mb-1">
                                                    <div class="px-2 py-1 border-top border-bottom bg-light">
                                                        <small class="text-muted fw-bold">SWITCH TO USER</small>
                                                    </div>
                                                    <div id="userSwitcherList" class="py-1 max-height-200 overflow-auto">
                                                        <div class="px-2 py-1 text-center">
                                                            <div class="spinner-border spinner-border-sm" role="status"></div>
                                                            <small class="ms-2">Loading users...</small>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endif
                                            
                                            @if(Session::has('admin_user_id'))
                                                <form method="POST" action="{{ route('admin.user-switcher.switch-back') }}">
                                                    @csrf
                                                    <button type="submit" class="d-block w-100 text-start p-2 border-0 bg-transparent text-primary rounded hover-bg-light">
                                                        <i class="bi bi-arrow-return-left me-2"></i>Switch Back to Admin
                                                    </button>
                                                </form>
                                            @endif
                                            
                                            <hr class="my-2">
                                            
                                            <form method="POST" action="{{ route('logout') }}">
                                                @csrf
                                                <button type="submit" class="d-block w-100 text-start p-2 border-0 bg-transparent text-danger rounded hover-bg-light">
                                                    <i class="bi bi-box-arrow-right me-2"></i>Sign Out
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Sidebar and Main Content -->
                    <div class="col-md-3 col-lg-2 bg-white border-end min-vh-100 px-0">
                        <!-- Navigation -->
                        <div class="p-3">
                            <ul class="nav flex-column">
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('home') ? 'active' : '' }}" href="{{ route('home') }}">
                                        <i class="bi bi-house-fill me-1"></i> Home
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('spans.*') ? 'active' : '' }}" href="{{ route('spans.index') }}">
                                        <i class="bi bi-bar-chart-steps me-1"></i> Spans
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="#">
                                        <i class="bi bi-collection-fill me-1"></i> Collections
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="#">
                                        <i class="bi bi-search me-1"></i> Explorer
                                    </a>
                                </li>
                            </ul>

                            @if(Auth::user()->is_admin)
                                <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-2 text-muted text-uppercase">
                                    <span>Administration</span>
                                </h6>
                                <ul class="nav flex-column">
                                    <li class="nav-item">
                                        <a class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}">
                                            <i class="bi bi-speedometer2 me-1"></i> Dashboard
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link {{ request()->routeIs('admin.spans.*') ? 'active' : '' }}" href="{{ route('admin.spans.index') }}">
                                            <i class="bi bi-bar-chart-steps me-1"></i> Manage Spans
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link {{ request()->routeIs('admin.span-types.*') ? 'active' : '' }}" href="{{ route('admin.span-types.index') }}">
                                            <i class="bi bi-ui-checks me-1"></i> Manage Span Types
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link {{ request()->routeIs('admin.span-access.index') ? 'active' : '' }}" href="{{ route('admin.span-access.index') }}">
                                            <i class="bi bi-shield-lock me-1"></i> Manage Span Access
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link {{ request()->routeIs('admin.connections.*') ? 'active' : '' }}" href="{{ route('admin.connections.index') }}">
                                            <i class="bi bi-arrow-left-right me-1"></i> Manage Connections
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link {{ request()->routeIs('admin.connection-types.*') ? 'active' : '' }}" href="{{ route('admin.connection-types.index') }}">
                                            <i class="bi bi-sliders2 me-1"></i> Manage Connection Types
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}" href="{{ route('admin.users.index') }}">
                                            <i class="bi bi-people me-1"></i> Manage Users
                                        </a>
                                    </li>
                                </ul>

                                <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-2 text-muted text-uppercase">
                                    <span>Import</span>
                                </h6>
                                <ul class="nav flex-column">
                                    <li class="nav-item">
                                        <a class="nav-link {{ request()->routeIs('admin.import.index') ? 'active' : '' }}" href="{{ route('admin.import.index') }}">
                                            <i class="bi bi-file-earmark-text me-1"></i> YAML Import
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link {{ request()->routeIs('admin.import.musicbrainz.*') ? 'active' : '' }}" href="{{ route('admin.import.musicbrainz.index') }}">
                                            <i class="bi bi-music-note-list me-1"></i> MusicBrainz Import
                                        </a>
                                    </li>
                                </ul>

                                <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-2 text-muted text-uppercase">
                                    <span>Visualizers</span>
                                </h6>
                                <ul class="nav flex-column">
                                    <li class="nav-item">
                                        <a class="nav-link {{ request()->routeIs('admin.visualizer.index') ? 'active' : '' }}" href="{{ route('admin.visualizer.index') }}">
                                            <i class="bi bi-graph-up me-1"></i> Network Explorer
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link {{ request()->routeIs('admin.visualizer.temporal') ? 'active' : '' }}" href="{{ route('admin.visualizer.temporal') }}">
                                            <i class="bi bi-calendar-range me-1"></i> Temporal Explorer
                                        </a>
                                    </li>
                                </ul>
                            @endif
                        </div>
                    </div>

                    <!-- Main Content Area -->
                    <main class="col-md-9 col-lg-10 bg-white min-vh-100">
                        <div class="p-3">
                            <div class="header-section mb-4">
                                @yield('header')
                                <x-flash-messages />
                            </div>
                            @yield('content')
                        </div>
                    </main>
                @else
                    <main class="col-12 bg-white min-vh-100">
                        <div class="p-3">
                            <div class="header-section mb-4">
                                @yield('header')
                                <x-flash-messages />
                            </div>
                            @yield('content')
                        </div>
                    </main>
                @endauth
            </div>
        </div>
        
        <!-- Modals -->
        @stack('modals')
        
    </body>
</html>
