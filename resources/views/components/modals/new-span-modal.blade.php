<!-- New Span Modal -->
<div class="modal fade" id="newSpanModal" tabindex="-1" aria-labelledby="newSpanModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <div class="modal-header border-bottom">
                <h5 class="modal-title fw-bold" id="newSpanModalLabel">
                    <i class="bi bi-plus-circle me-2 text-primary"></i>Create New Span
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <!-- Dynamic content area -->
            <div class="modal-body" id="modal-content">
                <!-- Content will be dynamically replaced -->
            </div>

            <div class="modal-footer border-top" id="modal-footer">
                <!-- Footer will be dynamically replaced -->
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
        padding: 1rem 1.5rem;
    }
    
    #newSpanModal .modal-body {
        padding: 1.5rem;
    }
    
    #newSpanModal .modal-footer {
        padding: 1rem 1.5rem;
        background-color: #f8f9fa;
        border-radius: 0 0 0.75rem 0.75rem;
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
    }
    
    #newSpanModal .form-label {
        color: #495057;
        margin-bottom: 0.5rem;
        font-size: 0.9rem;
    }
    
    #newSpanModal .form-control,
    #newSpanModal .form-select {
        border-radius: 0.5rem;
        border: 1px solid #dee2e6;
        transition: all 0.2s ease;
        font-size: 0.9rem;
    }
    
    #newSpanModal .form-control:focus,
    #newSpanModal .form-select:focus {
        border-color: #0d6efd;
        box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.15);
    }
    
    #newSpanModal .btn {
        border-radius: 0.5rem;
        font-weight: 500;
        transition: all 0.2s ease;
        font-size: 0.9rem;
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
        font-size: 0.8rem;
        padding: 0.375rem 0.75rem;
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
    
    /* Card selection styling */
    #newSpanModal .card {
        cursor: pointer;
        transition: all 0.2s ease;
        border: 2px solid #dee2e6;
        border-radius: 0.5rem;
    }
    
    #newSpanModal .card:hover {
        border-color: #0d6efd;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(13, 110, 253, 0.15);
    }
    
    #newSpanModal .card.selected {
        border-color: #0d6efd;
        background-color: #f8f9ff;
    }
    
    #newSpanModal .card-body {
        padding: 1rem;
    }
    
    /* Badge styling */
    #newSpanModal .badge {
        font-size: 0.75rem;
        padding: 0.5rem 0.75rem;
    }
    
    /* Alert styling */
    #newSpanModal .alert {
        border-radius: 0.5rem;
        font-size: 0.85rem;
    }
    
    /* Responsive adjustments */
    @media (max-width: 768px) {
        #newSpanModal .modal-body {
            padding: 1rem;
        }
        
        #newSpanModal .modal-footer {
            padding: 0.75rem 1rem;
        }
    }
</style>

