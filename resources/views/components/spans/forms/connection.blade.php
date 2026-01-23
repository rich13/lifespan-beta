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

        <!-- SPO Sentence -->
        <div class="mb-4">
            <label class="form-label">Connection</label>
            <div class="input-group">
                <input type="text" 
                       class="form-control" 
                       value="{{ $subject?->name ?? 'No subject' }}" 
                       readonly
                       title="The subject (parent) of this connection cannot be changed here. To change it, edit the connection from the subject's page.">
                
                <input type="text" 
                       class="form-control" 
                       value="{{ $connection?->type?->forward_predicate ?? 'No predicate' }}" 
                       readonly
                       title="The connection type (predicate) cannot be changed here. To change it, delete and recreate the connection with a different type.">

                <input type="text" 
                       class="form-control @error('object_id') is-invalid @enderror" 
                       id="object_name" 
                       name="object_name" 
                       value="{{ old('object_name', $object?->name ?? '') }}" 
                       placeholder="Search for object..."
                       autocomplete="off"
                       required>
                @error('object_id')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="form-text">
                <small class="text-muted">
                    You can change the object (child) of this connection. The subject and connection type are fixed and cannot be changed here.
                </small>
            </div>
        </div>

        <!-- Preview -->
        <div class="alert alert-info mb-0">
            <strong>Preview:</strong> 
            <span id="connection-preview">
                @if($subject && $connection && $object)
                    {{ $subject->name }} {{ $connection->type->forward_predicate }} {{ $object->name }}
                @else
                    Select an object to see preview
                @endif
            </span>
        </div>
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
            preview.text('Enter an object name to see preview');
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