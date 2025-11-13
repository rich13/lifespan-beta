@props(['span'])

@php
    // Only show for person spans
    if ($span->type_id !== 'person') {
        return;
    }

    $residenceConnections = $span->connectionsAsSubject()
        ->whereHas('type', function($q) { $q->where('type', 'residence'); })
        ->with(['child', 'connectionSpan'])
        ->get()
        ->sortBy(function($conn) {
            // Use effective sort date helper to build a sortable key
            $parts = $conn->getEffectiveSortDate();
            // Normalise very large values to push unknowns to the end
            $y = $parts[0] ?? PHP_INT_MAX;
            $m = $parts[1] ?? PHP_INT_MAX;
            $d = $parts[2] ?? PHP_INT_MAX;
            return sprintf('%08d-%02d-%02d', $y, $m, $d);
        })
        ->values();
    
    // Don't show the card if there are no residences
    if ($residenceConnections->isEmpty()) {
        return;
    }
@endphp

<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="card-title mb-0">
            <i class="bi bi-house me-2"></i>
            <a href="{{ url('/spans/' . $span->id . '/lived-in') }}" class="text-decoration-none">
                Places Lived
            </a>
        </h6>
    </div>
    <div class="card-body p-2">
        <div class="list-group list-group-flush">
            @foreach($residenceConnections as $connection)
                @php
                    $place = $connection->child;
                    $dates = $connection->connectionSpan;
                    $hasDates = $dates && ($dates->start_year || $dates->end_year);
                    $dateText = null;
                    if ($hasDates) {
                        if ($dates->start_year && $dates->end_year) {
                            $dateText = ($dates->formatted_start_date ?? $dates->start_year) . ' – ' . ($dates->formatted_end_date ?? $dates->end_year);
                        } elseif ($dates->start_year) {
                            $dateText = 'from ' . ($dates->formatted_start_date ?? $dates->start_year);
                        } elseif ($dates->end_year) {
                            $dateText = 'until ' . ($dates->formatted_end_date ?? $dates->end_year);
                        }
                    }
                @endphp
                <div class="list-group-item px-0 py-2 border-0 border-bottom">
                    <div class="d-flex align-items-center">
                        <!-- Place name and dates -->
                        <div class="flex-grow-1">
                            <a href="{{ route('spans.show', $place) }}" 
                               class="text-decoration-none fw-semibold">
                                {{ $place->name }}
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

