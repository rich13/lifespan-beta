<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Lifespan') }} - @yield('error_title', 'Error')</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <!-- Favicon -->
    <link rel="icon" href="{{ asset('favicon.ico') }}" type="image/x-icon">
    
    <style>
        body {
            background: #eee;
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        
        .error-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: none;
            border-radius: 13px;
            box-shadow: 0 13px 35px rgba(0, 0, 0, 0.1);
        }
        
        .error-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }
        
        .error-code {
            font-size: 6rem;
            font-weight: 700;
            color: #6c757d;
            line-height: 1;
            margin-bottom: 0.5rem;
        }
        
        .error-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }
        
        .error-message {
            color: #6c757d;
            font-size: 1.1rem;
            line-height: 1.6;
        }
        
        .btn-home {
            background: #6c757d;
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 25px;
            font-weight: 500;
            color: #000;
        }
        
        .btn-home:hover {
            color: #000;
        }
    
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="card error-card text-center p-5">
                    <div class="error-icon text-@yield('error_color', 'danger')">
                        <i class="@yield('error_icon', 'bi-exclamation-triangle-fill')"></i>
                    </div>
                    
                    <div class="error-code">@yield('error_code', '500')</div>
                    
                    <h1 class="error-title">@yield('error_title', 'Server Error')</h1>
                    
                    <p class="error-message mb-4">
                        @yield('error_message', 'We encountered an error while processing your request.')
                    </p>
                    
                    @yield('error_details')
                    
                    <div class="d-flex flex-column flex-sm-row gap-3 justify-content-center">
                        <a href="{{ request()->getScheme() }}://{{ request()->getHttpHost() }}/" class="btn btn-primary">Home</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    @stack('scripts')
</body>
</html> 