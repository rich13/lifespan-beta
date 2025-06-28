@extends('layouts.app')

@section('page_title')
    Connection Types
@endsection

@section('page_tools')
    <a href="{{ route('admin.connection-types.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-circle me-1"></i>New Connection Type
    </a>
@endsection

@section('content')
<div class="py-4">
    @if (session('status'))
        <div class="alert alert-success">
            {{ session('status') }}
        </div>
    @endif

    <div class="card">
        <div class="card-body">
            <div class="alert alert-info mb-4">
                <h5 class="alert-heading">Connection Types and SPO Triples</h5>
                <p class="mb-0">Each connection represents a subject-predicate-object (SPO) triple. The subject is typically a person, and the predicate describes how the subject relates to the object. For example: "Albert Einstein (subject) worked at (predicate) Princeton University (object)".</p>
            </div>

            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Type ID</th>
                            <th>Forward Predicate</th>
                            <th>Inverse Predicate</th>
                            <th>Temporal Constraint</th>
                            <th>Allowed Subject Types</th>
                            <th>Allowed Object Types</th>
                            <th>Connections</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($types as $type)
                            <tr>
                                <td><code>{{ $type->type }}</code></td>
                                <td>
                                    <span class="badge bg-person text-white">subject</span>
                                    <span class="badge bg-{{ $type->type }}">{{ $type->forward_predicate }}</span>
                                    <span class="badge bg-place text-white">object</span>
                                </td>
                                <td>
                                    <span class="badge bg-place text-white">object</span>
                                    <span class="badge bg-{{ $type->type }}">{{ $type->inverse_predicate }}</span>
                                    <span class="badge bg-person text-white">subject</span>
                                </td>
                                <td>
                                    @if($type->constraint_type === 'single')
                                        <span class="badge bg-info">Single</span>
                                    @else
                                        <span class="badge bg-warning">Non-overlapping</span>
                                    @endif
                                </td>
                                <td>
                                    @foreach($type->getAllowedSpanTypes('parent') as $spanType)
                                        <span class="badge bg-{{ $spanType }} text-white">{{ $spanType }}</span>
                                    @endforeach
                                </td>
                                <td>
                                    @foreach($type->getAllowedSpanTypes('child') as $spanType)
                                        <span class="badge bg-{{ $spanType }} text-white">{{ $spanType }}</span>
                                    @endforeach
                                </td>
                                <td>
                                    <span class="badge bg-secondary">{{ $type->connections_count }}</span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('admin.connection-types.edit', $type) }}" 
                                           class="btn btn-outline-primary">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <button type="button" 
                                                class="btn btn-outline-danger" 
                                                onclick="deleteConnectionType('{{ $type->id }}', '{{ $type->type }}')">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function deleteConnectionType(id, type) {
    if (confirm(`Are you sure you want to delete the connection type "${type}"? This action cannot be undone.`)) {
        fetch(`/admin/connection-types/${id}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.reload();
            } else {
                alert('Error deleting connection type: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error deleting connection type');
        });
    }
}
</script>
@endpush
@endsection 