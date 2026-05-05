@php
    $userId = auth()->id();

    // Base query: only include connections where both spans are accessible
    $baseQuery = \App\Models\Connection::query()
        ->join('spans as subject_spans', 'connections.parent_id', '=', 'subject_spans.id')
        ->join('spans as object_spans', 'connections.child_id', '=', 'object_spans.id')
        ->where(function ($query) use ($userId) {
            $query->where('subject_spans.access_level', 'public')
                  ->orWhere('subject_spans.owner_id', $userId);
        })
        ->where(function ($query) use ($userId) {
            $query->where('object_spans.access_level', 'public')
                  ->orWhere('object_spans.owner_id', $userId);
        });

    // Matrix where span type is the subject
    $subjectRows = (clone $baseQuery)
        ->selectRaw('subject_spans.type_id as span_type, connections.type_id as connection_type, COUNT(*) as count')
        ->groupBy('subject_spans.type_id', 'connections.type_id')
        ->get();

    // Matrix where span type is the object
    $objectRows = (clone $baseQuery)
        ->selectRaw('object_spans.type_id as span_type, connections.type_id as connection_type, COUNT(*) as count')
        ->groupBy('object_spans.type_id', 'connections.type_id')
        ->get();

    $subjectMatrix = [];
    $objectMatrix = [];
    $spanTypeIds = [];
    $connectionTypeIds = [];

    $maxSubjectCount = 0;
    $maxObjectCount = 0;

    foreach ($subjectRows as $row) {
        $subjectMatrix[$row->span_type][$row->connection_type] = (int) $row->count;
        $maxSubjectCount = max($maxSubjectCount, (int) $row->count);
        $spanTypeIds[] = $row->span_type;
        $connectionTypeIds[] = $row->connection_type;
    }

    foreach ($objectRows as $row) {
        $objectMatrix[$row->span_type][$row->connection_type] = (int) $row->count;
        $maxObjectCount = max($maxObjectCount, (int) $row->count);
        $spanTypeIds[] = $row->span_type;
        $connectionTypeIds[] = $row->connection_type;
    }

    $spanTypeIds = array_values(array_unique(array_filter($spanTypeIds)));
    $connectionTypeIds = array_values(array_unique(array_filter($connectionTypeIds)));

    // Load labels
    $spanTypes = !empty($spanTypeIds)
        ? \App\Models\SpanType::whereIn('type_id', $spanTypeIds)->get()->keyBy('type_id')
        : collect();

    $connectionTypes = !empty($connectionTypeIds)
        ? \App\Models\ConnectionType::whereIn('type', $connectionTypeIds)->get()->keyBy('type')
        : collect();

    // Sort IDs for stable display
    sort($spanTypeIds);
    sort($connectionTypeIds);
@endphp

