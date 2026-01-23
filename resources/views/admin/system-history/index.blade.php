@extends('layouts.app')

@section('title')
    System Versioning History - Admin
@endsection

@section('page_title')
<x-breadcrumb :items="[
        [
            'text' => 'Admin',
            'url' => route('admin.dashboard'),
            'icon' => 'shield',
            'icon_category' => 'action'
        ],
        [
            'text' => 'System History',
            'icon' => 'clock-history',
            'icon_category' => 'action'
        ]
    ]" />
@endsection

@section('content')
<div class="container-fluid my-4">
    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">Total Versions</h6>
                            <h3>{{ number_format($stats['total_span_versions'] + $stats['total_connection_versions']) }}</h3>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-clock-history fs-1"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">Active Users</h6>
                            <h3>{{ $stats['users_who_made_changes'] }}</h3>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-people fs-1"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">Last 24h</h6>
                            <h3>{{ $stats['recent_activity']['last_24h'] }}</h3>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-calendar-day fs-1"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">Last 7 Days</h6>
                            <h3>{{ $stats['recent_activity']['last_7d'] }}</h3>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-calendar-week fs-1"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Filters</h5>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('admin.system-history.index') }}" class="row g-3">
                <div class="col-md-2">
                    <label for="type" class="form-label">Type</label>
                    <select name="type" id="type" class="form-select">
                        <option value="all" {{ $type === 'all' ? 'selected' : '' }}>All Changes</option>
                        <option value="spans" {{ $type === 'spans' ? 'selected' : '' }}>Span Changes</option>
                        <option value="connections" {{ $type === 'connections' ? 'selected' : '' }}>Connection Changes</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="user" class="form-label">User</label>
                    <select name="user" id="user" class="form-select">
                        <option value="">All Users</option>
                        @foreach($users as $userOption)
                            <option value="{{ $userOption->id }}" {{ $user == $userOption->id ? 'selected' : '' }}>
                                {{ $userOption->name }} ({{ $userOption->email }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="date_from" class="form-label">From Date</label>
                    <input type="date" name="date_from" id="date_from" class="form-control" value="{{ $dateFrom }}">
                </div>
                <div class="col-md-2">
                    <label for="date_to" class="form-label">To Date</label>
                    <input type="date" name="date_to" id="date_to" class="form-control" value="{{ $dateTo }}">
                </div>
                <div class="col-md-2">
                    <label for="search" class="form-label">Search</label>
                    <input type="text" name="search" id="search" class="form-control" value="{{ $search }}" placeholder="Search names...">
                </div>
                <div class="col-md-2">
                    <label for="per_page" class="form-label">Per Page</label>
                    <select name="per_page" id="per_page" class="form-select">
                        <option value="25" {{ $perPage == 25 ? 'selected' : '' }}>25</option>
                        <option value="50" {{ $perPage == 50 ? 'selected' : '' }}>50</option>
                        <option value="100" {{ $perPage == 100 ? 'selected' : '' }}>100</option>
                        <option value="200" {{ $perPage == 200 ? 'selected' : '' }}>200</option>
                    </select>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-search"></i> Apply Filters
                    </button>
                    <a href="{{ route('admin.system-history.index') }}" class="btn btn-secondary">
                        <i class="bi bi-x-circle"></i> Clear
                    </a>
                    <a href="{{ route('admin.system-history.stats') }}" class="btn btn-info">
                        <i class="bi bi-graph-up"></i> View Statistics
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Results -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">System Versioning History ({{ number_format($totalCount) }} total)</h5>
            <div class="text-muted">
                Showing {{ $allChanges->count() }} of {{ number_format($totalCount) }} changes
            </div>
        </div>
        <div class="card-body p-0">
            @if($allChanges->count() > 0)
                <div class="table-responsive">
                    <table class="table table-striped mb-0">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Entity</th>
                                <th>Version</th>
                                <th>Changed By</th>
                                <th>Summary</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($allChanges as $change)
                                <tr>
                                    <td>
                                        @if($change['type'] === 'span')
                                            <span class="badge bg-primary">
                                                <i class="bi bi-box"></i> Span
                                            </span>
                                        @else
                                            <span class="badge bg-info">
                                                <i class="bi bi-link-45deg"></i> Connection
                                            </span>
                                        @endif
                                    </td>
                                    <td>
                                        <div>
                                            <strong>{{ $change['entity_name'] }}</strong>
                                            @if($change['type'] === 'span')
                                                <br><small class="text-muted">{{ ucfirst($change['entity_type']) }}</small>
                                            @else
                                                <br><small class="text-muted">{{ ucfirst($change['entity_type']) }}</small>
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary">v{{ $change['version_number'] }}</span>
                                    </td>
                                    <td>
                                        {{ $change['changed_by']?->name ?? 'Unknown' }}
                                    </td>
                                    <td>
                                        {{ $change['change_summary'] ?? '-' }}
                                    </td>
                                    <td>
                                        {{ $change['created_at']->format('Y-m-d H:i') }}
                                    </td>
                                    <td>
                                        @if($change['type'] === 'span')
                                            <a href="{{ route('spans.history', [$change['entity'], $change['version_number']]) }}" 
                                               class="btn btn-sm btn-outline-primary" title="View version">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="{{ route('spans.show', $change['entity']) }}" 
                                               class="btn btn-sm btn-outline-secondary" title="View span">
                                                <i class="bi bi-box"></i>
                                            </a>
                                        @else
                                            @if($change['entity']->connectionSpan)
                                                <a href="{{ route('spans.history', [$change['entity']->connectionSpan, $change['version_number']]) }}" 
                                                   class="btn btn-sm btn-outline-primary" title="View version">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                            @endif
                                            <a href="{{ route('spans.show', $change['entity']->subject) }}" 
                                               class="btn btn-sm btn-outline-secondary" title="View subject">
                                                <i class="bi bi-person"></i>
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                @if($totalCount > $perPage)
                    <div class="card-footer">
                        <nav aria-label="System history pagination">
                            <ul class="pagination justify-content-center mb-0">
                                @php
                                    $currentPage = request()->get('page', 1);
                                    $totalPages = ceil($totalCount / $perPage);
                                    $startPage = max(1, $currentPage - 2);
                                    $endPage = min($totalPages, $currentPage + 2);
                                @endphp

                                @if($currentPage > 1)
                                    <li class="page-item">
                                        <a class="page-link" href="{{ request()->fullUrlWithQuery(['page' => $currentPage - 1]) }}">
                                            <i class="bi bi-chevron-left"></i>
                                        </a>
                                    </li>
                                @endif

                                @for($i = $startPage; $i <= $endPage; $i++)
                                    <li class="page-item {{ $i == $currentPage ? 'active' : '' }}">
                                        <a class="page-link" href="{{ request()->fullUrlWithQuery(['page' => $i]) }}">
                                            {{ $i }}
                                        </a>
                                    </li>
                                @endfor

                                @if($currentPage < $totalPages)
                                    <li class="page-item">
                                        <a class="page-link" href="{{ request()->fullUrlWithQuery(['page' => $currentPage + 1]) }}">
                                            <i class="bi bi-chevron-right"></i>
                                        </a>
                                    </li>
                                @endif
                            </ul>
                        </nav>
                    </div>
                @endif
            @else
                <div class="text-center py-5">
                    <i class="bi bi-clock-history fs-1 text-muted"></i>
                    <h5 class="mt-3">No changes found</h5>
                    <p class="text-muted">Try adjusting your filters to see more results.</p>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection 