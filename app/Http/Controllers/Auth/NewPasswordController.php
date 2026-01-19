<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class NewPasswordController extends Controller
{
    /**
     * Display the password reset view.
     */
    public function create(Request $request): View
    {
        return view('auth.reset-password', ['request' => $request]);
    }

    /**
     * Handle an incoming new password request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        // Store the user reference before reset (Password::reset callback receives the user)
        $userToLogin = null;

        // Here we will attempt to reset the user's password. If it is successful we
        // will update the password on an actual user model and persist it to the
        // database. Otherwise we will parse the error and return the response.
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user) use ($request, &$userToLogin) {
                // Auto-verify email since user has proven ownership by resetting password
                if (!$user->hasVerifiedEmail()) {
                    $user->email_verified_at = now();
                    Log::info('Email auto-verified after password reset', [
                        'user_id' => $user->id,
                        'user_email' => $user->email,
                    ]);
                }
                
                $user->forceFill([
                    'password' => Hash::make($request->password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
                
                // Store user reference for login after successful reset
                $userToLogin = $user;
            }
        );

        // If the password was successfully reset, authenticate the user
        if ($status == Password::PASSWORD_RESET && $userToLogin) {
            // Always authenticate the user after password reset (they've proven email ownership)
            Auth::login($userToLogin);
            
            // Regenerate session for security
            $request->session()->regenerate();
            
            // Ensure default sets exist (only if user has personal span)
            if ($userToLogin->personal_span_id) {
                $userToLogin->ensureDefaultSetsExist();
            }
            
            // Generate session bridge token for handling redeploys
            $this->generateSessionBridgeToken($userToLogin);
            
            // Send Slack notification for password reset completion
            try {
                $slackService = app(\App\Services\SlackNotificationService::class);
                $slackService->notifyPasswordResetCompleted($userToLogin, $request->ip());
            } catch (\Exception $e) {
                Log::error('Failed to send Slack notification for password reset completion', [
                    'error' => $e->getMessage()
                ]);
            }
            
            // Store email in cookie for future logins (1 year expiration)
            $cookie = cookie('remembered_email', $userToLogin->email, 525600); // 1 year in minutes
            
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
            
            // Check if user is approved - show appropriate message
            if (!$userToLogin->approved_at) {
                // User is signed in but not approved - show approval pending message
                return redirect($intendedUrl)
                    ->with('status', 'Your password has been reset and your email has been verified. Your account is pending admin approval. You will receive an email once your account has been approved.')
                    ->with('approval_pending', true)
                    ->withCookie($cookie);
            }
            
            return redirect($intendedUrl)
                ->with('status', 'Your password has been reset and you have been signed in.')
                ->withCookie($cookie);
        }

        // If there was an error, redirect back with error message
        return back()->withInput($request->only('email'))
                    ->withErrors(['email' => __($status)]);
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
        
        // Store the token in session so it can be passed to the view
        request()->session()->put('bridge_token', $token->plainTextToken);
    }
}
