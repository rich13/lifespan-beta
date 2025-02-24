@extends('layouts.app')

@section('content')
<div class="py-4">
    <div class="row">
        <div class="col-12 d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0">Admin Dashboard</h1>
        </div>
    </div>

    <div class="row">
        <!-- Stats Cards -->
        <div class="col-md-3 mb-4">
            <div class="card h-100">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2 text-muted">Total Spans</h6>
                    <h2 class="card-title mb-0">{{ number_format($stats['total_spans']) }}</h2>
                </div>
            </div>
        </div>

        <div class="col-md-3 mb-4">
            <div class="card h-100">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2 text-muted">Total Users</h6>
                    <h2 class="card-title mb-0">{{ number_format($stats['total_users']) }}</h2>
                </div>
            </div>
        </div>

        <div class="col-md-3 mb-4">
            <div class="card h-100">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2 text-muted">Public Spans</h6>
                    <h2 class="card-title mb-0">{{ number_format($stats['public_spans']) }}</h2>
                </div>
            </div>
        </div>

        <div class="col-md-3 mb-4">
            <div class="card h-100">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2 text-muted">Private Spans</h6>
                    <h2 class="card-title mb-0">{{ number_format($stats['private_spans']) }}</h2>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Quick Actions -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Quick Actions</h5>
                    <div class="list-group">
                        <a href="{{ route('admin.spans.index') }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                            Manage Spans
                            <span class="badge bg-primary rounded-pill">{{ $stats['total_spans'] }}</span>
                        </a>
                        <a href="{{ route('admin.users.index') }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                            Manage Users
                            <span class="badge bg-primary rounded-pill">{{ $stats['total_users'] }}</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Permission Stats -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Permission Stats</h5>
                    <div class="list-group">
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            Inherited Permissions
                            <span class="badge bg-secondary rounded-pill">{{ $stats['inherited_spans'] }}</span>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            Public Spans
                            <span class="badge bg-success rounded-pill">{{ $stats['public_spans'] }}</span>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            Private Spans
                            <span class="badge bg-warning rounded-pill">{{ $stats['private_spans'] }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection 