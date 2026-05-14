/**
 * Confirm before deleting a time series dataset (admin).
 */
window.jQuery(function ($) {
    $(document).on('submit', '.js-dataset-delete-form', function (e) {
        const name = $(this).data('datasetName') || 'this dataset';
        const message =
            'Delete "' + name + '"? All series and observations will be permanently removed.';
        if (!window.confirm(message)) {
            e.preventDefault();
        }
    });
});
