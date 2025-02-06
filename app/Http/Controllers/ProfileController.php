<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use App\Models\Span;
use App\Models\User;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        
        // Update email if changed
        if ($request->has('email') && $user->email !== $request->email) {
            $user->email = $request->email;
            $user->email_verified_at = null;
        }

        // Update name in personal span if changed
        if ($request->has('name') && $user->personalSpan && $user->personalSpan->name !== $request->name) {
            $user->personalSpan->name = $request->name;
            $user->personalSpan->save();
        }

        $user->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        // Prevent admin users from deleting their accounts
        if ($request->user()->is_admin) {
            abort(403, 'Admin accounts cannot be deleted.');
        }

        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();
        $systemUser = User::where('email', 'system@example.com')->first();

        // Get the personal span ID before deleting
        $personalSpanId = $user->personal_span_id;

        // First, nullify the personal_span_id reference
        if ($personalSpanId) {
            $user->personal_span_id = null;
            $user->save();
        }

        // Now we can safely delete the personal span
        if ($personalSpanId) {
            Span::where('id', $personalSpanId)->delete();
        }

        // Then transfer ownership of remaining spans to system user
        Span::where('creator_id', $user->id)->update(['creator_id' => $systemUser->id]);
        Span::where('updater_id', $user->id)->update(['updater_id' => $systemUser->id]);

        Auth::logout();

        // Now we can safely delete the user
        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }

    /**
     * Update the user's password.
     */
    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $request->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        return back()->with('status', 'password-updated');
    }
}
