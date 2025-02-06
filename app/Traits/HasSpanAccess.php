<?php

namespace App\Traits;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

trait HasSpanAccess
{
    /**
     * The metadata fields that control access
     */
    protected array $accessFields = [
        'is_public' => false,      // World readable
        'is_editable' => false,    // World writable
        'is_system' => false,      // System span (always readable)
    ];

    /**
     * Check if a user has read access to this span
     */
    public function isAccessibleBy(?User $user = null): bool
    {
        // Get the current user if none provided
        $user = $user ?? Auth::user();

        // No access if no user and span isn't public/system
        if (!$user) {
            return $this->metadata['is_public'] ?? false || $this->metadata['is_system'] ?? false;
        }

        // Admin always has access
        if ($user->is_admin) {
            return true;
        }

        // Check if user owns the span
        if ($this->users()->where('user_id', $user->id)->exists()) {
            return true;
        }

        // Check if span is public or system
        return $this->metadata['is_public'] ?? false || $this->metadata['is_system'] ?? false;
    }

    /**
     * Check if a user can edit this span
     */
    public function isEditableBy(?User $user = null): bool
    {
        // Get the current user if none provided
        $user = $user ?? Auth::user();

        // No editing without a user
        if (!$user) {
            return false;
        }

        // Admin can edit
        if ($user->is_admin) {
            return true;
        }

        // Owner can edit
        if ($this->users()->where('user_id', $user->id)->exists()) {
            return true;
        }

        // Check if world-editable
        return $this->metadata['is_editable'] ?? false;
    }

    /**
     * Check if a user can delete this span
     */
    public function isDeletableBy(?User $user = null): bool
    {
        // Get the current user if none provided
        $user = $user ?? Auth::user();

        // No deleting without a user
        if (!$user) {
            return false;
        }

        // Admin can delete
        if ($user->is_admin) {
            return true;
        }

        // Only owner can delete
        return $this->users()->where('user_id', $user->id)->exists();
    }

    /**
     * Scope query to only include spans accessible by a user
     */
    public function scopeAccessibleBy($query, ?User $user = null)
    {
        // Get the current user if none provided
        $user = $user ?? Auth::user();

        // If no user, only show public/system spans
        if (!$user) {
            return $query->where(function($q) {
                $q->whereJsonContains('metadata->is_public', true)
                  ->orWhereJsonContains('metadata->is_system', true);
            });
        }

        // Admin sees all
        if ($user->is_admin) {
            return $query;
        }

        // User sees:
        // 1. Spans they own
        // 2. Public spans
        // 3. System spans
        return $query->where(function($q) use ($user) {
            $q->whereHas('users', function($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->orWhereJsonContains('metadata->is_public', true)
            ->orWhereJsonContains('metadata->is_system', true);
        });
    }
} 