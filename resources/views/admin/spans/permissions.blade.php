@extends('layouts.app')

@section('page_title')
    Manage Permissions: {{ $span->name }}
@endsection

@section('content')
<div class="py-4">
    <div class="row">
        <div class="col-12 d-flex justify-content-end mb-4">
            <div>
                <a href="{{ route('admin.spans.show', $span) }}" class="btn btn-outline-secondary">View Span</a>
                <a href="{{ route('admin.spans.index') }}" class="btn btn-outline-secondary">Back to List</a>
            </div>
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
            <!-- Permission Mode -->
            <div class="card mb-4">
                <div class="card-body">
                    <h2 class="card-title h5">Permission Mode</h2>
                    
                    @if($inheritedFrom)
                        <div class="alert alert-info">
                            This span inherits permissions from 
                            <a href="{{ route('admin.spans.permissions.edit', $inheritedFrom) }}">{{ $inheritedFrom->name }}</a>
                        </div>
                    @endif

                    <form action="{{ route('admin.spans.permissions.mode', $span) }}" method="POST" class="mb-3">
                        @csrf
                        @method('PUT')
                        
                        <div class="form-check mb-2">
                            <input type="radio" class="form-check-input" id="mode_own" name="mode" value="own"
                                   {{ $span->permission_mode === 'own' ? 'checked' : '' }}>
                            <label class="form-check-label" for="mode_own">
                                Own Permissions
                                <small class="d-block text-muted">This span manages its own permissions</small>
                            </label>
                        </div>

                        <div class="form-check mb-3">
                            <input type="radio" class="form-check-input" id="mode_inherit" name="mode" value="inherit"
                                   {{ $span->permission_mode === 'inherit' ? 'checked' : '' }}
                                   {{ !$span->parent_id ? 'disabled' : '' }}>
                            <label class="form-check-label" for="mode_inherit">
                                Inherit from Parent
                                <small class="d-block text-muted">
                                    @if($span->parent_id)
                                        Inherit permissions from parent span
                                    @else
                                        This span has no parent to inherit from
                                    @endif
                                </small>
                            </label>
                        </div>

                        <button type="submit" class="btn btn-primary">Update Mode</button>
                    </form>
                </div>
            </div>

            <!-- Unix-style Permissions -->
            @if($span->permission_mode === 'own')
            <div class="card mb-4">
                <div class="card-body">
                    <h2 class="card-title h5 mb-4">Permissions</h2>
                    <p class="text-muted mb-4">Current permissions: <code>{{ $permissionString }}</code></p>

                    <form action="{{ route('admin.spans.permissions.update', $span) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="row mb-4">
                            <!-- Owner Permissions -->
                            <div class="col-md-4">
                                <h6>Owner</h6>
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="owner_read" name="owner_read"
                                           {{ ($effectivePermissions & 0400) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="owner_read">Read</label>
                                </div>
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="owner_write" name="owner_write"
                                           {{ ($effectivePermissions & 0200) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="owner_write">Write</label>
                                </div>
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="owner_execute" name="owner_execute"
                                           {{ ($effectivePermissions & 0100) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="owner_execute">Execute</label>
                                </div>
                            </div>

                            <!-- Group Permissions -->
                            <div class="col-md-4">
                                <h6>Group</h6>
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="group_read" name="group_read"
                                           {{ ($effectivePermissions & 0040) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="group_read">Read</label>
                                </div>
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="group_write" name="group_write"
                                           {{ ($effectivePermissions & 0020) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="group_write">Write</label>
                                </div>
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="group_execute" name="group_execute"
                                           {{ ($effectivePermissions & 0010) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="group_execute">Execute</label>
                                </div>
                            </div>

                            <!-- Others Permissions -->
                            <div class="col-md-4">
                                <h6>Others</h6>
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="others_read" name="others_read"
                                           {{ ($effectivePermissions & 0004) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="others_read">Read</label>
                                </div>
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="others_write" name="others_write"
                                           {{ ($effectivePermissions & 0002) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="others_write">Write</label>
                                </div>
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="others_execute" name="others_execute"
                                           {{ ($effectivePermissions & 0001) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="others_execute">Execute</label>
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary">Update Permissions</button>
                    </form>
                </div>
            </div>
            @endif
        </div>

        <div class="col-md-4">
            <!-- Group Members -->
            @if($span->permission_mode === 'own')
            <div class="card">
                <div class="card-body">
                    <h2 class="card-title h5 mb-4">Group Members</h2>
                    
                    <form action="{{ route('admin.spans.permissions.update', $span) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="mb-3">
                            <select class="form-select" name="group_members[]" multiple size="10">
                                @foreach($users as $user)
                                    <option value="{{ $user->id }}" 
                                            {{ $groupMembers->contains('id', $user->id) ? 'selected' : '' }}>
                                        {{ $user->email }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-text">Hold Ctrl/Cmd to select multiple users</div>
                        </div>

                        <button type="submit" class="btn btn-primary">Update Group</button>
                    </form>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection 