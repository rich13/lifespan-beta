@props(['span', 'connectionForSpan' => null])

@php
    // Only show for connection spans
    if ($span->type_id !== 'connection') {
        return;
    }

    // Find the connection that uses this span as its connection_span_id (use shared connectionForSpan when provided)
    $currentConnection = $connectionForSpan ?? \App\Models\Connection::where('connection_span_id', $span->id)
        ->with(['parent', 'child', 'type'])
        ->first();

    // If no connection found, don't show the component
    if (!$currentConnection) {
        return;
    }

    $relatedConnections = \App\Support\SpanShowLookups::siblingConnections($currentConnection);

    // Don't show the card if there are no related connections
    if ($relatedConnections->isEmpty()) {
        return;
    }
@endphp

<div class="card mb-4" data-related-connections-card>
    <div class="card-header">
        <h6 class="card-title mb-0">
            <i class="bi bi-link-45deg me-2"></i>
            Related Connections
        </h6>
    </div>
    <div class="card-body p-2">
        <div class="list-group list-group-flush">
            @foreach($relatedConnections as $connection)
                @php
                    $connectionSpan = $connection->connectionSpan;
                    // Skip if connection span doesn't exist
                    if (!$connectionSpan) {
                        continue;
                    }
                    $dateText = $connectionSpan->formatted_date_range;
                @endphp
                <div class="list-group-item px-0 py-2 border-0 border-bottom">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="flex-grow-1">
                            <x-span-link :span="$connectionSpan" class="text-decoration-none fw-semibold" />
                            @if($dateText)
                                <div class="text-muted small">
                                    <i class="bi bi-calendar me-1"></i>{{ $dateText }}
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
