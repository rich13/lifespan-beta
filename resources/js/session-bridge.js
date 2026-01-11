/**
 * Session Bridge Module
 * 
 * Handles session restoration when the application redeploys and the server session is lost.
 * This uses a "bridge token" (a long-lived API token) stored in localStorage to restore
 * the user's session seamlessly without requiring re-authentication.
 * 
 * Flow:
 * 1. On login/registration, a bridge token is issued and stored in localStorage
 * 2. On each page load, we check if the user is still authenticated
 * 3. If not authenticated but bridge token exists, we use it to restore the session
 * 4. The token is automatically refreshed on each successful session restoration
 * 5. Token is deleted on logout
 */

const SessionBridge = {
    // Track if we're in the middle of a navigation
    _isNavigating: false,
    // Track if a reload is pending (to prevent multiple reloads)
    _reloadPending: false,
    
    /**
     * Initialize the session bridge system
     * Should be called on page load
     */
    init: function() {
        // Delay session check slightly to avoid interfering with navigation
        // This gives the browser time to complete the navigation transition
        setTimeout(() => {
            // Only proceed if we're not in the middle of a navigation
            if (this._isNavigating) {
                return;
            }
            
            // Get bridge token from localStorage
            const bridgeToken = this.getBridgeToken();
            
            // Check if session is still valid
            this.checkSession()
                .done((response) => {
                    // Double-check we're still not navigating before acting
                    if (this._isNavigating) {
                        return;
                    }
                    
                    if (response.authenticated) {
                        // Session is still valid, refresh the token in the background
                        this.refreshToken();
                    } else if (bridgeToken) {
                        // Session lost but bridge token available - restore it
                        this.restoreSession(bridgeToken);
                    }
                })
                .fail(() => {
                    // Check endpoint failed - might be server issue
                    // Don't do anything, let user experience play out naturally
                    console.warn('Session bridge check failed');
                });
        }, 100); // Small delay to let navigation settle
    },

    /**
     * Get bridge token from localStorage
     * @returns {string|null} The bridge token or null if not found
     */
    getBridgeToken: function() {
        try {
            return localStorage.getItem('lifespan_bridge_token');
        } catch (e) {
            console.warn('Cannot access localStorage:', e);
            return null;
        }
    },

    /**
     * Store bridge token in localStorage
     * @param {string} token The token to store
     */
    setBridgeToken: function(token) {
        try {
            localStorage.setItem('lifespan_bridge_token', token);
        } catch (e) {
            console.error('Cannot store bridge token in localStorage:', e);
        }
    },

    /**
     * Clear bridge token from localStorage
     */
    clearBridgeToken: function() {
        try {
            localStorage.removeItem('lifespan_bridge_token');
        } catch (e) {
            console.warn('Cannot clear bridge token from localStorage:', e);
        }
    },

    /**
     * Check if the current session is valid
     * @returns {jQuery.ajax} jQuery ajax promise
     */
    checkSession: function() {
        return $.ajax({
            type: 'POST',
            url: '/api/session-bridge/check',
            headers: {
                'X-CSRF-TOKEN': this.getCsrfToken()
            },
            timeout: 5000
        });
    },

    /**
     * Restore session using a bridge token
     * @param {string} token The bridge token
     */
    restoreSession: function(token) {
        // Prevent multiple simultaneous restore attempts
        if (this._reloadPending) {
            return;
        }
        
        console.log('Attempting to restore session with bridge token...');
        
        $.ajax({
            type: 'POST',
            url: '/api/session-bridge/restore',
            data: {
                token: token
            },
            headers: {
                'X-CSRF-TOKEN': this.getCsrfToken()
            },
            timeout: 5000
        })
        .done((response) => {
            // Don't reload if we're in the middle of navigation
            if (this._isNavigating) {
                console.log('Session restored but skipping reload due to active navigation');
                return;
            }
            
            if (response.success && response.new_token) {
                console.log('Session restored successfully');
                // Store the new token
                this.setBridgeToken(response.new_token);
                // Show notification
                this.showNotification('You\'ve been automatically signed back in', 'success');
                // Mark reload as pending
                this._reloadPending = true;
                // Reload the page to apply the restored session
                setTimeout(() => {
                    // Final check before reload
                    if (!this._isNavigating) {
                        window.location.reload();
                    } else {
                        this._reloadPending = false;
                    }
                }, 1000); // Give user time to see the notification
            } else {
                console.warn('Session restoration failed:', response.message);
                this.clearBridgeToken();
            }
        })
        .fail((xhr, status, error) => {
            console.warn('Session restoration AJAX failed:', status, error);
            // If it's a 401 or invalid token, clear it
            if (xhr.status === 401) {
                this.clearBridgeToken();
            }
        });
    },

    /**
     * Refresh the bridge token (called when session is still valid)
     * This keeps the token fresh for future use
     */
    refreshToken: function() {
        $.ajax({
            type: 'POST',
            url: '/api/session-bridge/refresh',
            headers: {
                'X-CSRF-TOKEN': this.getCsrfToken()
            },
            timeout: 5000
        })
        .done((response) => {
            if (response.success && response.token) {
                // Store the refreshed token
                this.setBridgeToken(response.token);
            } else {
                console.warn('Token refresh failed');
            }
        })
        .fail(() => {
            // Silently fail - user is still authenticated via session cookie
            console.debug('Token refresh failed (but session still valid)');
        });
    },

    /**
     * Get CSRF token from meta tag or cookie
     * @returns {string} The CSRF token
     */
    getCsrfToken: function() {
        // Try to get from meta tag first
        let token = $('meta[name="csrf-token"]').attr('content');
        
        if (!token) {
            // Fallback to cookie
            token = this.getCookie('XSRF-TOKEN');
        }
        
        return token || '';
    },

    /**
     * Get cookie value by name
     * @param {string} name The cookie name
     * @returns {string|null} The cookie value or null
     */
    getCookie: function(name) {
        const nameEQ = name + '=';
        const cookies = document.cookie.split(';');
        
        for (let i = 0; i < cookies.length; i++) {
            let cookie = cookies[i].trim();
            if (cookie.indexOf(nameEQ) === 0) {
                return decodeURIComponent(cookie.substring(nameEQ.length));
            }
        }
        
        return null;
    },

    /**
     * Store bridge token from server session (called after login/registration)
     * This is called from the server-rendered template
     * @param {string} token The bridge token from the session
     */
    storeBridgeTokenFromServer: function(token) {
        if (token) {
            this.setBridgeToken(token);
            console.log('Bridge token stored from server');
        }
    },

    /**
     * Clear bridge token on logout
     * Should be called when user logs out
     */
    logout: function() {
        this.clearBridgeToken();
        console.log('Bridge token cleared on logout');
    },

    /**
     * Show a toast notification to the user
     * Uses the existing showToast function if available, otherwise creates a simple alert
     * @param {string} message The message to display
     * @param {string} type The type of toast: 'success', 'error', 'info', 'warning'
     */
    showNotification: function(message, type = 'info') {
        // Use existing showToast function if available (from tools-button-functions.js)
        if (typeof showToast === 'function') {
            showToast(message, type);
        } else {
            // Fallback: create our own toast
            this.createSimpleToast(message, type);
        }
    },

    /**
     * Create a simple toast notification as fallback
     * @param {string} message The message to display
     * @param {string} type The type of toast
     */
    createSimpleToast: function(message, type = 'info') {
        const bgColor = type === 'success' ? 'success' : type === 'error' ? 'danger' : 'info';
        const toast = document.createElement('div');
        toast.className = `toast align-items-center text-white bg-${bgColor} border-0`;
        toast.setAttribute('role', 'alert');
        toast.setAttribute('aria-live', 'assertive');
        toast.setAttribute('aria-atomic', 'true');
        
        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        `;
        
        // Add to page
        let toastContainer = document.getElementById('toast-container');
        if (!toastContainer) {
            toastContainer = document.createElement('div');
            toastContainer.id = 'toast-container';
            toastContainer.className = 'toast-container position-fixed top-0 end-0 p-3';
            toastContainer.style.zIndex = '9999';
            document.body.appendChild(toastContainer);
        }
        
        toastContainer.appendChild(toast);
        
        // Show toast
        const bsToast = new bootstrap.Toast(toast);
        bsToast.show();
        
        // Remove after it's hidden
        toast.addEventListener('hidden.bs.toast', () => {
            toast.remove();
        });
    }
};

// Track navigation state
let navigationStartTime = Date.now();
const NAVIGATION_TIMEOUT = 2000; // Consider navigation active for 2 seconds after page load

// Mark navigation as active when page starts loading
if (document.readyState === 'loading') {
    SessionBridge._isNavigating = true;
    navigationStartTime = Date.now();
}

// Clear navigation flag after page has settled
window.addEventListener('load', function() {
    // Wait a bit more to ensure navigation is complete
    setTimeout(() => {
        SessionBridge._isNavigating = false;
        SessionBridge._reloadPending = false;
    }, 300);
});

// Track link clicks to detect navigation
document.addEventListener('click', function(e) {
    const link = e.target.closest('a');
    if (link && link.href && link.hostname === window.location.hostname) {
        // Same-domain navigation detected
        SessionBridge._isNavigating = true;
        navigationStartTime = Date.now();
    }
}, true); // Use capture phase to catch links early

// Also track programmatic navigation
let originalPushState = history.pushState;
let originalReplaceState = history.replaceState;

history.pushState = function() {
    SessionBridge._isNavigating = true;
    navigationStartTime = Date.now();
    return originalPushState.apply(history, arguments);
};

history.replaceState = function() {
    SessionBridge._isNavigating = true;
    navigationStartTime = Date.now();
    return originalReplaceState.apply(history, arguments);
};

// Clear navigation flag after timeout (safety net)
setInterval(function() {
    const timeSinceNavigation = Date.now() - navigationStartTime;
    if (timeSinceNavigation > NAVIGATION_TIMEOUT) {
        SessionBridge._isNavigating = false;
    }
}, 500);

// Initialize on document ready
document.addEventListener('DOMContentLoaded', function() {
    SessionBridge.init();
});

// Also handle page visibility - refresh token when user returns to tab
document.addEventListener('visibilitychange', function() {
    if (document.visibilityState === 'visible') {
        // User came back to the tab
        SessionBridge.checkSession()
            .done((response) => {
                if (!response.authenticated && SessionBridge.getBridgeToken()) {
                    // Session was lost while we were away
                    SessionBridge.restoreSession(SessionBridge.getBridgeToken());
                } else if (response.authenticated) {
                    // Refresh token on visibility change
                    SessionBridge.refreshToken();
                }
            });
    }
});

// Export for use in other modules
export default SessionBridge;
