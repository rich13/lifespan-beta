<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Span;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SpanPermissionsController extends Controller
{
    public function edit(Span $span): View
    {
        $this->authorize('managePermissions', $span);

        $users = User::all();
        $groupMembers = $span->users()->get();
        $effectivePermissions = $span->getEffectivePermissions();
        $permissionString = $span->getPermissionsString();
        $inheritedFrom = null;

        if ($span->permission_mode === 'inherit') {
            $currentSpan = $span;
            while ($currentSpan && $currentSpan->permission_mode === 'inherit') {
                $currentSpan = $currentSpan->parent;
            }
            $inheritedFrom = $currentSpan;
        }

        return view('admin.spans.permissions', compact(
            'span',
            'users',
            'groupMembers',
            'effectivePermissions',
            'permissionString',
            'inheritedFrom'
        ));
    }

    public function update(Request $request, Span $span)
    {
        $this->authorize('managePermissions', $span);

        $validated = $request->validate([
            'owner_read' => 'boolean',
            'owner_write' => 'boolean',
            'owner_execute' => 'boolean',
            'group_read' => 'boolean',
            'group_write' => 'boolean',
            'group_execute' => 'boolean',
            'others_read' => 'boolean',
            'others_write' => 'boolean',
            'others_execute' => 'boolean',
            'group_members' => 'array',
            'group_members.*' => 'exists:users,id'
        ]);

        // Calculate permissions integer
        $permissions = 0;
        if ($validated['owner_read']) $permissions |= 0400;
        if ($validated['owner_write']) $permissions |= 0200;
        if ($validated['owner_execute']) $permissions |= 0100;
        if ($validated['group_read']) $permissions |= 0040;
        if ($validated['group_write']) $permissions |= 0020;
        if ($validated['group_execute']) $permissions |= 0010;
        if ($validated['others_read']) $permissions |= 0004;
        if ($validated['others_write']) $permissions |= 0002;
        if ($validated['others_execute']) $permissions |= 0001;

        DB::beginTransaction();
        try {
            // Update permissions
            $span->useOwnPermissions($permissions);

            // Update group members
            $span->users()->detach();
            if (!empty($validated['group_members'])) {
                foreach ($validated['group_members'] as $userId) {
                    $span->users()->attach($userId, [
                        'id' => Str::uuid(),
                        'access_level' => 'viewer'
                    ]);
                }
            }

            DB::commit();
            return redirect()->route('admin.spans.permissions.edit', $span)
                ->with('status', 'Permissions updated successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Failed to update permissions']);
        }
    }

    public function updateMode(Request $request, Span $span)
    {
        $this->authorize('managePermissions', $span);

        $validated = $request->validate([
            'mode' => 'required|in:own,inherit'
        ]);

        if ($validated['mode'] === 'inherit' && !$span->parent_id) {
            return back()->withErrors(['error' => 'Cannot inherit permissions without a parent']);
        }

        try {
            if ($validated['mode'] === 'inherit') {
                $span->inheritPermissions();
            } else {
                $span->useOwnPermissions();
            }

            return redirect()->route('admin.spans.permissions.edit', $span)
                ->with('status', 'Permission mode updated successfully');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to update permission mode']);
        }
    }
} 