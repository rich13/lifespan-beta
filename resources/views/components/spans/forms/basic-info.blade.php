@props(['span', 'spanTypes'])

<div class="card mb-4">
    <div class="card-body">
        <h2 class="card-title h5 mb-3">Basic Information</h2>

        <!-- Name -->
        <div class="mb-3">
            <label for="name" class="form-label">Name</label>
            <input type="text" class="form-control @error('name') is-invalid @enderror" 
                   id="name" name="name" value="{{ old('name', $span->name) }}" required>
            @error('name')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <!-- Slug -->
        <div class="mb-3">
            <label for="slug" class="form-label">Slug</label>
            <input type="text" class="form-control @error('slug') is-invalid @enderror" 
                   id="slug" name="slug" value="{{ old('slug', $span->slug) }}" 
                   pattern="[a-z0-9-]+" title="Only lowercase letters, numbers, and hyphens allowed">
            <div class="form-text">
                Used in URLs. Only lowercase letters, numbers, and hyphens allowed. Leave blank to auto-generate from name.
            </div>
            @error('slug')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <!-- Type -->
        <div class="mb-3">
            <label for="type_id" class="form-label">Type</label>
            <select class="form-select @error('type_id') is-invalid @enderror" 
                    id="type_id" name="type_id" required>
                @foreach($spanTypes as $type)
                    <option value="{{ $type->type_id }}" 
                            {{ old('type_id', $span->type_id) == $type->type_id ? 'selected' : '' }}>
                        {{ $type->name }}
                    </option>
                @endforeach
            </select>
            @error('type_id')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <!-- Description -->
        <div class="mb-3">
            <label for="description" class="form-label">Description</label>
            <textarea class="form-control @error('description') is-invalid @enderror" 
                      id="description" name="description" rows="3">{{ old('description', $span->description) }}</textarea>
            <div class="form-text">Public description of this span, supports Markdown formatting.</div>
            @error('description')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>
</div> 