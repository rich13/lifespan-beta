@push('scripts')
<script>
$(function() {
    var virtualPlaqueApiBase = @json(url('/explore/plaques'));
    var csrfToken = @json(csrf_token());
    var predicateMappings = @json(config('plaques.predicate_mappings'));

    var $card = $('#virtualPlaqueAdminCard');
    if (!$card.length) {
        return;
    }

    var $loading = $('#virtualPlaqueLoading');
    var $content = $('#virtualPlaqueContent');
    var $message = $('#virtualPlaqueMessage');
    var $badge = $('#virtualPlaqueStatusBadge');
    var $pairSelectors = $('#virtualPlaquePairSelectors');
    var $personSelect = $('#virtualPlaquePersonSelect');
    var $placeSelect = $('#virtualPlaquePlaceSelect');
    var $createForm = $('#virtualPlaqueCreateForm');
    var $typeSelect = $('#virtualPlaqueTypeSelect');
    var $suggestedHint = $('#virtualPlaqueSuggestedHint');
    var $startYear = $('#virtualPlaqueStartYear');
    var $endYear = $('#virtualPlaqueEndYear');
    var $previewWrap = $('#virtualPlaquePreviewWrap');
    var $openLink = $('#virtualPlaqueOpenLink');
    var $plaquePositioned = $('#mainMapPlaquePositioned');
    var virtualPlaqueLatLng = null;
    var mainMapPlaqueListenersBound = false;
    var $createBtn = $('#virtualPlaqueCreateBtn');
    var $subjectLink = $('#virtualPlaqueSubjectLink');
    var $objectLink = $('#virtualPlaqueObjectLink');
    var $connectionSentence = $('#virtualPlaqueConnectionSentence');
    var $placeholderNote = $('#virtualPlaquePlaceholderNote');

    var currentPlaqueId = null;
    var currentStatus = null;

    function findSpanInList(list, id) {
        if (!list || !id) {
            return null;
        }
        return list.find(function(item) {
            return item.id === id;
        }) || null;
    }

    function selectedConnectionTypeLabel() {
        var typeId = $typeSelect.val();
        var types = (currentStatus && currentStatus.connection_types) || [];
        var match = types.find(function(t) {
            return t.type === typeId;
        });
        return match ? match.label : 'connected to';
    }

    function hasDateInputs() {
        var startYear = parseInt($startYear.val(), 10);
        var endYear = parseInt($endYear.val(), 10);
        return !isNaN(startYear) || !isNaN(endYear);
    }

    function updateConnectionSummary() {
        if (!currentStatus || !currentStatus.available) {
            return;
        }

        var person = findSpanInList(currentStatus.people, $personSelect.val());
        var place = findSpanInList(currentStatus.places, $placeSelect.val());

        if (!person || !place) {
            return;
        }

        $subjectLink.text(person.name).attr('href', person.url);
        $objectLink.text(place.name).attr('href', place.url);
        $connectionSentence.text(person.name + ' ' + selectedConnectionTypeLabel() + ' ' + place.name);
        $placeholderNote.toggleClass('d-none', hasDateInputs());
    }

    function virtualPlaqueStatusUrl(spanId) {
        return virtualPlaqueApiBase + '/' + encodeURIComponent(spanId) + '/virtual-plaque';
    }

    function findPair(status, personId, placeId) {
        if (!status || !status.pairs) {
            return null;
        }
        return status.pairs.find(function(pair) {
            return pair.person.id === personId && pair.place.id === placeId;
        }) || null;
    }

    function virtualUrlForPair(status, personId, placeId) {
        var pair = findPair(status, personId, placeId);
        if (!pair || !pair.existing_connections || !pair.existing_connections.length) {
            return null;
        }
        var withUrl = pair.existing_connections.find(function(c) {
            return c.virtual_plaque_url;
        });
        return withUrl ? withUrl.virtual_plaque_url : null;
    }

    function pairNeedsCreate(status, personId, placeId) {
        var pair = findPair(status, personId, placeId);
        return !pair || !pair.has_connection;
    }

    function populateSelect($select, items) {
        $select.empty();
        items.forEach(function(item) {
            $select.append($('<option>', { value: item.id, text: item.name }));
        });
    }

    function populateTypeSelect(types, selectedType) {
        $typeSelect.empty();
        types.forEach(function(type) {
            $typeSelect.append($('<option>', { value: type.type, text: type.label }));
        });
        if (selectedType) {
            $typeSelect.val(selectedType);
        }
    }

    function applySuggestedDates(suggested) {
        $startYear.val(suggested && suggested.start_year ? suggested.start_year : '');
        $endYear.val(suggested && suggested.end_year ? suggested.end_year : '');
    }

    function exploreMap() {
        return window.explorePlaquesMap || null;
    }

    function bindMainMapPlaqueListeners() {
        var map = exploreMap();
        if (!map || mainMapPlaqueListenersBound) {
            return;
        }
        map.on('move', updatePlaqueOnMapPosition);
        map.on('zoom', updatePlaqueOnMapPosition);
        map.on('resize', updatePlaqueOnMapPosition);
        mainMapPlaqueListenersBound = true;
    }

    function clearMainMapVirtualPlaque() {
        virtualPlaqueLatLng = null;
        $plaquePositioned.addClass('d-none').empty();
        if (window.explorePlaquesRestoreSelectedMarker) {
            window.explorePlaquesRestoreSelectedMarker();
        }
    }

    function formatYearRange(startYear, endYear) {
        if (startYear == null && endYear == null) {
            return null;
        }
        if (startYear != null && endYear != null && startYear !== endYear) {
            return startYear + ' – ' + endYear;
        }
        if (startYear != null) {
            return String(startYear);
        }
        return String(endYear);
    }

    function predicateLabelForType(typeId) {
        var types = (currentStatus && currentStatus.connection_types) || [];
        var match = types.find(function(t) {
            return t.type === typeId;
        });
        if (!match) {
            return '';
        }
        var key = match.label.replace(/ /g, '-');
        return predicateMappings[key] || match.label;
    }

    function currentPlaquePreview() {
        var pair = findPair(currentStatus, $personSelect.val(), $placeSelect.val());
        if (!pair || !pair.preview) {
            return null;
        }

        var preview = $.extend(true, {}, pair.preview);

        if (!pair.has_connection) {
            var startYear = parseInt($startYear.val(), 10);
            var endYear = parseInt($endYear.val(), 10);
            preview.predicate = predicateLabelForType($typeSelect.val());
            preview.connection_dates = formatYearRange(
                isNaN(startYear) ? null : startYear,
                isNaN(endYear) ? null : endYear
            );
        }

        return preview;
    }

    function escapeHtml(text) {
        return $('<div>').text(text || '').html();
    }

    function renderVirtualPlaqueSvg(preview) {
        if (!preview || !preview.name_lines || !preview.name_lines.length) {
            $plaquePositioned.empty();
            return;
        }

        var clipId = 'virtual-plaque-clip-' + Date.now();
        var y = 150;
        var html = '<svg class="virtual-plaque-svg" viewBox="0 0 400 400" xmlns="http://www.w3.org/2000/svg" role="img">';
        html += '<defs><clipPath id="' + clipId + '"><circle cx="200" cy="200" r="170"/></clipPath></defs>';
        html += '<circle cx="200" cy="200" r="190" fill="#e8e4d9" stroke="#d4cfc4" stroke-width="2"/>';
        html += '<circle cx="200" cy="200" r="170" fill="#1a3a5c"/>';
        html += '<g clip-path="url(#' + clipId + ')" fill="#f5f0e6" font-family="Georgia, \'Times New Roman\', serif" text-anchor="middle">';

        preview.name_lines.forEach(function(line, i) {
            var fontSize = 26;
            if (preview.name_lines.length === 2 && i === 1) {
                var charCount = line.length;
                fontSize = charCount > 0 ? Math.max(26, Math.min(48, Math.floor(260 / (charCount * 0.65)))) : 26;
            }
            html += '<text x="200" y="' + y + '" font-size="' + fontSize + '" font-weight="700">' + escapeHtml(line) + '</text>';
            y += (preview.name_lines.length === 2 && i === 0) ? 48 : 28;
        });

        if (preview.subject_dates) {
            html += '<text x="200" y="' + y + '" font-size="16" font-weight="400">' + escapeHtml(preview.subject_dates) + '</text>';
            y += 28;
        }

        if (preview.predicate) {
            html += '<text x="200" y="' + y + '" font-size="18" font-weight="600">' + escapeHtml(preview.predicate) + '</text>';
            y += 28;
        }

        if (preview.connection_dates) {
            html += '<text x="200" y="' + y + '" font-size="14" font-weight="400">' + escapeHtml(preview.connection_dates) + '</text>';
        }

        html += '</g></svg>';
        $plaquePositioned.html(html);
    }

    function updatePlaqueOnMapPosition() {
        var map = exploreMap();
        if (!map || !virtualPlaqueLatLng || !$plaquePositioned.children().length) {
            return;
        }
        var point = map.latLngToContainerPoint(virtualPlaqueLatLng);
        $plaquePositioned.css({
            left: point.x + 'px',
            top: point.y + 'px',
        });
    }

    function selectedPlaceCoords() {
        if (!currentStatus || !currentStatus.available) {
            return null;
        }
        var place = findSpanInList(currentStatus.places, $placeSelect.val());
        if (!place || place.latitude == null || place.longitude == null) {
            return null;
        }
        return {
            lat: place.latitude,
            lng: place.longitude,
        };
    }

    function updateVirtualPlaqueOnMainMap() {
        var coords = selectedPlaceCoords();
        var preview = currentPlaquePreview();
        var map = exploreMap();

        if (!coords || !map || typeof L === 'undefined' || !$plaquePositioned.length) {
            clearMainMapVirtualPlaque();
            return;
        }

        if (window.explorePlaquesHideSelectedMarker) {
            window.explorePlaquesHideSelectedMarker();
        }

        virtualPlaqueLatLng = L.latLng(coords.lat, coords.lng);
        $plaquePositioned.removeClass('d-none');
        bindMainMapPlaqueListeners();
        renderVirtualPlaqueSvg(preview);

        window.setTimeout(function() {
            updatePlaqueOnMapPosition();
        }, 0);
    }

    function showPreview(url) {
        if (!url) {
            $previewWrap.addClass('d-none');
            return;
        }
        $previewWrap.removeClass('d-none');
        $openLink.attr('href', url);
    }

    function updateUiForSelection() {
        if (!currentStatus || !currentStatus.available) {
            return;
        }

        var personId = $personSelect.val();
        var placeId = $placeSelect.val();
        var virtualUrl = virtualUrlForPair(currentStatus, personId, placeId);
        var needsCreate = pairNeedsCreate(currentStatus, personId, placeId);

        updateVirtualPlaqueOnMainMap();

        if (virtualUrl) {
            $badge.removeClass('bg-secondary bg-warning').addClass('bg-success').text('Ready');
            $createForm.addClass('d-none');
            showPreview(virtualUrl);
        } else if (needsCreate) {
            $badge.removeClass('bg-secondary bg-success').addClass('bg-warning').text('Missing');
            $createForm.removeClass('d-none');
            showPreview(null);
            updateConnectionSummary();
        }
    }

    function renderStatus(status) {
        currentStatus = status;
        $loading.addClass('d-none');
        $content.removeClass('d-none');
        $message.addClass('d-none').text('');
        $pairSelectors.addClass('d-none');
        $createForm.addClass('d-none');
        $previewWrap.addClass('d-none');
        clearMainMapVirtualPlaque();
        showPreview(null);

        if (!status.available) {
            $badge.removeClass('bg-success bg-warning').addClass('bg-secondary').text('N/A');
            $message.removeClass('d-none').text(status.message || 'Cannot build a virtual plaque for this selection.');
            return;
        }

        var people = status.people || [];
        var places = status.places || [];

        if (people.length > 1 || places.length > 1) {
            $pairSelectors.removeClass('d-none');
        }

        populateSelect($personSelect, people);
        populateSelect($placeSelect, places);
        populateTypeSelect(status.connection_types || [], status.suggested ? status.suggested.connection_type : null);

        if (status.suggested) {
            var hint = status.suggested.reason || '';
            if (status.plaque && status.plaque.description_snippet) {
                hint += (hint ? ' ' : '') + '"' + status.plaque.description_snippet + '"';
            }
            $suggestedHint.text(hint);
            applySuggestedDates(status.suggested);
        } else {
            $suggestedHint.text('');
            applySuggestedDates(null);
        }

        updateUiForSelection();
    }

    function loadVirtualPlaqueStatus(plaqueId) {
        currentPlaqueId = plaqueId;
        $card.removeClass('d-none');
        $content.addClass('d-none');
        $loading.removeClass('d-none');

        $.get(virtualPlaqueStatusUrl(plaqueId))
            .done(function(data) {
                renderStatus(data);
            })
            .fail(function(xhr) {
                $loading.addClass('d-none');
                $content.removeClass('d-none');
                $badge.removeClass('bg-success bg-warning').addClass('bg-secondary').text('Error');
                var msg = 'Could not load virtual plaque status.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                }
                $message.removeClass('d-none').text(msg);
            });
    }

    $personSelect.add($placeSelect).on('change', updateUiForSelection);
    $typeSelect.on('change', function() {
        updateConnectionSummary();
        updateVirtualPlaqueOnMainMap();
    });
    $startYear.add($endYear).on('input', function() {
        updateConnectionSummary();
        updateVirtualPlaqueOnMainMap();
    });

    $createBtn.on('click', function() {
        if (!currentPlaqueId || !currentStatus || !currentStatus.available) {
            return;
        }

        var payload = {
            person_id: $personSelect.val(),
            place_id: $placeSelect.val(),
            connection_type: $typeSelect.val(),
            _token: csrfToken,
        };

        var startYear = parseInt($startYear.val(), 10);
        var endYear = parseInt($endYear.val(), 10);
        if (!isNaN(startYear)) {
            payload.start_year = startYear;
        }
        if (!isNaN(endYear)) {
            payload.end_year = endYear;
        }

        $createBtn.prop('disabled', true);

        $.ajax({
            url: virtualPlaqueStatusUrl(currentPlaqueId),
            method: 'POST',
            data: payload,
        })
            .done(function(data) {
                if (data.status) {
                    renderStatus(data.status);
                } else {
                    loadVirtualPlaqueStatus(currentPlaqueId);
                }
            })
            .fail(function(xhr) {
                var msg = 'Could not create connection.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                }
                alert(msg);
            })
            .always(function() {
                $createBtn.prop('disabled', false);
            });
    });

    window.loadVirtualPlaqueForPlaque = loadVirtualPlaqueStatus;
});
</script>
@endpush
