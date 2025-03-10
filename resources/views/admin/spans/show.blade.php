@extends('layouts.app')

@section('page_title')
    {{ $span->name }}
@endsection

@section('content')
<div class="container py-4">
    <div class="row">
        <div class="col-12 d-flex justify-content-end mb-4">
            <div>
                <a href="{{ route('admin.spans.edit', $span) }}" class="btn btn-primary">Edit Span</a>
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
            <!-- Basic Information -->
            <div class="card mb-4">
                <div class="card-body">
                    <h2 class="card-title h5">Basic Information</h2>
                    <dl class="row">
                        <dt class="col-sm-3">Type</dt>
                        <dd class="col-sm-9">{{ $span->type->name }}</dd>

                        <dt class="col-sm-3">Owner</dt>
                        <dd class="col-sm-9">
                            <a href="{{ route('admin.users.show', $span->owner) }}" 
                               class="text-decoration-none">
                                {{ $span->owner->name }}
                            </a>
                        </dd>

                        <dt class="col-sm-3">Start Date</dt>
                        <dd class="col-sm-9">{{ $span->formatted_start_date }}</dd>

                        <dt class="col-sm-3">End Date</dt>
                        <dd class="col-sm-9">
                            @if($span->is_ongoing)
                                <span class="text-muted">Ongoing</span>
                            @else
                                {{ $span->formatted_end_date }}
                            @endif
                        </dd>

                        <dt class="col-sm-3">Created</dt>
                        <dd class="col-sm-9">{{ $span->created_at->format('Y-m-d H:i:s') }}</dd>

                        <dt class="col-sm-3">Last Updated</dt>
                        <dd class="col-sm-9">{{ $span->updated_at->format('Y-m-d H:i:s') }}</dd>
                    </dl>
                </div>
            </div>

            <!-- Permissions -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 class="card-title h5 mb-0">Permissions</h2>
                        <a href="{{ route('admin.spans.permissions', $span) }}" 
                           class="btn btn-sm btn-outline-primary">Manage Permissions</a>
                    </div>
                    <dl class="row mb-0">
                        <dt class="col-sm-3">Mode</dt>
                        <dd class="col-sm-9">
                            @if($span->permission_mode === 'own')
                                <span class="badge bg-primary">Own Permissions</span>
                            @else
                                <span class="badge bg-secondary">Inherited from Parent</span>
                            @endif
                        </dd>

                        <dt class="col-sm-3">Permissions</dt>
                        <dd class="col-sm-9">{{ $span->getPermissionsString() }}</dd>

                        @if($span->permission_mode === 'inherit')
                            <dt class="col-sm-3">Parent</dt>
                            <dd class="col-sm-9">
                                <a href="{{ route('admin.spans.show', $span->parent) }}" 
                                   class="text-decoration-none">
                                    {{ $span->parent->name }}
                                </a>
                            </dd>
                        @endif
                    </dl>
                </div>
            </div>

            <!-- Child Spans -->
            <div class="card mb-4">
                <div class="card-body">
                    <h2 class="card-title h5">Child Spans</h2>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Type</th>
                                    <th>Owner</th>
                                    <th>Mode</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($span->children as $child)
                                    <tr>
                                        <td>{{ $child->name }}</td>
                                        <td>{{ $child->type->name }}</td>
                                        <td>{{ $child->owner->name }}</td>
                                        <td>{{ $child->permission_mode }}</td>
                                        <td class="text-end">
                                            <a href="{{ route('admin.spans.show', $child) }}" 
                                               class="btn btn-sm btn-outline-secondary">View</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-muted text-center">No child spans</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <!-- Group Members -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 class="card-title h5 mb-0">Group Members</h2>
                        <a href="{{ route('admin.spans.access', $span) }}" 
                           class="btn btn-sm btn-outline-primary">Manage Access</a>
                    </div>
                    @if($span->groupMembers->count() > 0)
                        <ul class="list-unstyled mb-0">
                            @foreach($span->groupMembers as $member)
                                <li class="mb-2">
                                    <a href="{{ route('admin.users.show', $member) }}" 
                                       class="text-decoration-none">
                                        {{ $member->name }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-muted mb-0">No group members</p>
                    @endif
                </div>
            </div>

            <!-- Actions -->
            <div class="card border-danger">
                <div class="card-body">
                    <h2 class="card-title h5 text-danger">Danger Zone</h2>
                    <div class="d-grid gap-2">
                        <button class="btn btn-danger" disabled>
                            Delete Span
                        </button>
                    </div>
                    <p class="text-muted small mt-2 mb-0">
                        Deleting a span will also remove all its child spans.
                        This action cannot be undone.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection 