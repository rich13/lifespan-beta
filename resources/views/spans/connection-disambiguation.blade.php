@extends('layouts.app')

@section('title', "Multiple connections: {$subject->name} {$connectionType->forward_predicate} {$object->name}")

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
            'url' => route('spans.all-connections', $subject) . '#' . str_replace(' ', '-', $connectionType->forward_predicate),
            'icon' => $connectionType->type_id,
            'icon_category' => 'connection'
        ],
        [
            'text' => $object->getDisplayTitle(),
            'url' => route('spans.show', $object),
            'icon' => $object->type_id,
            'icon_category' => 'span'
        ],
        [
            'text' => 'Multiple connections',
            'url' => request()->url(),
            'icon' => 'link',
            'icon_category' => 'action'
        ]
    ]" />
@endsection

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        Multiple {{ $connectionType->forward_predicate }} connections
                    </h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">
                        There are {{ $connections->count() }} connections between
                        <a href="{{ route('spans.show', $subject) }}">{{ $subject->name }}</a>
                        and
                        <a href="{{ route('spans.show', $object) }}">{{ $object->name }}</a>.
                        Select one to view its details:
                    </p>
                    <div class="list-group">
                        @foreach($connections as $connection)
                            @php
                                $connectionSpan = $connection->connectionSpan;
                                $dateText = null;
                                if ($connectionSpan) {
                                    if ($connectionSpan->start_year && $connectionSpan->end_year) {
                                        $dateText = ($connectionSpan->formatted_start_date ?? $connectionSpan->start_year) . ' – ' . ($connectionSpan->formatted_end_date ?? $connectionSpan->end_year);
                                    } elseif ($connectionSpan->start_year) {
                                        $dateText = 'from ' . ($connectionSpan->formatted_start_date ?? $connectionSpan->start_year);
                                    } elseif ($connectionSpan->end_year) {
                                        $dateText = 'until ' . ($connectionSpan->formatted_end_date ?? $connectionSpan->end_year);
                                    }
                                }
                            @endphp
                            <a href="{{ route('spans.connection.by-id', ['subject' => $subject, 'predicate' => str_replace(' ', '-', $connectionType->forward_predicate), 'object' => $object, 'connectionSpanId' => $connectionSpan->id]) }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                <span>{{ $connectionSpan->name }}</span>
                                @if($dateText)
                                    <span class="text-muted small">{{ $dateText }}</span>
                                @endif
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
