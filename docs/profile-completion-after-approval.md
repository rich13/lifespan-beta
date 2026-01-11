# Profile Completion After Approval & Verification

## Overview

Move name and date of birth collection to a profile completion step that appears after the user has:
1. Registered (email + password only)
2. Verified their email
3. Been approved by admin
4. Logged in for the first time

This approach is cleaner than storing data in metadata because:
- No temporary data storage needed
- Natural onboarding flow
- User provides info when they're ready to use the system
- Simpler implementation

## New Flow

### Registration
1. User enters email + password
2. User record created (no personal span)
3. Email verification sent
4. Admin approval request sent

### After Approval & Verification
1. User logs in
2. System checks: Does user have personal span?
3. If no → Redirect to profile completion page
4. User enters name + DOB
5. Personal span created
6. User redirected to home

## Required Changes

### 1. Simplify Registration Form

**File**: `resources/views/auth/register.blade.php`

**Remove**:
- Name field (lines 26-33)
- Birth date fields (lines 35-81)

**Keep**:
- Email (readonly)
- Password
- Password confirmation
- Honeypot field

### 2. Update Registration Request

**File**: `app/Http/Requests/Auth/RegisterRequest.php`

**Current** (lines ~43-59):
```php
$rules = [
    'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
    'password' => ['required', 'string', 'min:8', 'confirmed'],
    'name' => ['required', 'string', 'max:255'],
    'birth_year' => ['required', 'integer', 'min:1900', 'max:' . date('Y')],
    'birth_month' => ['required', 'integer', 'min:1', 'max:12'],
    'birth_day' => ['required', 'integer', 'min:1', 'max:31'],
    // ...
];
```

**Change to**:
```php
$rules = [
    'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
    'password' => ['required', 'string', 'min:8', 'confirmed'],
    'website' => ['nullable'], // Honeypot - checked in authorize()
    // Name and DOB removed - collected during profile completion
];
```

**Current** (lines ~188-193):
```php
// Create personal span for the user using the User model's method
$personalSpan = $user->createPersonalSpan([
    'name' => $this->name,
    'birth_year' => $this->birth_year,
    'birth_month' => $this->birth_month,
    'birth_day' => $this->birth_day,
]);
```

**Remove entirely** - no span creation during registration.

### 3. Create Profile Completion Controller

