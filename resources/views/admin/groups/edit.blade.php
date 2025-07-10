@extends('layouts.app')

@section('title', 'Edit ' . $group->name)

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>Edit {{ $group->name }}</h1>
                <div>
                    <a href="{{ route('admin.groups.show', $group) }}" class="btn btn-secondary me-2">
                        <i class="bi bi-eye"></i> View Group
                    </a>
                    <a href="{{ route('admin.groups.index') }}" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Groups
                    </a>
                </div>
            </div>

            @if(session('status'))
                <div class="alert alert-success">
                    {{ session('status') }}
                </div>
            @endif

            <div class="row">
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Group Details</h5>
                        </div>
                        <div class="card-body">
                            <form action="{{ route('admin.groups.update', $group) }}" method="POST">
                                @csrf
                                @method('PUT')

                                <div class="mb-3">
                                    <label for="name" class="form-label">Group Name *</label>
                                    <input type="text" 
                                           class="form-control @error('name') is-invalid @enderror" 
                                           id="name" 
                                           name="name" 
                                           value="{{ old('name', $group->name) }}" 
                                           required>
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label for="description" class="form-label">Description</label>
                                    <textarea class="form-control @error('description') is-invalid @enderror" 
                                              id="description" 
                                              name="description" 
                                              rows="3">{{ old('description', $group->description) }}</textarea>
                                    @error('description')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label for="owner_id" class="form-label">Group Owner *</label>
                                    <select class="form-select @error('owner_id') is-invalid @enderror" 
                                            id="owner_id" 
                                            name="owner_id" 
                                            required>
                                        <option value="">Select an owner...</option>
                                        @foreach($users as $user)
                                            <option value="{{ $user->id }}" 
                                                    {{ old('owner_id', $group->owner_id) == $user->id ? 'selected' : '' }}>
                                                {{ $user->name }} ({{ $user->email }})
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('owner_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="d-flex justify-content-end">
                                    <a href="{{ route('admin.groups.show', $group) }}" class="btn btn-secondary me-2">Cancel</a>
                                    <button type="submit" class="btn btn-primary">Update Group</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Add Member</h5>
                        </div>
                        <div class="card-body">
                            <form action="{{ route('admin.groups.add-member', $group) }}" method="POST">
                                @csrf
                                <div class="row">
                                    <div class="col-8">
                                        <select name="user_id" class="form-select" required>
                                            <option value="">Select a user...</option>
                                            @foreach($users as $user)
                                                @if(!$group->hasMember($user))
                                                    <option value="{{ $user->id }}">
                                                        {{ $user->name }} ({{ $user->email }})
                                                    </option>
                                                @endif
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-4">
                                        <button type="submit" class="btn btn-primary w-100">Add Member</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Group Members</h5>
                        </div>
                        <div class="card-body">
                            @if($group->users->count() > 0)
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Name</th>
                                                <th>Email</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($group->users as $user)
                                                <tr>
                                                    <td>{{ $user->name }}</td>
                                                    <td>{{ $user->email }}</td>
                                                    <td>
                                                        @if($user->id !== $group->owner_id)
                                                            <form action="{{ route('admin.groups.remove-member', [$group, $user]) }}" 
                                                                  method="POST" 
                                                                  class="d-inline"
                                                                  onsubmit="return confirm('Are you sure you want to remove {{ $user->name }} from this group?')">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                                    <i class="bi bi-person-x"></i> Remove
                                                                </button>
                                                            </form>
                                                        @else
                                                            <span class="badge bg-primary">Owner</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <p class="text-muted">No members in this group.</p>
                            @endif
                        </div>
                    </div>

                    @if($group->spanPermissions->count() > 0)
                        <div class="card mt-4">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Group Permissions</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Span</th>
                                                <th>Permission</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($group->spanPermissions as $permission)
                                                <tr>
                                                    <td>
                                                        <a href="{{ route('spans.show', $permission->span) }}" 
                                                           class="text-decoration-none">
                                                            {{ $permission->span->name }}
                                                        </a>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-{{ $permission->permission_type === 'edit' ? 'warning' : 'info' }}">
                                                            {{ ucfirst($permission->permission_type) }}
                                                        </span>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection 