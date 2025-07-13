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
        <div class="card-header d-flex justify-content-between align-items-center" style="cursor: pointer;" 
             data-bs-toggle="collapse" data-bs-target="#connections-collapse-{{ $span->id }}" aria-expanded="false" aria-controls="connections-collapse-{{ $span->id }}">
            <h2 class="card-title h5 mb-0">Connections <small class="text-muted">(will become contextual)</small></h2>
            <i class="bi bi-chevron-down connections-toggle-icon" id="connections-toggle-icon-{{ $span->id }}"></i>
        </div>
        
        <div class="collapse" id="connections-collapse-{{ $span->id }}">
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
    </div>

    <style>
        .connections-toggle-icon {
            transition: transform 0.2s ease-in-out;
        }
        [aria-expanded="true"] .connections-toggle-icon {
            transform: rotate(180deg);
        }
    </style>
    
    <script>
        // Ensure proper toggle functionality
        document.addEventListener('DOMContentLoaded', function() {
            const toggleButton = document.querySelector('[data-bs-target="#connections-collapse-{{ $span->id }}"]');
            const collapseElement = document.getElementById('connections-collapse-{{ $span->id }}');
            
            if (toggleButton && collapseElement) {
                // Listen for collapse events to update aria-expanded
                collapseElement.addEventListener('shown.bs.collapse', function() {
                    toggleButton.setAttribute('aria-expanded', 'true');
                });
                
                collapseElement.addEventListener('hidden.bs.collapse', function() {
                    toggleButton.setAttribute('aria-expanded', 'false');
                });
            }
        });
    </script>
@endif 