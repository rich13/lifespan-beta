@props(['span' => null])

<!-- Mobile Right Navigation Offcanvas -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="mobileRightNav" aria-labelledby="mobileRightNavLabel">
    <div class="offcanvas-header bg-light border-bottom">
        <h5 class="offcanvas-title" id="mobileRightNavLabel">
            <i class="bi bi-gear me-2"></i>Quick Actions
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body p-0">
        <!-- Mobile Global Search -->
        <div class="p-3 border-bottom">
            <h6 class="mb-3">
                <i class="bi bi-search me-2"></i>Search
            </h6>
            <div class="global-search-container position-relative w-100">
                <div class="d-flex align-items-center position-relative">
                    <i class="bi bi-search position-absolute ms-2 text-muted z-index-1" style="top: 50%; transform: translateY(-50%); font-size: 0.875rem;"></i>
                    <input type="text" id="mobile-global-search" class="form-control ps-4" placeholder="Search spans..." autocomplete="off">
                </div>
            </div>
        </div>

        <!-- Mobile Page Filters -->
        @if(trim($__env->yieldContent('page_filters')))
            <div class="p-3 border-bottom">
                <h6 class="mb-3">
                    <i class="bi bi-funnel me-2"></i>Filters
                </h6>
                <div class="d-grid gap-2">
                    @yield('page_filters')
                </div>
            </div>
        @endif

        <!-- Mobile Page Tools -->
        @if(trim($__env->yieldContent('page_tools')))
            <div class="p-3 border-bottom">
                <h6 class="mb-3">
                    <i class="bi bi-tools me-2"></i>Tools
                </h6>
                <div class="d-grid gap-2">
                    @yield('page_tools')
                </div>
            </div>
        @endif

        <!-- Mobile Action Buttons -->
        <div class="p-3 border-bottom">
            <h6 class="mb-3">
                <i class="bi bi-plus-circle me-2"></i>Actions
            </h6>
            <x-shared.action-buttons :span="$span" variant="mobile" />
        </div>

        <!-- Mobile User Profile -->
        @auth
            <div class="p-3 border-bottom">
                <h6 class="mb-3">
                    <i class="bi bi-person-circle me-2"></i>Profile
                </h6>
                <div class="d-grid gap-2">
                    <x-shared.user-profile-info variant="mobile" />
                    <x-shared.user-switcher variant="mobile" containerId="mobileUserSwitcherList" />
                </div>
            </div>
        @endauth
    </div>
</div>

<style>
    /* Mobile right nav scrolling */
    #mobileRightNav .offcanvas-body {
        overflow-y: auto;
        max-height: calc(100vh - 60px); /* Account for header height */
    }
    
    /* Mobile search dropdown positioning */
    #mobileRightNav .global-search-container {
        position: relative;
        width: 100%;
    }
    
    #mobileRightNav #mobile-global-search {
        width: 100%;
        font-size: 1rem;
        padding: 0.5rem 0.75rem;
        border-radius: 0.375rem;
        height: calc(1.5em + 1rem + 2px);
        line-height: 1.5;
    }
    
    #mobileRightNav #mobile-global-search-dropdown {
        position: absolute !important;
        top: 100% !important;
        left: 0 !important;
        right: 0 !important;
        z-index: 1050;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        border: 1px solid rgba(0, 0, 0, 0.125);
        max-height: 300px;
        overflow-y: auto;
    }
    
    /* Ensure dropdown items are properly styled */
    #mobileRightNav #mobile-global-search-dropdown .dropdown-item {
        padding: 0.75rem 1rem;
        border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        cursor: pointer;
        transition: all 0.15s ease;
    }
    
    #mobileRightNav #mobile-global-search-dropdown .dropdown-item:last-child {
        border-bottom: none;
    }
    
    #mobileRightNav #mobile-global-search-dropdown .dropdown-item:hover {
        background-color: #f8f9fa;
        color: #495057;
    }
    
    #mobileRightNav #mobile-global-search-dropdown .dropdown-item.active {
        background-color: #0d6efd;
        color: white !important;
    }
    
    #mobileRightNav #mobile-global-search-dropdown .dropdown-item.active .fw-medium {
        color: white !important;
        font-weight: 600;
    }
    
    #mobileRightNav #mobile-global-search-dropdown .dropdown-item.active small {
        color: rgba(255, 255, 255, 0.8) !important;
    }
    
    #mobileRightNav #mobile-global-search-dropdown .dropdown-item.active i {
        color: rgba(255, 255, 255, 0.9) !important;
    }
    
    /* Mobile-specific styling for filters and tools */
    #mobileRightNav .btn-group {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }
    
    #mobileRightNav .btn-group .btn {
        border-radius: 0.375rem !important;
        margin: 0 !important;
    }
    
    #mobileRightNav .btn-group-vertical {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }
    
    #mobileRightNav .btn-group-vertical .btn {
        border-radius: 0.375rem !important;
        margin: 0 !important;
    }
    
    /* Ensure form controls are full width in mobile nav */
    #mobileRightNav .form-control,
    #mobileRightNav .form-select {
        width: 100%;
    }
    
    /* Make sure any custom page filters/tools are mobile-friendly */
    #mobileRightNav .d-grid {
        gap: 0.5rem;
    }
    
    #mobileRightNav .d-grid .btn {
        width: 100%;
        justify-content: flex-start;
        text-align: left;
    }
