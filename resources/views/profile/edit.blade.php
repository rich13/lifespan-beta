@extends('layouts.app')

@section('page_title')
    Your Account
    @if(Auth::user()->is_admin)
        <span class="badge bg-primary ms-2">Administrator</span>
    @endif
@endsection

@push('styles')
<style>
    .stats-card {
        transition: transform 0.2s ease-in-out;
    }
    
    .stats-card:hover {
        transform: translateY(-2px);
    }
    
    .stat-number {
        font-size: 2rem;
        font-weight: 700;
        line-height: 1;
    }
    
    .stat-label {
        font-size: 0.875rem;
        color: #6c757d;
        font-weight: 500;
    }
    
    .activity-item {
        border-left: 3px solid #e9ecef;
        padding-left: 1rem;
        margin-bottom: 1rem;
    }
    
    .activity-item:last-child {
        margin-bottom: 0;
    }
    
    .activity-item:hover {
        border-left-color: #0d6efd;
    }
    
    .profile-header {
        background: var(--bs-primary);
        color: white;
        border-radius: 0.5rem;
        padding: 2rem;
        margin-bottom: 2rem;
    }
    
    .profile-avatar {
        width: 80px;
        height: 80px;
        background: rgba(255, 255, 255, 0.2);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2rem;
        font-weight: 700;
        margin-bottom: 1rem;
    }
    
    .quick-actions .btn {
        margin-bottom: 0.5rem;
    }
    
    .quick-actions .btn:last-child {
        margin-bottom: 0;
    }
</style>
@endpush

