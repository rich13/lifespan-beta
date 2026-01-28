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
<div class="py-4">
    <div class="row">
        <div class="col-12">
            @if($allConnections->count() > 0)
                <x-spans.connections-timeline-card
                    :subject="$subject"
                    :connections="$allConnections"
                    :relevantConnectionTypes="$relevantConnectionTypes"
                    :connectionCounts="$connectionCounts"
                    :connectionTypeDirections="$connectionTypeDirections"
                    :containerId="'connection-timeline-container-' . $subject->id . '-' . $connectionType->type"
                    :initialConnectionType="$connectionType->type"
                />
            @endif
            
            <!-- Connections List -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h4 class="card-title mb-0">{{ ucfirst($connectionType->forward_predicate) }} Connections</h4>
                            @auth
                                @if(auth()->user()->can('update', $subject))
                                    <button type="button" class="btn btn-sm btn-outline-primary" 
                                            data-bs-toggle="modal" data-bs-target="#addConnectionModal"
                                            data-span-id="{{ $subject->id }}" data-span-name="{{ $subject->name }}" data-span-type="{{ $subject->type_id }}">
                                        <i class="bi bi-plus-lg"></i> Add Connection
                                    </button>
                                @endif
                            @endauth
                        </div>
                        <div class="card-body">
                            @if($connections->count() > 0)
                                <div class="connection-spans">
                                    @foreach($connections as $connection)
                                        <x-connections.interactive-card :connection="$connection" :isIncoming="false" />
                                    @endforeach
                                </div>
                                
                                                                        <div class="d-flex justify-content-center mt-4">
                                            <x-pagination :paginator="$connections" />
                                        </div>
                            @else
                                <p class="text-muted mb-0">No {{ $connectionType->forward_predicate }} connections found for {{ $subject->name }}.</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection 