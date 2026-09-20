$(function () {
    const modalUrl = window.routes && window.routes.newSpanModal;
    let loadPromise = null;

    const openWithNameStub = function (name) {
        return ensureNewSpanModal().then(function () {
            if (window.openNewSpanModalWithName !== openWithNameStub) {
                window.openNewSpanModalWithName(name);
                return;
            }

            $('#newSpanModal').modal('show');
        });
    };

    window.openNewSpanModalWithName = openWithNameStub;

    function modalIsPresent() {
        return $('#newSpanModal').length > 0;
    }

    function injectHtmlWithScripts(html) {
        const $wrap = $('<div></div>').append($.parseHTML(html, document, true));
        const $scripts = $wrap.find('script').add($wrap.children('script')).detach();

        $('body').append($wrap.contents());

        $scripts.each(function () {
            $.globalEval(this.textContent || this.innerText || '');
        });
    }

    function ensureNewSpanModal() {
        if (modalIsPresent()) {
            return $.Deferred().resolve().promise();
        }

        if (loadPromise) {
            return loadPromise;
        }

        if (!modalUrl) {
            return $.Deferred().reject().promise();
        }

        loadPromise = $.ajax({
            url: modalUrl,
            dataType: 'html',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (html) {
            if (typeof html !== 'string' || html.indexOf('id="newSpanModal"') === -1) {
                window.location.href = '/login';
                return $.Deferred().reject().promise();
            }

            injectHtmlWithScripts(html);

            if (!modalIsPresent()) {
                return $.Deferred().reject().promise();
            }
        }).fail(function () {
            loadPromise = null;
        });

        return loadPromise;
    }

    function showNewSpanModal() {
        return ensureNewSpanModal().then(function () {
            $('#newSpanModal').modal('show');
        });
    }

    $(document).on('click', '#new-span-btn, #mobile-new-span-btn', function (e) {
        e.preventDefault();
        showNewSpanModal();
    });
});
