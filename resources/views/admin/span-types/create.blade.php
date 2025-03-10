@extends('layouts.app')

@section('page_title')
    Create New Span Type
@endsection

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-end mb-4">
        <div>
            <a href="{{ route('admin.span-types.index') }}" class="btn btn-outline-secondary">
                Cancel
            </a>
        </div>
    </div>

    <form action="{{ route('admin.span-types.store') }}" method="POST">
        @csrf

        <div class="row">
            <div class="col-md-8">
                <!-- Basic Information -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h3 class="card-title h5">Basic Information</h3>
                        
                        <div class="mb-3">
                            <label for="type_id" class="form-label">Type ID</label>
                            <input type="text" class="form-control @error('type_id') is-invalid @enderror" 
                                   id="type_id" name="type_id" value="{{ old('type_id') }}" required
                                   pattern="[a-z0-9_-]+" title="Only lowercase letters, numbers, hyphens, and underscores allowed">
                            <div class="form-text">
                                This will be used in URLs and cannot be changed later. Use only lowercase letters, numbers, hyphens, and underscores.
                            </div>
                            @error('type_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="name" class="form-label">Name</label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror" 
                                   id="name" name="name" value="{{ old('name') }}" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control @error('description') is-invalid @enderror" 
                                      id="description" name="description" rows="3" required>{{ old('description') }}</textarea>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <!-- Schema Fields -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h3 class="card-title h5 mb-0">Schema Fields</h3>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="addSchemaField()">
                                Add Field
                            </button>
                        </div>

                        <div id="schema-fields">
                            <!-- Dynamic fields will be added here -->
                        </div>
                    </div>
                </div>

                <!-- Validation Rules -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h3 class="card-title h5 mb-0">Validation Rules</h3>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="addValidationRule()">
                                Add Rule
                            </button>
                        </div>

                        <div id="validation-rules">
                            <!-- Dynamic rules will be added here -->
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <!-- Actions -->
                <div class="card">
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">Create Span Type</button>
                            <a href="{{ route('admin.span-types.index') }}" class="btn btn-outline-secondary">
                                Cancel
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

@push('scripts')
<script>
function addSchemaField() {
    const template = `
        <div class="card mb-3 schema-field">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <h6 class="card-subtitle">Field Configuration</h6>
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeSchemaField(this)">
                        Remove
                    </button>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Field Name</label>
                        <input type="text" class="form-control" name="metadata[schema][new_${Date.now()}][name]" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Label</label>
                        <input type="text" class="form-control" name="metadata[schema][new_${Date.now()}][label]" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Type</label>
                        <select class="form-select" name="metadata[schema][new_${Date.now()}][type]" required>
                            <option value="text">Text</option>
                            <option value="number">Number</option>
                            <option value="date">Date</option>
                            <option value="boolean">Boolean</option>
                            <option value="array">Array</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Component</label>
                        <select class="form-select" name="metadata[schema][new_${Date.now()}][component]" required>
                            <option value="text-input">Text Input</option>
                            <option value="textarea">Textarea</option>
                            <option value="select">Select</option>
                            <option value="checkbox">Checkbox</option>
                            <option value="date-input">Date Input</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Required</label>
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" name="metadata[schema][new_${Date.now()}][required]">
                            <label class="form-check-label">Field is required</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Help Text</label>
                        <input type="text" class="form-control" name="metadata[schema][new_${Date.now()}][help]">
                    </div>
                </div>
            </div>
        </div>
    `;
    document.getElementById('schema-fields').insertAdjacentHTML('beforeend', template);
}

function removeSchemaField(button) {
    button.closest('.schema-field').remove();
}

function addValidationRule() {
    const template = `
        <div class="card mb-3 validation-rule">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <h6 class="card-subtitle">Rule Configuration</h6>
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeValidationRule(this)">
                        Remove
                    </button>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Field</label>
                        <input type="text" class="form-control" name="metadata[validation_rules][new_${Date.now()}][field]" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Rules</label>
                        <input type="text" class="form-control" name="metadata[validation_rules][new_${Date.now()}][rules]" required>
                        <div class="form-text">Separate multiple rules with |</div>
                    </div>
                </div>
            </div>
        </div>
    `;
    document.getElementById('validation-rules').insertAdjacentHTML('beforeend', template);
}

function removeValidationRule(button) {
    button.closest('.validation-rule').remove();
}
</script>
@endpush

@endsection 