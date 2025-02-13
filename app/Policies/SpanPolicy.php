<?php

namespace App\Policies;

use App\Models\Span;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class SpanPolicy
{
    /**
     * Get the effective span that owns the permissions
     */
    private function getEffectiveSpan(Span $span): ?Span
    {
        if ($span->permission_mode !== 'inherit') {
            return $span;
        }

        $currentSpan = $span->parent;
        while ($currentSpan && $currentSpan->permission_mode === 'inherit') {
            $currentSpan = $currentSpan->parent;
        }
        return $currentSpan;
    }

    /**
     * Check if user has the required permission level on a span
     */
    private function checkPermission(User $user, Span $span, int $requiredPermission): bool
    {
        // Admin override
        if ($user->is_admin) {
            return true;
        }

        // Get effective span for permission check
        $effectiveSpan = $this->getEffectiveSpan($span);
        if (!$effectiveSpan) {
            return false;
        }

        // Owner check (first octal digit)
        if ($user->id === $effectiveSpan->owner_id) {
            return ($effectiveSpan->permissions & ($requiredPermission << 6)) > 0;
        }

        // Group check (second octal digit) - uses user_spans table
        $hasGroupAccess = $effectiveSpan->users()
            ->where('user_id', $user->id)
            ->exists();
        if ($hasGroupAccess) {
            return ($effectiveSpan->permissions & ($requiredPermission << 3)) > 0;
        }

        // Others check (third octal digit)
        return ($effectiveSpan->permissions & $requiredPermission) > 0;
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(?User $user): bool
    {
        // Anyone can view the spans list
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(?User $user, Span $span): bool
    {
        // Admin can view all spans
        if ($user && $user->is_admin) {
            return true;
        }

        // Public spans can be viewed by anyone
        if ($span->access_level === 'public') {
            return true;
        }

        // Must be authenticated to view non-public spans
        if (!$user) {
            return false;
        }

        // Owner can always view
        if ($span->owner_id === $user->id) {
            return true;
        }

        // For shared spans, check if user has permission
        if ($span->access_level === 'shared') {
            return $span->permissions()
                ->where('user_id', $user->id)
                ->exists();
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true; // Any authenticated user can create spans
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Span $span): bool
    {
        // Admin can edit all spans
        if ($user->is_admin) {
            return true;
        }

        // Owner can always edit
        if ($span->owner_id === $user->id) {
            return true;
        }

        // For shared spans, check if user has edit permission
        if ($span->access_level === 'shared') {
            return $span->permissions()
                ->where('user_id', $user->id)
                ->where('permission_type', 'edit')
                ->exists();
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Span $span): bool
    {
        // Only owner and admin can delete
        return $user->is_admin || $span->owner_id === $user->id;
    }

    /**
     * Determine whether the user can manage permissions.
     */
    public function managePermissions(User $user, Span $span): bool
    {
        // Only owner and admin can manage permissions
        return $user->is_admin || $user->id === $span->owner_id;
    }
}