document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('newConnectionModal');
    if (!modal) return;

    const bsModal = new bootstrap.Modal(modal);
    let initialized = false;
    let connectionTypeSelect, connectedSpanSelect;

    // Initialize the connection modal
    function initializeConnectionModal() {
        if (initialized) return;

        const form = modal.querySelector('#newConnectionForm');
        connectionTypeSelect = $('#connection_type');
        connectedSpanSelect = $('#connected_span');
        const directionInputs = form.querySelectorAll('input[name="direction"]');

        // Initialize Select2 dropdowns
        connectionTypeSelect.select2({
            theme: 'bootstrap-5',
            dropdownParent: $('#newConnectionModal'),
            placeholder: 'Search for a connection type...',
            width: '100%'
        });

        connectedSpanSelect.select2({
            theme: 'bootstrap-5',
            dropdownParent: $('#newConnectionModal'),
            placeholder: 'Search for a span...',
            width: '100%',
            templateResult: formatSpan,
            templateSelection: formatSpan
        });

        // Handle connection type change
        connectionTypeSelect.on('change', function() {
            const selectedOption = $(this).find(':selected');
            const allowedTypes = selectedOption.data('allowed-types');
            const direction = form.querySelector('input[name="direction"]:checked').value;
            
            // Update predicate hint if type is selected
            if (selectedOption.val()) {
                const predicate = direction === 'forward' ? selectedOption.data('forward') : selectedOption.data('inverse');
                modal.querySelector('.connection-predicate').textContent = `This connection will use "${predicate}"`;
            } else {
                modal.querySelector('.connection-predicate').textContent = '';
            }
            
            // Filter connected span options
            filterConnectedSpans(allowedTypes, direction);
            updatePreview();
        });

        // Handle direction change
        directionInputs.forEach(input => {
            input.addEventListener('change', () => {
                const selectedOption = connectionTypeSelect.find(':selected');
                if (selectedOption.val()) {
                    const allowedTypes = selectedOption.data('allowed-types');
                    const predicate = input.value === 'forward' ? selectedOption.data('forward') : selectedOption.data('inverse');
                    modal.querySelector('.connection-predicate').textContent = `This connection will use "${predicate}"`;
                    filterConnectedSpans(allowedTypes, input.value);
                }
                updatePreview();
            });
        });

        // Handle connected span change
        connectedSpanSelect.on('change', function() {
            const selectedSpan = $(this).find(':selected');
            if (selectedSpan.val()) {
                const spanType = selectedSpan.data('type-name');
                modal.querySelector('.span-type-hint').textContent = `Selected: ${spanType}`;
            } else {
                modal.querySelector('.span-type-hint').textContent = '';
            }
            updatePreview();
        });

        // Handle form submission
        form.addEventListener('submit', handleSubmit);

        initialized = true;
    }

    // Format span display
    function formatSpan(span) {
        if (!span.id) return span.text;
        const $span = $(span.element);
        return $(`<span><strong>${$span.data('name')}</strong> <small class="text-muted">(${$span.data('type-name')})</small></span>`);
    }

    // Filter connected spans based on allowed types and direction
    function filterConnectedSpans(allowedTypes, direction) {
        connectedSpanSelect.val(null).trigger('change');
        
        const options = connectedSpanSelect[0].options;
        for (let i = 0; i < options.length; i++) {
            const option = options[i];
            const spanType = $(option).data('type');
            const role = direction === 'forward' ? 'child' : 'parent';
            option.disabled = allowedTypes?.[role]?.length > 0 && !allowedTypes[role].includes(spanType);
        }
    }

    // Update the connection preview
    function updatePreview() {
        const selectedType = connectionTypeSelect.find(':selected');
        const selectedSpan = connectedSpanSelect.find(':selected');
        const direction = modal.querySelector('input[name="direction"]:checked').value;
        const previewPredicate = modal.querySelector('.connection-preview-predicate');
        const previewTarget = modal.querySelector('.connection-preview-target');
        
        // Clear the preview if either type or span is not selected
        if (!selectedType.val() || !selectedSpan.val()) {
            previewPredicate.textContent = ' ... ';
            previewTarget.textContent = '(select both type and span)';
            return;
        }

        const predicate = direction === 'forward' ? selectedType.data('forward') : selectedType.data('inverse');
        
        if (direction === 'forward') {
            previewPredicate.textContent = ` ${predicate} `;
            previewTarget.textContent = selectedSpan.data('name');
        } else {
            previewPredicate.textContent = ` ${predicate} `;
            previewTarget.textContent = document.querySelector('#span-name').textContent;
        }
    }

    // Handle form submission
    async function handleSubmit(e) {
        e.preventDefault();
        const form = e.target;
        const submitButton = modal.querySelector('button[type="submit"]');
        if (!submitButton) {
            console.error('Submit button not found');
            return;
        }

        try {
            // Disable submit button and show loading state
            submitButton.disabled = true;
            submitButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Creating...';

            // Get form data
            const formData = new FormData(form);
            const direction = formData.get('direction');

            // If direction is inverse, swap parent_id and child_id
            if (direction === 'inverse') {
                const parentId = formData.get('parent_id');
                const childId = formData.get('child_id');
                formData.set('parent_id', childId);
                formData.set('child_id', parentId);
            }

            // Make the request
            const response = await fetch(form.action, {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                // Close modal and show success message
                bsModal.hide();
                showSuccessToast();
                // Reload the page to show new connection
                window.location.reload();
            } else {
                // Show error message from server
                showErrorMessage(form, result.message || 'Failed to create connection');
            }
        } catch (error) {
            console.error('Error creating connection:', error);
            showErrorMessage(form, error.message);
        } finally {
            // Re-enable submit button
            submitButton.disabled = false;
            submitButton.innerHTML = 'Create Connection';
        }
    }

    // Show success toast
    function showSuccessToast() {
        const toast = document.createElement('div');
        toast.className = 'toast align-items-center text-bg-success border-0';
        toast.setAttribute('role', 'alert');
        toast.setAttribute('aria-live', 'assertive');
        toast.setAttribute('aria-atomic', 'true');
        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">Connection created successfully</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        `;
        document.body.appendChild(toast);
        new bootstrap.Toast(toast).show();
        setTimeout(() => toast.remove(), 3000);
    }

    // Show error message
    function showErrorMessage(form, message) {
        const errorAlert = document.createElement('div');
        errorAlert.className = 'alert alert-danger alert-dismissible fade show mt-3';
        errorAlert.innerHTML = `
            <strong>Error:</strong> ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        form.insertBefore(errorAlert, form.firstChild);
    }

    // Handle span type change
    const typeSelect = document.getElementById('type_id');
    if (typeSelect) {
        typeSelect.addEventListener('change', function() {
            console.log('Type changed to:', this.value);
            // Show confirmation dialog
            if (confirm('Changing the span type will reload the form to show type-specific fields. Any unsaved changes will be lost. Continue?')) {
                // Submit the form with the new type
                const form = document.getElementById('span-edit-form');
                console.log('Form found:', !!form);
                if (form) {
                    console.log('Submitting form...');
                    form.submit();
                } else {
                    console.log('Form not found, falling back to page reload');
                    // Fallback to page reload if form not found
                    window.location.reload();
                }
            } else {
                console.log('User cancelled, resetting to:', this.getAttribute('data-original-value'));
                // Reset the select to the previous value
                this.value = this.getAttribute('data-original-value');
            }
        });

        // Store the original value
        typeSelect.setAttribute('data-original-value', typeSelect.value);
        console.log('Initial type value:', typeSelect.value);
    }

    // Modal event listeners
    modal.addEventListener('show.bs.modal', initializeConnectionModal);
    modal.addEventListener('shown.bs.modal', () => connectionTypeSelect?.select2('focus'));
    modal.addEventListener('hidden.bs.modal', () => {
        connectionTypeSelect?.val(null).trigger('change');
        connectedSpanSelect?.val(null).trigger('change');
        modal.querySelector('#newConnectionForm').reset();
        const errorAlert = modal.querySelector('.alert');
        if (errorAlert) errorAlert.remove();
    });
}); 