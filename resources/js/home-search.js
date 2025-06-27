$(document).ready(function() {
    console.log('Home search script loaded');
    
    const searchInput = $('#home-search');
    const searchContainer = $('.home-search-container');
    
    console.log('Search input found:', searchInput.length);
    console.log('Search container found:', searchContainer.length);
    
    let searchTimeout;
    let selectedIndex = -1;
    let searchResults = [];

    // Get CSRF token from meta tag
    const csrfToken = $('meta[name="csrf-token"]').attr('content');
    console.log('CSRF token:', csrfToken);

    // Create dropdown container
    const dropdown = $('<div class="dropdown-menu w-100 mt-1" id="search-dropdown"></div>');
    searchContainer.append(dropdown);

    // Handle input changes
    searchInput.on('input', function() {
        const query = $(this).val().trim();
        console.log('Search input:', query);
        
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
        const dropdownVisible = $('#search-dropdown').is(':visible');
        
        switch(e.key) {
            case 'ArrowDown':
                e.preventDefault();
                if (dropdownVisible) {
                    selectNext();
                }
                break;
            case 'ArrowUp':
                e.preventDefault();
                if (dropdownVisible) {
                    selectPrevious();
                }
                break;
            case 'Enter':
                e.preventDefault();
                if (dropdownVisible && selectedIndex >= 0) {
                    selectResult(searchResults[selectedIndex]);
                }
                break;
            case 'Escape':
                hideDropdown();
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
        console.log('Performing search for:', query);
        
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
            console.log('Search results:', response);
            // Handle both array response and object with spans property
            const results = Array.isArray(response) ? response : (response.spans || []);
            searchResults = results;
            displayResults(results);
        })
        .fail(function(xhr) {
            console.error('Search failed:', xhr.responseText);
            console.error('Status:', xhr.status);
            hideDropdown();
        });
    }

    // Display search results
    function displayResults(results) {
        const dropdown = $('#search-dropdown');
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
        if (selectedIndex < searchResults.length - 1) {
            selectedIndex++;
            updateSelection();
        }
    }

    // Select previous item
    function selectPrevious() {
        if (selectedIndex > 0) {
            selectedIndex--;
            updateSelection();
        }
    }

    // Update visual selection
    function updateSelection() {
        $('#search-dropdown .dropdown-item').removeClass('active');
        if (selectedIndex >= 0) {
            $(`#search-dropdown .dropdown-item[data-index="${selectedIndex}"]`).addClass('active');
        }
    }

    // Select a result
    function selectResult(result) {
        console.log('Selected result:', result);
        // Navigate to the span
        window.location.href = `/spans/${result.id}`;
    }

    // Hide dropdown
    function hideDropdown() {
        $('#search-dropdown').hide();
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