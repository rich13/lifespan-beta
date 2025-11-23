# User Roles Expansion Plan

**Status:** Planning / Future Implementation  
**Complexity:** Medium  
**Estimated Effort:** 2-3 days (Option 1) or 1.5-2 weeks (Option 2)  
**Date:** November 2024

## Overview

Currently, the application uses a simple binary `is_admin` boolean to determine user privileges. This document explores expanding the system to support multiple user roles (e.g., user, editor, admin) with different permission levels.

## Current State Analysis

### Usage Statistics
- **220 occurrences** of `is_admin` across **89 files**
- Distributed across:
  - Controllers: ~50 occurrences
  - Models: ~15 occurrences  
  - Views: ~20 occurrences
  - Middleware: 4 files
  - Tests: ~30 files
  - Traits: 3 occurrences

### Key Components

#### 1. Database Layer
- **Table:** `users`
- **Column:** `is_admin` (boolean, default false)
- **Migration:** `2024_02_07_000000_create_base_schema.php`

#### 2. User Model
```php
// app/Models/User.php
protected $fillable = ['email', 'password', 'is_admin', 'personal_span_id'];
protected $casts = ['is_admin' => 'boolean'];

// Good abstraction already exists!
public function getEffectiveAdminStatus(): bool
{
    if (!$this->is_admin) {
        return false;
    }
    // Handles admin mode toggle
}
```

#### 3. Middleware
- **AdminMiddleware** - Protects admin routes using `getEffectiveAdminStatus()`
- **UserSwitcherMiddleware** - Checks `is_admin` directly for user switching
- **SetsAccessMiddleware** - Uses `is_admin` for set access control
- **SpanAccessMiddleware** - Uses `getEffectiveAdminStatus()` abstraction

#### 4. Common Access Control Patterns

**Pattern 1: Visibility Filtering (most common)**
```php
if (!$user->is_admin) {
    $query->where(function ($q) use ($user) {
        $q->where('access_level', 'public')
          ->orWhere('owner_id', $user->id);
    });
}
```

**Pattern 2: Permission Checks**
```php
if (!auth()->user()->is_admin) {
    abort(403, 'Only administrators can...');
}
```

**Pattern 3: Feature Gating**
```php
if (auth()->user() && auth()->user()->is_admin) {
    // Show debug info, admin tools, etc.
}
```

**Pattern 4: Trait-Based Access**
```php
// app/Traits/HasRelationshipAccess.php
if ($user && $user->is_admin) {
    return true;
}
```

### Existing Abstraction Layer

**Good news:** Some abstraction already exists via helper methods:
- `getEffectiveAdminStatus()` - Used by AdminMiddleware and SpanAccessMiddleware
- `canToggleAdminMode()` - Checks if user can toggle admin mode

This provides a foundation to build upon.

## Proposed Solutions

### Option 1: Lightweight Role Enum (Recommended)

Add a simple `role` column with predefined roles: `user`, `editor`, `admin`.

#### Implementation

**Step 1: Database Migration**
```php
public function up()
{
    Schema::table('users', function (Blueprint $table) {
        $table->enum('role', ['user', 'editor', 'admin'])
              ->default('user')
              ->after('email');
    });
    
    // Migrate existing data
    DB::table('users')
        ->where('is_admin', true)
        ->update(['role' => 'admin']);
    
    DB::table('users')
        ->where('is_admin', false)
        ->update(['role' => 'user']);
}

public function down()
{
    Schema::table('users', function (Blueprint $table) {
        $table->dropColumn('role');
    });
}
```

