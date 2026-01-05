# Group-Based Ownership Proposal

## Overview

This proposal outlines a system for allowing spans to be owned by groups (particularly organisations) rather than just individual users. This enables scenarios like:
- An organisation (e.g., "Science Museum") owning exhibition spans
- Group admins managing organisation-owned spans
- Users being members of both collaborative sharing groups and organisational groups

## Current State

### Personal Spans
- Each user has one personal span (type='person', is_personal_span=true)
- Linked via `users.personal_span_id`
- Owned by the user via `spans.owner_id`

### Groups (Current Purpose: Collaborative Sharing)
- Groups exist for collaborative span sharing (e.g., "Northover Family")
- When a user joins a group, their personal span is automatically shared with the group
- Groups have permissions on spans via `span_permissions` table
- Groups have an `owner_id` (user who created/manages the group)
- Groups have members via `group_user` pivot table

### Ownership Model
- Currently: `spans.owner_id` → `users.id` (always a user)
- Foreign key constraint enforces this
- ~125+ places check `owner_id === user->id`

## Problem Statement

We need to support:
1. **Organisational Ownership**: An organisation span (type='organisation') should be able to own other spans (e.g., exhibitions)
2. **Group-Based Management**: Users who are admins of an organisation should be able to manage spans owned by that organisation
3. **Dual-Purpose Groups**: Groups should support both:
   - **Collaborative sharing** (current): Users share their personal spans with group members
   - **Organisational ownership** (new): Group represents an organisation and owns spans

### Example Scenarios

**Scenario 1: Collaborative Sharing Group**
- Group: "Northover Family"
- `organisation_span_id = null`
- Members share their personal spans
- Permissions managed via `span_permissions`

**Scenario 2: Organisational Group**
- Group: "Science Museum"
- `organisation_span_id = <science museum organisation span id>`
- Group owns exhibition spans
- Group owner + organisation admins can manage owned spans

## Proposed Solution

### Unified Group Model

Groups can serve two purposes, distinguished by the presence of `organisation_span_id`:

1. **Collaborative Sharing Groups** (`organisation_span_id = null`)
   - Members share their personal/user-owned spans
   - Permissions granted via `span_permissions` table
   - Example: "Northover Family"

2. **Organisational Groups** (`organisation_span_id` set)
   - Group is linked to an organisation span
   - Group can own spans (via `group_owner_id` on spans)
   - Group owner + organisation admins can manage owned spans
   - Example: "Science Museum" group linked to "Science Museum" organisation span

### Schema Changes

#### 1. Link Groups to Organisation Spans

```php
Schema::table('groups', function (Blueprint $table) {
    $table->uuid('organisation_span_id')->nullable()->after('owner_id');
    $table->foreign('organisation_span_id')->references('id')->on('spans')->nullOnDelete();
    $table->index('organisation_span_id');
    
    // Add comment for clarity
    $table->comment('If set, this group represents an organisation. Otherwise, it is a collaborative sharing group.');
});
```

**Rationale:**
- `organisation_span_id = null` → Collaborative sharing group
- `organisation_span_id` set → Organisational group
- Nullable allows existing groups to continue working
- `nullOnDelete()` prevents cascade issues if organisation span is deleted

#### 2. Allow Spans to be Owned by Groups

```php
Schema::table('spans', function (Blueprint $table) {
    $table->uuid('group_owner_id')->nullable()->after('owner_id');
    $table->foreign('group_owner_id')->references('id')->on('groups')->nullOnDelete();
    $table->index('group_owner_id');
    
    // Note: We allow both owner_id and group_owner_id to be set
    // owner_id = creator, group_owner_id = group that owns it
});
```

**Rationale:**
- `owner_id` remains the creator (always set)
- `group_owner_id` indicates group ownership (nullable)
- Both can be set: user created it, group owns it
- Allows tracking of both creator and owner

### Permission Logic

#### Updated Permission Check Flow

When checking if a user can manage a span:

