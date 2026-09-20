$(document).ready(function() {
    const loadCoverArt = function() {
        if (!$('.js-cover-art[data-cover-art-span]').length) {
            return;
        }

        let attempts = 0;
        const maxAttempts = 20;

        const showFallbackIcon = function($el) {
            $el.removeClass('js-cover-art is-loading')
                .removeAttr('data-cover-art-span')
                .attr('aria-busy', 'false');
            if (!$el.find('.bi-music-note-beamed').length) {
                $el.empty().append('<i class="bi bi-music-note-beamed text-muted"></i>');
            }
        };

        const ensureSpinner = function($el) {
            if ($el.hasClass('is-loading') && $el.find('.cover-art-spinner').length) {
                return;
            }
            $el.addClass('is-loading').attr('aria-busy', 'true');
            $el.empty().append(
                '<div class="spinner-border text-secondary cover-art-spinner" role="status">' +
                    '<span class="visually-hidden">Loading cover art</span>' +
                '</div>'
            );
        };

        $('.js-cover-art[data-cover-art-span]').each(function() {
            ensureSpinner($(this));
        });

        const applyCover = function($el, urls) {
            if (!urls || !urls.small) {
                return false;
            }

            const size = $el.data('cover-art-size') || 'small';
            const url = urls[size] || urls.small;
            if (!url) {
                return false;
            }

            const $parentSquare = $el.closest('.track-square');
            const variantClass = ($el.attr('class') || '')
                .split(/\s+/)
                .filter(function(cls) {
                    return cls.indexOf('cover-art--') === 0;
                })
                .join(' ');

            const $img = $('<img>', {
                src: url,
                alt: $el.data('cover-art-alt') || '',
                class: $.trim('cover-art-image ' + variantClass),
                loading: 'lazy'
            });
            $el.replaceWith($img);
            if ($parentSquare.length) {
                $parentSquare.addClass('has-cover-art');
            }
            return true;
        };

        const requestCovers = function() {
            const $current = $('.js-cover-art[data-cover-art-span]');
            const ids = [...new Set($current.map(function() {
                return $(this).attr('data-cover-art-span');
            }).get())];

            if (!ids.length) {
                return;
            }

            $.getJSON('/api/cover-art', { span_ids: ids })
                .done(function(data) {
                    const covers = data.covers || {};
                    $('.js-cover-art[data-cover-art-span]').each(function() {
                        const $el = $(this);
                        const id = $el.attr('data-cover-art-span');
                        if (!Object.prototype.hasOwnProperty.call(covers, id)) {
                            return;
                        }
                        if (!applyCover($el, covers[id])) {
                            showFallbackIcon($el);
                        }
                    });

                    attempts += 1;
                    const stillPending = $('.js-cover-art[data-cover-art-span]').length;
                    if (stillPending && attempts < maxAttempts && (data.pending || []).length) {
                        setTimeout(requestCovers, 2000);
                        return;
                    }
                    if (stillPending) {
                        $('.js-cover-art[data-cover-art-span]').each(function() {
                            showFallbackIcon($(this));
                        });
                    }
                })
                .fail(function() {
                    attempts += 1;
                    if (attempts < maxAttempts && $('.js-cover-art[data-cover-art-span]').length) {
                        setTimeout(requestCovers, 2000);
                        return;
                    }
                    $('.js-cover-art[data-cover-art-span]').each(function() {
                        showFallbackIcon($(this));
                    });
                });
        };

        requestCovers();
    };

    loadCoverArt();

    const loadLinkedDescriptions = function() {
        $('.js-description-links[data-description-span]').each(function() {
            const $el = $(this);
            const spanId = $el.attr('data-description-span');
            if (!spanId) {
                return;
            }

            $el.attr('aria-busy', 'true');
            $.getJSON('/api/spans/' + spanId + '/linked-description')
                .done(function(data) {
                    if (data && data.html) {
                        $el.html(data.html);
                    }
                })
                .always(function() {
                    $el.attr('aria-busy', 'false');
                });
        });
    };

    loadLinkedDescriptions();

    const loadUserConnection = function() {
        const $card = $('.js-user-connection[data-user-connection-span]').first();
        if (!$card.length) {
            return;
        }

        const spanId = $card.attr('data-user-connection-span');
        $card.attr('aria-busy', 'true');

        $.getJSON('/api/spans/' + spanId + '/user-connection')
            .done(function(data) {
                const steps = (data && data.steps) || [];
                if (!steps.length) {
                    $card.remove();
                    return;
                }

                const $steps = $card.find('.js-user-connection-steps').empty();
                steps.forEach(function(step, index) {
                    const $row = $('<div>', { class: 'mb-2' });
                    $row.append($('<a>', {
                        href: step.from.url,
                        class: 'text-decoration-none fw-bold',
                        text: step.from.name
                    }));
                    $row.append(document.createTextNode(' ' + step.predicate + ' '));
                    $row.append($('<a>', {
                        href: step.to.url,
                        class: 'text-decoration-none fw-bold',
                        text: step.to.name
                    }));
                    if (index < steps.length - 1) {
                        $row.append($('<i>', { class: 'bi bi-arrow-down text-muted ms-2' }));
                    }
                    $steps.append($row);
                });

                $card
                    .removeClass('d-none')
                    .attr('aria-hidden', 'false')
                    .attr('aria-busy', 'false');
            })
            .fail(function() {
                $card.remove();
            });
    };

    loadUserConnection();

    const $deleteBtn = $('#delete-span-btn');
    if ($deleteBtn.length) {
        $deleteBtn.on('click', function(event) {
            event.preventDefault();
            if (confirm('Are you sure you want to delete this span?')) {
                $('#delete-span-form').trigger('submit');
            }
        });
    }

    const $spanContainer = $('[data-span-id][data-span-slug]').first();
    if (!$spanContainer.length) {
        return;
    }

    const currentSpanId = $spanContainer.data('span-id');
    const currentSpanSlug = $spanContainer.data('span-slug');
    const connectionCache = {};
    let activeWrapper = null;

    const escapeHtml = (value) => $('<div>').text(value || '').html();

    const getSpanSlugFromHref = (href) => {
        if (!href) {
            return null;
        }
        try {
            const url = new URL(href, window.location.origin);
            let path = url.pathname.replace(/\/+$/, '');
            const match = path.match(/^\/spans\/([^/]+)$/);
            return match ? decodeURIComponent(match[1]) : null;
        } catch (error) {
            return null;
        }
    };

    const fetchConnection = (targetSlug) => {
        if (!connectionCache[targetSlug]) {
            connectionCache[targetSlug] = $.getJSON(`/api/spans/${currentSpanId}/connection-to/${encodeURIComponent(targetSlug)}`);
        }
        return connectionCache[targetSlug];
    };

    const buildConnectionIcon = (data) => {
        const connectionUrl = data?.connection_span?.url;
        const connectionName = data?.connection_span?.name || 'View connection';

        if (!connectionUrl) {
            return null;
        }

        return $(`
            <a href="${connectionUrl}" class="text-decoration-none connection-span-link" title="${escapeHtml(connectionName)}">
                <i class="bi bi-diagram-2"></i>
            </a>
        `);
    };

    $('a[href]').each(function() {
        const $link = $(this);
        const targetSlug = getSpanSlugFromHref($link.attr('href'));
        if (!targetSlug || targetSlug === currentSpanSlug) {
            return;
        }

        $link.data('span-target-slug', targetSlug);

        if ($link.data('span-connection-bound')) {
            return;
        }

        $link.data('span-connection-bound', true);
        const ensureWrapper = () => {
            const $existingWrapper = $link.closest('.span-connection-wrapper');
            if ($existingWrapper.length) {
                return $existingWrapper;
            }

            const wrapperId = `span-connection-${currentSpanId}-${targetSlug}-${Math.random().toString(36).slice(2, 9)}`;
            const $wrapper = $(`<span class="span-connection-wrapper" id="${wrapperId}"></span>`);
            $wrapper.insertBefore($link);
            $wrapper.append($link);
            return $wrapper;
        };

        const showWrapperIcon = ($wrapper, $iconLink) => {
            if (activeWrapper && activeWrapper[0] !== $wrapper[0]) {
                activeWrapper.find('.connection-span-link').removeClass('is-visible');
            }
            $iconLink.addClass('is-visible');
            activeWrapper = $wrapper;
            console.log('Span connection icon show', {
                targetSlug,
                linkHref: $link.attr('href'),
            });
        };

        const hideWrapperIcon = ($wrapper) => {
            $wrapper.find('.connection-span-link').removeClass('is-visible').remove();
            if (activeWrapper && activeWrapper[0] === $wrapper[0]) {
                activeWrapper = null;
            }
            $link.removeData('span-connection-ready');
            console.log('Span connection icon hide', {
                targetSlug,
                linkHref: $link.attr('href'),
            });
        };

        const bindWrapperHover = ($wrapper) => {
            if ($wrapper.data('span-connection-hover')) {
                return;
            }
            $wrapper.data('span-connection-hover', true);
            let wrapperShowTimeout = null;
            
            $wrapper.on('mouseenter.spanConnection focusin.spanConnection', () => {
                const $icon = $wrapper.find('.connection-span-link').first();
                if ($icon.length) {
                    // Clear any existing timeout
                    if (wrapperShowTimeout) {
                        clearTimeout(wrapperShowTimeout);
                    }
                    // Delay showing the icon by 1 second
                    wrapperShowTimeout = setTimeout(() => {
                        showWrapperIcon($wrapper, $icon);
                        wrapperShowTimeout = null;
                    }, 1000);
                }
            });
            
            $wrapper.on('mouseleave.spanConnection focusout.spanConnection', () => {
                // Cancel the timeout if mouse leaves before delay completes
                if (wrapperShowTimeout) {
                    clearTimeout(wrapperShowTimeout);
                    wrapperShowTimeout = null;
                }
                hideWrapperIcon($wrapper);
            });
        };

        const initIcon = () => {
            if ($link.data('span-connection-loading') || $link.data('span-connection-ready')) {
                return;
            }

            $link.data('span-connection-loading', true);
            fetchConnection(targetSlug)
                .done((response) => {
                    if (!response?.success || !response?.connection_span) {
                        return;
                    }

                    const $iconLink = buildConnectionIcon(response);
                    if (!$iconLink) {
                        return;
                    }

                    const $wrapper = ensureWrapper();
                    if ($wrapper.find('.connection-span-link').length === 0) {
                        $iconLink.insertBefore($link);
                    }
                    $link.data('span-connection-ready', true);
                    bindWrapperHover($wrapper);
                    // Don't show immediately - wait for hover delay
                })
                .always(() => {
                    $link.data('span-connection-loading', false);
                });
        };

        let linkHoverTimeout = null;
        
        $link.on('mouseenter.spanConnection focusin.spanConnection', () => {
            // Clear any existing timeout
            if (linkHoverTimeout) {
                clearTimeout(linkHoverTimeout);
            }
            // Delay calling initIcon by 1 second
            linkHoverTimeout = setTimeout(() => {
                initIcon();
                linkHoverTimeout = null;
            }, 1000);
        });
        
        $link.on('mouseleave.spanConnection focusout.spanConnection', () => {
            // Cancel the timeout if mouse leaves before delay completes
            if (linkHoverTimeout) {
                clearTimeout(linkHoverTimeout);
                linkHoverTimeout = null;
            }
        });
    });

    $('.temporal-relations-tabs').each(function() {
        const $tabs = $(this);
        const $predicateTabs = $tabs.next('.temporal-relations-predicate-tabs');
        const $list = ($predicateTabs.length ? $predicateTabs : $tabs).next('.temporal-relations-list');
        if (!$list.length) {
            return;
        }

        let currentRelation = null;
        let currentPredicate = 'all';

        const getAvailablePredicates = (relation) => {
            const predicates = new Set();
            $list.children(`[data-relation="${relation}"]`).each(function() {
                const predicate = $(this).data('predicate');
                if (predicate) {
                    predicates.add(predicate);
                }
            });
            return Array.from(predicates).sort();
        };

        const updatePredicateTabs = (relation) => {
            if (!$predicateTabs.length) {
                return;
            }

            const availablePredicates = getAvailablePredicates(relation);
            const $allButton = $predicateTabs.find('[data-predicate-filter="all"]').parent();
            
            // Show/hide predicate tabs based on availability
            $predicateTabs.find('[data-predicate-filter]').each(function() {
                const $button = $(this);
                const predicate = $button.data('predicate-filter');
                const $item = $button.parent();
                
                if (predicate === 'all') {
                    // Always show "all" tab if there are multiple predicates
                    $item.toggle(availablePredicates.length > 1);
                } else {
                    $item.toggle(availablePredicates.includes(predicate));
                }
            });

            // If current predicate is not available for this relation, switch to "all"
            if (currentPredicate !== 'all' && !availablePredicates.includes(currentPredicate)) {
                currentPredicate = 'all';
                $predicateTabs.find('[data-predicate-filter="all"]').trigger('click');
            }
        };

        const applyFilters = () => {
            $list.children('[data-relation]').each(function() {
                const $item = $(this);
                const matchesRelation = !currentRelation || $item.data('relation') === currentRelation;
                const matchesPredicate = currentPredicate === 'all' || $item.data('predicate') === currentPredicate;
                $item.toggle(matchesRelation && matchesPredicate);
            });
        };

        $tabs.on('click', '[data-relation-filter]', function() {
            const $button = $(this);
            currentRelation = $button.data('relation-filter');
            $tabs.find('.nav-link').removeClass('active');
            $button.addClass('active');
            updatePredicateTabs(currentRelation);
            applyFilters();
        });

        if ($predicateTabs.length) {
            $predicateTabs.on('click', '[data-predicate-filter]', function() {
                const $button = $(this);
                currentPredicate = $button.data('predicate-filter');
                $predicateTabs.find('.nav-link').removeClass('active');
                $button.addClass('active');
                applyFilters();
            });
        }

        const $initial = $tabs.find('.nav-link.active').first();
        if ($initial.length) {
            currentRelation = $initial.data('relation-filter');
            updatePredicateTabs(currentRelation);
            applyFilters();
        }
    });
});