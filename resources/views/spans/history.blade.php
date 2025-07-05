@extends('layouts.app')

@section('title')
    Version History for <span class="text-primary">{{ $span->name }}</span>
@endsection

@section('page_title')
<x-breadcrumb :items="[
        [
            'text' => 'Spans',
            'url' => route('spans.index'),
            'icon' => 'view',
            'icon_category' => 'action'
        ],
        [
            'text' => $span->getDisplayTitle(),
            'url' => route('spans.show', $span),
            'icon' => 'view',
            'icon_category' => 'action'
        ],
        [
            'text' => 'History',
            'icon' => $span->type_id,
            'icon_category' => 'span'
        ]
    ]" />
@endsection

@section('content')
<div class="container-fluid my-4">
    <div class="card">
        <div class="card-header">
            <strong>Version History</strong>
        </div>
        <div class="card-body p-0">
            <table class="table table-striped mb-0 w-100">
                <thead>
                    <tr>
                        <th>Version</th>
                        <th>Changed By</th>
                        <th>Summary</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($versions as $version)
                    <tr>
                        <td>{{ $version->version_number }}</td>
                        <td>{{ $version->changedBy?->name ?? 'Unknown' }}</td>
                        <td>{{ $version->change_summary ?? '-' }}</td>
                        <td>{{ $version->created_at->format('Y-m-d H:i') }}</td>
                        <td>
                            <a href="{{ route('spans.history.version', [$span, $version->version_number]) }}" class="btn btn-sm btn-outline-primary" title="View changes">
                                <i class="bi bi-eye"></i> View Changes
                            </a>
                        </td>
                    </tr>
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
@endsection 