# Session Bridge: Seamless Session Recovery During Redeploys

## Overview

The Session Bridge is a mechanism that allows users to remain authenticated even when the application redeploys and server sessions are reset. This is particularly useful for closed prototypes running on platforms like Railway.com where redeploys can invalidate session data.

## How It Works

### Architecture

The Session Bridge uses a **long-lived API token** (stored in `localStorage`) to bridge the gap between session losses:

1. **Token Generation**: When a user logs in or registers, a special "session-bridge" token is generated and stored in `localStorage`
2. **Session Checking**: On each page load, the client checks if the user's session is still valid
3. **Session Restoration**: If the session is lost but the bridge token exists, the token is used to restore the session automatically
4. **Token Refresh**: Tokens are periodically refreshed to keep them current
5. **Cleanup**: Tokens are deleted when the user logs out

### Key Components

#### Backend

**SessionBridgeController** (`app/Http/Controllers/Auth/SessionBridgeController.php`)
- `restoreSession()` - Validates a bridge token and creates a new session
- `refreshBridgeToken()` - Issues a fresh token for an authenticated user
- `checkSession()` - Verifies if the current session is still valid

**Modified Auth Controllers**
- `AuthenticatedSessionController` - Generates bridge token on login
- `EmailFirstAuthController` - Generates bridge token on login/registration

#### Frontend

**session-bridge.js** (`resources/js/session-bridge.js`)
- Initializes on page load
- Detects session loss
- Automatically restores session using stored token
- Handles token refresh
- Clears token on logout

#### Database

Uses Laravel Sanctum's `personal_access_tokens` table to store and validate bridge tokens.

## API Endpoints

### 1. Restore Session
**POST** `/api/session-bridge/restore`

Restores a user's session using a bridge token.

**Request:**
```json
{
    "token": "session-bridge-token-value"
}
```

**Response (Success):**
```json
{
    "success": true,
    "message": "Session restored successfully",
    "new_token": "new-session-bridge-token-value"
}
```

**Response (Failure):**
```json
{
    "success": false,
    "message": "Invalid or expired session token"
}
```

### 2. Check Session
**POST** `/api/session-bridge/check`

Checks if the current session is still valid (unauthenticated endpoint).

**Response:**
```json
{
    "authenticated": true,
    "user_id": "uuid",
    "has_token": true
}
```

### 3. Refresh Token
**POST** `/api/session-bridge/refresh` (requires authentication)

Refreshes the bridge token for an authenticated user.

**Response (Success):**
```json
{
    "success": true,
    "token": "new-session-bridge-token-value"
}
```

## Browser Storage

### localStorage Keys

- **`lifespan_bridge_token`** - The current session bridge token
  - Persists across browser tabs and sessions
  - Survives browser restarts (unlike session cookies)

## Token Lifecycle

### Generation
Tokens are generated at:
- User login (via `AuthenticatedSessionController` or `EmailFirstAuthController`)
- User registration
- Session restoration (new token issued immediately after restoration)

### Refresh
Tokens are refreshed:
- On every page load (if session is still valid)
- When the browser tab becomes visible again (page visibility API)
- Every time the session is restored

### Expiration
- Tokens **never expire** in the current implementation (suitable for closed prototypes)
- For production, set an expiration time in `config/sanctum.php`:
  ```php
  'expiration' => 1440, // 24 hours in minutes
  ```

### Cleanup
Tokens are deleted when:
- User clicks "Sign Out"
- Token fails validation (401 error)
- User explicitly clears their browser storage

## Security Considerations

### Current Security Level: **Prototype**

