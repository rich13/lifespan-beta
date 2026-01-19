@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card">
                <div class="card-body">
                    <h2 class="card-title text-center mb-4">Welcome</h2>
                    
                    @php
                        $emailError = $errors->has('email') ? $errors->first('email') : null;
                        $isApprovalError = $emailError && (str_contains($emailError, 'pending approval') || str_contains($emailError, 'approved'));
                    @endphp
                    
                    @if (session('approval_pending') || $isApprovalError)
                        <x-auth.approval-pending-alert />
                    @elseif ($errors->has('email') && !$isApprovalError)
                        <div class="alert alert-danger mb-4">
                            <strong>Error</strong>
                            <p class="mb-0">{{ $emailError }}</p>
                        </div>
                    @endif
                    
                    <form method="POST" action="{{ route('auth.email') }}">
                        @csrf
                        <div class="mb-3">
                            <label for="email" class="form-label">Email address</label>
                            <input type="email" class="form-control @error('email') is-invalid @enderror" 
                                   id="email" name="email" value="{{ old('email') }}" required autofocus>
                            @if ($errors->has('email') && !$isApprovalError)
                                <div class="invalid-feedback">{{ $errors->first('email') }}</div>
                            @endif
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Continue</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection 