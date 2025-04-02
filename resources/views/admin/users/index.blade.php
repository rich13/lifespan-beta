@extends('layouts.app')

@section('page_title')
    Users
@endsection

@section('content')
<div class="py-4">
    <div class="row">
        <div class="col-12 d-flex justify-content-between align-items-center mb-4">
            <div>
                <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary">Back to Dashboard</a>
            </div>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success">
            {{ session('status') }}
        </div>
    @endif

    @if (session('new_codes'))
        <div class="alert alert-info">
            <h5>New Invitation Codes Generated:</h5>
            <div class="mt-2">
                @foreach (session('new_codes') as $code)
                    <code class="me-2">{{ $code }}</code>
                @endforeach
            </div>
        </div>
    @endif

    <div class="card mb-4">
        <div class="card-body">
            <!-- Search and Filters -->
            <form action="{{ route('admin.users.index') }}" method="GET" class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="input-group">
                        <input type="text" class="form-control" placeholder="Search users..." 
                               name="search" value="{{ request('search') }}">
                        <button class="btn btn-outline-secondary" type="submit">Search</button>
                    </div>
                </div>
                <div class="col-md-auto">
                    <select class="form-select" name="role" onchange="this.form.submit()">
                        <option value="">All Roles</option>
                        <option value="admin" {{ request('role') === 'admin' ? 'selected' : '' }}>
                            Administrators
                        </option>
                        <option value="user" {{ request('role') === 'user' ? 'selected' : '' }}>
                            Regular Users
                        </option>
                    </select>
                </div>
                <div class="col-md-auto">
                    <select class="form-select" name="verified" onchange="this.form.submit()">
                        <option value="">All Verification Status</option>
                        <option value="1" {{ request('verified') === '1' ? 'selected' : '' }}>
                            Verified
                        </option>
                        <option value="0" {{ request('verified') === '0' ? 'selected' : '' }}>
                            Not Verified
                        </option>
                    </select>
                </div>
            </form>

            <!-- Users Table -->
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Verification</th>
                            <th>Joined</th>
                            <th>Spans</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($users as $user)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.users.show', $user) }}" class="text-decoration-none">
                                        {{ $user->name }}
                                    </a>
                                </td>
                                <td>{{ $user->email }}</td>
                                <td>
                                    @if($user->is_admin)
                                        <span class="badge bg-primary">Administrator</span>
                                    @else
                                        <span class="badge bg-secondary">User</span>
                                    @endif
                                </td>
                                <td>
                                    @if($user->email_verified_at)
                                        <span class="text-success">Verified</span>
                                    @else
                                        <span class="text-danger">Not Verified</span>
                                    @endif
                                </td>
                                <td>{{ $user->created_at->format('Y-m-d') }}</td>
                                <td>
                                    <span class="badge bg-secondary">
                                        {{ $user->ownedSpans->count() }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('admin.users.edit', $user) }}" 
                                       class="btn btn-sm btn-outline-primary">Edit</a>
                                    <a href="{{ route('admin.users.show', $user) }}" 
                                       class="btn btn-sm btn-outline-secondary">View</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    No users found matching your criteria
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="d-flex justify-content-between align-items-center">
                <div class="text-muted small">
                    Showing {{ $users->firstItem() ?? 0 }} to {{ $users->lastItem() ?? 0 }} 
                    of {{ $users->total() }} users
                </div>
                {{ $users->links() }}
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <h5 class="card-title">Invitation Code Management</h5>
            <div class="row align-items-center mb-4">
                <div class="col-md-6">
                    <p class="mb-0">
                        <strong>Available Codes:</strong> {{ $unusedCodes }}<br>
                        <strong>Used Codes:</strong> {{ $usedCodes }}
                    </p>
                </div>
                <div class="col-md-6 text-md-end">
                    <form action="{{ route('admin.users.generate-invitation-codes') }}" method="POST" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-primary">
                            Generate 10 New Codes
                        </button>
                    </form>
                    <form action="{{ route('admin.users.delete-all-invitation-codes') }}" method="POST" class="d-inline ms-2" onsubmit="return confirm('Are you sure you want to delete all invitation codes? This action cannot be undone.');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger">
                            Delete All Codes
                        </button>
                    </form>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Status</th>
                            <th>Used By</th>
                            <th>Used At</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($invitationCodes as $code)
                            <tr>
                                <td><code>{{ $code->code }}</code></td>
                                <td>
                                    @if($code->used)
                                        <span class="badge bg-secondary">Used</span>
                                    @else
                                        <span class="badge bg-success">Available</span>
                                    @endif
                                </td>
                                <td>{{ $code->used_by ?? '-' }}</td>
                                <td>{{ $code->used_at ? $code->used_at->format('Y-m-d H:i') : '-' }}</td>
                                <td>{{ $code->created_at->format('Y-m-d H:i') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted">
                                    No invitation codes found
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection 