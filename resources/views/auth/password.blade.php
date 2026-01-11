@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card">
                <div class="card-body">
                    <h2 class="card-title text-center mb-4">Sign In</h2>
                    
                    @if (session('status'))
                        <div class="alert alert-success mb-4">
                            {{ session('status') }}
                        </div>
                    @endif
                    
                    @if ($errors->has('email'))
                        <div class="alert alert-warning mb-4">
                            <strong>Email Verification Required</strong>
                            <p class="mb-2">{{ $errors->first('email') }}</p>
                            <form method="POST" action="{{ route('verification.resend') }}" class="d-inline">
                                @csrf
                                <input type="hidden" name="email" value="{{ $email }}">
                                <button type="submit" class="btn btn-sm btn-outline-primary">
                                    Resend Verification Email
                                </button>
                            </form>
                        </div>
                    @endif
                    
                    <form method="POST" action="{{ route('auth.password.submit') }}">
                        @csrf
                        <input type="hidden" name="email" value="{{ $email }}">
                        
                        <div class="mb-3">
                            <label for="email_display" class="form-label">Email address</label>
                            <input type="email" class="form-control bg-light" 
                                   id="email_display" name="email_display" 
                                   value="{{ $email }}" readonly>
                            @error('email')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
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