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

        <!-- Favicon -->
        <link rel="icon" href="{{ asset('favicon.ico') }}" type="image/x-icon">

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
        @vite(['resources/scss/app.scss', 'resources/js/app.js', 'resources/js/routes.js'])

        <!-- Page-specific styles -->
        @stack('styles')
        
        <!-- Custom styles -->
        <style>
            /* Only add minimal custom styling, prefer Bootstrap classes */
            body {
                padding-top: 56px; /* Height of the fixed navbar */
            }
            .nav-link {
                color: rgba(255, 255, 255, 0.8) !important;
            }
            .nav-link:hover, .nav-link.active {
                color: #fff !important;
                background-color: rgba(255, 255, 255, 0.1);
            }
        </style>
        
        <!-- Page-specific scripts -->
        @yield('scripts')
        @stack('scripts')
    </head>
    <body class="bg-light">
        <div class="container-fluid">
            <div class="row">
                @auth
                    <!-- Top Navigation Bar -->
                    <div class="col-12 px-0 fixed-top">
                        <div class="container-fluid px-0">
                            <div class="d-flex align-items-center bg-light border-bottom shadow-sm" style="height: 56px;">
                                <x-topnav.brand />
                                <x-topnav.page-title />
                                <x-topnav.page-filters />
                                <x-topnav.page-tools />
                                <x-topnav.user-profile />
                            </div>
                        </div>
                    </div>
                    
                    <!-- Sidebar and Main Content -->
                    <div class="row">
                        <!-- Sidebar -->
                        <div class="col-md-3 col-lg-2 bg-secondary border-end d-none d-md-block p-0">
                            <div class="sticky-top" style="top: 56px; height: calc(100vh - 56px); overflow-y: auto;">
                                <x-sidebar.main-nav />
                                <x-sidebar.admin-nav />
                                <x-sidebar.footer />
                            </div>
                        </div>

                        <!-- Main Content Area -->
                        <div class="col-md-9 col-lg-10 ms-auto bg-light py-3">
                            <div class="header-section mb-4">
                                @yield('header')
                                <x-flash-messages />
                            </div>
                            @yield('content')
                        </div>
                    </div>
                @else
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
        </div>
        
        <!-- Modals -->
        @stack('modals')
        
    </body>
</html>
