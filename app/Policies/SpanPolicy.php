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

        // Fallback: Users can view spans they last updated
        // This covers spans created through various import paths where owner_id might be NULL or incorrect
        if ($span->updater_id === $user->id) {
            return true;
        }

        // Special handling for connection spans
        // Users can view connection spans if they can view both the connected spans
        if ($span->type_id === 'connection') {
            // Find the connection record for this span
            $connection = \App\Models\Connection::where('connection_span_id', $span->id)->first();
            if ($connection) {
                $parent = $connection->parent;
                $child = $connection->child;
                
                // Check if user can access both parent and child spans
                if ($parent && $child) {
                    $canAccessParent = $parent->access_level === 'public' || 
                                      $parent->owner_id === $user->id ||
                                      $parent->hasPermission($user, 'view');
                    $canAccessChild = $child->access_level === 'public' || 
                                     $child->owner_id === $user->id ||
                                     $child->hasPermission($user, 'view');
                    
                    if ($canAccessParent && $canAccessChild) {
                        return true;
                    }
                }
            }
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
        return $user->getEffectiveAdminStatus() || $span->owner_id === $user->id || $span->updater_id === $user->id;
    }

    /**
     * Determine whether the user can manage permissions.
     */
    public function managePermissions(User $user, Span $span): bool
    {
        // Only owner and admin can manage permissions
        return $user->getEffectiveAdminStatus() || $user->id === $span->owner_id || $user->id === $span->updater_id;
    }
}