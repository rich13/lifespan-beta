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
        
        // Show error message
        alert('Failed to delete span. Please try again or contact an administrator.');
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