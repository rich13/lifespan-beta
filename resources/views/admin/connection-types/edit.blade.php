@extends('layouts.app')

@section('page_title')
    Edit Connection Type
@endsection

@section('content')
<div class="container-fluid" x-data="{
    forward: '{{ old('forward_predicate', $connectionType->forward_predicate) }}',
    inverse: '{{ old('inverse_predicate', $connectionType->inverse_predicate) }}',
    type: '{{ old('type', $connectionType->type) }}'
}">
    <div class="d-flex justify-content-end mb-4">
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
                            <label for="forward_predicate" class="form-label">Forward Predicate</label>
                            <input type="text" class="form-control @error('forward_predicate') is-invalid @enderror" 
                                   id="forward_predicate" name="forward_predicate" 
                                   x-model="forward"
                                   value="{{ old('forward_predicate', $connectionType->forward_predicate) }}" required>
                            <div class="form-text">
                                The predicate that describes how the subject relates to the object (e.g., "is parent of", "worked at").
                                Use present tense and make it read naturally in a sentence.
                            </div>
                            @error('forward_predicate')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="forward_description" class="form-label">Forward Description</label>
                            <textarea class="form-control @error('forward_description') is-invalid @enderror" 
                                   id="forward_description" name="forward_description" required>{{ old('forward_description', $connectionType->forward_description) }}</textarea>
                            <div class="form-text">
                                A longer description of how the subject relates to the object.
                            </div>
                            @error('forward_description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="inverse_predicate" class="form-label">Inverse Predicate</label>
                            <input type="text" class="form-control @error('inverse_predicate') is-invalid @enderror" 
                                   id="inverse_predicate" name="inverse_predicate" 
                                   x-model="inverse"
                                   value="{{ old('inverse_predicate', $connectionType->inverse_predicate) }}" required>
                            <div class="form-text">
                                The predicate that describes how the object relates back to the subject (e.g., "is child of", "employed").
                            </div>
                            @error('inverse_predicate')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="inverse_description" class="form-label">Inverse Description</label>
                            <textarea class="form-control @error('inverse_description') is-invalid @enderror" 
                                   id="inverse_description" name="inverse_description" required>{{ old('inverse_description', $connectionType->inverse_description) }}</textarea>
                            <div class="form-text">
                                A longer description of how the object relates back to the subject.
                            </div>
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