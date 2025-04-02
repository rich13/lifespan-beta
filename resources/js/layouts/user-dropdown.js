// Custom dropdown implementation
document.addEventListener('DOMContentLoaded', function() {
    // Check if jQuery is available
    if (typeof $ !== 'undefined') {
        // jQuery implementation
        const $toggle = $('#customUserDropdownToggle');
        const $menu = $('#customUserDropdownMenu');
        
        if ($toggle.length && $menu.length) {
            $toggle.on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $menu.toggleClass('d-none');
                
                // Load users when dropdown is shown (admin only)
                const $userList = $('#userSwitcherList');
                if (!$menu.hasClass('d-none') && $userList.length && !$userList.data('loaded')) {
                    loadUserList();
                }
            });
            
            // Close when clicking outside
            $(document).on('click', function(e) {
                if (!$toggle.is(e.target) && $toggle.has(e.target).length === 0 && 
                    !$menu.is(e.target) && $menu.has(e.target).length === 0) {
                    $menu.addClass('d-none');
                }
            });
        }
    } else {
        // Vanilla JS implementation (fallback)
        const toggle = document.getElementById('customUserDropdownToggle');
        const menu = document.getElementById('customUserDropdownMenu');
        
        if (toggle && menu) {
            toggle.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                menu.classList.toggle('d-none');
                
                // Load users when dropdown is shown (admin only)
                const userList = document.getElementById('userSwitcherList');
                if (!menu.classList.contains('d-none') && userList && !userList.hasAttribute('data-loaded')) {
                    loadUserList();
                }
            });
            
            // Close dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (!menu.contains(e.target) && !toggle.contains(e.target)) {
                    menu.classList.add('d-none');
                }
            });
        }
    }
    
    // Load user list for admin user switcher
    function loadUserList() {
        // Check if jQuery is available
        if (typeof $ !== 'undefined') {
            const $userList = $('#userSwitcherList');
            if ($userList.data('loaded')) return;
            
            $.ajax({
                url: window.routes.userSwitcher.users,
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    $userList.empty();
                    const users = Array.isArray(response) ? response : [];
                    
                    if (users.length === 0) {
                        $userList.html('<div class="px-2 py-1 text-center"><small>No users found</small></div>');
                        return;
                    }
                    
                    // Add users
                    $.each(users, function(index, user) {
                        // Skip the current user
                        if (user.is_current && !user.is_switch_back) {
                            return true; // Skip this iteration (continue)
                        }
                        
                        const $form = $('<form>', {
                            method: 'POST',
                            action: window.routes.userSwitcher.switch.replace(':userId', user.id),
                            class: 'user-switch-form'
                        });
                        
                        // Add CSRF token
                        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                        $form.append(
                            $('<input>', {
                                type: 'hidden',
                                name: '_token',
                                value: csrfToken
                            })
                        );
                        
                        // Create button with appropriate styling
                        let buttonClass = 'd-block w-100 text-start p-2 border-0 bg-transparent rounded hover-bg-light';
                        let buttonHtml = '';
                        
                        // Special styling for "Switch back" option
                        if (user.is_switch_back) {
                            buttonClass += ' text-primary';
                            buttonHtml = '<i class="bi bi-arrow-return-left me-2"></i>' + user.email;
                        } else {
                            buttonClass += ' text-dark';
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
                                html: buttonHtml
                            })
                        );
                        
                        $userList.append($form);
                    });
                    
                    // Mark as loaded
                    $userList.data('loaded', true);
                },
                error: function(xhr, status, error) {
                    $userList.html('<div class="px-2 py-1 text-center text-danger"><small>Error loading users</small></div>');
                }
            });
        } else {
            // Vanilla JS implementation (fallback)
            const userList = document.getElementById('userSwitcherList');
            if (!userList) return;
            
            userList.setAttribute('data-loaded', 'true');
            userList.innerHTML = '<div class="text-center py-2"><div class="spinner-border spinner-border-sm text-primary" role="status"></div> Loading users...</div>';
            
            fetch(window.routes.userSwitcher.users)
                .then(response => response.json())
                .then(data => {
                    if (Array.isArray(data) && data.length > 0) {
                        userList.innerHTML = '';
                        data.forEach(user => {
                            // Skip the current user
                            if (user.is_current && !user.is_switch_back) {
                                return;
                            }
                            
                            const form = document.createElement('form');
                            form.method = 'POST';
                            form.action = window.routes.userSwitcher.switch.replace(':userId', user.id);
                            form.className = 'user-switch-form';
                            
                            // Add CSRF token
                            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                            const csrfInput = document.createElement('input');
                            csrfInput.type = 'hidden';
                            csrfInput.name = '_token';
                            csrfInput.value = csrfToken;
                            form.appendChild(csrfInput);
                            
                            // Create button with appropriate styling
                            const button = document.createElement('button');
                            button.type = 'submit';
                            let buttonClass = 'd-block w-100 text-start p-2 border-0 bg-transparent rounded hover-bg-light';
                            
                            // Special styling for "Switch back" option
                            if (user.is_switch_back) {
                                buttonClass += ' text-primary';
                                button.innerHTML = `<i class="bi bi-arrow-return-left me-2"></i>${user.email}`;
                            } else {
                                buttonClass += ' text-dark';
                                let buttonHtml = `<i class="bi bi-person-fill me-2"></i>${user.email}`;
                                
                                // Add indicators
                                if (user.is_current) {
                                    buttonHtml += ' <span class="badge bg-info ms-1">Current</span>';
                                    buttonClass += ' disabled';
                                }
                                
                                if (user.is_admin_user) {
                                    buttonHtml += ' <span class="badge bg-warning ms-1">Admin</span>';
                                }
                                
                                button.innerHTML = buttonHtml;
                            }
                            
                            button.className = buttonClass;
                            form.appendChild(button);
                            userList.appendChild(form);
                        });
                    } else {
                        userList.innerHTML = '<div class="px-2 py-1 text-center"><small>No users found</small></div>';
                    }
                })
                .catch(error => {
                    userList.innerHTML = '<div class="px-2 py-1 text-center text-danger"><small>Error loading users</small></div>';
                    console.error('Error loading users:', error);
                });
        }
    }
    
    // Switch back to admin
    const switchBackBtn = document.getElementById('switchBackToAdmin');
    if (switchBackBtn) {
        switchBackBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = window.routes.userSwitcher.switchBack;
            
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = '_token';
            csrfInput.value = csrfToken;
            
            form.appendChild(csrfInput);
            document.body.appendChild(form);
            form.submit();
        });
    }
}); 