<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Illuminate\Support\Str;

class GroupsController extends Controller
{
    /**
     * Display a listing of groups the user is a member of
     */
    public function index(): View
    {
        $user = Auth::user();
        
        if (!$user) {
            abort(403, 'You must be logged in to view groups.');
        }
        
        // Get groups the user is a member of
        $groups = $user->groups()
            ->with(['owner.personalSpan', 'users.personalSpan'])
            ->orderBy('name')
            ->get();
        
        return view('groups.index', compact('groups'));
    }
    
    /**
     * Display the combined timeline for a specific group
     */
    public function show(Request $request, string $groupSlug): View
    {
        $user = Auth::user();
        
        if (!$user) {
            abort(403, 'You must be logged in to view groups.');
        }
        
        // Find group by name (convert slug back to name for matching)
        // Try to find by matching the slugified name
        $groups = $user->groups()->get();
        $group = $groups->first(function ($g) use ($groupSlug) {
            return Str::slug($g->name) === $groupSlug;
        });
        
        if (!$group) {
            abort(404, 'Group not found or you are not a member of this group.');
        }
        
        // Load group with members and their personal spans
        $group->load(['users.personalSpan', 'owner.personalSpan']);
        
        // Get all personal spans of group members (filter out nulls)
        $memberSpans = $group->users
            ->map(function ($user) {
                return $user->personalSpan;
            })
            ->filter()
            ->values();
        
        // If the owner is not already in the members list, add their span too
        if ($group->owner->personalSpan) {
            $ownerSpanId = $group->owner->personalSpan->id;
            $hasOwnerSpan = $memberSpans->contains(function ($span) use ($ownerSpanId) {
                return $span->id === $ownerSpanId;
            });
            if (!$hasOwnerSpan) {
                $memberSpans->prepend($group->owner->personalSpan);
            }
        }
        
        return view('groups.show', [
            'group' => $group,
            'memberSpans' => $memberSpans,
        ]);
    }
}
