/**
 * Admin Mode Toggle Module
 * 
 * Handles toggling admin mode on and off for admin users.
 * This allows admins to temporarily disable their admin status to see
 * what normal users see, whilst still maintaining the ability to toggle back.
 * 
 * The toggle state is stored in the session and is cleared on logout.
 */

(function() {
    'use strict';

    const AdminModeToggle = {
        /**
         * Initialize the admin mode toggle
         */
        init() {
            this.setupEventListeners();
            this.updateUIStatus();
        },

        /**
         * Set up event listeners for admin mode toggle buttons
         */
        setupEventListeners() {
            // Desktop dropdown toggle
            document.addEventListener('click', (e) => {
                const toggleBtn = e.target.closest('.admin-mode-toggle-btn');
                if (toggleBtn) {
                    e.preventDefault();
                    const action = toggleBtn.dataset.adminModeAction;
                    if (action === 'toggle') {
                        this.toggleAdminMode(toggleBtn);
                    }
                }
            });
        },

        /**
         * Toggle admin mode on/off
         */
        async toggleAdminMode(button) {
            try {
                // Disable button during request
                button.style.pointerEvents = 'none';
                button.style.opacity = '0.6';

                const response = await fetch('/admin-mode/toggle', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.getCsrfToken(),
                        'Accept': 'application/json',
                    },
                });

                if (!response.ok) {
                    const errorData = await response.json();
                    this.showError(errorData.message || 'Failed to toggle admin mode');
                    button.style.pointerEvents = 'auto';
                    button.style.opacity = '1';
                    return;
                }

                const data = await response.json();

                if (data.success) {
                    this.showSuccess(data.message);
                    
                    // Update UI to reflect new status
                    this.updateUIStatus();
                    
                    // Give user time to see the success message, then reload
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    this.showError(data.message || 'Failed to toggle admin mode');
                    button.style.pointerEvents = 'auto';
                    button.style.opacity = '1';
                }
            } catch (error) {
                console.error('Error toggling admin mode:', error);
                this.showError('An error occurred. Please try again.');
                button.style.pointerEvents = 'auto';
                button.style.opacity = '1';
            }
        },

        /**
         * Update the admin mode toggle UI based on current status
         */
        async updateUIStatus() {
            try {
                const response = await fetch('/admin-mode/status', {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.getCsrfToken(),
                    },
                });

                if (!response.ok) {
                    // User is not admin or not authenticated - silently return
                    return;
                }

                const data = await response.json();

                if (!data.can_toggle) {
                    // User cannot toggle, remove UI elements if they exist
                    document.querySelectorAll('.admin-mode-toggle-btn').forEach(btn => {
                        btn.remove();
                    });
                    return;
                }

                // Update all toggle buttons with current status
                document.querySelectorAll('.admin-mode-toggle-btn').forEach(btn => {
                    const textSpan = btn.querySelector('.admin-mode-toggle-text');
                    if (textSpan) {
                        if (data.admin_mode_disabled) {
                            textSpan.textContent = 'Admin Mode (OFF)';
                            btn.classList.add('text-warning');
                        } else {
                            textSpan.textContent = 'Admin Mode (ON)';
                            btn.classList.remove('text-warning');
                        }
                    }
                });
            } catch (error) {
                console.error('Error fetching admin mode status:', error);
            }
        },

        /**
         * Get CSRF token from meta tag or from DOM
         */
        getCsrfToken() {
            // Try meta tag first
            const metaToken = document.querySelector('meta[name="csrf-token"]');
            if (metaToken) {
                return metaToken.getAttribute('content');
            }
            
            // Fallback - try to get from cookie
            const name = 'XSRF-TOKEN';
            const value = `; ${document.cookie}`;
            const parts = value.split(`; ${name}=`);
            if (parts.length === 2) return parts.pop().split(';').shift();
            
            return '';
        },

        /**
         * Show success message to user
         */
        showSuccess(message) {
            this.showAlert(message, 'success');
        },

        /**
         * Show error message to user
         */
        showError(message) {
            this.showAlert(message, 'danger');
        },

        /**
         * Show alert message
         */
        showAlert(message, type = 'info') {
            // Try to use Bootstrap alert if available
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
            alertDiv.setAttribute('role', 'alert');
            alertDiv.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            `;

            // Find a suitable container - try to insert after header or at top of content
            const headerSection = document.querySelector('.header-section');
            const contentArea = document.querySelector('[data-admin-alerts]') || headerSection;
            
            if (contentArea) {
                contentArea.insertAdjacentElement('afterbegin', alertDiv);
            } else {
                // Fallback to body if no suitable container found
                document.body.insertAdjacentElement('afterbegin', alertDiv);
            }

            // Auto-dismiss after 5 seconds
            setTimeout(() => {
                alertDiv.remove();
            }, 5000);
        }
    };

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            AdminModeToggle.init();
        });
    } else {
        AdminModeToggle.init();
    }

    // Export for use in other modules if needed
    window.AdminModeToggle = AdminModeToggle;
})();
