@extends('layouts.app')

@section('title', 'Braggoscope Episodes Import')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-12">
            <nav aria-label="breadcrumb" class="mb-3">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.import.index') }}">Import</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Braggoscope Episodes</li>
                </ol>
            </nav>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="bi bi-broadcast me-2"></i>
                        Braggoscope Episodes Import (In Our Time)
                    </h3>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <h5><i class="bi bi-info-circle me-2"></i>About this importer</h5>
                        <p class="mb-0">
                            This tool imports episodes from the public Braggoscope feed
                            (<code>episodes.json</code>) and creates:
                        </p>
                        <ul class="mb-0 mt-2">
                            <li><strong>Thing spans</strong> with subtype <code>episode</code> for each programme episode</li>
                            <li><strong>A thing span</strong> with subtype <code>programme</code> for the series (e.g. In Our Time)</li>
                            <li><strong>Contains connections</strong> from the programme to each episode</li>
                        </ul>
                    </div>

                    <div id="alertsContainer"></div>

                    <div class="row mb-4" id="importStatusSection">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Import Status</h5>
                                </div>
                                <div class="card-body">
                                    <div id="importStatusContent">
                                        <div class="text-center">
                                            <div class="spinner-border text-primary" role="status">
                                                <span class="visually-hidden">Loading...</span>
                                            </div>
                                            <p class="mt-2 text-muted">Loading import status...</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Import Controls</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-4 mb-2">
                                            <button type="button" class="btn btn-success w-100" id="importAllBtn">
                                                <i class="bi bi-play-fill me-2"></i>
                                                Import All Episodes
                                            </button>
                                        </div>
                                        <div class="col-md-4 mb-2">
                                            <button type="button" class="btn btn-outline-success w-100" id="importBackgroundBtn">
                                                <i class="bi bi-cloud-upload me-2"></i>
                                                Import in Background
                                            </button>
                                        </div>
                                        <div class="col-md-4 mb-2">
                                            <button type="button" class="btn btn-primary w-100" id="resumeImportBtn" style="display: none;">
                                                <i class="bi bi-arrow-clockwise me-2"></i>
                                                Resume Import
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">
                                        <i class="bi bi-search me-2"></i>
                                        Import a single episode
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <p class="text-muted small mb-3">
                                        Search the Braggoscope feed by title, description, or episode id and import a single episode
                                        without running the full importer.
                                    </p>
                                    <div class="row align-items-end">
                                        <div class="col-md-6 mb-2">
                                            <label for="episodeSearchQuery" class="form-label">Search term</label>
                                            <input type="text"
                                                   class="form-control"
                                                   id="episodeSearchQuery"
                                                   placeholder="e.g. Fibonacci, Anatomy, p005488j"
                                                   maxlength="200">
                                        </div>
                                        <div class="col-md-3 mb-2">
                                            <button type="button" class="btn btn-outline-primary w-100" id="searchEpisodeBtn">
                                                <i class="bi bi-search me-2"></i>Search
                                            </button>
                                        </div>
                                    </div>
                                    <div id="searchEpisodeResults" class="mt-3" style="display: none;">
                                        <h6 class="mb-2">Results</h6>
                                        <div id="searchEpisodeResultsList"></div>
                                    </div>
                                    <div id="searchEpisodeEmpty" class="mt-3 alert alert-info" style="display: none;">
                                        No episodes found for that search.
                                    </div>
                                    <div id="searchEpisodeError" class="mt-3 alert alert-danger" style="display: none;"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-4" id="progressSection" style="display: none;">
                        <div class="col-md-8">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">
                                        <i class="bi bi-arrow-repeat me-2" id="progressSpinner"></i>
                                        Import Progress
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="progress mb-3" style="height: 25px;">
                                        <div class="progress-bar progress-bar-striped progress-bar-animated" id="progressBar" role="progressbar" style="width: 0%">
                                            <span id="progressText">0%</span>
                                        </div>
                                    </div>

                                    <div class="alert alert-info mb-3">
                                        <i class="bi bi-info-circle me-2"></i>
                                        <span id="statusText">Preparing import...</span>
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-md-4">
                                            <div class="text-center">
                                                <div class="h4 text-primary" id="processedCount">0</div>
                                                <small class="text-muted">Processed</small>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="text-center">
                                                <div class="h4 text-success" id="createdCount">0</div>
                                                <small class="text-muted">Created</small>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="text-center">
                                                <div class="h4 text-warning" id="skippedCount">0</div>
                                                <small class="text-muted">Skipped</small>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="text-center">
                                                <div class="h4 text-info" id="totalCount">0</div>
                                                <small class="text-muted">Total</small>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="text-center">
                                                <div class="h4 text-danger" id="errorCount">0</div>
                                                <small class="text-muted">Errors</small>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="text-center">
                                        <button type="button" class="btn btn-danger" id="cancelImportBtn">
                                            <i class="bi bi-x-circle me-2"></i>Cancel Import
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="card-title mb-0">
                                        <i class="bi bi-list-ul me-2"></i>
                                        Created Spans
                                    </h5>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="clearLogBtn" title="Clear log">
                                        <i class="bi bi-x-circle"></i>
                                    </button>
                                </div>
                                <div class="card-body" style="height: 600px; overflow-y: auto; padding: 0;">
                                    <div id="createdSpansLog" class="list-group list-group-flush">
                                        <div class="list-group-item text-muted text-center">
                                            <small>No spans created yet</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-4" id="statsSection" style="display: none;">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Current Statistics</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row" id="statsContent"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-4" id="resultsSection" style="display: none;">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Import Results</h5>
                                </div>
                                <div class="card-body">
                                    <div id="resultsContent"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<style>
