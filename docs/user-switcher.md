# User Switcher Functionality

The User Switcher is a feature that allows admin users to temporarily log in as another user without knowing their password. This is useful for:

- Troubleshooting issues that a specific user is experiencing
- Testing how the application appears to different users
- Performing actions on behalf of a user when necessary

## How It Works

1. **Admin Access**: Only users with `is_admin = true` can access the user switcher.
2. **Session-Based**: The switcher uses Laravel's session to store the original admin user's ID.
3. **UI Integration**: The user switcher is integrated into the user dropdown in the top navigation bar.

## User Interface

- Admin users see a "SWITCH TO USER" section in their user dropdown
- The section contains a list of all users in the system
- Each user entry shows their email and admin status (if applicable)
- When in a switched session, a "Switch Back to Admin" button appears

## Implementation Details

### Routes

The user switcher functionality is implemented with three main routes:

```php
// Get list of users
Route::get('/user-switcher/users', [UserSwitcherController::class, 'getUserList'])
    ->name('user-switcher.users');

// Switch to another user
Route::post('/user-switcher/switch/{userId}', [UserSwitcherController::class, 'switchToUser'])
    ->name('user-switcher.switch');

// Switch back to admin
Route::post('/user-switcher/switch-back', [UserSwitcherController::class, 'switchBack'])
    ->name('user-switcher.switch-back');
```

### Middleware

A custom middleware (`UserSwitcherMiddleware`) protects these routes:

```php
public function handle(Request $request, Closure $next): Response
{
    // Allow access if the user is an admin OR if there's an admin_user_id in the session
    if ($request->user() && ($request->user()->is_admin || session()->has('admin_user_id'))) {
        return $next($request);
    }
    
    // Otherwise, deny access
    abort(403, 'Access denied. Admin privileges or admin session required.');
}
```

### Controller

The `UserSwitcherController` handles the logic:

1. `switchToUser`: Stores the admin's ID in the session and logs in as the target user
2. `switchBack`: Retrieves the admin's ID from the session and logs back in as the admin
3. `getUserList`: Returns a list of all users with indicators for the current user and admin users

### Frontend

The user switcher UI is implemented using:

- Blade templates for the dropdown structure
- jQuery for dynamic loading of users and interaction
- Bootstrap for styling

## Security Considerations

1. **Access Control**: Only admin users can initiate a user switch
2. **Session Protection**: The original admin user's ID is stored securely in the session
3. **Middleware Protection**: All user switcher routes are protected by middleware
4. **UI Visibility**: The user switcher UI is only visible to admin users

## Testing

The user switcher functionality is covered by comprehensive tests in `tests/Feature/UserSwitcherTest.php`:

1. Admin users can access the user switcher API
2. Non-admin users cannot access the user switcher API
3. Admin users can switch to another user
4. Non-admin users cannot switch to another user
5. A switched user can switch back to the admin
6. The user switcher UI is present for admin users
7. The user switcher UI is not present for non-admin users 