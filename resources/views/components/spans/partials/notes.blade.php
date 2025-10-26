@props(['span'])

@php
    $isOwner = auth()->check() && (auth()->user()->id === $span->owner_id || auth()->user()->is_admin);
    $hasNotes = !empty($span->notes);
@endphp

@if($hasNotes || $isOwner)
    <div class="card mb-4 mt-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="card-title mb-0">
                <i class="bi bi-sticky me-2"></i>Notes
            </h6>
            @if($isOwner)
                <button type="button" class="btn btn-sm btn-outline-primary edit-notes-btn" 
                        data-span-id="{{ $span->id }}"
                        onclick="toggleNotesEdit(this)">
                    <i class="bi bi-pencil"></i> Edit
                </button>
            @endif
        </div>
        <div class="card-body">
            <div id="notes-view-{{ $span->id }}" class="notes-view">
                @if($hasNotes)
                    <p class="mb-0 text-break" style="white-space: pre-wrap; line-height: 1.6;">{{ $span->notes }}</p>
                @else
                    <p class="text-muted mb-0 fst-italic">No notes yet. Add internal notes for this span.</p>
                @endif
            </div>
            
            <div id="notes-edit-{{ $span->id }}" class="notes-edit" style="display: none;">
                <form class="notes-form" data-span-id="{{ $span->id }}" onsubmit="saveNotes(event, this)">
                    <textarea class="form-control mb-3" name="notes" rows="6" placeholder="Add internal notes...">{{ $span->notes }}</textarea>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-sm btn-primary">
                            <i class="bi bi-check me-1"></i>Save
                        </button>
                        <button type="button" class="btn btn-sm btn-secondary" onclick="toggleNotesEdit(this)">
                            <i class="bi bi-x me-1"></i>Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        function toggleNotesEdit(button) {
            const spanId = button.getAttribute('data-span-id') || button.closest('.notes-form').getAttribute('data-span-id');
            const viewDiv = document.getElementById('notes-view-' + spanId);
            const editDiv = document.getElementById('notes-edit-' + spanId);
            
            if (editDiv.style.display === 'none') {
                viewDiv.style.display = 'none';
                editDiv.style.display = 'block';
                editDiv.querySelector('textarea').focus();
            } else {
                viewDiv.style.display = 'block';
                editDiv.style.display = 'none';
            }
        }
        
        function saveNotes(event, form) {
            event.preventDefault();
            
            const spanId = form.getAttribute('data-span-id');
            const notes = form.querySelector('textarea').value;
            
            fetch(`/admin/spans/${spanId}/notes`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({ notes: notes })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update the view with new notes
                    const viewDiv = document.getElementById('notes-view-' + spanId);
                    if (notes.trim()) {
                        viewDiv.innerHTML = '<p class="mb-0 text-break" style="white-space: pre-wrap; line-height: 1.6;">' + 
                                          notes.replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</p>';
                    } else {
                        viewDiv.innerHTML = '<p class="text-muted mb-0 fst-italic">No notes yet. Add internal notes for this span.</p>';
                    }
                    
                    // Toggle back to view mode
                    toggleNotesEdit(form);
                } else {
                    alert('Failed to save notes: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error saving notes:', error);
                alert('Error saving notes. Please try again.');
            });
        }
    </script>
    @endpush
@endif