<script>
$(document).ready(function() {
    let currentStep = 1;
    let aiData = null;
    let selectedCreationMethod = null;
    let formData = {};
    let isImproveMode = false;
    
    // Get timeless span types from the server
    const timelessSpanTypes = @json(\App\Models\SpanType::getTimelessTypes());
    
    // Handle Improve button click
    $('#improve-span-btn').on('click', function() {
        isImproveMode = true;
        const spanName = $(this).data('span-name');
        const spanType = $(this).data('span-type');
        
        // Prefill the form data
        formData.name = spanName;
        formData.type_id = spanType;
        
        // Update modal title
        $('#newSpanModalLabel').html('<i class="bi bi-magic me-2 text-success"></i>Improve Span');
        
        // For people, skip directly to the creation method selection
        if (spanType === 'person') {
            // Show step 1 first to set up the form, then immediately go to step 2
            showStep(1);
            setTimeout(() => {
                showStep(2);
            }, 100);
        }
    });
    
    // Handle New button click (reset improve mode)
    $('#new-span-btn').on('click', function() {
        isImproveMode = false;
        formData = {};
        $('#newSpanModalLabel').html('<i class="bi bi-plus-circle me-2 text-primary"></i>Create New Span');
    });
    
    // Reset modal state when closed
    $('#newSpanModal').on('hidden.bs.modal', function() {
        isImproveMode = false;
        currentStep = 1;
        aiData = null;
        selectedCreationMethod = null;
        formData = {};
        $('#newSpanModalLabel').html('<i class="bi bi-plus-circle me-2 text-primary"></i>Create New Span');
    });
    
    // Step content templates
    const stepContent = {
        1: `
            <div class="text-center mb-3">
                <div class="badge bg-primary mb-2">Step 1 of 3</div>
                <h6 class="text-muted">Basic Information</h6>
            </div>
            
            <div class="mb-3">
                <label for="modal_name" class="form-label fw-medium">Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" 
                       id="modal_name" name="name" required 
                       placeholder="Enter span name...">
                <div class="invalid-feedback" id="name-error"></div>
            </div>

            <div class="mb-3">
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
        `,
        
        2: `
            <div class="text-center mb-3">
                <div class="badge bg-primary mb-2">Step 2 of 3</div>
                <h6 class="text-muted">Create Person</h6>
                <p class="text-muted small">How would you like to create this person?</p>
            </div>
            
            <div class="row g-3">
                <div class="col-6">
                    <div class="card h-100 border-2" id="create-manual-card" 
                         tabindex="2" role="button" aria-label="Create manually - fill in all details yourself"
                         onkeydown="if(event.key === 'Enter' || event.key === ' ') { event.preventDefault(); $(this).click(); }">
                        <div class="card-body text-center p-3">
                            <i class="bi bi-pencil-square text-primary mb-2" style="font-size: 1.5rem;"></i>
                            <h6 class="card-title mb-1">Create Manually</h6>
                            <p class="card-text text-muted small mb-0">Fill in all the details yourself</p>
                        </div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="card h-100 border-2" id="create-ai-card" 
                         tabindex="1" role="button" aria-label="Use AI to create - AI will research and fill in details" autofocus
                         onkeydown="if(event.key === 'Enter' || event.key === ' ') { event.preventDefault(); $(this).click(); }">
                        <div class="card-body text-center p-3">
                            <i class="bi bi-robot text-success mb-2" style="font-size: 1.5rem;"></i>
                            <h6 class="card-title mb-1">Use AI to Create</h6>
                            <p class="card-text text-muted small mb-0">AI will research and fill in details</p>
                        </div>
                    </div>
                </div>
            </div>
        `,
        
        3: `
            <div class="text-center mb-3">
                <div class="badge bg-primary mb-2">Step 3 of 3</div>
                <h6 class="text-muted">Additional Details</h6>
            </div>
            
            <form id="new-span-form">
                <div class="mb-3">
                    <label class="form-label fw-medium">Start Date</label>
                    <div class="row g-2">
                        <div class="col-4">
                            <input type="number" class="form-control form-control-sm" 
                                   id="modal_start_year" name="start_year"
                                   placeholder="Year">
                            <div class="invalid-feedback" id="start_year-error"></div>
                        </div>
                        <div class="col-4">
                            <input type="number" class="form-control form-control-sm" 
                                   id="modal_start_month" name="start_month" min="1" max="12"
                                   placeholder="Month">
                            <div class="invalid-feedback" id="start_month-error"></div>
                        </div>
                        <div class="col-4">
                            <input type="number" class="form-control form-control-sm" 
                                   id="modal_start_day" name="start_day" min="1" max="31"
                                   placeholder="Day">
                            <div class="invalid-feedback" id="start_day-error"></div>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-medium">End Date</label>
                    <div class="row g-2">
                        <div class="col-4">
                            <input type="number" class="form-control form-control-sm" 
                                   id="modal_end_year" name="end_year"
                                   placeholder="Year">
                            <div class="invalid-feedback" id="end_year-error"></div>
                        </div>
                        <div class="col-4">
                            <input type="number" class="form-control form-control-sm" 
                                   id="modal_end_month" name="end_month" min="1" max="12"
                                   placeholder="Month">
                            <div class="invalid-feedback" id="end_month-error"></div>
                        </div>
                        <div class="col-4">
                            <input type="number" class="form-control form-control-sm" 
                                   id="modal_end_day" name="end_day" min="1" max="31"
                                   placeholder="Day">
                            <div class="invalid-feedback" id="end_day-error"></div>
                        </div>
                    </div>
                    
                    <small class="text-muted">
                        <i class="bi bi-info-circle me-1"></i>
                        Leave dates empty for timeless spans or placeholders
                    </small>
                </div>
            </form>
        `,
        
        4: `
            <div class="text-center">
                <div class="spinner-border text-primary mb-3" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <h6 class="text-muted mb-2">AI is doing its thing...</h6>
                <p class="text-muted small">Finding out about <span id="ai-person-name"></span></p>
                <p class="text-muted small">Yes, it's <strike>a bit</strike> <strong>very</strong> slow...</p>
            </div>
        `,
        
        5: `
            <div class="text-center mb-3">
                <div class="badge bg-success mb-2">AI Results</div>
                <h6 class="text-muted">OK, I think it worked...</h6>
            </div>
            
            <div id="ai-success-content" class="d-none">
                <div class="alert alert-success py-2">
                    <i class="bi bi-check-circle me-2"></i>
                    <strong>Success!</strong> AI found information about this person.
                </div>
                
                <div class="card mb-3">
                    <div class="card-body p-3">
                        <h6 class="card-title">Summary of Found Information</h6>
                        <div id="ai-summary" class="small"></div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-body p-3">
                        <h6 class="card-title">Raw YAML Data</h6>
                        <div id="ai-details" class="small"></div>
                    </div>
                </div>
            </div>
            
            <div id="ai-error-content" class="d-none">
                <div class="alert alert-warning py-2">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <strong>Limited Information</strong> AI couldn't find much information about this person.
                </div>
                
                <div class="card">
                    <div class="card-body p-3">
                        <h6 class="card-title">Available Information</h6>
                        <div id="ai-error-details" class="small"></div>
                    </div>
                </div>
            </div>
        `,
        
        6: `
            <div class="text-center mb-3">
                <div class="badge bg-warning mb-2">Merge Available</div>
                <h6 class="text-muted">Merge Confirmation</h6>
                <p class="mb-3">A span with this name and type already exists. Do you want to merge the AI data into the existing span?</p>
                <div class="card mb-3">
                    <div class="card-body p-2">
                        <strong>Existing Span:</strong><br>
                        <span id="merge-existing-name"></span> <span class="text-muted">(<span id="merge-existing-type"></span>)</span>
                    </div>
                </div>
            </div>
        `,
        
        7: `
            <div class="text-center mb-3">
                <div class="badge bg-success mb-2">Success!</div>
                <h6 class="text-muted">Span Created Successfully</h6>
            </div>
            
            <div class="alert alert-success">
                <i class="bi bi-check-circle me-2"></i>
                <strong>Span created successfully!</strong>
            </div>
        `
    };
    
    // Footer templates
    const stepFooter = {
        1: `
            <button type="button" class="btn btn-outline-secondary me-2" data-bs-dismiss="modal">
                <i class="bi bi-x-circle me-1"></i>Cancel
            </button>
            <button type="button" class="btn btn-primary px-4" id="next-step-btn" tabindex="1" autofocus>
                <i class="bi bi-arrow-right me-1"></i>Next
            </button>
        `,
        
        2: `
            <button type="button" class="btn btn-outline-secondary me-2" id="back-to-step1">
                <i class="bi bi-arrow-left me-1"></i>Back
            </button>
        `,
        
        3: `
            <div class="d-flex align-items-center">
                <div class="me-3">
                    <div class="btn-group btn-group-sm" role="group" aria-label="Span state selection">                                 
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
                <button type="button" class="btn btn-primary px-4" id="save-span-btn" tabindex="1" autofocus>
                    <i class="bi bi-check-circle me-1"></i>Create Span
                </button>
            </div>
        `,
        
        4: `
            <button type="button" class="btn btn-outline-secondary me-2" data-bs-dismiss="modal">
                <i class="bi bi-x-circle me-1"></i>Cancel
            </button>
        `,
        
        5: `
            <button type="button" class="btn btn-outline-secondary me-2" id="back-to-manual" 
                    aria-label="Go back to manual creation form">
                <i class="bi bi-pencil me-1"></i>Create Manually
            </button>
            <button type="button" class="btn btn-primary px-4" id="confirm-ai-btn" 
                    aria-label="Create span using AI generated data" tabindex="1" autofocus>
                <i class="bi bi-check-circle me-1"></i>Create with AI Data
            </button>
        `,
        
        6: `
            <button type="button" class="btn btn-outline-secondary me-2" id="cancel-merge-btn">
                <i class="bi bi-x-circle me-1"></i>Cancel
            </button>
            <button type="button" class="btn btn-primary px-4" id="confirm-merge-btn" tabindex="1" autofocus>
                <i class="bi bi-arrow-repeat me-1"></i>Merge and Update
            </button>
        `,
        
        7: `
            <button type="button" class="btn btn-primary px-4" id="view-span-btn" 
                    aria-label="View the newly created span" tabindex="1" autofocus>
                <i class="bi bi-eye me-1"></i>View Span
            </button>
        `
    };
    
    function showStep(step) {
        currentStep = step;
        
        // Update modal content
        $('#modal-content').html(stepContent[step]);
        $('#modal-footer').html(stepFooter[step]);
        
        // Re-attach event listeners for the new content
        attachEventListeners(step);
        
        // Prefill form fields if in improve mode
        if (isImproveMode && step === 1) {
            $('#modal_name').val(formData.name);
            $('#modal_type_id').val(formData.type_id);
        }
        
        // Autofill merge info if merge step
        if (step === 6 && window.mergeData) {
            $('#merge-existing-name').text(window.mergeData.existing_span.name);
            $('#merge-existing-type').text(window.mergeData.existing_span.type_id);
        }
    }
    
    function attachEventListeners(step) {
        if (step === 1) {
            // Step 1: Next button
            $('#next-step-btn').on('click', function() {
                const name = $('#modal_name').val().trim();
                const typeId = $('#modal_type_id').val();
                
                if (!name || !typeId) {
                    if (!name) {
                        $('#modal_name').addClass('is-invalid');
                        $('#name-error').text('Name is required');
                    }
                    if (!typeId) {
                        $('#modal_type_id').addClass('is-invalid');
                        $('#type_id-error').text('Type is required');
                    }
                    return;
                }
                
                // Clear validation errors
                $('.is-invalid').removeClass('is-invalid');
                $('.invalid-feedback').text('');
                
                // Store form data
                formData.name = name;
                formData.type_id = typeId;
                
                if (typeId === 'person') {
                    showStep(2);
                } else {
                    showStep(3);
                }
            });
        }
        
        if (step === 2) {
            // Step 2: Card selection
            $('#create-manual-card, #create-ai-card').on('click', function() {
                $('.card').removeClass('selected');
                $(this).addClass('selected');
                
                if ($(this).attr('id') === 'create-manual-card') {
                    selectedCreationMethod = 'manual';
                    showStep(3);
                } else {
                    selectedCreationMethod = 'ai';
                    showStep(4);
                    startAiProcess();
                }
            });
            
            // Back button
            $('#back-to-step1').on('click', function() {
                showStep(1);
            });
        }
        
        if (step === 3) {
            // Step 3: Manual form
            $('#save-span-btn').on('click', function() {
                const form = $('#new-span-form');
                const formDataObj = new FormData(form[0]);
                
                // Add stored data
                formDataObj.append('name', formData.name);
                formDataObj.append('type_id', formData.type_id);
                formDataObj.append('_token', $('meta[name="csrf-token"]').attr('content'));
                
                // Ensure state is set
                const selectedState = $('input[name="state"]:checked').val();
                if (!selectedState) {
                    $('#state_placeholder').prop('checked', true);
                    formDataObj.set('state', 'placeholder');
                } else {
                    formDataObj.set('state', selectedState);
                }
                
                // Clear previous errors
                $('.is-invalid').removeClass('is-invalid');
                $('.invalid-feedback').text('');
                
                $.ajax({
                    url: '{{ route("spans.store") }}',
                    method: 'POST',
                    data: formDataObj,
                    processData: false,
                    contentType: false,
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                        'Accept': 'application/json'
                    },
                    success: function(response) {
                        $('#newSpanModal').modal('hide');
                        
                        if (typeof showAlert === 'function') {
                            showAlert('Span created successfully!', 'success');
                        } else {
                            alert('Span created successfully!');
                        }
                        
                        if (response.span_id) {
                            window.location.href = '/spans/' + response.span_id;
                        } else {
                            window.location.reload();
                        }
                    },
                    error: function(xhr) {
                        if (xhr.status === 422) {
                            const errors = xhr.responseJSON.errors;
                            Object.keys(errors).forEach(function(field) {
                                let input;
                                if (field === 'state') {
                                    input = $('input[name="state"]').closest('.btn-group');
                                } else {
                                    input = $('#modal_' + field);
                                }
                                const errorDiv = $('#' + field + '-error');
                                
                                input.addClass('is-invalid');
                                errorDiv.text(errors[field][0]);
                            });
                        } else {
                            alert('An error occurred while creating the span. Please try again.');
                        }
                    }
                });
            });
        }
        
        if (step === 5) {
            // Step 5: AI results
            $('#back-to-manual').on('click', function() {
                showStep(3);
            });
            
            $('#confirm-ai-btn').on('click', function() {
                if (!aiData) {
                    alert('No AI data available');
                    return;
                }
                
                // Show loading state
                const btn = $(this);
                const originalText = btn.html();
                btn.prop('disabled', true);
                btn.html('<span class="spinner-border spinner-border-sm me-1"></span>Creating...');
                
                // Create span with AI data
                $.ajax({
                    url: '{{ route("spans.store") }}',
                    method: 'POST',
                    data: {
                        name: formData.name,
                        type_id: formData.type_id,
                        state: 'placeholder',
                        ai_yaml: aiData.yaml,
                        _token: $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response) {
                        if (response.merge_available) {
                            window.mergeData = response;
                            showStep(6);
                            return;
                        }
                        showCreationSuccess(response);
                    },
                    error: function(xhr) {
                        // Reset button state
                        btn.prop('disabled', false);
                        btn.html(originalText);
                        showStep(5);
                        alert('Failed to create span with AI data. Please try again.');
                    }
                });
            });
        }
        if (step === 6) {
            // Merge confirmation step
            $('#confirm-merge-btn').on('click', function() {
                if (!window.mergeData) return;
                
                // Show loading state
                const btn = $(this);
                const originalText = btn.html();
                btn.prop('disabled', true);
                btn.html('<span class="spinner-border spinner-border-sm me-1"></span>Merging...');
                
                // Send confirm_merge=true
                $.ajax({
                    url: '{{ route("spans.store") }}',
                    method: 'POST',
                    data: {
                        name: formData.name,
                        type_id: formData.type_id,
                        state: 'placeholder',
                        ai_yaml: aiData.yaml,
                        confirm_merge: true,
                        _token: $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response) {
                        showCreationSuccess(response);
                    },
                    error: function(xhr) {
                        // Reset button state
                        btn.prop('disabled', false);
                        btn.html(originalText);
                        alert('Failed to merge and update span. Please try again.');
                    }
                });
            });
            $('#cancel-merge-btn').on('click', function() {
                showStep(5);
            });
        }
        
        if (step === 7) {
            // Step 7: Success summary
            $('#view-span-btn').on('click', function() {
                $('#newSpanModal').modal('hide');
                
                if (window.aiCreationResponse && window.aiCreationResponse.span_id) {
                    window.location.href = '/spans/' + window.aiCreationResponse.span_id;
                } else {
                    window.location.reload();
                }
            });
        }
    }
    
    function updateStartYearRequirement() {
        const typeSelect = document.getElementById('modal_type_id');
        const stateInputs = document.querySelectorAll('input[name="state"]');
        const startYearInput = document.getElementById('modal_start_year');
        
        if (!typeSelect || !startYearInput) return;
        
        const selectedType = typeSelect.value;
        const selectedState = Array.from(stateInputs).find(input => input.checked)?.value;
        
        const isTimeless = timelessSpanTypes.includes(selectedType);
        const isPlaceholder = selectedState === 'placeholder';
        
        if (isTimeless || isPlaceholder) {
            startYearInput.removeAttribute('required');
        } else {
            startYearInput.setAttribute('required', 'required');
        }
    }
    
    // AI Process
    function startAiProcess() {
        const name = formData.name;
        $('#ai-person-name').text(name);
        
        $.ajax({
            url: '{{ route("ai-yaml-generator.generate") }}',
            method: 'POST',
            data: {
                name: name,
                _token: $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                if (response.success) {
                    aiData = response;
                    showAiResults(true);
                } else {
                    showAiResults(false, response.error);
                }
            },
            error: function(xhr) {
                showAiResults(false, 'Failed to generate AI data');
            }
        });
    }
    
    function showAiResults(success, error = null) {
        if (success) {
            $('#ai-success-content').removeClass('d-none');
            $('#ai-error-content').addClass('d-none');
            
            // Parse YAML and generate summary
            const summary = parseYamlSummary(aiData.yaml);
            $('#ai-summary').html(summary);
            
            // Display AI generated details (truncated for compact view)
            const details = aiData.yaml;
            const truncatedDetails = details.length > 300 ? details.substring(0, 300) + '...' : details;
            $('#ai-details').html(`<pre class="mb-0 small"><code>${truncatedDetails}</code></pre>`);
        } else {
            $('#ai-success-content').addClass('d-none');
            $('#ai-error-content').removeClass('d-none');
            $('#ai-error-details').text(error || 'An error occurred');
        }
        
        showStep(5);
    }
    
    function parseYamlSummary(yamlText) {
        try {
            // Simple YAML parsing to extract key information
            const lines = yamlText.split('\n');
            const summary = [];
            
            // Extract basic info
            const nameMatch = yamlText.match(/name:\s*['"]?([^'\n]+)['"]?/);
            const typeMatch = yamlText.match(/type:\s*(\w+)/);
            const startMatch = yamlText.match(/start:\s*['"]?([^'\n]+)['"]?/);
            const endMatch = yamlText.match(/end:\s*['"]?([^'\n]+)['"]?/);
            
            if (nameMatch) summary.push(`<strong>Name:</strong> ${nameMatch[1]}`);
            if (typeMatch) summary.push(`<strong>Type:</strong> ${typeMatch[1]}`);
            if (startMatch) summary.push(`<strong>Start:</strong> ${startMatch[1]}`);
            if (endMatch) summary.push(`<strong>End:</strong> ${endMatch[1]}`);
            
            // Count connections
            const connectionTypes = ['parents', 'children', 'relationship', 'employment', 'education', 'residence'];
            const connections = [];
            
            connectionTypes.forEach(type => {
                const regex = new RegExp(`${type}:\\s*\\n\\s*-\\s*name:`, 'g');
                const matches = yamlText.match(regex);
                if (matches) {
                    connections.push(`${matches.length} ${type}`);
                }
            });
            
            if (connections.length > 0) {
                summary.push(`<strong>Connections:</strong> ${connections.join(', ')}`);
            }
            
            // Extract metadata
            const metadataMatches = yamlText.match(/metadata:\s*\n((?:\s+[^:\n]+:\s*[^\n]+\n?)+)/);
            if (metadataMatches) {
                const metadataLines = metadataMatches[1].split('\n').filter(line => line.trim());
                const metadata = metadataLines.map(line => {
                    const match = line.match(/\s+([^:]+):\s*(.+)/);
                    return match ? `${match[1].trim()}: ${match[2].trim()}` : null;
                }).filter(Boolean);
                
                if (metadata.length > 0) {
                    summary.push(`<strong>Details:</strong> ${metadata.join(', ')}`);
                }
            }
            
            return summary.length > 0 ? summary.join('<br>') : 'Information found but details not parsed';
            
        } catch (error) {
            return 'Information found but could not parse details';
        }
    }
    
    function showCreationSuccess(response) {
        // Store the response for the view button
        window.aiCreationResponse = response;
        
        // Show success step
        showStep(7);
    }
    
    function showCreationSummary(response) {
        if (response.span) {
            // Format the start date nicely
            let startDate = 'Not specified';
            if (response.span.start_year) {
                if (response.span.start_month && response.span.start_day) {
                    startDate = `${response.span.start_day}/${response.span.start_month}/${response.span.start_year}`;
                } else if (response.span.start_month) {
                    startDate = `${response.span.start_month}/${response.span.start_year}`;
                } else {
                    startDate = response.span.start_year.toString();
                }
            }
            
            return `
                <div class="text-center">
                    <h5 class="mb-3">${response.span.name}</h5>
                    <p class="text-muted mb-3">
                        <i class="bi bi-calendar-event me-1"></i>
                        ${startDate}
                    </p>
                </div>
            `;
        }
        
        return '<p class="text-muted">Span created successfully</p>';
    }
    
    // Initialize modal
    $('#newSpanModal').on('show.bs.modal', function() {
        showStep(1);
    });
    
    // Reset form when modal is hidden
    $('#newSpanModal').on('hidden.bs.modal', function() {
        // Reset variables
        currentStep = 1;
        aiData = null;
        selectedCreationMethod = null;
        formData = {};
        window.aiCreationResponse = null;
        window.mergeData = null; // Reset merge data
        
        // Clear any validation errors
        $('.is-invalid').removeClass('is-invalid');
        $('.invalid-feedback').text('');
    });
});
</script> 