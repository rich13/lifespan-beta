@extends('layouts.app')

@section('page_title')
    Fix Private Individual Connections
@endsection

@section('content')
<div class="py-4">
    <div class="row">
        <div class="col-12 d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0">Fix Private Individual Connections</h1>
            <a href="{{ route('admin.tools.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Back to Tools
            </a>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Overview</h5>
        </div>
        <div class="card-body">
            <p class="mb-2">
                This tool makes private individuals and their <strong>connection spans</strong> private,
                so personal spans are not exposed on public pages. The other end of each connection
                (a public figure, place, or organisation) is left unchanged.
            </p>
            <p class="text-muted small mb-0">
                <strong>Fix all</strong> runs as a background job (same pattern as Braggoscope and plaque import),
                so the page stays responsive and you can leave while it runs.
            </p>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card h-100">
                <div class="card-body text-center">
                    <h4 class="text-primary mb-1" id="statTotalPrivate">{{ $stats['total_private_individuals'] }}</h4>
                    <small class="text-muted">Total Private Individuals</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card h-100">
                <div class="card-body text-center">
                    <h4 class="text-warning mb-1" id="statWithPublic">{{ $stats['private_individuals_with_public_connections'] }}</h4>
                    <small class="text-muted">Individuals with Public Connections</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card h-100">
                <div class="card-body text-center">
                    <h4 class="text-danger mb-1" id="statPublicConnections">{{ $stats['total_public_connections'] }}</h4>
                    <small class="text-muted">Total Public Connections</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card h-100">
                <div class="card-body text-center">
                    <h4 class="text-success mb-1" id="statNeedingFix">{{ $stats['individuals_needing_fix'] }}</h4>
                    <small class="text-muted">Still Need Fixing</small>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
                <button type="button" class="btn btn-outline-primary" id="scanBtn">
                    <i class="bi bi-search me-1"></i>Scan affected
                </button>
                <button type="button" class="btn btn-primary" id="fixSelectedBtn" disabled>
                    <i class="bi bi-check-circle me-1"></i>Fix selected
                </button>
                <button type="button" class="btn btn-success" id="fixAllBtn">
                    <i class="bi bi-cloud-upload me-1"></i>Fix all
                </button>
                <button type="button" class="btn btn-outline-danger d-none" id="cancelFixBtn">
                    <i class="bi bi-x-circle me-1"></i>Cancel
                </button>
                <span class="text-muted small" id="scanStatus">Not scanned yet.</span>
            </div>

            <div class="progress mb-3 d-none" id="progressWrap">
                <div class="progress-bar progress-bar-striped progress-bar-animated" id="progressBar" role="progressbar">0%</div>
            </div>

            <div class="alert d-none" id="toolAlert" role="alert"></div>

            <div class="table-responsive">
                <table class="table table-hover table-sm align-middle" id="individualsTable">
                    <thead>
                        <tr>
                            <th>
                                <input type="checkbox" id="selectAll" title="Select all visible rows">
                            </th>
                            <th>Name</th>
                            <th>Access Level</th>
                            <th>Non-private Connections</th>
                            <th>Owner</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="empty-row">
                            <td colspan="6" class="text-center text-muted">Scan to list private individuals whose connections are still public.</td>
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
    const scanUrl = @json(route('admin.tools.fix-private-individual-connections.scan'));
    const statsUrl = @json(route('admin.tools.fix-private-individual-connections.stats'));
    const startUrl = @json(route('admin.tools.fix-private-individual-connections.start-background'));
    const cancelUrl = @json(route('admin.tools.fix-private-individual-connections.cancel-background'));
    const statusUrl = @json(route('admin.tools.fix-private-individual-connections.status'));
    const csrfToken = $('meta[name="csrf-token"]').attr('content');
    const batchSize = 25;

    let scanning = false;
    let jobRunning = false;
    let backgroundPollInterval = null;
    let rowsById = {};

    $.ajaxSetup({
        headers: { 'X-CSRF-TOKEN': csrfToken }
    });

    function showAlert(message, type) {
        $('#toolAlert')
            .removeClass('d-none alert-success alert-danger alert-info alert-warning')
            .addClass('alert-' + type)
            .text(message);
    }

    function hideAlert() {
        $('#toolAlert').addClass('d-none').text('');
    }

    function escapeHtml(value) {
        return $('<div>').text(value == null ? '' : String(value)).html();
    }

    function selectedIds() {
        const ids = [];
        $('#individualsTable tbody input.row-select:checked').each(function () {
            ids.push($(this).closest('tr').data('id'));
        });
        return ids;
    }

    function updateButtons() {
        const selected = selectedIds().length;
        const busy = scanning || jobRunning;
        $('#fixSelectedBtn').prop('disabled', busy || selected === 0);
        $('#fixAllBtn').prop('disabled', busy);
        $('#scanBtn').prop('disabled', busy);
        $('#selectAll').prop('disabled', busy);
        $('#cancelFixBtn').toggleClass('d-none', !jobRunning);
    }

    function accessBadge(level) {
        const klass = level === 'private' ? 'success' : 'warning';
        return '<span class="badge bg-' + klass + '">' + escapeHtml(level.charAt(0).toUpperCase() + level.slice(1)) + '</span>';
    }

    function connectionCell(row) {
        if (row.public_connection_count > 0) {
            return '<span class="badge bg-danger">' + row.public_connection_count + ' public/shared</span>';
        }
        return '<span class="badge bg-success">All private</span>';
    }

    function renderRow(row) {
        return (
            '<tr class="individual-row table-warning" data-id="' + escapeHtml(row.id) + '">' +
            '<td><input type="checkbox" class="row-select"></td>' +
            '<td><a href="' + escapeHtml(row.url) + '" target="_blank"><strong>' + escapeHtml(row.name) + '</strong></a>' +
            (row.description ? '<br><small class="text-muted">' + escapeHtml(row.description).substring(0, 50) + '</small>' : '') +
            '</td>' +
            '<td>' + accessBadge(row.access_level) + '</td>' +
            '<td class="connection-cell">' + connectionCell(row) + '</td>' +
            '<td>' + escapeHtml(row.owner_name) + '</td>' +
            '<td class="text-end"><button type="button" class="btn btn-sm btn-warning fix-one-btn">Fix</button></td>' +
            '</tr>'
        );
    }

    function upsertRow(row) {
        rowsById[row.id] = row;
        const $existing = $('#individualsTable tbody tr[data-id="' + row.id + '"]');
        if ($existing.length) {
            $existing.replaceWith(renderRow(row));
        } else {
            $('#individualsTable tbody').append(renderRow(row));
        }
    }

    function markRowFixed(id) {
        const $row = $('#individualsTable tbody tr[data-id="' + id + '"]');
        if (!$row.length) {
            return;
        }
        $row.removeClass('table-warning');
        $row.find('input.row-select').prop('checked', false).prop('disabled', true);
        $row.find('.connection-cell').html('<span class="badge bg-success">All private</span>');
        $row.find('.fix-one-btn').replaceWith('<span class="text-muted">No action needed</span>');
        delete rowsById[id];
        updateButtons();
    }

    function setProgress(processed, total, animated) {
        const percent = total > 0 ? Math.round((processed / total) * 100) : 0;
        $('#progressWrap').removeClass('d-none');
        $('#progressBar')
            .toggleClass('progress-bar-animated', !!animated)
            .css('width', percent + '%')
            .text(percent + '%');
    }

    function refreshStats() {
        $.get(statsUrl).done(function (response) {
            const stats = response.stats || {};
            $('#statTotalPrivate').text(stats.total_private_individuals || 0);
            $('#statWithPublic').text(stats.private_individuals_with_public_connections || 0);
            $('#statPublicConnections').text(stats.total_public_connections || 0);
            $('#statNeedingFix').text(stats.individuals_needing_fix || 0);
        });
    }

    function startScan() {
        if (scanning || jobRunning) {
            return;
        }

        scanning = true;
        rowsById = {};
        hideAlert();
        $('#individualsTable tbody').empty();
        $('#scanBtn').prop('disabled', true).html('<i class="bi bi-hourglass-split me-1"></i>Scanning…');
        $('#scanStatus').text('Starting scan…');
        updateButtons();

        function continueScan(offset, scannedSoFar) {
            $.get(scanUrl, { limit: batchSize, offset: offset })
                .done(function (response) {
                    const data = response.data || {};
                    const total = data.total || 0;
                    const scanned = scannedSoFar + (data.scanned || 0);

                    setProgress(scanned, total, data.has_more);
                    $('#individualsTable tbody tr.empty-row').remove();

                    (data.rows || []).forEach(function (row) {
                        upsertRow(row);
                    });

                    $('#scanStatus').text('Scanned ' + scanned + ' of ' + total + ' affected people.');

                    if (data.has_more) {
                        continueScan(offset + batchSize, scanned);
                    } else {
                        scanning = false;
                        $('#scanBtn').prop('disabled', false).html('<i class="bi bi-search me-1"></i>Scan affected');
                        $('#progressBar').removeClass('progress-bar-animated');
                        if (Object.keys(rowsById).length === 0) {
                            $('#individualsTable tbody').html(
                                '<tr class="empty-row"><td colspan="6" class="text-center text-muted">No private individuals currently expose public connection spans.</td></tr>'
                            );
                        }
                        updateButtons();
                    }
                })
                .fail(function (xhr) {
                    scanning = false;
                    $('#scanBtn').prop('disabled', false).html('<i class="bi bi-search me-1"></i>Scan affected');
                    const message = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Scan failed.';
                    showAlert(message, 'danger');
                    updateButtons();
                });
        }

        continueScan(0, 0);
    }

    function startFix(individualIds) {
        if (jobRunning || scanning) {
            return;
        }

        const isSelected = Array.isArray(individualIds) && individualIds.length > 0;
        const message = isSelected
            ? 'Make connections private for ' + individualIds.length + ' selected private individual(s)? This runs in the background.'
            : 'Make connections private for all private individuals that still expose public spans? This runs in the background and you can leave the page.';

        if (!window.confirm(message)) {
            return;
        }

        hideAlert();
        jobRunning = true;
        updateButtons();
        startBackgroundPolling();

        const payload = isSelected ? { individual_ids: individualIds } : {};

        $.post(startUrl, payload)
            .done(function (response) {
                if (!response.success) {
                    jobRunning = false;
                    stopBackgroundPolling();
                    showAlert(response.message || 'Failed to start background fix.', 'danger');
                    updateButtons();
                    return;
                }
                loadBackgroundStatus();
            })
            .fail(function (xhr) {
                jobRunning = false;
                stopBackgroundPolling();
                const message = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Failed to start background fix.';
                showAlert(message, 'danger');
                updateButtons();
            });
    }

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

    function loadBackgroundStatus() {
        $.get(statusUrl)
            .done(function (response) {
                const wasRunning = jobRunning;

                if (!response.background_job) {
                    jobRunning = false;
                    stopBackgroundPolling();
                    updateButtons();
                    return;
                }

                const jp = response.job_progress || {};
                const pct = jp.progress_percentage || 0;
                jobRunning = response.job_status === 'running';

                if (jobRunning || wasRunning) {
                    $('#progressWrap').removeClass('d-none');
                    $('#progressBar')
                        .toggleClass('progress-bar-animated', jobRunning)
                        .css('width', pct + '%')
                        .text(pct + '%');

                    const parts = [
                        'Processed ' + (jp.processed || 0) + ' of ' + (jp.total || 0) + ' people',
                        'connections made private: ' + (jp.created || 0)
                    ];
                    if (jp.skipped) {
                        parts.push('skipped ' + jp.skipped);
                    }
                    if (jp.errors) {
                        parts.push('errors ' + jp.errors);
                    }
                    if (jp.current_item && jobRunning) {
                        parts.push(jp.current_item);
                    }
                    $('#scanStatus').text(parts.join(' · '));

                    (jp.fixed_ids || []).forEach(function (id) {
                        markRowFixed(id);
                    });
                }

                if (jobRunning) {
                    startBackgroundPolling();
                } else {
                    stopBackgroundPolling();
                }

                if (wasRunning && response.job_status === 'completed') {
                    showAlert('Finished: made ' + (jp.created || 0) + ' connection span(s) private.', 'success');
                    refreshStats();
                } else if (wasRunning && response.job_status === 'cancelled') {
                    showAlert('Background fix cancelled after ' + (jp.created || 0) + ' connection span(s) made private.', 'warning');
                    refreshStats();
                } else if (wasRunning && response.job_status === 'failed') {
                    showAlert(jp.error || 'Background fix failed.', 'danger');
                }

                updateButtons();
            })
            .fail(function () {
                showAlert('Could not load background job status.', 'danger');
            });
    }

    $('#scanBtn').on('click', startScan);

    $('#selectAll').on('change', function () {
        const checked = $(this).is(':checked');
        $('#individualsTable tbody input.row-select:not(:disabled)').prop('checked', checked);
        updateButtons();
    });

    $('#individualsTable').on('change', 'input.row-select', updateButtons);

    $('#individualsTable').on('click', '.fix-one-btn', function () {
        const id = $(this).closest('tr').data('id');
        startFix([id]);
    });

    $('#fixSelectedBtn').on('click', function () {
        startFix(selectedIds());
    });

    $('#fixAllBtn').on('click', function () {
        startFix(null);
    });

    $('#cancelFixBtn').on('click', function () {
        if (!window.confirm('Cancel the background fix? It will stop after the current batch.')) {
            return;
        }
        $.post(cancelUrl)
            .done(function (response) {
                showAlert(response.message || 'Cancelled.', 'warning');
                loadBackgroundStatus();
            })
            .fail(function (xhr) {
                const message = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Failed to cancel.';
                showAlert(message, 'danger');
            });
    });

    loadBackgroundStatus();
    updateButtons();
});
</script>
@endpush
