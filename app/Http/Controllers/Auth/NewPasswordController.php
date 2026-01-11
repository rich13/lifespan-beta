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
            // Verify the user is approved and email is verified (same checks as login)
            if (!$userToLogin->approved_at) {
                return redirect()->route('login')
                    ->withErrors(['email' => 'Your account is pending approval. You will receive an email once your account has been approved.']);
            }
            
            if (!$userToLogin->hasVerifiedEmail()) {
                return redirect()->route('login')
                    ->withErrors(['email' => 'Please verify your email address before logging in. Check your inbox for the verification link.']);
            }
            
            // Authenticate the user securely
            Auth::login($userToLogin);
            
            // Regenerate session for security
            $request->session()->regenerate();
            
            // Ensure default sets exist
            $userToLogin->ensureDefaultSetsExist();
            
            // Generate session bridge token for handling redeploys
            $this->generateSessionBridgeToken($userToLogin);
            
            // Store email in cookie for future logins (1 year expiration)
            $cookie = cookie('remembered_email', $userToLogin->email, 525600); // 1 year in minutes
            
            return redirect(RouteServiceProvider::HOME)
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
