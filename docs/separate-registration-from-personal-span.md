# Separating Registration from Personal Span Creation

## Current Flow

1. User submits registration form (name, email, password, birth date)
2. `RegisterRequest::register()` is called
3. User record is created in database
4. **Personal span is created immediately** via `$user->createPersonalSpan()`
5. Email verification is sent
6. Admin approval request email is sent
7. User waits for admin approval

## Proposed Flow

1. User submits registration form (name, email, password, birth date)
2. `RegisterRequest::register()` is called
3. User record is created in database
4. **Registration data (name, birth date) is stored in user metadata** (temporary)
5. Email verification is sent
6. Admin approval request email is sent
7. User waits for admin approval
8. **When admin approves user → Personal span is created** using stored metadata

## Benefits

- **Data integrity**: No spans exist for unapproved users
- **Cleaner database**: Unapproved registrations don't create orphaned spans
- **Better admin experience**: Admins can see pending registrations without spans cluttering the system
- **Easier cleanup**: Rejecting a user only requires deleting the user record, not spans

## Required Changes

### 1. Store Registration Data in User Metadata

**File**: `app/Http/Requests/Auth/RegisterRequest.php`

**Current** (line ~188):
```php
// Create personal span for the user using the User model's method
$personalSpan = $user->createPersonalSpan([
    'name' => $this->name,
    'birth_year' => $this->birth_year,
    'birth_month' => $this->birth_month,
    'birth_day' => $this->birth_day,
]);
```

**Change to**:
```php
// Store registration data in metadata for later use when approved
$metadata = $user->metadata ?? [];
$metadata['pending_registration'] = [
    'name' => $this->name,
    'birth_year' => $this->birth_year,
    'birth_month' => $this->birth_month,
    'birth_day' => $this->birth_day,
];
$user->metadata = $metadata;
$user->save();

Log::info('Registration data stored in user metadata', [
    'user_id' => $user->id,
    'email' => $user->email,
]);
```

### 2. Create Personal Span on Approval

**File**: `app/Http/Controllers/Admin/UserController.php`

**Current** (line ~66):
```php
public function approve(User $user)
{
    if ($user->approved_at) {
        return redirect()->route('admin.users.show', $user)
            ->with('status', 'User is already approved.');
    }

    $user->update([
        'approved_at' => now(),
    ]);
    // ... send welcome email
}
```

**Change to**:
```php
public function approve(User $user)
{
    if ($user->approved_at) {
        return redirect()->route('admin.users.show', $user)
            ->with('status', 'User is already approved.');
    }

    $user->update([
        'approved_at' => now(),
    ]);

    // Create personal span if registration data exists and span doesn't exist
    if (!$user->personal_span_id && isset($user->metadata['pending_registration'])) {
        try {
            $registrationData = $user->metadata['pending_registration'];
            $personalSpan = $user->createPersonalSpan([
                'name' => $registrationData['name'],
                'birth_year' => $registrationData['birth_year'] ?? null,
                'birth_month' => $registrationData['birth_month'] ?? null,
                'birth_day' => $registrationData['birth_day'] ?? null,
            ]);

            // Remove pending registration data from metadata
            $metadata = $user->metadata;
            unset($metadata['pending_registration']);
            $user->metadata = $metadata;
            $user->save();

            Log::info('Personal span created during user approval', [
                'user_id' => $user->id,
                'span_id' => $personalSpan->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create personal span during approval', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            // Continue with approval even if span creation fails
        }
    }

    // ... send welcome email
}
```

### 3. Handle Edge Cases

#### 3a. User Name Accessor

**File**: `app/Models/User.php` (line ~154)

**Current**:
```php
public function getNameAttribute(): string
{
    return $this->personalSpan?->name ?? 'Unknown User';
}
```

**Change to**:
```php
public function getNameAttribute(): string
{
    // If personal span exists, use it
    if ($this->personalSpan) {
        return $this->personalSpan->name;
    }
    
    // If pending registration data exists, use that
    if (isset($this->metadata['pending_registration']['name'])) {
        return $this->metadata['pending_registration']['name'];
    }
    
    return 'Unknown User';
}
```

#### 3b. Views That Check for Personal Span

Many views check `$user->personalSpan` before displaying information. These will need to handle the case where a user is approved but span creation failed, or where span doesn't exist yet.

