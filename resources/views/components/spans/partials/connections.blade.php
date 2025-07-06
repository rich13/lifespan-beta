@props(['span', 'parentConnections' => null, 'childConnections' => null])

@php
    // If connections weren't passed in, fetch them
    if (!$parentConnections) {
        $parentConnections = $span->connectionsAsSubject()
            ->whereNotNull('connection_span_id')
            ->whereHas('connectionSpan')
            ->with(['connectionSpan', 'child', 'type'])
            ->get()
            ->sortBy(function ($connection) {
                return $connection->getEffectiveSortDate();
            });
    }

    if (!$childConnections) {
        $childConnections = $span->connectionsAsObject()
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
        <div class="card-body">
            <h2 class="card-title h5 mb-3">Connections</h2>
            
            @if($parentConnections->isNotEmpty())
            <h3 class="h6 mb-2"><i class="bi bi-box-arrow-in-right me-2"></i>From this span</h3>
            <div class="connection-spans mb-4">
                    @foreach($parentConnections as $connection)
                        @if($connection->connectionSpan)
                            <x-connections.interactive-card :connection="$connection" />
                        @endif
                    @endforeach
                </div>
            @endif

            @if($childConnections->isNotEmpty())
                <h3 class="h6 mb-2"><i class="bi bi-box-arrow-in-left me-2"></i>To this span</h3>
                <div class="connection-spans">
                    @foreach($childConnections as $connection)
                        @if($connection->connectionSpan)
                            <x-connections.interactive-card :connection="$connection" />
                        @endif
                    @endforeach
                </div>
            @endif
        </div>
    </div>
@endif 