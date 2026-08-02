$(document).ready(function() {
    // Only run this code on the spans edit page
    if (!window.location.pathname.includes('/spans/') || !window.location.pathname.includes('/edit')) {
        return;
    }

    // Reload the form to show type-specific metadata fields (does not save until Submit).
    $('#type_id').on('change', function() {
        const $select = $(this);
        const newType = $select.val();
        const savedType = $select.data('saved-type');

        if (newType === savedType) {
            const currentUrl = new URL(window.location.href);
            currentUrl.searchParams.delete('type_id');
            window.location.href = currentUrl.toString();
            return;
        }

        if (!window.confirm(
            'Changing the type will reload the form to show fields for the new type. ' +
            'Other unsaved edits on this page will be lost. Continue?'
        )) {
            $select.val(savedType);
            return;
        }

        const currentUrl = new URL(window.location.href);
        currentUrl.searchParams.set('type_id', newType);
        window.location.href = currentUrl.toString();
    });
}); 