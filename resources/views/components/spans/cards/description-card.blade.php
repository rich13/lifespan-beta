@props(['span'])

@php
    // Use the WikipediaSpanMatcherService to add links to the description
    $matcherService = new \App\Services\WikipediaSpanMatcherService();
    
    // Render markdown if present
    $renderedDescription = null;
    if ($span->description) {
        // First render markdown
        $markdownRendered = \Illuminate\Support\Str::markdown($span->description);
        // Then add automatic span links
        $renderedDescription = $matcherService->highlightMatches($markdownRendered);
    }
@endphp

<div class="card mb-4" id="description-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="card-title mb-0">
            <i class="bi bi-file-text me-2"></i>
            Description
        </h6>
        <div class="btn-group">
            @auth
                @can('update', $span)
                    @if($span->description)
                        <!-- Create span button (shown when text is selected) -->
                        <button type="button" class="btn btn-sm btn-primary d-none" id="create-span-from-selection-btn">
                            <i class="bi bi-plus-circle me-1"></i>
                            <span id="create-span-text">Create Span</span>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="edit-description-btn" onclick="toggleDescriptionEdit()">
                            <i class="bi bi-pencil me-1"></i>
                            Edit
                        </button>
                    @endif
                @endcan
                @if(auth()->user()->is_admin && !$span->description)
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="fetchWikidataDescription()">
                        <i class="bi bi-cloud-download me-1"></i>
                        Fetch from Wikidata
                    </button>
                @endif
            @endauth
        </div>
    </div>
    <div class="card-body">
        @if($span->description)
            <!-- View mode -->
            <div id="description-view-mode" class="description-content">               
                <div class="description-text">
                    {!! $renderedDescription !!}
                </div>
            </div>
            
            <!-- Edit mode -->
            <div id="description-edit-mode" class="d-none">
                <textarea class="form-control mb-3" id="description-textarea" rows="8">{{ $span->description }}</textarea>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-primary" onclick="saveDescription()">
                        <i class="bi bi-check-lg me-1"></i>
                        Save
                    </button>
                    <button type="button" class="btn btn-sm btn-secondary" onclick="cancelDescriptionEdit()">
                        <i class="bi bi-x-lg me-1"></i>
                        Cancel
                    </button>
                </div>
                <div class="form-text mt-2">
                    <i class="bi bi-info-circle me-1"></i>
                    You can use Markdown formatting. Existing span links will be preserved automatically.
                </div>
            </div>
        @else
            <!-- Add mode (when no description exists) -->
            <div id="description-add-mode" class="d-none">
                <textarea class="form-control mb-3" id="description-add-textarea" rows="8" placeholder="Enter a description..."></textarea>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-primary" onclick="saveNewDescription()">
                        <i class="bi bi-check-lg me-1"></i>
                        Save
                    </button>
                    <button type="button" class="btn btn-sm btn-secondary" onclick="cancelAddDescription()">
                        <i class="bi bi-x-lg me-1"></i>
                        Cancel
                    </button>
                </div>
                <div class="form-text mt-2">
                    <i class="bi bi-info-circle me-1"></i>
                    You can use Markdown formatting (bold, italic, lists, links, etc.).
                </div>
            </div>
        @endif
        
        <!-- Loading state for Wikidata fetch -->
        <div id="wikidata-loading" class="d-none">
            <div class="text-center py-3">
                <div class="spinner-border spinner-border-sm text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="text-muted small mt-2 mb-0">Fetching description from Wikidata...</p>
            </div>
        </div>
        
        <!-- Error state for Wikidata fetch -->
        <div id="wikidata-error" class="d-none">
            <div class="alert alert-warning alert-sm">
                <i class="bi bi-exclamation-triangle me-1"></i>
                <span id="wikidata-error-message">Failed to fetch description from Wikidata.</span>
            </div>
        </div>
    </div>
</div>

