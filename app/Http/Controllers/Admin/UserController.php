<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'admin']);
    }

    public function index(): View
    {
        $users = User::with('personalSpan')->paginate(20);
        $invitationCodes = \App\Models\InvitationCode::orderBy('created_at', 'desc')->get();
        $unusedCodes = $invitationCodes->where('used', false)->count();
        $usedCodes = $invitationCodes->where('used', true)->count();
        
        return view('admin.users.index', compact('users', 'unusedCodes', 'usedCodes', 'invitationCodes'));
    }

    public function show(User $user): View
    {
        return view('admin.users.show', compact('user'));
    }

    public function edit(User $user): View
    {
        return view('admin.users.edit', compact('user'));
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'email' => 'sometimes|required|email|unique:users,email,' . $user->id,
            'is_admin' => 'sometimes|boolean',
        ]);

        $user->update($validated);
        return redirect()->route('admin.users.show', $user)
            ->with('status', 'User updated successfully');
    }

    public function generateInvitationCodes(Request $request)
    {
        $count = 10;
        $codes = [];
        
        DB::enableQueryLog();
        
        for ($i = 0; $i < $count; $i++) {
            try {
                $code = \App\Models\InvitationCode::create([
                    'code' => 'BETA-' . strtoupper(uniqid()),
                    'used' => false,
                ]);
                $codes[] = $code->code;
            } catch (\Exception $e) {
                Log::error('Failed to create invitation code', [
                    'error' => $e->getMessage(),
                    'sql' => DB::getQueryLog(),
                ]);
                throw $e;
            }
        }
        
        return redirect()->route('admin.users.index')
            ->with('status', "Generated $count new invitation codes")
            ->with('new_codes', $codes);
    }

    public function deleteAllInvitationCodes(Request $request)
    {
        $count = \App\Models\InvitationCode::count();
        \App\Models\InvitationCode::truncate();
        
        return redirect()->route('admin.users.index')
            ->with('status', "Deleted all $count invitation codes");
    }

    public function destroy(User $user)
    {
        if ($user->is_admin) {
            return back()->with('error', 'Administrator accounts cannot be deleted.');
        }

        // First, remove the personal span reference
        $user->personal_span_id = null;
        $user->save();

        // Delete all spans owned by the user
        $user->ownedSpans()->delete();
        
        // Delete the user
        $user->delete();

        return redirect()->route('admin.users.index')
            ->with('status', 'User and their spans deleted successfully.');
    }
} 