<div class="card mb-3" id="connection-matrix-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="h6 mb-0">
            <i class="bi bi-grid-3x3-gap text-info me-2"></i>
            Connection Matrix
        </h3>
        <div class="btn-group btn-group-sm" role="group" aria-label="Matrix view toggle">
            <input type="radio" class="btn-check" name="matrix-view" id="matrix-subject" autocomplete="off" checked>
            <label class="btn btn-outline-info" for="matrix-subject">As subject</label>

            <input type="radio" class="btn-check" name="matrix-view" id="matrix-object" autocomplete="off">
            <label class="btn btn-outline-info" for="matrix-object">As object</label>
        </div>
    </div>
    <div class="card-body">
        @if(empty($spanTypeIds) || empty($connectionTypeIds))
            <p class="text-muted small mb-0">
                No accessible connections found to build the matrix.
            </p>
        @else
            <p class="text-muted small">
                Counts of accessible connections by span type (rows) and connection type (columns),
                viewed either when the span type is the subject or the object.
            </p>

            {{-- Subject matrix --}}
            <div id="matrix-subject-view" class="matrix-view">
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th scope="col" class="text-nowrap">Span type \ Connection type</th>
                                @foreach($connectionTypeIds as $connectionTypeId)
                                    @php
                                        $connectionType = $connectionTypes->get($connectionTypeId);
                                        $label = $connectionType
                                            ? ($connectionType->forward_predicate ?? ucfirst($connectionTypeId))
                                            : ucfirst($connectionTypeId);
                                    @endphp
                                    <th scope="col" class="text-center text-nowrap">
                                        {{ $label }}
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($spanTypeIds as $spanTypeId)
                                @php
                                    $spanType = $spanTypes->get($spanTypeId);
                                    $spanLabel = $spanType ? $spanType->name : ucfirst($spanTypeId);
                                @endphp
                                <tr>
                                    <th scope="row" class="text-nowrap">
                                        {{ $spanLabel }}
                                    </th>
                                    @foreach($connectionTypeIds as $connectionTypeId)
                                        @php
                                            $count = $subjectMatrix[$spanTypeId][$connectionTypeId] ?? 0;
                                            $max = $maxSubjectCount > 0 ? $maxSubjectCount : 1;
                                            $intensity = $count > 0 ? ($count / $max) : 0;
                                            // Map intensity to alpha between 0.15 and 0.9 for non-zero cells
                                            $alpha = $count > 0 ? (0.15 + 0.75 * $intensity) : 0;
                                            $bgColour = $count > 0 ? 'rgba(13, 110, 253, ' . $alpha . ')' : 'transparent';
                                            $textClass = $alpha > 0.55 ? 'text-white' : 'text-dark';
                                        @endphp
                                        <td class="text-center {{ $count > 0 ? $textClass : 'text-muted small' }}"
                                            @if($count > 0)
                                                style="background-color: {{ $bgColour }};"
                                            @endif
                                        >
                                            {{ $count > 0 ? number_format($count) : '–' }}
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Object matrix --}}
            <div id="matrix-object-view" class="matrix-view" style="display: none;">
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th scope="col" class="text-nowrap">Span type \ Connection type</th>
                                @foreach($connectionTypeIds as $connectionTypeId)
                                    @php
                                        $connectionType = $connectionTypes->get($connectionTypeId);
                                        $label = $connectionType
                                            ? ($connectionType->forward_predicate ?? ucfirst($connectionTypeId))
                                            : ucfirst($connectionTypeId);
                                    @endphp
                                    <th scope="col" class="text-center text-nowrap">
                                        {{ $label }}
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($spanTypeIds as $spanTypeId)
                                @php
                                    $spanType = $spanTypes->get($spanTypeId);
                                    $spanLabel = $spanType ? $spanType->name : ucfirst($spanTypeId);
                                @endphp
                                <tr>
                                    <th scope="row" class="text-nowrap">
                                        {{ $spanLabel }}
                                    </th>
                                    @foreach($connectionTypeIds as $connectionTypeId)
                                        @php
                                            $count = $objectMatrix[$spanTypeId][$connectionTypeId] ?? 0;
                                            $max = $maxObjectCount > 0 ? $maxObjectCount : 1;
                                            $intensity = $count > 0 ? ($count / $max) : 0;
                                            $alpha = $count > 0 ? (0.15 + 0.75 * $intensity) : 0;
                                            $bgColour = $count > 0 ? 'rgba(13, 110, 253, ' . $alpha . ')' : 'transparent';
                                            $textClass = $alpha > 0.55 ? 'text-white' : 'text-dark';
                                        @endphp
                                        <td class="text-center {{ $count > 0 ? $textClass : 'text-muted small' }}"
                                            @if($count > 0)
                                                style="background-color: {{ $bgColour }};"
                                            @endif
                                        >
                                            {{ $count > 0 ? number_format($count) : '–' }}
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</div>

<script>
$(document).ready(function () {
    var $card = $('#connection-matrix-card');

    $card.find('input[name="matrix-view"]').on('change', function () {
        if ($('#matrix-subject').is(':checked')) {
            $card.find('#matrix-subject-view').show();
            $card.find('#matrix-object-view').hide();
        } else {
            $card.find('#matrix-subject-view').hide();
            $card.find('#matrix-object-view').show();
        }
    });
});
</script>

