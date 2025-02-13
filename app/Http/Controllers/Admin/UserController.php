<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'admin']);
    }

    public function index(): View
    {
        $users = User::with('personalSpan')->paginate(20);
        return view('admin.users.index', compact('users'));
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
} 