@props(['span', 'parentConnections' => null, 'childConnections' => null])

@php
    // If connections weren't passed in, fetch them with access control
    if (!$parentConnections) {
        $parentConnections = $span->connectionsAsSubjectWithAccess()
            ->whereNotNull('connection_span_id')
            ->whereHas('connectionSpan')
            ->with(['connectionSpan', 'child', 'type'])
            ->get()
            ->sortBy(function ($connection) {
                return $connection->getEffectiveSortDate();
            });
    }

    if (!$childConnections) {
        $childConnections = $span->connectionsAsObjectWithAccess()
            ->whereNotNull('connection_span_id')
            ->whereHas('connectionSpan')
            ->with(['connectionSpan', 'parent', 'type'])
            ->get()
            ->sortBy(function ($connection) {
                return $connection->getEffectiveSortDate();
            });
    }
@endphp

@if($parentConnections->isNotEmpty() || $childConnections->isNotEmpty())
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h2 class="card-title h5 mb-0">Connections <small class="text-muted">(will become contextual)</small></h2>
            @auth
                @if(auth()->user()->can('update', $span))
                    <button type="button" class="btn btn-sm btn-outline-primary" 
                            data-bs-toggle="modal" data-bs-target="#addConnectionModal"
                            data-span-id="{{ $span->id }}" data-span-name="{{ $span->name }}" data-span-type="{{ $span->type_id }}">
                        <i class="bi bi-plus-lg"></i>
                    </button>
                @endif
            @endauth
        </div>
        
        <div class="card-body">
            @if($parentConnections->isNotEmpty())
            <h3 class="h6 mb-2"><i class="bi bi-box-arrow-in-right me-2"></i>From this span</h3>
            <div class="connection-spans mb-4">
                    @foreach($parentConnections as $connection)
                        @if($connection->connectionSpan)
                            <x-connections.interactive-card :connection="$connection" :isIncoming="false" />
                        @endif
                    @endforeach
                </div>
            @endif

            @if($childConnections->isNotEmpty())
                <h3 class="h6 mb-2"><i class="bi bi-box-arrow-in-left me-2"></i>To this span</h3>
                <div class="connection-spans">
                    @foreach($childConnections as $connection)
                        @if($connection->connectionSpan)
                            <x-connections.interactive-card :connection="$connection" :isIncoming="true" />
                        @endif
                    @endforeach
                </div>
            @endif
        </div>
    </div>
@else
    <!-- Show empty state with add button -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h2 class="card-title h5 mb-0">Connections</h2>
            @auth
                @if(auth()->user()->can('update', $span))
                    <button type="button" class="btn btn-sm btn-outline-primary" 
                            data-bs-toggle="modal" data-bs-target="#addConnectionModal"
                            data-span-id="{{ $span->id }}" data-span-name="{{ $span->name }}" data-span-type="{{ $span->type_id }}">
                        <i class="bi bi-plus-lg"></i>
                    </button>
                @endif
            @endauth
        </div>
        <div class="card-body">
            <p class="text-muted mb-0">No connections yet.</p>
        </div>
    </div>
@endif 