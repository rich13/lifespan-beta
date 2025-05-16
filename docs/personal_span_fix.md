# Personal Span Issue Documentation

## Problem Description

Users were experiencing an issue where their personal span (the span representing their own profile) was being switched to a different span. Specifically, the admin user's personal span was sometimes being switched from "Richard Northover" to other spans like "Arielle Northover" or "Aiden Northover".

## Root Causes

After investigation, we identified several contributing factors:

1. **Multiple Personal Spans**: The database contained multiple spans marked as `is_personal_span = true` for the same user, which created ambiguity.

2. **Missing Database Constraint**: There was no constraint to enforce that a user could only have one personal span.

3. **User Switching**: When using the admin user switcher feature, the personal span relationship wasn't properly handled during the switch/switch-back process.

4. **Seeder Logic**: The database seeder used a problematic query condition that didn't check for existing personal spans properly.

## Implemented Fixes

### 1. Database-Level Fixes

- Created a migration to add a unique partial index:
  ```sql
  CREATE UNIQUE INDEX personal_span_owner_unique ON spans (owner_id) WHERE is_personal_span = true
  ```
  
- Cleaned up duplicate personal spans, keeping only the most recently updated one for each user.
  
- Ensured user's `personal_span_id` points to their correct personal span.

### 2. Model-Level Fixes

- Added an `ensureCorrectPersonalSpan()` method to the User model that:
  - Checks if the user has a valid personal span assigned
  - Finds and assigns the correct personal span if needed
  - Creates a new personal span if none exists (optional)

### 3. Middleware Fixes

- Updated the `LoadUserRelations` middleware to call `ensureCorrectPersonalSpan()` on every request when the user is authenticated.
- Added error handling and logging to catch any issues.

### 4. User Switching Fixes

- Modified the `UserSwitcherController` to:
  - Properly load the target user with their personal span
  - Call `ensureCorrectPersonalSpan()` before switching
  - Handle edge cases where spans might be missing

### 5. Maintenance Command

- Created an `app:fix-personal-spans` command that:
  - Identifies users with multiple personal spans
  - Fixes users with missing personal spans
  - Corrects invalid personal span links
  - Can be run in dry-run mode to see changes without applying them

## Prevention of Future Issues

1. **Database Constraint**: The unique index enforces that a user can only have one personal span.

2. **Model Observer**: The `SpanObserver` ensures that when a span is marked as personal, no other spans for the same user are also marked as personal.

3. **Improved Seeder Logic**: Updated the seeder to properly check for existing personal spans.

4. **Middleware Protection**: The middleware adds a runtime check to ensure the correct personal span is always used.

## Testing

After implementing these fixes:

1. The diagnostic command finds no issues when run.
2. User switching works correctly without changing personal spans.
3. Each user maintains their correct personal span across sessions.

## Additional Notes

- The issue was particularly noticeable for admin users who frequently use the user switching feature.
- The personal span relationship is critical because it's used for displaying user information throughout the application.
- For further improvements, consider adding more comprehensive validation when spans are created or updated. 