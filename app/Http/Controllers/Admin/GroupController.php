<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class GroupController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'admin']);
    }

    /**
     * Display a listing of groups.
     */
    public function index(): View
    {
        $groups = Group::with(['owner', 'users'])->get()->sortBy('name');
        // If you want pagination, you can use LengthAwarePaginator manually, but for now just pass the sorted collection
        return view('admin.groups.index', ['groups' => $groups]);
    }

    /**
     * Show the form for creating a new group.
     */
    public function create(): View
    {
        $users = User::with('personalSpan')->get()->sortBy('name');
        return view('admin.groups.create', compact('users'));
    }

    /**
     * Store a newly created group.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'owner_id' => 'required|exists:users,id',
            'member_ids' => 'nullable|array',
            'member_ids.*' => 'exists:users,id',
        ]);

        $group = Group::create([
            'name' => $validated['name'],
            'description' => $validated['description'],
            'owner_id' => $validated['owner_id'],
        ]);

        // Add members if specified
        if (!empty($validated['member_ids'])) {
            $members = User::whereIn('id', $validated['member_ids'])->get();
            foreach ($members as $member) {
                $group->addMember($member);
            }
        }

        return redirect()->route('admin.groups.index')
            ->with('status', 'Group created successfully.');
    }

    /**
     * Display the specified group.
     */
    public function show(Group $group): View
    {
        $group->load(['owner', 'users', 'spanPermissions.span']);
        $allUsers = User::with('personalSpan')->get()->sortBy('name');
        
        return view('admin.groups.show', compact('group', 'allUsers'));
    }

    /**
     * Show the form for editing the specified group.
     */
    public function edit(Group $group): View
    {
        $group->load(['owner', 'users']);
        $users = User::with('personalSpan')->get()->sortBy('name');
        
        return view('admin.groups.edit', compact('group', 'users'));
    }

    /**
     * Update the specified group.
     */
    public function update(Request $request, Group $group): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'owner_id' => 'required|exists:users,id',
        ]);

        $group->update($validated);

        return redirect()->route('admin.groups.show', $group)
            ->with('status', 'Group updated successfully.');
    }

    /**
     * Remove the specified group.
     */
    public function destroy(Group $group): RedirectResponse
    {
        $group->delete();

        return redirect()->route('admin.groups.index')
            ->with('status', 'Group deleted successfully.');
    }

    /**
     * Add a member to the group.
     */
    public function addMember(Request $request, Group $group): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $user = User::findOrFail($validated['user_id']);
        $group->addMember($user);

        return redirect()->back()
            ->with('status', "User {$user->name} added to group successfully.");
    }

    /**
     * Remove a member from the group.
     */
    public function removeMember(Group $group, User $user): RedirectResponse
    {
        if ($user->id === $group->owner_id) {
            abort(403, 'Cannot remove the group owner.');
        }
        $group->removeMember($user);

        return redirect()->back()
            ->with('status', "User {$user->name} removed from group successfully.");
    }
} 