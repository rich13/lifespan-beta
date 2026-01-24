<!-- Add Connection Modal -->
<div class="modal fade" id="addConnectionModal" tabindex="-1" aria-labelledby="addConnectionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addConnectionModalLabel">
                    <i class="bi bi-link-45deg me-2"></i><span id="modalTitleText">Add Connection</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="addConnectionStatus"></div>
                <form id="addConnectionForm">
                    @csrf
                    <input type="hidden" id="connectionId" name="connection_id">
                    
                    <!-- Subject, Predicate, Object Row -->
                    <div class="row g-3 mb-4">
                        <!-- Subject (Current Span) - Read Only -->
                        <div class="col-md-4">
                            <label class="form-label fw-medium">Subject</label>
                            <div class="form-control-plaintext bg-light" id="connectionSubject" style="min-height: 38px;">
                                <!-- Will be populated by JavaScript -->
                            </div>
                        </div>

                        <!-- Predicate (Connection Type) -->
                        <div class="col-md-4">
                            <label for="connectionPredicate" class="form-label fw-medium">Connection</label>
                            <select class="form-select" id="connectionPredicate" name="connection_type" required>
                                <option value="">Select...</option>
                                <!-- Will be populated by JavaScript -->
                            </select>
                        </div>

                        <!-- Object (Target Span) -->
                        <div class="col-md-4">
                            <label for="connectionObject" class="form-label fw-medium">Object</label>
                            <input type="text" class="form-control" id="connectionObject" 
                                   name="object_search" placeholder="Search..." disabled>
                            <input type="hidden" id="connectionObjectId" name="object_id">
                            <input type="hidden" id="allowedSpanTypes" name="allowed_span_types">
                            <div id="connectionObjectDisplay" class="form-control-plaintext bg-light d-none" style="min-height: 38px;">
                                <!-- Will be populated when editing -->
                            </div>
                        </div>
                    </div>
                    
                    <!-- Direction Toggle -->
                    <div class="mb-3 text-center">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="directionToggle">
                            <i class="bi bi-arrow-left-right me-1"></i><span id="directionLabel">Forward</span>
                        </button>
                    </div>
                    
                    <!-- Search Results (Full Width) -->
                    <div class="mb-3">
                        <div id="searchResults" class="border rounded" style="max-height: 200px; overflow-y: auto;"></div>
                    </div>

                    <!-- Start Date -->
                    <div class="mb-3">
                        <label class="form-label fw-medium">Start Date</label>
                        <div class="row g-2">
                            <div class="col-4">
                                <input type="number" class="form-control" id="startYear" name="start_year" 
                                       placeholder="YYYY" min="1000" max="2100">
                                <div class="form-text">Year</div>
                            </div>
                            <div class="col-4">
                                <input type="number" class="form-control" id="startMonth" name="start_month" 
                                       placeholder="MM" min="1" max="12">
                                <div class="form-text">Month</div>
                            </div>
                            <div class="col-4">
                                <input type="number" class="form-control" id="startDay" name="start_day" 
                                       placeholder="DD" min="1" max="31">
                                <div class="form-text">Day</div>
                            </div>
                        </div>
                        <div class="form-text" id="startDateHelp">Required for draft and complete connections</div>
                    </div>

                    <!-- End Date -->
                    <div class="mb-3">
                        <label class="form-label fw-medium">End Date</label>
                        <div class="row g-2">
                            <div class="col-4">
                                <input type="number" class="form-control" id="endYear" name="end_year" 
                                       placeholder="YYYY" min="1000" max="2100">
                                <div class="form-text">Year</div>
                            </div>
                            <div class="col-4">
                                <input type="number" class="form-control" id="endMonth" name="end_month" 
                                       placeholder="MM" min="1" max="12">
                                <div class="form-text">Month</div>
                            </div>
                            <div class="col-4">
                                <input type="number" class="form-control" id="endDay" name="end_day" 
                                       placeholder="DD" min="1" max="31">
                                <div class="form-text">Day</div>
                            </div>
                        </div>
                        <div class="form-text">Optional - leave blank if connection is ongoing</div>
                    </div>
                </form>
            </div>
            <div class="modal-footer d-flex justify-content-between align-items-center">
                <!-- Connection State -->
                <div class="d-flex align-items-center">
                    <label class="form-label mb-0 me-2 fw-medium">State:</label>
                    <div class="btn-group" role="group" aria-label="Connection State">
                        <input type="radio" class="btn-check" name="state" id="statePlaceholder" value="placeholder" autocomplete="off" checked>
                        <label class="btn btn-outline-secondary btn-sm" for="statePlaceholder">Placeholder</label>
                        <input type="radio" class="btn-check" name="state" id="stateDraft" value="draft" autocomplete="off">
                        <label class="btn btn-outline-secondary btn-sm" for="stateDraft">Draft</label>
                        <input type="radio" class="btn-check" name="state" id="stateComplete" value="complete" autocomplete="off">
                        <label class="btn btn-outline-secondary btn-sm" for="stateComplete">Complete</label>
                    </div>
                </div>
                <div>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="addConnectionSubmitBtn" form="addConnectionForm">
                        <span class="spinner-border spinner-border-sm me-1 d-none" id="addConnectionSpinner" role="status" aria-hidden="true"></span>
                        <i class="bi bi-plus-circle me-1 d-none" id="createIcon"></i>
                        <i class="bi bi-save me-1 d-none" id="saveIcon"></i>
                        <span id="submitButtonText">Create</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div> 