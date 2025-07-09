/**
 * Session Utilities for handling 419 errors and session cookies
 * 
 * This utility provides functions to clear stale session cookies that cause 419 errors
 * and manage session-related cookies in the application.
 */

/**
 * Clear all session-related cookies that could cause 419 errors
 * This includes Laravel session cookies and CSRF tokens
 */
function clearSessionCookies() {
    const cookiesToClear = [
        'lifespan_session',           // Main Laravel session cookie
        'XSRF-TOKEN',                 // Laravel CSRF token cookie
        'sidebarCollapsed',           // App-specific cookie
        'remember_web_*',             // Remember me cookies
        'laravel_session',            // Fallback session cookie name
        'laravel_token',              // Fallback CSRF token
    ];
    
    console.log('Clearing session cookies to resolve 419 error...');
    
    cookiesToClear.forEach(cookieName => {
        // Clear cookie for current domain
        clearCookie(cookieName);
        
        // Also try to clear with domain prefix if we're on a subdomain
        if (window.location.hostname.includes('.')) {
            clearCookie(cookieName, window.location.hostname);
        }
        
        // Clear for parent domain
        const domainParts = window.location.hostname.split('.');
        if (domainParts.length > 2) {
            const parentDomain = domainParts.slice(-2).join('.');
            clearCookie(cookieName, parentDomain);
        }
    });
    
    console.log('Session cookies cleared. You may need to refresh the page.');
}

/**
 * Clear a specific cookie by name
 * @param {string} name - Cookie name to clear
 * @param {string} domain - Optional domain to clear from
 */
function clearCookie(name, domain = null) {
    const cookieOptions = [
        'expires=Thu, 01 Jan 1970 00:00:00 GMT',
        'path=/',
        'secure',
        'samesite=lax'
    ];
    
    if (domain) {
        cookieOptions.push(`domain=${domain}`);
    }
    
    // Set cookie with past expiration date to delete it
    document.cookie = `${name}=; ${cookieOptions.join('; ')}`;
    
    console.log(`Cleared cookie: ${name}${domain ? ` (domain: ${domain})` : ''}`);
}

/**
 * Check if a cookie exists
 * @param {string} name - Cookie name to check
 * @returns {boolean} - True if cookie exists
 */
function cookieExists(name) {
    return document.cookie.split(';').some(item => item.trim().startsWith(name + '='));
}

/**
 * Get cookie value by name
 * @param {string} name - Cookie name
 * @returns {string|null} - Cookie value or null if not found
 */
function getCookie(name) {
    const value = `; ${document.cookie}`;
    const parts = value.split(`; ${name}=`);
    if (parts.length === 2) {
        return parts.pop().split(';').shift();
    }
    return null;
}

/**
 * Handle 419 errors by clearing session cookies and refreshing
 * This can be called when a 419 error is detected
 */
function handle419Error() {
    console.warn('419 error detected - clearing session cookies...');
    clearSessionCookies();
    
    // Show user-friendly message
    const message = 'Your session has expired. The page will refresh to log you in again.';
    
    // Create a temporary notification
    const notification = document.createElement('div');
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: #f8d7da;
        color: #721c24;
        padding: 15px 20px;
        border-radius: 5px;
        border: 1px solid #f5c6cb;
        z-index: 9999;
        max-width: 300px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    `;
    notification.textContent = message;
    document.body.appendChild(notification);
    
    // Remove notification after 3 seconds
    setTimeout(() => {
        if (notification.parentNode) {
            notification.parentNode.removeChild(notification);
        }
    }, 3000);
    
    // Refresh the page after a short delay
    setTimeout(() => {
        window.location.reload();
    }, 1000);
}

/**
 * Set up global error handling for 419 errors
 * This intercepts fetch requests and handles 419 responses
 */
function setup419ErrorHandling() {
    // Intercept fetch requests to handle 419 errors
    const originalFetch = window.fetch;
    window.fetch = function(...args) {
        return originalFetch.apply(this, args).then(response => {
            if (response.status === 419) {
                handle419Error();
                throw new Error('419: Session expired');
            }
            return response;
        });
    };
    
    // Also handle jQuery AJAX requests if jQuery is available
    if (window.jQuery) {
        $(document).ajaxError(function(event, xhr, settings) {
            if (xhr.status === 419) {
                handle419Error();
            }
        });
    }
    
    console.log('419 error handling setup complete');
}

/**
 * Check if current session is valid by testing CSRF token
 * @returns {Promise<boolean>} - True if session is valid
 */
async function checkSessionValidity() {
    try {
        const response = await fetch('/sanctum/csrf-cookie', {
            method: 'GET',
            credentials: 'include'
        });
        
        if (response.ok) {
            const csrfToken = getCookie('XSRF-TOKEN');
            return !!csrfToken;
        }
        
        return false;
    } catch (error) {
        console.error('Error checking session validity:', error);
        return false;
    }
}

/**
 * Refresh CSRF token
 * @returns {Promise<string|null>} - New CSRF token or null if failed
 */
async function refreshCsrfToken() {
    try {
        const response = await fetch('/sanctum/csrf-cookie', {
            method: 'GET',
            credentials: 'include'
        });
        
        if (response.ok) {
            const newToken = getCookie('XSRF-TOKEN');
            if (newToken) {
                // Update meta tag if it exists
                const metaTag = document.querySelector('meta[name="csrf-token"]');
                if (metaTag) {
                    metaTag.setAttribute('content', newToken);
                }
                console.log('CSRF token refreshed');
                return newToken;
            }
        }
        
        return null;
    } catch (error) {
        console.error('Error refreshing CSRF token:', error);
        return null;
    }
}

// Auto-setup error handling when script loads
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', setup419ErrorHandling);
} else {
    setup419ErrorHandling();
}

// Export functions for use in other modules
window.SessionUtils = {
    clearSessionCookies,
    clearCookie,
    cookieExists,
    getCookie,
    handle419Error,
    setup419ErrorHandling,
    checkSessionValidity,
    refreshCsrfToken
}; 