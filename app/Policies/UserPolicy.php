<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    /**
     * Determine whether the user can delete their account.
     */
    public function delete(User $user, User $target): bool
    {
        // Users can only delete their own account
        // Admins cannot delete their accounts
        return $user->id === $target->id && !$target->is_admin;
    }
} 