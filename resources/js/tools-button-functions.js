// Tools button functions for star and info actions

// Expose functions to global scope for onclick handlers
window.toggleStar = function(button, modelId, modelClass) {
    console.log('Toggle star clicked:', { modelId, modelClass });
    
    const icon = button.querySelector('i');
    const isStarred = icon.classList.contains('bi-star-fill');
    
    if (isStarred) {
        // Unstar
        icon.classList.remove('bi-star-fill', 'text-warning');
        icon.classList.add('bi-star');
        button.classList.remove('btn-warning');
        button.classList.add('btn-outline-warning');
    } else {
        // Star
        icon.classList.remove('bi-star');
        icon.classList.add('bi-star-fill', 'text-warning');
        button.classList.remove('btn-outline-warning');
        button.classList.add('btn-warning');
    }
    
    // TODO: Send AJAX request to save star state
    // fetch('/api/star', {
    //     method: 'POST',
    //     headers: {
    //         'Content-Type': 'application/json',
    //         'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
    //     },
    //     body: JSON.stringify({
    //         model_id: modelId,
    //         model_class: modelClass,
    //         starred: !isStarred
    //     })
    // });
}

// Show info functionality
window.showInfo = function(button, modelId, modelClass) {
    console.log('Show info clicked:', { modelId, modelClass });
    
    // Create a simple info popup
    const infoContent = `
        <div class="p-3">
            <h6 class="mb-2">Item Information</h6>
            <p class="mb-1"><strong>ID:</strong> ${modelId}</p>
            <p class="mb-1"><strong>Type:</strong> ${modelClass.split('\\').pop()}</p>
            <p class="mb-0"><strong>Created:</strong> ${new Date().toLocaleDateString()}</p>
        </div>
    `;
    
    // Use Bootstrap tooltip or create a custom popup
    // For now, just show an alert
    alert(`Info for ${modelClass.split('\\').pop()} (ID: ${modelId})`);
    
    // TODO: Implement proper info modal or popup
    // const modal = new bootstrap.Modal(document.getElementById('infoModal'));
    // document.getElementById('infoModalContent').innerHTML = infoContent;
    // modal.show();
}

// Open access level modal
window.openAccessLevelModal = function(button) {
    const modelId = button.getAttribute('data-model-id');
    const modelClass = button.getAttribute('data-model-class');
    const currentLevel = button.getAttribute('data-current-level');
    
    console.log('Opening access level modal:', { modelId, modelClass, currentLevel });
    
    // Store the button reference for use in the modal
    window.currentAccessLevelButton = button;
    
    // Pre-select the current level
    document.querySelectorAll('input[name="accessLevel"]').forEach(radio => {
        radio.checked = radio.value === currentLevel;
    });
    
    // Show the modal
    const modal = new bootstrap.Modal(document.getElementById('accessLevelModal'));
    modal.show();
}

// Load sets data for the modal
function loadSetsData(modelId, modelClass) {
    fetch(`/sets/modal-data?model_id=${modelId}&model_class=${encodeURIComponent(modelClass)}`, {
        method: 'GET',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        console.log('Sets data loaded:', data);
        updateSetsModalContent(data, modelId, modelClass);
    })
    .catch(error => {
        console.error('Error loading sets data:', error);
        const modalContent = document.getElementById('setsModalContent');
        modalContent.innerHTML = `
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle me-2"></i>
                Failed to load sets. Please try again.
            </div>
        `;
    });
}

