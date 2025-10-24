# Session Bridge Implementation Summary

## What Was Built

A seamless session recovery system that automatically keeps users logged in when the application redeploys and server sessions are reset.

## Files Created

### Backend
1. **`app/Http/Controllers/Auth/SessionBridgeController.php`** (NEW)
   - Handles session restoration via bridge tokens
   - Three main endpoints:
     - `restoreSession()` - Restore session using a bridge token
     - `checkSession()` - Check if current session is valid
     - `refreshBridgeToken()` - Issue fresh token for authenticated user

### Frontend
2. **`resources/js/session-bridge.js`** (NEW)
   - Core client-side session bridge logic
   - Main methods:
     - `init()` - Initialize on page load
     - `restoreSession(token)` - Use bridge token to restore session
     - `refreshBridgeToken()` - Keep token fresh
     - `checkSession()` - Detect session loss
     - `showNotification(message, type)` - Show toast to user when session restored
   - Automatic initialization on page load
   - Handles page visibility changes (tabs switching)
   - Integrates with existing `showToast` system

## Files Modified

### Backend
1. **`app/Http/Controllers/Auth/AuthenticatedSessionController.php`**
   - Added `generateSessionBridgeToken()` method
   - Generates bridge token on login

2. **`app/Http/Controllers/Auth/EmailFirstAuthController.php`**
   - Added `generateSessionBridgeToken()` method
   - Generates bridge token on both login and registration
   - Generates bridge token after registration complete

3. **`routes/web.php`**
   - Added three new routes:
     - `POST /api/session-bridge/restore` - Restore session (public)
     - `POST /api/session-bridge/check` - Check session status (public)
     - `POST /api/session-bridge/refresh` - Refresh token (authenticated)

### Frontend/Views
4. **`resources/views/layouts/app.blade.php`**
   - Added `session-bridge.js` to Vite imports
   - Added inline JavaScript to store bridge token from session

5. **`resources/views/components/shared/user-profile-info.blade.php`**
   - Added `onsubmit` handler to logout forms
   - Calls `SessionBridge.logout()` to clear token on logout

## How to Test

### Test 1: Simulate Session Loss (Redeploy)
1. Log in to the app
2. Open DevTools (F12)
3. Go to Application > Cookies
4. Delete the session cookie (usually named `lifespan_session`)
5. Refresh the page
6. **Expected**: Green success toast appears saying "You've been automatically signed back in"
7. Page reloads and you remain logged in

### Test 2: Verify Token Storage
1. Log in to the app
2. Open DevTools (F12)
3. Go to Application > Local Storage
4. Look for key: `lifespan_bridge_token`
5. **Expected**: Token value is visible and stored

### Test 3: Token Refresh on Page Load
1. Log in and navigate around the app normally
2. Open DevTools Console (F12 > Console)
3. **Expected**: No error messages, only normal operations
4. Token is automatically refreshed on each page load

### Test 4: Logout Clears Token
1. Log in to the app
2. Click "Sign Out"
3. Open DevTools (F12)
4. Go to Application > Local Storage
5. Look for `lifespan_bridge_token`
6. **Expected**: Key should be gone (cleared on logout)

## User Experience Flow

### Normal Usage
- User logs in → Bridge token generated and stored
- User navigates → Token refreshed on each page load
- User logs out → Token cleared

### During Redeploy
- App redeploys
- Session cookie is lost
- User refreshes page (or just navigates)
- Session Bridge detects session loss
- Session Bridge finds bridge token in localStorage
- Session Bridge sends token to server
- Server validates and creates new session
- Fresh bridge token issued
- Green success toast: "You've been automatically signed back in"
- Page reloads
- User continues working - completely transparent!

### Tab Switching
- User leaves app in one tab
- App redeploys
- User comes back to the other tab
- Page visibility API triggers session restoration
- Session automatically restored when tab becomes visible

## Security Notes

### Current Implementation
- **Suitable for**: Closed prototypes with trusted users
- **Token Storage**: `localStorage` (accessible by same-origin JavaScript)
- **Token Type**: Never-expiring API tokens (Sanctum)
- **CSRF Protection**: All endpoints protected by CSRF middleware
- **Scope**: Tokens tied to specific users

### Production Considerations
See detailed section in `docs/session-bridge.md` for:
- Token expiration settings
- HTTPS enforcement
- HTTPOnly cookies alternative
- Rate limiting recommendations

## API Endpoints

### POST /api/session-bridge/restore
Restores a session using a bridge token.

