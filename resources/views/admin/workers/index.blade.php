@extends('layouts.app')

@section('page_title')
    Queue Workers
@endsection

@section('content')
<div class="container py-4">
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
            <li class="breadcrumb-item active" aria-current="page">Workers</li>
        </ol>
    </nav>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-cpu me-2"></i>Queue Workers</h5>
        </div>
        <div class="card-body">
            <h6 class="mb-2"><i class="bi bi-lightning-charge me-1"></i>Actions</h6>
            <div class="d-flex flex-wrap gap-2 mb-4">
                <button type="button" class="btn btn-outline-primary btn-sm" id="refreshStatsBtn">
                    <i class="bi bi-arrow-clockwise me-1"></i>Refresh
                </button>
                <button type="button" class="btn btn-outline-warning btn-sm" id="restartWorkersBtn">
                    <i class="bi bi-arrow-repeat me-1"></i>Restart Workers
                </button>
                @if (($stats['failed_count'] ?? 0) > 0)
                <button type="button" class="btn btn-outline-info btn-sm" id="retryAllFailedBtn">
                    <i class="bi bi-arrow-counterclockwise me-1"></i>Retry All Failed ({{ $stats['failed_count'] }})
                </button>
                <button type="button" class="btn btn-outline-danger btn-sm" id="flushFailedBtn">
                    <i class="bi bi-trash me-1"></i>Flush Failed Jobs
                </button>
                @endif
                @if (($stats['pending_count'] ?? 0) > 0)
                <button type="button" class="btn btn-outline-secondary btn-sm" id="clearQueueBtn">
                    <i class="bi bi-x-circle me-1"></i>Clear Pending ({{ $stats['pending_count'] }})
                </button>
                @endif
            </div>

            <div class="row mb-4" id="queueStats">
                <div class="col-md-4 mb-3">
                    <div class="card bg-light h-100">
                        <div class="card-body">
                            <h6 class="text-muted mb-1">Queue Connection</h6>
                            <p class="mb-0 fs-5"><strong id="stat-connection">{{ $stats['connection'] ?? 'unknown' }}</strong></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card bg-light h-100">
                        <div class="card-body">
                            <h6 class="text-muted mb-1">Pending Jobs</h6>
                            <p class="mb-0 fs-4"><strong id="stat-pending">{{ $stats['pending_count'] ?? 0 }}</strong></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card bg-light h-100">
                        <div class="card-body">
                            <h6 class="text-muted mb-1">Failed Jobs</h6>
                            <p class="mb-0 fs-4"><strong id="stat-failed" class="{{ ($stats['failed_count'] ?? 0) > 0 ? 'text-danger' : '' }}">{{ $stats['failed_count'] ?? 0 }}</strong></p>
                        </div>
                    </div>
                </div>
            </div>

            <h6 class="mb-2"><i class="bi bi-gear-wide-connected me-1"></i>Docker Control</h6>
            @if ($stats['docker_control_available'] ?? false)
            <div class="d-flex align-items-center gap-2 mb-2">
                <span class="badge {{ ($stats['queue_container_running'] ?? false) ? 'bg-success' : 'bg-secondary' }}" id="queueContainerBadge">
                    {{ ($stats['queue_container_running'] ?? false) ? 'Queue container: Running' : 'Queue container: Stopped' }}
                </span>
                @if ($stats['queue_container_running'] ?? false)
                <button type="button" class="btn btn-outline-danger btn-sm" id="stopQueueBtn">
                    <i class="bi bi-stop-circle me-1"></i>Stop Queue
                </button>
                @else
                <button type="button" class="btn btn-outline-success btn-sm" id="startQueueBtn">
                    <i class="bi bi-play-circle me-1"></i>Start Queue
                </button>
                @endif
            </div>
            <p class="text-muted small mb-0">With workers stopped, jobs run synchronously in web requests.</p>
            @else
            <p class="text-muted small mb-0">
                Queue workers run as a separate service. Use <strong>Restart Workers</strong> above to gracefully restart them. Container stop/start is available when the Docker socket is mounted (e.g. local development).
            </p>
            @endif

            @if (!empty($stats['pending_jobs'] ?? []))
            <h6 class="mb-2 mt-4"><i class="bi bi-hourglass-split me-1"></i>Pending Jobs</h6>
            <div class="table-responsive mb-4">
                <table class="table table-sm table-hover">
                    <thead>
                        <tr>
                            <th>Job</th>
                            <th>Queue</th>
                            <th>Attempts</th>
                            <th>Queued</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($stats['pending_jobs'] as $job)
                        <tr>
                            <td><code class="small">{{ $job['display_name'] }}</code></td>
                            <td>{{ $job['queue'] }}</td>
                            <td>{{ $job['attempts'] }}</td>
                            <td>{{ $job['created_at'] ? \Carbon\Carbon::createFromTimestamp($job['created_at'])->diffForHumans() : '-' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if (count($stats['pending_jobs']) >= 50)
            <p class="small text-muted mb-0">Showing first 50 of {{ $stats['pending_count'] ?? 0 }} pending jobs.</p>
            @endif
            @endif

            @if (!empty($stats['active_imports']))
            <h6 class="mb-2 mt-4"><i class="bi bi-cloud-upload me-1"></i>Active Imports</h6>
            <div class="table-responsive mb-4">
                <table class="table table-sm">
                    <thead>
                        <tr><th>Type</th><th>Progress</th><th>Started</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($stats['active_imports'] as $imp)
                        <tr>
                            <td>{{ $imp['import_type'] }} / {{ $imp['plaque_type'] ?? '-' }}</td>
                            <td>{{ $imp['processed'] }} / {{ $imp['total'] }}</td>
                            <td>{{ $imp['started_at'] ? \Carbon\Carbon::parse($imp['started_at'])->diffForHumans() : '-' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif

            @if (!empty($stats['recent_failed']))
            <h6 class="mb-2 mt-4"><i class="bi bi-exclamation-triangle text-danger me-1"></i>Recent Failed Jobs</h6>
            <div class="table-responsive">
                <table class="table table-sm table-hover">
                    <thead>
                        <tr>
                            <th>Job</th>
                            <th>Queue</th>
                            <th>Failed At</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($stats['recent_failed'] as $job)
                        <tr>
                            <td><code class="small">{{ $job['display_name'] }}</code></td>
                            <td>{{ $job['queue'] }}</td>
                            <td>{{ \Carbon\Carbon::parse($job['failed_at'])->diffForHumans() }}</td>
                            <td>
                                <button type="button" class="btn btn-outline-primary btn-sm retry-job-btn" data-uuid="{{ $job['uuid'] }}">
                                    Retry
                                </button>
                            </td>
                        </tr>
                        <tr class="table-light">
                            <td colspan="4" class="small text-muted font-monospace" style="font-size: 0.75rem;">{{ Str::limit($job['exception_preview'], 300) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @else
            <p class="text-muted small mt-4">No failed jobs.</p>
            @endif
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    function loadStats() {
        $.get('{{ route("admin.workers.stats") }}')
            .done(function(res) {
                if (res.success && res.stats) {
                    $('#stat-connection').text(res.stats.connection);
                    $('#stat-pending').text(res.stats.pending_count);
                    const $failed = $('#stat-failed').text(res.stats.failed_count);
                    $failed.toggleClass('text-danger', res.stats.failed_count > 0);
                }
            });
    }

    $('#refreshStatsBtn').on('click', function() {
        $(this).prop('disabled', true).find('i').addClass('bi-arrow-clockwise');
        loadStats();
        setTimeout(function() { location.reload(); }, 500);
    });

    $('#restartWorkersBtn').on('click', function() {
        if (!confirm('Restart workers? They will finish their current job first, then restart.')) return;
        postAndReload($(this), '{{ route("admin.workers.restart") }}', 'Workers will restart shortly.');
    });

    $('#retryAllFailedBtn').on('click', function() {
        if (!confirm('Retry all failed jobs? They will be re-queued for processing.')) return;
        postAndReload($(this), '{{ route("admin.workers.retry-all-failed") }}', 'All failed jobs queued for retry.');
    });

    $('#flushFailedBtn').on('click', function() {
        if (!confirm('Permanently delete all failed jobs? This cannot be undone.')) return;
        postAndReload($(this), '{{ route("admin.workers.flush-failed") }}', 'Failed jobs flushed.');
    });

    $('#clearQueueBtn').on('click', function() {
        if (!confirm('Clear all pending jobs from the queue? Jobs that have not run yet will be discarded. This cannot be undone.')) return;
        postAndReload($(this), '{{ route("admin.workers.clear-queue") }}', 'Queue cleared.');
    });

    function postAndReload($btn, url, successMsg) {
        $btn.prop('disabled', true);
        $.post(url, { _token: $('meta[name="csrf-token"]').attr('content') })
            .done(function(res) {
                alert(res.message || successMsg);
                location.reload();
            })
            .fail(function(xhr) {
                alert('Failed: ' + (xhr.responseJSON?.message || 'Unknown error'));
            })
            .always(function() {
                $btn.prop('disabled', false);
            });
    }

    $('.retry-job-btn').on('click', function() {
        const uuid = $(this).data('uuid');
        const $btn = $(this).prop('disabled', true);
        $.post('{{ route("admin.workers.retry-failed", ["uuid" => "__UUID__"]) }}'.replace('__UUID__', uuid), { _token: $('meta[name="csrf-token"]').attr('content') })
            .done(function(res) {
                alert(res.message || 'Job queued for retry.');
                location.reload();
            })
            .fail(function(xhr) {
                alert('Failed: ' + (xhr.responseJSON?.message || 'Unknown error'));
                $btn.prop('disabled', false);
            });
    });

    $('#stopQueueBtn').on('click', function() {
        if (!confirm('Stop the queue container? Jobs will run synchronously until you start it again.')) return;
        const $btn = $(this).prop('disabled', true);
        $.post('{{ route("admin.workers.stop-queue") }}', { _token: $('meta[name="csrf-token"]').attr('content') })
            .done(function(res) {
                alert(res.message || 'Queue stopped.');
                location.reload();
            })
            .fail(function(xhr) {
                alert('Failed: ' + (xhr.responseJSON?.message || 'Unknown error'));
                $btn.prop('disabled', false);
            });
    });

    $('#startQueueBtn').on('click', function() {
        const $btn = $(this).prop('disabled', true);
        $.post('{{ route("admin.workers.start-queue") }}', { _token: $('meta[name="csrf-token"]').attr('content') })
            .done(function(res) {
                alert(res.message || 'Queue started.');
                location.reload();
            })
            .fail(function(xhr) {
                alert('Failed: ' + (xhr.responseJSON?.message || 'Unknown error'));
                $btn.prop('disabled', false);
            });
    });
});
</script>
@endsection
