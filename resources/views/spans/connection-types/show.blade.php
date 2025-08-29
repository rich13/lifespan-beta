@extends('layouts.app')

@section('title', "{$connectionType->forward_predicate} Connections for {$span->name}")

@section('content')
<div class="container">
    <div class="row">
        <div class="col-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('spans.index') }}">Spans</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('spans.show', $span) }}">{{ $span->name }}</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('spans.connection-types.index', $span) }}">Connection Types</a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ $connectionType->forward_predicate }}</li>
                </ol>
            </nav>

            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1>{{ $connectionType->forward_predicate }} Connections</h1>
                    <p class="text-muted mb-0">
                        {{ $connectionType->forward_description }}
                    </p>
                    <small class="text-muted">
                        Showing connections for: <strong>{{ $span->name }}</strong>
                    </small>
                </div>
                <div>
                    <a href="{{ route('spans.connection-types.index', $span) }}" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Connection Types
                    </a>
                </div>
            </div>

            @if($connections->isEmpty())
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i>
                    No {{ strtolower($connectionType->forward_predicate) }} connections found for this span.
                </div>
            @else
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <span>
                                {{ $connections->total() }} connection{{ $connections->total() !== 1 ? 's' : '' }} found
                            </span>
                            @if($connections->hasPages())
                                <small class="text-muted">
                                    Showing {{ $connections->firstItem() }}-{{ $connections->lastItem() }} of {{ $connections->total() }}
                                </small>
                            @endif
                        </div>
                    </div>
                    <div class="list-group list-group-flush">
                        @foreach($connections as $connection)
                            <div class="list-group-item">
                                <div class="row align-items-center">
                                    <div class="col-md-8">
                                        <div class="d-flex align-items-center">
                                            <div class="me-3">
                                                @if($connection->is_parent)
                                                    <span class="badge bg-success">
                                                        <i class="bi bi-arrow-right"></i> {{ $connection->predicate }}
                                                    </span>
                                                @else
                                                    <span class="badge bg-info">
                                                        <i class="bi bi-arrow-left"></i> {{ $connection->predicate }}
                                                    </span>
                                                @endif
                                            </div>
                                            <div>
                                                <h6 class="mb-1">
                                                    <a href="{{ route('spans.show', $connection->other_span) }}" 
                                                       class="text-decoration-none">
                                                        {{ $connection->other_span->name }}
                                                    </a>
                                                </h6>
                                                @if($connection->other_span->description)
                                                    <p class="text-muted mb-0 small">
                                                        {{ Str::limit($connection->other_span->description, 100) }}
                                                    </p>
                                                @endif
                                                @if($connection->connectionSpan && $connection->connectionSpan->formatted_start_date)
                                                    <small class="text-muted">
                                                        <i class="bi bi-calendar"></i>
                                                        {{ $connection->connectionSpan->formatted_start_date }}
                                                    </small>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4 text-end">
                                        <div class="btn-group" role="group">
                                            <a href="{{ route('spans.show', $connection->other_span) }}" 
                                               class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-eye"></i> View
                                            </a>
                                            <a href="{{ route('spans.show', $connection->connectionSpan) }}" 
                                               class="btn btn-sm btn-outline-info">
                                                <i class="bi bi-link-45deg"></i> Connection
                                            </a>
                                            @if($connection->connectionSpan)
                                                <a href="{{ route('spans.show', $connection->connectionSpan) }}" 
                                                   class="btn btn-sm btn-outline-secondary">
                                                    <i class="bi bi-link"></i> Span
                                                </a>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                @if($connections->hasPages())
                    <div class="d-flex justify-content-center mt-4">
                        {{ $connections->links() }}
                    </div>
                @endif
            @endif
        </div>
    </div>
</div>
@endsection 