For a closed prototype with trusted users, the current implementation is suitable because:
- Access is restricted to authorized users only
- The token is stored in `localStorage`, which is not accessible to cross-origin scripts
- Tokens are tied to specific users (via Sanctum's `personal_access_tokens` table)
- CSRF protection is in place for all restore requests

### Production Recommendations

For production deployment, consider:

1. **Token Expiration**
   ```php
   // config/sanctum.php
   'expiration' => 1440, // 24 hours
   ```

2. **Token Rotation**
   ```php
   // Generate new token on each session restoration
   // (already implemented)
   ```

3. **HTTPS Only**
   ```php
   // config/session.php
   'secure' => true, // Only send over HTTPS
   ```

4. **HTTPOnly Cookies** (optional alternative)
   - Could store token in HTTPOnly cookies instead of localStorage
   - More resistant to XSS attacks
   - Less cross-tab visibility

5. **Rate Limiting**
   ```php
   // Add throttle middleware to restore endpoint
   Route::post('api/session-bridge/restore', [...])
       ->middleware('throttle:5,1'); // 5 attempts per minute
   ```

## User Experience

### Before Redeploy

User is logged in and using the app normally.

### During Redeploy

1. Server redeploys (can be instantaneous or take a few seconds)
2. Browser tab may show a connection error briefly
3. Page auto-reloads

### After Redeploy (Automatic)

1. Session Bridge initializes
2. Detects that session cookie is no longer valid
3. Finds bridge token in localStorage
4. Sends token to server
5. Server validates token and creates new session
6. New bridge token is issued and stored
7. Page reloads with fresh session
8. User sees their content exactly as before

**Result**: Transparent to user - they remain logged in and continue working.

## Implementation Details

### Session Storage Configuration

The application uses file-based sessions (configured in `config/session.php`):

```php
'driver' => env('SESSION_DRIVER', 'file'),
'lifetime' => env('SESSION_LIFETIME', 525600), // 1 year
```

**Important**: When the application restarts:
- If using file-based sessions (current), session files may be preserved if using persistent storage
- If using database sessions, sessions persist in PostgreSQL
- Bridge tokens persist in the `personal_access_tokens` table regardless

### CSRF Protection

All bridge endpoints are protected by CSRF middleware where applicable:

- `restore` - Public endpoint but protected by CSRF token
- `check` - Public endpoint, safe (read-only)
- `refresh` - Protected by `auth` middleware

### How Session Bridge Avoids Infinite Loops

```javascript
// On page load:
1. SessionBridge.init() runs
2. It calls checkSession()
3. If session invalid and bridge token exists, calls restoreSession()
4. restoreSession() calls window.location.reload()
5. New page load happens, but now session cookie is restored
6. SessionBridge.init() runs again
7. checkSession() returns authenticated: true
8. Calls refreshToken() (not restoreSession)
9. Done - no loop!
```

## Testing the Feature

### Simulate a Redeploy

1. Open your app and log in
2. In browser DevTools (F12), go to Application > Cookies
3. Find and delete the session cookie (usually named `lifespan_session`)
4. Refresh the page
5. Watch the console (F12 > Console tab) for "Session bridge token used to restore session"
6. Page should reload and you should remain logged in
7. Check Application > Local Storage to see `lifespan_bridge_token`

### Manual Token Restoration

```javascript
// In browser console
SessionBridge.restoreSession(SessionBridge.getBridgeToken());
```

### Debug Mode

Session Bridge logs to the browser console:
```javascript
// View logs in DevTools > Console
// Level: log = info, warn = warnings, error = errors
```

## Configuration

### Disabling Session Bridge (if needed)

To temporarily disable the feature, comment out the script include in `resources/views/layouts/app.blade.php`:

```php
// @vite(['resources/scss/app.scss', 'resources/js/app.js', 'resources/js/routes.js', 'resources/js/session-bridge.js'])
```

### Adjusting Token Name

To change the token name (e.g., from "session-bridge" to something else), modify:

1. `app/Http/Controllers/Auth/SessionBridgeController.php`
2. `app/Http/Controllers/Auth/AuthenticatedSessionController.php`
3. `app/Http/Controllers/Auth/EmailFirstAuthController.php`

Change all instances of `'session-bridge'` to your new name.

### Adjusting localStorage Key

To change the localStorage key (currently `lifespan_bridge_token`), modify `resources/js/session-bridge.js`:

```javascript
// Change this line:
localStorage.getItem('lifespan_bridge_token');

// To:
localStorage.getItem('your-custom-key');
```

## Troubleshooting

### Session Not Restoring

**Symptom**: User is logged out after redeploy

**Diagnosis**:
1. Check browser console for errors (F12 > Console)
2. Check server logs for `Session bridge token used to restore session`
3. Verify CSRF token is being sent correctly

**Solutions**:
- Verify the bridge token exists: `localStorage.getItem('lifespan_bridge_token')`
- Clear localStorage and log in again: `localStorage.clear()`
- Check if server session files are being persisted during redeploy

### Token Not Storing

**Symptom**: localStorage shows no bridge token after login

**Diagnosis**:
1. Check if localStorage is enabled (might be disabled in private browsing)
2. Check browser console for storage errors

**Solutions**:
- Try in non-private browsing mode
- Check if browser storage is full
- Clear browser cache and cookies

### CORS Issues with Endpoint

**Symptom**: 404 or CORS errors when calling `/api/session-bridge/restore`

**Diagnosis**:
1. Verify routes are registered in `routes/web.php`
2. Check if route is spelled correctly

**Solutions**:
- Run `php artisan route:list | grep session-bridge`
- Clear route cache: `php artisan route:clear`

## Related Documentation

- [Laravel Sanctum](https://laravel.com/docs/sanctum)
- [Session Configuration](../database_conventions.md)
- [CSRF Token Handling](./419-csrf-token-fix.md)
- [Production Deployment](./production-deployment.md)

## Future Improvements

1. **Token Revocation**: Add ability for users to manually revoke bridge tokens
2. **Multiple Devices**: Allow separate tokens for each device
3. **Usage Analytics**: Track when session bridges are used
4. **Gradual Rollout**: Feature flag to enable/disable per user or region
5. **Token Expiration**: Configurable expiration time
6. **Two-Factor Authentication**: Require 2FA before restoring session from bridge token
