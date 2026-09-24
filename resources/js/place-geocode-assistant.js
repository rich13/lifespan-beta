$(function () {
    const $form = $('#bulk-form');
    const $modal = $('#place-disambiguation-modal');
    if (!$form.length || !$modal.length) {
        return;
    }

    const queueUrl = $form.data('queue-url');
    const stepUrl = $form.data('step-url');
    const choicesUrl = $form.data('choices-url');
    const resolveUrl = $form.data('resolve-url');
    const pauseMs = 1100;
    let modal = null;
    let places = [];
    let index = 0;
    let runId = 0;
    let counts = { geocoded: 0, asked: 0, skipped: 0, unmatched: 0, errors: 0 };
    let current = null;

    const token = function () {
        return $('meta[name="csrf-token"]').attr('content');
    };

    const spanUrl = function (template, spanId) {
        return String(template).replace('__SPAN__', spanId);
    };

    const escapeHtml = function (value) {
        return $('<div>').text(value == null ? '' : String(value)).html();
    };

    const showAlert = function (message, type) {
        $('#geocode-alert')
            .removeClass('d-none alert-success alert-danger alert-info alert-warning')
            .addClass('alert-' + type)
            .text(message);
    };

    const renderProgress = function (currentName) {
        const total = places.length;
        const percent = total === 0 ? 0 : Math.min(100, Math.round((index / total) * 100));
        $('#geocode-progress-wrap').removeClass('d-none');
        $('#geocode-progress-bar').css('width', percent + '%').text(percent + '%');
        const message = 'Geocoded ' + counts.geocoded
            + ', asked ' + counts.asked
            + (counts.unmatched ? ', ' + counts.unmatched + ' unmatched' : '')
            + (counts.skipped ? ', ' + counts.skipped + ' skipped' : '')
            + (counts.errors ? ', ' + counts.errors + ' errors' : '')
            + (currentName ? '. Now: ' + currentName : '');
        showAlert(message, 'info');
    };

    const finish = function (stopped) {
        runId += 1;
        window.placeGeocodeInteractive = false;
        window.placeGeocodeStop = false;
        $('#cancel-geocode-btn').addClass('d-none');
        $('#bulk-submit, #bulk-all-submit, #pause-for-disambiguation').prop('disabled', false);
        $('#geocode-progress-bar').removeClass('progress-bar-animated');
        const message = (stopped ? 'Stopped. ' : 'Finished. ')
            + 'Geocoded ' + counts.geocoded
            + ', asked ' + counts.asked
            + '. Refresh to see the remaining list.';
        showAlert(message, stopped ? 'warning' : 'success');
        if (modal) {
            modal.hide();
        }
    };

    const renderChoices = function (choices) {
        const $list = $('#place-disambiguation-choices');
        if (!choices || !choices.length) {
            $list.html('<p class="text-muted mb-0">No matches for that search. Try a more specific query, or skip this place.</p>');
            return;
        }

        const rows = choices.map(function (choice, choiceIndex) {
            const coords = (choice.latitude != null && choice.longitude != null)
                ? choice.latitude + ', ' + choice.longitude
                : '';
            return '<div class="list-group-item">'
                + '<div class="d-flex justify-content-between gap-3">'
                + '<div>'
                + '<div class="fw-semibold">' + escapeHtml(choice.display_name) + '</div>'
                + '<div class="small text-muted">' + escapeHtml(choice.type || '') + (coords ? ' · ' + escapeHtml(coords) : '') + '</div>'
                + '</div>'
                + '<button type="button" class="btn btn-primary btn-sm place-disambiguation-pick" data-choice-index="' + choiceIndex + '">Use this</button>'
                + '</div>'
                + '</div>';
        });
        $list.html(rows.join(''));
    };

    const openChoices = function (place, reason, choices) {
        current = place;
        counts.asked += 1;
        $('#place-disambiguation-title').text('Choose a location for ' + place.name);
        $('#place-disambiguation-reason').text(reason || 'Nominatim returned more than one possible place.');
        $('#place-disambiguation-query').val(place.name);
        renderChoices(choices);
        modal = window.bootstrap.Modal.getOrCreateInstance($modal[0]);
        modal.show();
    };

    const advance = function () {
        index += 1;
        window.setTimeout(step, pauseMs);
    };

    const step = function () {
        const thisRun = runId;
        if (window.placeGeocodeStop) {
            finish(true);
            return;
        }
        if (index >= places.length) {
            finish(false);
            return;
        }

        const place = places[index];
        renderProgress(place.name);
        $.post(spanUrl(stepUrl, place.id), { _token: token() })
            .done(function (data) {
                if (thisRun !== runId || window.placeGeocodeStop) {
                    return;
                }
                if (data.decision === 'needs_disambiguation') {
                    openChoices(place, data.reason, data.choices || []);
                    return;
                }
                if (data.decision === 'geocoded') {
                    counts.geocoded += 1;
                } else if (data.decision === 'error') {
                    counts.errors += 1;
                } else if (data.decision === 'no_match') {
                    counts.unmatched += 1;
                } else {
                    counts.skipped += 1;
                }
                advance();
            })
            .fail(function () {
                if (thisRun !== runId) {
                    return;
                }
                counts.errors += 1;
                advance();
            });
    };

    const start = function (nextPlaces) {
        runId += 1;
        places = nextPlaces;
        index = 0;
        counts = { geocoded: 0, asked: 0, skipped: 0, unmatched: 0, errors: 0 };
        window.placeGeocodeInteractive = true;
        window.placeGeocodeStop = false;
        $('#bulk-submit, #bulk-all-submit, #pause-for-disambiguation').prop('disabled', true);
        $('#cancel-geocode-btn').removeClass('d-none');
        $('#geocode-progress-bar').addClass('progress-bar-animated');
        if (!places.length) {
            finish(false);
            showAlert('No places need geocoding.', 'success');
            return;
        }
        step();
    };

    $form.on('submit', function (event) {
        if (!$('#pause-for-disambiguation').prop('checked')) {
            return;
        }
        event.preventDefault();
        const submitter = event.originalEvent && event.originalEvent.submitter;
        const all = submitter && submitter.id === 'bulk-all-submit';
        if (all) {
            $.getJSON(queueUrl).done(function (data) {
                start(data.places || []);
            }).fail(function () {
                showAlert('Could not load the places that still need geocoding.', 'danger');
            });
            return;
        }

        const selected = [];
        $('.place-checkbox:checked').each(function () {
            const $row = $(this).closest('tr');
            selected.push({
                id: $(this).val(),
                name: $.trim($row.find('td').eq(1).text()),
            });
        });
        start(selected);
    });

    $('#place-disambiguation-search').on('click', function () {
        if (!current) {
            return;
        }
        const $button = $(this);
        $button.prop('disabled', true);
        $.getJSON(spanUrl(choicesUrl, current.id), {
            query: $('#place-disambiguation-query').val(),
        }).done(function (data) {
            renderChoices(data.choices || []);
        }).fail(function () {
            $('#place-disambiguation-choices').html('<p class="text-danger mb-0">The search failed. Try again.</p>');
        }).always(function () {
            $button.prop('disabled', false);
        });
    });

    $modal.on('click', '.place-disambiguation-pick', function () {
        if (!current) {
            return;
        }
        const choiceIndex = $(this).data('choice-index');
        const $button = $(this);
        $button.prop('disabled', true);
        $.post(spanUrl(resolveUrl, current.id), {
            _token: token(),
            index: choiceIndex,
        }).done(function () {
            counts.geocoded += 1;
            if (modal) {
                modal.hide();
            }
            advance();
        }).fail(function (xhr) {
            $button.prop('disabled', false);
            const message = xhr.responseJSON && xhr.responseJSON.message
                ? xhr.responseJSON.message
                : 'Could not save that match.';
            $('#place-disambiguation-reason').text(message);
        });
    });

    $('#place-disambiguation-skip').on('click', function () {
        counts.skipped += 1;
        if (modal) {
            modal.hide();
        }
        advance();
    });

    $('#place-disambiguation-stop').on('click', function () {
        window.placeGeocodeStop = true;
        if (modal) {
            modal.hide();
        }
        finish(true);
    });

    $(document).on('place-geocode-stop', function () {
        window.placeGeocodeStop = true;
        if (modal) {
            modal.hide();
        }
        finish(true);
    });
});
