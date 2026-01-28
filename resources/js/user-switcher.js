/**
 * User Switcher functionality for admin users
 * Using jQuery for consistency with the rest of the codebase
 */
$(document).ready(function() {
    console.log('User switcher script loaded');
    
    const $loadUserSwitcherBtn = $('#loadUserSwitcherBtn');
    const $userSwitcherList = $('#userSwitcherList');
    const $userDropdown = $('#userDropdown').closest('.dropdown');
    
    console.log('Load button found:', $loadUserSwitcherBtn.length > 0);
    console.log('List element found:', $userSwitcherList.length > 0);
    console.log('Routes available:', window.routes);
    
    if ($loadUserSwitcherBtn.length && $userSwitcherList.length) {
        // Position the user list correctly
        $userSwitcherList.appendTo($userDropdown);
        
        // Handle click on the "Switch to User" button
        $loadUserSwitcherBtn.on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            console.log('Load users button clicked');
            
            // Toggle the user list visibility
            if ($userSwitcherList.is(':visible')) {
                $userSwitcherList.slideUp(200);
            } else {
                // Position the list
                const dropdownMenu = $(this).closest('.dropdown-menu');
                const dropdownPos = dropdownMenu.offset();
                const buttonPos = $(this).offset();
                const buttonHeight = $(this).outerHeight();
                
                console.log('Dropdown positions:', {
                    dropdown: dropdownPos,
                    button: buttonPos,
                    buttonHeight: buttonHeight
                });
                
                $userSwitcherList.css({
                    'position': 'absolute',
                    'top': buttonPos.top + buttonHeight + 'px',
                    'left': dropdownPos.left + 'px',
                    'z-index': 1050
                });
                
                $userSwitcherList.slideDown(200);
                // Load users when list is shown
                loadUsers();
            }
        });
        
        // Prevent dropdown from closing when clicking inside the user list
        $userSwitcherList.on('click', function(e) {
            e.stopPropagation();
            console.log('User list clicked, preventing dropdown close');
        });
        
        // Close user list when clicking outside
        $(document).on('click', function(e) {
            if (!$(e.target).closest('#userSwitcherList').length && 
                !$(e.target).closest('#loadUserSwitcherBtn').length) {
                console.log('Click outside user list, closing');
                $userSwitcherList.slideUp(200);
            }
        });
        
        // Close user list when dropdown is hidden
        $userDropdown.on('hide.bs.dropdown', function() {
            console.log('Dropdown hidden, closing user list');
            $userSwitcherList.slideUp(200);
        });
    }
    
    /**
     * Load users from the server
     */
    function loadUsers() {
        console.log('loadUsers function called');
        
        // Check if users are already loaded
        if ($userSwitcherList.attr('data-loaded') === 'true') {
            console.log('Users already loaded, skipping');
            return;
        }
        
        console.log('Fetching users from server:', window.routes.userSwitcher.users);
        
        $.ajax({
            url: window.routes.userSwitcher.users,
            type: 'GET',
            dataType: 'json',
            success: function(users) {
                console.log('Users loaded successfully:', users.length);
                console.log('Users data:', users);
                
                // Clear loading indicator
                $userSwitcherList.empty();
                
                // Add search box
                const $searchBox = $('<div>').addClass('mb-2');
                $searchBox.append(
                    $('<input>', {
                        type: 'text',
                        class: 'form-control form-control-sm',
                        placeholder: 'Search users...',
                        id: 'userSearchInput'
                    })
                );
                $userSwitcherList.append($searchBox);
                
                // Create scrollable container for users
                const $scrollContainer = $('<div>').css({
                    'max-height': '300px',
                    'overflow-y': 'auto'
                });
                
                // Add users
                $.each(users, function(index, user) {
                    console.log('Processing user:', user);
                    
                    // Skip the current user
                    if (user.is_current && !user.is_switch_back) {
                        console.log('Skipping current user:', user.email);
                        return true; // Skip this iteration (continue)
                    }
                    
                    const switchUrl = window.routes.userSwitcher.switch.replace(':userId', user.id);
                    
                    const $form = $('<form>', {
                        method: 'POST',
                        action: switchUrl,
                        class: 'user-switch-form'
                    });
                    
                    // Add CSRF token
                    const csrfToken = $('meta[name="csrf-token"]').attr('content');
                    
                    $form.append(
                        $('<input>', {
                            type: 'hidden',
                            name: '_token',
                            value: csrfToken
                        })
                    );
                    
                    // Create button with appropriate styling
                    let buttonClass = 'btn btn-sm w-100 text-start';
                    let buttonHtml = '';
                    
                    // Special styling for "Switch back" option
                    if (user.is_switch_back) {
                        buttonClass += ' btn-light-primary';
                        buttonHtml = '<i class="bi bi-arrow-return-left me-2"></i>' + user.email;
                    } else {
                        buttonClass += ' btn-light-secondary';
                        buttonHtml = '<i class="bi bi-person-fill me-2"></i>' + user.email;
                        
                        // Add indicators
                        if (user.is_current) {
                            buttonHtml += ' <span class="badge bg-info ms-1">Current</span>';
                            buttonClass += ' disabled';
                        }
                        
                        if (user.is_admin_user) {
                            buttonHtml += ' <span class="badge bg-warning ms-1">Admin</span>';
                        }
                    }
                    
                    $form.append(
                        $('<button>', {
                            type: 'submit',
                            class: buttonClass,
                            html: buttonHtml,
                            'data-email': user.email.toLowerCase() // For search functionality
                        })
                    );
                    
                    $scrollContainer.append($form);
                });
                
                $userSwitcherList.append($scrollContainer);
                
                // Add search functionality
                $('#userSearchInput').on('keyup', function() {
                    const searchTerm = $(this).val().toLowerCase();
                    
                    $('.user-switch-form button').each(function() {
                        const userEmail = $(this).data('email');
                        const matches = userEmail && userEmail.includes(searchTerm);
                        $(this).parent().toggle(matches);
                    });
                });
                
                // Mark as loaded
                $userSwitcherList.attr('data-loaded', 'true');
            },
            error: function(xhr, status, error) {
                console.error('Error loading users:', error);
                console.error('Status:', status);
                console.error('Response:', xhr.responseText);
                
                $userSwitcherList.html(
                    $('<div>').addClass('alert alert-danger py-2').text('Error loading users')
                );
            }
        });
    }
}); 