// Update the sets modal content with the loaded data
function updateSetsModalContent(data, modelId, modelClass) {
    const modalContent = document.getElementById('setsModalContent');
    
    let content = '';
    
    // Show item summary
    if (data.itemSummary) {
        if (data.itemSummary.type === 'span') {
            content += `
                <div class="alert alert-info mb-3">
                    <i class="bi bi-person me-2"></i>
                    <strong>Add "${data.itemSummary.name}" to...</strong>
                    <div class="text-muted small">${data.itemSummary.type_name}</div>
                </div>
            `;
        } else if (data.itemSummary.type === 'connection') {
            content += `
                <div class="alert alert-info mb-3">
                    <i class="bi bi-arrow-left-right me-2"></i>
                    <strong>Connection: ${data.itemSummary.subject} → ${data.itemSummary.object}</strong>
                    <div class="text-muted small">${data.itemSummary.type_name}</div>
                </div>
            `;
        }
    }
    
    // Show add options for connections
    if (data.addOptions && data.addOptions.length > 1) {
        content += `
            <div class="mb-3">
                <label class="form-label fw-bold">Choose what to add:</label>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="addOption" id="addOption_${data.addOptions[0].id}" value="${data.addOptions[0].id}" checked>
                    <label class="form-check-label" for="addOption_${data.addOptions[0].id}">
                        <i class="bi bi-arrow-left-right me-1"></i>
                        ${data.addOptions[0].label} (Connection)
                    </label>
                </div>
        `;
        
        for (let i = 1; i < data.addOptions.length; i++) {
            const option = data.addOptions[i];
            const icon = option.type === 'subject' ? 'bi-person' : 'bi-person';
            content += `
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="addOption" id="addOption_${option.id}" value="${option.id}">
                    <label class="form-check-label" for="addOption_${option.id}">
                        <i class="bi ${icon} me-1"></i>
                        ${option.label} (${option.type === 'subject' ? 'Subject' : 'Object'})
                    </label>
                </div>
            `;
        }
        
        content += `</div>`;
    }
    
    // Show sets
    if (data.sets && data.sets.length > 0) {
        content += `<div class="mb-3"><label class="form-label fw-bold">Select a set:</label></div>`;
        
        data.sets.forEach(set => {
            // For connections, we'll determine membership based on the selected option
            // For spans, use the current memberships directly
            let isInSet = false;
            if (data.addOptions && data.addOptions.length > 1) {
                // For connections, we'll update this dynamically based on selection
                const selectedOption = document.querySelector('input[name="addOption"]:checked');
                if (selectedOption && data.membershipDetails && data.membershipDetails[selectedOption.value]) {
                    isInSet = data.membershipDetails[selectedOption.value].includes(set.id);
                }
            } else {
                // For spans, use the current memberships
                isInSet = data.currentMemberships && data.currentMemberships.includes(set.id);
            }
            
            const buttonClass = isInSet ? 'btn-outline-success' : 'btn-outline-primary';
            const buttonText = isInSet ? 'Remove from Set' : 'Add to Set';
            const buttonIcon = isInSet ? 'bi-archive-fill' : 'bi-archive';
            
            content += `
                <div class="card mb-2">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-1">${set.name}</h6>
                            <p class="card-text text-muted small mb-0">${set.description || 'No description'}</p>
                        </div>
                        <button type="button" 
                                class="btn ${buttonClass} btn-sm toggle-set-membership-btn"
                                data-set-id="${set.id}"
                                data-model-id="${modelId}"
                                data-model-class="${modelClass}"
                                data-is-member="${isInSet}">
                            <i class="bi ${buttonIcon} me-1"></i>
                            ${buttonText}
                        </button>
                    </div>
                </div>
            `;
        });
    } else {
        content += `
            <div class="alert alert-info">
                <i class="bi bi-info-circle me-2"></i>
                You don't have any sets yet. 
                <a href="/sets" class="alert-link">Create your first set</a> to start organizing items.
            </div>
        `;
    }
    
    modalContent.innerHTML = content;
    
    // Add event listeners for radio button changes if we have multiple options
    if (data.addOptions && data.addOptions.length > 1 && data.membershipDetails) {
        const radioButtons = modalContent.querySelectorAll('input[name="addOption"]');
        radioButtons.forEach(radio => {
            radio.addEventListener('change', function() {
                updateSetButtonStates(data.membershipDetails, this.value);
            });
        });
    }
}

