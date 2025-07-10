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
    public function view(User $user, Span $span): bool
    {
        // Public spans can be viewed by anyone
        if ($span->access_level === 'public') {
            return true;
        }

        // Owner can always view
        if ($span->owner_id === $user->id) {
            return true;
        }

        // Check for user-based permissions
        if ($span->hasPermission($user, 'view')) {
            return true;
        }

        // Check for group-based permissions
        return $span->spanPermissions()
            ->where('permission_type', 'view')
            ->whereNotNull('group_id')
            ->whereHas('group', function ($query) use ($user) {
                $query->whereHas('users', function ($userQuery) use ($user) {
                    $userQuery->where('user_id', $user->id);
                });
            })
            ->exists();
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
        // Owner can always update
        if ($span->owner_id === $user->id) {
            return true;
        }

        // Check for user-based permissions
        if ($span->hasPermission($user, 'edit')) {
            return true;
        }

        // Check for group-based permissions
        return $span->spanPermissions()
            ->where('permission_type', 'edit')
            ->whereNotNull('group_id')
            ->whereHas('group', function ($query) use ($user) {
                $query->whereHas('users', function ($userQuery) use ($user) {
                    $userQuery->where('user_id', $user->id);
                });
            })
            ->exists();
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