**New File**: `app/Http/Controllers/Auth/CompleteProfileController.php`

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class CompleteProfileController extends Controller
{
    /**
     * Show the profile completion form.
     */
    public function show(): View|RedirectResponse
    {
        $user = Auth::user();
        
        // If user already has a personal span, redirect to home
        if ($user->personal_span_id) {
            return redirect()->route('home');
        }
        
        return view('auth.complete-profile');
    }

    /**
     * Handle profile completion submission.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'birth_year' => ['required', 'integer', 'min:1900', 'max:' . date('Y')],
            'birth_month' => ['required', 'integer', 'min:1', 'max:12'],
            'birth_day' => ['required', 'integer', 'min:1', 'max:31'],
        ]);

        $user = Auth::user();
        
        // Double-check user doesn't already have a personal span
        if ($user->personal_span_id) {
            return redirect()->route('home')
                ->with('status', 'Your profile is already complete.');
        }

        try {
            // Create personal span
            $personalSpan = $user->createPersonalSpan([
                'name' => $validated['name'],
                'birth_year' => $validated['birth_year'],
                'birth_month' => $validated['birth_month'],
                'birth_day' => $validated['birth_day'],
            ]);

            Log::info('Profile completed - personal span created', [
                'user_id' => $user->id,
                'span_id' => $personalSpan->id,
            ]);

            return redirect()->route('home')
                ->with('status', 'Welcome to Lifespan! Your profile has been set up.');
        } catch (\Exception $e) {
            Log::error('Failed to create personal span during profile completion', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return back()
                ->withInput()
                ->withErrors(['name' => 'There was an error setting up your profile. Please try again.']);
        }
    }
}
```

### 4. Create Profile Completion View

**New File**: `resources/views/auth/complete-profile.blade.php`

```blade
@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card">
                <div class="card-body">
                    <h2 class="card-title text-center mb-4">Complete Your Profile</h2>
                    
                    <p class="text-muted mb-4">
                        Welcome to Lifespan! To get started, please tell us a bit about yourself.
                    </p>
                    
                    <form method="POST" action="{{ route('profile.complete') }}">
                        @csrf
                        
                        <div class="mb-3">
                            <label for="name" class="form-label">Name</label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror" 
                                   id="name" name="name" value="{{ old('name') }}" required autofocus>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label class="form-label">Birth Date</label>
                            <div class="row g-2">
                                <div class="col-sm-4">
                                    <select class="form-select @error('birth_year') is-invalid @enderror" 
                                            name="birth_year" required>
                                        <option value="">Year</option>
                                        @for ($year = date('Y'); $year >= 1900; $year--)
                                            <option value="{{ $year }}" {{ old('birth_year') == $year ? 'selected' : '' }}>
                                                {{ $year }}
                                            </option>
                                        @endfor
                                    </select>
                                </div>
                                <div class="col-sm-4">
                                    <select class="form-select @error('birth_month') is-invalid @enderror" 
                                            name="birth_month" required>
                                        <option value="">Month</option>
                                        @foreach (range(1, 12) as $month)
                                            <option value="{{ $month }}" {{ old('birth_month') == $month ? 'selected' : '' }}>
                                                {{ date('F', mktime(0, 0, 0, $month, 1)) }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-sm-4">
                                    <select class="form-select @error('birth_day') is-invalid @enderror" 
                                            name="birth_day" required>
                                        <option value="">Day</option>
                                        @foreach (range(1, 31) as $day)
                                            <option value="{{ $day }}" {{ old('birth_day') == $day ? 'selected' : '' }}>
                                                {{ $day }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            @error('birth_year')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                            @error('birth_month')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                            @error('birth_day')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <button type="submit" class="btn btn-primary w-100">Complete Profile</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
```

### 5. Create Middleware to Enforce Profile Completion

**New File**: `app/Http/Middleware/RequireProfileCompletion.php`

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RequireProfileCompletion
{
    /**
     * Handle an incoming request.
     *
     * Redirect authenticated users without personal spans to profile completion.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $user = Auth::user();
            
            // Skip if user already has a personal span
            if ($user->personal_span_id) {
                return $next($request);
            }
            
            // Skip if already on profile completion page
            if ($request->routeIs('profile.complete') || $request->routeIs('profile.complete.store')) {
                return $next($request);
            }
            
            // Skip for logout route
            if ($request->routeIs('logout')) {
                return $next($request);
            }
            
            // Redirect to profile completion
            return redirect()->route('profile.complete');
        }

        return $next($request);
    }
}
```

### 6. Register Middleware

**File**: `app/Http/Kernel.php`

Add to `$middlewareAliases`:
```php
'profile.complete' => \App\Http\Middleware\RequireProfileCompletion::class,
```

### 7. Apply Middleware to Authenticated Routes

**File**: `routes/web.php`

Apply to authenticated routes (but exclude profile completion routes):

```php
Route::middleware(['auth', 'verified', 'profile.complete'])->group(function () {
    // All authenticated routes except profile completion
});

// Profile completion routes (auth required, but skip profile.complete middleware)
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('profile/complete', [CompleteProfileController::class, 'show'])
        ->name('profile.complete');
    Route::post('profile/complete', [CompleteProfileController::class, 'store'])
        ->name('profile.complete.store');
});
```

**Alternative approach**: Apply middleware globally to `auth` routes and exclude specific routes:

```php
// In Kernel.php, add to $middlewareGroups['web']:
\App\Http\Middleware\RequireProfileCompletion::class,

// Then in routes, exclude profile completion:
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('profile/complete', ...)
        ->withoutMiddleware([\App\Http\Middleware\RequireProfileCompletion::class]);
    Route::post('profile/complete', ...)
        ->withoutMiddleware([\App\Http\Middleware\RequireProfileCompletion::class]);
});
```

### 8. Update Login Flow

**File**: `app/Http/Controllers/Auth/EmailFirstAuthController.php`

**Current** (line ~118):
```php
$user->ensureDefaultSetsExist();
```

**Change to**:
```php
// Only ensure default sets if user has a personal span
// If no personal span, middleware will redirect to profile completion
if ($user->personal_span_id) {
    $user->ensureDefaultSetsExist();
}
```

**File**: `app/Http/Controllers/Auth/AuthenticatedSessionController.php`

**Current** (line ~34):
```php
$user->ensureDefaultSetsExist();
```

**Change to**:
```php
// Only ensure default sets if user has a personal span
if ($user->personal_span_id) {
    $user->ensureDefaultSetsExist();
}
```

### 9. Update User Name Accessor

**File**: `app/Models/User.php`

**Current** (line ~154):
```php
public function getNameAttribute(): string
{
    return $this->personalSpan?->name ?? 'Unknown User';
}
```

**Keep as-is** - it already handles missing personal span gracefully.

### 10. Update Views That Assume Personal Span Exists

Views that check `$user->personalSpan` should already handle null cases, but verify:

**Files to check**:
- `resources/views/components/home/at-your-age-card.blade.php` - Already checks `if (!$user || !$user->personalSpan)`
- `resources/views/profile/edit.blade.php` - May need null checks
- `resources/views/admin/users/show.blade.php` - May need null checks

**Pattern**:
```php
@if($user->personalSpan)
    {{ $user->personalSpan->name }}
@else
    <span class="text-muted">Profile not completed</span>
@endif
```

### 11. Update Routes

**File**: `routes/web.php`

Add profile completion routes in authenticated section:

```php
// Profile completion (must be authenticated and verified, but before profile.complete middleware)
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('profile/complete', [App\Http\Controllers\Auth\CompleteProfileController::class, 'show'])
        ->name('profile.complete');
    Route::post('profile/complete', [App\Http\Controllers\Auth\CompleteProfileController::class, 'store'])
        ->name('profile.complete.store');
});

// All other authenticated routes (with profile completion check)
Route::middleware(['auth', 'verified', 'profile.complete'])->group(function () {
    // ... existing authenticated routes
});
```

## Implementation Order

1. ✅ Create `CompleteProfileController`
2. ✅ Create `complete-profile.blade.php` view
3. ✅ Create `RequireProfileCompletion` middleware
4. ✅ Register middleware in `Kernel.php`
5. ✅ Add routes for profile completion
6. ✅ Update registration form (remove name/DOB)
7. ✅ Update `RegisterRequest` (remove name/DOB validation, remove span creation)
8. ✅ Update login flows (conditional `ensureDefaultSetsExist()`)
9. ✅ Apply middleware to authenticated routes
10. ✅ Test end-to-end flow
11. ✅ Update tests

## Testing Checklist

- [ ] User can register with just email/password
- [ ] No personal span created during registration
- [ ] User can verify email
- [ ] User can be approved by admin
- [ ] User is redirected to profile completion on first login
- [ ] User cannot access other pages until profile is complete
- [ ] User can complete profile (name + DOB)
- [ ] Personal span is created on profile completion
- [ ] User is redirected to home after completion
- [ ] User can access all pages after profile completion
- [ ] User name accessor works before/after profile completion
- [ ] Views handle missing personal span gracefully

## Edge Cases

1. **User logs out during profile completion**: Should redirect back to profile completion on next login
2. **User somehow has personal_span_id but span doesn't exist**: Middleware should handle gracefully
3. **Profile completion fails**: Should show error and allow retry
4. **User tries to access profile completion when already complete**: Should redirect to home
5. **Admin creates user from span**: Should skip profile completion (span already exists)

## Migration for Existing Users

For existing unapproved users who already have personal spans:
- Leave them as-is (they'll complete normally when approved)
- Or create a migration to remove spans from unapproved users (optional cleanup)

## Benefits of This Approach

1. ✅ **Simpler**: No metadata storage needed
2. ✅ **Cleaner**: No temporary data to manage
3. ✅ **Better UX**: Natural onboarding flow
4. ✅ **More secure**: No spans for unapproved users
5. ✅ **Easier cleanup**: Rejecting user = delete user only
6. ✅ **Flexible**: User provides info when ready

## Potential Issues

1. **Views expecting personal span**: Need null checks (most already have them)
2. **API endpoints**: May need to handle users without personal spans
3. **Admin views**: May need to show "Profile not completed" status
4. **Email templates**: Should work fine (use `$user->name` accessor)
