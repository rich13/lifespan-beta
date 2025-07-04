<!-- New Span Modal -->
<div class="modal fade" id="newSpanModal" tabindex="-1" aria-labelledby="newSpanModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header border-bottom">
                <h5 class="modal-title fw-bold" id="newSpanModalLabel">
                    <i class="bi bi-plus-circle me-2 text-primary"></i>Create New Span
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form action="{{ route('spans.store') }}" method="POST" id="new-span-form">
                    @csrf
                    
                    <!-- Basic Information Section -->
                    <div class="mb-4">
                        <h6 class="text-muted mb-3 fw-semibold">
                            <i class="bi bi-info-circle me-2"></i>Basic Information
                        </h6>
                        
                        <div class="mb-3">
                            <label for="modal_name" class="form-label fw-medium">Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-lg" 
                                   id="modal_name" name="name" required 
                                   placeholder="Enter span name...">
                            <div class="invalid-feedback" id="name-error"></div>
                        </div>

                        <div class="row g-3">
                            <div class="col-12">
                                <label for="modal_type_id" class="form-label fw-medium">Type <span class="text-danger">*</span></label>
                                <select class="form-select" id="modal_type_id" name="type_id" required>
                                    <option value="">Choose a type...</option>
                                    @foreach($spanTypes as $type)
                                        <option value="{{ $type->type_id }}">
                                            {{ ucfirst($type->name) }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="invalid-feedback" id="type_id-error"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Date Information Section -->
                    <div class="mb-4">
                        <h6 class="text-muted mb-3 fw-semibold">
                            <i class="bi bi-calendar-range me-2"></i>Date Information
                        </h6>
                        
                        <div class="row g-0 mb-4 date-input-group">
                            <div class="col-md-4">
                                <label for="modal_start_year" class="form-label fw-medium">Start Year</label>
                                <input type="number" class="form-control" 
                                       id="modal_start_year" name="start_year"
                                       placeholder="YYYY">
                                <div class="invalid-feedback" id="start_year-error"></div>
                            </div>
                            <div class="col-md-4">
                                <label for="modal_start_month" class="form-label fw-medium">Start Month</label>
                                <input type="number" class="form-control" 
                                       id="modal_start_month" name="start_month" min="1" max="12"
                                       placeholder="MM">
                                <div class="invalid-feedback" id="start_month-error"></div>
                            </div>
                            <div class="col-md-4">
                                <label for="modal_start_day" class="form-label fw-medium">Start Day</label>
                                <input type="number" class="form-control" 
                                       id="modal_start_day" name="start_day" min="1" max="31"
                                       placeholder="DD">
                                <div class="invalid-feedback" id="start_day-error"></div>
                            </div>
                        </div>

                        <div class="row g-0 date-input-group">
                            <div class="col-md-4">
                                <label for="modal_end_year" class="form-label fw-medium">End Year</label>
                                <input type="number" class="form-control" 
                                       id="modal_end_year" name="end_year"
                                       placeholder="YYYY">
                                <div class="invalid-feedback" id="end_year-error"></div>
                            </div>
                            <div class="col-md-4">
                                <label for="modal_end_month" class="form-label fw-medium">End Month</label>
                                <input type="number" class="form-control" 
                                       id="modal_end_month" name="end_month" min="1" max="12"
                                       placeholder="MM">
                                <div class="invalid-feedback" id="end_month-error"></div>
                            </div>
                            <div class="col-md-4">
                                <label for="modal_end_day" class="form-label fw-medium">End Day</label>
                                <input type="number" class="form-control" 
                                       id="modal_end_day" name="end_day" min="1" max="31"
                                       placeholder="DD">
                                <div class="invalid-feedback" id="end_day-error"></div>
                            </div>
                        </div>
                        
                        <div class="mt-3">
                            <small class="text-muted">
                                <i class="bi bi-info-circle me-1"></i>
                                Leave dates empty for timeless spans or placeholders
                            </small>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-top">
                <div class="d-flex align-items-center">
                    <div class="me-3">
                        <!-- <label class="form-label fw-medium mb-1">State</label> -->
                        <div class="btn-group" role="group" aria-label="Span state selection">                                 
                            <input type="radio" class="btn-check" name="state" id="state_placeholder" value="placeholder" checked>
                            <label class="btn btn-outline-secondary" for="state_placeholder">
                                <i class="bi bi-question-circle me-1"></i>Placeholder
                            </label>
                            
                            <input type="radio" class="btn-check" name="state" id="state_draft" value="draft">
                            <label class="btn btn-outline-secondary" for="state_draft">
                                <i class="bi bi-pencil me-1"></i>Draft
                            </label>

                            <input type="radio" class="btn-check" name="state" id="state_complete" value="complete">
                            <label class="btn btn-outline-secondary" for="state_complete">
                                <i class="bi bi-check-circle me-1"></i>Complete
                            </label>
                        </div>
                        <div class="invalid-feedback" id="state-error"></div>
                    </div>
                </div>
                
                <div class="ms-auto">
                    <button type="button" class="btn btn-outline-secondary me-2" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i>Cancel
                    </button>
                    <button type="button" class="btn btn-primary px-4" id="save-span-btn">
                        <i class="bi bi-check-circle me-1"></i>Create Span
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    /* Custom modal styling */
    #newSpanModal .modal-content {
        border: none;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        border-radius: 0.75rem;
    }
    
    #newSpanModal .modal-header {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        border-radius: 0.75rem 0.75rem 0 0;
        padding: 1.5rem;
    }
    
    #newSpanModal .modal-body {
        padding: 2rem;
    }
    
    #newSpanModal .modal-footer {
        padding: 1.5rem 2rem;
        background-color: #f8f9fa;
        border-radius: 0 0 0.75rem 0.75rem;
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
    }
    
    #newSpanModal .modal-footer .form-label {
        font-size: 0.875rem;
        margin-bottom: 0.5rem;
        color: #6c757d;
    }
    
    #newSpanModal .form-label {
        color: #495057;
        margin-bottom: 0.5rem;
    }
    
    #newSpanModal .form-control,
    #newSpanModal .form-select {
        border-radius: 0.5rem;
        border: 1px solid #dee2e6;
        transition: all 0.2s ease;
    }
    
    /* Date input styling for button group appearance */
    #newSpanModal .date-input-group .form-control {
        border: 1px solid #dee2e6;
        border-radius: 0;
        border-right: 1px solid #dee2e6;
        box-shadow: none;
    }
    
    #newSpanModal .date-input-group .col-md-4:first-child .form-control {
        border-top-left-radius: 0.5rem;
        border-bottom-left-radius: 0.5rem;
    }
    
    #newSpanModal .date-input-group .col-md-4:last-child .form-control {
        border-top-right-radius: 0.5rem;
        border-bottom-right-radius: 0.5rem;
        border-right: 1px solid #dee2e6;
    }
    
    #newSpanModal .date-input-group .form-control:focus {
        border-color: #0d6efd;
        box-shadow: none;
        z-index: 1;
        position: relative;
    }
    
    #newSpanModal .form-control:focus,
    #newSpanModal .form-select:focus {
        border-color: #0d6efd;
        box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.15);
    }
    
    #newSpanModal .form-control-lg {
        font-size: 1.1rem;
        padding: 0.75rem 1rem;
    }
    
    #newSpanModal .btn {
        border-radius: 0.5rem;
        font-weight: 500;
        transition: all 0.2s ease;
    }
    
    #newSpanModal .btn-primary {
        background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%);
        border: none;
    }
    
    #newSpanModal .btn-primary:hover {
        background: linear-gradient(135deg, #0b5ed7 0%, #0a58ca 100%);
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(13, 110, 253, 0.3);
    }
    
    /* Button group styling */
    #newSpanModal .btn-group {
        border-radius: 0.5rem;
        overflow: hidden;
    }
    
    #newSpanModal .btn-group .btn {
        border: 1px solid #dee2e6;
        border-radius: 0;
        transition: all 0.2s ease;
        font-weight: 500;
    }
    
    #newSpanModal .btn-group .btn:first-child {
        border-top-left-radius: 0.5rem;
        border-bottom-left-radius: 0.5rem;
    }
    
    #newSpanModal .btn-group .btn:last-child {
        border-top-right-radius: 0.5rem;
        border-bottom-right-radius: 0.5rem;
    }
    
    #newSpanModal .btn-group .btn:hover {
        background-color: #e9ecef;
        border-color: #adb5bd;
    }
    
    #newSpanModal .btn-group .btn-check:checked + .btn {
        background-color: #0d6efd;
        border-color: #0d6efd;
        color: white;
    }
    
    #newSpanModal .btn-group.is-invalid {
        border: 1px solid #dc3545;
    }
    
    #newSpanModal .btn-group.is-invalid .btn {
        border-color: #dc3545;
    }
    
    #newSpanModal .section-header {
        border-bottom: 2px solid #e9ecef;
        padding-bottom: 0.5rem;
        margin-bottom: 1.5rem;
    }
    
    /* Responsive adjustments */
    @media (max-width: 768px) {
        #newSpanModal .modal-body {
            padding: 1.5rem;
        }
        
        #newSpanModal .modal-footer {
            padding: 1rem 1.5rem;
        }
    }
