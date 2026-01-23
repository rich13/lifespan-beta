@extends('layouts.app')

@section('title')
    Version {{ $versionModel->version_number }} of {{ $span->name }}
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
            'url' => route('spans.history', $span),
            'icon' => $span->type_id,
            'icon_category' => 'span'
        ],
        [
            'text' => 'Version ' . $versionModel->version_number,
            'icon' => $span->type_id,
            'icon_category' => 'span'
        ]
    ]" />
@endsection

@section('content')
<div class="container-fluid my-4">
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Version {{ $versionModel->version_number }} Details</h5>
                    <div>
                        @if($previousVersion)
                            <a href="{{ route('spans.history', [$span, $previousVersion->version_number]) }}" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-arrow-left"></i> Previous Version
                            </a>
                        @endif
                        @if($versionModel->version_number < $span->getLatestVersion()->version_number)
                            <a href="{{ route('spans.history', [$span, $versionModel->version_number + 1]) }}" class="btn btn-sm btn-outline-secondary">
                                Next Version <i class="bi bi-arrow-right"></i>
                            </a>
                        @endif
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Basic Information</h6>
                            <table class="table table-sm">
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
                        <div class="col-md-6">
                            <h6>Timeline</h6>
                            <table class="table table-sm">
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
                    </div>

                    @if($versionModel->description)
                        <div class="mt-3">
                            <h6>Description</h6>
                            <p>{{ $versionModel->description }}</p>
                        </div>
                    @endif

                    @if($versionModel->notes)
                        <div class="mt-3">
                            <h6>Notes</h6>
                            <p>{{ $versionModel->notes }}</p>
                        </div>
                    @endif

                    <div class="mt-3">
                        <h6>Version Information</h6>
                        <table class="table table-sm">
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
                </div>
            </div>
        </div>

        <div class="col-md-4">
            @if($previousVersion && !empty($changes))
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">Changes from Version {{ $previousVersion->version_number }}</h6>
                    </div>
                    <div class="card-body">
                        @foreach($changes as $field => $change)
                            <div class="mb-3">
                                <h6 class="text-capitalize">{{ str_replace('_', ' ', $field) }}</h6>
                                <div class="row">
                                    <div class="col-6">
                                        <small class="text-muted">From:</small>
                                        <div class="border rounded p-2 bg-light">
                                            @if(is_array($change['from']))
                                                <pre class="mb-0 small">{{ json_encode($change['from'], JSON_PRETTY_PRINT) }}</pre>
                                            @else
                                                {{ $change['from'] ?? 'null' }}
                                            @endif
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted">To:</small>
                                        <div class="border rounded p-2 bg-light">
                                            @if(is_array($change['to']))
                                                <pre class="mb-0 small">{{ json_encode($change['to'], JSON_PRETTY_PRINT) }}</pre>
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
            @else
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">Changes</h6>
                    </div>
                    <div class="card-body">
                        @if($previousVersion)
                            <p class="text-muted mb-0">No changes detected from the previous version.</p>
                        @else
                            <p class="text-muted mb-0">This is the initial version.</p>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection 