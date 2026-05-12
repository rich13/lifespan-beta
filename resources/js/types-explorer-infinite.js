/**
 * Infinite scroll for the types explorer third column (/spans/types/{type}/{subtype}).
 */
$(function () {
    const $scroll = $('#types-explorer-spans-scroll');
    if (!$scroll.length) {
        return;
    }

    const $list = $('#types-explorer-spans-list');
    if (!$list.length) {
        return;
    }

    const $loading = $('#types-explorer-spans-loading');
    const $search = $('#types-explorer-spans-search');
    const selectedSpanId = $scroll.attr('data-selected-span-id') || '';
    let loading = false;
    let nextPageUrl = $scroll.attr('data-next-page') || '';
    let selectedSpanAutoScrolled = false;
    let activeSearchTerm = '';
    let searchDebounceTimer = null;
    let loadErrorCount = 0;
    const maxLoadRetries = 3;

    function setNextPage(url) {
        nextPageUrl = url || '';
        if (nextPageUrl) {
            $scroll.attr('data-next-page', nextPageUrl);
        } else {
            $scroll.removeAttr('data-next-page');
        }
    }

    function nearBottom() {
        const el = $scroll[0];
        const threshold = 100;
        return el.scrollHeight - el.scrollTop - el.clientHeight < threshold;
    }

    function selectedRow() {
        if (!selectedSpanId) {
            return $();
        }
        return $list.find('.types-explorer__span-row--active').first();
    }

    function scrollSelectedIntoView() {
        if (selectedSpanAutoScrolled) {
            return;
        }
        const $row = selectedRow();
        if (!$row.length) {
            return;
        }
        const rowTop = $row.position().top;
        const rowBottom = rowTop + $row.outerHeight();
        const viewportHeight = $scroll.innerHeight();
        if (rowTop < 0 || rowBottom > viewportHeight) {
            const current = $scroll.scrollTop();
            const target = current + rowTop - (viewportHeight / 2) + ($row.outerHeight() / 2);
            $scroll.scrollTop(Math.max(0, target));
        }
        selectedSpanAutoScrolled = true;
    }

    function ensureSelectedVisible() {
        if (!selectedSpanId || selectedSpanAutoScrolled) {
            return;
        }
        if (selectedRow().length) {
            scrollSelectedIntoView();
            return;
        }
        if (nextPageUrl && !loading) {
            loadMore(ensureSelectedVisible);
        }
    }

    function loadMore(onComplete) {
        if (loading || !nextPageUrl) {
            if (typeof onComplete === 'function') {
                onComplete();
            }
            return;
        }

        loading = true;
        if ($loading.length) {
            $loading.removeClass('d-none');
        }

        const url = new URL(nextPageUrl, window.location.origin);
        url.searchParams.set('partial_spans', '1');
        if (selectedSpanId && !activeSearchTerm) {
            url.searchParams.set('selected_span_id', selectedSpanId);
        }
        if (activeSearchTerm) {
            url.searchParams.set('search', activeSearchTerm);
        }

        $.ajax({
            url: url.toString(),
            method: 'GET',
            dataType: 'json',
            success(data) {
                loadErrorCount = 0;
                if (data.html) {
                    $list.append(data.html);
                }
                setNextPage(data.next_page_url || '');
            },
            error() {
                loadErrorCount += 1;
                if (loadErrorCount >= maxLoadRetries) {
                    setNextPage('');
                }
            },
            complete() {
                loading = false;
                if ($loading.length) {
                    $loading.addClass('d-none');
                }
                if (typeof onComplete === 'function') {
                    onComplete();
                }
                if (selectedSpanId && !selectedSpanAutoScrolled && loadErrorCount > 0 && loadErrorCount < maxLoadRetries && nextPageUrl) {
                    setTimeout(function retryLoadForSelected() {
                        if (!loading) {
                            loadMore(ensureSelectedVisible);
                        }
                    }, 300);
                    return;
                }
                if (nextPageUrl && nearBottom()) {
                    loadMore();
                }
            },
        });
    }

    $scroll.on('scroll', function () {
        if (nearBottom()) {
            loadMore();
        }
    });

    function reloadForSearch() {
        if (loading) {
            return;
        }
        const url = new URL(window.location.href);
        url.searchParams.set('partial_spans', '1');
        url.searchParams.set('page', '1');
        if (activeSearchTerm) {
            url.searchParams.set('search', activeSearchTerm);
        } else {
            url.searchParams.delete('search');
        }
        if (selectedSpanId && !activeSearchTerm) {
            url.searchParams.set('selected_span_id', selectedSpanId);
        } else {
            url.searchParams.delete('selected_span_id');
        }
        loading = true;
        if ($loading.length) {
            $loading.removeClass('d-none');
        }
        $.ajax({
            url: url.toString(),
            method: 'GET',
            dataType: 'json',
            success(data) {
                $list.empty();
                if (data.html) {
                    $list.append(data.html);
                }
                setNextPage(data.next_page_url || '');
                selectedSpanAutoScrolled = false;
                if (!activeSearchTerm) {
                    ensureSelectedVisible();
                    scrollSelectedIntoView();
                }
            },
            complete() {
                loading = false;
                if ($loading.length) {
                    $loading.addClass('d-none');
                }
            },
        });
    }

    if ($search.length) {
        $search.on('input', function () {
            activeSearchTerm = String($(this).val() || '').trim();
            if (searchDebounceTimer) {
                clearTimeout(searchDebounceTimer);
            }
            searchDebounceTimer = setTimeout(reloadForSearch, 250);
        });
    }

    setTimeout(function maybeFillShortColumn() {
        if (nextPageUrl && nearBottom() && !loading) {
            loadMore();
        }
        ensureSelectedVisible();
        scrollSelectedIntoView();
    }, 0);

    const sentinel = document.getElementById('types-explorer-spans-sentinel');
    const scrollEl = $scroll[0];
    if (sentinel && scrollEl && 'IntersectionObserver' in window) {
        const io = new IntersectionObserver(
            function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting && !loading && nextPageUrl) {
                        loadMore();
                    }
                });
            },
            {
                root: scrollEl,
                rootMargin: '160px 0px 240px 0px',
                threshold: 0,
            }
        );
        io.observe(sentinel);
    }
});
