<!-- Create Note Modal -->
<script>
// Initialize global storage immediately - BEFORE anything else
window.currentAnnotationSpan = {
    id: null,
    name: null
};

// Attach show.bs.modal handler immediately at page load
// This runs BEFORE the IIFE, so it's ready from the start
document.addEventListener('DOMContentLoaded', function() {
    const createNoteModal = document.getElementById('createNoteModal');
    if (createNoteModal) {
        createNoteModal.addEventListener('show.bs.modal', function(event) {
            // Get the button that triggered the modal
            const triggerButton = event.relatedTarget;
            if (triggerButton) {
                const spanId = triggerButton.getAttribute('data-span-id');
                const spanName = triggerButton.getAttribute('data-span-name');
                // Store globally
                window.currentAnnotationSpan.id = spanId;
                window.currentAnnotationSpan.name = spanName;
                // Update modal title
                const noteAboutSpan = document.getElementById('noteAboutSpan');
                if (noteAboutSpan && spanName) {
                    noteAboutSpan.textContent = ' about ' + spanName;
                }
            } else {
                console.warn('No trigger button found in event.relatedTarget');
            }
            
            // Reset form and set dates to today
            document.getElementById('createNoteForm').reset();
            document.getElementById('createNoteStatus').innerHTML = '';
            
            // Set dates to today
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('noteDate').value = today;
            document.getElementById('noteDateEnd').value = today;
            
            // Reset access level to private
            document.getElementById('noteAccessPrivate').checked = true;
            toggleGroupSelection();
            
            // Load user's groups
            loadUserGroups();
        });
    }
});
</script>

<script>
// Define all functions immediately when component loads
window.toggleGroupSelection = function() {
    const accessLevel = document.querySelector('input[name="access_level"]:checked').value;
    const groupSelectionDiv = document.getElementById('groupSelectionDiv');
    
    if (accessLevel === 'shared') {
        groupSelectionDiv.classList.remove('d-none');
    } else {
        groupSelectionDiv.classList.add('d-none');
    }
}

window.loadUserGroups = function() {
    fetch('/user/groups', {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.groups) {
            window.renderGroupCheckboxes(data.groups);
        }
    })
    .catch(error => {
        console.error('Error loading groups:', error);
    });
}

window.renderGroupCheckboxes = function(groups) {
    const container = document.getElementById('groupCheckboxes');
    
    if (!groups || groups.length === 0) {
        container.innerHTML = '<p class="text-muted small mb-0">No groups available</p>';
        return;
    }
    
    let html = '';
    groups.forEach(group => {
        html += `
            <div class="form-check">
                <input class="form-check-input group-checkbox" type="checkbox" 
                       id="group_${group.id}" value="${group.id}" name="groups">
                <label class="form-check-label small" for="group_${group.id}">
                    ${group.name}
                </label>
            </div>
        `;
    });
    
    container.innerHTML = html;
}

