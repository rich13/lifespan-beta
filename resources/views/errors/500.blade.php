@extends('errors.layout')

@section('error_code', '500')
@section('error_title', 'Euston...')
@section('error_message', '...we have a problem.')
@section('error_color', 'danger')
@section('error_icon', 'bi-exclamation-triangle-fill')

@section('error_details')
    @if(isset($error) && !app()->environment('production'))
        <div class="mt-4 mb-4">
            <div class="alert alert-warning text-start">
                <h6 class="alert-heading"><i class="bi bi-bug me-2"></i>Debug Information</h6>
                <strong>Error:</strong> {{ $error ?? 'Unknown error' }}
                
                @if(isset($trace))
                    <hr>
                    <h6>Stack Trace</h6>
                    <pre class="bg-light p-3 small overflow-auto mb-0" style="max-height: 300px; font-size: 0.8rem;">{{ $trace }}</pre>
                @endif
            </div>
        </div>
    @endif
@endsection 