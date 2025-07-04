<!-- Global Search Component -->
<div class="global-search-container position-relative me-3" style="width: 120px;">
    <div class="d-flex align-items-center position-relative">
        <i class="bi bi-search position-absolute ms-2 text-muted z-index-1" style="top: 50%; transform: translateY(-50%); font-size: 0.875rem;"></i>
        <input type="text" id="global-search" class="form-control form-control-sm ps-4" placeholder="Search..." autocomplete="off">
    </div>
</div>

<style>
    /* Global search dropdown positioning */
    .global-search-container {
        position: relative;
        min-width: 120px;
        max-width: 400px;
        transition: width 0.3s ease;
    }
    
    .global-search-container:focus-within {
        width: 350px !important;
    }
    
    #global-search {
        width: 100%;
        min-width: 120px;
        transition: all 0.3s ease;
        /* Match button group dimensions */
        font-size: 0.875rem;
        padding: 0.25rem 0.5rem;
        border-radius: 0.375rem;
        height: calc(1.5em + 0.5rem + 2px);
        line-height: 1.5;
    }
    
    #global-search:focus {
        box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
        border-color: #0d6efd;
    }
    
    #global-search-dropdown {
        position: absolute !important;
        top: 100% !important;
        left: 0 !important;
        right: auto !important;
        min-width: 300px;
        max-width: 400px;
        z-index: 1050;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        border: 1px solid rgba(0, 0, 0, 0.125);
        /* Prevent overflow */
        max-width: calc(100vw - 2rem);
    }
    
    /* Responsive adjustments for search dropdown */
    @media (max-width: 768px) {
        .global-search-container {
            min-width: 150px;
            max-width: 300px;
        }
        
        .global-search-container:focus-within {
            width: 280px !important;
        }
        
        #global-search {
            min-width: 150px;
        }
        
        #global-search-dropdown {
            right: 0 !important;
            left: auto !important;
            min-width: auto;
            max-width: none;
            width: 100%;
        }
    }
    
    /* Additional responsive adjustments for very small screens */
    @media (max-width: 576px) {
        .global-search-container {
            min-width: 120px;
            max-width: 250px;
        }
        
        .global-search-container:focus-within {
            width: 220px !important;
        }
        
        #global-search {
            min-width: 120px;
        }
    }
    
    /* Ensure dropdown items are properly styled */
    #global-search-dropdown .dropdown-item {
        padding: 0.5rem 1rem;
        border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        cursor: pointer;
        transition: all 0.15s ease;
    }
    
    #global-search-dropdown .dropdown-item:last-child {
        border-bottom: none;
    }
    
    #global-search-dropdown .dropdown-item:hover {
        background-color: #f8f9fa;
        color: #495057;
    }
    
    #global-search-dropdown .dropdown-item.active {
        background-color: #0d6efd;
        color: white !important;
    }
    
    #global-search-dropdown .dropdown-item.active .fw-medium {
        color: white !important;
        font-weight: 600;
    }
    
    #global-search-dropdown .dropdown-item.active small {
        color: rgba(255, 255, 255, 0.8) !important;
    }
    
    #global-search-dropdown .dropdown-item.active i {
        color: rgba(255, 255, 255, 0.9) !important;
    }
    
    /* Keyboard shortcut hint */
    .search-shortcut-hint {
        position: absolute;
        right: 6px;
        top: 50%;
        transform: translateY(-50%);
        font-size: 0.75rem;
        color: #6c757d;
        background: rgba(255, 255, 255, 0.9);
        padding: 1px 4px;
        border-radius: 2px;
        border: 1px solid #dee2e6;
        opacity: 0.7;
        transition: opacity 0.3s ease;
        pointer-events: none;
    }
    
    .global-search-container:focus-within .search-shortcut-hint {
        opacity: 0;
    }
</style>

