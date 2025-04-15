<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta http-equiv="Content-Security-Policy" content="upgrade-insecure-requests">

    <title>{{ config('app.name', 'Lifespan') }} - Server Error</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
</head>
<body>
    <div class="container mt-5">
        <div class="row">
            <div class="col-md-8 offset-md-2">
                <div class="card border-danger">
                    <div class="card-header bg-danger text-white">
                        <h4 class="mb-0"><i class="bi bi-exclamation-triangle-fill me-2"></i> Server Error</h4>
                    </div>
                    <div class="card-body">
                        <h5 class="card-title">Sorry, something went wrong!</h5>
                        <p class="card-text">We encountered an error while processing your request. Our team has been notified and is working to fix it.</p>
                        
                        @if(isset($error) && !app()->environment('production'))
                        <div class="mt-4">
                            <h6>Debug Information</h6>
                            <div class="alert alert-warning">
                                <strong>Error:</strong> {{ $error ?? 'Unknown error' }}
                            </div>
                            
                            @if(isset($trace))
                            <div class="mt-3">
                                <h6>Stack Trace</h6>
                                <pre class="bg-light p-3 small overflow-auto" style="max-height: 300px">{{ $trace }}</pre>
                            </div>
                            @endif
                        </div>
                        @endif
                        
                        <div class="mt-4">
                            <a href="{{ route('home') }}" class="btn btn-primary">Return to Home</a>
                            <a href="javascript:history.back()" class="btn btn-outline-secondary ms-2">Go Back</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html> 