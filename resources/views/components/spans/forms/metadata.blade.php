@props(['span', 'spanType', 'connectionTypes', 'availableSpans'])

<div class="card mb-4">
    <div class="card-body">
        <h2 class="card-title h5 mb-3">Type-Specific Fields</h2>

        @if($span->type_id === 'connection')
            <x-spans.forms.connection 
                :span="$span" 
                :connection-types="$connectionTypes" 
                :available-spans="$availableSpans" 
            />
        @endif

        @if($spanType->metadata['schema'] ?? false)
            @foreach($spanType->metadata['schema'] as $fieldName => $field)
                <div class="mb-3">
                    <label for="metadata_{{ $fieldName }}" class="form-label">{{ $field['label'] }}</label>
                    @if($field['type'] === 'text')
                        <input type="text" class="form-control @error('metadata.' . $fieldName) is-invalid @enderror"
                               id="metadata_{{ $fieldName }}" 
                               name="metadata[{{ $fieldName }}]"
                               value="{{ old('metadata.' . $fieldName, $span->metadata[$fieldName] ?? '') }}">
                    @elseif($field['type'] === 'textarea')
                        <textarea class="form-control @error('metadata.' . $fieldName) is-invalid @enderror"
                                  id="metadata_{{ $fieldName }}" 
                                  name="metadata[{{ $fieldName }}]" 
                                  rows="3">{{ old('metadata.' . $fieldName, $span->metadata[$fieldName] ?? '') }}</textarea>
                    @elseif($field['type'] === 'select')
                        <select class="form-select @error('metadata.' . $fieldName) is-invalid @enderror"
                                id="metadata_{{ $fieldName }}" 
                                name="metadata[{{ $fieldName }}]">
                            <option value="">Select {{ $field['label'] }}</option>
                            @foreach($field['options'] as $option)
                                <option value="{{ $option['value'] ?? $option }}" 
                                        {{ old('metadata.' . $fieldName, $span->metadata[$fieldName] ?? '') == ($option['value'] ?? $option) ? 'selected' : '' }}>
                                    {{ $option['label'] ?? $option }}
                                </option>
                            @endforeach
                        </select>
                    @endif
                    @if(isset($field['help']))
                        <div class="form-text">{{ $field['help'] }}</div>
                    @endif
                    @error('metadata.' . $fieldName)
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            @endforeach
        @else
            <p class="text-muted mb-0">No type-specific fields available.</p>
        @endif
    </div>
</div> 