**Step 2: User Model Updates**
```php
// app/Models/User.php

protected $fillable = [
    'email',
    'password',
    'role',
    'personal_span_id',
];

protected $casts = [
    'email_verified_at' => 'datetime',
    'password' => 'hashed',
    'metadata' => 'array',
];

// Role checking methods
public function isAdmin(): bool 
{ 
    return $this->role === 'admin'; 
}

public function isEditor(): bool 
{ 
    return in_array($this->role, ['editor', 'admin']); 
}

public function isUser(): bool 
{ 
    return $this->role === 'user'; 
}

// Permission methods
public function canAccessAdminPanel(): bool 
{ 
    return $this->isEditor(); 
}

public function canManageUsers(): bool 
{ 
    return $this->isAdmin(); 
}

public function canEditPublicContent(): bool 
{ 
    return $this->isEditor(); 
}

public function canAccessImportTools(): bool 
{ 
    return $this->isEditor(); 
}

public function canUseAiTools(): bool 
{ 
    return $this->isEditor(); 
}

public function canDeleteContent(): bool 
{ 
    return $this->isAdmin(); 
}

public function canManageSystemConfig(): bool 
{ 
    return $this->isAdmin(); 
}

// Backward compatibility accessor
public function getIsAdminAttribute(): bool 
{
    return $this->role === 'admin';
}

// Update existing method to use new role
public function getEffectiveAdminStatus(): bool
{
    if (!$this->isAdmin()) {
        return false;
    }
    
    // Check admin mode toggle
    if ($this->getMetadataValue('admin_mode_disabled')) {
        return false;
    }
    
    return true;
}
```

**Step 3: Create Editor Middleware**
```php
// app/Http/Middleware/EditorMiddleware.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EditorMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user() || !$request->user()->isEditor()) {
            abort(403, 'Editor or admin privileges required.');
        }

        return $next($request);
    }
}
```

**Step 4: Register Middleware**
```php
// app/Http/Kernel.php or bootstrap/app.php (Laravel 11)

protected $middlewareAliases = [
    'admin' => \App\Http\Middleware\AdminMiddleware::class,
    'editor' => \App\Http\Middleware\EditorMiddleware::class,
    // ... other middleware
];
```

**Step 5: Update AdminMiddleware to use new method**
```php
// app/Http/Middleware/AdminMiddleware.php

public function handle(Request $request, Closure $next): Response
{
    if (!$request->user() || !$request->user()->getEffectiveAdminStatus()) {
        abort(403, 'Access denied. Admin privileges required.');
    }

    return $next($request);
}
```

#### Gradual Migration Strategy

**Phase 1: Add role system (backward compatible)**
1. Run migration to add `role` column
2. Add role methods to User model
3. Keep `is_admin` working via accessor
4. Update factories and seeders

**Phase 2: Update admin UI**
1. Update user edit form to show role dropdown
2. Update user index to display roles
3. Add role filtering

**Phase 3: Update high-value areas**
1. Admin dashboard access (use `isEditor()`)
2. Import tools (use `canAccessImportTools()`)
3. AI generator (use `canUseAiTools()`)
4. Metrics (use `isEditor()`)
5. User management (keep as `isAdmin()`)
6. System tools (keep as `isAdmin()`)

**Phase 4: Update access control logic**
1. Replace permission checks in controllers
2. Update middleware usage in routes
3. Update trait-based access checks

**Phase 5: Update views**
1. Admin section visibility
2. Edit button visibility
3. Feature flags

**Phase 6: Deprecate `is_admin` column (optional)**
1. After thorough testing, consider removing column
2. Or keep for backward compatibility

#### Proposed Editor Role Permissions

What an "editor" can do:

✅ **Allowed:**
- Create and edit public spans
- Import data (books, films, music, etc.)
- Use AI YAML generator
- Access metrics and analytics
- View system history
- Use network explorer
- Upload photos
- Create collections
- Export data

❌ **Not Allowed:**
- Manage users (create, edit, delete)
- Delete public spans
- Change system configuration
- Manage connection types
- Manage span types
- Access admin tools (maintenance, etc.)
- Manage invitation codes
- Delete collections

