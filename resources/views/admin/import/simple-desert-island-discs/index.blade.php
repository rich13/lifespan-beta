@extends('layouts.app')

@section('title', 'Desert Island Discs Import')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-12">
            <nav aria-label="breadcrumb" class="mb-3">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.import.index') }}">Import</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Desert Island Discs</li>
                </ol>
            </nav>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="bi bi-music-note-beamed me-2"></i>
                        Desert Island Discs bulk import
                    </h3>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <h5><i class="bi bi-info-circle me-2"></i>About this importer</h5>
                        <p class="mb-2">
                            Fetches Praful’s episode CSV and creates one public placeholder set per BBC programme.
                            Created/Skipped counts <strong>episodes</strong> by BBC programme id — a first run of
                            a few thousand rows should create almost everything. Re-running then skips those ids.
                            Existing episode sets from earlier importers are reused and tagged with the programme id,
                            not duplicated.
                        </p>
                        <p class="mb-0">
                            People, artists, tracks, and books are reused by name (case-insensitive), so they are
                            not counted as skipped. After a successful import, Wikipedia (people and books) and
                            MusicBrainz (artists and tracks) run as a follow-up job.
                        </p>
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
                                    <div id="enrichStatusContent" class="mt-3"></div>
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
                                            <button type="button" class="btn btn-outline-danger w-100 d-none" id="cancelImportBtn">
                                                <i class="bi bi-x-circle me-2"></i>
                                                Cancel
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
                                        Search the CSV by castaway, programme id, or broadcast date and import one episode
                                        without running the full importer.
                                    </p>
                                    <div class="row align-items-end">
                                        <div class="col-md-6 mb-2">
                                            <label for="episodeSearchQuery" class="form-label">Search term</label>
                                            <input type="text"
                                                   class="form-control"
                                                   id="episodeSearchQuery"
                                                   placeholder="e.g. David Attenborough, m002lpnf"
                                                   maxlength="200">
                                        </div>
                                        <div class="col-md-3 mb-2">
                                            <button type="button" class="btn btn-outline-primary w-100" id="searchEpisodeBtn">
                                                <i class="bi bi-search me-2"></i>Search
                                            </button>
                                        </div>
                                    </div>
                                    <div id="searchEpisodeResults" class="mt-3 d-none">
                                        <h6 class="mb-2">Results</h6>
                                        <div id="searchEpisodeResultsList"></div>
                                    </div>
                                    <div id="searchEpisodeEmpty" class="mt-3 alert alert-info d-none">
                                        No episodes found for that search.
                                    </div>
                                    <div id="searchEpisodeError" class="mt-3 alert alert-danger d-none"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-4 d-none" id="progressSection">
                        <div class="col-md-8">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">
                                        <i class="bi bi-arrow-repeat me-2" id="progressSpinner"></i>
                                        Import Progress
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="progress mb-3">
                                        <div class="progress-bar progress-bar-striped progress-bar-animated" id="progressBar" role="progressbar">0%</div>
                                    </div>
                                    <p class="mb-1" id="statusText">Preparing…</p>
                                    <p class="mb-2 fw-semibold" id="currentPersonText"></p>
                                    <p class="mb-0 small text-muted" id="countsText"></p>
                                    <div class="mt-3">
                                        <h6 class="small text-muted mb-2">Recent episodes</h6>
                                        <div id="didActivityLog" class="list-group list-group-flush did-activity-log"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-4 d-none" id="statsSection">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Imported so far</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row" id="statsContent"></div>
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

@push('styles')
<style>
.did-activity-log {
    max-height: 16rem;
    overflow-y: auto;
}
</style>
@endpush

