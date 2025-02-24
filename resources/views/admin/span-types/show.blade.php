@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h2 mb-0">{{ $spanType->name }}</h1>
            <div class="text-muted">
                <code>{{ $spanType->type_id }}</code>
            </div>
        </div>
        <div>
            <a href="{{ route('admin.span-types.edit', $spanType) }}" class="btn btn-primary">
                <i class="bi bi-pencil"></i> Edit Type
            </a>
            <a href="{{ route('admin.span-types.index') }}" class="btn btn-outline-secondary">
                Back to List
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8">
            <!-- Basic Information -->
            <div class="card mb-4">
                <div class="card-body">
                    <h3 class="card-title h5">Basic Information</h3>
                    <dl class="row mb-0">
                        <dt class="col-sm-3">Description</dt>
                        <dd class="col-sm-9">{{ $spanType->description }}</dd>

                        <dt class="col-sm-3">Total Spans</dt>
                        <dd class="col-sm-9">{{ $spanType->spans_count }}</dd>

                        <dt class="col-sm-3">Created</dt>
                        <dd class="col-sm-9">{{ $spanType->created_at->format('F j, Y') }}</dd>

                        <dt class="col-sm-3">Last Updated</dt>
                        <dd class="col-sm-9">{{ $spanType->updated_at->format('F j, Y') }}</dd>
                    </dl>
                </div>
            </div>

            <!-- Schema Fields -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h3 class="card-title h5 mb-0">Schema Fields</h3>
                        <a href="{{ route('admin.span-types.edit', $spanType) }}" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-pencil"></i> Edit Schema
                        </a>
                    </div>

                    @php
                        $metadata = is_array($spanType->metadata) ? $spanType->metadata : [];
                        $schema = is_array($metadata['schema'] ?? null) ? $metadata['schema'] : [];
                    @endphp

                    @if(!empty($schema))
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Field</th>
                                        <th>Label</th>
                                        <th>Type</th>
                                        <th>Component</th>
                                        <th>Required</th>
                                        <th>Details</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($schema as $field => $fieldSchema)
                                        @php
                                            $fieldSchema = is_array($fieldSchema) ? $fieldSchema : [];
                                        @endphp
                                        <tr>
                                            <td><code>{{ $field }}</code></td>
                                            <td>{{ $fieldSchema['label'] ?? $field }}</td>
                                            <td>
                                                <span class="badge bg-secondary">{{ $fieldSchema['type'] ?? 'text' }}</span>
                                            </td>
                                            <td>
                                                <span class="badge bg-light text-dark">{{ $fieldSchema['component'] ?? 'text-input' }}</span>
                                            </td>
                                            <td>
                                                @if($fieldSchema['required'] ?? false)
                                                    <i class="bi bi-check-circle-fill text-success"></i>
                                                @else
                                                    <i class="bi bi-circle text-muted"></i>
                                                @endif
                                            </td>
                                            <td>
                                                @if(isset($fieldSchema['help']))
                                                    <small class="text-muted d-block">{{ $fieldSchema['help'] }}</small>
                                                @endif

                                                @if(($fieldSchema['type'] ?? '') === 'select' && isset($fieldSchema['options']))
                                                    <small class="text-muted d-block">
                                                        Options: 
                                                        @foreach((array)$fieldSchema['options'] as $option)
                                                            <span class="badge bg-light text-dark">
                                                                {{ is_array($option) ? ($option['label'] ?? $option['value'] ?? '') : $option }}
                                                            </span>
                                                        @endforeach
                                                    </small>
                                                @endif

                                                @if(($fieldSchema['type'] ?? '') === 'array' && isset($fieldSchema['array_item_schema']))
                                                    @php
                                                        $itemSchema = is_array($fieldSchema['array_item_schema']) ? $fieldSchema['array_item_schema'] : [];
                                                    @endphp
                                                    <small class="text-muted d-block">
                                                        Items: {{ $itemSchema['type'] ?? 'text' }}
                                                        @if(isset($itemSchema['label']))
                                                            ({{ $itemSchema['label'] }})
                                                        @endif
                                                    </small>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-muted mb-0">No schema fields defined yet.</p>
                    @endif
                </div>
            </div>

            <!-- Recent Spans -->
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h3 class="card-title h5 mb-0">Recent Spans</h3>
                        <a href="{{ route('admin.spans.index', ['type' => $spanType->type_id]) }}" class="btn btn-sm btn-outline-secondary">
                            View All
                        </a>
                    </div>

                    @if($spanType->spans->isNotEmpty())
                        <div class="list-group list-group-flush">
                            @foreach($spanType->spans as $span)
                                <a href="{{ route('admin.spans.show', $span) }}" class="list-group-item list-group-item-action">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1">{{ $span->name }}</h6>
                                        <small class="text-muted">{{ $span->created_at->diffForHumans() }}</small>
                                    </div>
                                    @if($span->description)
                                        <p class="mb-1 text-muted">{{ Str::limit($span->description, 100) }}</p>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    @else
                        <p class="text-muted mb-0">No spans of this type yet.</p>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <!-- Validation Rules -->
            <div class="card mb-4">
                <div class="card-body">
                    <h3 class="card-title h5">Validation Rules</h3>
                    @php
                        $validationRules = is_array($metadata['validation_rules'] ?? null) ? $metadata['validation_rules'] : [];
                    @endphp
                    @if(!empty($validationRules))
                        <dl class="mb-0">
                            @foreach($validationRules as $field => $rules)
                                <dt><code>{{ $field }}</code></dt>
                                <dd class="mb-2">{{ is_array($rules) ? implode('|', $rules) : $rules }}</dd>
                            @endforeach
                        </dl>
                    @else
                        <p class="text-muted mb-0">No custom validation rules defined.</p>
                    @endif
                </div>
            </div>

            <!-- Required Fields -->
            <div class="card">
                <div class="card-body">
                    <h3 class="card-title h5">Required Fields</h3>
                    @php
                        $requiredFields = is_array($metadata['required_fields'] ?? null) ? $metadata['required_fields'] : [];
                    @endphp
                    @if(!empty($requiredFields))
                        <ul class="list-unstyled mb-0">
                            @foreach($requiredFields as $field)
                                <li><code>{{ $field }}</code></li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-muted mb-0">No required fields specified.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection 