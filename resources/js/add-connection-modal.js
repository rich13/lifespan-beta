$(document).ready(function() {
    let currentSpanId = null;
    let currentSpanName = null;
    let currentSpanType = null;
    let connectionTypes = [];
    let searchTimeout = null;
    let isReverseMode = false;
    let isEditMode = false;
    let currentConnectionId = null;

    // Initialize modal when opened
    $('#addConnectionModal').on('show.bs.modal', function(event) {
        const button = $(event.relatedTarget);
        currentSpanId = button.data('span-id');
        currentSpanName = button.data('span-name');
        currentSpanType = button.data('span-type');
        currentConnectionId = button.data('connection-id') || null;
        isEditMode = !!currentConnectionId;
        
        // Reset form and state
        $('#addConnectionForm')[0].reset();
        $('#connectionId').val(currentConnectionId || '');
        $('#connectionObjectId').val('');
        $('#searchResults').empty();
        isReverseMode = false;
        
        // Update modal title and button
        if (isEditMode) {
            $('#modalTitleText').text('Edit Connection');
            $('#submitButtonText').text('Save');
            $('#createIcon').addClass('d-none');
            $('#saveIcon').removeClass('d-none');
        } else {
            $('#modalTitleText').text('Add Connection');
            $('#submitButtonText').text('Create');
            $('#createIcon').removeClass('d-none');
            $('#saveIcon').addClass('d-none');
            // Ensure placeholder state is selected after reset (scope to form to avoid conflicts)
            $('#addConnectionForm #statePlaceholder').prop('checked', true);
        }
        
        // Reset toggle button
        $('#directionLabel').text('Forward');
        $('#directionToggle').removeClass('btn-outline-primary').addClass('btn-outline-secondary');
        
        // Update subject display
        $('#connectionSubject').text(currentSpanName);
        $('#connectionSubjectType').text(currentSpanType);
        
        if (isEditMode) {
            // Load connection data for editing
            loadConnectionForEdit(currentConnectionId);
            } else {
                // Reset form fields for create mode
                $('#connectionObject').prop('disabled', true);
                $('#searchObjectBtn').prop('disabled', true);
                $('#connectionObjectDisplay').addClass('d-none');
                $('#connectionObject').removeClass('d-none');
                $('#connectionPredicate').prop('disabled', false);
                $('#directionToggle').show();
                
                // Load connection types (no need to wait for promise in create mode)
                loadConnectionTypes();
            }
    });

    // Handle direction toggle
    $('#directionToggle').on('click', function() {
        isReverseMode = !isReverseMode;
        
        if (isReverseMode) {
            $('#directionLabel').text('Reverse');
            $(this).removeClass('btn-outline-secondary').addClass('btn-outline-primary');
        } else {
            $('#directionLabel').text('Forward');
            $(this).removeClass('btn-outline-primary').addClass('btn-outline-secondary');
        }
        
        // Clear current selection and reload connection types for the new mode
        $('#connectionPredicate').val('');
        $('#connectionObject').val('').prop('disabled', true);
        $('#connectionObjectId').val('');
        $('#searchResults').empty();
        loadConnectionTypes();
    });

    // Load connection data for editing
    function loadConnectionForEdit(connectionId) {
        // First load connection types so we can populate the dropdown
        loadConnectionTypes().then(function() {
            // Then load the connection data
            $.ajax({
                url: `/spans/api/connections/${connectionId}`,
                method: 'GET',
                headers: {
                    'Accept': 'application/json'
                },
                success: function(response) {
                    if (response.success && response.data) {
                        const conn = response.data;
                        
                        // Determine which span is the subject (current span)
                        const isParent = conn.parent_id === currentSpanId;
                        const subjectSpan = isParent ? {id: conn.parent_id, name: conn.parent_name, type: conn.parent_type} : {id: conn.child_id, name: conn.child_name, type: conn.child_type};
                        const objectSpan = isParent ? {id: conn.child_id, name: conn.child_name, type: conn.child_type} : {id: conn.parent_id, name: conn.parent_name, type: conn.parent_type};
                        
                        // Set connection type (read-only in edit mode)
                        $('#connectionPredicate').val(conn.type).prop('disabled', true);
                        
                        // Set object span (read-only in edit mode)
                        $('#connectionObject').addClass('d-none').prop('disabled', true);
                        $('#connectionObjectDisplay').removeClass('d-none').text(objectSpan.name);
                        $('#connectionObjectId').val(objectSpan.id);
                        
                        // Hide direction toggle in edit mode
                        $('#directionToggle').hide();
                        
                        // Set state
                        $(`#addConnectionForm input[name="state"][value="${conn.state}"]`).prop('checked', true);
                        
                        // Set dates
                        if (conn.start_year) $('#startYear').val(conn.start_year);
                        if (conn.start_month) $('#startMonth').val(conn.start_month);
                        if (conn.start_day) $('#startDay').val(conn.start_day);
                        if (conn.end_year) $('#endYear').val(conn.end_year);
                        if (conn.end_month) $('#endMonth').val(conn.end_month);
                        if (conn.end_day) $('#endDay').val(conn.end_day);
                        
                        // Update help text based on state
                        updateDateHelpText(conn.state, conn.type);
                    }
                },
                error: function(xhr) {
                    console.error('Error loading connection:', xhr);
                    showStatus('Error loading connection data', 'danger');
                }
            });
        });
    }
    
    // Update date help text based on state and connection type
    function updateDateHelpText(state, connectionType) {
        const startDateHelp = $('#startDateHelp');
        const connectionTypeObj = connectionTypes.find(t => t.type === connectionType);
        const isTimeless = connectionTypeObj && connectionTypeObj.constraint_type === 'timeless';
        
        if (isTimeless) {
            startDateHelp.text('Optional - not required for this connection type');
        } else if (state === 'placeholder') {
            startDateHelp.text('Optional for placeholder connections');
        } else {
            startDateHelp.text('Required for draft and complete connections');
        }
    }

    // Load connection types from the database
    function loadConnectionTypes() {
        return new Promise(function(resolve, reject) {
            const params = new URLSearchParams();
            if (currentSpanType) {
                params.append('span_type', currentSpanType);
                params.append('mode', isReverseMode ? 'reverse' : 'forward');
            }
            
            console.log('Loading connection types for span type:', currentSpanType, 'reverse mode:', isReverseMode);
            
            $.ajax({
                url: `/api/connection-types?${params.toString()}`,
                method: 'GET',
                headers: {
                    'Accept': 'application/json'
                },
                success: function(data) {
                    console.log('Loaded connection types:', data);
                    connectionTypes = data;
                    const dropdown = $('#connectionPredicate');
                    dropdown.empty();
                    dropdown.append('<option value="">Select connection type...</option>');
                    
                    data.forEach(function(type) {
                        // Use inverse predicate for reverse mode, forward predicate for forward mode
                        const displayText = isReverseMode ? (type.inverse_predicate || type.type) : (type.forward_predicate || type.type);
                        dropdown.append(`<option value="${type.type}">${displayText}</option>`);
                        console.log('Added option:', type.type, '->', displayText, '(mode:', isReverseMode ? 'reverse' : 'forward', ')');
                    });
                    
                    resolve(data);
                },
                error: function(xhr) {
                    console.error('Error loading connection types:', xhr);
                    showAlert('Error loading connection types', 'danger');
                    reject(xhr);
                }
            });
        });
    }

    // Handle predicate selection
    $('#connectionPredicate').on('change', function() {
        const selectedType = $(this).val();
        const objectField = $('#connectionObject');
        const searchBtn = $('#searchObjectBtn');
        
        if (selectedType) {
            objectField.prop('disabled', false);
            
            // Get allowed span types for this connection type
            const connectionType = connectionTypes.find(t => t.type === selectedType);
            console.log('Selected connection type:', connectionType);
            
            // Update date field helper text based on whether connection is timeless
            const startDateHelp = $('#startDateHelp');
            if (connectionType && connectionType.constraint_type === 'timeless') {
                startDateHelp.text('Optional - not required for this connection type');
            } else {
                const selectedState = $('#addConnectionForm input[name="state"]:checked').val();
                if (selectedState === 'placeholder') {
                    startDateHelp.text('Optional for placeholder connections');
                } else {
                    startDateHelp.text('Required for draft and complete connections');
                }
            }
            
            if (connectionType && connectionType.allowed_span_types) {
                // In reverse mode, we need parent types; in forward mode, we need child types
                const allowedTypesKey = isReverseMode ? 'parent' : 'child';
                const allowedTypes = connectionType.allowed_span_types[allowedTypesKey];
                
                if (allowedTypes && allowedTypes.length > 0) {
                    const allowedTypesString = allowedTypes.join(',');
                    $('#allowedSpanTypes').val(allowedTypesString);
                    console.log('Allowed span types for', isReverseMode ? 'reverse' : 'forward', 'mode:', allowedTypesString);
                } else {
                    console.log('No allowed span types found for:', selectedType, 'in', isReverseMode ? 'reverse' : 'forward', 'mode');
                }
            } else {
                console.log('No allowed span types found for:', selectedType);
            }
        } else {
            objectField.prop('disabled', true);
            $('#connectionObjectId').val('');
            $('#searchResults').empty();
        }
    });

    // Handle live search on input
    $('#connectionObject').on('input', function() {
        const query = $(this).val().trim();
        const allowedTypes = $('#allowedSpanTypes').val();
        
        // Clear previous timeout
        if (searchTimeout) {
            clearTimeout(searchTimeout);
        }
        
        // Clear results if query is empty
        if (!query) {
            $('#searchResults').empty();
            return;
        }
        
        // Debounce search
        searchTimeout = setTimeout(function() {
            performSearch(query, allowedTypes);
        }, 300);
    });
    
    // Handle connection state changes (scoped to the modal form)
    $('#addConnectionModal').on('change', 'input[name="state"]', function() {
        const selectedState = $(this).val();
        const startDateRequired = $('#startDateRequired');
        const startDateHelp = $('#startDateHelp');
        
        // Check if the selected connection type is timeless
        const selectedType = $('#connectionPredicate').val();
        const connectionType = connectionTypes.find(t => t.type === selectedType);
        const isTimeless = connectionType && connectionType.constraint_type === 'timeless';
        
        if (isTimeless) {
            // For timeless connections, dates are always optional
            startDateHelp.text('Optional - not required for this connection type');
        } else if (selectedState === 'placeholder') {
            startDateRequired.hide();
            startDateHelp.text('Optional for placeholder connections');
        } else {
            startDateRequired.show();
            startDateHelp.text('Required for draft and complete connections');
        }
    });
    
    // Handle keyboard navigation
    let selectedIndex = -1;
    let searchResults = [];
    
    $('#connectionObject').on('keydown', function(e) {
        const resultsContainer = $('#searchResults');
        const resultItems = resultsContainer.find('.search-result-item');
        
        if (resultItems.length === 0) return;
        
        switch(e.key) {
            case 'ArrowDown':
                e.preventDefault();
                selectedIndex = Math.min(selectedIndex + 1, resultItems.length - 1);
                updateSelection(resultItems);
                break;
            case 'ArrowUp':
                e.preventDefault();
                selectedIndex = Math.max(selectedIndex - 1, -1);
                updateSelection(resultItems);
                break;
            case 'Enter':
                e.preventDefault();
                if (selectedIndex >= 0 && selectedIndex < resultItems.length) {
                    const selectedItem = resultItems.eq(selectedIndex);
                    selectResult(selectedItem);
                }
                break;
            case 'Escape':
                e.preventDefault();
                resultsContainer.empty();
                selectedIndex = -1;
                break;
        }
    });
    
    // Update visual selection
    function updateSelection(resultItems) {
        resultItems.removeClass('bg-primary text-white');
        if (selectedIndex >= 0 && selectedIndex < resultItems.length) {
            resultItems.eq(selectedIndex).addClass('bg-primary text-white');
        }
    }
    
    // Select a result
    function selectResult(resultItem) {
        const spanId = resultItem.data('span-id');
        const spanName = resultItem.data('span-name');
        
        $('#connectionObject').val(spanName);
        $('#connectionObjectId').val(spanId);
        $('#searchResults').empty();
        selectedIndex = -1;
    }

    // Perform span search
    function performSearch(query, allowedTypes) {
        console.log('performSearch called with allowedTypes:', allowedTypes);
        
        const params = new URLSearchParams({
            q: query,
            exclude: currentSpanId
        });
        
        if (allowedTypes) {
            params.append('types', allowedTypes);
        }
        
        console.log('Searching with params:', params.toString());
        
        $.ajax({
            url: `/api/spans/search?${params.toString()}`,
            method: 'GET',
            headers: {
                'Accept': 'application/json'
            },
            success: function(data) {
                console.log('Search results:', data);
                displaySearchResults(data);
            },
            error: function(xhr) {
                console.error('Error searching spans:', xhr);
                showAlert('Error searching spans', 'danger');
            }
        });
    }

    // Display search results
    function displaySearchResults(response) {
        const resultsContainer = $('#searchResults');
        resultsContainer.empty();
        selectedIndex = -1; // Reset selection
        
        // Handle both direct array and object with spans property
        const spans = Array.isArray(response) ? response : (response.spans || []);
        
        // Check if we have any real results (not just placeholders)
        const realSpans = spans.filter(span => span.id !== null);
        const placeholderSpans = spans.filter(span => span.id === null);
        
        console.log('Search results - Real spans:', realSpans.length, 'Placeholder spans:', placeholderSpans.length);
        
        if (realSpans.length === 0) {
            const query = $('#connectionObject').val().trim();
            const allowedTypes = $('#allowedSpanTypes').val();
            
            console.log('No real search results found. Query:', query, 'Allowed types:', allowedTypes);
            
            if (query && allowedTypes) {
                // Show "Create new" option
                const typeArray = allowedTypes.split(',');
                const firstType = typeArray[0]; // Use the first allowed type
                const typeName = firstType.charAt(0).toUpperCase() + firstType.slice(1);
                
                console.log('Creating button for type:', firstType, 'with name:', typeName);
                
                const createButton = $(`
                    <div class="p-3 text-center border-top">
                        <button type="button" class="btn btn-outline-primary btn-sm" id="createNewSpanBtn" 
                                data-span-name="${query}" data-span-type="${firstType}">
                            <i class="bi bi-plus-circle me-1"></i>Create new ${typeName}: "${query}"
                        </button>
                    </div>
                `);
                
                createButton.on('click', function() {
                    createNewSpan(query, firstType);
                });
                
                resultsContainer.append(createButton);
            } else {
                console.log('Not showing create button. Query:', query, 'Allowed types:', allowedTypes);
                resultsContainer.append('<div class="text-muted p-2">No spans found</div>');
            }
            return;
        }
        
        // Display real spans first
        realSpans.forEach(function(span) {
            const resultItem = $(`
                <div class="search-result-item p-2 border-bottom cursor-pointer" data-span-id="${span.id}" data-span-name="${span.name}">
                    <div class="fw-bold">${span.name}</div>
                    <div class="text-muted small">${span.type_id}${span.start_year ? ' • ' + span.start_year : ''}</div>
                </div>
            `);
            
            // Add click handler for mouse selection
            resultItem.on('click', function() {
                selectResult($(this));
            });
            
            // Add hover effect
            resultItem.on('mouseenter', function() {
                selectedIndex = resultItem.index();
                updateSelection(resultsContainer.find('.search-result-item'));
            });
            
            resultsContainer.append(resultItem);
        });
        
        // Display placeholder spans with different styling
        placeholderSpans.forEach(function(span) {
            const resultItem = $(`
                <div class="search-result-item p-2 border-bottom cursor-pointer" data-span-id="${span.id}" data-span-name="${span.name}" data-is-placeholder="true">
                    <div class="fw-bold text-muted">${span.name} <span class="badge bg-secondary">placeholder</span></div>
                    <div class="text-muted small">${span.type_id} • Click to create</div>
                </div>
            `);
            
            // Add click handler for mouse selection
            resultItem.on('click', function() {
                // For placeholder spans, create them immediately
                createNewSpan(span.name, span.type_id);
            });
            
            // Add hover effect
            resultItem.on('mouseenter', function() {
                selectedIndex = resultItem.index();
                updateSelection(resultsContainer.find('.search-result-item'));
            });
            
            resultsContainer.append(resultItem);
        });
    }

    // Create a new span and select it
    function createNewSpan(name, typeId) {
        // Show loading state
        $('#createNewSpanBtn').prop('disabled', true).html('<i class="spinner-border spinner-border-sm me-1"></i>Creating...');
        
        $.ajax({
            url: '/api/spans/create',
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            data: JSON.stringify({
                name: name,
                type_id: typeId,
                state: 'placeholder'
            }),
            success: function(response) {
                // Create a temporary result item to simulate selection
                const tempResult = $(`<div data-span-id="${response.id}" data-span-name="${response.name}"></div>`);
                selectResult(tempResult);
                
                // Show success message
                showStatus(`Created new ${typeId}: "${name}"`, 'success');
                
                // Clear the success message after a short delay
                setTimeout(function() {
                    $('#addConnectionStatus').empty();
                }, 2000);
            },
            error: function(xhr) {
                console.error('Error creating span:', xhr);
                const message = xhr.responseJSON?.message || 'Error creating new span';
                showStatus(message, 'danger');
                
                // Re-enable the create button
                const typeName = typeId.charAt(0).toUpperCase() + typeId.slice(1);
                $('#createNewSpanBtn').prop('disabled', false).html(`<i class="bi bi-plus-circle me-1"></i>Create new ${typeName}: "${name}"`);
            }
        });
    }

    // Handle form submission
    $('#addConnectionForm').on('submit', function(e) {
        e.preventDefault();
        
        // Get state directly from form to ensure we have the current value
        // Scope to the addConnectionForm to avoid conflicts with other forms on the page
        const checkedState = $('#addConnectionForm input[name="state"]:checked').val();
        const currentState = checkedState || 'placeholder';
        
        console.log('=== Connection Form Validation Debug ===');
        console.log('Checked state value (scoped to form):', checkedState);
        console.log('Current state (with fallback):', currentState);
        console.log('All state radio values in form:', $('#addConnectionForm input[name="state"]').map(function() {
            return $(this).val() + ':' + ($(this).is(':checked') ? 'checked' : 'unchecked');
        }).get());
        console.log('All state radio values on page:', $('input[name="state"]').map(function() {
            return $(this).val() + ':' + ($(this).is(':checked') ? 'checked' : 'unchecked') + ' (id: ' + $(this).attr('id') + ')';
        }).get());
        
        // Gather form data
        const formData = {
            type: $('#connectionPredicate').val(),
            parent_id: currentSpanId,
            child_id: $('#connectionObjectId').val(),
            direction: isReverseMode ? 'inverse' : 'forward',
            state: currentState,
            connection_year: $('#startYear').val() ? parseInt($('#startYear').val()) : null,
            connection_month: $('#startMonth').val() ? parseInt($('#startMonth').val()) : null,
            connection_day: $('#startDay').val() ? parseInt($('#startDay').val()) : null,
            connection_end_year: $('#endYear').val() ? parseInt($('#endYear').val()) : null,
            connection_end_month: $('#endMonth').val() ? parseInt($('#endMonth').val()) : null,
            connection_end_day: $('#endDay').val() ? parseInt($('#endDay').val()) : null
        };
        
        console.log('Form data state:', formData.state);
        console.log('Form data connection_year:', formData.connection_year);
        console.log('Form data:', formData);
        
        // Validate required fields
        if (!formData.type) {
            showStatus('Please select a connection type', 'danger');
            return;
        }
        
        if (!formData.child_id) {
            showStatus('Please select a span to connect to', 'danger');
            return;
        }
        
        // Get the selected connection type to check if it's timeless
        const selectedConnectionType = connectionTypes.find(t => t.type === formData.type);
        const isTimeless = selectedConnectionType && selectedConnectionType.constraint_type === 'timeless';
        
        console.log('Selected connection type:', selectedConnectionType);
        console.log('Is timeless:', isTimeless);
        
        // Date validation depends on connection state and whether it's timeless
        const isPlaceholder = formData.state === 'placeholder';
        
        console.log('Is placeholder:', isPlaceholder);
        console.log('Validation check: !isPlaceholder =', !isPlaceholder, ', !isTimeless =', !isTimeless, ', !formData.connection_year =', !formData.connection_year);
        console.log('Will require start year?', !isPlaceholder && !isTimeless && !formData.connection_year);
        
        // For non-placeholder, non-timeless connections, require a start year
        if (!isPlaceholder && !isTimeless && !formData.connection_year) {
            console.log('ERROR: Requiring start year - this should not happen for placeholders!');
            showStatus('Please enter a start year', 'danger');
            return;
        }
        
        console.log('Date validation passed - proceeding with form submission');
        
        // Validate date format (only if dates are provided)
        if (formData.connection_year && !validateDate(formData.connection_year, formData.connection_month, formData.connection_day)) {
            showStatus('Please enter a valid start date', 'danger');
            return;
        }
        
        if (formData.connection_end_year && !validateDate(formData.connection_end_year, formData.connection_end_month, formData.connection_end_day)) {
            showStatus('Please enter a valid end date', 'danger');
            return;
        }
        
        // Show spinner and disable form
        $('#addConnectionSpinner').removeClass('d-none');
        $('#addConnectionSubmitBtn').prop('disabled', true);
        $('#addConnectionForm :input').prop('disabled', true);
        showStatus(isEditMode ? 'Updating connection...' : 'Creating connection...', 'info');
        
        console.log('Sending form data:', formData);
        
        if (isEditMode) {
            // Update connection
            const updateData = {
                state: formData.state,
                start_year: formData.connection_year,
                start_month: formData.connection_month,
                start_day: formData.connection_day,
                end_year: formData.connection_end_year,
                end_month: formData.connection_end_month,
                end_day: formData.connection_end_day
            };
            
            $.ajax({
                url: `/spans/api/connections/${currentConnectionId}/update`,
                method: 'PUT',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                data: JSON.stringify(updateData),
                success: function(response) {
                    showStatus('Connection updated successfully!', 'success');
                    // Wait a moment, then refresh
                    setTimeout(function() {
                        window.location.reload();
                    }, 1200);
                },
                error: function(xhr) {
                    console.error('Error updating connection:', xhr);
                    const message = xhr.responseJSON?.message || 'Error updating connection';
                    showStatus(message, 'danger');
                    // Re-enable form and hide spinner
                    $('#addConnectionSpinner').addClass('d-none');
                    $('#addConnectionSubmitBtn').prop('disabled', false);
                    $('#addConnectionForm :input').prop('disabled', false);
                }
            });
        } else {
            // Create connection
            $.ajax({
                url: '/spans/api/connections/create',
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                data: JSON.stringify(formData),
                success: function(response) {
                    showStatus('Connection created successfully!', 'success');
                    // Wait a moment, then refresh
                    setTimeout(function() {
                        window.location.reload();
                    }, 1200);
                },
                error: function(xhr) {
                    console.error('Error creating connection:', xhr);
                    const message = xhr.responseJSON?.message || 'Error creating connection';
                    showStatus(message, 'danger');
                    // Re-enable form and hide spinner
                    $('#addConnectionSpinner').addClass('d-none');
                    $('#addConnectionSubmitBtn').prop('disabled', false);
                    $('#addConnectionForm :input').prop('disabled', false);
                }
            });
        }
    });

    // Validate date format (YYYY, YYYY-MM, or YYYY-MM-DD)
    function validateDate(year, month, day) {
        if (!year) return false;
        
        // If day is provided, month must also be provided
        if (day && !month) return false;
        
        // Validate year range
        if (year < 1000 || year > 2100) return false;
        
        // Validate month if provided
        if (month && (month < 1 || month > 12)) return false;
        
        // Validate day if provided
        if (day && (day < 1 || day > 31)) return false;
        
        return true;
    }

    // Show alert message
    function showAlert(message, type) {
        const alertHtml = `
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `;
        
        $('#addConnectionModal .modal-body').prepend(alertHtml);
        
        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            $('#addConnectionModal .alert').alert('close');
        }, 5000);
    }

    // Show status message in modal
    function showStatus(message, type) {
        const html = `<div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>`;
        $('#addConnectionStatus').html(html);
    }
}); 