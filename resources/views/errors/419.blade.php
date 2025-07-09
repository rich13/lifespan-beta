@extends('errors.layout')

@section('error_code', '419')
@section('error_title', 'Time has run out')
@section('error_message', 'Something to do with sessions...')
@section('error_color', 'warning')
@section('error_icon', 'bi-hourglass-split')

@section('error_details')
<div class="alert alert-info mb-4" role="alert">
    <h6 class="alert-heading mb-2">
        <i class="bi bi-info-circle me-2"></i>Hack-o-matic
    </h6>
    <p class="mb-3">This will try to fix the problem...</p>
    
    <div class="d-grid gap-2">
        <button id="clearSessionBtn" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-trash me-2"></i>Eat the cookies
        </button>
        <small class="text-muted">This will clear your cookies and reload the page</small>
    </div>
</div>

<div id="sessionStatus" class="alert alert-secondary mb-4" style="display: none;">
    <div class="d-flex align-items-center">
        <div class="spinner-border spinner-border-sm me-2" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
        <span id="statusText">Clearing session cookies...</span>
    </div>
</div>
@endsection

@push('scripts')
<script>
/**
 * Session Utilities for handling 419 errors
 */
const SessionUtils = {
    clearSessionCookies() {
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
            this.clearCookie(cookieName);
            
            // Clear for current hostname
            this.clearCookie(cookieName, window.location.hostname);
            
            // Clear for parent domain (e.g., if on app.example.com, clear for example.com)
            const domainParts = window.location.hostname.split('.');
            if (domainParts.length > 2) {
                const parentDomain = domainParts.slice(-2).join('.');
                this.clearCookie(cookieName, parentDomain);
            }
            
            // Clear for root domain (e.g., if on app.example.com, clear for .example.com)
            if (domainParts.length > 1) {
                const rootDomain = '.' + domainParts.slice(-2).join('.');
                this.clearCookie(cookieName, rootDomain);
            }
            
            // Also try without domain (for localhost/development)
            this.clearCookie(cookieName, null);
        });
        
        // Also clear any cookies that might have secure/httpOnly issues
        this.clearCookie('lifespan_session', null, false, false);
        this.clearCookie('XSRF-TOKEN', null, false, false);
        
        console.log('Session cookies cleared.');
    },

    clearCookie(name, domain = null, secure = true, httpOnly = true) {
        const cookieOptions = [
            'expires=Thu, 01 Jan 1970 00:00:00 GMT',
            'path=/'
        ];
        
        if (domain) {
            cookieOptions.push(`domain=${domain}`);
        }
        
        if (secure) {
            cookieOptions.push('secure');
        }
        
        if (httpOnly) {
            cookieOptions.push('httpOnly');
        }
        
        cookieOptions.push('samesite=lax');
        
        // Set cookie with past expiration date to delete it
        document.cookie = `${name}=; ${cookieOptions.join('; ')}`;
        
        console.log(`Cleared cookie: ${name}${domain ? ` (domain: ${domain})` : ''}${secure ? ' (secure)' : ''}${httpOnly ? ' (httpOnly)' : ''}`);
    },

    getCookie(name) {
        const value = `; ${document.cookie}`;
        const parts = value.split(`; ${name}=`);
        if (parts.length === 2) {
            return parts.pop().split(';').shift();
        }
        return null;
    }
};

// Handle the clear session button click
document.addEventListener('DOMContentLoaded', function() {
    const clearBtn = document.getElementById('clearSessionBtn');
    const statusDiv = document.getElementById('sessionStatus');
    const statusText = document.getElementById('statusText');
    
    clearBtn.addEventListener('click', function() {
        // Disable button and show status
        clearBtn.disabled = true;
        clearBtn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Clearing...';
        statusDiv.style.display = 'block';
        statusText.textContent = 'Clearing session cookies...';
        
        // Clear cookies
        SessionUtils.clearSessionCookies();
        
        // Update status
        statusText.textContent = 'Session cleared! Getting fresh CSRF token...';
        
        // Get a fresh CSRF token before refreshing
        fetch('/sanctum/csrf-cookie', {
            method: 'GET',
            credentials: 'include'
        })
        .then(() => {
            statusText.textContent = 'Fresh token obtained! Redirecting to home...';
            // Redirect to home page after getting fresh token
            setTimeout(() => {
                window.location.href = '/';
            }, 1000);
        })
        .catch(() => {
            statusText.textContent = 'Redirecting to home...';
            // Fallback: redirect to home anyway
            setTimeout(() => {
                window.location.href = '/';
            }, 1000);
        });
    });
    
    // Also provide a fallback - if user clicks anywhere on the page, 
    // show a subtle hint about the clear button
    document.addEventListener('click', function(e) {
        if (e.target !== clearBtn && !clearBtn.contains(e.target)) {
            clearBtn.classList.add('btn-outline-warning');
            clearBtn.classList.remove('btn-outline-primary');
            setTimeout(() => {
                clearBtn.classList.remove('btn-outline-warning');
                clearBtn.classList.add('btn-outline-primary');
            }, 200);
        }
    });
});
</script>
@endpush 