#### Routes Update Example
```php
// Before
Route::middleware('admin')->prefix('admin')->group(function () {
    Route::get('/import/musicbrainz', ...);
    Route::get('/ai-yaml-generator', ...);
    Route::get('/metrics', ...);
    Route::get('/users', ...); // Admin only
});

// After
Route::middleware('editor')->prefix('admin')->group(function () {
    Route::get('/import/musicbrainz', ...); // Editors can import
    Route::get('/ai-yaml-generator', ...);  // Editors can use AI
    Route::get('/metrics', ...);            // Editors can view metrics
    
    // Admin-only section
    Route::middleware('admin')->group(function () {
        Route::get('/users', ...);          // Only admins manage users
        Route::get('/system-config', ...);  // Only admins configure system
    });
});
```

#### Testing Updates
```php
// Create test users with different roles
$admin = User::factory()->create(['role' => 'admin']);
$editor = User::factory()->create(['role' => 'editor']);
$user = User::factory()->create(['role' => 'user']);

// Test permission methods
$this->assertTrue($admin->isAdmin());
$this->assertTrue($admin->isEditor());
$this->assertTrue($editor->isEditor());
$this->assertFalse($editor->isAdmin());
$this->assertFalse($user->isEditor());
```

### Option 2: Full Permission System (Spatie Laravel-Permission)

