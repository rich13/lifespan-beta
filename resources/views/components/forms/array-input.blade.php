@props([
    'name',
    'value' => [],
    'label' => null,
    'required' => false,
    'help' => null,
    'itemSchema' => [], // Schema for array items
])

@php
$initialValue = is_array($value) ? array_values($value) : [];
@endphp

<div class="array-input" x-data='{
    items: @json($initialValue),
    
    addItem() {
        this.items.push("");
        setTimeout(() => {
            const inputs = this.$el.querySelectorAll(".array-item input");
            inputs[inputs.length - 1]?.focus();
        }, 50);
    },
    
    removeItem(index) {
        this.items.splice(index, 1);
    },

    isValidUrl(url) {
        if (!url) return true;
        try {
            new URL(url);
            return true;
        } catch {
            return false;
        }
    },

    validateUrl(event, index) {
        const input = event.target;
        const url = input.value.trim();
        if (url && !url.startsWith("http://") && !url.startsWith("https://")) {
            this.items[index] = "https://" + url;
        }
    }
}'>
    @if($label)
        <label class="form-label">
            {{ $label }}
            @if($required)<span class="text-danger">*</span>@endif
        </label>
    @endif

    <div class="array-items mb-2">
        <template x-for="(item, index) in items" :key="index">
            <div class="array-item mb-2">
                <div class="input-group">
                    @if(($itemSchema['type'] ?? '') === 'url')
                        <span class="input-group-text">
                            <i class="bi bi-link-45deg"></i>
                        </span>
                        <input type="url" 
                               class="form-control"
                               :name="'{{ $name }}[]'"
                               x-model="items[index]"
                               :class="{ 'is-invalid': items[index] && !isValidUrl(items[index]) }"
                               :placeholder="'{{ $itemSchema['placeholder'] ?? 'Enter URL' }}'"
                               @blur="validateUrl($event, index)">
                        <button type="button" 
                                class="btn btn-outline-danger" 
                                @click="removeItem(index)"
                                title="Remove this source">
                            <i class="bi bi-trash"></i>
                        </button>
                        <div class="invalid-feedback" x-show="items[index] && !isValidUrl(items[index])">
                            Please enter a valid URL starting with http:// or https://
                        </div>
                    @else
                        <input type="text" 
                               class="form-control"
                               :name="'{{ $name }}[]'"
                               x-model="items[index]"
                               :placeholder="'{{ $itemSchema['placeholder'] ?? '' }}'">
                        <button type="button" 
                                class="btn btn-outline-danger"
                                @click="removeItem(index)">
                            <i class="bi bi-trash"></i>
                        </button>
                    @endif
                </div>
                @if(isset($itemSchema['help']))
                    <div class="form-text mt-1" x-show="index === 0">{{ $itemSchema['help'] }}</div>
                @endif
            </div>
        </template>
    </div>

    <button type="button" 
            class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-1"
            @click="addItem">
        <i class="bi bi-plus-lg"></i>
        <span>Add Source</span>
    </button>

    @if($help)
        <div class="form-text mt-2">{{ $help }}</div>
    @endif

    @error($name)
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
    @error($name . '.*')
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
</div> 