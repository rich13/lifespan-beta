# Access Control Implementation Plan

## Overview
This document outlines the plan for implementing a comprehensive access control system for the Lifespan application, focusing on public/private visibility, individual permissions, and group-based access control.

## Current State
- Basic access levels (public, private, shared)
- Individual user permissions
- Admin override capabilities
- Permission inheritance from parent spans

## Requirements

### 1. Public Spans
- Visible to all visitors (not signed in)
- Visible to all signed-in users
- Editable only by:
  - Admin users
  - Users with explicit edit permission
  - Span owners

### 2. Private Spans
- Owner controls visibility and permissions
- Only visible to:
  - Owner
  - Admin users
  - Users with explicit view permission
  - Members of groups with view permission

### 3. Editing Permissions
- Owner has full control
- Users with edit permission can modify but not:
  - Change ownership
  - Modify permissions
  - Delete the span
- Admin users can:
  - Edit any span
  - Transfer ownership
  - Delete spans
  - Modify permissions

### 4. Group-Based Access
- Owners can grant group access
- Access levels:
  - View
  - Edit
- Automatic permission updates:
  - User gains access when added to group
  - User loses access when removed from group
  - All users lose access when group is deleted

### 5. Admin Capabilities
- Full access to all spans
- Can manage all permissions
- Can manage groups
- Can perform bulk operations

## Implementation Phases

### Phase 1: Database Schema Updates
```sql
-- Groups Table
CREATE TABLE groups (
    id UUID PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

-- Group Memberships Table
CREATE TABLE group_memberships (
    id UUID PRIMARY KEY,
    group_id UUID REFERENCES groups(id) ON DELETE CASCADE,
    user_id UUID REFERENCES users(id) ON DELETE CASCADE,
    role VARCHAR(50) DEFAULT 'member',
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    UNIQUE(group_id, user_id)
);

-- Update Span Permissions Table
ALTER TABLE span_permissions ADD COLUMN group_id UUID REFERENCES groups(id) ON DELETE CASCADE;
ALTER TABLE span_permissions ADD CONSTRAINT user_or_group_not_both CHECK (
    (user_id IS NOT NULL AND group_id IS NULL) OR 
    (user_id IS NULL AND group_id IS NOT NULL)
);
```

### Phase 2: Models and Relationships
1. Group Model
   - Basic CRUD
   - Member management
   - Permission management
   - Relationship to users and spans

2. User Model Updates
   - Group membership relationships
   - Permission checking methods
   - Access control helpers

3. Span Model Updates
   - Group permission methods
   - Enhanced permission checking
   - Bulk operation support

4. SpanPermission Model Updates
   - Group permission handling
   - Permission validation
   - Conflict resolution

### Phase 3: Access Control Implementation
1. Update SpanPolicy
   - Public access rules
   - Private access rules
   - Group-based access
   - Admin overrides
   - Permission inheritance

2. Permission System
   - Individual permissions
   - Group permissions
   - Permission inheritance
   - Conflict resolution
   - Caching strategy

### Phase 4: Controllers and Routes
1. Group Management
   ```php
   Route::resource('groups', GroupController::class);
   Route::post('groups/{group}/members', [GroupController::class, 'addMember']);
   Route::delete('groups/{group}/members/{user}', [GroupController::class, 'removeMember']);
   ```

2. Permission Management
   ```php
   Route::put('spans/{span}/permissions', [SpanPermissionController::class, 'update']);
   Route::post('spans/{span}/groups/{group}/permissions', [SpanPermissionController::class, 'grantGroupPermission']);
   Route::delete('spans/{span}/groups/{group}/permissions', [SpanPermissionController::class, 'revokeGroupPermission']);
   ```

### Phase 5: UI/UX Implementation
1. Group Management Interface
   - Group CRUD operations
   - Member management
   - Permission visualization
   - Bulk operations

2. Permission Management Interface
   - Individual permissions
   - Group permissions
   - Permission inheritance
   - Access level controls

## Testing Strategy

### Unit Tests
1. Model Tests
   - Group model
   - Updated User model
   - Updated Span model
   - SpanPermission model

2. Policy Tests
   - Public access
   - Private access
   - Group access
   - Admin access
   - Permission inheritance

### Feature Tests
1. Group Management
   - Create/Edit/Delete groups
   - Member management
   - Permission management

2. Access Control
   - Public span access
   - Private span access
   - Group-based access
   - Permission inheritance
   - Admin capabilities

### Performance Tests
1. Permission Checking
   - Individual permission checks
   - Group permission checks
   - Combined permission scenarios

2. Bulk Operations
   - Group member updates
   - Permission changes
   - Access level changes

## Security Considerations
1. Permission Validation
   - Validate all permission changes
   - Prevent permission escalation
   - Handle edge cases

2. Group Security
   - Validate group membership changes
   - Prevent circular dependencies
   - Handle group deletion

3. Admin Security
   - Validate admin actions
   - Audit logging
   - Prevent privilege escalation

## Open Questions
1. Group Hierarchies
   - Should we implement nested groups?
   - How would permission inheritance work?

2. Permission Conflicts
   - How to handle user vs group permission conflicts?
   - Which permission takes precedence?

3. Performance Optimization
   - How to cache permissions effectively?
   - How to handle large groups?

4. Future Extensibility
   - How to make the system extensible for future needs?
   - What additional metadata might be needed?

## Success Metrics
1. Functionality
   - All user stories implemented
   - All test cases passing
   - No security vulnerabilities

2. Performance
   - Permission checks under 10ms
   - Bulk operations complete in reasonable time
   - No significant database load

3. Usability
   - Clear permission management UI
   - Intuitive group management
   - Helpful error messages

## Timeline
1. Phase 1: Database Schema (1 week)
2. Phase 2: Models and Relationships (1 week)
3. Phase 3: Access Control Implementation (2 weeks)
4. Phase 4: Controllers and Routes (1 week)
5. Phase 5: UI/UX Implementation (2 weeks)
6. Testing and Documentation (1 week)

Total Estimated Time: 8 weeks 