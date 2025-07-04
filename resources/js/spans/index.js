document.addEventListener('DOMContentLoaded', function() {
    // Only run this code on the spans index page
    if (!window.location.pathname.match(/^\/spans\/?$/)) {
        return;
    }

    // Auto-submit form when any filter button is clicked
    const filterCheckboxes = document.querySelectorAll('#type-filter-form .btn-check');
    if (filterCheckboxes.length > 0) {
        filterCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                // If a main type is unchecked, uncheck all its subtype filters
                if (!this.checked && this.name === 'types') {
                    const typeId = this.value;
                    const subtypeCheckboxes = document.querySelectorAll(`input[name="${typeId}_subtype"]`);
                    subtypeCheckboxes.forEach(subtypeCheckbox => {
                        subtypeCheckbox.checked = false;
                    });
                }
                document.getElementById('type-filter-form').submit();
            });
        });
    }
}); 