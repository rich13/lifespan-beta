<!-- Group Permissions Modal -->
<div class="modal fade" id="groupPermissionsModal" tabindex="-1" aria-labelledby="groupPermissionsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="groupPermissionsModalLabel">Manage Group Permissions</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-4">Select which groups have access to this span:</p>
                
                <div id="groupPermissionsCheckboxes">
                    <!-- Checkboxes will be loaded here by JavaScript -->
                    <div class="text-center py-3">
                        <div class="spinner-border spinner-border-sm" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmGroupPermissions">Save Changes</button>
            </div>
        </div>
    </div>
</div>