window.submitCreateNoteForm = function() {
    const form = document.getElementById('createNoteForm');
    const spinner = document.getElementById('createNoteSpinner');
    const submitBtn = document.getElementById('createNoteSubmitBtn');
    const statusDiv = document.getElementById('createNoteStatus');
    
    const formData = new FormData(form);
    
    // Get span_id from global storage
    const spanId = window.currentAnnotationSpan.id;
    
    if (!spanId) {
        console.error('No span ID available!');
        statusDiv.innerHTML = '<div class="alert alert-danger">Error: No span selected. Please refresh and try again.</div>';
        return false;
    }
    
    // Collect selected groups
    const selectedGroups = [];
    document.querySelectorAll('.group-checkbox:checked').forEach(checkbox => {
        selectedGroups.push(checkbox.value);
    });
    
    spinner.classList.remove('d-none');
    submitBtn.disabled = true;
    statusDiv.innerHTML = '';
    
    fetch('/notes/create', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({
            span_id: spanId,
            description: formData.get('description'),
            tags: formData.get('tags'),
            state: formData.get('state'),
            access_level: formData.get('access_level'),
            groups: selectedGroups,
            note_date: formData.get('note_date'),
            note_date_end: formData.get('note_date_end')
        })
    })
    .then(response => response.json())
    .then(data => {
        spinner.classList.add('d-none');
        
        if (data.success) {
            // Show success message
            const successAlert = document.createElement('div');
            successAlert.className = 'alert alert-success alert-dismissible fade show';
            successAlert.innerHTML = `
                <i class="bi bi-check-circle me-2"></i>
                <strong>Success!</strong> Note created and connected.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            statusDiv.appendChild(successAlert);
            
            // Reset form
            form.reset();
            
            // Close modal and reload after 1 second
            setTimeout(() => {
                bootstrap.Modal.getInstance(document.getElementById('createNoteModal')).hide();
                window.location.reload();
            }, 1000);
        } else {
            // Show error message
            const errorAlert = document.createElement('div');
            errorAlert.className = 'alert alert-danger alert-dismissible fade show';
            errorAlert.innerHTML = `
                <i class="bi bi-exclamation-circle me-2"></i>
                <strong>Error:</strong> ${data.message || 'Failed to create note'}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            statusDiv.appendChild(errorAlert);
            submitBtn.disabled = false;
            console.error('Note creation failed:', data);
        }
    })
    .catch(error => {
        spinner.classList.add('d-none');
        submitBtn.disabled = false;
        const errorAlert = document.createElement('div');
        errorAlert.className = 'alert alert-danger alert-dismissible fade show';
        errorAlert.innerHTML = `
            <i class="bi bi-exclamation-circle me-2"></i>
            <strong>Error:</strong> ${error.message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        statusDiv.appendChild(errorAlert);
        console.error('Error creating note:', error);
    });
    return false;
}
</script>

<div class="modal fade" id="createNoteModal" tabindex="-1" aria-labelledby="createNoteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createNoteModalLabel">
                    <i class="bi bi-chat-square-text me-2"></i>Create Note
                    <span id="noteAboutSpan"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="createNoteStatus"></div>
                <form id="createNoteForm" method="POST" onsubmit="return submitCreateNoteForm(); return false;">
                    @csrf
                    <input type="hidden" id="noteSpanId" name="span_id">
                    
                    <!-- Note Content -->
                    <div class="mb-3">
                        <label for="noteDescription" class="form-label fw-medium">Note Content</label>
                        <textarea class="form-control" id="noteDescription" name="description" 
                                  rows="6" placeholder="Write your note..." required></textarea>
                    </div>
                    
                    <!-- Tags -->
                    <div class="mb-3">
                        <label for="noteTags" class="form-label fw-medium">Tags</label>
                        <input type="text" class="form-control" id="noteTags" name="tags" 
                               placeholder="e.g. important, follow-up, verified">
                    </div>
                    
                    <!-- Date Range -->
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="noteDate" class="form-label fw-medium">Date</label>
                            <input type="date" class="form-control" id="noteDate" name="note_date" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="noteDateEnd" class="form-label fw-medium">End Date (optional)</label>
                            <input type="date" class="form-control" id="noteDateEnd" name="note_date_end">
                        </div>
                    </div>
                    
                    <!-- Access Level -->
                    <div class="mb-3">
                        <label class="form-label fw-medium">Access Level</label>
                        <div class="btn-group w-100" role="group" aria-label="Note Access Level">
                            <input type="radio" class="btn-check" name="access_level" id="noteAccessPrivate" value="private" autocomplete="off" checked onchange="toggleGroupSelection()">
                            <label class="btn btn-outline-secondary" for="noteAccessPrivate">Private</label>
                            <input type="radio" class="btn-check" name="access_level" id="noteAccessShared" value="shared" autocomplete="off" onchange="toggleGroupSelection()">
                            <label class="btn btn-outline-secondary" for="noteAccessShared">Shared</label>
                        </div>
                    </div>
                    
                    <!-- Group Selection (shown when shared) -->
                    <div class="mb-3 d-none" id="groupSelectionDiv">
                        <label class="form-label fw-medium">Share With Groups</label>
                        <div id="groupCheckboxes" class="border rounded p-2" style="max-height: 200px; overflow-y: auto;">
                            <!-- Will be populated by JavaScript -->
                        </div>
                    </div>
                    
                    <!-- State -->
                    <div class="mb-3">
                        <label class="form-label fw-medium">State</label>
                        <div class="btn-group w-100" role="group" aria-label="Note State">
                            <input type="radio" class="btn-check" name="state" id="noteStateDraft" value="draft" autocomplete="off" checked>
                            <label class="btn btn-outline-secondary" for="noteStateDraft">Draft</label>
                            <input type="radio" class="btn-check" name="state" id="noteStateComplete" value="complete" autocomplete="off">
                            <label class="btn btn-outline-secondary" for="noteStateComplete">Complete</label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary" id="createNoteSubmitBtn" form="createNoteForm">
                    <span class="spinner-border spinner-border-sm me-1 d-none" id="createNoteSpinner" role="status" aria-hidden="true"></span>
                    <i class="bi bi-plus-circle me-1"></i>Create Note
                </button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
// Self-executing initialization
(function() {
    function initializeCreateNoteModal() {
        // Handle submit button click directly since button is outside form
        const submitBtn = document.getElementById('createNoteSubmitBtn');
        if (submitBtn) {
            submitBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                submitCreateNoteForm();
            });
        } else {
            console.warn('Submit button not found');
        }
    }
    
    // Try to initialize immediately if DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeCreateNoteModal);
    } else {
        // DOM is already loaded
        setTimeout(initializeCreateNoteModal, 100);
    }
})();
</script>
@endpush