**Files to review**:
- `resources/views/profile/edit.blade.php` - Shows user name and birth date
- `resources/views/components/home/at-your-age-card.blade.php` - Requires personal span
- `resources/views/admin/users/show.blade.php` - Displays user information
- `resources/views/admin/users/index.blade.php` - Lists users with names

**Pattern to use**:
```php
@if($user->personalSpan)
    {{ $user->personalSpan->name }}
@elseif(isset($user->metadata['pending_registration']))
    {{ $user->metadata['pending_registration']['name'] }} <span class="badge bg-warning">Pending</span>
@else
    {{ $user->name }} <!-- Falls back to accessor -->
@endif
```

#### 3c. Login Flow

**File**: `app/Http/Controllers/Auth/EmailFirstAuthController.php`

The login flow calls `$user->ensureDefaultSetsExist()` which requires a personal span. This needs to be updated to handle users without personal spans.

**Current** (line ~118):
```php
$user->ensureDefaultSetsExist();
```

**Change to**:
```php
// Only ensure default sets if user has a personal span
if ($user->personalSpan) {
    $user->ensureDefaultSetsExist();
}
```

#### 3d. Default Sets Creation

**File**: `app/Models/User.php` (line ~249)

The `createDefaultSets()` method requires a personal span. This is fine since it's only called when creating the span, but we should verify it's not called elsewhere.

### 4. Migration Strategy

For existing users who are unapproved but already have personal spans:

**Option A**: Leave them as-is (they'll be cleaned up when approved/rejected)
**Option B**: Create a migration to remove personal spans from unapproved users

**Migration script**:
```php
// Remove personal spans from unapproved users
$unapprovedUsers = User::whereNull('approved_at')
    ->whereNotNull('personal_span_id')
    ->get();

foreach ($unapprovedUsers as $user) {
    $span = Span::find($user->personal_span_id);
    if ($span && $span->is_personal_span) {
        // Store registration data in metadata
        $metadata = $user->metadata ?? [];
        $metadata['pending_registration'] = [
            'name' => $span->name,
            'birth_year' => $span->start_year,
            'birth_month' => $span->start_month,
            'birth_day' => $span->start_day,
        ];
        $user->metadata = $metadata;
        $user->personal_span_id = null;
        $user->save();
        
        // Delete the span
        $span->delete();
    }
}
```

### 5. Testing Considerations

1. **Registration flow**: Verify user is created without span
2. **Approval flow**: Verify span is created on approval
3. **Name accessor**: Verify it works with pending registration data
4. **Views**: Verify they handle missing personal spans gracefully
5. **Login**: Verify login works for approved users without spans (edge case)
6. **Edge cases**: 
   - User approved but span creation fails
   - User with pending registration data but no span
   - User with both pending data and existing span

### 6. Potential Issues

1. **Email templates**: May reference `$user->name` which should work via accessor
2. **Admin notifications**: May need to use metadata for name display
3. **Slack notifications**: May need to handle pending registration data
4. **API endpoints**: May need to handle users without personal spans
5. **Queries**: Any queries that assume all users have personal spans will need updates

### 7. Files to Modify

**Core Changes**:
- `app/Http/Requests/Auth/RegisterRequest.php` - Remove span creation, store metadata
- `app/Http/Controllers/Admin/UserController.php` - Add span creation on approval
- `app/Models/User.php` - Update `getNameAttribute()` accessor

**Views to Review** (may need updates):
- `resources/views/profile/edit.blade.php`
- `resources/views/admin/users/show.blade.php`
- `resources/views/admin/users/index.blade.php`
- `resources/views/components/home/at-your-age-card.blade.php`
- `resources/views/emails/registration-approval-request.blade.php`

**Controllers to Review**:
- `app/Http/Controllers/Auth/EmailFirstAuthController.php` - Login flow
- `app/Http/Controllers/Auth/AuthenticatedSessionController.php` - Session handling
- `app/Http/Controllers/Auth/SessionBridgeController.php` - Session bridge

**Tests to Update**:
- `tests/Feature/Auth/InvitationCodeTest.php` - Registration tests
- Any tests that create users and expect personal spans

## Implementation Order

1. Update `getNameAttribute()` accessor to handle pending registration
2. Update registration to store metadata instead of creating span
3. Update approval method to create span
4. Update views to handle missing personal spans
5. Update login/authentication flows
6. Add migration for existing unapproved users
7. Update tests
8. Test end-to-end flow

## Rollback Plan

If issues arise, we can:
1. Revert to creating spans during registration
2. Keep the metadata storage as a backup
3. Run a cleanup script to create spans for approved users missing them
