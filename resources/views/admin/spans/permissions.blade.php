@extends('layouts.app')

@section('title', 'Permissions for ' . $span->name)

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>Permissions for {{ $span->name }}</h1>
                <div>
                    <a href="{{ route('spans.show', $span) }}" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Span
                    </a>
                </div>
            </div>

            @if(session('status'))
                <div class="alert alert-success">
                    {{ session('status') }}
                </div>
            @endif

            <!-- Span Information Card -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Span Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <dl class="row">
                                        <dt class="col-sm-4">Name:</dt>
                                        <dd class="col-sm-8">{{ $span->name }}</dd>

                                        <dt class="col-sm-4">Type:</dt>
                                        <dd class="col-sm-8">{{ $span->type->name }}</dd>

                                        <dt class="col-sm-4">Owner:</dt>
                                        <dd class="col-sm-8">{{ $span->owner->personalSpan ? $span->owner->personalSpan->name : $span->owner->email }}</dd>
                                    </dl>
                                </div>
                                <div class="col-md-6">
                                    <dl class="row">
                                        <dt class="col-sm-4">Access Level:</dt>
                                        <dd class="col-sm-8">
                                            <span class="badge bg-{{ $span->access_level === 'public' ? 'success' : ($span->access_level === 'shared' ? 'warning' : 'secondary') }}">
                                                {{ ucfirst($span->access_level) }}
                                            </span>
                                        </dd>

                                        <dt class="col-sm-4">Current Permissions:</dt>
                                        <dd class="col-sm-8">
                                            <span class="badge bg-info">{{ $userPermissions->count() }} user permissions</span>
                                            <span class="badge bg-warning">{{ $groupPermissions->count() }} group permissions</span>
                                        </dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Grant Permissions Section -->
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Grant User Permission</h5>
                        </div>
                        <div class="card-body">
                            <form action="{{ route('admin.spans.permissions.grant-user', $span) }}" method="POST">
                                @csrf
                                <div class="row">
                                    <div class="col-6">
                                        <select name="user_id" class="form-select" required>
                                            <option value="">Select a user...</option>
                                            @foreach($users as $user)
                                                <option value="{{ $user->id }}">
                                                    {{ $user->name }} ({{ $user->email }})
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-4">
                                        <select name="permission_type" class="form-select" required>
                                            <option value="">Permission...</option>
                                            <option value="view">View</option>
                                            <option value="edit">Edit</option>
                                        </select>
                                    </div>
                                    <div class="col-2">
                                        <button type="submit" class="btn btn-primary w-100">Grant</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Grant Group Permission</h5>
                        </div>
                        <div class="card-body">
                            <form action="{{ route('admin.spans.permissions.grant-group', $span) }}" method="POST">
                                @csrf
                                <div class="row">
                                    <div class="col-6">
                                        <select name="group_id" class="form-select" required>
                                            <option value="">Select a group...</option>
                                            @foreach($groups as $group)
                                                <option value="{{ $group->id }}">
                                                    {{ $group->name }} ({{ $group->users->count() }} members)
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-4">
                                        <select name="permission_type" class="form-select" required>
                                            <option value="">Permission...</option>
                                            <option value="view">View</option>
                                            <option value="edit">Edit</option>
                                        </select>
                                    </div>
                                    <div class="col-2">
                                        <button type="submit" class="btn btn-primary w-100">Grant</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Current Permissions Section -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Current Permissions</h5>
                        </div>
                        <div class="card-body">
                            @if($userPermissions->count() > 0 || $groupPermissions->count() > 0)
                                @if($userPermissions->count() > 0)
                                    <h6>User Permissions</h6>
                                    <div class="table-responsive mb-3">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>User</th>
                                                    <th>Permission</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($userPermissions as $permission)
                                                    <tr>
                                                        <td>{{ $permission->user->name }}</td>
                                                        <td>
                                                            <span class="badge bg-{{ $permission->permission_type === 'edit' ? 'warning' : 'info' }}">
                                                                {{ ucfirst($permission->permission_type) }}
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <form action="{{ route('admin.spans.permissions.revoke-user', [$span, $permission->user, $permission->permission_type]) }}" 
                                                                  method="POST" 
                                                                  class="d-inline"
                                                                  onsubmit="return confirm('Are you sure you want to revoke this permission?')">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                                    <i class="bi bi-x-circle"></i>
                                                                </button>
                                                            </form>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @endif

                                @if($groupPermissions->count() > 0)
                                    <h6>Group Permissions</h6>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Group</th>
                                                    <th>Permission</th>
                                                    <th>Members</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($groupPermissions as $permission)
                                                    <tr>
                                                        <td>
                                                            <a href="{{ route('admin.groups.show', $permission->group) }}" 
                                                               class="text-decoration-none">
                                                                {{ $permission->group->name }}
                                                            </a>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-{{ $permission->permission_type === 'edit' ? 'warning' : 'info' }}">
                                                                {{ ucfirst($permission->permission_type) }}
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <small class="text-muted">
                                                                {{ $permission->group->users->count() }} members
                                                            </small>
                                                        </td>
                                                        <td>
                                                            <form action="{{ route('admin.spans.permissions.revoke-group', [$span, $permission->group, $permission->permission_type]) }}" 
                                                                  method="POST" 
                                                                  class="d-inline"
                                                                  onsubmit="return confirm('Are you sure you want to revoke this permission?')">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                                    <i class="bi bi-x-circle"></i>
                                                                </button>
                                                            </form>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @endif
                            @else
                                <p class="text-muted">No permissions granted for this span.</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Groups This Span Is In -->
            @if($groupPermissions->count() > 0)
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Groups This Span Is In</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                @foreach($groupPermissions as $permission)
                                    <div class="col-md-6 mb-3">
                                        <div class="card border">
                                            <div class="card-body">
                                                <h6 class="card-title">
                                                    <a href="{{ route('admin.groups.show', $permission->group) }}" 
                                                       class="text-decoration-none">
                                                        {{ $permission->group->name }}
                                                    </a>
                                                </h6>
                                                <p class="card-text text-muted small">
                                                    {{ $permission->group->description ?: 'No description' }}
                                                </p>
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <span class="badge bg-{{ $permission->permission_type === 'edit' ? 'warning' : 'info' }}">
                                                        {{ ucfirst($permission->permission_type) }} permission
                                                    </span>
                                                    <small class="text-muted">
                                                        {{ $permission->group->users->count() }} members
                                                    </small>
                                                </div>
                                                @if($permission->group->users->count() > 0)
                                                    <div class="mt-2">
                                                        <small class="text-muted">Members:</small>
                                                        <div class="mt-1">
                                                            @foreach($permission->group->users->take(5) as $member)
                                                                <span class="badge bg-light text-dark me-1">
                                                                    {{ $member->personalSpan ? $member->personalSpan->name : $member->email }}
                                                                </span>
                                                            @endforeach
                                                            @if($permission->group->users->count() > 5)
                                                                <span class="badge bg-light text-dark">
                                                                    +{{ $permission->group->users->count() - 5 }} more
                                                                </span>
                                                            @endif
                                                        </div>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection 