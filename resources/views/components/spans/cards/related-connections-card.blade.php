@props(['span'])

@php
    // Only show for connection spans
    if ($span->type_id !== 'connection') {
        return;
    }

    // Find the connection that uses this span as its connection_span_id
    $currentConnection = \App\Models\Connection::where('connection_span_id', $span->id)
        ->with(['subject', 'object', 'type'])
        ->first();

    // If no connection found, don't show the component
    if (!$currentConnection) {
        return;
    }

    // Get the subject and object
    $subject = $currentConnection->subject;
    $object = $currentConnection->object;
    $connectionType = $currentConnection->type_id;

    // Find all connections between the same subject and object with the same type
    // Exclude the current connection and filter to only those with connection spans
    $relatedConnections = \App\Models\Connection::where('type_id', $connectionType)
        ->where(function($query) use ($subject, $object) {
            $query->where(function($q) use ($subject, $object) {
                $q->where('parent_id', $subject->id)
                  ->where('child_id', $object->id);
            });
        })
        ->where('id', '!=', $currentConnection->id)
        ->whereNotNull('connection_span_id')
        ->with(['connectionSpan'])
        ->get()
        ->filter(function($conn) {
            // Filter out connections without connection spans
            return $conn->connectionSpan !== null;
        })
        ->sortBy(function($conn) {
            // Sort by start year, then start month, then start day
            $span = $conn->connectionSpan;
            $year = $span->start_year ?? PHP_INT_MAX;
            $month = $span->start_month ?? PHP_INT_MAX;
            $day = $span->start_day ?? PHP_INT_MAX;
            return sprintf('%08d-%02d-%02d', $year, $month, $day);
        })
        ->values();

    // Don't show the card if there are no related connections
    if ($relatedConnections->isEmpty()) {
        return;
    }
@endphp

<div class="card mb-4">
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
                    $hasDates = $connectionSpan->start_year || $connectionSpan->end_year;
                    $dateText = null;
                    if ($hasDates) {
                        if ($connectionSpan->start_year && $connectionSpan->end_year) {
                            $dateText = ($connectionSpan->formatted_start_date ?? $connectionSpan->start_year) . ' – ' . ($connectionSpan->formatted_end_date ?? $connectionSpan->end_year);
                        } elseif ($connectionSpan->start_year) {
                            $dateText = 'from ' . ($connectionSpan->formatted_start_date ?? $connectionSpan->start_year);
                        } elseif ($connectionSpan->end_year) {
                            $dateText = 'until ' . ($connectionSpan->formatted_end_date ?? $connectionSpan->end_year);
                        }
                    }
                @endphp
                <div class="list-group-item px-0 py-2 border-0 border-bottom">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="flex-grow-1">
                            <a href="{{ route('spans.show', $connectionSpan) }}" 
                               class="text-decoration-none fw-semibold">
                                {{ $connectionSpan->name }}
                            </a>
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
