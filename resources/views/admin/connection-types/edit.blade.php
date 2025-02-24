@extends('layouts.app')

@section('content')
<div class="container-fluid" x-data="{
    forward: '{{ old('name', $connectionType->name) }}',
    inverse: '{{ old('inverse_name', $connectionType->inverse_name) }}'
}">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h2 mb-0">Edit Connection Type</h1>
        <div>
            <a href="{{ route('admin.connection-types.index') }}" class="btn btn-outline-secondary">
                Cancel
            </a>
        </div>
    </div>

    <form action="{{ route('admin.connection-types.update', $connectionType) }}" method="POST">
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
                            <input type="text" class="form-control" value="{{ $connectionType->type }}" disabled>
                            <div class="form-text">Type ID cannot be changed after creation.</div>
                        </div>

                        <div class="mb-3">
                            <label for="name" class="form-label">Name (Forward Predicate)</label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror" 
                                   id="name" name="name" 
                                   x-model="forward"
                                   value="{{ old('name', $connectionType->name) }}" required>
                            <div class="form-text">
                                The predicate that describes how the subject relates to the object (e.g., "is parent of", "worked at").
                                Use present tense and make it read naturally in a sentence.
                            </div>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control @error('description') is-invalid @enderror" 
                                    id="description" name="description" 
                                    rows="2" required>{{ old('description', $connectionType->description) }}</textarea>
                            <div class="form-text">Explain when and how this predicate should be used from subject to object.</div>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="inverse_name" class="form-label">Inverse Name (Inverse Predicate)</label>
                            <input type="text" class="form-control @error('inverse_name') is-invalid @enderror" 
                                   id="inverse_name" name="inverse_name" 
                                   x-model="inverse"
                                   value="{{ old('inverse_name', $connectionType->inverse_name) }}" required>
                            <div class="form-text">
                                The predicate that describes how the object relates back to the subject (e.g., "is child of", "employed").
                                Should be the logical inverse of the forward predicate.
                            </div>
                            @error('inverse_name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="inverse_description" class="form-label">Inverse Description</label>
                            <textarea class="form-control @error('inverse_description') is-invalid @enderror" 
                                    id="inverse_description" name="inverse_description" 
                                    rows="2" required>{{ old('inverse_description', $connectionType->inverse_description) }}</textarea>
                            <div class="form-text">Explain when and how this predicate should be used from object to subject.</div>
                            @error('inverse_description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Live Example Preview -->
                        <div class="card bg-light">
                            <div class="card-body">
                                <h4 class="h6 mb-3">Live Example</h4>
                                <div class="text-muted">
                                    <p class="mb-2">
                                        Forward: "Albert Einstein <strong x-text="forward || '[forward predicate]'"></strong> Princeton University"
                                    </p>
                                    <p class="mb-0">
                                        Inverse: "Princeton University <strong x-text="inverse || '[inverse predicate]'"></strong> Albert Einstein"
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <a href="{{ route('admin.connection-types.index') }}" class="btn btn-outline-secondary">
                        Cancel
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg"></i> Save Changes
                    </button>
                </div>
            </div>

            <!-- Tips Sidebar -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h3 class="h5 card-title">Writing Tips</h3>
                        <ul class="list-unstyled mb-0">
                            <li class="mb-2">✏️ Use present tense for all predicates</li>
                            <li class="mb-2">🔄 Make sure forward and inverse predicates are logical opposites</li>
                            <li class="mb-2">📖 Write predicates that read naturally in a sentence</li>
                            <li class="mb-2">🎯 Be specific and avoid ambiguity</li>
                            <li>💡 Test your predicates in the live example above</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection 