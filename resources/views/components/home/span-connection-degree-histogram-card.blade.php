@php
    $userId = auth()->id();

    // Accessible, non-connection spans
    $accessibleSpansQuery = \App\Models\Span::where(function ($query) use ($userId) {
            $query->where('access_level', 'public')
                  ->orWhere('owner_id', $userId);
        })
        ->where('type_id', '!=', 'connection');

    $totalSpans = $accessibleSpansQuery->count();

    // Accessible, non-connection spans with at least a start date
    $totalDatedSpans = (clone $accessibleSpansQuery)
        ->whereNotNull('start_year')
        ->count();

    // Base connections query: only where both spans are accessible
    $baseConnections = \App\Models\Connection::whereHas('parent', function ($query) use ($userId) {
            $query->where(function ($q) use ($userId) {
                $q->where('access_level', 'public')
                  ->orWhere('owner_id', $userId);
            })->where('type_id', '!=', 'connection');
        })
        ->whereHas('child', function ($query) use ($userId) {
            $query->where(function ($q) use ($userId) {
                $q->where('access_level', 'public')
                  ->orWhere('owner_id', $userId);
            })->where('type_id', '!=', 'connection');
        });

    // For degree, count each connection once for subject and once for object
    $subjectEndpoints = (clone $baseConnections)
        ->selectRaw('parent_id as span_id');

    $objectEndpoints = (clone $baseConnections)
        ->selectRaw('child_id as span_id');

    $degreeRows = \Illuminate\Support\Facades\DB::query()
        ->fromSub(
            $subjectEndpoints->unionAll($objectEndpoints),
            'span_connections'
        )
        ->selectRaw('span_id, COUNT(*) as connection_count')
        ->groupBy('span_id')
        ->get();

    // Load start_year for spans that have at least one connection
    $spanIdsWithConnections = $degreeRows->pluck('span_id')->all();
    $spanStartYears = !empty($spanIdsWithConnections)
        ? \App\Models\Span::whereIn('id', $spanIdsWithConnections)
            ->pluck('start_year', 'id')
        : collect();

    // Build raw histograms:
    // - $histogramTotal: connection_count => number of spans
    // - $histogramDated: connection_count => number of spans with start_year
    $histogramTotal = [];
    $histogramDated = [];
    foreach ($degreeRows as $row) {
        $count = (int) $row->connection_count;
        $spanId = $row->span_id;
        $hasStartDate = !empty($spanStartYears[$spanId]);

        $histogramTotal[$count] = ($histogramTotal[$count] ?? 0) + 1;

        if ($hasStartDate) {
            $histogramDated[$count] = ($histogramDated[$count] ?? 0) + 1;
        }
    }

    $spansWithConnections = $degreeRows->count();
    $zeroCount = max(0, $totalSpans - $spansWithConnections);
    if ($zeroCount > 0) {
        $histogramTotal[0] = ($histogramTotal[0] ?? 0) + $zeroCount;
    }

    // Work out how many dated spans have at least one connection, then derive zero-degree dated count
    $datedSpansWithConnections = !empty($histogramDated)
        ? array_sum($histogramDated)
        : 0;

    $zeroDatedCount = max(0, $totalDatedSpans - $datedSpansWithConnections);
    if ($zeroDatedCount > 0) {
        $histogramDated[0] = ($histogramDated[0] ?? 0) + $zeroDatedCount;
    }

    if (!empty($histogramTotal)) {
        ksort($histogramTotal);
    }
    if (!empty($histogramDated)) {
        ksort($histogramDated);
    }
@endphp

<div class="card mb-3" id="span-connection-degree-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="h6 mb-0">
            <i class="bi bi-diagram-3 text-info me-2"></i>
            Connections per span
        </h3>
    </div>
    <div class="card-body">
        @if($totalSpans === 0)
            <p class="text-muted small mb-0">
                No spans found yet.
            </p>
        @elseif(empty($histogramTotal))
            <p class="text-muted small mb-0">
                No connections found yet.
            </p>
        @else
            <p class="text-muted small">
                Table showing how many spans have each number of accessible connections (counting both subject and object roles),
                and how many of those spans have at least a start date.
            </p>
            <div class="table-responsive">
                <table class="table table-sm table-bordered mb-0 align-middle w-auto">
                    <thead class="table-light">
                        <tr>
                            <th scope="col" class="text-nowrap">Connections</th>
                            <th scope="col" class="text-nowrap text-end">Spans</th>
                            <th scope="col" class="text-nowrap text-end">Spans with date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($histogramTotal as $degree => $spanCount)
                            @php
                                $label = $degree . ' connection' . ($degree === 1 ? '' : 's');
                                $datedCount = $histogramDated[$degree] ?? 0;
                            @endphp
                            <tr>
                                <td class="text-nowrap">
                                    {{ $label }}
                                </td>
                                <td class="text-end">
                                    {{ number_format($spanCount) }}
                                </td>
                                <td class="text-end">
                                    {{ number_format($datedCount) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="text-muted small mt-2 mb-0">
                Total spans: {{ number_format($totalSpans) }}. Spans with at least a start date: {{ number_format($totalDatedSpans) }}.
            </p>
        @endif
    </div>
</div>