Use a package like [Spatie Laravel-Permission](https://spatie.be/docs/laravel-permission) for a robust roles and permissions system.

#### Advantages
- Industry-standard package
- Highly flexible
- Can assign specific permissions to users
- Multiple roles per user
- Role hierarchies
- Permission caching

#### Database Structure
```
roles
- id
- name
- guard_name

permissions  
- id
- name
- guard_name

model_has_permissions
- permission_id
- model_type
- model_id

model_has_roles
- role_id
- model_type
- model_id

role_has_permissions
- permission_id
- role_id
```

#### Implementation Example
```php
// Installation
composer require spatie/laravel-permission

// Usage
$user->assignRole('editor');
$user->givePermissionTo('edit spans');

// In code
if ($user->can('edit spans')) {
    // Do something
}

// In Blade
@can('edit spans')
    <button>Edit</button>
@endcan

// Middleware
Route::middleware(['role:editor'])->group(function () {
    //
});
```

#### Proposed Permission Structure
```php
// Roles
- user (default)
- editor
- admin
- super-admin (optional)

// Permissions
- view spans
- create spans
- edit spans
- delete spans
- view users
- manage users
- access imports
- access ai tools
- access metrics
- manage system
```

## Comparison: Option 1 vs Option 2

| Aspect | Option 1: Enum Roles | Option 2: Spatie Permission |
|--------|---------------------|----------------------------|
| **Complexity** | Low | Medium-High |
| **Database Changes** | 1 column | 5+ tables |
| **Code Changes** | ~80 files | ~120+ files |
| **Implementation Time** | 2-3 days | 1.5-2 weeks |
| **Flexibility** | Fixed roles | Very flexible |
| **Performance** | Fast | Cached, still fast |
| **Learning Curve** | Minimal | Medium |
| **Maintenance** | Easy | Package dependency |
| **Backward Compatibility** | Easy to maintain | Requires migration |
| **Future Scalability** | Limited | Excellent |
| **Best For** | Small team, clear hierarchy | Large team, complex permissions |

## Recommendation

**Start with Option 1** (Lightweight Role Enum) for the following reasons:

1. **Quick Implementation**: 2-3 days vs 1-2 weeks
2. **Minimal Risk**: Easy to maintain backward compatibility
3. **Clear Use Case**: The need seems to be for a middle role between user and admin
4. **Existing Abstraction**: Already have `getEffectiveAdminStatus()` pattern
5. **Incremental**: Can implement gradually without breaking existing code
6. **Simple Mental Model**: Linear hierarchy (user < editor < admin)

You can always migrate to Option 2 later if you need:
- More granular permissions
- Complex permission combinations
- Multiple roles per user
- Dynamic permission assignment

## Implementation Checklist

### Phase 1: Foundation (Day 1)
- [ ] Create migration for `role` column
- [ ] Update User model with role methods
- [ ] Add backward compatibility accessor for `is_admin`
- [ ] Create EditorMiddleware
- [ ] Register middleware
- [ ] Update UserFactory
- [ ] Update DatabaseSeeder
- [ ] Run tests to ensure nothing breaks

### Phase 2: Admin UI (Day 1-2)
- [ ] Update user edit form (dropdown for role selection)
- [ ] Update user index page (show role badges)
- [ ] Add role filtering to user list
- [ ] Update UserController validation
- [ ] Test user role changes

### Phase 3: Strategic Updates (Day 2-3)
- [ ] Update admin dashboard access
- [ ] Update import tool routes/middleware
- [ ] Update AI generator access
- [ ] Update metrics access
- [ ] Keep user management admin-only
- [ ] Keep system tools admin-only
- [ ] Update relevant views

### Phase 4: Testing (Day 3)
- [ ] Update feature tests
- [ ] Add role-specific tests
- [ ] Test all admin features with editor role
- [ ] Test permission boundaries
- [ ] Update documentation

### Phase 5: Documentation
- [ ] Document role permissions
- [ ] Update README if needed
- [ ] Add comments to permission methods
- [ ] Create migration guide for existing admins

## Files Requiring Changes (Estimated)

### Critical (must change for Option 1)
- `database/migrations/[new]_add_role_to_users_table.php`
- `app/Models/User.php`
- `app/Http/Middleware/EditorMiddleware.php`
- `app/Http/Controllers/Admin/UserController.php`
- `resources/views/admin/users/edit.blade.php`
- `resources/views/admin/users/index.blade.php`
- `database/factories/UserFactory.php`

### High Priority (should change for best results)
- `routes/web.php` (add editor middleware to certain routes)
- `app/Http/Controllers/CollectionsController.php`
- Import controller checks (~10 files)
- Admin dashboard routing
- Various view files showing admin features

### Medium Priority (can change gradually)
- Access control checks in controllers (~30 files)
- View conditionals (~15 files)
- Test factories (~30 files)

### Low Priority (can remain as-is initially)
- Trait-based checks (work via accessor)
- Legacy view checks (work via accessor)
- Some test assertions

## Risks & Mitigation

### Risk 1: Breaking Existing Functionality
**Mitigation:** Use accessor pattern to maintain `is_admin` compatibility during transition

### Risk 2: Incomplete Migration
**Mitigation:** Phase the implementation, test thoroughly at each phase

### Risk 3: Confusing Permission Model
**Mitigation:** Clear documentation, consistent naming, simple hierarchy

### Risk 4: Test Failures
**Mitigation:** Update test factories first, run full test suite frequently

## Future Considerations

### Potential Additional Roles
- **Contributor**: Can create/edit own content only
- **Moderator**: Can moderate content but not access system tools  
- **API User**: Programmatic access only
- **Viewer**: Read-only access to specific data

### Migration to Full Permission System
If you later need Option 2, the migration path would be:
1. Install Spatie package
2. Create permissions matching current role abilities
3. Migrate role column to role assignments
4. Replace `isEditor()` calls with `can('permission')` 
5. Remove role methods

## Questions to Answer Before Implementation

1. **What specific tasks does an "editor" need to do?**
   - Answer determines which routes/features need editor access

2. **Should editors be able to delete their own content?**
   - Affects permission granularity

3. **Do you need editors to have limited admin panel access?**
   - Might need separate editor dashboard vs full admin dashboard

4. **Will editors manage other users' public content?**
   - Affects ownership checks in controllers

5. **How many editors do you expect?**
   - Affects whether to build management UI or manually assign

## References

- Current admin middleware: `app/Http/Middleware/AdminMiddleware.php`
- User model: `app/Models/User.php`
- Admin routes: `routes/web.php` (line 893+)
- Spatie Permission docs: https://spatie.be/docs/laravel-permission
- Laravel Authorization docs: https://laravel.com/docs/authorization

## Notes

- The `getEffectiveAdminStatus()` method already provides admin mode toggle functionality
- Many areas already use abstraction via this method
- The `is_admin` column can remain in database for backward compatibility
- Consider keeping the column indefinitely as a "source of truth" that feeds the role enum

---

**Last Updated:** November 21, 2024  
**Next Review:** When editor role requirement becomes concrete





