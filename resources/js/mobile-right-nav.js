import UserSwitcher from './shared/user-switcher.js';

// Only run if admin user (server-side blade check will ensure this is only included for admins)
document.addEventListener('DOMContentLoaded', function() {
    // Initialize shared user switcher for mobile
    const mobileUserSwitcher = new UserSwitcher('mobileUserSwitcherList');

    // Load user switcher when offcanvas is shown
    const $mobileRightNav = typeof $ !== 'undefined' ? $('#mobileRightNav') : null;
    if ($mobileRightNav && $mobileRightNav.length) {
        $mobileRightNav.on('shown.bs.offcanvas', function() {
            mobileUserSwitcher.loadUserList();
        });
    } else {
        // Fallback for vanilla JS if jQuery is not available
        const mobileRightNav = document.getElementById('mobileRightNav');
        if (mobileRightNav) {
            mobileRightNav.addEventListener('shown.bs.offcanvas', function() {
                mobileUserSwitcher.loadUserList();
            });
        }
    }
}); 