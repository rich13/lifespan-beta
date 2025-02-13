@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="row">
        <div class="col-12 d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0">Edit: {{ $span->name }}</h1>
            <div>
                <a href="{{ route('spans.show', $span) }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </div>
    </div>

    <form action="{{ route('spans.update', $span) }}" method="POST">
        @csrf
        @method('PUT')

        <div class="row">
            <div class="col-md-8">
                <!-- Basic Information -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h2 class="card-title h5 mb-3">Basic Information</h2>

                        <div class="mb-3">
                            <label for="name" class="form-label">Name</label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror" 
                                   id="name" name="name" value="{{ old('name', $span->name) }}" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control @error('description') is-invalid @enderror" 
                                      id="description" name="description" rows="3">{{ old('description', $span->description) }}</textarea>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <!-- Temporal Information -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h2 class="card-title h5 mb-3">Dates</h2>

                        <!-- Start Date -->
                        <div class="mb-4">
                            <label class="form-label">Start Date</label>
                            <div class="row g-2">
                                <div class="col-sm-4">
                                    <select class="form-select @error('start_year') is-invalid @enderror" 
                                            name="start_year" required>
                                        <option value="">Year</option>
                                        @for ($year = date('Y') + 100; $year >= 1; $year--)
                                            <option value="{{ $year }}" {{ old('start_year', $span->start_year) == $year ? 'selected' : '' }}>
                                                {{ $year }}
                                            </option>
                                        @endfor
                                    </select>
                                    @error('start_year')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-sm-4">
                                    <select class="form-select @error('start_month') is-invalid @enderror" 
                                            name="start_month">
                                        <option value="">Month</option>
                                        @foreach (range(1, 12) as $month)
                                            <option value="{{ $month }}" {{ old('start_month', $span->start_month) == $month ? 'selected' : '' }}>
                                                {{ date('F', mktime(0, 0, 0, $month, 1)) }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('start_month')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-sm-4">
                                    <select class="form-select @error('start_day') is-invalid @enderror" 
                                            name="start_day">
                                        <option value="">Day</option>
                                        @foreach (range(1, 31) as $day)
                                            <option value="{{ $day }}" {{ old('start_day', $span->start_day) == $day ? 'selected' : '' }}>
                                                {{ $day }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('start_day')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="mt-2">
                                <select class="form-select @error('start_precision') is-invalid @enderror" 
                                        name="start_precision" required>
                                    <option value="year" {{ old('start_precision', $span->start_precision) == 'year' ? 'selected' : '' }}>
                                        Year Precision
                                    </option>
                                    <option value="month" {{ old('start_precision', $span->start_precision) == 'month' ? 'selected' : '' }}>
                                        Month Precision
                                    </option>
                                    <option value="day" {{ old('start_precision', $span->start_precision) == 'day' ? 'selected' : '' }}>
                                        Day Precision
                                    </option>
                                </select>
                                @error('start_precision')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <!-- End Date -->
                        <div class="mb-3">
                            <label class="form-label">End Date</label>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="is_ongoing" 
                                       {{ !$span->end_year ? 'checked' : '' }}
                                       onclick="document.getElementById('endDateFields').style.display = this.checked ? 'none' : 'block'">
                                <label class="form-check-label" for="is_ongoing">
                                    Ongoing/Present
                                </label>
                            </div>
                            <div id="endDateFields" class="row g-2" {{ !$span->end_year ? 'style=display:none' : '' }}>
                                <div class="col-sm-4">
                                    <select class="form-select @error('end_year') is-invalid @enderror" 
                                            name="end_year">
                                        <option value="">Year</option>
                                        @for ($year = date('Y') + 100; $year >= 1; $year--)
                                            <option value="{{ $year }}" {{ old('end_year', $span->end_year) == $year ? 'selected' : '' }}>
                                                {{ $year }}
                                            </option>
                                        @endfor
                                    </select>
                                    @error('end_year')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-sm-4">
                                    <select class="form-select @error('end_month') is-invalid @enderror" 
                                            name="end_month">
                                        <option value="">Month</option>
                                        @foreach (range(1, 12) as $month)
                                            <option value="{{ $month }}" {{ old('end_month', $span->end_month) == $month ? 'selected' : '' }}>
                                                {{ date('F', mktime(0, 0, 0, $month, 1)) }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('end_month')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-sm-4">
                                    <select class="form-select @error('end_day') is-invalid @enderror" 
                                            name="end_day">
                                        <option value="">Day</option>
                                        @foreach (range(1, 31) as $day)
                                            <option value="{{ $day }}" {{ old('end_day', $span->end_day) == $day ? 'selected' : '' }}>
                                                {{ $day }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('end_day')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="mt-2">
                                <select class="form-select @error('end_precision') is-invalid @enderror" 
                                        name="end_precision" required>
                                    <option value="year" {{ old('end_precision', $span->end_precision) == 'year' ? 'selected' : '' }}>
                                        Year Precision
                                    </option>
                                    <option value="month" {{ old('end_precision', $span->end_precision) == 'month' ? 'selected' : '' }}>
                                        Month Precision
                                    </option>
                                    <option value="day" {{ old('end_precision', $span->end_precision) == 'day' ? 'selected' : '' }}>
                                        Day Precision
                                    </option>
                                </select>
                                @error('end_precision')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Type-Specific Fields -->
                @if($span->type->getMetadataSchema())
                <div class="card mb-4">
                    <div class="card-body">
                        <h2 class="card-title h5 mb-3">{{ $span->type->name }} Details</h2>
                        @foreach($span->type->getMetadataSchema() as $field => $schema)
                            @if(!in_array($field, ['is_public', 'is_system']))
                                <div class="mb-3">
                                    <label for="metadata_{{ $field }}" class="form-label">
                                        {{ $schema['label'] }}
                                        @if($schema['required'])
                                            <span class="text-danger">*</span>
                                        @endif
                                    </label>

                                    @switch($schema['component'])
                                        @case('textarea')
                                            <textarea class="form-control @error('metadata.' . $field) is-invalid @enderror"
                                                    id="metadata_{{ $field }}" 
                                                    name="metadata[{{ $field }}]" 
                                                    rows="3"
                                                    {{ $schema['required'] ? 'required' : '' }}>{{ old('metadata.' . $field, $span->metadata[$field] ?? '') }}</textarea>
                                            @break

                                        @case('select')
                                            <select class="form-select @error('metadata.' . $field) is-invalid @enderror"
                                                    id="metadata_{{ $field }}" 
                                                    name="metadata[{{ $field }}]"
                                                    {{ $schema['required'] ? 'required' : '' }}>
                                                <option value="">Select {{ $schema['label'] }}</option>
                                                @foreach($schema['options'] as $option)
                                                    <option value="{{ $option['value'] }}" 
                                                            {{ old('metadata.' . $field, $span->metadata[$field] ?? '') == $option['value'] ? 'selected' : '' }}>
                                                        {{ $option['label'] }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            @break

                                        @default
                                            <input type="{{ $schema['type'] }}" 
                                                   class="form-control @error('metadata.' . $field) is-invalid @enderror"
                                                   id="metadata_{{ $field }}" 
                                                   name="metadata[{{ $field }}]"
                                                   value="{{ old('metadata.' . $field, $span->metadata[$field] ?? '') }}"
                                                   {{ $schema['required'] ? 'required' : '' }}>
                                    @endswitch

                                    @if(isset($schema['help']))
                                        <div class="form-text">{{ $schema['help'] }}</div>
                                    @endif

                                    @error('metadata.' . $field)
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>
                @endif
            </div>

            <div class="col-md-4">
                <!-- State -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h2 class="card-title h5 mb-3">Status</h2>
                        <div class="mb-3">
                            <select class="form-select @error('state') is-invalid @enderror" 
                                    name="state" required>
                                <option value="draft" {{ old('state', $span->state) == 'draft' ? 'selected' : '' }}>
                                    Draft
                                </option>
                                <option value="placeholder" {{ old('state', $span->state) == 'placeholder' ? 'selected' : '' }}>
                                    Placeholder
                                </option>
                                <option value="complete" {{ old('state', $span->state) == 'complete' ? 'selected' : '' }}>
                                    Complete
                                </option>
                            </select>
                            @error('state')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="notes" class="form-label">Internal Notes</label>
                            <textarea class="form-control @error('notes') is-invalid @enderror" 
                                      id="notes" name="notes" rows="3">{{ old('notes', $span->notes) }}</textarea>
                            @error('notes')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">These notes are only visible to editors.</div>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="card">
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                            <a href="{{ route('spans.show', $span) }}" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection 