@extends('layouts.app')

@section('page_title')
    Family Connection Date Sync Tool
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
        <div class="col-md-4 mb-3">
            <div class="card bg-primary text-white h-100">
                <div class="card-body text-center">
                    <h4 class="mb-1">{{ number_format($stats['total_family_connections']) }}</h4>
                    <small>Total Family Connections</small>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card bg-success text-white h-100">
                <div class="card-body text-center">
                    <h4 class="mb-1">{{ number_format($stats['connections_with_dates']) }}</h4>
                    <small>With Proper Dates</small>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card bg-warning text-white h-100">
                <div class="card-body text-center">
                    <h4 class="mb-1">{{ number_format($stats['connections_without_dates']) }}</h4>
                    <small>Need Date Sync</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Sync Actions -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-calendar-check me-2"></i>
                        Sync Family Connection Dates
                    </h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">
                        This tool automatically sets start and end dates for family connections based on the birth and death dates of the connected people.
                        Parent-child relationships start at the child's birth and end at the parent's death (or child's death if sooner).
                        Other family relationships start at the later birth date and end at the earlier death date.
                    </p>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="card border-primary">
                                <div class="card-header bg-primary text-white">
                                    <h6 class="mb-0">Sync All Connections</h6>
                                </div>
                                <div class="card-body">
                                    <form action="{{ route('admin.tools.family-connection-date-sync-action') }}" method="POST">
                                        @csrf
                                        <input type="hidden" name="dry_run" value="1">
                                        <button type="submit" class="btn btn-outline-primary btn-sm me-2">
                                            <i class="bi bi-eye me-1"></i>Dry Run
                                        </button>
                                    </form>
                                    
                                    <form action="{{ route('admin.tools.family-connection-date-sync-action') }}" method="POST" class="d-inline">
                                        @csrf
                                        <input type="hidden" name="dry_run" value="0">
                                        <button type="submit" class="btn btn-primary btn-sm" 
                                                onclick="return confirm('Are you sure you want to sync all family connection dates? This will update {{ number_format($stats['connections_without_dates']) }} connections.')">
                                            <i class="bi bi-check-circle me-1"></i>Apply Changes
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="card border-info">
                                <div class="card-header bg-info text-white">
                                    <h6 class="mb-0">Sync Specific Connection</h6>
                                </div>
                                <div class="card-body">
                                    <form action="{{ route('admin.tools.family-connection-date-sync-action') }}" method="POST">
                                        @csrf
                                        <div class="input-group input-group-sm mb-2">
                                            <input type="text" class="form-control" name="connection_id" 
                                                   placeholder="Connection ID" required>
                                            <button type="submit" class="btn btn-outline-info">
                                                <i class="bi bi-search me-1"></i>Find & Sync
                                            </button>
                                        </div>
                                        <small class="text-muted">Enter a connection ID to sync a specific connection</small>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Sample Connections -->
    @if($sampleConnections->isNotEmpty())
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-list-ul me-2"></i>
                        Sample Connections Needing Sync
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Connection</th>
                                    <th>Type</th>
                                    <th>Subject Birth/Death</th>
                                    <th>Object Birth/Death</th>
                                    <th>Current Dates</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($sampleConnections as $connection)
                                <tr>
                                    <td>
                                        <strong>{{ $connection->subject->name }}</strong> ↔ 
                                        <strong>{{ $connection->object->name }}</strong>
                                        <br>
                                        <small class="text-muted">ID: {{ $connection->id }}</small>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary">{{ $connection->type->type }}</span>
                                    </td>
                                    <td>
                                        @if($connection->subject->start_year)
                                            {{ $connection->subject->start_year }}-{{ $connection->subject->start_month ?: '01' }}-{{ $connection->subject->start_day ?: '01' }}
                                        @else
                                            <span class="text-muted">No birth date</span>
                                        @endif
                                        <br>
                                        @if($connection->subject->end_year)
                                            <small class="text-muted">Died: {{ $connection->subject->end_year }}-{{ $connection->subject->end_month ?: '01' }}-{{ $connection->subject->end_day ?: '01' }}</small>
                                        @endif
                                    </td>
                                    <td>
                                        @if($connection->object->start_year)
                                            {{ $connection->object->start_year }}-{{ $connection->object->start_month ?: '01' }}-{{ $connection->object->start_day ?: '01' }}
                                        @else
                                            <span class="text-muted">No birth date</span>
                                        @endif
                                        <br>
                                        @if($connection->object->end_year)
                                            <small class="text-muted">Died: {{ $connection->object->end_year }}-{{ $connection->object->end_month ?: '01' }}-{{ $connection->object->end_day ?: '01' }}</small>
                                        @endif
                                    </td>
                                    <td>
                                        @if($connection->connectionSpan)
                                            @if($connection->connectionSpan->start_year)
                                                <strong>Start:</strong> {{ $connection->connectionSpan->start_year }}-{{ $connection->connectionSpan->start_month ?: '01' }}-{{ $connection->connectionSpan->start_day ?: '01' }}<br>
                                            @else
                                                <span class="text-warning">No start date</span><br>
                                            @endif
                                            @if($connection->connectionSpan->end_year)
                                                <strong>End:</strong> {{ $connection->connectionSpan->end_year }}-{{ $connection->connectionSpan->end_month ?: '01' }}-{{ $connection->connectionSpan->end_day ?: '01' }}
                                            @else
                                                <span class="text-warning">No end date</span>
                                            @endif
                                        @else
                                            <span class="text-danger">No connection span</span>
                                        @endif
                                    </td>
                                    <td>
                                        <form action="{{ route('admin.tools.family-connection-date-sync-action') }}" method="POST" class="d-inline">
                                            @csrf
                                            <input type="hidden" name="connection_id" value="{{ $connection->id }}">
                                            <input type="hidden" name="dry_run" value="0">
                                            <button type="submit" class="btn btn-primary btn-sm">
                                                <i class="bi bi-check-circle me-1"></i>Sync
                                            </button>
                                        </form>
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
    @endif

    <!-- Sync Results -->
    @if(session('sync_results'))
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-info-circle me-2"></i>
                        Sync Results
                    </h5>
                </div>
                <div class="card-body">
                    @php $results = session('sync_results'); @endphp
                    
                    @if($results['updated'] > 0)
                        <div class="alert alert-success">
                            <i class="bi bi-check-circle me-2"></i>
                            Successfully updated {{ $results['updated'] }} connections.
                        </div>
                    @endif

                    @if(!empty($results['issues']))
                        <h6>Connections that need updates:</h6>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Connection</th>
                                        <th>Type</th>
                                        <th>Suggested Changes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($results['issues'] as $issue)
                                    <tr>
                                        <td>
                                            <strong>{{ $issue['span1']->name }}</strong> ↔ 
                                            <strong>{{ $issue['span2']->name }}</strong>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary">{{ $issue['connection']->type->type }}</span>
                                        </td>
                                        <td>
                                            @foreach($issue['changes'] as $field => $value)
                                                <div>
                                                    <strong>{{ ucfirst(str_replace('_', ' ', $field)) }}:</strong>
                                                    {{ $value ? $value->format('Y-m-d') : 'NULL' }}
                                                </div>
                                            @endforeach
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
@endsection 