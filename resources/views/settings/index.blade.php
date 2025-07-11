@extends('layouts.app')

@section('page_title')
    @php
        $breadcrumbItems = [
            [
                'text' => 'Settings',
                'url' => route('settings.index'),
                'icon' => 'gear',
                'icon_category' => 'action'
            ]
        ];
    @endphp
    
    <x-breadcrumb :items="$breadcrumbItems" />
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
</style>
@endpush

@section('content')
    <div class="container-fluid">
        <div class="row">
            <!-- Left Sidebar Menu -->
            <div class="col-md-3">
                <x-settings-nav active="overview" />
            </div>

            <!-- Main Content Area -->
            <div class="col-md-9">
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

                <!-- Quick Settings Access -->
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <div class="card h-100">
                            <div class="card-body">
                                <h6 class="card-title">
                                    <i class="bi bi-upload me-2"></i>Import Settings
                                </h6>
                                <p class="card-text text-muted">Configure how data is imported into your account.</p>
                                <a href="{{ route('settings.import') }}" class="btn btn-outline-primary btn-sm">
                                    Configure Import
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <div class="card h-100">
                            <div class="card-body">
                                <h6 class="card-title">
                                    <i class="bi bi-bell me-2"></i>Notifications
                                </h6>
                                <p class="card-text text-muted">Manage your notification preferences and settings.</p>
                                <a href="{{ route('settings.notifications') }}" class="btn btn-outline-primary btn-sm">
                                    Configure Notifications
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection 