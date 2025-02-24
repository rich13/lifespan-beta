<?php

namespace App\Policies;

use App\Models\Span;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class SpanPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(?User $user): bool
    {
        return true; // Anyone can view the spans list
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(?User $user, Span $span): bool
    {
        // Public spans can be viewed by anyone
        if ($span->access_level === 'public') {
            return true;
        }

        // Must be authenticated to view non-public spans
        if (!$user) {
            return false;
        }

        // Admin can view all spans
        if ($user->is_admin) {
            return true;
        }

        // Owner can always view
        if ($span->owner_id === $user->id) {
            return true;
        }

        // For shared spans, check if user has view permission
        if ($span->access_level === 'shared') {
            return $span->permissions()
                ->where('user_id', $user->id)
                ->where('permission_type', 'view')
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