# Admin Mode Toggle Feature

## Overview

The Admin Mode Toggle feature allows admin users to temporarily disable their admin status to see what the application looks like and behaves like for regular users. This is useful for:

- **Testing the user experience** as a non-admin would see it
- **Identifying admin-only features** that might confuse regular users
- **Verifying that access controls work correctly** for non-admin users
- **Debugging permission-related issues** from a user's perspective

The key advantage is that an admin can toggle admin mode on and off without logging out or affecting the database. The toggle state is **session-based** and is cleared when the user logs out.

## How It Works

### User Interface

Admin users will see an "Admin Mode" toggle item in their user dropdown menu (accessible from the top-right user avatar):

- When admin mode is **ON** (enabled): The menu shows "Admin Mode (ON)" in normal text
- When admin mode is **OFF** (disabled): The menu shows "Admin Mode (OFF)" in warning/orange text

### State Management

The admin mode toggle state is stored **in the session**, not in the database. This means:

- The actual `is_admin` flag in the database is never modified
- Toggling admin mode does not affect the user's permanent admin status
- The toggle state is **per-session** - opening a new browser window/tab won't reflect the toggle
- The state is cleared when the user logs out
- The user can always toggle back to see the admin interface again

### How Authorization Works

When a request is made, the system checks the **effective admin status**, which accounts for the toggle:

1. **Database check**: Is the user's `is_admin` flag true?
2. **Session check**: Has admin mode been disabled in this session?
3. **Result**: If the user is an admin (database) AND admin mode is not disabled (session), they are treated as an admin

This means:

- **Admin with admin mode ON**: Full admin access to admin routes, admin features, etc.
- **Admin with admin mode OFF**: Same restrictions as a normal user - cannot access admin routes, cannot see admin features, cannot access private spans they don't own
- **Normal user**: Always restricted, regardless of admin settings

## Using the Feature

### Toggling Admin Mode

1. Click on your user avatar in the top-right corner
2. In the dropdown menu, you'll see "Admin Mode (ON)" or "Admin Mode (OFF)"
3. Click on "Admin Mode" to toggle it
4. A success message will appear, and the page will refresh

### What Changes When Admin Mode is Disabled

When you disable admin mode, the following admin-only features are hidden:

**Navigation:**
- The "Admin" section in the sidebar disappears (with links to Admin Dashboard, Manage Images, Upload Photos, Span Metrics)

**UI Controls:**
- The access level control button (showing Public/Private/Shared status) disappears from span cards
- The user switcher control disappears from the dropdown and mobile menu
- Delete buttons disappear from spans and connections you don't own
- The "Fetch OSM data" button disappears from location cards
- Other admin-only controls are hidden throughout the interface

**Access Restrictions:**
- Attempting to access admin-only routes (like `/admin`) will show a 403 forbidden error
- Permission checks now treat you as a regular user
- You cannot access private spans that you don't own
- You cannot edit or delete spans you don't own

### When to Use It

**Use admin mode OFF to:**
- Test the interface as a regular user would see it
- Verify that private spans are truly inaccessible to non-owners
- Check that admin-only pages (like `/admin`) correctly deny access
- See what the interface looks like without admin-only UI elements
- Verify that permission-based features work correctly

**Use admin mode ON to:**
- Access the admin dashboard and tools
- Manage system settings and configurations
- Debug admin-related issues
- Restore normal admin access

## Technical Implementation

### Components

#### 1. User Model Methods

Located in `app/Models/User.php`:

- `canToggleAdminMode(): bool` - Returns true if the user is an admin (can toggle)
- `isAdminModeDisabled(): bool` - Returns true if admin mode is disabled in this session
- `getEffectiveAdminStatus(): bool` - Returns the admin status accounting for the toggle
- `disableAdminMode(): bool` - Disables admin mode for this session
- `enableAdminMode(): bool` - Enables admin mode for this session

#### 2. AdminModeController

Located in `app/Http/Controllers/AdminModeController.php`:

Provides four endpoints:

- `GET /admin-mode/status` - Get current admin mode status
- `POST /admin-mode/disable` - Disable admin mode
- `POST /admin-mode/enable` - Enable admin mode
- `POST /admin-mode/toggle` - Toggle admin mode on/off

All endpoints require authentication and return JSON responses with `success`, `message`, and status information.

