@extends('layouts.app')

@section('page_title')
    Admin Dashboard
@endsection

@section('content')
<div class="py-4">
    <div class="row">
        <!-- Overview Stats -->
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
                    <h6 class="card-subtitle mb-2 text-muted">Total Connections</h6>
                    <h2 class="card-title mb-0">{{ number_format($stats['total_connections']) }}</h2>
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
                    <h6 class="card-subtitle mb-2 text-muted">Connection Spans</h6>
                    <h2 class="card-title mb-0">{{ number_format($stats['connection_spans']) }}</h2>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Span Types -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Span Types</h5>
                    <div class="list-group">
                        @foreach($spanTypeStats as $type)
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <strong>{{ $type['name'] }}</strong>
                                    <span class="badge bg-primary rounded-pill">{{ number_format($type['count']) }}</span>
                                </div>
                                @if(count($type['subtypes']) > 0)
                                    <div class="mt-2">
                                        <small class="text-muted">Subtypes:</small>
                                        <div class="ms-3">
                                            @foreach($type['subtypes'] as $subtype => $count)
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <span>{{ $subtype }}</span>
                                                    <span class="badge bg-secondary rounded-pill">{{ number_format($count) }}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <!-- Connection Types -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Connection Types</h5>
                    <div class="list-group">
                        @foreach($connectionTypeStats as $type)
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <span>{{ $type['name'] }}</span>
                                <span class="badge bg-primary rounded-pill">{{ number_format($type['count']) }}</span>
                            </div>
                        @endforeach
                    </div>
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
                        <a href="{{ route('admin.visualizer.index') }}" class="list-group-item list-group-item-action">
                            Network Visualizer
                            <i class="bi bi-graph-up ms-2"></i>
                        </a>
                        <a href="{{ route('admin.import.musicbrainz.index') }}" class="list-group-item list-group-item-action">
                            Import from MusicBrainz
                            <i class="bi bi-music-note-beamed ms-2"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Additional Stats -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Additional Stats</h5>
                    <div class="list-group">
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            Spans with Dates
                            <span class="badge bg-info rounded-pill">{{ number_format($stats['spans_with_dates']) }}</span>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            Spans with End Dates
                            <span class="badge bg-info rounded-pill">{{ number_format($stats['spans_with_end_dates']) }}</span>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            Ongoing Spans
                            <span class="badge bg-info rounded-pill">{{ number_format($stats['ongoing_spans']) }}</span>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            Public Spans
                            <span class="badge bg-success rounded-pill">{{ number_format($stats['public_spans']) }}</span>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            Private Spans
                            <span class="badge bg-warning rounded-pill">{{ number_format($stats['private_spans']) }}</span>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            Inherited Permissions
                            <span class="badge bg-secondary rounded-pill">{{ number_format($stats['inherited_spans']) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection 