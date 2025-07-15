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
        // Use shared UserSwitcher class
        if (typeof UserSwitcher !== 'undefined') {
            const userSwitcher = new UserSwitcher('userSwitcherList');
            userSwitcher.loadUserList();
        } else {
            console.error('UserSwitcher class not available');
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