<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>@yield('title', config('app.name', 'Laravel'))</title>

        <!-- Favicon and App Icons -->
        <link rel="icon" href="{{ asset('img/favicon.ico') }}" type="image/x-icon">
        <link rel="icon" href="{{ asset('img/favicon-16x16.png') }}" type="image/png" sizes="16x16">
        <link rel="icon" href="{{ asset('img/favicon-32x32.png') }}" type="image/png" sizes="32x32">
        <link rel="apple-touch-icon" href="{{ asset('img/apple-touch-icon.png') }}">
        <link rel="manifest" href="{{ asset('img/site.webmanifest') }}">

        <!-- jQuery -->
        <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

        <!-- Bootstrap -->
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

        <!-- Bootstrap Icons -->
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

        <style>
        .plaques-back-link {
            position: fixed;
            top: 1rem;
            left: 1rem;
            z-index: 2000;
            padding: 0.5rem 1rem;
            background: #fff;
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            color: #212529;
            text-decoration: none;
            font-size: 0.9rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
}
        .plaques-back-link:hover {
            background: #f8f9fa;
            color: #0d6efd;
        }
        </style>
        @stack('styles')
    </head>
    <body class="min-vh-100 d-flex align-items-center justify-content-center bg-light">
        <a href="{{ route('plaques.index') }}" class="plaques-back-link">Plaques</a>
        @yield('content')
        @stack('scripts')
    </body>
</html>