</style>

<script>
$(document).ready(function() {
    // Get timeless span types from the server
    const timelessSpanTypes = @json(\App\Models\SpanType::getTimelessTypes());
    
    function updateStartYearRequirement() {
        const typeSelect = document.getElementById('modal_type_id');
        const stateInputs = document.querySelectorAll('input[name="state"]');
        const startYearInput = document.getElementById('modal_start_year');
        
        const selectedType = typeSelect.value;
        const selectedState = Array.from(stateInputs).find(input => input.checked)?.value;
        
        const isTimeless = timelessSpanTypes.includes(selectedType);
        const isPlaceholder = selectedState === 'placeholder';
        
        console.log('updateStartYearRequirement called:', {
            selectedType,
            selectedState,
            isTimeless,
            isPlaceholder,
            startYearRequired: startYearInput.hasAttribute('required')
        });
        
        if (isTimeless || isPlaceholder) {
            startYearInput.removeAttribute('required');
            console.log('Removed required from start_year');
        } else {
            startYearInput.setAttribute('required', 'required');
            console.log('Added required to start_year');
        }
    }
    
    // Update on change
    $('#modal_type_id').on('change', updateStartYearRequirement);
    $('input[name="state"]').on('change', updateStartYearRequirement);
    
    // Update when modal is shown (to handle initial state)
    $('#newSpanModal').on('shown.bs.modal', function() {
        updateStartYearRequirement();
    });
    
    // Handle save button click
    $('#save-span-btn').on('click', function() {
        const form = $('#new-span-form');
        const formData = new FormData(form[0]);
        
        // Ensure state is set (default to placeholder if none selected)
        const selectedState = $('input[name="state"]:checked').val();
        console.log('Selected state before processing:', selectedState);
        
        if (!selectedState) {
            $('#state_placeholder').prop('checked', true);
            formData.set('state', 'placeholder');
            console.log('No state selected, defaulting to placeholder');
        } else {
            // Explicitly set the state in form data since radio buttons can be tricky with FormData
            formData.set('state', selectedState);
        }
        
        // Double-check what's in the form data
        console.log('Final form data state:', formData.get('state'));
        

        
        // Clear previous errors
        $('.is-invalid').removeClass('is-invalid');
        $('.invalid-feedback').text('');
        
        $.ajax({
            url: form.attr('action'),
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                'Accept': 'application/json'
            },
            success: function(response) {
                // Close modal
                $('#newSpanModal').modal('hide');
                
                // Show success message
                if (typeof showAlert === 'function') {
                    showAlert('Span created successfully!', 'success');
                } else {
                    alert('Span created successfully!');
                }
                
                // Redirect to the new span
                if (response.span_id) {
                    window.location.href = '/spans/' + response.span_id;
                } else {
                    // Refresh the page to show the new span
                    window.location.reload();
                }
            },
            error: function(xhr) {
                if (xhr.status === 422) {
                    // Validation errors
                    const errors = xhr.responseJSON.errors;
                    Object.keys(errors).forEach(function(field) {
                        let input;
                        if (field === 'state') {
                            // For state field, add error to the button group
                            input = $('input[name="state"]').closest('.btn-group');
                        } else {
                            input = $('#modal_' + field);
                        }
                        const errorDiv = $('#' + field + '-error');
                        
                        input.addClass('is-invalid');
                        errorDiv.text(errors[field][0]);
                    });
                } else {
                    // Other errors
                    alert('An error occurred while creating the span. Please try again.');
                }
            }
        });
    });
    
    // Reset form when modal is hidden
    $('#newSpanModal').on('hidden.bs.modal', function() {
        $('#new-span-form')[0].reset();
        $('.is-invalid').removeClass('is-invalid');
        $('.invalid-feedback').text('');
        // Reset button group to default (placeholder)
        $('input[name="state"]').prop('checked', false);
        $('#state_placeholder').prop('checked', true);
    });
});
</script> 