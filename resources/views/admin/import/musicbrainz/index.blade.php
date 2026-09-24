@extends('layouts.app')

@section('page_title', 'Import from MusicBrainz')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-12">
            <nav aria-label="breadcrumb" class="mb-3">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.import.index') }}">Import</a></li>
                    <li class="breadcrumb-item active" aria-current="page">MusicBrainz</li>
                </ol>
            </nav>

            <div class="card mb-4">
                <div class="card-header">
                    <h3 class="card-title mb-0">
                        <i class="bi bi-music-note-list me-2"></i>
                        MusicBrainz bulk import
                    </h3>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <h5><i class="bi bi-info-circle me-2"></i>About this importer</h5>
                        <p class="mb-2">
                            Matches bands and people with the Musician role to MusicBrainz without asking you to pick
                            a result. Tribute, karaoke, and bootleg artists are rejected. If two artists score too
                            closely, that name is skipped rather than guessed.
                        </p>
                        <p class="mb-0">
                            Artists we already know (a MusicBrainz artist id, or albums imported previously) skip the
                            name search. Import still checks MusicBrainz for newer official studio albums, and only
                            fetches tracks for albums we do not already have.
                        </p>
                    </div>

                    <div id="alertsContainer"></div>

                    <div class="row mb-4">
                        <div class="col-md-4">
                            <div class="text-center">
                                <h4 class="text-primary mb-0" id="artistCount">{{ $artistCount }}</h4>
                                <small class="text-muted">Artists in catalogue</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-center">
                                <h4 class="text-success mb-0" id="withMusicBrainz">{{ $withMusicBrainz }}</h4>
                                <small class="text-muted">Already known</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-center">
                                <h4 class="text-secondary mb-0" id="readyCount">{{ $readyCount }}</h4>
                                <small class="text-muted">Ready to match</small>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-4 mb-2">
                            <button type="button" class="btn btn-success w-100" id="importBackgroundBtn">
                                <i class="bi bi-cloud-upload me-2"></i>
                                Import all in background
                            </button>
                        </div>
                        <div class="col-md-4 mb-2">
                            <button type="button" class="btn btn-outline-danger w-100 d-none" id="cancelImportBtn">
                                <i class="bi bi-x-circle me-2"></i>
                                Cancel
                            </button>
                        </div>
                    </div>

                    <div class="row mb-4">
                        <div class="col-12">
                            <label for="artistSelect" class="form-label">Or import one artist</label>
                            <div class="row align-items-end">
                                <div class="col-md-8 mb-2">
                                    <select id="artistSelect" class="form-select">
                                        <option value="">Select an artist…</option>
                                        @foreach($allArtists as $artist)
                                            <option value="{{ $artist->id }}">{{ $artist->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4 mb-2">
                                    <button type="button" class="btn btn-outline-primary w-100" id="importOneBtn" disabled>
                                        <i class="bi bi-box-arrow-in-down me-2"></i>Import this artist
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="importStatusContent"></div>

                    <div class="row mt-4 d-none" id="progressSection">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Import progress</h5>
                                </div>
                                <div class="card-body">
                                    <div class="progress mb-3">
                                        <div class="progress-bar progress-bar-striped progress-bar-animated" id="progressBar" role="progressbar">0%</div>
                                    </div>
                                    <p class="mb-1" id="statusText">Preparing…</p>
                                    <p class="mb-2 fw-semibold" id="currentArtistText"></p>
                                    <p class="mb-0 small text-muted" id="countsText"></p>
                                    <div class="mt-3">
                                        <h6 class="small text-muted mb-2">Recent artists</h6>
                                        <div id="mbActivityLog" class="list-group list-group-flush import-activity-log"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-link-45deg me-2"></i>
                        Import by MusicBrainz release URL
                    </h5>
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-3">Paste a specific release when you already know the MusicBrainz page.</p>
                    <div class="row align-items-end">
                        <div class="col-md-8 mb-2">
                            <label for="musicbrainzUrl" class="form-label">Release URL</label>
                            <input type="url" class="form-control" id="musicbrainzUrl" placeholder="https://musicbrainz.org/release/…">
                        </div>
                        <div class="col-md-4 mb-2">
                            <button type="button" class="btn btn-outline-secondary w-100" id="previewUrlBtn">
                                Preview
                            </button>
                        </div>
                    </div>
                    <div id="urlPreview" class="mt-3 d-none"></div>
                    <button type="button" class="btn btn-primary d-none" id="importUrlBtn">Import this release</button>
                    <div id="urlMessage" class="mt-3"></div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
.import-activity-log {
    max-height: 16rem;
    overflow-y: auto;
}
</style>
@endpush

@push('scripts')
<script>
let backgroundJobPollInterval = null;
let statusRequest = null;

$(document).ready(function() {
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    loadImportStatus();

    $('#artistSelect').on('change', function() {
        $('#importOneBtn').prop('disabled', !$(this).val());
    });

    $('#importBackgroundBtn').click(function() {
        startBackgroundImport();
    });

    $('#importOneBtn').click(function() {
        const artistId = $('#artistSelect').val();
        if (!artistId) {
            return;
        }
        startBackgroundImport(artistId);
    });

    $('#cancelImportBtn').click(function() {
        if (!confirm('Cancel the import? It will stop after the current artist.')) {
            return;
        }
        $.post('{{ route("admin.import.musicbrainz.cancel") }}')
            .done(function() {
                loadImportStatus(true);
            })
            .fail(function(xhr) {
                alert('Failed to cancel: ' + errorMessage(xhr));
            });
    });

    $('#previewUrlBtn').click(previewReleaseUrl);
    $('#importUrlBtn').click(importReleaseUrl);
});

function startBackgroundImport(artistId) {
    const $btn = artistId ? $('#importOneBtn') : $('#importBackgroundBtn');
    const original = $btn.html();
    $btn.prop('disabled', true).html('<i class="bi bi-hourglass-split me-2"></i>Starting…');

    const payload = artistId ? { artist_id: artistId } : {};
    $.post('{{ route("admin.import.musicbrainz.import-background") }}', payload)
        .done(function(response) {
            if (!response.success) {
                alert(response.message || 'Failed to start import');
                return;
            }
            startBackgroundJobPolling();
            loadImportStatus();
        })
        .fail(function(xhr) {
            alert('Failed to start import: ' + errorMessage(xhr));
        })
        .always(function() {
            $btn.prop('disabled', false).html(original);
        });
}

function startBackgroundJobPolling() {
    if (backgroundJobPollInterval) {
        return;
    }
    backgroundJobPollInterval = setInterval(function() {
        loadImportStatus();
    }, 2000);
}

function stopBackgroundJobPolling() {
    if (backgroundJobPollInterval) {
        clearInterval(backgroundJobPollInterval);
        backgroundJobPollInterval = null;
    }
}

function loadImportStatus(force) {
    if (statusRequest) {
        return;
    }

    statusRequest = $.get('{{ route("admin.import.musicbrainz.status") }}')
        .done(function(data) {
            if (!data.success) {
                return;
            }

            if (data.artist_count != null) {
                $('#artistCount').text(data.artist_count);
            }
            if (data.with_musicbrainz != null) {
                $('#withMusicBrainz').text(data.with_musicbrainz);
            }
            if (data.ready_count != null) {
                $('#readyCount').text(data.ready_count);
            }

            const running = data.is_importing;
            $('#cancelImportBtn').toggleClass('d-none', !running);
            $('#importBackgroundBtn').prop('disabled', running);
            $('#importOneBtn').prop('disabled', running || !$('#artistSelect').val());

            if (running) {
                startBackgroundJobPolling();
            } else if (!force) {
                stopBackgroundJobPolling();
            }

            renderProgress(data);
        })
        .always(function() {
            statusRequest = null;
        });
}

function renderProgress(data) {
    const progress = data.job_progress || {};
    const running = data.is_importing;
    const hasJob = data.background_job;

    if (!hasJob) {
        $('#progressSection').addClass('d-none');
        $('#importStatusContent').html('');
        return;
    }

    $('#progressSection').removeClass('d-none');
    const percent = progress.progress_percentage || 0;
    $('#progressBar').css('width', percent + '%').text(percent + '%');
    if (running) {
        $('#progressBar').addClass('progress-bar-animated');
    } else {
        $('#progressBar').removeClass('progress-bar-animated');
    }

    $('#statusText').text(
        (data.job_status || 'unknown') + (progress.error ? ' — ' + progress.error : '')
    );
    $('#currentArtistText').text(progress.current_item ? ((running ? 'Working on ' : 'Last: ') + progress.current_item) : '');
    $('#countsText').text(
        (progress.created || 0) + ' imported · ' +
        (progress.skipped || 0) + ' skipped · ' +
        (progress.errors || 0) + ' errors · ' +
        (progress.processed || 0) + '/' + (progress.total || 0)
    );

    const recent = progress.recent_artists || [];
    let html = '';
    recent.slice().reverse().forEach(function(item) {
        const badge = statusBadge(item.status);
        html += '<div class="list-group-item py-2">' +
            '<strong>' + escapeText(item.name) + '</strong> ' + badge +
            (item.message ? '<div class="small text-muted">' + escapeText(item.message) + '</div>' : '') +
            '</div>';
    });
    $('#mbActivityLog').html(html || '<div class="text-muted small">Waiting for the first artist…</div>');
}

function statusBadge(status) {
    const map = {
        imported: 'success',
        up_to_date: 'info',
        working: 'primary',
        skipped: 'secondary',
        ambiguous: 'warning',
        no_match: 'secondary',
        error: 'danger'
    };
    const tone = map[status] || 'secondary';
    return '<span class="badge bg-' + tone + '">' + escapeText(status || 'unknown') + '</span>';
}

function escapeText(text) {
    return $('<div>').text(text == null ? '' : String(text)).html();
}

function errorMessage(xhr) {
    return (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Unknown error';
}

function previewReleaseUrl() {
    const url = $.trim($('#musicbrainzUrl').val());
    const $msg = $('#urlMessage');
    $msg.removeClass().text('');
    if (!url) {
        $msg.addClass('alert alert-danger').text('Please enter a MusicBrainz release URL.');
        return;
    }

    $.post('{{ route("admin.import.musicbrainz.preview-by-url") }}', { url: url })
        .done(function(response) {
            if (!response.success) {
                $msg.addClass('alert alert-danger').text(response.error || 'Preview failed');
                return;
            }
            const preview = response.preview;
            $('#urlPreview').removeClass('d-none').html(
                '<strong>' + escapeText(preview.title) + '</strong> by ' + escapeText(preview.artist_name) +
                (preview.date ? ' (' + escapeText(preview.date) + ')' : '') +
                '<div class="small text-muted">' + (preview.tracks ? preview.tracks.length : 0) + ' tracks</div>'
            );
            $('#importUrlBtn').removeClass('d-none');
        })
        .fail(function(xhr) {
            $msg.addClass('alert alert-danger').text(errorMessage(xhr));
        });
}

function importReleaseUrl() {
    const url = $.trim($('#musicbrainzUrl').val());
    const $msg = $('#urlMessage');
    $.post('{{ route("admin.import.musicbrainz.import-by-url") }}', { url: url })
        .done(function(response) {
            if (!response.success) {
                $msg.removeClass().addClass('alert alert-danger').text(response.error || 'Import failed');
                return;
            }
            $msg.removeClass().addClass('alert alert-success').text(response.message);
            $('#musicbrainzUrl').val('');
            $('#urlPreview').addClass('d-none').html('');
            $('#importUrlBtn').addClass('d-none');
        })
        .fail(function(xhr) {
            $msg.removeClass().addClass('alert alert-danger').text(errorMessage(xhr));
        });
}
</script>
@endpush