// Update set button states based on the selected option
function updateSetButtonStates(membershipDetails, selectedOptionValue) {
    const buttons = document.querySelectorAll('.toggle-set-membership-btn');
    const selectedMemberships = membershipDetails[selectedOptionValue] || [];
    
    buttons.forEach(button => {
        const setId = button.getAttribute('data-set-id');
        const isInSet = selectedMemberships.includes(parseInt(setId));
        
        const buttonClass = isInSet ? 'btn-outline-success' : 'btn-outline-primary';
        const buttonText = isInSet ? 'Remove from Set' : 'Add to Set';
        const buttonIcon = isInSet ? 'bi-archive-fill' : 'bi-archive';
        
        button.className = `btn ${buttonClass} btn-sm toggle-set-membership-btn`;
        button.innerHTML = `
            <i class="bi ${buttonIcon} me-1"></i>
            ${buttonText}
        `;
        button.setAttribute('data-is-member', isInSet);
    });
}

// Delegated event listener for set membership buttons
$(document).on('click', '.toggle-set-membership-btn', function(event) {
    const button = event.currentTarget;
    const setId = button.getAttribute('data-set-id');
    const modelId = button.getAttribute('data-model-id');
    const modelClass = button.getAttribute('data-model-class');
    const isCurrentlyMember = button.getAttribute('data-is-member') === 'true';
    window.toggleSetMembership(setId, modelId, modelClass, isCurrentlyMember, event);
});

// Toggle set membership (now only uses data attributes)
window.toggleSetMembership = function(setId, modelId, modelClass, isCurrentlyMember, event) {
    const button = event ? event.target.closest('button') : null;
    if (!button) {
        console.error('Button element not found');
        return;
    }
    const originalText = button.innerHTML;
    
    // Get the selected option if it's a connection
    let selectedOption = null;
    const addOptionInputs = document.querySelectorAll('input[name="addOption"]');
    if (addOptionInputs.length > 0) {
        const selectedInput = document.querySelector('input[name="addOption"]:checked');
        if (selectedInput) {
            selectedOption = selectedInput.value;
        }
    }
    
    // Show loading state
    button.disabled = true;
    button.innerHTML = `
        <div class="spinner-border spinner-border-sm me-1" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
        ${isCurrentlyMember ? 'Removing...' : 'Adding...'}
    `;
    
    const action = isCurrentlyMember ? 'remove' : 'add';
    
    const requestBody = {
        action: action,
        model_id: String(modelId),
        model_class: modelClass
    };
    
    // If we have a selected option, use that instead
    if (selectedOption) {
        const optionParts = selectedOption.split('_');
        if (optionParts.length >= 2) {
            requestBody.model_id = optionParts[1];
            requestBody.model_class = 'App\\Models\\Span';
        }
    }
    
    fetch(`/sets/${setId}/items`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify(requestBody)
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        console.log('Set membership updated:', data);
        
        // Update button state
        const newIsMember = !isCurrentlyMember;
        const buttonClass = newIsMember ? 'btn-outline-success' : 'btn-outline-primary';
        const buttonText = newIsMember ? 'Remove from Set' : 'Add to Set';
        const buttonIcon = newIsMember ? 'bi-archive-fill' : 'bi-archive';
        
        button.className = `btn ${buttonClass} btn-sm`;
        button.innerHTML = `
            <i class="bi ${buttonIcon} me-1"></i>
            ${buttonText}
        `;
        button.setAttribute('data-is-member', newIsMember);
        button.disabled = false;
        
        // Show success feedback
        const successMessage = newIsMember ? 'Added to set successfully!' : 'Removed from set successfully!';
        showToast(successMessage, 'success');
    })
    .catch(error => {
        console.error('Error updating set membership:', error);
        
        // Reset button state
        button.innerHTML = originalText;
        button.disabled = false;
        
        // Show error message
        showToast('Failed to update set membership. Please try again.', 'error');
    });
}

