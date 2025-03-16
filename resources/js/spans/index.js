document.addEventListener('DOMContentLoaded', function() {
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

    // Live search functionality
    const searchInput = document.getElementById('span-search');
    if (searchInput) {
        let searchTimeout;
        
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(function() {
                const searchValue = searchInput.value.trim();
                const currentUrl = new URL(window.location.href);
                
                // Update or remove search parameter
                if (searchValue) {
                    currentUrl.searchParams.set('search', searchValue);
                } else {
                    currentUrl.searchParams.delete('search');
                }
                
                // Navigate to the new URL
                window.location.href = currentUrl.toString();
            }, 500); // 500ms debounce
        });
        
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const form = document.getElementById('type-filter-form');
                const searchField = document.createElement('input');
                searchField.type = 'hidden';
                searchField.name = 'search';
                searchField.value = this.value;
                form.appendChild(searchField);
                form.submit();
            }
        });
    }

    // Handle clear search
    const clearSearch = document.getElementById('clear-search');
    if (clearSearch) {
        clearSearch.addEventListener('click', function(e) {
            e.preventDefault();
            const currentUrl = new URL(window.location.href);
            currentUrl.searchParams.delete('search');
            window.location.href = currentUrl.toString();
        });
    }
}); 