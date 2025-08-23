@extends('layouts.app')

@section('page_title')
    Admin Dashboard
@endsection

@section('content')
<div class="py-4">
    <!-- Overview Stats Row -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card bg-primary text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">Total Spans</h6>
                            <h2 class="mb-0">{{ number_format($stats['total_spans']) }}</h2>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-box fs-1"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card bg-success text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">Total Connections</h6>
                            <h2 class="mb-0">{{ number_format($stats['total_connections']) }}</h2>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-arrow-left-right fs-1"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card bg-info text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">Total Users</h6>
                            <h2 class="mb-0">{{ number_format($stats['total_users']) }}</h2>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-people fs-1"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card bg-warning text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">Connection Spans</h6>
                            <h2 class="mb-0">{{ number_format($stats['connection_spans']) }}</h2>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-link-45deg fs-1"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Admin Sections Grid -->
    <div class="row">
        <!-- Data Management Section -->
        <div class="col-12 mb-4">
            <h4 class="mb-3">
                <i class="bi bi-database me-2"></i>Data Management
            </h4>
            <div class="row">
                <div class="col-md-3 mb-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <i class="bi bi-bar-chart-steps fs-2 text-primary me-3"></i>
                                <div>
                                    <h5 class="card-title mb-1">Manage Spans</h5>
                                    <p class="card-text text-muted">View, edit, and manage all spans in the system</p>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="badge bg-primary">{{ number_format($stats['total_spans']) }} spans</span>
                                <a href="{{ route('admin.spans.index') }}" class="btn btn-outline-primary btn-sm">
                                    <i class="bi bi-arrow-right"></i> Manage
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <i class="bi bi-arrow-left-right fs-2 text-success me-3"></i>
                                <div>
                                    <h5 class="card-title mb-1">Manage Connections</h5>
                                    <p class="card-text text-muted">View and manage relationships between spans</p>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="badge bg-success">{{ number_format($stats['total_connections']) }} connections</span>
                                <a href="{{ route('admin.connections.index') }}" class="btn btn-outline-success btn-sm">
                                    <i class="bi bi-arrow-right"></i> Manage
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <i class="bi bi-people fs-2 text-info me-3"></i>
                                <div>
                                    <h5 class="card-title mb-1">Manage Users</h5>
                                    <p class="card-text text-muted">Manage user accounts and permissions</p>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="badge bg-info">{{ number_format($stats['total_users']) }} users</span>
                                <a href="{{ route('admin.users.index') }}" class="btn btn-outline-info btn-sm">
                                    <i class="bi bi-arrow-right"></i> Manage
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <i class="bi bi-geo-alt fs-2 text-warning me-3"></i>
                                <div>
                                    <h5 class="card-title mb-1">Manage Places</h5>
                                    <p class="card-text text-muted">Manage place spans and geospatial data</p>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="badge bg-warning">{{ number_format($stats['place_spans'] ?? 0) }} places</span>
                                <a href="{{ route('admin.places.index') }}" class="btn btn-outline-warning btn-sm">
                                    <i class="bi bi-arrow-right"></i> Manage
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <i class="bi bi-image fs-2 text-secondary me-3"></i>
                                <div>
                                    <h5 class="card-title mb-1">Manage Images</h5>
                                    <p class="card-text text-muted">View and manage photo spans and their connections</p>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="badge bg-secondary">{{ number_format($stats['photo_spans'] ?? 0) }} photos</span>
                                <a href="{{ route('admin.images.index') }}" class="btn btn-outline-secondary btn-sm">
                                    <i class="bi bi-arrow-right"></i> Manage
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- User & Access Management Section -->
        <div class="col-12 mb-4">
            <h4 class="mb-3">
                <i class="bi bi-people me-2"></i>User & Access Management
            </h4>
            <div class="row">
                <div class="col-md-3 mb-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <i class="bi bi-people fs-2 text-primary me-3"></i>
                                <div>
                                    <h5 class="card-title mb-1">User Groups</h5>
                                    <p class="card-text text-muted">Manage user groups and memberships</p>
                                </div>
                            </div>
                            <a href="{{ route('admin.groups.index') }}" class="btn btn-outline-primary btn-sm w-100">
                                <i class="bi bi-arrow-right"></i> Manage Groups
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <i class="bi bi-person-badge fs-2 text-warning me-3"></i>
                                <div>
                                    <h5 class="card-title mb-1">Person Subtypes</h5>
                                    <p class="card-text text-muted">Manage public figures vs private individuals</p>
                                </div>
                            </div>
                            <a href="{{ route('admin.spans.manage-person-subtypes') }}" class="btn btn-outline-warning btn-sm w-100">
                                <i class="bi bi-arrow-right"></i> Manage Subtypes
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <i class="bi bi-shield-lock fs-2 text-danger me-3"></i>
                                <div>
                                    <h5 class="card-title mb-1">Access Control</h5>
                                    <p class="card-text text-muted">Manage span access levels and permissions</p>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="badge bg-danger">{{ number_format($stats['public_spans']) }} public</span>
                                <a href="{{ route('admin.span-access.index') }}" class="btn btn-outline-danger btn-sm">
                                    <i class="bi bi-arrow-right"></i> Manage
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- System Configuration Section -->
        <div class="col-12 mb-4">
            <h4 class="mb-3">
                <i class="bi bi-gear me-2"></i>System Configuration
            </h4>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <i class="bi bi-ui-checks fs-2 text-secondary me-3"></i>
                                <div>
                                    <h5 class="card-title mb-1">Span Types</h5>
                                    <p class="card-text text-muted">Configure span types and their properties</p>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="badge bg-secondary">{{ count($spanTypeStats) }} types</span>
                                <a href="{{ route('admin.span-types.index') }}" class="btn btn-outline-secondary btn-sm">
                                    <i class="bi bi-arrow-right"></i> Configure
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <i class="bi bi-sliders2 fs-2 text-warning me-3"></i>
                                <div>
                                    <h5 class="card-title mb-1">Connection Types</h5>
                                    <p class="card-text text-muted">Configure connection types and constraints</p>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="badge bg-warning">{{ count($connectionTypeStats) }} types</span>
                                <a href="{{ route('admin.connection-types.index') }}" class="btn btn-outline-warning btn-sm">
                                    <i class="bi bi-arrow-right"></i> Configure
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Data Operations Section -->
        <div class="col-12 mb-4">
            <h4 class="mb-3">
                <i class="bi bi-arrow-left-right me-2"></i>Data Operations
            </h4>
            <div class="row">
                <div class="col-md-3 mb-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <i class="bi bi-upload fs-2 text-primary me-3"></i>
                                <div>
                                    <h5 class="card-title mb-1">Data Import</h5>
                                    <p class="card-text text-muted">Import data from various sources</p>
                                </div>
                            </div>
                            <a href="{{ route('admin.data-import.index') }}" class="btn btn-outline-primary btn-sm w-100">
                                <i class="bi bi-arrow-right"></i> Import
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <i class="bi bi-download fs-2 text-success me-3"></i>
                                <div>
                                    <h5 class="card-title mb-1">Data Export</h5>
                                    <p class="card-text text-muted">Export data in various formats</p>
                                </div>
                            </div>
                            <a href="{{ route('admin.data-export.index') }}" class="btn btn-outline-success btn-sm w-100">
                                <i class="bi bi-arrow-right"></i> Export
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <i class="bi bi-music-note-list fs-2 text-info me-3"></i>
                                <div>
                                    <h5 class="card-title mb-1">MusicBrainz Import</h5>
                                    <p class="card-text text-muted">Import music data from MusicBrainz</p>
                                </div>
                            </div>
                            <a href="{{ route('admin.import.musicbrainz.index') }}" class="btn btn-outline-info btn-sm w-100">
                                <i class="bi bi-arrow-right"></i> Import
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <i class="bi bi-file-earmark-text fs-2 text-warning me-3"></i>
                                <div>
                                    <h5 class="card-title mb-1">YAML Import</h5>
                                    <p class="card-text text-muted">Import data from YAML files</p>
                                </div>
                            </div>
                            <a href="{{ route('admin.import.index') }}" class="btn btn-outline-warning btn-sm w-100">
                                <i class="bi bi-arrow-right"></i> Import
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <i class="bi bi-music-note-beamed fs-2 text-danger me-3"></i>
                                <div>
                                    <h5 class="card-title mb-1">Desert Island Discs</h5>
                                    <p class="card-text text-muted">Import BBC Desert Island Discs data</p>
                                </div>
                            </div>
                            <a href="{{ route('admin.import.desert-island-discs.index') }}" class="btn btn-outline-danger btn-sm w-100">
                                <i class="bi bi-arrow-right"></i> Import
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <i class="bi bi-music-note-beamed fs-2 text-warning me-3"></i>
                                <div>
                                    <h5 class="card-title mb-1">Simple DID Import</h5>
                                    <p class="card-text text-muted">Import DID data as placeholders only</p>
                                </div>
                            </div>
                            <a href="{{ route('admin.import.simple-desert-island-discs.index') }}" class="btn btn-outline-warning btn-sm w-100">
                                <i class="bi bi-arrow-right"></i> Import
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <i class="bi bi-building fs-2 text-primary me-3"></i>
                                <div>
                                    <h5 class="card-title mb-1">Parliament Explorer</h5>
                                    <p class="card-text text-muted">Explore UK Parliament member data</p>
                                </div>
                            </div>
                            <a href="{{ route('admin.import.parliament.index') }}" class="btn btn-outline-primary btn-sm w-100">
                                <i class="bi bi-arrow-right"></i> Explore
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <i class="bi bi-person-badge fs-2 text-success me-3"></i>
                                <div>
                                    <h5 class="card-title mb-1">Prime Ministers</h5>
                                    <p class="card-text text-muted">Import UK Prime Ministers</p>
                                </div>
                            </div>
                            <a href="{{ route('admin.import.prime-ministers.index') }}" class="btn btn-outline-success btn-sm w-100">
                                <i class="bi bi-arrow-right"></i> Import
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <i class="bi bi-museum fs-2 text-secondary me-3"></i>
                                <div>
                                    <h5 class="card-title mb-1">Science Museum Group</h5>
                                    <p class="card-text text-muted">Import museum objects and creators</p>
                                </div>
                            </div>
                            <a href="{{ route('admin.import.science-museum-group.index') }}" class="btn btn-outline-secondary btn-sm w-100">
                                <i class="bi bi-arrow-right"></i> Import
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <i class="bi bi-images fs-2 text-primary me-3"></i>
                                <div>
                                    <h5 class="card-title mb-1">Wikimedia Commons</h5>
                                    <p class="card-text text-muted">Import images from Wikimedia Commons</p>
                                </div>
                            </div>
                            <a href="{{ route('admin.import.wikimedia-commons.index') }}" class="btn btn-outline-primary btn-sm w-100">
                                <i class="bi bi-arrow-right"></i> Import
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tools & Analysis Section -->
        <div class="col-12 mb-4">
            <h4 class="mb-3">
                <i class="bi bi-tools me-2"></i>Tools & Analysis
            </h4>
            <div class="row">
                <div class="col-md-3 mb-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <i class="bi bi-tools fs-2 text-secondary me-3"></i>
                                <div>
                                    <h5 class="card-title mb-1">Admin Tools</h5>
                                    <p class="card-text text-muted">Various administrative utilities</p>
                                </div>
                            </div>
                            <a href="{{ route('admin.tools.index') }}" class="btn btn-outline-secondary btn-sm w-100">
                                <i class="bi bi-arrow-right"></i> Tools
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <i class="bi bi-robot fs-2 text-primary me-3"></i>
                                <div>
                                    <h5 class="card-title mb-1">AI Generator</h5>
                                    <p class="card-text text-muted">Generate YAML using AI</p>
                                </div>
                            </div>
                            <a href="{{ route('admin.ai-yaml-generator.show') }}" class="btn btn-outline-primary btn-sm w-100">
                                <i class="bi bi-arrow-right"></i> Generate
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <i class="bi bi-clock-history fs-2 text-info me-3"></i>
                                <div>
                                    <h5 class="card-title mb-1">System History</h5>
                                    <p class="card-text text-muted">View versioning history across the system</p>
                                </div>
                            </div>
                            <a href="{{ route('admin.system-history.index') }}" class="btn btn-outline-info btn-sm w-100">
                                <i class="bi bi-arrow-right"></i> View History
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <i class="bi bi-graph-up fs-2 text-success me-3"></i>
                                <div>
                                    <h5 class="card-title mb-1">Network Explorer</h5>
                                    <p class="card-text text-muted">Visualize network relationships</p>
                                </div>
                            </div>
                            <a href="{{ route('admin.visualizer.index') }}" class="btn btn-outline-success btn-sm w-100">
                                <i class="bi bi-arrow-right"></i> Explore
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <i class="bi bi-slack fs-2 text-info me-3"></i>
                                <div>
                                    <h5 class="card-title mb-1">Slack Notifications</h5>
                                    <p class="card-text text-muted">Manage Slack integration and notifications</p>
                                </div>
                            </div>
                            <a href="{{ route('admin.slack-notifications.index') }}" class="btn btn-outline-info btn-sm w-100">
                                <i class="bi bi-arrow-right"></i> Manage
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Additional Stats Section -->
        <div class="col-12">
            <h4 class="mb-3">
                <i class="bi bi-graph-up me-2"></i>Additional Statistics
            </h4>
            <div class="row">
                <div class="col-md-2 mb-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <h6 class="card-title">Spans with Dates</h6>
                            <h4 class="text-primary">{{ number_format($stats['spans_with_dates']) }}</h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 mb-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <h6 class="card-title">With End Dates</h6>
                            <h4 class="text-success">{{ number_format($stats['spans_with_end_dates']) }}</h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 mb-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <h6 class="card-title">Ongoing Spans</h6>
                            <h4 class="text-warning">{{ number_format($stats['ongoing_spans']) }}</h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 mb-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <h6 class="card-title">Public Spans</h6>
                            <h4 class="text-info">{{ number_format($stats['public_spans']) }}</h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 mb-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <h6 class="card-title">Private Spans</h6>
                            <h4 class="text-secondary">{{ number_format($stats['private_spans']) }}</h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 mb-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <h6 class="card-title">Inherited</h6>
                            <h4 class="text-muted">{{ number_format($stats['inherited_spans']) }}</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection 