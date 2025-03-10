<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <base href="{{ config('app.url') }}">

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
        
        <!-- Page-specific scripts -->
        @yield('scripts')
    </head>
    <body class="bg-light">
        <div class="container-fluid">
            <div class="row">
                @auth
                    <div class="col-md-3 col-lg-2 bg-white border-end min-vh-100 px-0">
                        <!-- Brand -->
                        <div class="p-3 border-bottom bg-light">
                            <a class="text-decoration-none" href="{{ route('home') }}">
                                <h5 class="mb-0 text-dark">
                                    <i class="bi bi-bar-chart-steps me-1"></i> Lifespan &beta;
                                </h5>
                            </a>
                        </div>

                        <!-- User Profile Section -->
                        <div class="p-3 border-bottom">
                            <div class="d-flex align-items-center">
                            
                                <div class="flex-grow-1 ms-3">
                                    @if(Auth::user()->personalSpan)
                                        <x-spans.display.micro-card :span="Auth::user()->personalSpan" />
                                    @else
                                        <h6 class="mb-0">{{ Auth::user()->name }}</h6>
                                    @endif
                                    <div class="btn-group mt-2">
                                        <a href="{{ route('profile.edit') }}" class="btn btn-sm btn-secondary">
                                            <i class="bi bi-person me-1"></i>Profile
                                        </a>
                                        <form method="POST" action="{{ route('logout') }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-secondary">
                                                <i class="bi bi-box-arrow-right me-1"></i>Sign Out
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>

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
                                    <span>Tools</span>
                                </h6>
                                <ul class="nav flex-column">
                                    <li class="nav-item">
                                        <a class="nav-link {{ request()->routeIs('admin.import.*') ? 'active' : '' }}" href="{{ route('admin.import.index') }}">
                                            <i class="bi bi-box-arrow-in-down me-1"></i> Legacy Import
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link {{ request()->routeIs('admin.visualizer.index') ? 'active' : '' }}" href="{{ route('admin.visualizer.index') }}">
                                            <i class="bi bi-graph-up me-1"></i> Network Visualizer
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link {{ request()->routeIs('admin.visualizer.temporal') ? 'active' : '' }}" href="{{ route('admin.visualizer.temporal') }}">
                                            <i class="bi bi-calendar-range me-1"></i> Temporal Visualizer
                                        </a>
                                </ul>
                            @endif
                        </div>
                    </div>
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
    </body>
</html>
