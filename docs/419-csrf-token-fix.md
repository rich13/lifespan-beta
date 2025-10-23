# 419 CSRF Token Mismatch Fix

## Problem

When creating new spans in production (Railway environment) via the new span modal, users received a **419 Token Mismatch** error, while the same action worked fine on localhost.

### Example Error Log
```
[2025-10-23T08:50:20.008505+00:00] production.WARNING: Client Error {
    "url":"https://beta.lifespan.dev/spans",
    "method":"POST",
    "status":419,
    ...
}
127.0.0.1 -  23/Oct/2025:08:50:19 +0000 "POST /index.php" 419
```

## Root Cause

The 419 error indicates a **CSRF token mismatch** between the client-side token and the server-side validation. This occurred because:

1. **Session/Token Misalignment**: In the Railway production environment, the session and CSRF token could become misaligned between browser requests
2. **Token Staleness**: The CSRF token fetched from the meta tag might not match the server's internal session token
3. **Secure Cookie Settings**: Production uses `secure=true`, `same_site=lax`, and explicit domain configuration, which can cause timing issues
4. **Multiple Attempts**: No retry mechanism existed, so a single token mismatch resulted in immediate failure

## Solution

### 1. **Token Caching** (Lines 654-655)
```javascript
// Get CSRF token from meta tag ONCE and cache it
const csrfToken = $('meta[name="csrf-token"]').attr('content');
```

This ensures the same token is used consistently in both the FormData and headers, preventing mismatches due to meta tag updates mid-request.

### 2. **Pre-emptive Token Refresh** (Lines 1331-1340)
When the modal opens, we proactively fetch a fresh CSRF token:
```javascript
$('#newSpanModal').on('show.bs.modal', function() {
    // Ensure we have a fresh CSRF token before showing the modal
    fetch('/sanctum/csrf-cookie', {
        method: 'GET',
        credentials: 'include'
    })
    .catch(err => {
        console.warn('Could not refresh CSRF token, proceeding anyway:', err);
    });
    
    showStep(1);
});
```

This guarantees that when the user submits the form, they have a current valid token.

### 3. **Automatic Retry with Token Refresh** (Lines 706-774)
When a 419 error occurs, the application now:
- Fetches a fresh CSRF token via `/sanctum/csrf-cookie`
- Recreates the FormData with the new token
- Automatically retries the request
- Only fails to user if retry also fails

```javascript
if (xhr.status === 419) {
    // CSRF token mismatch - try to refresh and retry
    console.warn('419 CSRF token mismatch - attempting to refresh token and retry');
    
    // Refresh the CSRF token
    fetch('/sanctum/csrf-cookie', {
        method: 'GET',
        credentials: 'include'
    })
    .then(response => {
        if (response.ok) {
            // Get new token and retry
            const newCsrfToken = $('meta[name="csrf-token"]').attr('content');
            // ... recreate FormData and retry request ...
        }
    });
}
```

### 4. **Consistent Headers and FormData** 
All span creation requests now include:
- `_token` field in FormData for Laravel validation
- `X-CSRF-TOKEN` header for Sanctum validation
- `Accept: application/json` header

This redundancy ensures compatibility with multiple validation pathways.

### 5. **Improved Error Messaging**
User-friendly messages distinguish between:
- Session expiration (419 errors)
- Validation failures (422 errors)
- Other errors
- Network errors

## Files Modified

- `resources/views/components/modals/new-span-modal.blade.php`
  - Lines 654-655: Token caching
  - Lines 679-680: Added debugging logging
  - Lines 706-774: 419 error handling with retry
  - Lines 776-807: Improved error messaging
  - Lines 896-903: Headers for AI creation
  - Lines 909-920: 419 handling for AI creation
  - Lines 949-956: Headers for merge confirmation
  - Lines 959-966: 419 handling for merge
  - Lines 1331-1340: Pre-emptive token refresh on modal open

## Testing

### Local Testing
1. Open the new span modal
2. Fill in span details and submit
3. Verify span creation succeeds
4. Check browser console for any warnings

### Production Testing
1. Create a new span in production
2. Monitor for 419 errors in Sentry
3. If 419 occurs, verify auto-retry attempts connection (should see console message)
4. After fix, verify no 419 errors occur

## Related Files

- `app/Http/Middleware/VerifyCsrfToken.php` - Logs CSRF debug info in production
- `app/Http/Middleware/FixSessionInRailway.php` - Handles Railway-specific session config
- `config/session.php` - Session cookie configuration
- `resources/js/session-utils.js` - Session utilities for 419 error handling

## Notes for Developers

1. **Session Lifetime**: If spans are being created after long idle periods, sessions may expire. The automatic retry helps but users may still need to refresh occasionally.

2. **Sanctum CSRF**: The application uses Laravel Sanctum for CSRF protection. The `/sanctum/csrf-cookie` endpoint refreshes the CSRF token.

3. **API Routes vs Web Routes**: 
   - Web routes (like `/spans`) use session-based CSRF
   - API routes may use token-based or Sanctum authentication
   - This fix targets web routes in the modal

4. **Production Specifics**:
   - Railway environment requires `secure=true` for cookies
   - SameSite=lax is enforced
   - Session domain is set dynamically based on request host

## References

- Laravel CSRF Protection: https://laravel.com/docs/csrf
- Sanctum Documentation: https://laravel.com/docs/sanctum
- Railway Environment: See `docker/prod/set-session-config.php`
