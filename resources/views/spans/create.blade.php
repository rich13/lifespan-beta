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

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="start_year" class="form-label">Start Year</label>
                                <input type="number" class="form-control" id="start_year" name="start_year" 
                                       value="{{ old('start_year') }}" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="start_month" class="form-label">Start Month</label>
                                <input type="number" class="form-control" id="start_month" name="start_month" 
                                       value="{{ old('start_month') }}" min="1" max="12">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="start_day" class="form-label">Start Day</label>
                                <input type="number" class="form-control" id="start_day" name="start_day" 
                                       value="{{ old('start_day') }}" min="1" max="31">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="end_year" class="form-label">End Year</label>
                                <input type="number" class="form-control" id="end_year" name="end_year" 
                                       value="{{ old('end_year') }}">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="end_month" class="form-label">End Month</label>
                                <input type="number" class="form-control" id="end_month" name="end_month" 
                                       value="{{ old('end_month') }}" min="1" max="12">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="end_day" class="form-label">End Day</label>
                                <input type="number" class="form-control" id="end_day" name="end_day" 
                                       value="{{ old('end_day') }}" min="1" max="31">
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