<script>
$(document).ready(function() {
    console.log('Global search script loaded');
    
    const searchInput = $('#global-search');
    const searchContainer = $('.global-search-container');
    
    console.log('Global search input found:', searchInput.length);
    console.log('Global search container found:', searchContainer.length);
    
    let searchTimeout;
    let selectedIndex = -1;
    let searchResults = [];

    // Get CSRF token from meta tag
    const csrfToken = $('meta[name="csrf-token"]').attr('content');
    console.log('CSRF token:', csrfToken);

    // Create dropdown container
    const dropdown = $('<div class="dropdown-menu w-100 mt-1" id="global-search-dropdown"></div>');
    searchContainer.append(dropdown);
    
    // Add keyboard shortcut hint
    const shortcutHint = $('<div class="search-shortcut-hint">⌘F</div>');
    searchContainer.append(shortcutHint);

    // Global keyboard shortcut (Cmd+F or Ctrl+F)
    $(document).on('keydown', function(e) {
        // Check for Cmd+F (Mac) or Ctrl+F (Windows/Linux)
        if ((e.metaKey || e.ctrlKey) && e.key === 'f') {
            e.preventDefault(); // Prevent browser's find functionality
            searchInput.focus();
            searchInput.select(); // Select any existing text
        }
    });

    // Handle input changes
    searchInput.on('input', function() {
        const query = $(this).val().trim();
        console.log('Global search input:', query);
        
        // Clear previous timeout
        clearTimeout(searchTimeout);
        
        // Hide dropdown if query is empty
        if (query.length === 0) {
            hideDropdown();
            return;
        }

        // Debounce search requests
        searchTimeout = setTimeout(() => {
            performSearch(query);
        }, 300);
    });

    // Handle keyboard navigation
    searchInput.on('keydown', function(e) {
        const dropdownVisible = $('#global-search-dropdown').is(':visible');
        
        switch(e.key) {
            case 'ArrowDown':
                e.preventDefault();
                if (dropdownVisible && searchResults.length > 0) {
                    selectNext();
                }
                break;
            case 'ArrowUp':
                e.preventDefault();
                if (dropdownVisible && searchResults.length > 0) {
                    selectPrevious();
                }
                break;
            case 'Enter':
                e.preventDefault();
                if (dropdownVisible && selectedIndex >= 0 && selectedIndex < searchResults.length) {
                    selectResult(searchResults[selectedIndex]);
                } else if (searchInput.val().trim()) {
                    // If no dropdown is visible but there's text, perform a search
                    performSearch(searchInput.val().trim());
                }
                break;
            case 'Escape':
                e.preventDefault();
                hideDropdown();
                searchInput.blur(); // Remove focus to collapse the search
                break;
        }
    });

    // Handle clicks outside dropdown
    $(document).on('click', function(e) {
        if (!searchContainer.is(e.target) && searchContainer.has(e.target).length === 0) {
            hideDropdown();
        }
    });

    // Perform search
    function performSearch(query) {
        console.log('Performing global search for:', query);
        
        $.ajax({
            url: '/spans/search',
            method: 'GET',
            data: { q: query },
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            }
        })
        .done(function(response) {
            console.log('Global search results:', response);
            // Handle both array response and object with spans property
            const results = Array.isArray(response) ? response : (response.spans || []);
            searchResults = results;
            displayResults(results);
        })
        .fail(function(xhr) {
            console.error('Global search failed:', xhr.responseText);
            console.error('Status:', xhr.status);
            hideDropdown();
        });
    }

    // Display search results
    function displayResults(results) {
        const dropdown = $('#global-search-dropdown');
        dropdown.empty();

        if (results.length === 0) {
            dropdown.append('<div class="dropdown-item text-muted">No results found</div>');
        } else {
            results.forEach((result, index) => {
                const item = $(`
                    <div class="dropdown-item d-flex align-items-center gap-2" data-index="${index}">
                        <i class="bi bi-${getTypeIcon(result.type_id)} text-muted"></i>
                        <div class="flex-grow-1">
                            <div class="fw-medium">${result.name}</div>
                            <small class="text-muted">${result.type_name}</small>
                        </div>
                    </div>
                `);
                
                item.on('click', function() {
                    selectResult(result);
                });
                
                item.on('mouseenter', function() {
                    selectedIndex = index;
                    updateSelection();
                });
                
                dropdown.append(item);
            });
        }

        dropdown.show();
        selectedIndex = -1;
    }

    // Select next item
    function selectNext() {
        if (searchResults.length === 0) return;
        
        if (selectedIndex < searchResults.length - 1) {
            selectedIndex++;
        } else {
            // Wrap to first item
            selectedIndex = 0;
        }
        updateSelection();
    }

    // Select previous item
    function selectPrevious() {
        if (searchResults.length === 0) return;
        
        if (selectedIndex > 0) {
            selectedIndex--;
        } else {
            // Wrap to last item
            selectedIndex = searchResults.length - 1;
        }
        updateSelection();
    }

    // Update visual selection
    function updateSelection() {
        $('#global-search-dropdown .dropdown-item').removeClass('active');
        if (selectedIndex >= 0 && selectedIndex < searchResults.length) {
            $(`#global-search-dropdown .dropdown-item[data-index="${selectedIndex}"]`).addClass('active');
        }
    }

    // Select a result
    function selectResult(result) {
        console.log('Selected global search result:', result);
        // Navigate to the span
        window.location.href = `/spans/${result.id}`;
    }

    // Hide dropdown
    function hideDropdown() {
        $('#global-search-dropdown').hide();
        selectedIndex = -1;
    }

    // Get icon for span type
    function getTypeIcon(typeId) {
        const icons = {
            'person': 'person-fill',
            'organisation': 'building',
            'place': 'geo-alt-fill',
            'event': 'calendar-event-fill',
            'connection': 'link-45deg',
            'band': 'cassette',
            'thing': 'box'
        };
        return icons[typeId] || 'box';
    }
});
</script> 