**Request**: `{ "token": "..." }`  
**Response**: `{ "success": true, "message": "...", "new_token": "..." }`

### POST /api/session-bridge/check
Checks if current session is still valid.

**Response**: `{ "authenticated": true/false, "user_id": "...", "has_token": true/false }`

### POST /api/session-bridge/refresh (Auth Required)
Refreshes the bridge token for current user.

**Response**: `{ "success": true, "token": "..." }`

## Key Features

✅ **Automatic Session Restoration** - No user action required  
✅ **Toast Notifications** - User informed when session restored  
✅ **Token Refresh** - Tokens kept fresh during normal use  
✅ **Page Visibility Handling** - Works even when switching tabs  
✅ **CSRF Protection** - All endpoints secured  
✅ **Graceful Fallback** - Existing `showToast` function used when available  
✅ **Console Logging** - Debug-friendly logging for troubleshooting  
✅ **Logout Cleanup** - Tokens cleared on logout  

## Configuration

### Disable Feature (if needed)
Comment out in `resources/views/layouts/app.blade.php`:
```php
// @vite(['resources/scss/app.scss', 'resources/js/app.js', 'resources/js/routes.js', 'resources/js/session-bridge.js'])
```

### Customize Token Name
Search for `'session-bridge'` in:
- `app/Http/Controllers/Auth/SessionBridgeController.php`
- `app/Http/Controllers/Auth/AuthenticatedSessionController.php`
- `app/Http/Controllers/Auth/EmailFirstAuthController.php`

### Customize Storage Key
Search for `'lifespan_bridge_token'` in:
- `resources/js/session-bridge.js`

### Customize Notification Message
In `resources/js/session-bridge.js`, line ~121:
```javascript
this.showNotification('You\'ve been automatically signed back in', 'success');
```

## Browser Console Commands (for testing)

```javascript
// Check if bridge token exists
SessionBridge.getBridgeToken()

// Manually trigger restoration
SessionBridge.restoreSession(SessionBridge.getBridgeToken())

// Manually clear token
SessionBridge.clearBridgeToken()

// Check session status
SessionBridge.checkSession()

// Manually refresh token
SessionBridge.refreshBridgeToken()

// Show test notification
SessionBridge.showNotification('Test notification', 'success')
```

## Related Files

- `/docs/session-bridge.md` - Comprehensive technical documentation
- `/routes/web.php` - Route definitions
- `/config/session.php` - Session configuration
- `/config/sanctum.php` - Token configuration

## What Happens on Each Page Load

```
Page Load
  ↓
SessionBridge.init()
  ↓
checkSession() AJAX call
  ↓
Is session authenticated?
  ├─ YES → Refresh token silently
  └─ NO → Bridge token exists?
           ├─ YES → Restore session → Show toast → Reload
           └─ NO → User redirected to login
```

## Common Use Cases

### Scenario 1: Production Redeploy
- User is working in the app
- Production deployment triggers
- User's session is cleared
- User refreshes page or navigates
- Session Bridge automatically restores
- Green toast notification
- User continues working

### Scenario 2: Browser Restart
- User logs in
- Closes browser completely
- Bridge token persists in localStorage (browser restarts)
- User reopens app
- Session cookie doesn't exist
- Bridge token used to restore session
- User logged back in automatically

### Scenario 3: New Tab
- User logged in on tab A
- Opens new tab and goes to app
- New tab has no session cookie (not shared)
- Bridge token from localStorage used
- New tab authenticated via session restoration
- Works on both tabs independently

## Performance Impact

- **On Login**: +1 API token creation (negligible)
- **On Page Load**: +1 AJAX call to check session (5 second timeout, ~50ms typically)
- **Token Refresh**: Background AJAX call (doesn't block page load)
- **Storage**: ~250 bytes in localStorage per token

## Troubleshooting

### Session not restoring?
1. Check DevTools Console for errors
2. Verify bridge token exists: `localStorage.getItem('lifespan_bridge_token')`
3. Check server logs for "Session bridge token used..."
4. Clear localStorage and re-login: `localStorage.clear()`

### Token not storing?
1. Check if private browsing mode (disabled localStorage)
2. Check if browser storage is full
3. Check DevTools Console for storage errors

### Toast not showing?
1. Check if `toast-container` div exists
2. Check DevTools Console for errors
3. Verify Bootstrap Toast is loaded

## Next Steps (Optional Enhancements)

1. Add token revocation endpoint
2. Track token usage analytics
3. Implement feature flag for gradual rollout
4. Add token expiration configuration
5. Implement separate tokens per device
6. Add token revocation from settings
