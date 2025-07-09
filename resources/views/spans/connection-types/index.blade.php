@extends('layouts.app')

@section('title', "Connection Types for {$span->name}")

@section('content')
<div class="container">
    <div class="row">
        <div class="col-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('spans.index') }}">Spans</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('spans.show', $span) }}">{{ $span->name }}</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Connection Types</li>
                </ol>
            </nav>

            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>Connection Types for {{ $span->name }}</h1>
                <a href="{{ route('spans.show', $span) }}" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Back to Span
                </a>
            </div>

            @if($connectionTypes->isEmpty())
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i>
                    This span has no connections yet.
                </div>
            @else
                <div class="row">
                    @foreach($connectionTypes as $connectionType)
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h5 class="card-title">
                                        <a href="{{ route('spans.connection-types.show', [$span, $connectionType]) }}" 
                                           class="text-decoration-none">
                                            {{ $connectionType->forward_predicate }}
                                        </a>
                                    </h5>
                                    <p class="card-text text-muted">
                                        {{ $connectionType->forward_description }}
                                    </p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="badge bg-primary">
                                            {{ $connectionType->connections_count }} connection{{ $connectionType->connections_count !== 1 ? 's' : '' }}
                                        </span>
                                        <a href="{{ route('spans.connection-types.show', [$span, $connectionType]) }}" 
                                           class="btn btn-sm btn-outline-primary">
                                            View Connections
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
@endsection 