@extends('layouts.app')

@section('title', "Connection: {$subject->name} → {$object->name}")

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
        ],
        [
            'text' => $object->getDisplayTitle(),
            'url' => route('spans.show', $object),
            'icon' => $object->type_id,
            'icon_category' => 'span'
        ]
    ]" />
@endsection

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">

            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <!-- Subject Span -->
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Subject</h5>
                                </div>
                                <div class="card-body">
                                    <h6><a href="{{ route('spans.show', $subject) }}">{{ $subject->name }}</a></h6>
                                    <p class="text-muted mb-1">{{ $subject->type->name }}</p>
                                    @if($subject->start_year)
                                        <p class="text-muted mb-0">
                                            {{ $subject->start_year }}
                                            @if($subject->end_year)
                                                - {{ $subject->end_year }}
                                            @endif
                                        </p>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <!-- Connection Details -->
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Connection</h5>
                                </div>
                                <div class="card-body">
                                    <h6>{{ $connectionType->forward_predicate }}</h6>
                                    <p class="text-muted mb-1">{{ $connectionType->description }}</p>
                                    
                                    @if($connection->connectionSpan)
                                        <div class="mt-3">
                                            <h6>Connection Span</h6>
                                            <p class="mb-1"><a href="{{ route('spans.show', $connection->connectionSpan) }}">{{ $connection->connectionSpan->name }}</a></p>
                                            @if($connection->connectionSpan->start_year)
                                                <p class="text-muted mb-0">
                                                    {{ $connection->connectionSpan->start_year }}
                                                    @if($connection->connectionSpan->end_year)
                                                        - {{ $connection->connectionSpan->end_year }}
                                                    @endif
                                                </p>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <!-- Object Span -->
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Object</h5>
                                </div>
                                <div class="card-body">
                                    <h6><a href="{{ route('spans.show', $object) }}">{{ $object->name }}</a></h6>
                                    <p class="text-muted mb-1">{{ $object->type->name }}</p>
                                    @if($object->start_year)
                                        <p class="text-muted mb-0">
                                            {{ $object->start_year }}
                                            @if($object->end_year)
                                                - {{ $object->end_year }}
                                            @endif
                                        </p>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Connection Metadata -->
                    @if($connection->metadata)
                        <div class="row mt-4">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0">Connection Details</h5>
                                    </div>
                                    <div class="card-body">
                                        <dl class="row">
                                            @foreach($connection->metadata as $key => $value)
                                                <dt class="col-sm-3">{{ ucfirst(str_replace('_', ' ', $key)) }}</dt>
                                                <dd class="col-sm-9">{{ $value }}</dd>
                                            @endforeach
                                        </dl>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    <!-- Connection Timeline -->
                    @if($connection->start_year || $connection->end_year)
                        <div class="row mt-4">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0">Connection Timeline</h5>
                                    </div>
                                    <div class="card-body">
                                        <p class="mb-0">
                                            @if($connection->start_year)
                                                <strong>Start:</strong> {{ $connection->start_year }}
                                                @if($connection->start_month && $connection->start_day)
                                                    ({{ $connection->start_month }}/{{ $connection->start_day }})
                                                @endif
                                            @endif
                                            
                                            @if($connection->end_year)
                                                <br><strong>End:</strong> {{ $connection->end_year }}
                                                @if($connection->end_month && $connection->end_day)
                                                    ({{ $connection->end_month }}/{{ $connection->end_day }})
                                                @endif
                                            @endif
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection 