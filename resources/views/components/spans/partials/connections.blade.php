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
    
    // Special handling for connection spans - find the original connection this span represents
    $originalConnection = null;
    if ($span->type_id === 'connection') {
        $originalConnection = \App\Models\Connection::where('connection_span_id', $span->id)
            ->with(['parent', 'child', 'type', 'connectionSpan'])
            ->first();
    }
@endphp

@if($span->type_id === 'connection' && $originalConnection)
    <!-- Special display for connection spans showing the full nested structure -->
    <div class="card mb-4">
        <div class="card-header">
            <h2 class="card-title h5 mb-0">Connection Structure</h2>
        </div>
        <div class="card-body">
            <!-- Show the original connection this span represents -->
            <div class="mb-3">
                <h6 class="text-muted mb-2">This connection represents:</h6>
                <x-connections.interactive-card :connection="$originalConnection" :isIncoming="false" />
            </div>
            
            <!-- Show nested connections if any -->
            @if($parentConnections->isNotEmpty() || $childConnections->isNotEmpty())
                <div class="mt-4">
                    <h6 class="text-muted mb-2">Additional connections from this connection span:</h6>
                    
                    @if($parentConnections->isNotEmpty())
                        <div class="mb-3">
                            <small class="text-muted">From this connection span:</small>
                            <div class="connection-spans">
                                @foreach($parentConnections as $connection)
                                    @if($connection->connectionSpan)
                                        <x-connections.interactive-card :connection="$connection" :isIncoming="false" />
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if($childConnections->isNotEmpty())
                        <div class="mb-3">
                            <small class="text-muted">To this connection span:</small>
                            <div class="connection-spans">
                                @foreach($childConnections as $connection)
                                    @if($connection->connectionSpan)
                                        <x-connections.interactive-card :connection="$connection" :isIncoming="true" />
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
@elseif($parentConnections->isNotEmpty() || $childConnections->isNotEmpty())
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h2 class="card-title h5 mb-0">Connections <small class="text-muted">(will become contextual)</small></h2>
            <div class="d-flex gap-2">
                <a href="{{ route('spans.all-connections', $span) }}" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-clock-history me-1"></i>
                    Overview
                </a>
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