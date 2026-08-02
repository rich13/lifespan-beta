<div id="virtualPlaqueAdminCard" class="border-top d-none">
    <div class="card border-0 border-bottom rounded-0">
        <div class="card-body d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">
                <i class="bi bi-award me-2"></i>
                Virtual Plaque
            </h5>
            <span class="badge bg-secondary" id="virtualPlaqueStatusBadge">—</span>
        </div>
    </div>
    <div class="p-3">
        <div id="virtualPlaqueLoading" class="text-center py-4 d-none">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2 text-muted small mb-0">Loading connection details...</p>
        </div>
        <div id="virtualPlaqueContent" class="d-none">
            <p id="virtualPlaqueMessage" class="text-muted small mb-0 d-none"></p>
            <div id="virtualPlaquePairSelectors" class="mb-3 d-none">
                <label class="form-label small mb-1">Person</label>
                <select class="form-select form-select-sm mb-2" id="virtualPlaquePersonSelect"></select>
                <label class="form-label small mb-1">Place</label>
                <select class="form-select form-select-sm" id="virtualPlaquePlaceSelect"></select>
            </div>
            <div id="virtualPlaqueCreateForm" class="d-none">
                <div id="virtualPlaqueConnectionSummary" class="mb-3">
                    <h6 class="text-muted mb-2">Connection</h6>
                    <p class="mb-1 small">
                        <strong>Subject:</strong>
                        <a href="#" id="virtualPlaqueSubjectLink"></a>
                    </p>
                    <p class="mb-1 small">
                        <strong>Object:</strong>
                        <a href="#" id="virtualPlaqueObjectLink"></a>
                    </p>
                    <p class="mb-0 small text-muted" id="virtualPlaqueConnectionSentence"></p>
                    <p class="mb-0 mt-2 small text-muted d-none" id="virtualPlaquePlaceholderNote">
                        No dates provided — connection span will be created as a placeholder.
                    </p>
                </div>
                <div class="mb-3">
                    <h6 class="text-muted mb-2">Connection type</h6>
                    <select class="form-select form-select-sm" id="virtualPlaqueTypeSelect"></select>
                    <p id="virtualPlaqueSuggestedHint" class="small text-muted mt-2 mb-0"></p>
                </div>
                <div class="mb-3">
                    <h6 class="text-muted mb-2">Dates</h6>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label small mb-1">From year</label>
                            <input type="number" class="form-control form-control-sm" id="virtualPlaqueStartYear" min="1" max="9999">
                        </div>
                        <div class="col-6">
                            <label class="form-label small mb-1">To year</label>
                            <input type="number" class="form-control form-control-sm" id="virtualPlaqueEndYear" min="1" max="9999">
                        </div>
                    </div>
                </div>
                <button type="button" class="btn btn-primary btn-sm w-100" id="virtualPlaqueCreateBtn">
                    Create connection
                </button>
            </div>
            <div id="virtualPlaquePreviewWrap" class="d-none">
                <a href="#" target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm w-100" id="virtualPlaqueOpenLink">
                    Open virtual plaque
                </a>
            </div>
        </div>
    </div>
</div>
