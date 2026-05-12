@php
    use App\Models\Connection;
@endphp

@props(['span', 'connectionTypes', 'availableSpans'])

<div class="card mb-4">
    <div class="card-body">
        <h2 class="card-title h5 mb-3">Connection Details</h2>

        @php
            // If this is a connection span, get the connection directly
            if ($span->type_id === 'connection') {
                $connection = Connection::where('connection_span_id', $span->id)
                    ->with(['subject', 'object', 'type'])
                    ->first();
            } else {
                // If this is a connected span, find the connection where this span is either subject or object
                $connection = Connection::where(function($query) use ($span) {
                    $query->where('parent_id', $span->id)
                        ->orWhere('child_id', $span->id);
                })
                ->with(['subject', 'object', 'type'])
                ->first();
            }
            
            $subject = $connection?->subject;
            $object = $connection?->object;
        @endphp

        <!-- Hidden fields for form validation -->
        <input type="hidden" name="subject_id" value="{{ $subject?->id }}">
        <input type="hidden" name="connection_type" value="{{ $connection?->type?->type }}">
        <input type="hidden" name="object_id" id="object_id" value="{{ old('object_id', $object?->id) }}">

        <div class="row g-3 mb-4">
            <div class="col-12 col-lg-4">
                <label class="form-label fw-semibold mb-1 d-block" for="connection_subject_display">
                    Subject <span class="fw-normal text-muted">(parent)</span>
                </label>
                <input type="text"
                       id="connection_subject_display"
                       class="form-control"
                       value="{{ $subject?->name ?? 'No subject' }}"
                       readonly
                       title="The subject is the parent end of this connection (parent_id). It cannot be changed here; edit from the subject span if you need to replace it.">
            </div>
            <div class="col-12 col-lg-4">
                <label class="form-label fw-semibold mb-1 d-block" for="connection_predicate_display">
                    Predicate
                </label>
                <input type="text"
                       id="connection_predicate_display"
                       class="form-control"
                       value="{{ $connection?->type?->forward_predicate ?? 'No predicate' }}"
                       readonly
                       title="The connection type cannot be changed here. To use a different type, remove this connection and create a new one.">
            </div>
            <div class="col-12 col-lg-4">
                <label class="form-label fw-semibold mb-1 d-block" for="object_name">
                    Object <span class="fw-normal text-muted">(child)</span>
                </label>
                <input type="text"
                       class="form-control @error('object_id') is-invalid @enderror"
                       id="object_name"
                       name="object_name"
                       value="{{ old('object_name', $object?->name ?? '') }}"
                       placeholder="Search or type object span name…"
                       autocomplete="off"
                       required
                       title="The object is the child end of this connection (child_id). You can point this edge at a different span.">
                @error('object_id')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <!-- Preview -->
        <div class="alert alert-info mb-0">
            <strong>Preview (subject → predicate → object):</strong>
            <span id="connection-preview" class="d-block mt-1">
                @if($subject && $connection && $object)
                    {{ $subject->name }} {{ $connection->type->forward_predicate }} {{ $object->name }}
                @else
                    Enter the object (child) name to see the full sentence.
                @endif
            </span>
        </div>

        @if($span->type_id === 'connection' && $connection && $connection->type_id === 'family')
            <div class="mt-3 pt-3 border-top">
                <button type="button"
                        class="btn btn-outline-warning btn-sm"
                        data-bs-toggle="modal"
                        data-bs-target="#swap-family-ends-modal">
                    Swap subject and object…
                </button>
            </div>

            @push('span-edit-modals')
            <div class="modal fade"
                 id="swap-family-ends-modal"
                 tabindex="-1"
                 aria-labelledby="swap-family-ends-modal-label"
                 aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h2 class="modal-title h5" id="swap-family-ends-modal-label">Swap subject and object?</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p>
                                This exchanges who is stored as the <strong>parent</strong> (subject) and who is stored as the
                                <strong>child</strong> (object) for this <strong>family</strong> link.
                            </p>
                            <p class="mb-0 text-muted small">
                                Only use this if the relationship was saved the wrong way round. Any directional metadata on the connection
                                (for example relationship labels) will be cleared so you can set it again correctly.
                            </p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <form method="post" action="{{ route('spans.connection.swap-family-ends', $span) }}">
                                @csrf
                                <button type="submit" class="btn btn-warning">Swap subject and object</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            @endpush
        @endif
    </div>
</div>

@push('scripts')
<script>
$(document).ready(function() {
    const objectInput = $('#object_name');
    const objectIdInput = $('#object_id');
    const preview = $('#connection-preview');
    
    function updatePreview() {
        const subject = '{{ $subject?->name ?? "" }}';
        const predicate = '{{ $connection?->type?->forward_predicate ?? "" }}';
        const object = objectInput.val() || '';
        
        if (subject && predicate && object) {
            preview.text(`${subject} ${predicate} ${object}`);
        } else {
            preview.text('Enter the object (child) name to see the full sentence.');
        }
    }
    
    objectInput.on('input', updatePreview);

    // Initial preview
    updatePreview();
    
    // TODO: Add autocomplete functionality here
    // The autocomplete should:
    // 1. Search for spans as the user types
    // 2. Update both objectInput (name) and objectIdInput (id) when a span is selected
});
</script>
@endpush 