@push('scripts')
<script>
let isProcessing = false;
let currentOffset = 0;
let cumulativeProcessed = 0;
let cumulativeCreated = 0;
let cumulativeSkipped = 0;
let cumulativeErrors = 0;
let backgroundJobPollInterval = null;
let foregroundPeople = [];

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
        if (!confirm('This will import all Desert Island Discs episodes from the CSV. Continue?')) {
            return;
        }
        startImport(0);
    });

    $('#importBackgroundBtn').click(function() {
        if (isProcessing) {
            return;
        }
        if (!confirm('Start import in background? Wikipedia and MusicBrainz enrichment will follow automatically.')) {
            return;
        }

        $(this).prop('disabled', true).html('<i class="bi bi-hourglass-split me-2"></i>Starting...');

        $.post('{{ route("admin.import.simple-desert-island-discs.import-background") }}')
            .done(function(response) {
                if (!response.success) {
                    alert(response.message || 'Failed to start import');
                    return;
                }
                startBackgroundJobPolling();
                loadImportStatus();
            })
            .fail(function(xhr) {
                alert('Failed to start import: ' + (xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Unknown error'));
            })
            .always(function() {
                $('#importBackgroundBtn').prop('disabled', false).html('<i class="bi bi-cloud-upload me-2"></i>Import in Background');
            });
    });

    $('#cancelImportBtn').click(function() {
        if (!confirm('Cancel the import? It will stop after the current batch.')) {
            return;
        }
        isProcessing = false;
        $.post('{{ route("admin.import.simple-desert-island-discs.cancel-background") }}')
            .done(function() {
                loadImportStatus(true);
            })
            .fail(function(xhr) {
                showError('Failed to cancel import: ' + (xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Unknown error'));
            });
    });

    $('#searchEpisodeBtn').click(function() {
        searchEpisodes();
    });

    $('#episodeSearchQuery').on('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            searchEpisodes();
        }
    });

    $(document).on('click', '#cancelBackgroundBtn', function() {
        if (!confirm('Cancel the background import? It will stop after the current batch.')) {
            return;
        }
        const $btn = $(this);
        $btn.prop('disabled', true).html('<i class="bi bi-hourglass-split me-1"></i>Cancelling...');

        $.post('{{ route("admin.import.simple-desert-island-discs.cancel-background") }}')
            .done(function(response) {
                if (response.success) {
                    loadImportStatus(true);
                }
            })
            .fail(function(xhr) {
                showError('Failed to cancel import: ' + (xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Unknown error'));
            });
    });

    $(document).on('click', '.import-single-episode-btn', function() {
        const episodeIndex = $(this).data('episode-index');
        const $btn = $(this);
        const $item = $btn.closest('.list-group-item');
        $btn.prop('disabled', true).html('<i class="bi bi-hourglass-split me-1"></i>Importing...');

        $.post('{{ route("admin.import.simple-desert-island-discs.process-single") }}', {
            episode_index: episodeIndex
        })
            .done(function(response) {
                if (response.success) {
                    const details = response.details || {};
                    if (details.skipped) {
                        $btn.removeClass('btn-success').addClass('btn-secondary').html('<i class="bi bi-check2 me-1"></i>Already in database');
                    } else {
                        $btn.removeClass('btn-success').addClass('btn-secondary').html('<i class="bi bi-check2 me-1"></i>Imported');
                    }

                    if (details.set_id) {
                        const url = '{{ url("/spans") }}/' + details.set_id;
                        $item.append('<br><small><a href="' + url + '" target="_blank">View episode set</a></small>');
                    }
                    loadStats();
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

function searchEpisodes() {
    const query = $.trim($('#episodeSearchQuery').val());
    const $btn = $('#searchEpisodeBtn');
    $('#searchEpisodeResults').addClass('d-none');
    $('#searchEpisodeEmpty').addClass('d-none');
    $('#searchEpisodeError').addClass('d-none').text('');

    if (!query) {
        $('#searchEpisodeError').text('Enter a search term.').removeClass('d-none');
        return;
    }

    $btn.prop('disabled', true).html('<i class="bi bi-hourglass-split me-2"></i>Searching...');

    $.post('{{ route("admin.import.simple-desert-island-discs.search-episode") }}', { query: query })
        .done(function(response) {
            if (!response.success) {
                $('#searchEpisodeError').text(response.message || 'Search failed').removeClass('d-none');
                return;
            }

            const matches = response.matches || [];
            if (matches.length === 0) {
                $('#searchEpisodeEmpty').removeClass('d-none');
                return;
            }

            let html = '<div class="list-group">';
            matches.forEach(function(m) {
                const title = $('<div>').text(m.title || 'Untitled').html();
                const published = m.published ? m.published : 'Unknown date';
                const programmeId = m.id ? m.id : '';
                const url = m.url || null;
                html += `
                    <div class="list-group-item list-group-item-action" data-episode-index="${m.index}">
                        <div class="d-flex w-100 justify-content-between align-items-start flex-wrap">
                            <div class="mb-1">
                                <strong>${title}</strong>
                                <br><small class="text-muted">${published}${programmeId ? ' · ' + programmeId : ''}</small>
                                ${url ? '<br><small class="text-muted"><a href="' + url + '" target="_blank">BBC programme</a></small>' : ''}
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
            $('#searchEpisodeResults').removeClass('d-none');
        })
        .fail(function(xhr) {
            $('#searchEpisodeError').text('Search failed: ' + (xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Unknown error')).removeClass('d-none');
        })
        .always(function() {
            $btn.prop('disabled', false).html('<i class="bi bi-search me-2"></i>Search');
        });
}

function escapeText(text) {
    return $('<div>').text(text == null ? '' : String(text)).html();
}

function currentItemLine(item, running) {
    if (!item) {
        return '';
    }
    const label = running ? 'Now' : 'Last';
    return `<p class="mb-0 mt-2"><strong>${label}:</strong> ${escapeText(item)}</p>`;
}

function actionBadge(action) {
    if (action === 'created') {
        return '<span class="badge bg-success">Created</span>';
    }
    if (action === 'skipped') {
        return '<span class="badge bg-secondary">Skipped</span>';
    }
    if (action === 'error') {
        return '<span class="badge bg-danger">Error</span>';
    }
    return '<span class="badge bg-light text-dark">' + escapeText(action || '') + '</span>';
}

function recentPeopleMarkup(people) {
    if (!people || !people.length) {
        return '<p class="text-muted small mb-0">Waiting for the first episode…</p>';
    }

    let html = '';
    people.slice().reverse().forEach(function(person) {
        html += `
            <div class="list-group-item px-0 py-2 d-flex justify-content-between align-items-center">
                <span>${escapeText(person.name)}</span>
                ${actionBadge(person.action)}
            </div>
        `;
    });
    return html;
}

function recentPeopleSection(people, running) {
    if (!people || !people.length) {
        if (!running) {
            return '';
        }

        return `
            <h6 class="small text-muted mb-2">Recent episodes</h6>
            <div class="list-group list-group-flush did-activity-log">
                <p class="text-muted small mb-0">Waiting for the first episode…</p>
            </div>
        `;
    }

    return `
        <h6 class="small text-muted mb-2">Recent episodes</h6>
        <div class="list-group list-group-flush did-activity-log">
            ${recentPeopleMarkup(people)}
        </div>
    `;
}

function renderActivityLog($container, people) {
    $container.html(recentPeopleMarkup(people));
}

function startBackgroundJobPolling() {
    if (backgroundJobPollInterval) {
        return;
    }
    backgroundJobPollInterval = setInterval(function() {
        loadImportStatus(true);
    }, 1000);
}

function stopBackgroundJobPolling() {
    if (backgroundJobPollInterval) {
        clearInterval(backgroundJobPollInterval);
        backgroundJobPollInterval = null;
    }
}

function renderEnrichStatus(response) {
    if (!response.enrich_job || !response.enrich_progress) {
        $('#enrichStatusContent').empty();
        return;
    }

    const ep = response.enrich_progress;
    const pct = ep.progress_percentage || 0;
    const statusLabel = response.enrich_status === 'running' ? '(in progress)' : response.enrich_status;
    const alertClass = response.enrich_status === 'running'
        ? 'info'
        : response.enrich_status === 'completed'
            ? 'success'
            : response.enrich_status === 'cancelled'
                ? 'warning'
                : 'warning';

    $('#enrichStatusContent').html(`
        <div class="alert alert-${alertClass} mb-0">
            <h5 class="mb-2"><i class="bi bi-search me-2"></i>Wikipedia / MusicBrainz enrichment ${statusLabel}</h5>
            <p class="mb-2">
                <strong>${ep.processed || 0}</strong> of <strong>${ep.total || 0}</strong> spans
                (${pct}% complete)
            </p>
            <p class="mb-0 small">
                Enriched: ${ep.created || 0} | Skipped: ${ep.skipped || 0} | Errors: ${ep.errors || 0}
            </p>
            ${currentItemLine(ep.current_item, response.enrich_status === 'running')}
        </div>
    `);

    if (response.enrich_status === 'running' && !backgroundJobPollInterval) {
        startBackgroundJobPolling();
    }
}

function loadImportStatus(isPolling) {
    $.get('{{ route("admin.import.simple-desert-island-discs.status") }}')
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
                        ${currentItemLine(jp.current_item, response.job_status === 'running')}
                    </div>
                    <div class="progress mb-3">
                        <div class="progress-bar progress-bar-striped ${response.job_status === 'running' ? 'progress-bar-animated' : ''}" role="progressbar">${pct}%</div>
                    </div>
                    ${recentPeopleSection(jp.recent_people || [], response.job_status === 'running')}
                `);
                $('#importStatusContent .progress-bar').css('width', pct + '%');

                if (response.job_status === 'running' && !backgroundJobPollInterval) {
                    startBackgroundJobPolling();
                }

                if (response.job_status === 'completed' || response.job_status === 'failed' || response.job_status === 'cancelled') {
                    if (response.enrich_status !== 'running') {
                        stopBackgroundJobPolling();
                    }
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
                    <div class="progress mb-0">
                        <div class="progress-bar progress-bar-striped" role="progressbar">${pct}%</div>
                    </div>
                `);
                $('#importStatusContent .progress-bar').css('width', pct + '%');
            }

            renderEnrichStatus(response);
        })
        .fail(function(xhr) {
            $('#importStatusContent').html(`
                <div class="alert alert-warning mb-0">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    Could not load import status: ${xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Unknown error'}
                </div>
            `);
        });
}

function loadStats() {
    $.get('{{ route("admin.import.simple-desert-island-discs.stats") }}')
        .done(function(response) {
            if (!response.success) {
                $('#statsSection').addClass('d-none');
                return;
            }
            const stats = response.stats;
            $('#statsSection').removeClass('d-none');
            $('#statsContent').html(`
                <div class="col-md-4">
                    <div class="text-center">
                        <div class="h4 text-primary">${stats.total_episodes}</div>
                        <small class="text-muted">Episodes</small>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="text-center">
                        <div class="h4 text-success">${stats.total_people}</div>
                        <small class="text-muted">People</small>
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
            $('#statsSection').addClass('d-none');
        });
}

function startImport(startOffset) {
    isProcessing = true;
    currentOffset = startOffset;
    cumulativeProcessed = 0;
    cumulativeCreated = 0;
    cumulativeSkipped = 0;
    cumulativeErrors = 0;

    foregroundPeople = [];
    $('#progressSection').removeClass('d-none');
    $('#cancelImportBtn').removeClass('d-none');
    $('#currentPersonText').text('');
    renderActivityLog($('#didActivityLog'), []);
    processNextBatch();
}

function processNextBatch() {
    if (!isProcessing) {
        return;
    }

    const batchSize = 20;
    $('#statusText').text('Processing episodes...');

    $.ajax({
        url: '{{ route("admin.import.simple-desert-island-discs.process-batch") }}',
        method: 'POST',
        data: {
            batch_size: batchSize,
            offset: currentOffset
        },
        timeout: 120000
    })
    .done(function(response) {
        if (!response.success) {
            showError('Import failed: ' + (response.message || 'Unknown error'));
            isProcessing = false;
            return;
        }

        const data = response.data;
        cumulativeProcessed += data.processed || 0;
        cumulativeCreated += data.created || 0;
        cumulativeSkipped += data.skipped || 0;
        cumulativeErrors += (data.errors || []).length;

        const pct = data.progress_percentage || 0;
        $('#progressBar').css('width', pct + '%').text(pct + '%');
        $('#countsText').text('Created: ' + cumulativeCreated + ' | Skipped: ' + cumulativeSkipped + ' | Errors: ' + cumulativeErrors);
        if (data.current_item) {
            $('#currentPersonText').text(data.current_item);
            $('#statusText').text(data.current_item);
        }
        if (Array.isArray(data.recent_people) && data.recent_people.length) {
            foregroundPeople = foregroundPeople.concat(data.recent_people).slice(-40);
            renderActivityLog($('#didActivityLog'), foregroundPeople);
        }

        if (data.is_last_batch) {
            isProcessing = false;
            $('#statusText').text('Import complete. Enrichment will continue in the background.');
            $('#cancelImportBtn').addClass('d-none');
            loadImportStatus();
            loadStats();
            startBackgroundJobPolling();
            return;
        }

        currentOffset = data.next_offset;
        processNextBatch();
    })
    .fail(function(xhr) {
        showError('Import failed: ' + (xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Unknown error'));
        isProcessing = false;
    });
}

function showError(message) {
    $('#alertsContainer').html(`
        <div class="alert alert-danger alert-dismissible fade show">
            ${$('<div>').text(message).html()}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `);
}
</script>
@endpush
