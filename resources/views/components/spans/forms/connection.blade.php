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

            // Get the allowed span types for the current connection type
            $allowedObjectTypes = [];
            if ($connection?->type) {
                $allowedObjectTypes = $connection->type->allowed_span_types['child'] ?? [];
            }

            // Filter available spans based on allowed types
            $filteredSpans = $availableSpans->filter(function($span) use ($allowedObjectTypes) {
                return $span->type_id !== 'connection' && in_array($span->type_id, $allowedObjectTypes);
            });
        @endphp

        <!-- Hidden fields for form validation -->
        <input type="hidden" name="subject_id" value="{{ $subject?->id }}">
        <input type="hidden" name="connection_type" value="{{ $connection?->type?->type }}">

        <!-- SPO Sentence -->
        <div class="mb-4">
            <label class="form-label">Connection</label>
            <div class="input-group">
                <input type="text" 
                       class="form-control" 
                       value="{{ $subject?->name ?? 'No subject' }}" 
                       readonly>
                
                <input type="text" 
                       class="form-control" 
                       value="{{ $connection?->type?->forward_predicate ?? 'No predicate' }}" 
                       readonly>

                <select class="form-select @error('object_id') is-invalid @enderror" 
                        id="object_id" 
                        name="object_id" 
                        required>
                    <option value="">Select Object</option>
                    @foreach($filteredSpans as $availableSpan)
                        <option value="{{ $availableSpan->id }}" 
                                {{ old('object_id', $object?->id) == $availableSpan->id ? 'selected' : '' }}>
                            {{ $availableSpan->name }}
                            ({{ $availableSpan->type_id }})
                        </option>
                    @endforeach
                </select>
                @error('object_id')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
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
    const objectSelect = $('#object_id');
    const preview = $('#connection-preview');
    
    function updatePreview() {
        const subject = '{{ $subject?->name ?? "" }}';
        const predicate = '{{ $connection?->type?->forward_predicate ?? "" }}';
        const object = objectSelect.find('option:selected').text().split(' (')[0] || '';
        
        if (subject && predicate && object) {
            preview.text(`${subject} ${predicate} ${object}`);
        } else {
            preview.text('Select an object to see preview');
        }
    }
    
    objectSelect.on('change', updatePreview);

    // Initial preview
    updatePreview();
});
</script>
@endpush 