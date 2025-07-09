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
                        <th>Changed By</th>
                        <th>Summary</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($allChanges as $change)
                    @if($change['type'] === 'span_change')
                        <tr class="change-row span-change">
                            <td>
                                <span class="badge bg-primary">
                                    <i class="bi bi-box"></i> Span Change
                                </span>
                            </td>
                            <td>{{ $change['version']->changedBy?->name ?? 'Unknown' }}</td>
                            <td>{{ $change['version']->change_summary ?? '-' }}</td>
                            <td>{{ $change['version']->created_at->format('Y-m-d H:i') }}</td>
                            <td>
                                <a href="{{ route('spans.history.version', [$span, $change['version']->version_number]) }}" class="btn btn-sm btn-outline-primary" title="View changes">
                                    <i class="bi bi-eye"></i> View Changes
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
                                    <a href="{{ route('spans.history.version', [$change['connection']->connectionSpan, $change['version']->version_number]) }}" class="btn btn-sm btn-outline-info" title="View connection changes">
                                        <i class="bi bi-eye"></i> View Changes
                                    </a>
                                @else
                                    <span class="text-muted">No connection span</span>
                                @endif
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted">No version history found for this span.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
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