# Understanding Permissions and Access Control in Lifespan

Lifespan implements a flexible and secure permissions system that balances ease of use with fine-grained access control. This document explains how permissions work and how to use them effectively.

## Access Levels

Every span in Lifespan has one of three access levels:

### 1. Private (Default)
- Only visible to the span's owner and system administrators
- Most restrictive level
- Default state for newly created spans
- Ideal for personal or sensitive information

### 2. Shared
- Visible to specific users with explicit permissions
- Supports granular permission types (view/edit)
- Great for collaboration with specific individuals
- Automatically set when granting permissions to users

### 3. Public
- Visible to all users (even those not logged in)
- Read-only for non-owners
- Best for published, finalized content
- Cannot be edited by users without explicit permissions

## Permission Types

When a span is shared, two types of permissions can be granted:

1. **View Permission**
   - Allows reading the span's content
   - Cannot make modifications
   - Basic permission level for shared spans

2. **Edit Permission**
   - Includes view permission
   - Allows modifying the span's content
   - Cannot delete the span or manage permissions
   - Ideal for collaborators

## Special Permissions

Certain users have elevated permissions regardless of the span's access level:

1. **Owner**
   - Full control over their spans
   - Can change access levels
   - Can grant/revoke permissions
   - Can delete the span
   - Cannot be removed from their own spans

2. **Administrator**
   - Full access to all spans
   - Can modify any span
   - Can manage permissions
   - Can delete spans
   - System-wide oversight

## Permission Management

### Changing Access Levels

```php
$span->makePrivate();  // Remove all shared permissions
$span->makeShared();   // Enable user-specific permissions
$span->makePublic();   // Make visible to everyone
```

### Managing User Permissions

```php
// Grant permissions
$span->grantPermission($user, 'view');
$span->grantPermission($user, 'edit');

// Revoke permissions
$span->revokePermission($user, 'edit');
$span->revokePermission($user);  // Revoke all permissions
```

### Automatic State Management

The system automatically manages access levels:

- When granting the first permission to a private span, it becomes shared
- When revoking all permissions from a shared span, it becomes private
- Public spans maintain their permissions for granular control

## Implementation Details

The permission system is implemented through several components:

1. **Database Tables**
   - `spans`: Contains `access_level` column
   - `span_permissions`: Stores user-specific permissions

2. **Policy Layer**
   - `SpanPolicy` enforces access rules
   - Handles all permission checks
   - Used by Laravel's Gate facade

3. **Middleware**
   - `SpanAccessMiddleware` protects routes
   - Handles authentication requirements
   - Enforces permission checks

## Best Practices

1. **Default to Private**
   - Start with private access
   - Share only when needed
   - Be conservative with public access

2. **Use Appropriate Permissions**
   - Grant view permission for readers
   - Reserve edit permission for trusted collaborators
   - Regularly audit permissions

3. **Group Management**
   - Share with specific users rather than making public
   - Remove permissions when no longer needed
   - Document permission changes

4. **Security Considerations**
   - Never expose private spans
   - Regularly review public spans
   - Audit permission changes
   - Log access changes

## Examples

### Common Use Cases

1. **Personal Research**
   ```php
   $span->makePrivate();  // Keep it to yourself
   ```

2. **Collaboration**
   ```php
   $span->makeShared();
   $span->grantPermission($colleague, 'edit');
   $span->grantPermission($reviewer, 'view');
   ```

3. **Publication**
   ```php
   $span->makePublic();  // Share with the world
   ```

### Permission Checks

```php
if ($span->hasPermission($user, 'view')) {
    // User can view the span
}

if ($span->hasPermission($user, 'edit')) {
    // User can edit the span
}
```

## Conclusion

Lifespan's permission system provides a robust foundation for controlling access to spans. By understanding and properly utilizing these features, you can ensure that your data remains secure while enabling effective collaboration when needed. 