@section('content')
<div class="container-fluid py-4">
    

    <div class="row">
        <!-- Left Column: Main Content -->
        <div class="col-lg-8">
            <!-- Statistics Overview -->
            <div class="row mb-4">
                <div class="col-md-3 mb-3">
                    <div class="card stats-card h-100">
                        <div class="card-body text-center">
                            <div class="stat-number text-primary">{{ $stats['total_spans_created'] }}</div>
                            <div class="stat-label">Spans Created</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card stats-card h-100">
                        <div class="card-body text-center">
                            <div class="stat-number text-success">{{ $stats['public_spans'] }}</div>
                            <div class="stat-label">Public Spans</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card stats-card h-100">
                        <div class="card-body text-center">
                            <div class="stat-number text-info">{{ $stats['private_spans'] }}</div>
                            <div class="stat-label">Private Spans</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card stats-card h-100">
                        <div class="card-body text-center">
                            <div class="stat-number text-warning">{{ $stats['shared_spans'] }}</div>
                            <div class="stat-label">Shared Spans</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Connection Statistics (if user has personal span) -->
            @if(!empty($connectionStats))
            <div class="row mb-4">
                <div class="col-md-4 mb-3">
                    <div class="card stats-card h-100">
                        <div class="card-body text-center">
                            <div class="stat-number text-primary">{{ $connectionStats['total_connections'] }}</div>
                            <div class="stat-label">Total Connections</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card stats-card h-100">
                        <div class="card-body text-center">
                            <div class="stat-number text-success">{{ $connectionStats['temporal_connections'] }}</div>
                            <div class="stat-label">Temporal Connections</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card stats-card h-100">
                        <div class="card-body text-center">
                            <div class="stat-number text-info">{{ $connectionStats['connections_as_subject'] }}</div>
                            <div class="stat-label">As Subject</div>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            <!-- Recent Activity -->
            <div class="row">
                <div class="col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-clock-history me-2"></i>Recent Spans
                            </h5>
                        </div>
                        <div class="card-body">
                            @if($recentSpans->count() > 0)
                                @foreach($recentSpans as $span)
                                    <div class="activity-item">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <a href="{{ route('spans.show', $span) }}" class="text-decoration-none">
                                                    <strong>{{ $span->name }}</strong>
                                                </a>
                                                <div class="small text-muted">
                                                    <span class="badge bg-{{ $span->type_id }}">{{ ucfirst($span->type_id) }}</span>
                                                    {{ $span->updated_at->diffForHumans() }}
                                                </div>
                                            </div>
                                            <span class="badge bg-{{ $span->access_level === 'public' ? 'success' : ($span->access_level === 'private' ? 'danger' : 'info') }}">
                                                {{ ucfirst($span->access_level) }}
                                            </span>
                                        </div>
                                    </div>
                                @endforeach
                            @else
                                <p class="text-muted mb-0">No spans created yet.</p>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-diagram-3 me-2"></i>Recent Connections
                            </h5>
                        </div>
                        <div class="card-body">
                            @if($recentConnections->count() > 0)
                                @foreach($recentConnections as $connection)
                                    <div class="activity-item">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <strong>{{ $connection->type->forward_predicate }}</strong>
                                                <div class="small text-muted">
                                                    <a href="{{ route('spans.show', $connection->child) }}" class="text-decoration-none">
                                                        {{ $connection->child->name }}
                                                    </a>
                                                    • {{ $connection->created_at->diffForHumans() }}
                                                </div>
                                            </div>
                                            @if($connection->connectionSpan && $connection->connectionSpan->start_year)
                                                <span class="badge bg-secondary">
                                                    {{ $connection->connectionSpan->start_year }}
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            @else
                                <p class="text-muted mb-0">No connections yet.</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Profile Settings -->
            <div class="row">
                <div class="col-md-6 mb-4">
                    <div class="card bg-secondary-subtle">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-person-gear me-2"></i>You
                            </h5>
                        </div>
                        <div class="card-body">
                            @include('profile.partials.update-profile-information-form')
                        </div>
                    </div>
                </div>
            

            <!-- Security Settings -->
            
                <div class="col-md-6 mb-4">
                    <div class="card bg-secondary-subtle">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-shield-lock me-2"></i>Password
                            </h5>
                        </div>
                        <div class="card-body">
                            @include('profile.partials.update-password-form')
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Sidebar -->
        <div class="col-lg-4">
            <!-- Profile Header -->
    <div class="profile-header">
        <div class="row align-items-center">
            <div class="col-md-4">
                <div class="profile-avatar mx-auto mx-md-0">
                    {{ strtoupper(substr($user->name, 0, 1)) }}
                </div>
                <h1 class="h3 mb-2">{{ $user->name }}</h1>
                <p class="mb-0 opacity-75">{{ $user->email }}</p>
                @if($user->personalSpan && $user->personalSpan->start_year)
                    <p class="mb-0 opacity-75">
                        <i class="bi bi-calendar-event me-1"></i>
                        Born {{ $user->personalSpan->formatted_start_date }}
                    </p>
                @endif
            </div>
        </div>
    </div>
            <!-- Quick Actions -->
            <!-- <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-lightning me-2"></i>Quick Actions
                    </h5>
                </div>
                <div class="card-body quick-actions">
                    <div class="d-grid gap-2">
                        <a href="{{ route('spans.create') }}" class="btn btn-primary">
                            <i class="bi bi-plus-circle me-2"></i>Create New Span
                        </a>
                        @if($user->personalSpan)
                            <a href="{{ route('spans.show', $user->personalSpan) }}" class="btn btn-outline-primary">
                                <i class="bi bi-person me-2"></i>View Personal Span
                            </a>
                            <a href="{{ route('spans.edit', $user->personalSpan) }}" class="btn btn-outline-secondary">
                                <i class="bi bi-pencil me-2"></i>Edit Personal Span
                            </a>
                        @endif
                        <a href="{{ route('spans.index') }}" class="btn btn-outline-info">
                            <i class="bi bi-collection me-2"></i>Browse All Spans
                        </a>
                        @if($user->is_admin)
                            <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-warning">
                                <i class="bi bi-gear me-2"></i>Admin Dashboard
                            </a>
                        @endif
                    </div>
                </div>
            </div> -->

            <!-- Account Information -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-info-circle me-2"></i>Account Information
                    </h5>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Member since</dt>
                        <dd class="col-sm-8">{{ $accountStats['member_since'] }}</dd>
                        
                        <dt class="col-sm-4">Last active</dt>
                        <dd class="col-sm-8">{{ $accountStats['last_active'] }}</dd>
                        
                        <dt class="col-sm-4">Email status</dt>
                        <dd class="col-sm-8">
                            @if($user->email_verified_at)
                                <span class="badge bg-success">Verified</span>
                            @else
                                <span class="badge bg-warning">Not verified</span>
                            @endif
                        </dd>
                        
                        @if($user->personalSpan && $user->personalSpan->start_year)
                            <dt class="col-sm-4">Birth date</dt>
                            <dd class="col-sm-8">{{ $user->personalSpan->formatted_start_date }}</dd>
                        @endif
                    </dl>
                </div>
            </div>

            <!-- Personal Span Summary -->
            <!-- @if($user->personalSpan)
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-person-badge me-2"></i>Personal Span
                    </h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <strong>{{ $user->personalSpan->name }}</strong>
                        <div class="small text-muted">
                            <span class="badge bg-person">{{ ucfirst($user->personalSpan->type_id) }}</span>
                            <span class="badge bg-{{ $user->personalSpan->access_level === 'public' ? 'success' : 'danger' }}">
                                {{ ucfirst($user->personalSpan->access_level) }}
                            </span>
                        </div>
                    </div>
                    
                    @if($user->personalSpan->description)
                        <p class="small text-muted mb-3">{{ Str::limit($user->personalSpan->description, 100) }}</p>
                    @endif
                    
                    <div class="d-grid gap-2">
                        <a href="{{ route('spans.show', $user->personalSpan) }}" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-eye me-1"></i>View Details
                        </a>
                        <a href="{{ route('spans.edit', $user->personalSpan) }}" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-pencil me-1"></i>Edit
                        </a>
                    </div>
                </div>
            </div>
            @endif -->

            <!-- Danger Zone -->
            @if(Auth::user()->is_admin)
            <div class="card border-danger">
                <div class="card-header bg-danger text-white">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-exclamation-triangle me-2"></i>Danger Zone
                    </h5>
                </div>
                <div class="card-body">
                    @include('profile.partials.delete-user-form')
                </div>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
