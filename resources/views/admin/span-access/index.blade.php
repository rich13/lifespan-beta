@extends('layouts.app')

@section('page_title')
    Manage Span Access
@endsection

@section('content')
<style>
    /* Fix pagination styling */
    .pagination {
        margin-bottom: 0;
    }
    .pagination .page-link {
        padding: 0.25rem 0.5rem;
        font-size: 0.875rem;
    }
    .pagination .page-item .page-link svg {
        width: 12px;
        height: 12px;
    }
</style>

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

    @if (session('error'))
        <div class="alert alert-danger">
            {{ session('error') }}
        </div>
    @endif

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title">Filters</h5>
            <form action="{{ route('admin.span-access.index') }}" method="GET" class="row g-3">
                <div class="col-md-6">
                    <label for="user_id" class="form-label">User</label>
                    <select name="user_id" id="user_id" class="form-select">
                        <option value="">All Users</option>
                        @foreach($users as $user)
                            <option value="{{ $user->id }}" {{ $userId == $user->id ? 'selected' : '' }}>
                                {{ $user->email }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="span_type" class="form-label">Span Type</label>
                    <select name="span_type" id="span_type" class="form-select">
                        <option value="">All Types</option>
                        @foreach($spanTypes as $type)
                            <option value="{{ $type->type_id }}" {{ $spanType == $type->type_id ? 'selected' : '' }}>
                                {{ $type->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                    <a href="{{ route('admin.span-access.index') }}" class="btn btn-outline-secondary">Clear Filters</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Spans Tables -->
    <div class="row">
        <!-- Private/Shared Spans -->
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header bg-secondary text-white">
                    <h5 class="card-title mb-0">Private/Shared Spans</h5>
                </div>
                <div class="card-body">
                    @if($privateSharedSpans->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Span</th>
                                        <th>Owner</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($privateSharedSpans as $span)
                                        <tr>
                                            <td>
                                                <x-spans.display.micro-card :span="$span" />
                                            </td>
                                            <td>
                                                <a href="{{ route('admin.users.show', $span->owner_id) }}">
                                                    {{ $span->owner->email }}
                                                </a>
                                            </td>
                                            <td>
                                                <div class="btn-group">
                                                    <a href="{{ route('admin.spans.access.edit', $span) }}" class="btn btn-sm btn-outline-secondary">
                                                        Edit
                                                    </a>
                                                    <form action="{{ route('admin.span-access.make-public', $span->id) }}" method="POST" class="d-inline">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-success">
                                                            Make Public
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <div class="d-flex justify-content-center mt-4">
                            {{ $privateSharedSpans->onEachSide(1)->appends(['user_id' => $userId, 'span_type' => $spanType, 'public_page' => request('public_page')])->links() }}
                        </div>
                    @else
                        <div class="alert alert-info">
                            No private or shared spans found matching your criteria.
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Public Spans -->
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header bg-success text-white">
                    <h5 class="card-title mb-0">Public Spans</h5>
                </div>
                <div class="card-body">
                    @if($publicSpans->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Span</th>
                                        <th>Owner</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($publicSpans as $span)
                                        <tr>
                                            <td>
                                                <x-spans.display.micro-card :span="$span" />
                                            </td>
                                            <td>
                                                <a href="{{ route('admin.users.show', $span->owner_id) }}">
                                                    {{ $span->owner->email }}
                                                </a>
                                            </td>
                                            <td>
                                                <div class="btn-group">
                                                    <a href="{{ route('admin.spans.access.edit', $span) }}" class="btn btn-sm btn-outline-secondary">
                                                        Edit
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <div class="d-flex justify-content-center mt-4">
                            {{ $publicSpans->onEachSide(1)->appends(['user_id' => $userId, 'span_type' => $spanType, 'private_page' => request('private_page')])->links() }}
                        </div>
                    @else
                        <div class="alert alert-info">
                            No public spans found matching your criteria.
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection 