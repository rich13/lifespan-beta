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
            <!-- Connection Type Navigation -->
            @if($relevantConnectionTypes->count() > 1)
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title mb-3">
                            <i class="bi bi-diagram-3 me-2"></i>
                            Connection Types
                        </h5>
                        <div class="d-flex flex-wrap gap-2">
                            <a href="{{ route('spans.all-connections', $subject) }}" 
                               class="btn btn-sm btn-outline-secondary">
                                All Connections
                            </a>
                            @foreach($relevantConnectionTypes as $type)
                                @php
                                    $isCurrent = $type->type === $connectionType->type;
                                    $hasConnections = $type->connection_count > 0;
                                    $routePredicate = str_replace(' ', '-', $type->forward_predicate);
                                    $url = route('spans.connections', ['subject' => $subject, 'predicate' => $routePredicate]);
                                @endphp
                                @if($hasConnections)
                                    <a href="{{ $url }}" 
                                       class="btn btn-sm {{ $isCurrent ? 'btn-primary' : 'btn-secondary' }}"
                                       style="{{ !$isCurrent ? 'background-color: var(--connection-' . $type->type . '-color, #007bff); border-color: var(--connection-' . $type->type . '-color, #007bff); color: white;' : '' }}">
                                        {{ ucfirst($type->forward_predicate) }}
                                        <span class="badge bg-secondary ms-1">{{ $type->connection_count }}</span>
                                    </a>
                                @else
                                    <span class="btn btn-sm btn-outline-secondary disabled" style="opacity: 0.5;">
                                        {{ ucfirst($type->forward_predicate) }}
                                        <span class="badge bg-secondary ms-1">0</span>
                                    </span>
                                @endif
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

            <!-- Timeline for this connection type -->
            @if($connections->count() > 0)
                <x-spans.connection-type-timeline :span="$subject" :connectionType="$connectionType" :connections="$connections" />
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