```php
// In Span::hasPermission() or SpanPolicy::update()

// 1. Admin override (existing)
if ($user->getEffectiveAdminStatus()) {
    return true;
}

// 2. Direct user ownership (existing)
if ($this->owner_id === $user->id) {
    return true;
}

// 3. Group ownership (NEW)
if ($this->group_owner_id) {
    $group = Group::find($this->group_owner_id);
    
    // Group owner can manage
    if ($group->owner_id === $user->id) {
        return true;
    }
    
    // If group represents an organisation, check organisation admin access
    if ($group->organisation_span_id) {
        $orgSpan = Span::find($group->organisation_span_id);
        
        // Check if user is admin of organisation span via user_spans
        if ($orgSpan->users()
            ->where('user_id', $user->id)
            ->wherePivot('access_level', 'owner')
            ->exists()) {
            return true;
        }
    }
}

// 4. Existing permission checks (span_permissions, groups, etc.)
// ... existing logic ...
```

#### View Permission Logic

Similar flow, but also check:
- Group members might have view/edit permissions via `span_permissions`
- Public spans remain viewable by all
- Shared spans with group permissions work as before

### Model Changes

#### Group Model

```php
// Add relationship to organisation span
public function organisationSpan(): BelongsTo
{
    return $this->belongsTo(Span::class, 'organisation_span_id');
}

// Check if this is an organisational group
public function isOrganisational(): bool
{
    return $this->organisation_span_id !== null;
}

// Get all spans owned by this group
public function ownedSpans(): HasMany
{
    return $this->hasMany(Span::class, 'group_owner_id');
}

// Check if user can manage spans owned by this group
public function canManageOwnedSpans(User $user): bool
{
    // Group owner can always manage
    if ($this->owner_id === $user->id) {
        return true;
    }
    
    // If organisational, check organisation admin access
    if ($this->organisation_span_id) {
        $orgSpan = Span::find($this->organisation_span_id);
        return $orgSpan->users()
            ->where('user_id', $user->id)
            ->wherePivot('access_level', 'owner')
            ->exists();
    }
    
    return false;
}
```

#### Span Model

```php
// Add relationship to group owner
public function groupOwner(): BelongsTo
{
    return $this->belongsTo(Group::class, 'group_owner_id');
}

// Check if span is group-owned
public function isGroupOwned(): bool
{
    return $this->group_owner_id !== null;
}

// Get effective owner (user or group)
public function getEffectiveOwner()
{
    if ($this->group_owner_id) {
        return $this->groupOwner;
    }
    return $this->owner;
}
```

### User-Span Admin Relationship

For organisational groups, we need a way to assign admins to the organisation span:

**Option A: Use existing `user_spans` table**
- When a user is made admin of an organisation:
  ```php
  DB::table('user_spans')->insert([
      'user_id' => $user->id,
      'span_id' => $organisationSpan->id,
      'access_level' => 'owner',
  ]);
  ```
- Pros: Reuses existing infrastructure
- Cons: `user_spans` currently used for different purpose (needs clarification)

**Option B: Add `group_admins` pivot table**
- Separate table for group admins
- Pros: Clear separation
- Cons: More complexity

**Recommendation: Option A** - Use `user_spans` with `access_level='owner'` for organisation admins.

## Open Questions

### 1. Span Ownership Model
**Q:** Can a span have both `owner_id` and `group_owner_id` set?

**Options:**
- **A:** Mutually exclusive (either user-owned or group-owned)
- **B:** Both allowed (user created it, group owns it) ← **RECOMMENDED**

**Recommendation:** Option B - allows tracking both creator and owner, more flexible.

### 2. Organisation Span Creation
**Q:** When creating an organisational group, should we:
- **A:** Automatically create the organisation span
- **B:** Require linking to an existing organisation span
- **C:** Allow groups without organisation spans (collaborative only) ← **RECOMMENDED**

**Recommendation:** Option C - most flexible, allows gradual migration.

### 3. Group Member Admin Rights
**Q:** For organisational groups, should:
- **A:** Only group owner + users in `user_spans` be admins
- **B:** All group members be admins
- **C:** Configurable per group ← **RECOMMENDED**