@auth
    @can('update', $span)
        <script>
        let selectedText = '';
        
        // Text selection handling for admin users
        @if(auth()->user()->is_admin)
        $(document).ready(function() {
            const descriptionText = document.querySelector('#description-view-mode .description-text');
            const createButton = document.getElementById('create-span-from-selection-btn');
            const createSpanText = document.getElementById('create-span-text');
            
            if (descriptionText && createButton) {
                // Show button on text selection
                descriptionText.addEventListener('mouseup', function(e) {
                    const selection = window.getSelection();
                    selectedText = selection.toString().trim();
                    
                    if (selectedText.length > 0 && selectedText.length < 100) {
                        // Update button text with selected text
                        const displayText = selectedText.length > 20 
                            ? selectedText.substring(0, 20) + '...' 
                            : selectedText;
                        createSpanText.textContent = 'Create "' + displayText + '"';
                        createButton.classList.remove('d-none');
                    } else {
                        createButton.classList.add('d-none');
                    }
                });
                
                // Hide button when clicking elsewhere
                document.addEventListener('mousedown', function(e) {
                    if (!createButton.contains(e.target) && !descriptionText.contains(e.target)) {
                        createButton.classList.add('d-none');
                        window.getSelection().removeAllRanges();
                    }
                });
                
                // Handle create span button click
                createButton.addEventListener('click', function() {
                    if (selectedText) {
                        // Hide button
                        createButton.classList.add('d-none');
                        window.getSelection().removeAllRanges();
                        
                        // Trigger the new span modal with prefilled name
                        if (typeof window.openNewSpanModalWithName === 'function') {
                            window.openNewSpanModalWithName(selectedText);
                        } else {
                            // Fallback: Open modal and try to prefill
                            $('#newSpanModal').modal('show');
                            
                            // Wait for modal to load and try to set the name
                            setTimeout(function() {
                                const nameInput = document.querySelector('#newSpanModal input[name="name"]');
                                if (nameInput) {
                                    nameInput.value = selectedText;
                                    $(nameInput).trigger('input');
                                }
                            }, 300);
                        }
                    }
                });
            }
        });
        @endif
        
        function toggleDescriptionEdit() {
            $('#description-view-mode').addClass('d-none');
            $('#description-edit-mode').removeClass('d-none');
            $('#edit-description-btn').prop('disabled', true);
        }

        function cancelDescriptionEdit() {
            $('#description-view-mode').removeClass('d-none');
            $('#description-edit-mode').addClass('d-none');
            $('#edit-description-btn').prop('disabled', false);
            // Reset textarea to original value
            $('#description-textarea').val(@json($span->description));
        }

        function showAddDescription() {
            $('#description-no-content').addClass('d-none');
            $('#description-add-mode').removeClass('d-none');
        }

        function cancelAddDescription() {
            $('#description-no-content').removeClass('d-none');
            $('#description-add-mode').addClass('d-none');
            $('#description-add-textarea').val('');
        }

        function saveNewDescription() {
            const newDescription = $('#description-add-textarea').val();
            
            if (!newDescription.trim()) {
                alert('Please enter a description');
                return;
            }
            
            const saveButton = $('#description-add-mode button.btn-primary');
            const cancelButton = $('#description-add-mode button.btn-secondary');
            
            // Disable buttons during save
            saveButton.prop('disabled', true);
            cancelButton.prop('disabled', true);
            saveButton.html('<span class="spinner-border spinner-border-sm me-1"></span>Saving...');
            
            $.ajax({
                url: `/api/spans/${@json($span->id)}/description`,
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                data: JSON.stringify({ description: newDescription }),
                success: function(response) {
                    if (response.success) {
                        // Show success message and reload
                        const alertDiv = $('<div>')
                            .addClass('alert alert-success alert-sm')
                            .html('<i class="bi bi-check-circle me-1"></i>Description added successfully!');
                        
                        $('#description-card .card-body').prepend(alertDiv);
                        
                        setTimeout(() => {
                            window.location.reload();
                        }, 1500);
                    } else {
                        alert(response.message || 'Failed to add description');
                        saveButton.prop('disabled', false);
                        cancelButton.prop('disabled', false);
                        saveButton.html('<i class="bi bi-check-lg me-1"></i>Save');
                    }
                },
                error: function(xhr) {
                    console.error('Error adding description:', xhr);
                    alert('Network error occurred while adding description.');
                    saveButton.prop('disabled', false);
                    cancelButton.prop('disabled', false);
                    saveButton.html('<i class="bi bi-check-lg me-1"></i>Save');
                }
            });
        }

        function saveDescription() {
            const newDescription = $('#description-textarea').val();
            const saveButton = $('#description-edit-mode button.btn-primary');
            const cancelButton = $('#description-edit-mode button.btn-secondary');
            
            // Disable buttons during save
            saveButton.prop('disabled', true);
            cancelButton.prop('disabled', true);
            saveButton.html('<span class="spinner-border spinner-border-sm me-1"></span>Saving...');
            
            $.ajax({
                url: `/api/spans/${@json($span->id)}/description`,
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                data: JSON.stringify({ description: newDescription }),
                success: function(response) {
                    if (response.success) {
                        // Show success message and reload
                        const alertDiv = $('<div>')
                            .addClass('alert alert-success alert-sm')
                            .html('<i class="bi bi-check-circle me-1"></i>Description updated successfully!');
                        
                        $('#description-card .card-body').prepend(alertDiv);
                        
                        setTimeout(() => {
                            window.location.reload();
                        }, 1500);
                    } else {
                        alert(response.message || 'Failed to update description');
                        saveButton.prop('disabled', false);
                        cancelButton.prop('disabled', false);
                        saveButton.html('<i class="bi bi-check-lg me-1"></i>Save');
                    }
                },
                error: function(xhr) {
                    console.error('Error updating description:', xhr);
                    alert('Network error occurred while updating description.');
                    saveButton.prop('disabled', false);
                    cancelButton.prop('disabled', false);
                    saveButton.html('<i class="bi bi-check-lg me-1"></i>Save');
                }
            });
        }
        </script>
    @endcan
    
    @if(auth()->user()->is_admin)
        <script>
        function fetchWikidataDescription() {
            const loadingDiv = document.getElementById('wikidata-loading');
            const errorDiv = document.getElementById('wikidata-error');
            const cardBody = document.querySelector('.card-body');
            
            // Show loading state
            loadingDiv.classList.remove('d-none');
            errorDiv.classList.add('d-none');
            
            // Make API request
            fetch(`/api/spans/${@json($span->id)}/fetch-wikimedia-description`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            })
            .then(response => response.json())
            .then(data => {
                loadingDiv.classList.add('d-none');
                
                if (data.success) {
                    // Show success message before reloading
                    const successMessage = data.wikipedia_url 
                        ? 'Description fetched and Wikipedia source added successfully!'
                        : 'Description fetched successfully!';
                    
                    // Create a temporary success alert
                    const alertDiv = document.createElement('div');
                    alertDiv.className = 'alert alert-success alert-sm';
                    alertDiv.innerHTML = `
                        <i class="bi bi-check-circle me-1"></i>
                        ${successMessage}
                    `;
                    
                    // Insert the alert at the top of the card body
                    const cardBody = document.querySelector('.card-body');
                    cardBody.insertBefore(alertDiv, cardBody.firstChild);
                    
                    // Remove the alert after 3 seconds and reload
                    setTimeout(() => {
                        window.location.reload();
                    }, 3000);
                } else {
                    // Show error
                    document.getElementById('wikidata-error-message').textContent = data.message || 'Failed to fetch description from Wikidata.';
                    errorDiv.classList.remove('d-none');
                }
            })
            .catch(error => {
                loadingDiv.classList.add('d-none');
                document.getElementById('wikidata-error-message').textContent = 'Network error occurred while fetching description.';
                errorDiv.classList.remove('d-none');
                console.error('Error fetching Wikidata description:', error);
            });
        }
        </script>
    @endif
@endauth

<style>
.description-text {
    line-height: 1.6;
    color: #333;
    user-select: text;
    -webkit-user-select: text;
    -moz-user-select: text;
    -ms-user-select: text;
}

.description-text p {
    margin-bottom: 0.75rem;
}

.description-text p:last-child {
    margin-bottom: 0;
}

.description-text a {
    color: #0d6efd;
    text-decoration: none;
    border-bottom: 1px dotted #0d6efd;
    transition: all 0.2s ease;
}

.description-text a:hover {
    color: #0a58ca;
    border-bottom-color: #0a58ca;
    text-decoration: none;
}

.description-text a:focus {
    outline: 2px solid #0d6efd;
    outline-offset: 2px;
}

.description-text::selection {
    background-color: #b3d7ff;
    color: #000;
}

.description-text::-moz-selection {
    background-color: #b3d7ff;
    color: #000;
}

#create-span-from-selection-btn {
    animation: fadeIn 0.2s ease;
    white-space: nowrap;
}

@keyframes fadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}
</style>
