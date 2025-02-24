@extends('layouts.app')

@section('scripts')
<script>
$(document).ready(function() {
    window.fieldCounter = {{ count($spanType->metadata['schema'] ?? []) }};

    // Add new schema field
    window.addSchemaField = function() {
        fieldCounter++;
        const template = `
            <div class="card mb-3 schema-field">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <h6 class="card-subtitle">Field Configuration</h6>
                        <button type="button" class="btn btn-sm btn-outline-danger remove-field">
                            Remove
                        </button>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Field Name</label>
                            <input type="text" class="form-control" 
                                   name="metadata[schema][new_${fieldCounter}][name]" 
                                   pattern="[a-z0-9_]+" 
                                   title="Use snake_case (lowercase with underscores)"
                                   required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Label</label>
                            <input type="text" class="form-control" 
                                   name="metadata[schema][new_${fieldCounter}][label]" 
                                   required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Type</label>
                            <select class="form-select field-type" 
                                    name="metadata[schema][new_${fieldCounter}][type]" 
                                    required>
                                <option value="text">Text</option>
                                <option value="number">Number</option>
                                <option value="date">Date</option>
                                <option value="boolean">Boolean</option>
                                <option value="array">Array</option>
                                <option value="select">Select</option>
                                <option value="markdown">Markdown</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Component</label>
                            <select class="form-select component-select" 
                                    name="metadata[schema][new_${fieldCounter}][component]" 
                                    required>
                                <option value="text-input">Text Input</option>
                                <option value="textarea">Textarea</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Required</label>
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" 
                                       name="metadata[schema][new_${fieldCounter}][required]">
                                <label class="form-check-label">Field is required</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Help Text</label>
                            <input type="text" class="form-control" 
                                   name="metadata[schema][new_${fieldCounter}][help]">
                        </div>

                        <!-- Options for select type -->
                        <div class="col-12 select-options" style="display: none">
                            <label class="form-label">Options</label>
                            <div class="options-container">
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-secondary add-option">
                                Add Option
                            </button>
                        </div>

                        <!-- Schema for array type -->
                        <div class="col-12 array-schema" style="display: none">
                            <label class="form-label">Array Item Schema</label>
                            <div class="card">
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Item Type</label>
                                            <select class="form-select" 
                                                    name="metadata[schema][new_${fieldCounter}][array_item_schema][type]">
                                                <option value="text">Text</option>
                                                <option value="number">Number</option>
                                                <option value="url">URL</option>
                                                <option value="span">Span</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Item Label</label>
                                            <input type="text" class="form-control" 
                                                   name="metadata[schema][new_${fieldCounter}][array_item_schema][label]">
                                        </div>
                                        <!-- Span type restriction -->
                                        <div class="col-md-12 span-type-restriction" style="display: none">
                                            <label class="form-label">Restrict to Span Type (optional)</label>
                                            <input type="text" class="form-control" 
                                                   name="metadata[schema][new_${fieldCounter}][array_item_schema][span_type]" 
                                                   placeholder="Enter span type ID to restrict selection">
                                            <div class="form-text">Leave empty to allow any span type</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
        $('#schema-fields').append(template);
        
        // Initialize the new field's component options
        const $newField = $('#schema-fields').children().last();
        updateComponentOptions($newField.find('.field-type'));
    };

    // Event delegation for dynamic elements
    $(document).on('click', '.remove-field', function() {
        $(this).closest('.schema-field').remove();
    });

    $(document).on('click', '.add-option', function() {
        const $container = $(this).prev('.options-container');
        const fieldName = $(this).closest('.schema-field').find('input[name$="[name]"]').val();
        const template = `
            <div class="input-group mb-2">
                <input type="text" class="form-control" 
                       name="metadata[schema][${fieldName}][options][][value]" 
                       placeholder="Value" required>
                <input type="text" class="form-control" 
                       name="metadata[schema][${fieldName}][options][][label]" 
                       placeholder="Label" required>
                <button type="button" class="btn btn-outline-danger remove-option">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
        `;
        $container.append(template);
    });

    $(document).on('click', '.remove-option', function() {
        $(this).closest('.input-group').remove();
    });

    // Update component options when field type changes
    $(document).on('change', '.field-type', function() {
        updateComponentOptions($(this));
    });

    // Show/hide span type restriction when array item type changes
    $(document).on('change', 'select[name$="[array_item_schema][type]"]', function() {
        const $spanTypeRestriction = $(this).closest('.card-body').find('.span-type-restriction');
        const isSpan = $(this).val() === 'span';
        $spanTypeRestriction.toggle(isSpan);

        // Update component to span-input when type is span
        if (isSpan) {
            const fieldName = $(this).closest('.schema-field').find('input[name$="[name]"]').val();
            const componentInput = $(this).closest('.card-body').find('input[name$="[array_item_schema][component]"]');
            if (componentInput.length === 0) {
                // Add hidden component input if it doesn't exist
                $(this).closest('.card-body').append(`
                    <input type="hidden" 
                           name="metadata[schema][${fieldName}][array_item_schema][component]" 
                           value="span-input">
                `);
            } else {
                componentInput.val('span-input');
            }
        }
    });

    function updateComponentOptions($select) {
        const $fieldCard = $select.closest('.schema-field');
        const type = $select.val();
        const $componentSelect = $fieldCard.find('.component-select');
        const $selectOptions = $fieldCard.find('.select-options');
        const $arraySchema = $fieldCard.find('.array-schema');

        // Show/hide options based on type
        $selectOptions.toggle(type === 'select');
        $arraySchema.toggle(type === 'array');

        // Update available components based on type
        const components = {
            text: ['text-input', 'textarea'],
            number: ['text-input'],
            date: ['date-input'],
            boolean: ['checkbox'],
            array: ['array-input'],
            select: ['select'],
            markdown: ['markdown-editor'],
            span: ['span-input']
        };

        $componentSelect.empty();
        
        (components[type] || ['text-input']).forEach(component => {
            const label = component.split('-').map(word => 
                word.charAt(0).toUpperCase() + word.slice(1)
            ).join(' ');
            $componentSelect.append(new Option(label, component));
        });
    }

    // Initialize component options for existing fields
    $('.field-type').each(function() {
        updateComponentOptions($(this));
    });
});
</script>
@endsection

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h2 mb-0">Edit: {{ $spanType->name }}</h1>
        <div>
            <a href="{{ route('admin.span-types.index') }}" class="btn btn-outline-secondary">
                Cancel
            </a>
        </div>
    </div>

    <form action="{{ route('admin.span-types.update', $spanType) }}" method="POST">
        @csrf
        @method('PUT')

        <div class="row">
            <div class="col-md-8">
                <!-- Basic Information -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h3 class="card-title h5">Basic Information</h3>
                        
                        <div class="mb-3">
                            <label class="form-label">Type ID</label>
                            <input type="text" class="form-control" value="{{ $spanType->type_id }}" disabled>
                            <div class="form-text">Type ID cannot be changed after creation.</div>
                        </div>

                        <div class="mb-3">
                            <label for="name" class="form-label">Name</label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror" 
                                   id="name" name="name" value="{{ old('name', $spanType->name) }}" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control @error('description') is-invalid @enderror" 
                                      id="description" name="description" rows="3" required>{{ old('description', $spanType->description) }}</textarea>
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
                            @if($spanType->metadata['schema'] ?? false)
                                @foreach($spanType->metadata['schema'] as $fieldName => $schema)
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
                                                    <input type="text" class="form-control" 
                                                           name="metadata[schema][{{ $fieldName }}][name]" 
                                                           value="{{ $fieldName }}"
                                                           pattern="[a-z0-9_]+" 
                                                           title="Use snake_case (lowercase with underscores)"
                                                           required>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Label</label>
                                                    <input type="text" class="form-control" 
                                                           name="metadata[schema][{{ $fieldName }}][label]" 
                                                           value="{{ $schema['label'] ?? '' }}" 
                                                           required>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label">Type</label>
                                                    <select class="form-select" 
                                                            name="metadata[schema][{{ $fieldName }}][type]" 
                                                            onchange="updateComponentOptions(this)"
                                                            required>
                                                        <option value="text" {{ ($schema['type'] ?? 'text') === 'text' ? 'selected' : '' }}>Text</option>
                                                        <option value="number" {{ ($schema['type'] ?? '') === 'number' ? 'selected' : '' }}>Number</option>
                                                        <option value="date" {{ ($schema['type'] ?? '') === 'date' ? 'selected' : '' }}>Date</option>
                                                        <option value="boolean" {{ ($schema['type'] ?? '') === 'boolean' ? 'selected' : '' }}>Boolean</option>
                                                        <option value="array" {{ ($schema['type'] ?? '') === 'array' ? 'selected' : '' }}>Array</option>
                                                        <option value="select" {{ ($schema['type'] ?? '') === 'select' ? 'selected' : '' }}>Select</option>
                                                        <option value="markdown" {{ ($schema['type'] ?? '') === 'markdown' ? 'selected' : '' }}>Markdown</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label">Component</label>
                                                    <select class="form-select component-select" 
                                                            name="metadata[schema][{{ $fieldName }}][component]" 
                                                            required>
                                                        <option value="text-input" {{ ($schema['component'] ?? '') === 'text-input' ? 'selected' : '' }}>Text Input</option>
                                                        <option value="textarea" {{ ($schema['component'] ?? '') === 'textarea' ? 'selected' : '' }}>Textarea</option>
                                                        <option value="select" {{ ($schema['component'] ?? '') === 'select' ? 'selected' : '' }}>Select</option>
                                                        <option value="checkbox" {{ ($schema['component'] ?? '') === 'checkbox' ? 'selected' : '' }}>Checkbox</option>
                                                        <option value="date-input" {{ ($schema['component'] ?? '') === 'date-input' ? 'selected' : '' }}>Date Input</option>
                                                        <option value="array-input" {{ ($schema['component'] ?? '') === 'array-input' ? 'selected' : '' }}>Array Input</option>
                                                        <option value="markdown-editor" {{ ($schema['component'] ?? '') === 'markdown-editor' ? 'selected' : '' }}>Markdown Editor</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label">Required</label>
                                                    <div class="form-check mt-2">
                                                        <input class="form-check-input" type="checkbox" 
                                                               name="metadata[schema][{{ $fieldName }}][required]" 
                                                               {{ ($schema['required'] ?? false) ? 'checked' : '' }}>
                                                        <label class="form-check-label">Field is required</label>
                                                    </div>
                                                </div>
                                                <div class="col-12">
                                                    <label class="form-label">Help Text</label>
                                                    <input type="text" class="form-control" 
                                                           name="metadata[schema][{{ $fieldName }}][help]" 
                                                           value="{{ $schema['help'] ?? '' }}">
                                                </div>

                                                <!-- Options for select type -->
                                                <div class="col-12 select-options" style="{{ ($schema['type'] ?? '') === 'select' ? '' : 'display: none' }}">
                                                    <label class="form-label">Options</label>
                                                    <div class="options-container">
                                                        @if(isset($schema['options']))
                                                            @foreach($schema['options'] as $option)
                                                                <div class="input-group mb-2">
                                                                    <input type="text" class="form-control" 
                                                                           name="metadata[schema][{{ $fieldName }}][options][][value]" 
                                                                           placeholder="Value"
                                                                           value="{{ $option['value'] ?? '' }}">
                                                                    <input type="text" class="form-control" 
                                                                           name="metadata[schema][{{ $fieldName }}][options][][label]" 
                                                                           placeholder="Label"
                                                                           value="{{ $option['label'] ?? '' }}">
                                                                    <button type="button" class="btn btn-outline-danger" onclick="removeOption(this)">
                                                                        <i class="bi bi-trash"></i>
                                                                    </button>
                                                                </div>
                                                            @endforeach
                                                        @endif
                                                    </div>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="addOption(this)">
                                                        Add Option
                                                    </button>
                                                </div>

                                                <!-- Schema for array type -->
                                                <div class="col-12 array-schema" style="{{ ($schema['type'] ?? '') === 'array' ? '' : 'display: none' }}">
                                                    <label class="form-label">Array Item Schema</label>
                                                    <div class="card">
                                                        <div class="card-body">
                                                            <div class="row g-3">
                                                                <div class="col-md-6">
                                                                    <label class="form-label">Item Type</label>
                                                                    <select class="form-select" 
                                                                            name="metadata[schema][{{ $fieldName }}][array_item_schema][type]">
                                                                        <option value="text" {{ ($schema['array_item_schema']['type'] ?? '') === 'text' ? 'selected' : '' }}>Text</option>
                                                                        <option value="number" {{ ($schema['array_item_schema']['type'] ?? '') === 'number' ? 'selected' : '' }}>Number</option>
                                                                        <option value="url" {{ ($schema['array_item_schema']['type'] ?? '') === 'url' ? 'selected' : '' }}>URL</option>
                                                                        <option value="span" {{ ($schema['array_item_schema']['type'] ?? '') === 'span' ? 'selected' : '' }}>Span</option>
                                                                    </select>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <label class="form-label">Item Label</label>
                                                                    <input type="text" class="form-control" 
                                                                           name="metadata[schema][{{ $fieldName }}][array_item_schema][label]" 
                                                                           value="{{ $schema['array_item_schema']['label'] ?? '' }}">
                                                                </div>
                                                                <!-- Span type restriction -->
                                                                <div class="col-md-12 span-type-restriction" style="{{ ($schema['array_item_schema']['type'] ?? '') === 'span' ? '' : 'display: none' }}">
                                                                    <label class="form-label">Restrict to Span Type (optional)</label>
                                                                    <input type="text" class="form-control" 
                                                                           name="metadata[schema][{{ $fieldName }}][array_item_schema][span_type]" 
                                                                           value="{{ $schema['array_item_schema']['span_type'] ?? '' }}"
                                                                           placeholder="Enter span type ID to restrict selection">
                                                                    <div class="form-text">Leave empty to allow any span type</div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <!-- Actions -->
                <div class="card">
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">Save Changes</button>
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
@endsection 