**Recommendation:** Option C - most flexible, but start with Option A for simplicity.

### 4. `addMember()` Behavior
**Q:** Should `Group::addMember()` behavior differ for organisational groups?

**Current behavior:** Auto-shares personal span with group.

**Options:**
- **A:** Same for all groups (auto-share personal span)
- **B:** Skip auto-share for organisational groups
- **C:** Configurable per group

**Recommendation:** Option B - organisational groups don't need personal span sharing.

### 5. Migration Strategy
**Q:** How to handle existing groups?

**Recommendation:**
- All existing groups have `organisation_span_id = null` (collaborative)
- No breaking changes
- Gradual migration as needed

## Implementation Plan

### Phase 1: Schema Changes
1. Add `organisation_span_id` to `groups` table
2. Add `group_owner_id` to `spans` table
3. Add indexes and foreign keys
4. Migration script to handle existing data

### Phase 2: Model Updates
1. Update `Group` model with new relationships and methods
2. Update `Span` model with group ownership support
3. Add helper methods for permission checking
4. Update `User` model if needed

### Phase 3: Permission Logic
1. Update `Span::hasPermission()` method
2. Update `SpanPolicy` (view, update, delete)
3. Update `SpanAccessMiddleware` if needed
4. Add tests for new permission scenarios

### Phase 4: UI/UX
1. Group creation/edit: option to link organisation span
2. Span creation/edit: option to set group owner
3. Admin interface: assign organisation admins via `user_spans`
4. Display: show group ownership in span views

### Phase 5: Testing
1. Unit tests for new model methods
2. Feature tests for permission scenarios
3. Integration tests for group ownership flows
4. Edge case testing

## Edge Cases to Consider

1. **Group deletion**: What happens to group-owned spans?
   - Option: Transfer to group owner or set `group_owner_id = null`
   
2. **Organisation span deletion**: What happens to linked group?
   - Option: Set `organisation_span_id = null` (becomes collaborative group)

3. **Circular ownership**: Can a group own itself? (via organisation span)
   - Prevention: Check in validation

4. **Multiple group ownership**: Can a span be owned by multiple groups?
   - Current design: No (single `group_owner_id`)
   - Future: Could add pivot table if needed

5. **Permission conflicts**: User permission vs group permission
   - Resolution: Most permissive wins (existing logic)

## Benefits

1. **Unified Model**: Single Groups system handles both use cases
2. **Backward Compatible**: Existing groups continue working
3. **Flexible**: Supports both collaborative and organisational scenarios
4. **Extensible**: Can add more group types in future
5. **Consistent**: Uses existing permission infrastructure

## Risks & Mitigations

1. **Complexity**: Permission logic becomes more complex
   - Mitigation: Clear helper methods, comprehensive tests

2. **Performance**: Additional joins for permission checks
   - Mitigation: Proper indexing, caching where needed

3. **Confusion**: Users might not understand dual-purpose groups
   - Mitigation: Clear UI indicators, documentation

4. **Migration**: Existing code assumes user ownership
   - Mitigation: Gradual migration, helper methods abstract complexity

## Future Enhancements

1. **Group Roles**: Different roles within groups (admin, editor, viewer)
2. **Nested Groups**: Groups within groups
3. **Group Hierarchies**: Parent-child group relationships
4. **Bulk Operations**: Transfer multiple spans to group ownership
5. **Audit Trail**: Track ownership changes

## References

- Current Groups implementation: `app/Models/Group.php`
- Current Span ownership: `app/Models/Span.php` (owner relationship)
- Permission system: `app/Policies/SpanPolicy.php`
- User-Span relationships: `app/Models/User.php` (spans relationship)
- Access control plan: `plans/access-control-implementation.md`

## Notes

- This proposal builds on existing Groups infrastructure
- No breaking changes to current collaborative sharing functionality
- Organisation spans are already supported (type='organisation')
- `user_spans` table exists but needs clarification on usage
- Consider documenting the distinction between collaborative and organisational groups in UI



