@extends('layouts.app')

@section('page_title')
    Plaque Residence Connections
@endsection

@section('content')
<div class="py-4">
    <div class="row">
        <div class="col-12 d-flex justify-content-between align-items-center mb-4">
            <div>
                <a href="{{ route('admin.tools.index') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i>Back to Tools
                </a>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0">
                <i class="bi bi-house-door me-2"></i>
                Plaque residence connections
            </h5>
        </div>
        <div class="card-body">
            <p class="mb-2">
                Scan plaque spans that already have a featured person and a location, and create the matching
                person &rarr; lived in &rarr; place connection when the plaque text supports it.
            </p>
            <p class="text-muted small mb-0">
                Create is only allowed when the inscription mentions living here, in, or at the place
                and years can be parsed (for example “lived here 1837–1842”). Other plaques are listed so you can see gaps.
                <strong>Create all ready</strong> runs as a background job (same pattern as Braggoscope and plaque import), so you can leave the page.
            </p>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card bg-primary text-white h-100">
                <div class="card-body text-center">
                    <h4 class="mb-1" id="statTotalPlaques">{{ number_format($totalPlaques) }}</h4>
                    <small>Plaques in catalogue</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card bg-info text-white h-100">
                <div class="card-body text-center">
                    <h4 class="mb-1" id="statScanned">0</h4>
                    <small>Plaques scanned</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card bg-success text-white h-100">
                <div class="card-body text-center">
                    <h4 class="mb-1" id="statHasResidence">0</h4>
                    <small>Already have a residence</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card bg-warning text-white h-100">
                <div class="card-body text-center">
                    <h4 class="mb-1" id="statCreatable">0</h4>
                    <small>Ready to create</small>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
                <button type="button" class="btn btn-primary" id="startScanBtn">
                    <i class="bi bi-search me-1"></i>Scan plaques
                </button>
                <button type="button" class="btn btn-success" id="createSelectedBtn" disabled>
                    <i class="bi bi-plus-circle me-1"></i>Create selected
                </button>
                <button type="button" class="btn btn-outline-success" id="createAllBtn">
                    <i class="bi bi-cloud-upload me-1"></i>Create all ready
                </button>
                <button type="button" class="btn btn-outline-danger d-none" id="cancelCreateAllBtn">
                    <i class="bi bi-x-circle me-1"></i>Cancel
                </button>
                <span class="text-muted small" id="scanStatus">Not scanned yet.</span>
            </div>

            <div class="progress mb-3 d-none" id="scanProgressWrap">
                <div class="progress-bar progress-bar-striped progress-bar-animated" id="scanProgressBar" role="progressbar" style="width: 0%">0%</div>
            </div>

            <div class="alert d-none" id="toolAlert" role="alert"></div>

            <div class="btn-group mb-3" role="group" aria-label="Row filters">
                <input type="radio" class="btn-check" name="rowFilter" id="filterAll" value="all" checked>
                <label class="btn btn-outline-secondary btn-sm" for="filterAll">All</label>

                <input type="radio" class="btn-check" name="rowFilter" id="filterCreatable" value="creatable">
                <label class="btn btn-outline-secondary btn-sm" for="filterCreatable">Ready to create</label>

                <input type="radio" class="btn-check" name="rowFilter" id="filterMissing" value="missing">
                <label class="btn btn-outline-secondary btn-sm" for="filterMissing">Missing residence</label>

                <input type="radio" class="btn-check" name="rowFilter" id="filterExisting" value="existing">
                <label class="btn btn-outline-secondary btn-sm" for="filterExisting">Already connected</label>
            </div>

            <div class="table-responsive">
                <table class="table table-striped table-sm align-middle" id="plaqueRowsTable">
                    <thead>
                        <tr>
                            <th>
                                <input type="checkbox" id="selectAllCreatable" title="Select all ready rows">
                            </th>
                            <th>Plaque</th>
                            <th>Person</th>
                            <th>Place</th>
                            <th>Inscription</th>
                            <th>Dates</th>
                            <th>Residence</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="empty-row">
                            <td colspan="8" class="text-center text-muted">Scan plaques to see person / place matches.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(document).ready(function () {
    const scanUrl = @json(route('admin.tools.plaque-residence-connections.scan'));
    const createUrl = @json(route('admin.tools.plaque-residence-connections.create'));
    const createBackgroundUrl = @json(route('admin.tools.plaque-residence-connections.create-background'));
    const cancelBackgroundUrl = @json(route('admin.tools.plaque-residence-connections.cancel-background'));
    const statusUrl = @json(route('admin.tools.plaque-residence-connections.status'));
    const csrfToken = $('meta[name="csrf-token"]').attr('content');
    const batchSize = 25;
    const createBatchSize = 10;

    let scanning = false;
    let creating = false;
    let jobRunning = false;
    let backgroundPollInterval = null;
    let rowsByKey = {};

    $.ajaxSetup({
        headers: { 'X-CSRF-TOKEN': csrfToken }
    });

    function showAlert(message, type) {
        const $alert = $('#toolAlert');
        $alert.removeClass('d-none alert-success alert-danger alert-info alert-warning')
            .addClass('alert-' + type)
            .text(message);
    }

    function hideAlert() {
        $('#toolAlert').addClass('d-none').text('');
    }

    function currentFilter() {
        return $('input[name="rowFilter"]:checked').val();
    }

    function rowMatchesFilter($row, filter) {
        if (filter === 'all') {
            return true;
        }
        if (filter === 'creatable') {
            return $row.data('can-create') === 1;
        }
        if (filter === 'missing') {
            return $row.data('has-residence') !== 1;
        }
        if (filter === 'existing') {
            return $row.data('has-residence') === 1;
        }
        return true;
    }

    function applyFilter() {
        const filter = currentFilter();
        const $rows = $('#plaqueRowsTable tbody tr.plaque-row');
        $rows.each(function () {
            $(this).toggle(rowMatchesFilter($(this), filter));
        });
    }

    function updateStats() {
        const rows = Object.values(rowsByKey);
        const hasResidence = rows.filter(function (row) { return row.has_residence; }).length;
        const creatable = rows.filter(function (row) { return row.can_create; }).length;
        $('#statHasResidence').text(hasResidence);
        $('#statCreatable').text(creatable);
        updateCreateButtons();
    }

    function updateCreateButtons() {
        const selected = selectedCreatableKeys().length;
        const busy = creating || scanning || jobRunning;
        $('#createSelectedBtn').prop('disabled', busy || selected === 0);
        $('#createAllBtn').prop('disabled', busy);
        $('#cancelCreateAllBtn').toggleClass('d-none', !jobRunning);
        $('#selectAllCreatable').prop('disabled', busy);
        $('#startScanBtn').prop('disabled', busy);
    }

    function selectedCreatableKeys() {
        const keys = [];
        $('#plaqueRowsTable tbody input.row-select:checked').each(function () {
            const key = $(this).closest('tr').data('key');
            if (rowsByKey[key] && rowsByKey[key].can_create) {
                keys.push(key);
            }
        });
        return keys;
    }

    function escapeHtml(value) {
        return $('<div>').text(value == null ? '' : String(value)).html();
    }

    function residenceCell(row) {
        if (row.has_residence) {
            const label = row.residence_dates ? 'Connected (' + row.residence_dates + ')' : 'Connected';
            if (row.residence_url) {
                return '<a href="' + escapeHtml(row.residence_url) + '" target="_blank">' + escapeHtml(label) + '</a>';
            }
            return escapeHtml(label);
        }
        if (row.can_create) {
            return '<span class="badge bg-warning text-dark">Ready to create</span>';
        }
        if (row.create_blocked_reason === 'no_lived_phrase') {
            return '<span class="text-muted">Does not mention living there</span>';
        }
        if (row.create_blocked_reason === 'no_dates') {
            return '<span class="text-muted">No dates in inscription</span>';
        }
        return '<span class="text-muted">Missing</span>';
    }

    function datesCell(row) {
        if (row.start_year && row.end_year) {
            return row.start_year + '–' + row.end_year;
        }
        return '<span class="text-muted">—</span>';
    }

    function createButton(row) {
        if (row.can_create) {
            return '<button type="button" class="btn btn-sm btn-success create-one-btn">Create</button>';
        }
        return '';
    }

    function checkboxCell(row) {
        if (row.can_create) {
            return '<input type="checkbox" class="row-select">';
        }
        return '';
    }

    function inscriptionCell(row) {
        const text = row.inscription || '';
        if (!text) {
            return '<span class="text-muted">—</span>';
        }
        return escapeHtml(text);
    }

    function renderRow(row) {
        return (
            '<tr class="plaque-row"' +
            ' data-key="' + escapeHtml(row.key) + '"' +
            ' data-can-create="' + (row.can_create ? 1 : 0) + '"' +
            ' data-has-residence="' + (row.has_residence ? 1 : 0) + '">' +
            '<td>' + checkboxCell(row) + '</td>' +
            '<td><a href="' + escapeHtml(row.plaque_url) + '" target="_blank">' + escapeHtml(row.plaque_name) + '</a></td>' +
            '<td><a href="' + escapeHtml(row.person_url) + '" target="_blank">' + escapeHtml(row.person_name) + '</a></td>' +
            '<td><a href="' + escapeHtml(row.place_url) + '" target="_blank">' + escapeHtml(row.place_name) + '</a></td>' +
            '<td class="small text-break">' + inscriptionCell(row) + '</td>' +
            '<td>' + datesCell(row) + '</td>' +
            '<td class="residence-cell">' + residenceCell(row) + '</td>' +
            '<td class="text-end create-cell">' + createButton(row) + '</td>' +
            '</tr>'
        );
    }

    function upsertRow(row) {
        rowsByKey[row.key] = row;
        const $existing = $('#plaqueRowsTable tbody tr[data-key="' + row.key + '"]');
        const html = renderRow(row);
        if ($existing.length) {
            $existing.replaceWith(html);
        } else {
            $('#plaqueRowsTable tbody').append(html);
        }
        applyFilter();
        updateStats();
    }

    function setProgress(scanned, total) {
        const percent = total > 0 ? Math.round((scanned / total) * 100) : 0;
        $('#scanProgressWrap').removeClass('d-none');
        $('#scanProgressBar').css('width', percent + '%').text(percent + '%');
    }

    function scanBatch(offset) {
        return $.get(scanUrl, { limit: batchSize, offset: offset });
    }

    function startScan() {
        if (scanning) {
            return;
        }

        scanning = true;
        rowsByKey = {};
        hideAlert();
        $('#plaqueRowsTable tbody').empty();
        $('#startScanBtn').prop('disabled', true).html('<i class="bi bi-hourglass-split me-1"></i>Scanning…');
        $('#scanStatus').text('Starting scan…');
        updateCreateButtons();

        function continueScan(offset, scannedSoFar) {
            scanBatch(offset)
                .done(function (response) {
                    const data = response.data || {};
                    const total = data.total_plaques || 0;
                    const scanned = scannedSoFar + (data.plaques_scanned || 0);

                    $('#statTotalPlaques').text(total);
                    $('#statScanned').text(scanned);
                    setProgress(scanned, total);

                    $('#plaqueRowsTable tbody tr.empty-row').remove();

                    (data.rows || []).forEach(function (row) {
                        upsertRow(row);
                    });

                    $('#scanStatus').text('Scanned ' + scanned + ' of ' + total + ' plaques.');

                    if (data.has_more) {
                        continueScan(offset + batchSize, scanned);
                    } else {
                        scanning = false;
                        $('#startScanBtn').prop('disabled', false).html('<i class="bi bi-search me-1"></i>Scan plaques');
                        $('#scanProgressBar').removeClass('progress-bar-animated');
                        if (Object.keys(rowsByKey).length === 0) {
                            $('#plaqueRowsTable tbody').html(
                                '<tr class="empty-row"><td colspan="8" class="text-center text-muted">No plaques found with both a featured person and a location.</td></tr>'
                            );
                        }
                        updateCreateButtons();
                    }
                })
                .fail(function (xhr) {
                    scanning = false;
                    $('#startScanBtn').prop('disabled', false).html('<i class="bi bi-search me-1"></i>Scan plaques');
                    const message = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Scan failed.';
                    showAlert(message, 'danger');
                    updateCreateButtons();
                });
        }

        continueScan(0, 0);
    }

    function createItems(items) {
        if (creating || jobRunning || items.length === 0) {
            return;
        }

        creating = true;
        updateCreateButtons();
        hideAlert();

        let created = 0;
        let skipped = 0;
        let ineligible = 0;
        let errors = 0;
        let index = 0;

        function nextBatch() {
            const batch = items.slice(index, index + createBatchSize);
            if (batch.length === 0) {
                creating = false;
                const parts = [];
                if (created) {
                    parts.push('created ' + created);
                }
                if (skipped) {
                    parts.push('skipped ' + skipped);
                }
                if (ineligible) {
                    parts.push('ineligible ' + ineligible);
                }
                if (errors) {
                    parts.push('errors ' + errors);
                }
                showAlert(parts.length ? 'Finished: ' + parts.join(', ') + '.' : 'Nothing to create.', errors ? 'warning' : 'success');
                updateCreateButtons();
                return;
            }

            $('#scanStatus').text('Creating ' + Math.min(index + batch.length, items.length) + ' of ' + items.length + '…');

            $.post(createUrl, { items: batch })
                .done(function (response) {
                    created += response.created || 0;
                    skipped += response.skipped || 0;
                    ineligible += response.ineligible || 0;
                    errors += response.errors || 0;

                    (response.results || []).forEach(function (result) {
                        if (result.row) {
                            upsertRow(result.row);
                        }
                    });

                    index += batch.length;
                    nextBatch();
                })
                .fail(function (xhr) {
                    creating = false;
                    const message = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Create failed.';
                    showAlert(message, 'danger');
                    updateCreateButtons();
                });
        }

        nextBatch();
    }

    function itemsFromKeys(keys) {
        return keys.map(function (key) {
            const row = rowsByKey[key];
            return {
                plaque_id: row.plaque_id,
                person_id: row.person_id,
                place_id: row.place_id
            };
        });
    }

    $('#startScanBtn').on('click', startScan);

    $('input[name="rowFilter"]').on('change', applyFilter);

    $('#selectAllCreatable').on('change', function () {
        const checked = $(this).is(':checked');
        $('#plaqueRowsTable tbody tr.plaque-row').each(function () {
            const $row = $(this);
            if ($row.data('can-create') === 1 && $row.is(':visible')) {
                $row.find('input.row-select').prop('checked', checked);
            }
        });
        updateCreateButtons();
    });

    $('#plaqueRowsTable').on('change', 'input.row-select', updateCreateButtons);

    $('#plaqueRowsTable').on('click', '.create-one-btn', function () {
        const key = $(this).closest('tr').data('key');
        createItems(itemsFromKeys([key]));
    });

    $('#createSelectedBtn').on('click', function () {
        createItems(itemsFromKeys(selectedCreatableKeys()));
    });

    $('#createAllBtn').on('click', function () {
        const creatableCount = Object.values(rowsByKey).filter(function (row) { return row.can_create; }).length;
        const message = creatableCount > 0
            ? 'Create all eligible residence connections in the background? The scan found ' + creatableCount + ' ready row(s). You can leave this page while it runs.'
            : 'Find and create all eligible residence connections in the background? You can leave this page while it runs.';
        if (!window.confirm(message)) {
            return;
        }

        $('#createAllBtn').prop('disabled', true);
        jobRunning = true;
        updateCreateButtons();
        startBackgroundPolling();

        $.post(createBackgroundUrl)
            .done(function (response) {
                if (!response.success) {
                    jobRunning = false;
                    stopBackgroundPolling();
                    showAlert(response.message || 'Failed to start background create.', 'danger');
                    updateCreateButtons();
                    return;
                }
                loadBackgroundStatus();
            })
            .fail(function (xhr) {
                jobRunning = false;
                stopBackgroundPolling();
                const message = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Failed to start background create.';
                showAlert(message, 'danger');
                updateCreateButtons();
            });
    });

    $('#cancelCreateAllBtn').on('click', function () {
        if (!window.confirm('Cancel the background create? It will stop after the current batch.')) {
            return;
        }
        $.post(cancelBackgroundUrl)
            .done(function (response) {
                showAlert(response.message || 'Cancelled.', 'warning');
                loadBackgroundStatus();
            })
            .fail(function (xhr) {
                const message = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Failed to cancel.';
                showAlert(message, 'danger');
            });
    });

    function startBackgroundPolling() {
        if (backgroundPollInterval) {
            return;
        }
        backgroundPollInterval = setInterval(loadBackgroundStatus, 2000);
    }

    function stopBackgroundPolling() {
        if (backgroundPollInterval) {
            clearInterval(backgroundPollInterval);
            backgroundPollInterval = null;
        }
    }

    function applyCreatedKeys(keys) {
        if (!keys || !keys.length) {
            return;
        }
        keys.forEach(function (key) {
            const row = rowsByKey[key];
            if (!row) {
                return;
            }
            row.can_create = false;
            row.has_residence = true;
            row.create_blocked_reason = 'already_has_residence';
            if (!row.residence_dates && row.start_year && row.end_year) {
                row.residence_dates = row.start_year + ' – ' + row.end_year;
            }
            upsertRow(row);
        });
    }

    function loadBackgroundStatus() {
        $.get(statusUrl)
            .done(function (response) {
                const wasRunning = jobRunning;

                if (!response.background_job) {
                    if (jobRunning) {
                        $('#scanStatus').text('Waiting for background worker…');
                        startBackgroundPolling();
                        return;
                    }
                    jobRunning = false;
                    stopBackgroundPolling();
                    updateCreateButtons();
                    return;
                }

                const jp = response.job_progress || {};
                const pct = jp.progress_percentage || 0;
                jobRunning = response.job_status === 'running';

                if (jobRunning || wasRunning) {
                    $('#scanProgressWrap').removeClass('d-none');
                    $('#scanProgressBar')
                        .toggleClass('progress-bar-animated', jobRunning)
                        .css('width', pct + '%')
                        .text(pct + '%');

                    const parts = [
                        'Processed ' + (jp.processed || 0) + ' of ' + (jp.total || 0) + ' plaques',
                        'created ' + (jp.created || 0),
                        'skipped ' + (jp.skipped || 0)
                    ];
                    if (jp.errors) {
                        parts.push('errors ' + jp.errors);
                    }
                    if (jp.current_item && jobRunning) {
                        parts.push(jp.current_item);
                    }
                    $('#scanStatus').text(parts.join(' · '));
                    applyCreatedKeys(jp.created_keys || []);
                }

                if (jobRunning) {
                    startBackgroundPolling();
                } else {
                    stopBackgroundPolling();
                }

                if (wasRunning && response.job_status === 'completed') {
                    showAlert('Finished: created ' + (jp.created || 0) + ', skipped ' + (jp.skipped || 0) + '.', 'success');
                } else if (wasRunning && response.job_status === 'cancelled') {
                    showAlert('Background create cancelled after ' + (jp.created || 0) + ' created.', 'warning');
                } else if (wasRunning && response.job_status === 'failed') {
                    showAlert(jp.error || 'Background create failed.', 'danger');
                }

                updateCreateButtons();
            })
            .fail(function () {
                showAlert('Could not load background job status.', 'danger');
            });
    }

    loadBackgroundStatus();
});
</script>
@endpush
