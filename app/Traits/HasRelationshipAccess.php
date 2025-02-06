<?php

namespace App\Traits;

use App\Models\User;
use App\Models\Span;
use Illuminate\Support\Facades\Auth;

trait HasRelationshipAccess
{
    /**
     * Check if a user has read access to this relationship
     * Access is granted if user:
     * 1. Is an admin
     * 2. Owns either the parent or child span
     * 3. Both spans are public/system spans
     */
    public function isAccessibleBy(?User $user = null): bool
    {
        // Get the current user if none provided
        $user = $user ?? Auth::user();

        // Load parent and child if not loaded
        if (!$this->relationLoaded('parent')) {
            $this->load('parent');
        }
        if (!$this->relationLoaded('child')) {
            $this->load('child');
        }

        // Both spans must exist
        if (!$this->parent || !$this->child) {
            return false;
        }

        // Admin always has access
        if ($user && $user->is_admin) {
            return true;
        }

        // Check if both spans are accessible
        $parentAccessible = $this->parent->isAccessibleBy($user);
        $childAccessible = $this->child->isAccessibleBy($user);

        return $parentAccessible && $childAccessible;
    }

    /**
     * Check if a user can edit this relationship
     * Edit access requires:
     * 1. Admin access, or
     * 2. Ownership of either the parent or child span
     */
    public function isEditableBy(?User $user = null): bool
    {
        // Get the current user if none provided
        $user = $user ?? Auth::user();

        // No editing without a user
        if (!$user) {
            return false;
        }

        // Load parent and child if not loaded
        if (!$this->relationLoaded('parent')) {
            $this->load('parent');
        }
        if (!$this->relationLoaded('child')) {
            $this->load('child');
        }

        // Both spans must exist
        if (!$this->parent || !$this->child) {
            return false;
        }

        // Admin can edit
        if ($user->is_admin) {
            return true;
        }

        // Can edit if user owns either span
        return $this->parent->isEditableBy($user) || $this->child->isEditableBy($user);
    }

    /**
     * Check if a user can delete this relationship
     * Delete access requires:
     * 1. Admin access, or
     * 2. Ownership of either the parent or child span
     */
    public function isDeletableBy(?User $user = null): bool
    {
        return $this->isEditableBy($user);
    }

    /**
     * Scope query to only include relationships accessible by a user
     */
    public function scopeAccessibleBy($query, ?User $user = null)
    {
        // Get the current user if none provided
        $user = $user ?? Auth::user();

        // Admin sees all
        if ($user && $user->is_admin) {
            return $query;
        }

        // Get IDs of spans accessible to the user
        $accessibleSpanIds = Span::accessibleBy($user)->pluck('id');

        // Only include relationships where both spans are accessible
        return $query->whereIn('parent_id', $accessibleSpanIds)
                    ->whereIn('child_id', $accessibleSpanIds);
    }
} 