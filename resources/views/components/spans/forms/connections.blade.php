@props(['span', 'connectionTypes', 'availableSpans'])

<div class="card mb-4">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="card-title h5 mb-0">Connections</h2>
            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#newConnectionModal">
                <i class="bi bi-plus-lg"></i> Add Connection
            </button>
        </div>

        @php
            $parentConnections = $span->connectionsAsSubjectWithAccess()
                ->whereNotNull('connection_span_id')
                ->whereHas('connectionSpan')
                ->with(['connectionSpan', 'child', 'type'])
                ->get()
                ->sortBy(function ($connection) {
                    $span = $connection->connectionSpan;
                    return [
                        $span->start_year ?? PHP_INT_MAX,
                        $span->start_month ?? PHP_INT_MAX,
                        $span->start_day ?? PHP_INT_MAX
                    ];
                });

            $childConnections = $span->connectionsAsObjectWithAccess()
                ->whereNotNull('connection_span_id')
                ->whereHas('connectionSpan')
                ->with(['connectionSpan', 'parent', 'type'])
                ->get()
                ->sortBy(function ($connection) {
                    $span = $connection->connectionSpan;
                    return [
                        $span->start_year ?? PHP_INT_MAX,
                        $span->start_month ?? PHP_INT_MAX,
                        $span->start_day ?? PHP_INT_MAX
                    ];
                });
        @endphp

        @if($parentConnections->isEmpty() && $childConnections->isEmpty())
            <p class="text-muted mb-0">No connections yet.</p>
        @else
            <div class="list-group list-group-flush">
                @foreach($parentConnections as $connection)
                    <div class="list-group-item px-0">
                        <x-connections.micro-card :connection="$connection" />
                    </div>
                @endforeach
                @foreach($childConnections as $connection)
                    <div class="list-group-item px-0">
                        <x-connections.micro-card :connection="$connection" />
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>

@push('modals')
<!-- New Connection Modal -->
<div class="modal fade" id="newConnectionModal" tabindex="-1" aria-labelledby="newConnectionModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="newConnectionModalLabel">Create New Connection</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="newConnectionForm" action="{{ route('admin.connections.store') }}" method="POST">
                    @csrf
                    <input type="hidden" name="parent_id" value="{{ $span->id }}">
                    
                    <div class="mb-3">
                        <label for="connection_type" class="form-label">Connection Type</label>
                        <select class="form-select" id="connection_type" name="type" required>
                            <option value="">Select a type...</option>
                            @foreach($connectionTypes as $type)
                                <option value="{{ $type->type }}" 
                                        data-forward="{{ $type->forward_predicate }}" 
                                        data-inverse="{{ $type->inverse_predicate }}"
                                        data-allowed-types='@json($type->allowed_span_types)'>
                                    {{ $type->type }}
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text connection-predicate"></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Direction</label>
                        <div class="btn-group w-100" role="group">
                            <input type="radio" class="btn-check" name="direction" id="direction_forward" value="forward" checked>
                            <label class="btn btn-outline-secondary" for="direction_forward">Forward</label>
                            <input type="radio" class="btn-check" name="direction" id="direction_inverse" value="inverse">
                            <label class="btn btn-outline-secondary" for="direction_inverse">Inverse</label>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="connected_span" class="form-label">Connected To</label>
                        <select class="form-select" id="connected_span" name="child_id" required>
                            <option value="">Select a span...</option>
                            @foreach($availableSpans as $otherSpan)
                                <option value="{{ $otherSpan->id }}" 
                                        data-type="{{ $otherSpan->type_id }}"
                                        data-name="{{ $otherSpan->name }}"
                                        data-type-name="{{ $otherSpan->type->name }}">
                                    {{ $otherSpan->name }} ({{ $otherSpan->type->name }})
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text span-type-hint"></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Preview</label>
                        <div class="alert alert-secondary mb-0">
                            <p class="mb-0">
                                <strong>{{ $span->name }}</strong>
                                <span class="text-muted connection-preview-predicate"></span>
                                <strong class="connection-preview-target"></strong>
                            </p>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Dates</label>
                        <x-spans.forms.date-select 
                            prefix="connection"
                            label="Start Date"
                            :value="null"
                            :showPrecision="false"
                        />
                        <x-spans.forms.date-select 
                            prefix="connection_end"
                            label="End Date (Optional)"
                            :value="null"
                            :showPrecision="false"
                        />
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="newConnectionForm" class="btn btn-primary">Create Connection</button>
            </div>
        </div>
    </div>
</div>
@endpush

@push('scripts')
<script>
$(document).ready(function() {
    const $connectionType = $('#connection_type');
    const $direction = $('input[name="direction"]');
    const $connectedSpan = $('#connected_span');
    const $connectionPreview = $('.connection-preview-predicate');
    const $connectionTarget = $('.connection-preview-target');
    const $connectionPredicate = $('.connection-predicate');
    const $spanTypeHint = $('.span-type-hint');
    
    function updateConnectionPreview() {
        const selectedType = $connectionType.find('option:selected');
        const selectedSpan = $connectedSpan.find('option:selected');
        const direction = $('input[name="direction"]:checked').val();

        if (selectedType.val() && selectedSpan.val()) {
            const predicate = direction === 'forward' 
                ? selectedType.data('forward') 
                : selectedType.data('inverse');
            
            $connectionPreview.text(' ' + predicate + ' ');
            $connectionTarget.text(selectedSpan.data('name'));
            $connectionPredicate.text(predicate);

            // Update allowed types hint
            const allowedTypes = selectedType.data('allowed-types') || [];
            const spanType = selectedSpan.data('type');
            const spanTypeName = selectedSpan.data('type-name');
            
            if (allowedTypes.length > 0) {
                if (allowedTypes.includes(spanType)) {
                    $spanTypeHint.removeClass('text-danger').addClass('text-success')
                        .text(`✓ ${spanTypeName} is an allowed type for this connection`);
                } else {
                    $spanTypeHint.removeClass('text-success').addClass('text-danger')
                        .text(`⚠ ${spanTypeName} is not an allowed type for this connection`);
                }
            } else {
                $spanTypeHint.text('');
            }
        } else {
            $connectionPreview.text('');
            $connectionTarget.text('');
            $connectionPredicate.text('');
            $spanTypeHint.text('');
        }
    }

    $connectionType.change(updateConnectionPreview);
    $direction.change(updateConnectionPreview);
    $connectedSpan.change(updateConnectionPreview);
});
</script>
@endpush 