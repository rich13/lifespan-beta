@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card">
                <div class="card-body">
                    <h2 class="card-title text-center mb-4">Welcome to Lifespan</h2>
                                        
                    <form method="POST" action="{{ route('profile.complete') }}">
                        @csrf
                        
                        <div class="mb-3">
                            <label for="name" class="form-label">What's your full name?</label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror" 
                                   id="name" name="name" value="{{ old('name') }}" required autofocus>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label class="form-label">What's your date of birth?</label>
                            <div class="row g-2">
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
                            </div>
                            @error('birth_day')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                            @error('birth_month')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                            @error('birth_year')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <button type="submit" class="btn btn-primary w-100">Continue</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
