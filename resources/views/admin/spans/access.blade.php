@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="row">
        <div class="col-12 d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0">Manage Access: {{ $span->name }}</h1>
            <a href="{{ route('spans.show', $span) }}" class="btn btn-outline-secondary">Back to Span</a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success">
            {{ session('status') }}
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger">
            {{ session('error') }}
        </div>
    @endif

    <div class="row">
        <div class="col-md-8">
            <!-- User Access Management -->
            <div class="card mb-4">
                <div class="card-body">
                    <h2 class="card-title h5 mb-4">User Access</h2>

                    <form action="{{ route('admin.spans.access.update', $span) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>User</th>
                                        <th>Access Level</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody id="accessList">
                                    @foreach($currentAccess as $access)
                                        <tr>
                                            <td>
                                                <select name="access[{{ $loop->index }}][user_id]" class="form-select" required>
                                                    @foreach($users as $user)
                                                        <option value="{{ $user->id }}" {{ $user->id === $access->user_id ? 'selected' : '' }}>
                                                            {{ $user->email }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </td>
                                            <td>
                                                <select name="access[{{ $loop->index }}][level]" class="form-select" required>
                                                    <option value="viewer" {{ $access->access_level === 'viewer' ? 'selected' : '' }}>Viewer</option>
                                                    <option value="editor" {{ $access->access_level === 'editor' ? 'selected' : '' }}>Editor</option>
                                                    <option value="owner" {{ $access->access_level === 'owner' ? 'selected' : '' }}>Owner</option>
                                                </select>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('tr').remove()">
                                                    Remove
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <button type="button" class="btn btn-outline-secondary mb-3" onclick="addAccessRow()">
                            Add User
                        </button>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">Save Access Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <!-- Visibility Settings -->
            <div class="card">
                <div class="card-body">
                    <h2 class="card-title h5 mb-4">Visibility Settings</h2>

                    <form action="{{ route('admin.spans.visibility.update', $span) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="form-check mb-3">
                            <input type="checkbox" class="form-check-input" id="is_public" name="is_public" 
                                   {{ ($span->metadata['is_public'] ?? false) ? 'checked' : '' }}>
                            <label class="form-check-label" for="is_public">
                                Public Span
                                <small class="d-block text-muted">Visible to all users</small>
                            </label>
                        </div>

                        <div class="form-check mb-4">
                            <input type="checkbox" class="form-check-input" id="is_system" name="is_system"
                                   {{ ($span->metadata['is_system'] ?? false) ? 'checked' : '' }}>
                            <label class="form-check-label" for="is_system">
                                System Span
                                <small class="d-block text-muted">Core system data, always visible</small>
                            </label>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">Update Visibility</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
function addAccessRow() {
    const tbody = document.getElementById('accessList');
    const newIndex = tbody.children.length;
    const template = `
        <tr>
            <td>
                <select name="access[${newIndex}][user_id]" class="form-select" required>
                    <option value="">Select User</option>
                    @foreach($users as $user)
                        <option value="{{ $user->id }}">{{ $user->email }}</option>
                    @endforeach
                </select>
            </td>
            <td>
                <select name="access[${newIndex}][level]" class="form-select" required>
                    <option value="viewer">Viewer</option>
                    <option value="editor">Editor</option>
                    <option value="owner">Owner</option>
                </select>
            </td>
            <td>
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('tr').remove()">
                    Remove
                </button>
            </td>
        </tr>
    `;
    tbody.insertAdjacentHTML('beforeend', template);
}
</script>
@endpush 