// Simple toast notification function
function showToast(message, type = 'info') {
    // Create toast element
    const toast = document.createElement('div');
    toast.className = `toast align-items-center text-white bg-${type === 'success' ? 'success' : type === 'error' ? 'danger' : 'info'} border-0`;
    toast.setAttribute('role', 'alert');
    toast.setAttribute('aria-live', 'assertive');
    toast.setAttribute('aria-atomic', 'true');
    
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                ${message}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    `;
    
    // Add to page
    const toastContainer = document.getElementById('toast-container') || createToastContainer();
    toastContainer.appendChild(toast);
    
    // Show toast
    const bsToast = new bootstrap.Toast(toast);
    bsToast.show();
    
    // Remove after it's hidden
    toast.addEventListener('hidden.bs.toast', () => {
        toast.remove();
    });
}

// Create toast container if it doesn't exist
function createToastContainer() {
    const container = document.createElement('div');
    container.id = 'toast-container';
    container.className = 'toast-container position-fixed top-0 end-0 p-3';
    container.style.zIndex = '9999';
    document.body.appendChild(container);
    return container;
}

// Open sets modal
window.openSetsModal = function(button) {
    const modelId = button.getAttribute('data-model-id');
    const modelClass = button.getAttribute('data-model-class');
    
    console.log('Opening sets modal:', { modelId, modelClass });
    
    // Store the button reference for use in the modal
    window.currentSetsButton = button;
    
    // Show loading state
    const modalContent = document.getElementById('setsModalContent');
    modalContent.innerHTML = `
        <div class="text-center">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2 text-muted">Loading sets...</p>
        </div>
    `;
    
    // Show the modal
    const modal = new bootstrap.Modal(document.getElementById('setsModal'));
    modal.show();
    
    // Load sets data
    loadSetsData(modelId, modelClass);
}

// Confirm and delete span
window.confirmDeleteSpan = function(button) {
    const modelId = button.getAttribute('data-model-id');
    const modelName = button.getAttribute('data-model-name');
    
    console.log('Delete span clicked:', { modelId, modelName });
    
    // Show confirmation dialog
    const confirmed = confirm(`Are you sure you want to delete the span "${modelName}"?\n\nThis action cannot be undone and will also delete all associated connections.`);
    
    if (!confirmed) {
        return;
    }
    
    // Show loading state
    const icon = button.querySelector('i');
    const originalIcon = icon.className;
    icon.className = 'bi bi-hourglass-split';
    button.disabled = true;
    
    // Send delete request
    fetch(`/spans/${modelId}`, {
        method: 'DELETE',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    })
    .then(response => {
        console.log('Delete response status:', response.status);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        console.log('Span deleted successfully:', data);
        
        // Show success feedback
        button.style.backgroundColor = '#28a745';
        icon.className = 'bi bi-check';
        
        // Remove the span from the DOM after a short delay
        setTimeout(() => {
            const spanElement = button.closest('.interactive-card-base, .card, .span-item');
            if (spanElement) {
                spanElement.style.opacity = '0';
                spanElement.style.transform = 'scale(0.8)';
                spanElement.style.transition = 'all 0.3s ease';
                
                setTimeout(() => {
                    spanElement.remove();
                }, 300);
            } else {
                // Fallback: reload the page
                location.reload();
            }
        }, 1000);
        
        // Show success message
        alert(`Span "${modelName}" deleted successfully!`);
    })
    .catch(error => {
        console.error('Error deleting span:', error);
        
        // Reset button state
        icon.className = originalIcon;
        button.disabled = false;
        
        // Show error message with more details
        let errorMessage = 'Failed to delete span. Please try again or contact an administrator.';
        if (error.message) {
            errorMessage += '\n\nError: ' + error.message;
        }
        alert(errorMessage);
    });
}

// Confirm and delete connection
window.confirmDeleteConnection = function(button) {
    const modelId = button.getAttribute('data-model-id');
    const modelName = button.getAttribute('data-model-name');
    
    console.log('Delete connection clicked:', { modelId, modelName });
    
    // Show confirmation dialog
    const confirmed = confirm(`Are you sure you want to delete the connection "${modelName}"?\n\nThis action cannot be undone.`);
    
    if (!confirmed) {
        return;
    }
    
    // Show loading state
    const icon = button.querySelector('i');
    const originalIcon = icon.className;
    icon.className = 'bi bi-hourglass-split';
    button.disabled = true;
    
    // Send delete request
    fetch(`/connections/${modelId}`, {
        method: 'DELETE',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        console.log('Connection deleted successfully:', data);
        
        // Show success feedback
        button.style.backgroundColor = '#28a745';
        icon.className = 'bi bi-check';
        
        // Remove the connection from the DOM after a short delay
        setTimeout(() => {
            const connectionElement = button.closest('.interactive-card-base, .card, .connection-item');
            if (connectionElement) {
                connectionElement.style.opacity = '0';
                connectionElement.style.transform = 'scale(0.8)';
                connectionElement.style.transition = 'all 0.3s ease';
                
                setTimeout(() => {
                    connectionElement.remove();
                }, 300);
            } else {
                // Fallback: reload the page
                location.reload();
            }
        }, 1000);
        
        // Show success message
        alert(`Connection "${modelName}" deleted successfully!`);
    })
    .catch(error => {
        console.error('Error deleting connection:', error);
        
        // Reset button state
        icon.className = originalIcon;
        button.disabled = false;
        
        // Show error message
        alert('Failed to delete connection. Please try again or contact an administrator.');
    });
}

// Update access level via modal
window.updateAccessLevel = function(newLevel) {
    const button = window.currentAccessLevelButton;
    if (!button) {
        console.error('No button reference found');
        return;
    }
    
    const modelId = button.getAttribute('data-model-id');
    const modelClass = button.getAttribute('data-model-class');
    const currentLevel = button.getAttribute('data-current-level');
    
    console.log('Updating access level:', { modelId, modelClass, currentLevel, newLevel });
    
    // Update button appearance immediately for responsive feedback
    const icon = button.querySelector('i');
    console.log('Updating icon from', icon.className, 'to new level:', newLevel);
    
    // Update button class
    button.classList.remove('btn-success', 'btn-danger', 'btn-warning');
    if (newLevel === 'public') {
        button.classList.add('btn-success');
        icon.className = 'bi bi-globe';
        console.log('Set icon to globe');
    } else if (newLevel === 'private') {
        button.classList.add('btn-danger');
        icon.className = 'bi bi-lock';
        console.log('Set icon to lock');
    } else if (newLevel === 'shared') {
        button.classList.add('btn-warning');
        icon.className = 'bi bi-people';
        console.log('Set icon to people');
    }
    
    // Update tooltip
    button.setAttribute('title', `Access: ${newLevel.charAt(0).toUpperCase() + newLevel.slice(1)}`);
    
    // Force icon update by ensuring the element is properly updated
    icon.style.display = 'none';
    setTimeout(() => {
        icon.style.display = '';
    }, 10);
    
    // Show visual feedback
    button.style.transform = 'scale(1.1)';
    setTimeout(() => {
        button.style.transform = 'scale(1)';
    }, 150);
    
    // Send AJAX request to update access level
    fetch('/admin/spans/' + modelId + '/access-level', {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({
            access_level: newLevel
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('Access level updated successfully:', newLevel);
            // Show success feedback
            button.style.backgroundColor = '#28a745';
            setTimeout(() => {
                button.style.backgroundColor = '';
            }, 500);
            
            // Update the button's data attributes for future clicks
            button.setAttribute('data-current-level', newLevel);
            
            // Refresh Bootstrap tooltip to reflect new title
            const tooltip = bootstrap.Tooltip.getInstance(button);
            if (tooltip) {
                tooltip.dispose();
            }
            new bootstrap.Tooltip(button);
            
            // Show success message and close modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('accessLevelModal'));
            modal.hide();
            
            // Show success toast or alert
            alert(`Access level changed to ${newLevel.charAt(0).toUpperCase() + newLevel.slice(1)} successfully!`);
        } else {
            console.error('Failed to update access level:', data.message);
            // Revert changes on failure
            if (currentLevel === 'public') {
                button.classList.remove('btn-success');
                button.classList.add('btn-success');
                icon.className = 'bi bi-globe';
            } else if (currentLevel === 'private') {
                button.classList.remove('btn-danger');
                button.classList.add('btn-danger');
                icon.className = 'bi bi-lock';
            } else if (currentLevel === 'shared') {
                button.classList.remove('btn-warning');
                button.classList.add('btn-warning');
                icon.className = 'bi bi-people';
            }
            button.setAttribute('title', `Access: ${currentLevel.charAt(0).toUpperCase() + currentLevel.slice(1)}`);
            alert('Failed to update access level: ' + (data.message || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error updating access level:', error);
        // Revert changes on error
        if (currentLevel === 'public') {
            button.classList.remove('btn-success');
            button.classList.add('btn-success');
            icon.className = 'bi bi-globe';
        } else if (currentLevel === 'private') {
            button.classList.remove('btn-danger');
            button.classList.add('btn-danger');
            icon.className = 'bi bi-lock';
        } else if (currentLevel === 'shared') {
            button.classList.remove('btn-warning');
            button.classList.add('btn-warning');
            icon.className = 'bi bi-people';
        }
        button.setAttribute('title', `Access: ${currentLevel.charAt(0).toUpperCase() + currentLevel.slice(1)}`);
        alert('Error updating access level. Please try again.');
    });
}

// Initialize tooltips for all tools buttons
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Bootstrap tooltips for ALL elements with data-bs-toggle="tooltip"
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Handle tools button hover behavior for immediate hiding
    document.querySelectorAll('.tools-button').forEach(function(toolsButton) {
        toolsButton.addEventListener('mouseenter', function() {
            // Show tools immediately
            const expandedTools = this.querySelectorAll('.tools-expanded');
            expandedTools.forEach(function(tool) {
                tool.style.visibility = 'visible';
                tool.style.position = 'relative';
            });
        });
        
        toolsButton.addEventListener('mouseleave', function() {
            // Hide tools immediately
            const expandedTools = this.querySelectorAll('.tools-expanded');
            expandedTools.forEach(function(tool) {
                tool.style.visibility = 'hidden';
                tool.style.position = 'absolute';
            });
        });
    });
    
    // Also handle hover on the button group itself for better coverage
    document.querySelectorAll('.tools-button .btn-group').forEach(function(btnGroup) {
        btnGroup.addEventListener('mouseenter', function() {
            const toolsButton = this.closest('.tools-button');
            if (toolsButton) {
                const expandedTools = toolsButton.querySelectorAll('.tools-expanded');
                expandedTools.forEach(function(tool) {
                    tool.style.visibility = 'visible';
                    tool.style.position = 'relative';
                });
            }
        });
        
        btnGroup.addEventListener('mouseleave', function() {
            const toolsButton = this.closest('.tools-button');
            if (toolsButton) {
                const expandedTools = toolsButton.querySelectorAll('.tools-expanded');
                expandedTools.forEach(function(tool) {
                    tool.style.visibility = 'hidden';
                    tool.style.position = 'absolute';
                });
            }
        });
    });
    
    // Add click event listeners for debugging
    document.querySelectorAll('.tools-button .btn').forEach((btn, index) => {
        btn.addEventListener('click', function(e) {
            console.log(`Tools button ${index + 1} clicked:`, this);
        });
    });
    
    // Set up access level modal confirm button
    const confirmButton = document.getElementById('confirmAccessLevel');
    if (confirmButton) {
        confirmButton.addEventListener('click', function() {
            const selectedLevel = document.querySelector('input[name="accessLevel"]:checked');
            if (selectedLevel) {
                updateAccessLevel(selectedLevel.value);
            } else {
                alert('Please select an access level');
            }
        });
    }
}); 