<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Http\RedirectResponse;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Log;

class EmailFirstAuthController extends Controller
{
    public function showEmailForm(Request $request)
    {
        // Check if we have a remembered email in cookie
        $rememberedEmail = $request->cookie('remembered_email');
        
        if ($rememberedEmail) {
            // Check if user exists with this email
            $user = User::where('email', $rememberedEmail)->first();
            if ($user) {
                // Skip email screen and go directly to password screen
                return redirect()->route('auth.password')
                    ->with('email', $rememberedEmail);
            }
        }
        
        return view('auth.email-first');
    }

    public function processEmail(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::where('email', $request->email)->first();

        if ($user) {
            // User exists - redirect to password login form
            return redirect()->route('auth.password')
                ->with('email', $request->email);
        } else {
            // New user - redirect to registration form
            return redirect()->route('register')
                ->with('email', $request->email);
        }
    }

    public function showPasswordForm(Request $request)
    {
        $email = session('email') ?? $request->cookie('remembered_email');
        
        if (!$email) {
            return redirect()->route('login');
        }

        return view('auth.password', [
            'email' => $email
        ]);
    }
    
    /**
     * Clear remembered email and return to email input screen
     */
    public function clearRememberedEmail(): RedirectResponse
    {
        $cookie = cookie()->forget('remembered_email');
        
        return redirect()->route('login')
            ->withCookie($cookie);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();
            
            // Ensure default sets exist as a failsafe
            $user = Auth::user();
            
            // Check if user is approved
            if (!$user->approved_at) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                
                return back()
                    ->withInput(['email' => $request->email])
                    ->withErrors([
                        'email' => 'Your account is pending approval. You will receive an email once your account has been approved.'
                    ]);
            }
            
            // Check if email is verified
            if (!$user->hasVerifiedEmail()) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                
                return back()
                    ->withInput(['email' => $request->email])
                    ->withErrors([
                        'email' => 'Please verify your email address before logging in. Check your inbox for the verification link.'
                    ]);
            }
            
            $user->ensureDefaultSetsExist();
            
            // Generate session bridge token for handling redeploys
            $this->generateSessionBridgeToken($user);
            
            // Store email in cookie for future logins (1 year expiration)
            $cookie = cookie('remembered_email', $request->email, 525600); // 1 year in minutes
            
            return redirect()->intended(RouteServiceProvider::HOME)
                ->withCookie($cookie);
        }

        return back()
            ->withInput(['email' => $request->email])
            ->withErrors([
                'password' => 'The provided credentials do not match our records.'
            ]);
    }

    public function register(Request $request)
    {
        Log::info('Starting user registration', ['email' => $request->email]);
        
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'birth_year' => ['required', 'integer', 'min:1900', 'max:' . date('Y')],
            'birth_month' => ['required', 'integer', 'min:1', 'max:12'],
            'birth_day' => ['required', 'integer', 'min:1', 'max:31'],
        ]);

        Log::info('Creating user', ['email' => $validated['email']]);
        
        // Create user without personal span first
        $user = User::create([
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        Log::info('User created', ['user_id' => $user->id]);

        // Create personal span
        Log::info('Creating personal span', [
            'user_id' => $user->id,
            'name' => $validated['name'],
            'birth_year' => $validated['birth_year'],
            'birth_month' => $validated['birth_month'],
            'birth_day' => $validated['birth_day'],
        ]);
        
        $personalSpan = $user->createPersonalSpan([
            'name' => $validated['name'],
            'birth_year' => $validated['birth_year'],
            'birth_month' => $validated['birth_month'],
            'birth_day' => $validated['birth_day'],
        ]);

        Log::info('Personal span created', [
            'user_id' => $user->id,
            'span_id' => $personalSpan->id
        ]);

        event(new Registered($user));
        
        Auth::login($user);
        
        // Generate session bridge token for handling redeploys
        $this->generateSessionBridgeToken($user);

        return redirect(RouteServiceProvider::HOME);
    }

    /**
     * Generate a session bridge token that can restore the session after a redeploy
     * Token is stored in session so it can be passed to the view
     */
    private function generateSessionBridgeToken($user)
    {
        // Delete any existing bridge tokens
        $user->tokens()->where('name', 'session-bridge')->delete();
        
        // Create a new bridge token that never expires (for prototype)
        $token = $user->createToken('session-bridge');
        
        // Store token in session so it can be passed to views
        request()->session()->put('bridge_token', $token->plainTextToken);
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