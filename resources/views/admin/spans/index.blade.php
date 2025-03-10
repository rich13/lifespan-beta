@extends('layouts.app')

@section('page_title')
    Spans Administration
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

    <div class="card">
        <div class="card-body">
            <!-- Search and Filters -->
            <form action="{{ route('admin.spans.index') }}" method="GET" class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="input-group">
                        <input type="text" class="form-control" placeholder="Search spans..." 
                               name="search" value="{{ request('search') }}">
                        <button class="btn btn-outline-secondary" type="submit">Search</button>
                    </div>
                </div>
                <div class="col-md-auto">
                    <select class="form-select" name="type" onchange="this.form.submit()">
                        <option value="">All Types</option>
                        @foreach($types as $type)
                            <option value="{{ $type->type_id }}" 
                                    {{ request('type') == $type->type_id ? 'selected' : '' }}>
                                {{ $type->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-auto">
                    <select class="form-select" name="permission_mode" onchange="this.form.submit()">
                        <option value="">All Permission Modes</option>
                        <option value="own" {{ request('permission_mode') === 'own' ? 'selected' : '' }}>
                            Own Permissions
                        </option>
                        <option value="inherit" {{ request('permission_mode') === 'inherit' ? 'selected' : '' }}>
                            Inherited Permissions
                        </option>
                    </select>
                </div>
                <div class="col-md-auto">
                    <select class="form-select" name="visibility" onchange="this.form.submit()">
                        <option value="">All Visibility</option>
                        <option value="public" {{ request('visibility') === 'public' ? 'selected' : '' }}>
                            Public
                        </option>
                        <option value="private" {{ request('visibility') === 'private' ? 'selected' : '' }}>
                            Private
                        </option>
                        <option value="group" {{ request('visibility') === 'group' ? 'selected' : '' }}>
                            Group Access
                        </option>
                    </select>
                </div>
                <div class="col-md-auto">
                    <select class="form-select" name="state" onchange="this.form.submit()">
                        <option value="">All States</option>
                        <option value="placeholder" {{ request('state') === 'placeholder' ? 'selected' : '' }}>
                            Placeholder
                        </option>
                        <option value="draft" {{ request('state') === 'draft' ? 'selected' : '' }}>
                            Draft
                        </option>
                        <option value="complete" {{ request('state') === 'complete' ? 'selected' : '' }}>
                            Complete
                        </option>
                    </select>
                </div>
            </form>

            <!-- Spans Table -->
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Owner</th>
                            <th>Permissions</th>
                            <th>Mode</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>State</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($spans as $span)
                            <tr>
                                <td>
                                    <x-spans.display.micro-card :span="$span" />
                                    @if($span->type_id === 'person' && $span->is_personal_span)
                                        <span class="badge bg-info ms-1">Personal</span>
                                    @endif
                                </td>
                                <td>{{ $span->type->name }}</td>
                                <td>
                                    <a href="{{ route('admin.users.show', $span->owner) }}"
                                        class="text-decoration-none">
                                        {{ $span->owner->name }}
                                    </a>
                                </td>
                                <td>
                                    @if($span->isPublic())
                                        <span class="badge bg-success">Public</span>
                                    @elseif($span->isPrivate())
                                        <span class="badge bg-danger">Private</span>
                                    @else
                                        <span class="badge bg-info">Group</span>
                                    @endif
                                </td>
                                <td>
                                    @if($span->permission_mode === 'own')
                                        <span class="badge bg-primary">Own</span>
                                    @else
                                        <span class="badge bg-secondary">Inherited</span>
                                    @endif
                                </td>
                                <td>{{ $span->formatted_start_date }}</td>
                                <td>{{ $span->formatted_end_date }}</td>
                                <td>
                                    <span class="badge bg-{{ $span->state === 'complete' ? 'success' : ($span->state === 'draft' ? 'warning' : 'secondary') }}">
                                        {{ ucfirst($span->state) }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('spans.edit', $span) }}" 
                                       class="btn btn-sm btn-outline-primary">Edit</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center text-muted py-4">
                                    No spans found matching your criteria
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="d-flex justify-content-between align-items-center">
                <div class="text-muted small">
                    Showing {{ $spans->firstItem() ?? 0 }} to {{ $spans->lastItem() ?? 0 }} 
                    of {{ $spans->total() }} spans
                </div>
                {{ $spans->links() }}
            </div>
        </div>
    </div>
</div>
@endsection 