</style>

<script>
$(document).ready(function() {
    console.log('Mobile right nav script loaded');
    
    const searchInput = $('#mobile-global-search');
    const searchContainer = $('#mobileRightNav .global-search-container');
    
    console.log('Mobile search input found:', searchInput.length);
    console.log('Mobile search container found:', searchContainer.length);
    
    let searchTimeout;
    let selectedIndex = -1;
    let searchResults = [];

    // Get CSRF token from meta tag
    const csrfToken = $('meta[name="csrf-token"]').attr('content');
    console.log('CSRF token:', csrfToken);

    // Create dropdown container
    const dropdown = $('<div class="dropdown-menu w-100 mt-1" id="mobile-global-search-dropdown"></div>');
    searchContainer.append(dropdown);

    // Handle input changes
    searchInput.on('input', function() {
        const query = $(this).val().trim();
        console.log('Mobile search input:', query);
        
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
        const dropdownVisible = $('#mobile-global-search-dropdown').is(':visible');
        
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
                searchInput.blur();
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
        console.log('Performing mobile search for:', query);
        
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
            console.log('Mobile search results:', response);
            // Handle both array response and object with spans property
            const results = Array.isArray(response) ? response : (response.spans || []);
            searchResults = results;
            displayResults(results);
        })
        .fail(function(xhr) {
            console.error('Mobile search failed:', xhr.responseText);
            console.error('Status:', xhr.status);
            hideDropdown();
        });
    }

    // Display search results
    function displayResults(results) {
        const dropdown = $('#mobile-global-search-dropdown');
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
        $('#mobile-global-search-dropdown .dropdown-item').removeClass('active');
        if (selectedIndex >= 0 && selectedIndex < searchResults.length) {
            $(`#mobile-global-search-dropdown .dropdown-item[data-index="${selectedIndex}"]`).addClass('active');
        }
    }

    // Select a result
    function selectResult(result) {
        console.log('Selected mobile search result:', result);
        // Close the offcanvas and navigate to the span
        $('#mobileRightNav').offcanvas('hide');
        setTimeout(() => {
            window.location.href = `/spans/${result.id}`;
        }, 300);
    }

    // Hide dropdown
    function hideDropdown() {
        $('#mobile-global-search-dropdown').hide();
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

    // Load user switcher list for admin users
    @if(Auth::user() && Auth::user()->is_admin)
        // Initialize shared user switcher for mobile
        const mobileUserSwitcher = new UserSwitcher('mobileUserSwitcherList');

        // Load user switcher when offcanvas is shown
        $('#mobileRightNav').on('shown.bs.offcanvas', function() {
            mobileUserSwitcher.loadUserList();
        });
    @endif
});
</script> 