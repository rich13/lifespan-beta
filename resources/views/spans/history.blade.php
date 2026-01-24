@extends('layouts.app')

@section('title')
    Version History for <span class="text-primary">{{ $span->name }}</span>
@endsection

@section('page_title')
<x-breadcrumb :items="[
        [
            'text' => 'History',
            'icon' => $span->type_id,
            'icon_category' => 'span'
        ],
        [
            'text' => $span->getDisplayTitle(),
            'url' => route('spans.show', $span),
            'icon' => 'view',
            'icon_category' => 'action'
        ]
    ]" />
@endsection

@section('content')
<div class="container-fluid my-4">
    @if($versions->isNotEmpty())
        <x-spans.versions-timeline :span="$span" :versions="$versions" :selectedVersion="$versionModel" />
    @endif
    <div class="row">
        <div class="{{ $versionModel ? 'col-md-8' : 'col-12' }}">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong>Version History</strong>
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button" class="btn btn-outline-primary active" id="show-all">All Changes</button>
                        <button type="button" class="btn btn-outline-primary" id="show-spans">Span Changes</button>
                        <button type="button" class="btn btn-outline-primary" id="show-connections">Connection Changes</button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <table class="table table-striped mb-0 w-100">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Version</th>
                                <th>Changed By</th>
                                <th>Summary</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse($allChanges as $change)
                            @if($change['type'] === 'span_change')
                                @php
                                    $isSelected = $versionModel && $versionModel->version_number === $change['version']->version_number;
                                @endphp
                                <tr class="change-row span-change {{ $isSelected ? 'table-primary' : '' }}">
                                    <td>
                                        <span class="badge bg-primary">
                                            <i class="bi bi-box"></i> Span Change
                                        </span>
                                    </td>
                                    <td>
                                        <a href="{{ route('spans.history', [$span, $change['version']->version_number]) }}" class="text-decoration-none">
                                            <strong>v{{ $change['version']->version_number }}</strong>
                                        </a>
                                    </td>
                                    <td>{{ $change['version']->changedBy?->name ?? 'Unknown' }}</td>
                                    <td>{{ $change['version']->change_summary ?? '-' }}</td>
                                    <td>{{ $change['version']->created_at->format('Y-m-d H:i') }}</td>
                                    <td>
                                        <a href="{{ route('spans.history', [$span, $change['version']->version_number]) }}" class="btn btn-sm btn-outline-primary" title="View changes">
                                            <i class="bi bi-eye"></i> View
                                        </a>
                                    </td>
                                </tr>
                            @elseif($change['type'] === 'connection_change')
                                <tr class="change-row connection-change">
                                    <td>
                                        <span class="badge bg-info">
                                            <i class="bi bi-link-45deg"></i> Connection Change
                                        </span>
                                    </td>
                                    <td>
                                        @if($change['connection']->connectionSpan)
                                            <a href="{{ route('spans.history', [$change['connection']->connectionSpan, $change['version']->version_number]) }}" class="text-decoration-none">
                                                <strong>v{{ $change['version']->version_number }}</strong>
                                            </a>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td>{{ $change['version']->changedBy?->name ?? 'Unknown' }}</td>
                                    <td>
                                        @if($change['is_parent'])
                                            The <strong>{{ $change['relationship_type'] }}</strong> relationship to 
                                            <a href="{{ route('spans.history', $change['other_span']) }}">{{ $change['other_span']->name }}</a> was modified
                                        @else
                                            <a href="{{ route('spans.history', $change['other_span']) }}">{{ $change['other_span']->name }}</a>'s <strong>{{ $change['relationship_type'] }}</strong> relationship to this span was modified
                                        @endif
                                        @if($change['version']->change_summary)
                                            <br><small class="text-muted">{{ $change['version']->change_summary }}</small>
                                        @endif
                                    </td>
                                    <td>{{ $change['version']->created_at->format('Y-m-d H:i') }}</td>
                                    <td>
                                        @if($change['connection']->connectionSpan)
                                            <a href="{{ route('spans.history', [$change['connection']->connectionSpan, $change['version']->version_number]) }}" class="btn btn-sm btn-outline-info" title="View connection changes">
                                                <i class="bi bi-eye"></i> View
                                            </a>
                                        @else
                                            <span class="text-muted">No connection span</span>
                                        @endif
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted">No version history found for this span.</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        @if($versionModel)
        <div class="col-md-4">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">Version {{ $versionModel->version_number }} Details</h6>
                    <div>
                        @if($previousVersion)
                            <a href="{{ route('spans.history', [$span, $previousVersion->version_number]) }}" class="btn btn-sm btn-outline-secondary" title="Previous version">
                                <i class="bi bi-arrow-left"></i>
                            </a>
                        @endif
                        @php
                            $nextVersion = $span->versions()
                                ->where('version_number', '>', $versionModel->version_number)
                                ->orderBy('version_number')
                                ->first();
                        @endphp
                        @if($nextVersion)
                            <a href="{{ route('spans.history', [$span, $nextVersion->version_number]) }}" class="btn btn-sm btn-outline-secondary" title="Next version">
                                <i class="bi bi-arrow-right"></i>
                            </a>
                        @endif
                        <a href="{{ route('spans.history', $span) }}" class="btn btn-sm btn-outline-secondary" title="Clear selection">
                            <i class="bi bi-x"></i>
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <h6>Basic Information</h6>
                        <table class="table table-sm mb-0">
                            <tr>
                                <td><strong>Name:</strong></td>
                                <td>{{ $versionModel->name }}</td>
                            </tr>
                            <tr>
                                <td><strong>Type:</strong></td>
                                <td>{{ $versionModel->type_id }}</td>
                            </tr>
                            <tr>
                                <td><strong>State:</strong></td>
                                <td><span class="badge bg-{{ $versionModel->state === 'complete' ? 'success' : ($versionModel->state === 'draft' ? 'warning' : 'secondary') }}">{{ ucfirst($versionModel->state) }}</span></td>
                            </tr>
                            <tr>
                                <td><strong>Access Level:</strong></td>
                                <td><span class="badge bg-{{ $versionModel->access_level === 'public' ? 'success' : ($versionModel->access_level === 'shared' ? 'info' : 'secondary') }}">{{ ucfirst($versionModel->access_level) }}</span></td>
                            </tr>
                        </table>
                    </div>

                    <div class="mb-3">
                        <h6>Timeline</h6>
                        <table class="table table-sm mb-0">
                            <tr>
                                <td><strong>Start:</strong></td>
                                <td>{{ $versionModel->formatted_start_date ?? 'Not set' }}</td>
                            </tr>
                            <tr>
                                <td><strong>End:</strong></td>
                                <td>{{ $versionModel->formatted_end_date ?? 'Ongoing' }}</td>
                            </tr>
                            <tr>
                                <td><strong>Start Precision:</strong></td>
                                <td>{{ ucfirst($versionModel->start_precision) }}</td>
                            </tr>
                            <tr>
                                <td><strong>End Precision:</strong></td>
                                <td>{{ ucfirst($versionModel->end_precision) }}</td>
                            </tr>
                        </table>
                    </div>

                    @if($versionModel->description)
                        <div class="mb-3">
                            <h6>Description</h6>
                            <p class="small mb-0">{{ $versionModel->description }}</p>
                        </div>
                    @endif

                    @if($versionModel->notes)
                        <div class="mb-3">
                            <h6>Notes</h6>
                            <p class="small mb-0">{{ $versionModel->notes }}</p>
                        </div>
                    @endif

                    <div class="mb-3">
                        <h6>Version Information</h6>
                        <table class="table table-sm mb-0">
                            <tr>
                                <td><strong>Changed By:</strong></td>
                                <td>{{ $versionModel->changedBy?->name ?? 'Unknown' }}</td>
                            </tr>
                            <tr>
                                <td><strong>Changed At:</strong></td>
                                <td>{{ $versionModel->created_at->format('Y-m-d H:i:s') }}</td>
                            </tr>
                            @if($versionModel->change_summary)
                                <tr>
                                    <td><strong>Change Summary:</strong></td>
                                    <td>{{ $versionModel->change_summary }}</td>
                                </tr>
                            @endif
                        </table>
                    </div>

                    @if($previousVersion && !empty($changes))
                        <div class="mt-3">
                            <h6>Changes from Version {{ $previousVersion->version_number }}</h6>
                            <div class="small">
                                @foreach($changes as $field => $change)
                                    <div class="mb-2">
                                        <strong class="text-capitalize">{{ str_replace('_', ' ', $field) }}:</strong>
                                        <div class="row mt-1">
                                            <div class="col-6">
                                                <small class="text-muted d-block">From:</small>
                                                <div class="border rounded p-1 bg-light">
                                                    @if(is_array($change['from']))
                                                        <pre class="mb-0" style="font-size: 0.7rem;">{{ json_encode($change['from'], JSON_PRETTY_PRINT) }}</pre>
                                                    @else
                                                        {{ $change['from'] ?? 'null' }}
                                                    @endif
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <small class="text-muted d-block">To:</small>
                                                <div class="border rounded p-1 bg-light">
                                                    @if(is_array($change['to']))
                                                        <pre class="mb-0" style="font-size: 0.7rem;">{{ json_encode($change['to'], JSON_PRETTY_PRINT) }}</pre>
                                                    @else
                                                        {{ $change['to'] ?? 'null' }}
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @elseif($previousVersion)
                        <div class="mt-3">
                            <p class="text-muted small mb-0">No changes detected from the previous version.</p>
                        </div>
                    @else
                        <div class="mt-3">
                            <p class="text-muted small mb-0">This is the initial version.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
        @endif
    </div>
</div>

<script>
$(document).ready(function() {
    // Filter functionality
    $('#show-all').click(function() {
        $('.change-row').show();
        $('.btn-group .btn').removeClass('active');
        $(this).addClass('active');
    });
    
    $('#show-spans').click(function() {
        $('.span-change').show();
        $('.connection-change').hide();
        $('.btn-group .btn').removeClass('active');
        $(this).addClass('active');
    });
    
    $('#show-connections').click(function() {
        $('.span-change').hide();
        $('.connection-change').show();
        $('.btn-group .btn').removeClass('active');
        $(this).addClass('active');
    });
});
</script>
@endsection 