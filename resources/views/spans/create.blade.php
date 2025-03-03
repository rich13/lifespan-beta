@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h2>Create New Span</h2>
                </div>
                <div class="card-body">
                    <form action="{{ route('spans.store') }}" method="POST">
                        @csrf
                        
                        <div class="mb-3">
                            <label for="name" class="form-label">Name</label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror" 
                                   id="name" name="name" value="{{ old('name') }}" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="type_id" class="form-label">Type</label>
                                <select class="form-select @error('type_id') is-invalid @enderror" 
                                        id="type_id" name="type_id" required>
                                    <option value="">Select a type...</option>
                                    @foreach($spanTypes as $type)
                                        <option value="{{ $type->type_id }}" {{ old('type_id') == $type->type_id ? 'selected' : '' }}>
                                            {{ ucfirst($type->name) }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('type_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label for="state" class="form-label">State</label>
                                <select class="form-select @error('state') is-invalid @enderror" 
                                        id="state" name="state" required>
                                    <option value="">Select a state...</option>
                                    <option value="draft" {{ old('state') == 'draft' ? 'selected' : '' }}>Draft</option>
                                    <option value="placeholder" {{ old('state') == 'placeholder' ? 'selected' : '' }}>Placeholder</option>
                                    <option value="complete" {{ old('state') == 'complete' ? 'selected' : '' }}>Complete</option>
                                </select>
                                @error('state')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="start_year" class="form-label">Start Year</label>
                                <input type="number" class="form-control @error('start_year') is-invalid @enderror" 
                                       id="start_year" name="start_year" value="{{ old('start_year') }}" required>
                                @error('start_year')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="start_month" class="form-label">Start Month</label>
                                <input type="number" class="form-control @error('start_month') is-invalid @enderror" 
                                       id="start_month" name="start_month" value="{{ old('start_month') }}" min="1" max="12">
                                @error('start_month')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="start_day" class="form-label">Start Day</label>
                                <input type="number" class="form-control @error('start_day') is-invalid @enderror" 
                                       id="start_day" name="start_day" value="{{ old('start_day') }}" min="1" max="31">
                                @error('start_day')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="end_year" class="form-label">End Year</label>
                                <input type="number" class="form-control @error('end_year') is-invalid @enderror" 
                                       id="end_year" name="end_year" value="{{ old('end_year') }}">
                                @error('end_year')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="end_month" class="form-label">End Month</label>
                                <input type="number" class="form-control @error('end_month') is-invalid @enderror" 
                                       id="end_month" name="end_month" value="{{ old('end_month') }}" min="1" max="12">
                                @error('end_month')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="end_day" class="form-label">End Day</label>
                                <input type="number" class="form-control @error('end_day') is-invalid @enderror" 
                                       id="end_day" name="end_day" value="{{ old('end_day') }}" min="1" max="31">
                                @error('end_day')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="{{ route('spans.index') }}" class="btn btn-outline-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">Create Span</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection 