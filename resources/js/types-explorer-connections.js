/**
 * Types explorer fifth column: load /spans/{span}/connections.json and list explore links.
 */
$(function () {
    const $panel = $('#types-explorer-connections-panel');
    if (!$panel.length) {
        return;
    }

    const url = $panel.data('connections-json-url');
    if (!url) {
        return;
    }

    const $connectionsSection = $('#types-explorer-connections-section');
    const $participantsSection = $('#types-explorer-participants-section');
    const $sortControls = $('#types-explorer-connections-sort');
    const $search = $('#types-explorer-connections-search');
    const $loading = $('#types-explorer-connections-loading');
    const $error = $('#types-explorer-connections-error');
    const $list = $('#types-explorer-connections-list');
    const $truncated = $('#types-explorer-connections-truncated');
    const $participantsList = $('#types-explorer-participants-list');
    const selectedSpanType = ($panel.data('selected-span-type') || '').toString();
    let currentSortMode = 'chronological';
    let connectionRows = [];
    let participantRows = [];
    let activeSearchTerm = '';

    function formatExplorerDate(year, month, day) {
        if (year == null || year === '') {
            return '';
        }
        const y = Number(year);
        const m = month != null && month !== '' ? Number(month) : null;
        const d = day != null && day !== '' ? Number(day) : null;
        if (m && d) {
            const dt = new Date(Date.UTC(y, m - 1, d));
            return dt.toLocaleDateString('en-US', {
                timeZone: 'UTC',
                year: 'numeric',
                month: 'long',
                day: 'numeric',
            });
        }
        if (m) {
            const dt = new Date(Date.UTC(y, m - 1, 1));
            return dt.toLocaleDateString('en-US', {
                timeZone: 'UTC',
                year: 'numeric',
                month: 'long',
            });
        }
        return String(year);
    }

    function connectionSpanDatesIdentical(cs) {
        if (cs == null || cs.start_year == null || cs.end_year == null) {
            return false;
        }
        const sm = cs.start_month != null && cs.start_month !== '' ? Number(cs.start_month) : 0;
        const em = cs.end_month != null && cs.end_month !== '' ? Number(cs.end_month) : 0;
        const sd = cs.start_day != null && cs.start_day !== '' ? Number(cs.start_day) : 0;
        const ed = cs.end_day != null && cs.end_day !== '' ? Number(cs.end_day) : 0;
        return Number(cs.start_year) === Number(cs.end_year) && sm === em && sd === ed;
    }

    function formatConnectionDateRange(cs) {
        if (!cs) {
            return '';
        }
        const start = formatExplorerDate(cs.start_year, cs.start_month, cs.start_day);
        const end = formatExplorerDate(cs.end_year, cs.end_month, cs.end_day);
        if (start && end && connectionSpanDatesIdentical(cs)) {
            return start;
        }
        if (start) {
            return `${start} – ${end || 'now'}`;
        }
        if (end) {
            return end;
        }
        return '';
    }

    function connectionBadgeColour(connectionTypeId) {
        if (!connectionTypeId) {
            return '';
        }
        return getComputedStyle(document.documentElement)
            .getPropertyValue(`--connection-${connectionTypeId}-color`)
            .trim();
    }

    function connectionSpanStartUtcMs(row) {
        const cs = row.connection_span;
        if (!cs || cs.start_year == null || cs.start_year === '') {
            return null;
        }
        const y = Number(cs.start_year);
        const m = cs.start_month != null && cs.start_month !== '' ? Number(cs.start_month) : 1;
        const d = cs.start_day != null && cs.start_day !== '' ? Number(cs.start_day) : 1;
        return Date.UTC(y, m - 1, d);
    }

    function compareChronologicalThenPredicateAndName(a, b) {
        const aStart = connectionSpanStartUtcMs(a);
        const bStart = connectionSpanStartUtcMs(b);
        if (aStart !== bStart) {
            if (aStart == null) {
                return 1;
            }
            if (bStart == null) {
                return -1;
            }
            return aStart - bStart;
        }
        const aPred = String(a.predicate || '').toLowerCase();
        const bPred = String(b.predicate || '').toLowerCase();
        if (aPred !== bPred) {
            return aPred.localeCompare(bPred);
        }
        const aOther = String((a.other && a.other.name) || '').toLowerCase();
        const bOther = String((b.other && b.other.name) || '').toLowerCase();
        return aOther.localeCompare(bOther);
    }

    function sortRows(rows, mode) {
        if (mode === 'chronological') {
            return rows.slice();
        }
        if (mode === 'direction') {
            return rows.slice().sort(function (a, b) {
                const aDir = String(a.direction || '').toLowerCase();
                const bDir = String(b.direction || '').toLowerCase();
                if (aDir !== bDir) {
                    return aDir.localeCompare(bDir);
                }
                return compareChronologicalThenPredicateAndName(a, b);
            });
        }
        if (mode === 'type') {
            return rows.slice().sort(function (a, b) {
                const aType = String(a.predicate_type_id || '').toLowerCase();
                const bType = String(b.predicate_type_id || '').toLowerCase();
                if (aType !== bType) {
                    return aType.localeCompare(bType);
                }
                return compareChronologicalThenPredicateAndName(a, b);
            });
        }
        return rows.slice();
    }

    function otherEndpointGenealogyLabel(row) {
        const r = String(row.genealogy_other_role || '');
        if (r === 'child') {
            return 'Child';
        }
        if (r === 'parent') {
            return 'Parent';
        }
        if (String(row.predicate_type_id || '') === 'family') {
            if (row.direction === 'outgoing') {
                return 'Child';
            }
            if (row.direction === 'incoming') {
                return 'Parent';
            }
        }
        return '';
    }

    function directionTooltip(row) {
        const role = String(row.genealogy_other_role || '');
        if (role === 'child') {
            return 'They are recorded as the child on this family link (this span is the parent in the record).';
        }
        if (role === 'parent') {
            return 'They are recorded as the parent on this family link (this span is the child in the record).';
        }
        if (row.direction === 'incoming') {
            return 'This span is the child on the connection (the other span is the parent in the record).';
        }
        if (row.direction === 'outgoing') {
            return 'This span is the parent on the connection (the other span is the child in the record).';
        }
        return '';
    }

    function filterConnections(rows, term) {
        if (!term) {
            return rows;
        }
        const q = term.toLowerCase();
        return rows.filter(function (row) {
            const pred = String(row.predicate || row.predicate_type_id || '').toLowerCase();
            const otherName = String((row.other && row.other.name) || '').toLowerCase();
            const role = String(otherEndpointGenealogyLabel(row) || '').toLowerCase();
            return pred.includes(q) || otherName.includes(q) || role.includes(q);
        });
    }

    function filterParticipants(rows, term) {
        if (!term) {
            return rows;
        }
        const q = term.toLowerCase();
        return rows.filter(function (row) {
            const name = String(row.name || '').toLowerCase();
            const meta = String(typeSubtypeLabel(row.type_id, row.subtype_key) || '').toLowerCase();
            return name.includes(q) || meta.includes(q);
        });
    }

    function humaniseToken(value) {
        return String(value || '')
            .replace(/_/g, ' ')
            .replace(/\b\w/g, function (match) {
                return match.toUpperCase();
            });
    }

    function typeSubtypeLabel(typeId, subtypeKey) {
        const typeText = String(typeId || '').trim();
        const subtypeText = String(subtypeKey || '').trim();
        if (!typeText) {
            return '';
        }
        if (!subtypeText || subtypeText === 'no_subtype') {
            return humaniseToken(typeText);
        }
        return `${humaniseToken(typeText)} / ${humaniseToken(subtypeText)}`;
    }

    function renderConnections(rows) {
        $list.empty();
        if (!rows.length) {
            $connectionsSection.addClass('d-none');
            return;
        }
        $connectionsSection.removeClass('d-none');
        $list.removeClass('d-none');
        $.each(rows, function (_, row) {
            const other = row.other || {};
            const spanHref = other.explorer_url || other.url || '#';
            const connectionHref = (row.connection_span && (row.connection_span.explorer_url || row.connection_span.url)) || '#';
            const pred = (row.predicate || row.predicate_type_id || '').trim();
            const otherName = (other.name || '').trim() || '—';
            const otherTypeSubtype = typeSubtypeLabel(other.type_id, other.subtype_key);
            const dateLine = formatConnectionDateRange(row.connection_span);
            const connectionTypeId = row.predicate_type_id || '';
            const predicateBadgeColour = connectionBadgeColour(connectionTypeId);
            const roleLabel = otherEndpointGenealogyLabel(row);
            const dirTip = directionTooltip(row);
            const dirIcon =
                row.direction === 'incoming'
                    ? 'bi-arrow-return-left'
                    : row.direction === 'outgoing'
                      ? 'bi-arrow-return-right'
                      : 'bi-link-45deg';
            const $row = $('<div/>', {
                class: 'list-group-item py-2 px-3 types-explorer__connection-row',
            });
            const $rowInner = $('<div/>', {
                class: 'd-flex align-items-start gap-2',
            });
            const $dir = $('<span/>', {
                class: 'types-explorer__connection-dir',
                title: dirTip,
                'aria-hidden': 'true',
            }).append(
                $('<i/>', {
                    class: `bi ${dirIcon}`,
                })
            );
            const $badges = $('<div/>', {
                class: 'types-explorer__connection-badges d-flex flex-wrap align-items-center gap-2 flex-grow-1 min-w-0',
            });
            const $predicateBadge = $('<a/>', {
                href: connectionHref,
                class: 'badge rounded-pill text-decoration-none types-explorer__connection-badge types-explorer__connection-badge--predicate',
            }).text(pred || row.predicate_type_id || 'connection');
            if (predicateBadgeColour) {
                $predicateBadge.css({
                    'background-color': predicateBadgeColour,
                    'border-color': predicateBadgeColour,
                });
            }
            const otherTypeId = (other.type_id || '').trim();
            const $spanBadge = $('<a/>', {
                href: spanHref,
                class: `badge rounded-pill text-decoration-none types-explorer__connection-badge ${
                    otherTypeId ? `bg-${otherTypeId}` : 'bg-secondary'
                }`,
            }).text(otherName);
            $badges.append($predicateBadge, $spanBadge);
            if (otherTypeSubtype) {
                $badges.append(
                    $('<span/>', {
                        class: 'badge rounded-pill bg-body-tertiary text-body-secondary border types-explorer__connection-meta-chip',
                    }).text(otherTypeSubtype)
                );
            }
            if (roleLabel) {
                const roleDisplay = roleLabel.toUpperCase();
                $badges.append(
                    $('<span/>', {
                        class: 'badge rounded-pill bg-body-secondary text-body-emphasis types-explorer__connection-role-chip',
                        title:
                            roleLabel === 'Child'
                                ? 'The other person is the child on this family link (this span is the parent in the record).'
                                : 'The other person is the parent on this family link (this span is the child in the record).',
                    }).text(roleDisplay)
                );
            }
            $rowInner.append($dir, $badges);
            $row.append($rowInner);
            if (dateLine) {
                $row.append($('<div/>', { class: 'small text-muted mt-2 text-truncate' }).text(dateLine));
            }
            $list.append($row);
        });
    }

    function renderParticipants(participants) {
        $participantsList.empty();
        if (selectedSpanType === 'connection' && participants.length) {
            $participantsSection.removeClass('d-none');
            $participantsList.removeClass('d-none');
            $.each(participants, function (_, participant) {
                const participantHref = participant.explorer_url || participant.url || '#';
                const participantTypeId = (participant.type_id || '').trim();
                const participantName = (participant.name || '').trim() || '—';
                const participantTypeSubtype = typeSubtypeLabel(participant.type_id, participant.subtype_key);
                const $participantRow = $('<div/>', { class: 'list-group-item py-2 px-3 types-explorer__connection-row' });
                const $participantBadges = $('<div/>', {
                    class: 'd-flex flex-wrap align-items-center gap-2',
                });
                const $participantBadge = $('<a/>', {
                    href: participantHref,
                    class: `badge rounded-pill text-decoration-none types-explorer__connection-badge ${
                        participantTypeId ? `bg-${participantTypeId}` : 'bg-secondary'
                    }`,
                }).text(participantName);
                $participantBadges.append($participantBadge);
                if (participantTypeSubtype) {
                    $participantBadges.append(
                        $('<span/>', {
                            class: 'badge rounded-pill bg-body-tertiary text-body-secondary border types-explorer__connection-meta-chip',
                        }).text(participantTypeSubtype)
                    );
                }
                $participantRow.append($participantBadges);
                $participantsList.append($participantRow);
            });
        } else {
            $participantsSection.addClass('d-none');
        }
    }

    function renderAllFiltered() {
        const sorted = sortRows(connectionRows, currentSortMode);
        renderConnections(filterConnections(sorted, activeSearchTerm));
        renderParticipants(filterParticipants(participantRows, activeSearchTerm));
    }

    function loadAllConnections(nextUrl, participants, onDone) {
        if (!nextUrl) {
            onDone(participants);
            return;
        }
        $.ajax({
            url: nextUrl,
            dataType: 'json',
            success(payload) {
                const rows = payload && payload.data && payload.data.connections ? payload.data.connections : [];
                const meta = payload && payload.meta ? payload.meta : {};
                if (rows.length) {
                    connectionRows = connectionRows.concat(rows);
                    renderAllFiltered();
                }
                const moreUrl = (meta && (meta.next_page_url || (meta.links && meta.links.next_page))) || null;
                loadAllConnections(moreUrl, participants, onDone);
            },
            error() {
                $connectionsSection.removeClass('d-none');
                $error.removeClass('d-none').text('Could not load all connections.');
                onDone(participants);
            },
        });
    }

    $.ajax({
        url,
        dataType: 'json',
        success(payload) {
            const rows = payload && payload.data && payload.data.connections ? payload.data.connections : [];
            const participants = payload && payload.data && payload.data.participants ? payload.data.participants : [];
            const meta = payload && payload.meta ? payload.meta : {};
            connectionRows = rows.slice();
            participantRows = participants.slice();
            renderAllFiltered();
            const nextUrl = (meta && (meta.next_page_url || (meta.links && meta.links.next_page))) || null;
            loadAllConnections(nextUrl, participants, function () {
                $loading.addClass('d-none');
                $truncated.addClass('d-none');
            });
        },
        error() {
            $loading.addClass('d-none');
            $connectionsSection.removeClass('d-none');
            $error.removeClass('d-none');
            $participantsSection.addClass('d-none');
        },
    });

    if ($sortControls.length) {
        $sortControls.on('click', '[data-sort-mode]', function () {
            const $btn = $(this);
            const sortMode = String($btn.data('sort-mode') || 'chronological');
            if (sortMode === currentSortMode) {
                return;
            }
            currentSortMode = sortMode;
            $sortControls.find('[data-sort-mode]').removeClass('active');
            $btn.addClass('active');
            renderAllFiltered();
        });
    }

    if ($search.length) {
        $search.on('input', function () {
            activeSearchTerm = String($(this).val() || '').trim();
            renderAllFiltered();
        });
    }
});
