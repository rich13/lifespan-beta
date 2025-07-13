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
use Illuminate\Support\Str;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        $user = $request->user();
        
        // Load user relationships
        $user->load(['personalSpan', 'createdSpans', 'updatedSpans']);
        
        // Get user statistics
        $stats = [
            'total_spans_created' => $user->createdSpans()->count(),
            'total_spans_updated' => $user->updatedSpans()->count(),
            'public_spans' => $user->createdSpans()->where('access_level', 'public')->count(),
            'private_spans' => $user->createdSpans()->where('access_level', 'private')->count(),
            'shared_spans' => $user->createdSpans()->where('access_level', 'shared')->count(),
        ];
        
        // Get recent activity
        $recentSpans = $user->createdSpans()
            ->with('type')
            ->orderBy('updated_at', 'desc')
            ->limit(5)
            ->get();
            
        // Get connection statistics if user has a personal span
        $connectionStats = [];
        if ($user->personalSpan) {
            $personalSpan = $user->personalSpan;
            
            $connectionStats = [
                'total_connections' => $personalSpan->connectionsAsSubject()->count() + $personalSpan->connectionsAsObject()->count(),
                'connections_as_subject' => $personalSpan->connectionsAsSubject()->count(),
                'connections_as_object' => $personalSpan->connectionsAsObject()->count(),
                'temporal_connections' => $personalSpan->connectionsAsSubject()
                    ->whereNotNull('connection_span_id')
                    ->whereHas('connectionSpan', function($query) {
                        $query->whereNotNull('start_year');
                    })
                    ->count(),
            ];
            
            // Get recent connections
            $recentConnections = $personalSpan->connectionsAsSubject()
                ->with(['child', 'type', 'connectionSpan'])
                ->orderBy('created_at', 'desc')
                ->limit(3)
                ->get();
        } else {
            $recentConnections = collect();
        }
        
        // Get account statistics
        $accountStats = [
            'member_since' => $user->created_at->diffForHumans(),
            'last_active' => $user->updated_at->diffForHumans(),
            'email_verified' => $user->email_verified_at ? $user->email_verified_at->diffForHumans() : 'Not verified',
        ];
        
        return view('profile.edit', [
            'user' => $user,
            'stats' => $stats,
            'connectionStats' => $connectionStats,
            'accountStats' => $accountStats,
            'recentSpans' => $recentSpans,
            'recentConnections' => $recentConnections,
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
        $systemUser = User::where('email', 'system@lifespan.app')->first();

        // Create system user if it doesn't exist
        if (!$systemUser) {
            $systemUser = User::create([
                'email' => 'system@lifespan.app',
                'password' => Hash::make(Str::random(32)),
                'is_admin' => true,
                'email_verified_at' => now(),
            ]);
        }

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
        Span::where('owner_id', $user->id)->update(['owner_id' => $systemUser->id]);
        Span::where('updater_id', $user->id)->update(['updater_id' => $systemUser->id]);

        // Update span_versions to reference system user instead of the user being deleted
        \DB::table('span_versions')->where('changed_by', $user->id)->update(['changed_by' => $systemUser->id]);

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
