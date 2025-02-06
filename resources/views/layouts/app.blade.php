<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Lifespan') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        {{-- 
        Main Layout Structure:
        - Fixed header with navigation
        - Optional sidebar (can be toggled)
        - Main content area
        - Footer with meta information
        --}}
        <div class="min-h-screen bg-gray-100">
            {{-- Header Navigation --}}
            <nav class="bg-white border-b border-gray-100">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="flex justify-between h-16">
                        {{-- Logo/Brand --}}
                        <div class="flex">
                            <div class="shrink-0 flex items-center">
                                <a href="{{ route('home') }}">
                                    {{-- TODO: Replace with actual logo --}}
                                    <span class="text-xl font-bold">Lifespan</span>
                                </a>
                            </div>
                        </div>

                        {{-- Navigation Links --}}
                        <div class="hidden sm:flex sm:items-center sm:ml-6">
                            {{-- 
                            TODO: Navigation items will include:
                            - Spans
                            - Search
                            - Create New
                            - User Menu
                            --}}
                            <div class="ml-3 relative">
                                {{-- Placeholder for navigation items --}}
                                <a href="#" class="text-gray-500 hover:text-gray-700 px-3 py-2">Spans</a>
                            </div>
                        </div>
                    </div>
                </div>
            </nav>

            {{-- 
            Page Layout:
            - Optional sidebar (sm:w-64)
            - Main content area (flex-grow)
            --}}
            <div class="flex">
                {{-- Sidebar (if needed) --}}
                @hasSection('sidebar')
                    <aside class="hidden sm:block w-64 border-r border-gray-200 min-h-screen">
                        <div class="p-4">
                            @yield('sidebar')
                        </div>
                    </aside>
                @endif

                {{-- Main Content --}}
                <main class="flex-grow">
                    <div class="py-12">
                        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                            {{-- Page Header --}}
                            @hasSection('header')
                                <header class="mb-8">
                                    @yield('header')
                                </header>
                            @endif

                            {{-- Main Content Area --}}
                            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                                <div class="p-6 text-gray-900">
                                    @yield('content')
                                </div>
                            </div>
                        </div>
                    </div>
                </main>
            </div>

            {{-- Footer --}}
            <footer class="bg-white border-t border-gray-200">
                <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                    <div class="text-center text-sm text-gray-500">
                        {{-- TODO: Add footer content --}}
                        &copy; {{ date('Y') }} Lifespan
                    </div>
                </div>
            </footer>
        </div>

        {{-- 
        JavaScript Components:
        - Alpine.js for interactivity
        - Custom scripts
        --}}
        @stack('scripts')
    </body>
</html>
