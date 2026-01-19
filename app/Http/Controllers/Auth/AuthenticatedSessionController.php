<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Providers\RouteServiceProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();
        
        // Ensure default sets exist as a failsafe (only if user has personal span)
        $user = Auth::user();
        if ($user->personal_span_id) {
            $user->ensureDefaultSetsExist();
        }
        
        // Generate session bridge token for handling redeploys
        $this->generateSessionBridgeToken($user);

        // Get intended URL, but ignore API/status endpoints
        $intendedUrl = $request->session()->pull('url.intended', RouteServiceProvider::HOME);
        
        // If intended URL is an API endpoint or status endpoint, ignore it
        if ($intendedUrl && (
            str_starts_with($intendedUrl, '/admin-mode/') ||
            str_starts_with($intendedUrl, '/api/') ||
            str_starts_with($intendedUrl, '/wikipedia/')
        )) {
            $intendedUrl = RouteServiceProvider::HOME;
        }
        
        return redirect($intendedUrl);
    }

    /**
     * Generate a session bridge token that can restore the session after a redeploy
     * Token is returned in response so it can be stored in localStorage
     */
    private function generateSessionBridgeToken($user)
    {
        // Delete any existing bridge tokens
        $user->tokens()->where('name', 'session-bridge')->delete();
        
        // Create a new bridge token that never expires (for prototype)
        $token = $user->createToken('session-bridge');
        
        // Return the token in a session variable so it can be passed to the view
        $request = request();
        $request->session()->put('bridge_token', $token->plainTextToken);
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = Auth::user();
        if ($user) {
            // Delete the session bridge token
            $user->tokens()->where('name', 'session-bridge')->delete();
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
