@extends('layouts.app')

@section('content')
<div class="py-4">
    <div class="row">
        <div class="col-12 d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0">Edit Connection</h1>
            <div>
                <a href="{{ route('admin.connections.index') }}" class="btn btn-outline-secondary">Back to List</a>
            </div>
        </div>
    </div>

    @if($errors->any())
        <div class="alert alert-danger">
            <h5 class="alert-heading">Please fix the following errors:</h5>
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-body">
                    <form action="{{ route('admin.connections.update', $connection) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <!-- Connection Type -->
                        <div class="mb-3">
                            <label for="type_id" class="form-label">Connection Type</label>
                            <select class="form-select @error('type_id') is-invalid @enderror" 
                                    id="type_id" 
                                    name="type_id" 
                                    required>
                                <option value="">Select Type</option>
                                @foreach($types as $type)
                                    <option value="{{ $type->type }}" 
                                            {{ old('type_id', $connection->type_id) == $type->type ? 'selected' : '' }}>
                                        {{ $type->forward_predicate }}
                                        ({{ $type->type }})
                                    </option>
                                @endforeach
                            </select>
                            @error('type_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Source Span -->
                        <div class="mb-3">
                            <label for="parent_id" class="form-label">Subject</label>
                            <select class="form-select @error('parent_id') is-invalid @enderror" 
                                    id="parent_id" 
                                    name="parent_id" 
                                    required>
                                <option value="">Select Subject</option>
                                @foreach($spans as $span)
                                    <option value="{{ $span->id }}" 
                                            {{ old('parent_id', $connection->parent_id) == $span->id ? 'selected' : '' }}>
                                        {{ $span->name }}
                                        ({{ $span->type_id }})
                                    </option>
                                @endforeach
                            </select>
                            @error('parent_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Target Span -->
                        <div class="mb-3">
                            <label for="child_id" class="form-label">Object</label>
                            <select class="form-select @error('child_id') is-invalid @enderror" 
                                    id="child_id" 
                                    name="child_id" 
                                    required>
                                <option value="">Select Object</option>
                                @foreach($spans as $span)
                                    <option value="{{ $span->id }}" 
                                            {{ old('child_id', $connection->child_id) == $span->id ? 'selected' : '' }}>
                                        {{ $span->name }}
                                        ({{ $span->type_id }})
                                    </option>
                                @endforeach
                            </select>
                            @error('child_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Connection Span -->
                        <div class="mb-3">
                            <label for="connection_span_id" class="form-label">Connection Span</label>
                            <select class="form-select @error('connection_span_id') is-invalid @enderror" 
                                    id="connection_span_id" 
                                    name="connection_span_id" 
                                    required>
                                <option value="">Select Connection Span</option>
                                @foreach($spans->where('type_id', 'connection') as $span)
                                    <option value="{{ $span->id }}" 
                                            {{ old('connection_span_id', $connection->connection_span_id) == $span->id ? 'selected' : '' }}>
                                        {{ $span->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('connection_span_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">
                                Select the span that represents this connection. This must be a span of type 'connection'.
                            </div>
                        </div>

                        <!-- Preview -->
                        <div class="alert alert-secondary">
                            <h6 class="alert-heading">Connection Preview</h6>
                            <p class="mb-0">
                                <span id="subject">{{ $connection->parent->name }}</span>
                                <span class="text-muted" id="predicate">{{ $connection->type->forward_predicate }}</span>
                                <span id="object">{{ $connection->child->name }}</span>
                            </p>
                            <p class="mb-0 mt-2 text-muted small">
                                Inverse: 
                                <span id="inverse-object">{{ $connection->child->name }}</span>
                                <span class="text-muted" id="inverse-predicate">{{ $connection->type->inverse_predicate }}</span>
                                <span id="inverse-subject">{{ $connection->parent->name }}</span>
                            </p>
                        </div>

                        <!-- Connection Span Info -->
                        @if($connection->connectionSpan)
                            <div class="alert alert-info">
                                <h6 class="alert-heading">Connection Span</h6>
                                <p class="mb-0">
                                    This connection is part of the connection span:
                                    <a href="{{ route('spans.show', $connection->connectionSpan) }}" 
                                       class="alert-link">
                                        {{ $connection->connectionSpan->name }}
                                    </a>
                                </p>
                            </div>
                        @endif

                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                            <a href="{{ route('admin.connections.index') }}" class="btn btn-link">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Connection Details</h5>
                    <dl class="mb-0">
                        <dt>Created</dt>
                        <dd>{{ $connection->created_at->format('Y-m-d H:i:s') }}</dd>
                        
                        <dt>Last Updated</dt>
                        <dd>{{ $connection->updated_at->format('Y-m-d H:i:s') }}</dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const typeSelect = document.getElementById('type_id');
    const parentSelect = document.getElementById('parent_id');
    const childSelect = document.getElementById('child_id');
    
    const subjectSpan = document.getElementById('subject');
    const predicateSpan = document.getElementById('predicate');
    const objectSpan = document.getElementById('object');
    const inverseSubjectSpan = document.getElementById('inverse-subject');
    const inversePredicateSpan = document.getElementById('inverse-predicate');
    const inverseObjectSpan = document.getElementById('inverse-object');
    
    const types = @json($types);
    
    function updatePreview() {
        const type = types.find(t => t.type === typeSelect.value);
        const parent = parentSelect.selectedOptions[0]?.text.split(' (')[0] || '(Select Subject)';
        const child = childSelect.selectedOptions[0]?.text.split(' (')[0] || '(Select Object)';
        
        if (type) {
            predicateSpan.textContent = type.forward_predicate;
            inversePredicateSpan.textContent = type.inverse_predicate;
        } else {
            predicateSpan.textContent = '(Select Type)';
            inversePredicateSpan.textContent = '(Select Type)';
        }
        
        subjectSpan.textContent = parent;
        objectSpan.textContent = child;
        inverseSubjectSpan.textContent = parent;
        inverseObjectSpan.textContent = child;
    }
    
    typeSelect.addEventListener('change', updatePreview);
    parentSelect.addEventListener('change', updatePreview);
    childSelect.addEventListener('change', updatePreview);
});
</script>
@endpush

@endsection 