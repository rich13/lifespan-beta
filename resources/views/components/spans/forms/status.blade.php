@props(['span'])

<!-- Status -->
<div class="card mb-4">
    <div class="card-body">
        <h2 class="card-title h5 mb-3">Status</h2>
        <div class="mb-3">
            <select class="form-select @error('state') is-invalid @enderror" 
                    name="state" required>
                <option value="draft" {{ old('state', $span->state) == 'draft' ? 'selected' : '' }}>
                    Draft (work in progress)
                </option>
                                                    <option value="placeholder" {{ old('state', $span->state) == 'placeholder' ? 'selected' : '' }}>
                                        Placeholder (collaborative - help needed)
                                    </option>
                <option value="complete" {{ old('state', $span->state) == 'complete' ? 'selected' : '' }}>
                    Complete (ready for viewing)
                </option>
            </select>
            @error('state')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>
</div>

<!-- Internal Notes -->
<div class="card mb-4">
    <div class="card-body">
        <h2 class="card-title h5 mb-3">Internal Notes</h2>
        <div class="mb-3">
            <textarea class="form-control @error('notes') is-invalid @enderror" 
                      id="notes" name="notes" rows="3">{{ old('notes', $span->notes) }}</textarea>
            <div class="form-text">Private notes for editors, not shown publicly.</div>
            @error('notes')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>
</div> 