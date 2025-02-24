@extends('layouts.app')

@section('content')
<div class="py-4">
    <form action="{{ route('spans.update', $span) }}" method="POST">
        @csrf
        @method('PUT')

        <div class="row">
            <div class="col-12 d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">Edit: {{ $span->name }}</h1>
                <div class="d-flex gap-2">
                    <a href="{{ route('spans.show', $span) }}" class="btn btn-outline-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg"></i> Save Changes
                    </button>
                </div>
            </div>
        </div>

        @if($errors->any())
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <h5 class="alert-heading">Please fix the following errors:</h5>
                <ul class="mb-0">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <div class="row">
            <div class="col-md-8">
                <!-- Common Fields -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h2 class="card-title h5 mb-3">Basic Information</h2>

                        <!-- Name -->
                        <div class="mb-3">
                            <label for="name" class="form-label">Name</label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror" 
                                   id="name" name="name" value="{{ old('name', $span->name) }}" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Description -->
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control @error('description') is-invalid @enderror" 
                                      id="description" name="description" rows="3">{{ old('description', $span->description) }}</textarea>
                            <div class="form-text">Public description of this span, supports Markdown formatting.</div>
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
                        <x-spans.forms.date-select 
                            prefix="start"
                            label="Start Date"
                            :value="$span"
                            required="true"
                            :showPrecision="false"
                        />

                        <!-- End Date -->
                        <x-spans.forms.date-select 
                            prefix="end"
                            label="End Date"
                            :value="$span"
                            :showPrecision="false"
                        />
                    </div>
                </div>

                <!-- Type-Specific Fields -->
                @if($span->type->getMetadataSchema())
                <div class="card mb-4">
                    <div class="card-body">
                        <h2 class="card-title h5 mb-3">{{ $span->type->name }} Details</h2>
                        @foreach($span->type->getMetadataSchema() as $field => $schema)
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
                                                {{ $schema['required'] ? 'required' : '' }}>{{ old('metadata.' . $field, is_array($span->metadata) && array_key_exists($field, $span->metadata) ? $span->metadata[$field] : '') }}</textarea>
                                        @break

                                    @case('select')
                                        <select class="form-select @error('metadata.' . $field) is-invalid @enderror"
                                                id="metadata_{{ $field }}" 
                                                name="metadata[{{ $field }}]"
                                                {{ $schema['required'] ? 'required' : '' }}>
                                            <option value="">Select {{ $schema['label'] }}</option>
                                            @if(isset($schema['options']))
                                                @foreach((array)$schema['options'] as $key => $option)
                                                    @php
                                                        if (is_array($option)) {
                                                            $value = $option['value'];
                                                            $label = $option['label'];
                                                        } else {
                                                            $value = $option;
                                                            $label = ucfirst($option);
                                                        }
                                                    @endphp
                                                    <option value="{{ $value }}" 
                                                            {{ old('metadata.' . $field, is_array($span->metadata) && array_key_exists($field, $span->metadata) ? $span->metadata[$field] : '') == $value ? 'selected' : '' }}>
                                                        {{ $label }}
                                                    </option>
                                                @endforeach
                                            @endif
                                        </select>
                                        @break

                                    @default
                                        <input type="{{ $schema['type'] }}" 
                                               class="form-control @error('metadata.' . $field) is-invalid @enderror"
                                               id="metadata_{{ $field }}" 
                                               name="metadata[{{ $field }}]"
                                               value="{{ old('metadata.' . $field, is_array($span->metadata) && array_key_exists($field, $span->metadata) ? $span->metadata[$field] : '') }}"
                                               {{ $schema['required'] ? 'required' : '' }}>
                                @endswitch

                                @if(isset($schema['help']))
                                    <div class="form-text">{{ $schema['help'] }}</div>
                                @endif

                                @error('metadata.' . $field)
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        @endforeach
                    </div>
                </div>
                @endif
            </div>

            <div class="col-md-4">
                <!-- Status -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h2 class="card-title h5 mb-3">Status</h2>
                        <div class="mb-3">
                            <select class="form-select @error('state') is-invalid @enderror" 
                                    name="state" required>
                                <option value="draft" {{ old('state', $span->state) == 'draft' ? 'selected' : '' }}>
                                    Draft (work in progress)
                                </option>
                                <option value="placeholder" {{ old('state', $span->state) == 'placeholder' ? 'selected' : '' }}>
                                    Placeholder (date unknown)
                                </option>
                                <option value="complete" {{ old('state', $span->state) == 'complete' ? 'selected' : '' }}>
                                    Complete (ready for viewing)
                                </option>
                            </select>
                            @error('state')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <!-- Internal Notes -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h2 class="card-title h5 mb-3">Internal Notes</h2>
                        <div class="mb-3">
                            <textarea class="form-control @error('notes') is-invalid @enderror" 
                                      id="notes" name="notes" rows="3">{{ old('notes', $span->notes) }}</textarea>
                            <div class="form-text">Private notes for editors, not shown publicly.</div>
                            @error('notes')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <!-- Sources -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h2 class="card-title h5 mb-3">Sources</h2>
                        <x-forms.array-input
                            name="sources"
                            :value="old('sources', $span->sources ?? [])"
                            :item-schema="[
                                'type' => 'url',
                                'placeholder' => 'Enter URL (e.g., https://wikipedia.org/...)',
                                'help' => 'Add links to source material and references'
                            ]"
                            help="Add URLs to source material (e.g., Wikipedia pages, articles, documents)"
                            label="Source URLs"
                        />
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

@endsection 