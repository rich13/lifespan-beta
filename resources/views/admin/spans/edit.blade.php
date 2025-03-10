@extends('layouts.app')

@section('page_title')
    Edit Span: {{ $span->name }}
@endsection

@section('content')
<div class="container py-4">
    <div class="row">
        <div class="col-12 d-flex justify-content-end mb-4">
            <div>
                <a href="{{ route('admin.spans.show', $span) }}" class="btn btn-outline-secondary">View Span</a>
                <a href="{{ route('admin.spans.permissions.edit', $span) }}" class="btn btn-outline-secondary">Manage Permissions</a>
                <a href="{{ route('admin.spans.access.edit', $span) }}" class="btn btn-outline-secondary">Manage Access</a>
                <a href="{{ route('admin.spans.index') }}" class="btn btn-outline-secondary">Back to List</a>
            </div>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success">
            {{ session('status') }}
        </div>
    @endif

    <div class="row">
        <div class="col-md-8">
            <form action="{{ route('admin.spans.update', $span) }}" method="POST">
                @csrf
                @method('PUT')

                <div class="card mb-4">
                    <div class="card-body">
                        <h2 class="card-title h5">Basic Information</h2>

                        <div class="mb-3">
                            <label for="name" class="form-label">Name</label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror" 
                                   id="name" name="name" value="{{ old('name', $span->name) }}" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="type_id" class="form-label">Type</label>
                            <select class="form-select @error('type_id') is-invalid @enderror" 
                                    id="type_id" name="type_id" required>
                                @foreach($types as $type)
                                    <option value="{{ $type->id }}" 
                                            {{ old('type_id', $span->type_id) == $type->id ? 'selected' : '' }}>
                                        {{ $type->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('type_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="owner_id" class="form-label">Owner</label>
                            <select class="form-select @error('owner_id') is-invalid @enderror" 
                                    id="owner_id" name="owner_id" required>
                                @foreach($users as $user)
                                    <option value="{{ $user->id }}" 
                                            {{ old('owner_id', $span->owner_id) == $user->id ? 'selected' : '' }}>
                                        {{ $user->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('owner_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="start_date" class="form-label">Start Date</label>
                            <input type="date" class="form-control @error('start_date') is-invalid @enderror" 
                                   id="start_date" name="start_date" 
                                   value="{{ old('start_date', $span->start_date->format('Y-m-d')) }}" required>
                            @error('start_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="end_date" class="form-label">End Date</label>
                            <input type="date" class="form-control @error('end_date') is-invalid @enderror" 
                                   id="end_date" name="end_date" 
                                   value="{{ old('end_date', $span->end_date ? $span->end_date->format('Y-m-d') : '') }}">
                            @error('end_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">Leave blank for ongoing spans.</div>
                        </div>

                        <div class="mb-3">
                            <label for="parent_id" class="form-label">Parent Span</label>
                            <select class="form-select @error('parent_id') is-invalid @enderror" 
                                    id="parent_id" name="parent_id">
                                <option value="">No Parent</option>
                                @foreach($availableParents as $parent)
                                    <option value="{{ $parent->id }}" 
                                            {{ old('parent_id', $span->parent_id) == $parent->id ? 'selected' : '' }}>
                                        {{ $parent->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('parent_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-body">
                        <h2 class="card-title h5">Permissions</h2>

                        <div class="mb-3">
                            <label class="form-label">Permission Mode</label>
                            <div class="form-check">
                                <input type="radio" class="form-check-input @error('permission_mode') is-invalid @enderror" 
                                       id="permission_mode_own" name="permission_mode" value="own" 
                                       {{ old('permission_mode', $span->permission_mode) === 'own' ? 'checked' : '' }}>
                                <label class="form-check-label" for="permission_mode_own">
                                    Own Permissions
                                </label>
                            </div>
                            <div class="form-check">
                                <input type="radio" class="form-check-input @error('permission_mode') is-invalid @enderror" 
                                       id="permission_mode_inherit" name="permission_mode" value="inherit" 
                                       {{ old('permission_mode', $span->permission_mode) === 'inherit' ? 'checked' : '' }}
                                       {{ !$span->parent_id ? 'disabled' : '' }}>
                                <label class="form-check-label" for="permission_mode_inherit">
                                    Inherit from Parent
                                </label>
                                @error('permission_mode')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="form-text">
                                Inherited permissions require a parent span to be selected.
                            </div>
                        </div>

                        <div id="permissions_section" 
                             class="{{ old('permission_mode', $span->permission_mode) === 'inherit' ? 'd-none' : '' }}">
                            <div class="mb-3">
                                <label class="form-label">Visibility</label>
                                <div class="form-check">
                                    <input type="radio" class="form-check-input @error('visibility') is-invalid @enderror" 
                                           id="visibility_public" name="visibility" value="public" 
                                           {{ $span->isPublic() ? 'checked' : '' }}>
                                    <label class="form-check-label" for="visibility_public">
                                        Public (Anyone can view)
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input type="radio" class="form-check-input @error('visibility') is-invalid @enderror" 
                                           id="visibility_private" name="visibility" value="private" 
                                           {{ $span->isPrivate() ? 'checked' : '' }}>
                                    <label class="form-check-label" for="visibility_private">
                                        Private (Only owner and group members)
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input type="radio" class="form-check-input @error('visibility') is-invalid @enderror" 
                                           id="visibility_group" name="visibility" value="group" 
                                           {{ !$span->isPublic() && !$span->isPrivate() ? 'checked' : '' }}>
                                    <label class="form-check-label" for="visibility_group">
                                        Group Access (Configure below)
                                    </label>
                                    @error('visibility')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div id="group_permissions" 
                                 class="{{ !$span->isPublic() && !$span->isPrivate() ? '' : 'd-none' }}">
                                <div class="mb-3">
                                    <label class="form-label">Group Permissions</label>
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" 
                                               id="group_read" name="group_read" value="1" 
                                               {{ $span->canGroupRead() ? 'checked' : '' }}>
                                        <label class="form-check-label" for="group_read">
                                            Group members can view
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" 
                                               id="group_write" name="group_write" value="1" 
                                               {{ $span->canGroupWrite() ? 'checked' : '' }}>
                                        <label class="form-check-label" for="group_write">
                                            Group members can edit
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-between">
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                    
                    <div>
                        <a href="{{ route('admin.spans.permissions', $span) }}" 
                           class="btn btn-outline-primary">Manage Permissions</a>
                        <a href="{{ route('admin.spans.access', $span) }}" 
                           class="btn btn-outline-primary">Manage Access</a>
                    </div>
                </div>
            </form>
        </div>

        <div class="col-md-4">
            <!-- Child Spans -->
            <div class="card mb-4">
                <div class="card-body">
                    <h2 class="card-title h5">Child Spans</h2>
                    @if($span->children->count() > 0)
                        <ul class="list-unstyled mb-0">
                            @foreach($span->children as $child)
                                <li class="mb-2">
                                    <a href="{{ route('admin.spans.show', $child) }}" 
                                       class="text-decoration-none">
                                        {{ $child->name }}
                                    </a>
                                    @if($child->permission_mode === 'inherit')
                                        <span class="badge bg-secondary">Inherited</span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-muted mb-0">No child spans</p>
                    @endif
                </div>
            </div>

            <!-- Notes -->
            <div class="card">
                <div class="card-body">
                    <h2 class="card-title h5">Notes</h2>
                    <ul class="small text-muted mb-0">
                        <li>Changing the parent span will affect all child spans with inherited permissions.</li>
                        <li>Making a span private will remove all group members.</li>
                        <li>Changing to inherited permissions will override any custom permissions.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const permissionModeInputs = document.querySelectorAll('input[name="permission_mode"]');
    const permissionsSection = document.getElementById('permissions_section');
    const parentSelect = document.getElementById('parent_id');
    const inheritInput = document.getElementById('permission_mode_inherit');
    const visibilityInputs = document.querySelectorAll('input[name="visibility"]');
    const groupPermissions = document.getElementById('group_permissions');

    function updatePermissionMode() {
        const selectedMode = document.querySelector('input[name="permission_mode"]:checked').value;
        permissionsSection.classList.toggle('d-none', selectedMode === 'inherit');
    }

    function updateParentDependent() {
        const hasParent = parentSelect.value !== '';
        inheritInput.disabled = !hasParent;
        if (!hasParent && inheritInput.checked) {
            document.getElementById('permission_mode_own').checked = true;
            updatePermissionMode();
        }
    }

    function updateGroupPermissions() {
        const selectedVisibility = document.querySelector('input[name="visibility"]:checked').value;
        groupPermissions.classList.toggle('d-none', selectedVisibility !== 'group');
    }

    permissionModeInputs.forEach(input => {
        input.addEventListener('change', updatePermissionMode);
    });

    parentSelect.addEventListener('change', updateParentDependent);

    visibilityInputs.forEach(input => {
        input.addEventListener('change', updateGroupPermissions);
    });
});
</script>
@endpush

@endsection 