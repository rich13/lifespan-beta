@extends('layouts.app')

@section('title', "{$subject->name} - {$connectionType->forward_predicate}")

@section('page_title')
    <x-breadcrumb :items="[
        [
            'text' => 'Spans',
            'url' => route('spans.index'),
            'icon' => 'view',
            'icon_category' => 'action'
        ],
        [
            'text' => $subject->getDisplayTitle(),
            'url' => route('spans.show', $subject),
            'icon' => $subject->type_id,
            'icon_category' => 'span'
        ],
        [
            'text' => $connectionType->forward_predicate,
            'url' => route('spans.connections', ['subject' => $subject, 'predicate' => str_replace(' ', '-', $connectionType->forward_predicate)]),
            'icon' => $connectionType->type_id,
            'icon_category' => 'connection'
        ]
    ]" />
@endsection

@section('content')
<div class="container-fluid">
    <div class="row">
       <p class="bg-warning text-muted">This page is a work in progress. It will be updated to show things in a more user-friendly way.</p>
    </div>
    <div class="row">
        @if($connections->count() > 0)
                <div class="row">
                    @foreach($connections as $connection)
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h5 class="card-title">
                                        <a href="{{ route('spans.show', $connection->other_span) }}" class="text-decoration-none">
                                            {{ $connection->other_span->name }}
                                        </a>
                                    </h5>
                                    
                                    <p class="card-text text-muted">
                                        <i class="bi bi-arrow-right"></i> {{ $connection->predicate }}
                                    </p>

                                    @if($connection->connectionSpan)
                                        <p class="card-text">
                                            <small class="text-muted">
                                                Connection: 
                                                <a href="{{ route('spans.show', $connection->connectionSpan) }}" class="text-decoration-none">
                                                    {{ $connection->connectionSpan->name }}
                                                </a>
                                            </small>
                                        </p>
                                    @endif

                                    <div class="btn-group" role="group">
                                        <a href="{{ route('spans.show', $connection->other_span) }}" 
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-eye"></i> View
                                        </a>
                                        <a href="{{ route('spans.connection', [$subject, $predicate, $connection->other_span]) }}" 
                                           class="btn btn-sm btn-outline-info">
                                            <i class="bi bi-link-45deg"></i> Connection
                                        </a>
                                        @if($connection->connectionSpan)
                                            <a href="{{ route('spans.show', $connection->connectionSpan) }}" 
                                               class="btn btn-sm btn-outline-secondary">
                                                <i class="bi bi-arrow-left-right"></i> Span
                                            </a>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="d-flex justify-content-center">
                    {{ $connections->links() }}
                </div>
            @else
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i>
                    No {{ $connectionType->forward_predicate }} connections found for {{ $subject->name }}.
                </div>
            @endif
        </div>
    </div>
</div>
@endsection 