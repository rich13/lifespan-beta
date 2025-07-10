<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\Span;
use App\Models\SpanPermission;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class SpanPermissionController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'admin']);
    }

    /**
     * Show the permissions management page for a span.
     */
    public function show(Span $span): View
    {
        $span->load(['spanPermissions.user.personalSpan', 'spanPermissions.group.users']);
        
        // Get users with their personal span names for display
        $users = User::with('personalSpan')
            ->get()
            ->map(function ($user) {
                $user->name = $user->personalSpan ? $user->personalSpan->name : $user->email;
                return $user;
            })
            ->sortBy('name');
        
        $groups = Group::with('users')->orderBy('name')->get();
        
        // Separate user and group permissions
        $userPermissions = $span->spanPermissions()
            ->whereNotNull('user_id')
            ->with(['user.personalSpan'])
            ->get()
            ->map(function ($permission) {
                $permission->user->name = $permission->user->personalSpan 
                    ? $permission->user->personalSpan->name 
                    : $permission->user->email;
                return $permission;
            });
            
        $groupPermissions = $span->spanPermissions()
            ->whereNotNull('group_id')
            ->with(['group.users'])
            ->get();
        
        return view('admin.spans.permissions', compact(
            'span', 
            'users', 
            'groups', 
            'userPermissions', 
            'groupPermissions'
        ));
    }

    /**
     * Grant permission to a user for a span.
     */
    public function grantUserPermission(Request $request, Span $span): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'permission_type' => 'required|in:view,edit',
        ]);

        $user = User::findOrFail($validated['user_id']);
        $span->grantPermission($user, $validated['permission_type']);

        return redirect()->back()
            ->with('status', "Permission granted to {$user->name} successfully.");
    }

    /**
     * Grant permission to a group for a span.
     */
    public function grantGroupPermission(Request $request, Span $span): RedirectResponse
    {
        $validated = $request->validate([
            'group_id' => 'required|exists:groups,id',
            'permission_type' => 'required|in:view,edit',
        ]);

        $group = Group::findOrFail($validated['group_id']);
        $span->grantGroupPermission($group, $validated['permission_type']);

        return redirect()->back()
            ->with('status', "Permission granted to group {$group->name} successfully.");
    }

    /**
     * Revoke permission from a user for a span.
     */
    public function revokeUserPermission(Span $span, User $user, string $permissionType): RedirectResponse
    {
        $span->revokePermission($user, $permissionType);

        return redirect()->back()
            ->with('status', "Permission revoked from {$user->name} successfully.");
    }

    /**
     * Revoke permission from a group for a span.
     */
    public function revokeGroupPermission(Span $span, Group $group, string $permissionType): RedirectResponse
    {
        $span->revokeGroupPermission($group, $permissionType);

        return redirect()->back()
            ->with('status', "Permission revoked from group {$group->name} successfully.");
    }

    /**
     * Bulk update permissions for a span.
     */
    public function bulkUpdate(Request $request, Span $span): RedirectResponse
    {
        $validated = $request->validate([
            'user_permissions' => 'nullable|array',
            'user_permissions.*.user_id' => 'exists:users,id',
            'user_permissions.*.permission_type' => 'in:view,edit',
            'group_permissions' => 'nullable|array',
            'group_permissions.*.group_id' => 'exists:groups,id',
            'group_permissions.*.permission_type' => 'in:view,edit',
        ]);

        // Clear existing permissions
        $span->spanPermissions()->delete();

        // Add user permissions
        if (!empty($validated['user_permissions'])) {
            foreach ($validated['user_permissions'] as $permission) {
                if (!empty($permission['user_id']) && !empty($permission['permission_type'])) {
                    $span->grantPermission(
                        User::find($permission['user_id']), 
                        $permission['permission_type']
                    );
                }
            }
        }

        // Add group permissions
        if (!empty($validated['group_permissions'])) {
            foreach ($validated['group_permissions'] as $permission) {
                if (!empty($permission['group_id']) && !empty($permission['permission_type'])) {
                    $span->grantGroupPermission(
                        Group::find($permission['group_id']), 
                        $permission['permission_type']
                    );
                }
            }
        }

        return redirect()->back()
            ->with('status', 'Permissions updated successfully.');
    }
} 