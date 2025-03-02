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

        <!-- Scripts and Styles -->
        @viteReactRefresh
        @vite(['resources/scss/app.scss', 'resources/js/app.js'])
        
        <!-- Page-specific scripts -->
        @yield('scripts')
    </head>
    <body>
        <nav class="navbar navbar-expand-lg navbar-light bg-light">
            <div class="container-fluid">
                <a class="navbar-brand" href="{{ route('home') }}"><i class="bi bi-bar-chart-steps me-1"></i> Lifespan &beta;</a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                    <span class="navbar-toggler-icon"></span>
                </button>
                
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav ms-auto">
                        @auth
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                                    <i class="bi bi-person-circle me-1"></i> {{ Auth::user()->name }}
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="{{ route('profile.edit') }}">Profile</a></li>
                                    @if(Auth::user()->is_admin)
                                        <li><a class="dropdown-item" href="{{ route('admin.dashboard') }}">Admin Dashboard</a></li>
                                    @endif
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <form method="POST" action="{{ route('logout') }}">
                                            @csrf
                                            <button type="submit" class="dropdown-item">Sign Out</button>
                                        </form>
                                    </li>
                                </ul>
                            </li>
                        @endauth
                    </ul>
                </div>
            </div>
        </nav>

        <div class="container-fluid">
            <div class="row">
                @auth
                    <div class="col-md-3 col-lg-2 bg-light border-end min-vh-100">
                        <div class="pt-3">
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
                            </ul>

                            @if(Auth::user()->is_admin)
                                <hr>
                                <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
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

                                <hr>
                                <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
                                    <span>Tools</span>
                                </h6>
                                <ul class="nav flex-column">
                                    <li class="nav-item">
                                        <a class="nav-link {{ request()->routeIs('admin.import.*') ? 'active' : '' }}" href="{{ route('admin.import.index') }}">
                                            <i class="bi bi-box-arrow-in-down me-1"></i> Legacy Import
                                        </a>
                                    </li>
                                </ul>
                            @endif
                        </div>
                    </div>
                    <main class="col-md-9 col-lg-10 px-4 py-3">
                        @yield('header')
                        <x-flash-messages />
                        @yield('content')
                    </main>
                @else
                    <main class="col-12 px-4 py-3">
                        @yield('header')
                        <x-flash-messages />
                        @yield('content')
                    </main>
                @endauth
            </div>
        </div>
    </body>
</html>
