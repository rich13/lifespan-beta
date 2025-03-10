@extends('layouts.app')

@section('content')
<div class="py-4">
    <div class="row">
        <div class="col-12 d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0">Manage Connections</h1>
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

    @if (session('error'))
        <div class="alert alert-danger">
            {{ session('error') }}
        </div>
    @endif

    <div class="card">
        <div class="card-body">
            <!-- Search and Filters -->
            <form action="{{ route('admin.connections.index') }}" method="GET" class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="input-group">
                        <input type="text" class="form-control" placeholder="Search connections..." 
                               name="search" value="{{ request('search') }}">
                        <button class="btn btn-outline-secondary" type="submit">Search</button>
                    </div>
                </div>
                <div class="col-md-auto">
                    <select class="form-select" name="type" onchange="this.form.submit()">
                        <option value="">All Types</option>
                        @foreach($types as $type)
                            <option value="{{ $type->type }}" 
                                    {{ request('type') == $type->type ? 'selected' : '' }}>
                                {{ $type->forward_predicate }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </form>

            <!-- Connections Table -->
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Connection</th>
                            <th>Connection Span</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Created</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($connections as $connection)
                            <tr>
                                <td>
                                    <a href="{{ route('spans.show', $connection->parent) }}" 
                                       class="text-decoration-none">
                                        {{ $connection->parent->name }}
                                    </a>
                                    <span class="text-muted">
                                        {{ $connection->type->forward_predicate }}
                                    </span>
                                    <a href="{{ route('spans.show', $connection->child) }}" 
                                       class="text-decoration-none">
                                        {{ $connection->child->name }}
                                    </a>
                                </td>
                                <td>
                                    @if($connection->connectionSpan)
                                        <a href="{{ route('spans.show', $connection->connectionSpan) }}" 
                                           class="text-decoration-none">
                                            {{ $connection->connectionSpan->name }}
                                        </a>
                                    @else
                                        <span class="text-muted">Direct Connection</span>
                                    @endif
                                </td>
                                <td>{{ $connection->formatted_start_date }}</td>
                                <td>{{ $connection->formatted_end_date }}</td>
                                <td>{{ $connection->created_at->format('Y-m-d') }}</td>
                                <td class="text-end">
                                    <a href="{{ route('admin.connections.edit', $connection) }}" 
                                       class="btn btn-sm btn-outline-primary">Edit</a>
                                    <form action="{{ route('admin.connections.destroy', $connection) }}" 
                                          method="POST" 
                                          class="d-inline"
                                          onsubmit="return confirm('Are you sure you want to delete this connection?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    No connections found matching your criteria
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="d-flex justify-content-between align-items-center">
                <div class="text-muted small">
                    Showing {{ $connections->firstItem() ?? 0 }} to {{ $connections->lastItem() ?? 0 }} 
                    of {{ $connections->total() }} connections
                </div>
                {{ $connections->links() }}
            </div>
        </div>
    </div>
</div>
@endsection 