@extends('layouts.app')

@section('title')
    System Versioning Statistics - Admin
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
            'url' => route('admin.system-history.index'),
            'icon' => 'clock-history',
            'icon_category' => 'action'
        ],
        [
            'text' => 'Statistics',
            'icon' => 'graph-up',
            'icon_category' => 'action'
        ]
    ]" />
@endsection

@section('content')
<div class="container-fluid my-4">
    <!-- User Statistics -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Versioning Activity by User</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Span Versions</th>
                                    <th>Connection Versions</th>
                                    <th>Total Changes</th>
                                    <th>Percentage</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $totalChanges = $userStats->sum('total');
                                @endphp
                                @foreach($userStats->sortByDesc('total') as $userId => $stats)
                                    @php
                                        $user = App\Models\User::find($userId);
                                        $percentage = $totalChanges > 0 ? round(($stats['total'] / $totalChanges) * 100, 1) : 0;
                                    @endphp
                                    <tr>
                                                                            <td>
                                        <strong>{{ $user?->name ?? 'Unknown User' }}</strong>
                                        <br><small class="text-muted">{{ $user?->email ?? 'No email' }}</small>
                                    </td>
                                        <td>
                                            <span class="badge bg-primary">{{ number_format($stats['span_versions']) }}</span>
                                        </td>
                                        <td>
                                            <span class="badge bg-info">{{ number_format($stats['connection_versions']) }}</span>
                                        </td>
                                        <td>
                                            <strong>{{ number_format($stats['total']) }}</strong>
                                        </td>
                                        <td>
                                            <div class="progress" style="height: 20px;">
                                                <div class="progress-bar" role="progressbar" 
                                                     style="width: {{ $percentage }}%" 
                                                     aria-valuenow="{{ $percentage }}" 
                                                     aria-valuemin="0" 
                                                     aria-valuemax="100">
                                                    {{ $percentage }}%
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Most Active Entities -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Most Active Spans</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Span</th>
                                    <th>Type</th>
                                    <th>Versions</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($mostActiveSpans as $spanVersion)
                                    <tr>
                                        <td>
                                            <strong>{{ $spanVersion->span->name }}</strong>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary">{{ ucfirst($spanVersion->span->type_id) }}</span>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary">{{ $spanVersion->version_count }}</span>
                                        </td>
                                        <td>
                                            <a href="{{ route('spans.history', $spanVersion->span) }}" 
                                               class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-clock-history"></i> History
                                            </a>
                                            <a href="{{ route('spans.show', $spanVersion->span) }}" 
                                               class="btn btn-sm btn-outline-secondary">
                                                <i class="bi bi-eye"></i> View
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Most Active Connections</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Connection</th>
                                    <th>Type</th>
                                    <th>Versions</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($mostActiveConnections as $connectionVersion)
                                    <tr>
                                        <td>
                                            <strong>{{ $connectionVersion->connection->subject->name }} → {{ $connectionVersion->connection->object->name }}</strong>
                                        </td>
                                        <td>
                                            <span class="badge bg-info">{{ ucfirst($connectionVersion->connection->type_id) }}</span>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary">{{ $connectionVersion->version_count }}</span>
                                        </td>
                                        <td>
                                            @if($connectionVersion->connection->connectionSpan)
                                                <a href="{{ route('spans.history', $connectionVersion->connection->connectionSpan) }}" 
                                                   class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-clock-history"></i> History
                                                </a>
                                            @endif
                                            <a href="{{ route('spans.show', $connectionVersion->connection->subject) }}" 
                                               class="btn btn-sm btn-outline-secondary">
                                                <i class="bi bi-eye"></i> View
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Activity Over Time -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Versioning Activity Over Time</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Total Changes</th>
                                    <th>Activity Level</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $maxActivity = $activityOverTime->max();
                                @endphp
                                @foreach($activityOverTime->take(30) as $date => $count)
                                    @php
                                        $percentage = $maxActivity > 0 ? round(($count / $maxActivity) * 100, 1) : 0;
                                        $activityClass = $percentage > 80 ? 'danger' : ($percentage > 60 ? 'warning' : ($percentage > 40 ? 'info' : 'success'));
                                    @endphp
                                    <tr>
                                        <td>
                                            <strong>{{ \Carbon\Carbon::parse($date)->format('M j, Y') }}</strong>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary">{{ $count }}</span>
                                        </td>
                                        <td>
                                            <div class="progress" style="height: 20px;">
                                                <div class="progress-bar bg-{{ $activityClass }}" role="progressbar" 
                                                     style="width: {{ $percentage }}%" 
                                                     aria-valuenow="{{ $percentage }}" 
                                                     aria-valuemin="0" 
                                                     aria-valuemax="100">
                                                    {{ $percentage }}%
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Back to History -->
    <div class="row mt-4">
        <div class="col-12 text-center">
            <a href="{{ route('admin.system-history.index') }}" class="btn btn-primary">
                <i class="bi bi-arrow-left"></i> Back to System History
            </a>
        </div>
    </div>
</div>
@endsection 