#createdSpansLog {
    max-height: 600px;
    overflow-y: auto;
}

#createdSpansLog .list-group-item {
    border-left: 3px solid transparent;
    transition: all 0.2s ease;
}

#createdSpansLog .list-group-item:hover {
    background-color: #f8f9fa;
    border-left-color: #0d6efd;
}

#createdSpansLog .list-group-item a {
    color: #212529;
    transition: color 0.2s ease;
}

#createdSpansLog .list-group-item a:hover {
    color: #0d6efd;
}

#createdSpansLog .list-group-item[data-type="episode"] {
    border-left-color: #0d6efd;
}

#createdSpansLog .list-group-item[data-type="programme"] {
    border-left-color: #198754;
}
</style>
<script>
let isProcessing = false;
let currentOffset = 0;
let totalEpisodes = 0;
let cumulativeProcessed = 0;
let cumulativeCreated = 0;
let cumulativeSkipped = 0;
let cumulativeErrors = 0;
let createdSpansLog = [];
let backgroundJobPollInterval = null;

$(document).ready(function() {
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    loadImportStatus();
    loadStats();

    $('#importAllBtn').click(function() {
        if (isProcessing) {
            return;
        }
        if (!confirm('This will import all episodes from Braggoscope. Continue?')) {
            return;
        }
        startImport(0);
    });

    $('#importBackgroundBtn').click(function() {
        if (isProcessing) {
            return;
        }
        if (!confirm('Start import in background? This avoids browser timeouts.')) {
            return;
        }

        $(this).prop('disabled', true).html('<i class="bi bi-hourglass-split me-2"></i>Starting...');
        startBackgroundJobPolling();

        $.post('{{ route("admin.import.braggoscope.import-background") }}')
            .done(function(response) {
                if (!response.success) {
                    alert(response.message || 'Failed to start import');
                }
            })
            .fail(function(xhr) {
                alert('Failed to start import: ' + (xhr.responseJSON?.message || 'Unknown error'));
            })
            .always(function() {
                $('#importBackgroundBtn').prop('disabled', false).html('<i class="bi bi-cloud-upload me-2"></i>Import in Background');
            });
    });

    $('#resumeImportBtn').click(function() {
        if (isProcessing) {
            return;
        }
        const resumeOffset = $(this).data('resume-offset');
        if (!confirm(`This will resume importing from episode ${resumeOffset + 1}. Continue?`)) {
            return;
        }
        startImport(resumeOffset);
    });

    $('#cancelImportBtn').click(function() {
        if (confirm('Are you sure you want to cancel the import?')) {
            isProcessing = false;
            $('#progressSpinner').removeClass('bi-arrow-clockwise').addClass('bi-exclamation-triangle-fill').css('animation', 'none');
            $('#statusText').text('Import cancelled');
        }
    });

    $('#clearLogBtn').click(function() {
        createdSpansLog = [];
        updateCreatedSpansLog();
    });

    $('#searchEpisodeBtn').click(function() {
        const query = $.trim($('#episodeSearchQuery').val());
        if (!query) {
            alert('Please enter a search term.');
            return;
        }

        const $btn = $(this);
        $btn.prop('disabled', true).html('<i class="bi bi-hourglass-split me-2"></i>Searching...');
        $('#searchEpisodeError').hide().empty();
        $('#searchEpisodeEmpty').hide();
        $('#searchEpisodeResults').hide();

        $.post('{{ route("admin.import.braggoscope.search-episode") }}', {
            query: query
        })
            .done(function(response) {
                if (!response.success) {
                    $('#searchEpisodeError').text(response.message || 'Search failed').show();
                    return;
                }
                if (!response.matches || response.matches.length === 0) {
                    $('#searchEpisodeEmpty').show();
                    return;
                }

                let html = '<div class="list-group">';
                response.matches.forEach(function(m) {
                    const title = (m.title || 'Untitled episode').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                    const published = m.published ? m.published : 'Unknown date';
                    const url = m.url || null;
                    html += `
                        <div class="list-group-item list-group-item-action" data-episode-index="${m.index}">
                            <div class="d-flex w-100 justify-content-between align-items-start flex-wrap">
                                <div class="mb-1">
                                    <strong>${title}</strong>
                                    <br><small class="text-muted">${published}</small>
                                    ${url ? `<br><small class="text-muted"><a href="${url}" target="_blank">View on Braggoscope</a></small>` : ''}
                                </div>
                                <button type="button" class="btn btn-sm btn-success import-single-episode-btn" data-episode-index="${m.index}">
                                    <i class="bi bi-download me-1"></i>Import
                                </button>
                            </div>
                        </div>
                    `;
                });
                html += '</div>';
                $('#searchEpisodeResultsList').html(html);
                $('#searchEpisodeResults').show();
            })
            .fail(function(xhr) {
                $('#searchEpisodeError').text('Search failed: ' + (xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Unknown error')).show();
            })
            .always(function() {
                $btn.prop('disabled', false).html('<i class="bi bi-search me-2"></i>Search');
            });
    });

    $(document).on('click', '.import-single-episode-btn', function() {
        const episodeIndex = $(this).data('episode-index');
        const $btn = $(this);
        const $item = $btn.closest('.list-group-item');
        $btn.prop('disabled', true).html('<i class="bi bi-hourglass-split me-1"></i>Importing...');

        $.post('{{ route("admin.import.braggoscope.process-single") }}', {
            episode_index: episodeIndex
        })
            .done(function(response) {
                if (response.success) {
                    const details = response.details || {};
                    const skipped = details.skipped || false;
                    if (skipped) {
                        $btn.removeClass('btn-success').addClass('btn-secondary').html('<i class="bi bi-check2 me-1"></i>Already in database');
                    } else {
                        $btn.removeClass('btn-success').addClass('btn-secondary').html('<i class="bi bi-check2 me-1"></i>Imported');
                    }

                    if (details.episode_id) {
                        const url = '{{ url("/spans") }}/' + details.episode_id;
                        $item.append('<br><small><a href="' + url + '" target="_blank">View episode span</a></small>');
                    }
                } else {
                    $btn.prop('disabled', false).html('<i class="bi bi-download me-1"></i>Import');
                    alert(response.message || 'Import failed');
                }
            })
            .fail(function(xhr) {
                $btn.prop('disabled', false).html('<i class="bi bi-download me-1"></i>Import');
                alert('Import failed: ' + (xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Unknown error'));
            });
    });
});

function startImport(startOffset) {
    isProcessing = true;
    currentOffset = startOffset;
    cumulativeProcessed = 0;
    cumulativeCreated = 0;
    cumulativeSkipped = 0;
    cumulativeErrors = 0;
    createdSpansLog = [];

    showProgress();
    updateCreatedSpansLog();
    processNextBatch();
}

function processNextBatch() {
    if (!isProcessing) {
        return;
    }

    const batchSize = 20;

    $('#statusText').text('Processing episodes...');

    $.ajax({
        url: '{{ route("admin.import.braggoscope.process-batch") }}',
        method: 'POST',
        data: {
            batch_size: batchSize,
            offset: currentOffset
        },
        timeout: 30000
    })
    .done(function(response) {
        if (!response.success) {
            showError('Import failed: ' + (response.message || 'Unknown error'));
            isProcessing = false;
            return;
        }

        const data = response.data;
        totalEpisodes = data.total_episodes;

        cumulativeProcessed += data.processed;
        cumulativeCreated += data.created;
        cumulativeSkipped += data.skipped;
        cumulativeErrors += data.errors.length;

        if (data.created_spans && data.created_spans.length > 0) {
            createdSpansLog.push(...data.created_spans);
            updateCreatedSpansLog();
        }

        updateProgress(data.progress_percentage);

        if (!data.is_last_batch) {
            currentOffset = data.next_offset;
            setTimeout(function() {
                processNextBatch();
            }, 500);
        } else {
            isProcessing = false;
            $('#progressSpinner').removeClass('bi-arrow-clockwise').addClass('bi-check-circle-fill').css('animation', 'none');
            $('#statusText').text('Import completed successfully!');
            completeImport();
            loadStats();
        }
    })
    .fail(function(xhr) {
        let errorMessage = 'Import failed: ' + (xhr.responseJSON?.message || 'Unknown error');
        if (xhr.statusText === 'timeout') {
            errorMessage = 'Request timed out after 30 seconds. The batch may be too large.';
        }
        showError(errorMessage);
        isProcessing = false;
    });
}

function updateProgress(percentage) {
    $('#progressBar').css('width', percentage + '%');
    $('#progressText').text(percentage + '%');
    $('#processedCount').text(cumulativeProcessed);
    $('#createdCount').text(cumulativeCreated);
    $('#skippedCount').text(cumulativeSkipped);
    $('#totalCount').text(totalEpisodes);
    $('#errorCount').text(cumulativeErrors);
}

function showProgress() {
    $('#progressSection').show();
    $('#resultsSection').hide();
}

function updateCreatedSpansLog() {
    const logContainer = $('#createdSpansLog');
    const maxItems = 300;

    if (createdSpansLog.length === 0) {
        logContainer.html('<div class="list-group-item text-muted text-center"><small>No spans created yet</small></div>');
        return;
    }

    logContainer.empty();
    const displayLog = createdSpansLog.slice(-maxItems).reverse();

    $.each(displayLog, function(_, span) {
        const typeIcon = span.type === 'programme' ? 'collection-play' : 'broadcast';
        const typeLabel = span.type === 'programme' ? 'Programme' : 'Episode';

        const html = `
            <div class="list-group-item list-group-item-action" data-type="${span.type}">
                <div class="d-flex w-100 justify-content-between align-items-start">
                    <div class="flex-grow-1">
                        <div class="d-flex align-items-center mb-1">
                            <i class="bi bi-${typeIcon} me-2"></i>
                            <small class="text-muted">${typeLabel}</small>
                        </div>
                        <h6 class="mb-1">
                            <a href="${span.url}" target="_blank" class="text-decoration-none">
                                ${escapeHtml(span.name)}
                            </a>
                        </h6>
                        <small class="text-muted">ID: ${span.id.substring(0, 8)}...</small>
                    </div>
                </div>
            </div>
        `;
        logContainer.append(html);
    });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function completeImport() {
    $('#resultsSection').show();
    $('#resultsContent').html(`
        <div class="alert alert-success">
            <h5><i class="bi bi-check-circle me-2"></i>Import Completed!</h5>
            <p>Successfully processed all episodes.</p>
        </div>
        <div class="row">
            <div class="col-md-3">
                <div class="text-center">
                    <div class="h4 text-primary">${cumulativeProcessed}</div>
                    <small class="text-muted">Total Processed</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="text-center">
                    <div class="h4 text-success">${cumulativeCreated}</div>
                    <small class="text-muted">Created</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="text-center">
                    <div class="h4 text-warning">${cumulativeSkipped}</div>
                    <small class="text-muted">Skipped</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="text-center">
                    <div class="h4 text-danger">${cumulativeErrors}</div>
                    <small class="text-muted">Errors</small>
                </div>
            </div>
        </div>
    `);
}

function startBackgroundJobPolling() {
    if (backgroundJobPollInterval) {
        clearInterval(backgroundJobPollInterval);
    }
    loadImportStatus();
    backgroundJobPollInterval = setInterval(function() {
        loadImportStatus(true);
    }, 2000);
}

function stopBackgroundJobPolling() {
    if (backgroundJobPollInterval) {
        clearInterval(backgroundJobPollInterval);
        backgroundJobPollInterval = null;
    }
}

function loadImportStatus(isPolling) {
    $.get('{{ route("admin.import.braggoscope.status") }}')
        .done(function(response) {
            if (!response.success) {
                $('#importStatusContent').html(`
                    <div class="alert alert-warning mb-0">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Could not load import status: ${response.message || 'Unknown error'}
                    </div>
                `);
                return;
            }

            if (response.background_job && response.job_progress) {
                const jp = response.job_progress;
                const pct = jp.progress_percentage || 0;
                const statusLabel = response.job_status === 'running' ? '(in progress)' : response.job_status;
                const alertClass = response.job_status === 'running'
                    ? 'info'
                    : response.job_status === 'completed'
                        ? 'success'
                        : response.job_status === 'cancelled'
                            ? 'warning'
                            : 'warning';

                $('#importStatusContent').html(`
                    <div class="alert alert-${alertClass} mb-3">
                        <div class="d-flex justify-content-between align-items-start">
                            <h5 class="mb-0"><i class="bi bi-cloud-upload me-2"></i>Background Import ${statusLabel}</h5>
                            ${response.job_status === 'running'
                                ? '<button type="button" class="btn btn-sm btn-outline-danger" id="cancelBackgroundBtn"><i class="bi bi-x-circle me-1"></i>Cancel</button>'
                                : ''}
                        </div>
                        <p class="mb-2 mt-2">
                            <strong>${jp.processed || 0}</strong> of <strong>${jp.total || 0}</strong> episodes
                            (${pct}% complete)
                        </p>
                        <p class="mb-0 small">
                            Created: ${jp.created || 0} | Skipped: ${jp.skipped || 0} | Errors: ${jp.errors || 0}
                        </p>
                    </div>
                    <div class="progress mb-3" style="height: 25px;">
                        <div class="progress-bar progress-bar-striped ${response.job_status === 'running' ? 'progress-bar-animated' : ''}" role="progressbar" style="width: ${pct}%">
                            ${pct}%
                        </div>
                    </div>
                `);

                if (response.job_status === 'completed' || response.job_status === 'failed' || response.job_status === 'cancelled') {
                    stopBackgroundJobPolling();
                    if (response.job_status === 'completed' || response.job_status === 'cancelled') {
                        loadStats();
                    }
                }
            } else {
                const imported = response.total_imported_episodes || 0;
                const total = response.total_available_episodes || imported;
                const pct = response.import_progress_percentage || (total > 0 ? Math.round((imported / total) * 100) : 0);

                $('#importStatusContent').html(`
                    <div class="alert alert-info mb-3">
                        <h5><i class="bi bi-info-circle me-2"></i>Import Progress</h5>
                        <p class="mb-2">
                            <strong>${imported}</strong> of <strong>${total}</strong> episodes imported
                            (${pct}% complete)
                        </p>
                    </div>
                    <div class="progress mb-3" style="height: 25px;">
                        <div class="progress-bar progress-bar-striped" role="progressbar" style="width: ${pct}%">
                            ${pct}%
                        </div>
                    </div>
                `);
            }
        })
        .fail(function(xhr) {
            $('#importStatusContent').html(`
                <div class="alert alert-warning mb-0">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    Could not load import status: ${xhr.responseJSON?.message || 'Unknown error'}
                </div>
            `);
        });
}

function loadStats() {
    $.get('{{ route("admin.import.braggoscope.stats") }}')
        .done(function(response) {
            if (!response.success) {
                $('#statsSection').hide();
                return;
            }
            const stats = response.stats;
            $('#statsSection').show();
            $('#statsContent').html(`
                <div class="col-md-4">
                    <div class="text-center">
                        <div class="h4 text-primary">${stats.total_episodes}</div>
                        <small class="text-muted">Episodes</small>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="text-center">
                        <div class="h4 text-success">${stats.total_programmes}</div>
                        <small class="text-muted">Programmes</small>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="text-center">
                        <div class="h4 text-warning">${stats.total_connections}</div>
                        <small class="text-muted">Connections</small>
                    </div>
                </div>
            `);
        })
        .fail(function() {
            $('#statsSection').hide();
        });
}

$(document).on('click', '#cancelBackgroundBtn', function() {
    if (!confirm('Cancel the background import? It will stop after the current batch.')) {
        return;
    }
    const $btn = $(this);
    $btn.prop('disabled', true).html('<i class="bi bi-hourglass-split me-1"></i>Cancelling...');

    $.post('{{ route("admin.import.braggoscope.cancel-background") }}')
        .done(function(response) {
            if (response.success) {
                loadImportStatus(true);
            }
        })
        .fail(function(xhr) {
            showError('Failed to cancel import: ' + (xhr.responseJSON?.message || 'Unknown error'));
        })
        .always(function() {
            $btn.prop('disabled', false).html('<i class="bi bi-x-circle me-1"></i>Cancel');
        });
});

function showError(message) {
    const alert = `<div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-x-circle me-2"></i>${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>`;
    $('#alertsContainer').append(alert);
}
</script>
@endpush

