$(function () {
    const $root = $('#did-explorer');
    const $people = $('#did-explorer-people');
    if (!$root.length || !$people.length) {
        return;
    }

    const setsBase = String($root.data('sets-base') || '').replace(/\/$/, '');
    let requestId = 0;
    let currentPayload = null;

    const escapeText = function (value) {
        return $('<div>').text(value == null ? '' : String(value)).html();
    };

    const coverMarkup = function (album, alt, large) {
        if (!album) {
            return '<div class="did-explorer-cover-fallback"><i class="bi bi-disc"></i></div>';
        }

        const url = large ? (album.cover_large_url || album.cover_url) : album.cover_url;
        if (url) {
            return '<img class="did-explorer-cover-image" src="' + escapeText(url) + '" alt="' + escapeText(alt) + '" loading="lazy">';
        }

        if (album.needs_cover && album.id) {
            return '<div class="did-explorer-cover-fallback js-did-cover" data-cover-span="' + escapeText(album.id) + '" data-cover-size="' + (large ? 'large' : 'small') + '" data-cover-alt="' + escapeText(alt) + '"><div class="spinner-border spinner-border-sm text-secondary" role="status"><span class="visually-hidden">Loading cover</span></div></div>';
        }

        return '<div class="did-explorer-cover-fallback"><i class="bi bi-disc"></i></div>';
    };

    const loadMissingCovers = function () {
        const $pending = $('.js-did-cover[data-cover-span]');
        if (!$pending.length) {
            return;
        }

        const ids = [...new Set($pending.map(function () {
            return $(this).attr('data-cover-span');
        }).get())];

        $.getJSON('/api/cover-art', { span_ids: ids }).done(function (data) {
            const covers = data.covers || {};
            $('.js-did-cover[data-cover-span]').each(function () {
                const $el = $(this);
                const id = $el.attr('data-cover-span');
                const urls = covers[id];
                const size = $el.attr('data-cover-size') || 'small';
                const url = urls && (urls[size] || urls.small);
                if (!url) {
                    $el.removeClass('js-did-cover').html('<i class="bi bi-disc"></i>');
                    return;
                }
                const $img = $('<img>', {
                    class: 'did-explorer-cover-image',
                    src: url,
                    alt: $el.attr('data-cover-alt') || '',
                    loading: 'lazy'
                });
                $el.replaceWith($img);
            });
        });
    };

    const renderDetailPrompt = function () {
        $('#did-explorer-detail').html(
            '<div class="did-explorer-placeholder text-muted">' +
                '<i class="bi bi-music-note-beamed"></i>' +
                '<p class="mb-0">Choose a record to read about it.</p>' +
            '</div>'
        );
    };

    const renderTrack = function (track) {
        const artist = track.artist
            ? '<a class="did-explorer-detail-artist" href="' + escapeText(track.artist.url) + '">' + escapeText(track.artist.name) + '</a>'
            : '';
        let album = '';
        if (track.album) {
            const year = track.album.year ? ' <span class="text-muted">' + escapeText(track.album.year) + '</span>' : '';
            album = '<a class="did-explorer-detail-album" href="' + escapeText(track.album.url) + '">' + escapeText(track.album.name) + '</a>' + year;
        }
        const favourite = track.is_favourite
            ? '<p class="did-explorer-favourite"><i class="bi bi-star-fill"></i> Favourite disc</p>'
            : '';
        const description = track.description
            ? '<p class="did-explorer-detail-note">' + escapeText(track.description) + '</p>'
            : '<p class="did-explorer-detail-note text-muted">No note recorded for this track.</p>';

        $('#did-explorer-detail').html(
            '<div class="did-explorer-detail-body">' +
                '<div class="did-explorer-detail-cover">' + coverMarkup(track.album, track.name + ' cover', true) + '</div>' +
                (track.position ? '<p class="did-explorer-detail-position">Disc ' + escapeText(track.position) + '</p>' : '') +
                '<h2 class="did-explorer-detail-title"><a href="' + escapeText(track.url) + '">' + escapeText(track.name) + '</a></h2>' +
                artist +
                (album ? '<p class="did-explorer-detail-album-line">' + album + '</p>' : '') +
                favourite +
                description +
            '</div>'
        );
        loadMissingCovers();
    };

    const renderSet = function (payload) {
        currentPayload = payload;
        const person = payload.person || {};
        const set = payload.set || {};
        const personName = person.url
            ? '<a href="' + escapeText(person.url) + '">' + escapeText(person.name) + '</a>'
            : escapeText(person.name);
        const meta = [set.broadcast, set.presenter].filter(Boolean).map(escapeText).join(' · ');

        const $grid = $('<div class="did-explorer-grid"></div>');
        const tracks = payload.tracks || [];
        tracks.forEach(function (track, index) {
            const position = track.position || (index + 1);
            const $button = $('<button type="button" class="did-explorer-cover"></button>');
            $button.attr('data-track-id', track.id);
            $button.attr('title', track.name);
            if (track.is_favourite) {
                $button.addClass('is-favourite');
            }
            const artistName = track.artist && track.artist.name ? track.artist.name : '';
            $button.html(
                coverMarkup(track.album, track.name + ' cover', false) +
                '<span class="did-explorer-cover-caption">' +
                    '<span class="did-explorer-cover-title">' + escapeText(track.name) + '</span>' +
                    (artistName ? '<span class="did-explorer-cover-artist">' + escapeText(artistName) + '</span>' : '') +
                '</span>' +
                '<span class="did-explorer-cover-number">' + escapeText(position) + '</span>' +
                (track.is_favourite ? '<span class="did-explorer-cover-star" title="Favourite disc"><i class="bi bi-star-fill"></i></span>' : '')
            );
            $grid.append($button);
        });

        const slots = Math.max(8, tracks.length);
        for (let i = tracks.length; i < slots; i += 1) {
            $grid.append(
                '<div class="did-explorer-cover is-empty" aria-hidden="true">' +
                    '<span class="did-explorer-cover-number">' + (i + 1) + '</span>' +
                '</div>'
            );
        }

        let extras = '';
        (payload.books || []).forEach(function (book) {
            const author = book.author
                ? ' <span class="text-muted">by <a href="' + escapeText(book.author.url) + '">' + escapeText(book.author.name) + '</a></span>'
                : '';
            extras += '<p class="mb-1"><i class="bi bi-book me-1"></i><a href="' + escapeText(book.url) + '">' + escapeText(book.name) + '</a>' + author + '</p>';
        });
        if (set.luxury) {
            extras += '<p class="mb-0"><i class="bi bi-gem me-1"></i>' + escapeText(set.luxury) + '</p>';
        }

        const extrasMarkup = extras
            ? '<div class="did-explorer-extras">' + extras + '</div>'
            : '';

        $('#did-explorer-covers').html(
            '<div class="did-explorer-covers-body">' +
                '<header class="did-explorer-covers-header">' +
                    '<h2 class="did-explorer-castaway">' + personName + '</h2>' +
                    (meta ? '<p class="did-explorer-broadcast text-muted mb-0">' + meta + '</p>' : '') +
                '</header>' +
                (tracks.length ? '' : '<p class="text-muted">No tracks in this set yet.</p>') +
            '</div>'
        );
        $('#did-explorer-covers .did-explorer-covers-body').append($grid).append(extrasMarkup);
        renderDetailPrompt();
        loadMissingCovers();
    };

    const markSelectedPerson = function (setKey) {
        $people.find('.did-explorer-person').removeClass('is-active').attr('aria-pressed', 'false');
        const $selected = $people.find('.did-explorer-person').filter(function () {
            return $(this).attr('data-set') === setKey;
        });
        $selected.addClass('is-active').attr('aria-pressed', 'true');
        if ($selected.length && $selected[0].scrollIntoView) {
            $selected[0].scrollIntoView({ block: 'nearest' });
        }
    };

    const showLoading = function () {
        $('#did-explorer-covers').attr('aria-busy', 'true').html(
            '<div class="did-explorer-placeholder text-muted">' +
                '<div class="spinner-border spinner-border-sm" role="status"><span class="visually-hidden">Loading records</span></div>' +
                '<p class="mb-0">Loading records…</p>' +
            '</div>'
        );
    };

    const loadSet = function (setKey, updateUrl) {
        if (!setKey) {
            return;
        }

        markSelectedPerson(setKey);
        if (updateUrl) {
            const url = new URL(window.location.href);
            url.searchParams.set('set', setKey);
            window.history.replaceState({}, '', url);
        }

        const id = ++requestId;
        showLoading();
        renderDetailPrompt();

        $.getJSON(setsBase + '/' + encodeURIComponent(setKey))
            .done(function (payload) {
                if (id !== requestId) {
                    return;
                }
                $('#did-explorer-covers').attr('aria-busy', 'false');
                renderSet(payload);
            })
            .fail(function () {
                if (id !== requestId) {
                    return;
                }
                $('#did-explorer-covers').attr('aria-busy', 'false').html(
                    '<div class="did-explorer-placeholder text-muted">' +
                        '<i class="bi bi-exclamation-circle"></i>' +
                        '<p class="mb-0">Could not load this set.</p>' +
                    '</div>'
                );
            });
    };

    $('#did-explorer-search').on('input', function () {
        const query = $(this).val().trim().toLowerCase();
        let visible = 0;
        $people.find('.did-explorer-person').each(function () {
            const name = String($(this).attr('data-name') || '').toLowerCase();
            const matches = query === '' || name.indexOf(query) !== -1;
            $(this).closest('li').toggle(matches);
            if (matches) {
                visible += 1;
            }
        });
        const label = visible === 1 ? 'castaway' : 'castaways';
        $('#did-explorer-count').text(visible + ' ' + label);
        $('#did-explorer-no-matches').prop('hidden', visible !== 0);
    });

    $people.on('click', '.did-explorer-person', function () {
        loadSet($(this).attr('data-set'), true);
    });

    $('#did-explorer-covers').on('click', '.did-explorer-cover', function () {
        if (!currentPayload) {
            return;
        }
        const trackId = $(this).attr('data-track-id');
        const track = (currentPayload.tracks || []).find(function (item) {
            return item.id === trackId;
        });
        if (!track) {
            return;
        }
        $('#did-explorer-covers .did-explorer-cover').removeClass('is-selected');
        $(this).addClass('is-selected');
        renderTrack(track);
    });

    const requested = String($root.attr('data-selected') || '');
    const $requested = requested
        ? $people.find('.did-explorer-person').filter(function () {
            return $(this).attr('data-set') === requested;
        })
        : $();
    const $initial = $requested.length ? $requested : $people.find('.did-explorer-person').first();
    if ($initial.length) {
        loadSet($initial.attr('data-set'), false);
    }
});