#### 3. Middleware Updates

The following middleware were updated to use `getEffectiveAdminStatus()`:

- `AdminMiddleware` - Checks admin access for protected routes
- `SpanAccessMiddleware` - Checks admin access for span viewing

#### 4. Model Updates

The `Span` model's `hasPermission()` method was updated to use `getEffectiveAdminStatus()` instead of checking `is_admin` directly.

#### 5. User Interface

- `resources/views/components/shared/user-profile-info.blade.php` - Displays the toggle button in the user dropdown (desktop only)
- `resources/js/admin-mode-toggle.js` - Handles toggle functionality and UI updates

#### 6. Routes

Routes are defined in `routes/web.php`:

```php
Route::prefix('admin-mode')->name('admin-mode.')->group(function () {
    Route::get('/status', [AdminModeController::class, 'getStatus'])->name('status');
    Route::post('/disable', [AdminModeController::class, 'disable'])->name('disable');
    Route::post('/enable', [AdminModeController::class, 'enable'])->name('enable');
    Route::post('/toggle', [AdminModeController::class, 'toggle'])->name('toggle');
});
```

### Session Storage

The toggle state is stored in the session under the key `admin_mode_disabled_{user_id}` with a boolean value:

```php
session()->put('admin_mode_disabled_' . $user->id, true);  // Disable admin mode
session()->forget('admin_mode_disabled_' . $user->id);     // Enable admin mode
```

### Authorization

All admin mode endpoints verify that:

1. The user is authenticated
2. The user is actually an admin (via `canToggleAdminMode()`)
3. The requested action is valid (e.g., can't disable if already disabled)

Attempts by non-admin users to toggle admin mode are:
- Rejected with a 403 status code
- Logged as security warnings
- Prevented from having any effect

## Testing

Comprehensive tests are included in `tests/Feature/AdminModeToggleTest.php`:

- Tests for authorization (non-admins cannot toggle)
- Tests for state transitions (enable/disable/toggle)
- Tests for middleware integration
- Tests for session-based storage
- Tests for error handling
- Tests for permission checking

Run tests with:

```bash
./scripts/run-pest.sh tests/Feature/AdminModeToggleTest.php
```

## Security Considerations

1. **Session-scoped only**: The toggle affects only the current session, so multiple browser windows won't interfere with each other
2. **Non-destructive**: The database is never modified; the user's `is_admin` flag remains unchanged
3. **Audit logging**: All toggle actions are logged (see Laravel logs)
4. **Authorization checks**: All endpoints verify the user is actually an admin
5. **CSRF protection**: All POST endpoints are protected by Laravel's CSRF middleware
6. **Can be toggled back**: The admin can always toggle admin mode back on without any special process

## Limitations

1. The toggle only works for users with `is_admin = true` in the database
2. The toggle state is per-session and cleared on logout
3. If the user is no longer an admin (database flag changed), they lose the ability to toggle
4. The toggle does not affect API access (only web routes and authorization checks)
5. The mobile navigation does not currently show the toggle (could be added in future versions)

## Future Enhancements

Possible improvements for future versions:

1. Add mobile support for the toggle button
2. Add a "Remember my choice" option to persist toggle state across sessions (with confirmation)
3. Add admin mode status indicator in page header
4. Add ability to set default admin mode state
5. Add analytics to track when admins use this feature
6. Add notification when switching between modes
7. Add audit log entries (in addition to Laravel logs) for admin mode changes

## Troubleshooting

### Admin mode toggle doesn't appear

- Verify the user is actually an admin (check `is_admin` in database)
- Check that the JavaScript file is loaded (look for `admin-mode-toggle.js` in page source)
- Clear browser cache and reload

### Clicking toggle does nothing

- Check browser console for errors (F12 -> Console tab)
- Verify CSRF token is present in page (look for `<meta name="csrf-token">`)
- Check that you're logged in

### Can't access admin routes after toggling off

This is expected behavior! The toggle is working correctly. Re-enable admin mode by clicking the toggle again.

### Don't see status update immediately

The page reloads after toggling to reflect the new admin mode state. If this doesn't happen, manually refresh the page (F5).

## Related Documentation

- [Authorization and Permissions](./access-control-implementation.md)
- [Middleware Documentation](../README.md#middleware)
- [Admin Dashboard](../README.md#admin-interface)
