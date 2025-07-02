<!-- Access Level Selection Modal -->
<div class="modal fade" id="accessLevelModal" tabindex="-1" aria-labelledby="accessLevelModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="accessLevelModalLabel">Change Access Level</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-3">Select the new access level for this item:</p>
                
                <div class="form-check mb-3">
                    <input class="form-check-input" type="radio" name="accessLevel" id="accessPrivate" value="private">
                    <label class="form-check-label d-flex align-items-center" for="accessPrivate">
                        <i class="bi bi-lock text-danger me-2"></i>
                        <div>
                            <strong>Private</strong>
                            <div class="text-muted small">Only you can see this item</div>
                        </div>
                    </label>
                </div>
                
                <div class="form-check mb-3">
                    <input class="form-check-input" type="radio" name="accessLevel" id="accessShared" value="shared">
                    <label class="form-check-label d-flex align-items-center" for="accessShared">
                        <i class="bi bi-people text-warning me-2"></i>
                        <div>
                            <strong>Shared</strong>
                            <div class="text-muted small">Visible to people you've shared with</div>
                        </div>
                    </label>
                </div>
                
                <div class="form-check mb-3">
                    <input class="form-check-input" type="radio" name="accessLevel" id="accessPublic" value="public">
                    <label class="form-check-label d-flex align-items-center" for="accessPublic">
                        <i class="bi bi-globe text-success me-2"></i>
                        <div>
                            <strong>Public</strong>
                            <div class="text-muted small">Visible to everyone</div>
                        </div>
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmAccessLevel">Confirm Change</button>
            </div>
        </div>
    </div>
</div> 