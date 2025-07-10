<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Represents a group of users for collaborative span sharing.
 *
 * @property string $id UUID of the group
 * @property string $name Name of the group
 * @property string|null $description Optional description of the group
 * @property string $owner_id UUID of the user who owns this group
 * @property \Carbon\Carbon $created_at When the group was created
 * @property \Carbon\Carbon $updated_at When the group was last updated
 * @property-read User $owner The user who owns this group
 * @property-read \Illuminate\Database\Eloquent\Collection<int, User> $users Users who are members of this group
 * @property-read \Illuminate\Database\Eloquent\Collection<int, SpanPermission> $spanPermissions Permissions granted to this group
 */
class Group extends Model
{
    use HasUuids, HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'description',
        'owner_id',
    ];

    /**
     * Get the user who owns this group.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Get the users who are members of this group.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'group_user')
            ->withTimestamps();
    }

    /**
     * Get the span permissions granted to this group.
     */
    public function spanPermissions(): HasMany
    {
        return $this->hasMany(SpanPermission::class);
    }

    /**
     * Check if a user is a member of this group.
     */
    public function hasMember(User $user): bool
    {
        return $this->users()->where('user_id', $user->id)->exists();
    }

    /**
     * Check if a user can manage this group.
     */
    public function canBeManagedBy(User $user): bool
    {
        return $user->is_admin || $this->owner_id === $user->id;
    }

    /**
     * Add a user to this group.
     */
    public function addMember(User $user): void
    {
        if (!$this->hasMember($user)) {
            $this->users()->attach($user->id);
            
            // Automatically grant the group permission to the user's personal span
            if ($user->personalSpan) {
                $this->grantPermission($user->personalSpan, 'view');
            }
        }
    }

    /**
     * Remove a user from this group.
     */
    public function removeMember(User $user): void
    {
        $this->users()->detach($user->id);
        
        // Automatically revoke the group permission from the user's personal span
        if ($user->personalSpan) {
            $this->revokePermission($user->personalSpan, 'view');
        }
    }

    /**
     * Get all spans that this group has access to.
     */
    public function accessibleSpans()
    {
        return Span::whereHas('permissions', function ($query) {
            $query->where('group_id', $this->id);
        });
    }

    /**
     * Grant permission to this group for a span.
     */
    public function grantPermission(Span $span, string $permissionType = 'view'): void
    {
        $span->grantGroupPermission($this, $permissionType);
    }

    /**
     * Revoke permission from this group for a span.
     */
    public function revokePermission(Span $span, string $permissionType = 'view'): void
    {
        SpanPermission::where([
            'span_id' => $span->id,
            'group_id' => $this->id,
            'permission_type' => $permissionType,
        ])->delete();
    }
} 