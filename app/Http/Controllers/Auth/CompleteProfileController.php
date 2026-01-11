<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class CompleteProfileController extends Controller
{
    /**
     * Show the profile completion form.
     */
    public function show(): View|RedirectResponse
    {
        $user = Auth::user();
        
        // If user already has a personal span, redirect to home
        if ($user->personal_span_id) {
            return redirect()->route('home');
        }
        
        return view('auth.complete-profile');
    }

    /**
     * Handle profile completion submission.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'birth_year' => ['required', 'integer', 'min:1900', 'max:' . date('Y')],
            'birth_month' => ['required', 'integer', 'min:1', 'max:12'],
            'birth_day' => ['required', 'integer', 'min:1', 'max:31'],
        ]);

        $user = Auth::user();
        
        // Double-check user doesn't already have a personal span
        if ($user->personal_span_id) {
            return redirect()->route('home')
                ->with('status', 'Your profile is already complete.');
        }

        try {
            // Create personal span
            $personalSpan = $user->createPersonalSpan([
                'name' => $validated['name'],
                'birth_year' => $validated['birth_year'],
                'birth_month' => $validated['birth_month'],
                'birth_day' => $validated['birth_day'],
            ]);

            Log::info('Profile completed - personal span created', [
                'user_id' => $user->id,
                'span_id' => $personalSpan->id,
            ]);

            return redirect()->route('home');
        } catch (\Exception $e) {
            Log::error('Failed to create personal span during profile completion', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return back()
                ->withInput()
                ->withErrors(['name' => 'There was an error setting up your profile. Please try again.']);
        }
    }
}
