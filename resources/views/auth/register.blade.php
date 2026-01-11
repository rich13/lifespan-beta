@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card">
                <div class="card-body">
                    <h2 class="card-title text-center mb-4">Create Account</h2>
                    <form method="POST" action="{{ route('register.store') }}">
                        @csrf
                        <input type="hidden" name="email" value="{{ $email ?? old('email') }}">
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email address</label>
                            <input type="email" class="form-control bg-light" 
                                   id="email" name="email" 
                                   value="{{ $email ?? old('email') }}" 
                                   readonly 
                                   required>
                            @error('email')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                        
                        <div class="mb-3">
                            <label for="name" class="form-label">Name</label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror" 
                                   id="name" name="name" value="{{ old('name') }}" required autofocus>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label class="form-label">Birth Date</label>
                            <div class="row g-2">
                                <div class="col-sm-4">
                                    <select class="form-select @error('birth_year') is-invalid @enderror" 
                                            name="birth_year" required>
                                        <option value="">Year</option>
                                        @for ($year = date('Y'); $year >= 1900; $year--)
                                            <option value="{{ $year }}" {{ old('birth_year') == $year ? 'selected' : '' }}>
                                                {{ $year }}
                                            </option>
                                        @endfor
                                    </select>
                                </div>
                                <div class="col-sm-4">
                                    <select class="form-select @error('birth_month') is-invalid @enderror" 
                                            name="birth_month" required>
                                        <option value="">Month</option>
                                        @foreach (range(1, 12) as $month)
                                            <option value="{{ $month }}" {{ old('birth_month') == $month ? 'selected' : '' }}>
                                                {{ date('F', mktime(0, 0, 0, $month, 1)) }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-sm-4">
                                    <select class="form-select @error('birth_day') is-invalid @enderror" 
                                            name="birth_day" required>
                                        <option value="">Day</option>
                                        @foreach (range(1, 31) as $day)
                                            <option value="{{ $day }}" {{ old('birth_day') == $day ? 'selected' : '' }}>
                                                {{ $day }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            @error('birth_year')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                            @error('birth_month')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                            @error('birth_day')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control @error('password') is-invalid @enderror" 
                                   id="password" name="password" required>
                            @error('password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="password_confirmation" class="form-label">Confirm Password</label>
                            <input type="password" class="form-control" 
                                   id="password_confirmation" name="password_confirmation" required>
                        </div>

                        {{-- Honeypot field - hidden from users but bots may fill it --}}
                        <div style="position: absolute; left: -9999px; opacity: 0;" aria-hidden="true">
                            <label for="website">Website</label>
                            <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                        </div>

                        {{-- Invitation code field - commented out but kept for potential future use
                        <div class="mb-3">
                            <label for="invitation_code" class="form-label">Invitation Code <span class="text-muted">(optional)</span></label>
                            <input type="text" class="form-control @error('invitation_code') is-invalid @enderror" 
                                   id="invitation_code" name="invitation_code" value="{{ old('invitation_code') }}">
                            <small class="form-text text-muted">If you don't have an invitation code, your registration will be reviewed and approved manually.</small>
                            @error('invitation_code')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        --}}

                        <button type="submit" class="btn btn-primary w-100">Create Account</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
