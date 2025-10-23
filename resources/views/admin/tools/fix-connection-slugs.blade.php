@extends('layouts.app')

@section('page_title')
    Fix Connection Slugs
@endsection

@section('content')
<div class="py-4">
    <div class="row">
        <div class="col-12 d-flex justify-content-between align-items-center mb-4">
            <div>
                <a href="{{ route('admin.tools.index') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i>Back to Tools
                </a>
            </div>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success">
            <i class="bi bi-check-circle me-2"></i>
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- Statistics Row -->
    <div class="row mb-4">
        <div class="col-md-6 mb-3">
            <div class="card bg-warning text-white h-100">
                <div class="card-body text-center">
                    <h4 class="mb-1">{{ number_format($stats['total_connections_to_fix']) }}</h4>
                    <small>Connections to Fix</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Fix Actions -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-link-45deg me-2"></i>
                        Fix Connection Slugs
                    </h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">
                        This tool finds all connections with slugs containing "connection-between-spans" (including variants like "connection-between-spans-1", "connection-between-spans-2", etc.) 
                        and renames them using the proper convention: <code>"{subject name} {connection type predicate} {object name}"</code>.
                    </p>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="card border-primary">
                                <div class="card-header bg-primary text-white">
                                    <h6 class="mb-0">Preview Changes</h6>
                                </div>
                                <div class="card-body">
                                    <form action="{{ route('admin.tools.fix-connection-slugs-action') }}" method="POST">
                                        @csrf
                                        <input type="hidden" name="dry_run" value="1">
                                        <button type="submit" class="btn btn-outline-primary btn-sm">
                                            <i class="bi bi-eye me-1"></i>Dry Run
                                        </button>
                                        <small class="text-muted d-block mt-2">See what would be changed without actually making changes</small>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="card border-success">
                                <div class="card-header bg-success text-white">
                                    <h6 class="mb-0">Apply Changes</h6>
                                </div>
                                <div class="card-body">
                                    <form action="{{ route('admin.tools.fix-connection-slugs-action') }}" method="POST">
                                        @csrf
                                        <input type="hidden" name="dry_run" value="0">
                                        <button type="submit" class="btn btn-success btn-sm" 
                                                onclick="return confirm('Are you sure you want to fix {{ number_format($stats['total_connections_to_fix']) }} connection slugs? This action cannot be easily undone.')">
                                            <i class="bi bi-check-circle me-1"></i>Fix All
                                        </button>
                                        <small class="text-muted d-block mt-2">Rename and regenerate slugs for all problematic connections</small>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Problematic Connections List -->
    @if($problematicConnections->isNotEmpty())
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-list-ul me-2"></i>
                        Connections to Fix ({{ $problematicConnections->count() }})
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead>
                                <tr>
                                    <th>Subject</th>
                                    <th>Connection Type</th>
                                    <th>Object</th>
                                    <th>Current Slug</th>
                                    <th>New Slug (Preview)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($problematicConnections as $connection)
                                <tr>
                                    <td>
                                        <strong>{{ $connection->subject->name ?? 'N/A' }}</strong>
                                        <br>
                                        <small class="text-muted">{{ $connection->parent_id }}</small>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary">{{ $connection->type->type ?? 'unknown' }}</span>
                                        <br>
                                        <small class="text-muted">{{ $connection->type->forward_predicate ?? 'N/A' }}</small>
                                    </td>
                                    <td>
                                        <strong>{{ $connection->object->name ?? 'N/A' }}</strong>
                                        <br>
                                        <small class="text-muted">{{ $connection->child_id }}</small>
                                    </td>
                                    <td>
                                        <code>{{ $connection->connectionSpan->slug ?? 'N/A' }}</code>
                                    </td>
                                    <td>
                                        @php
                                            if ($connection->subject && $connection->object && $connection->type) {
                                                $newName = "{$connection->subject->name} {$connection->type->forward_predicate} {$connection->object->name}";
                                                $newSlug = \Illuminate\Support\Str::slug($newName);
                                            } else {
                                                $newSlug = '(missing data)';
                                            }
                                        @endphp
                                        <code>{{ $newSlug }}</code>
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
    @else
    <div class="row">
        <div class="col-12">
            <div class="alert alert-info">
                <i class="bi bi-info-circle me-2"></i>
                <strong>Great!</strong> No problematic connections found. All connection slugs appear to be properly formatted.
            </div>
        </div>
    </div>
    @endif
</div>
@endsection
