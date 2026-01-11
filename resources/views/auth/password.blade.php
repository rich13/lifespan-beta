@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card">
                <div class="card-body">
                    <h2 class="card-title text-center mb-4">Sign In</h2>
                    <form method="POST" action="{{ route('auth.password.submit') }}">
                        @csrf
                        <input type="hidden" name="email" value="{{ $email }}">
                        
                        <div class="mb-3">
                            <label for="email_display" class="form-label">Email address</label>
                            <input type="email" class="form-control bg-light" 
                                   id="email_display" name="email_display" 
                                   value="{{ $email }}" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control @error('password') is-invalid @enderror" 
                                   id="password" name="password" required autofocus>
                            @error('password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <button type="submit" class="btn btn-primary w-100">Continue</button>
                    </form>
                    <div class="d-flex justify-content-between align-items-center mt-3">
                        <a href="{{ route('auth.clear-remembered-email') }}" class="text-muted small text-decoration-none">
                            ← Use a different email
                        </a>
                        <a href="{{ route('password.request', ['email' => $email]) }}" class="text-muted small text-decoration-none">
                            Forgot your password?
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection 