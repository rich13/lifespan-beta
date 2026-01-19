$(document).ready(function() {
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
                <i class="bi bi-link-45deg"></i>
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
            $wrapper.on('mouseenter.spanConnection focusin.spanConnection', () => {
                const $icon = $wrapper.find('.connection-span-link').first();
                if ($icon.length) {
                    showWrapperIcon($wrapper, $icon);
                }
            });
            $wrapper.on('mouseleave.spanConnection focusout.spanConnection', () => hideWrapperIcon($wrapper));
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
                    showWrapperIcon($wrapper, $iconLink);
                })
                .always(() => {
                    $link.data('span-connection-loading', false);
                });
        };

        $link.on('mouseenter.spanConnection focusin.spanConnection', initIcon);
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