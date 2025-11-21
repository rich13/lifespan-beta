<?php

namespace App\Services;

use App\Models\Span;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class SetFilterService
{
    /**
     * Apply a filter to get set contents
     */
    public static function applyFilter(string $filterType, array $criteria, User $user): Collection
    {
        switch ($filterType) {
            case 'in_set':
                return self::getItemsInSet($criteria['set_id'], $user);
            case 'friends':
                return self::getFriends($user);
            case 'starred':
                return self::getStarredItems($user);
            case 'public':
                return self::getPublicItems($user);
            case 'shared':
                return self::getSharedItems($user);
            case 'recent':
                return self::getRecentItems($user, $criteria['days'] ?? 30);
            default:
                return collect();
        }
    }

    /**
     * Get items that are in a specific set (traditional set membership)
     */
    private static function getItemsInSet(string $setId, User $user): Collection
    {
        $set = Span::find($setId);
        if (!$set || !$set->isSet() || !$set->hasPermission($user, 'view')) {
            return collect();
        }

        return $set->getSetContents();
    }

    /**
     * Get all friends of the user
     */
    private static function getFriends(User $user): Collection
    {
        $personalSpan = $user->personalSpan;
        if (!$personalSpan) {
            return collect();
        }

        // Get all friend spans using a single query with OR conditions
        $friends = Span::where(function ($query) use ($personalSpan) {
            $query->whereIn('id', function ($subQuery) use ($personalSpan) {
                $subQuery->select('child_id')
                         ->from('connections')
                         ->where('parent_id', $personalSpan->id)
                         ->where('type_id', 'friend');
            })->orWhereIn('id', function ($subQuery) use ($personalSpan) {
                $subQuery->select('parent_id')
                         ->from('connections')
                         ->where('child_id', $personalSpan->id)
                         ->where('type_id', 'friend');
            });
        })->get();
        
        // Filter to only include friends the user can view
        return $friends->filter(function ($friend) use ($user) {
            return $friend->hasPermission($user, 'view');
        });
    }

    /**
     * Get all starred items for the user
     */
    private static function getStarredItems(User $user): Collection
    {
        // This would need to be implemented based on how starring works
        // For now, return empty collection
        return collect();
    }

    /**
     * Get all public items the user can access
     */
    private static function getPublicItems(User $user): Collection
    {
        return Span::where('access_level', 'public')
            ->where('type_id', '!=', 'set') // Exclude sets themselves
            ->where(function (Builder $query) use ($user) {
                $query->where('owner_id', $user->id)
                    ->orWhereHas('permissions', function (Builder $q) use ($user) {
                        $q->where('user_id', $user->id);
                    });
            })
            ->get();
    }

    /**
     * Get all shared items the user can access
     */
    private static function getSharedItems(User $user): Collection
    {
        return Span::where('access_level', 'shared')
            ->where('type_id', '!=', 'set') // Exclude sets themselves
            ->whereHas('permissions', function (Builder $query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->get();
    }

    /**
     * Get items created in the last X days
     */
    private static function getRecentItems(User $user, int $days): Collection
    {
        $date = now()->subDays($days);
        
        return Span::where('created_at', '>=', $date)
            ->where('type_id', '!=', 'set') // Exclude sets themselves
            ->where(function (Builder $query) use ($user) {
                $query->where('owner_id', $user->id)
                    ->orWhere('access_level', 'public')
                    ->orWhereHas('permissions', function (Builder $q) use ($user) {
                        $q->where('user_id', $user->id);
                    });
            })
            ->get();
    }

    /**
     * Get predefined smart sets configuration
     */
    public static function getPredefinedSets(): array
    {
        return [
            // No predefined sets currently active
        ];
    }
} 