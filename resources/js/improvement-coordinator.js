$(function () {
    const $root = $('#improvement-coordinator');
    if (!$root.length) {
        return;
    }

    const statusUrl = $root.data('status-url');
    const startUrl = $root.data('start-url');
    const stopUrl = $root.data('stop-url');
    let poll = null;

    const escapeHtml = function (value) {
        return $('<div>').text(value == null ? '' : String(value)).html();
    };

    const token = function () {
        return $('meta[name="csrf-token"]').attr('content');
    };

    const renderActivity = function (log) {
        const $body = $('#improvement-activity');
        if (!log || !log.length) {
            $body.html('<tr><td colspan="4" class="text-muted">No activity yet.</td></tr>');
            return;
        }

        const rows = log.map(function (entry) {
            return '<tr>'
                + '<td>' + escapeHtml(entry.name) + '</td>'
                + '<td>' + escapeHtml(entry.improver) + '</td>'
                + '<td>' + escapeHtml(entry.outcome) + '</td>'
                + '<td>' + escapeHtml(entry.detail) + '</td>'
                + '</tr>';
        });
        $body.html(rows.join(''));
    };

    const renderStatus = function (payload) {
        const progress = payload.progress || {};
        const running = !!payload.running;
        const status = progress.status || 'idle';
        const processed = progress.processed || 0;
        const total = progress.total || 0;
        const improved = progress.created || 0;
        const skipped = progress.skipped || 0;
        const errors = progress.errors || 0;
        const current = progress.current_item || '';
        const improver = progress.current_improver || '';
        const percentage = progress.progress_percentage || 0;

        $('#improvement-start').prop('disabled', running);
        $('#improvement-stop').prop('disabled', !running);

        let heading = 'Idle';
        if (running) {
            heading = 'Working';
        } else if (status === 'completed') {
            heading = 'Finished';
        } else if (status === 'cancelled') {
            heading = 'Stopped';
        } else if (status === 'failed') {
            heading = 'Failed';
        }

        let currentLine = '';
        if (current) {
            currentLine = '<p class="mb-2">Current: <strong>' + escapeHtml(current) + '</strong>'
                + (improver ? ' <span class="text-muted">(' + escapeHtml(improver) + ')</span>' : '')
                + '</p>';
        }

        let errorLine = '';
        if (progress.error) {
            errorLine = '<p class="text-danger mb-2">' + escapeHtml(progress.error) + '</p>';
        }

        $('#improvement-status').html(
            '<h6 class="mb-2">' + heading + '</h6>'
            + currentLine
            + errorLine
            + '<div class="progress mb-2" role="progressbar" aria-valuenow="' + percentage + '" aria-valuemin="0" aria-valuemax="100">'
            + '<div class="progress-bar" style="width: ' + percentage + '%">' + percentage + '%</div>'
            + '</div>'
            + '<p class="mb-0"><strong>' + processed + '</strong> of <strong>' + total + '</strong> spans. '
            + improved + ' improved, ' + skipped + ' skipped, ' + errors + ' errors.</p>'
        );

        renderActivity(progress.activity_log);

        if (payload.ai_budget) {
            $('#improvement-ai-budget').text(payload.ai_budget.used + ' / ' + payload.ai_budget.limit);
        }

        if (running && !poll) {
            poll = setInterval(loadStatus, 2000);
        }
        if (!running && poll) {
            clearInterval(poll);
            poll = null;
        }
    };

    const loadStatus = function () {
        $.get(statusUrl).done(renderStatus);
    };

    $('#improvement-start').on('click', function () {
        if (!window.confirm('Start span improvement? It keeps going until the queues are clear, or until you press Hard stop.')) {
            return;
        }

        $('#improvement-start').prop('disabled', true);
        $.post(startUrl, { _token: token() })
            .done(function () {
                loadStatus();
            })
            .fail(function (xhr) {
                window.alert(xhr.responseJSON?.message || 'Could not start span improvement.');
                loadStatus();
            });
    });

    $('#improvement-stop').on('click', function () {
        if (!window.confirm('Hard stop span improvement? The worker is recycled and nothing further will start.')) {
            return;
        }

        $('#improvement-stop').prop('disabled', true);
        $.post(stopUrl, { _token: token() })
            .done(function () {
                loadStatus();
            })
            .fail(function () {
                window.alert('Could not stop span improvement.');
                loadStatus();
            });
    });

    loadStatus();
});
