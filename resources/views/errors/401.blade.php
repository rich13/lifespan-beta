@extends('errors.layout')

@section('error_code', '401')
@section('error_title', 'You shall not pass')
@section('error_message', 'But you could try signing in...')
@section('error_color', 'warning')
@section('error_icon', 'bi-person-x')

@section('error_details')
    <div class="mt-4 mb-4">
        <a href="{{ route('login') }}" class="btn btn-warning">
            <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
        </a>
    </div>
@endsection 