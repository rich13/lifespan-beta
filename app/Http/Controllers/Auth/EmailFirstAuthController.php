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

class EmailFirstAuthController extends Controller
{
    public function showEmailForm()
    {
        return view('auth.email-first');
    }

    public function handleEmail(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::where('email', $request->email)->first();

        if ($user) {
            // User exists - show password login form
            return view('auth.password', ['email' => $request->email]);
        } else {
            // New user - show registration form
            return view('auth.register', ['email' => $request->email]);
        }
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();
            return redirect()->intended(RouteServiceProvider::HOME);
        }

        return back()
            ->withInput(['email' => $request->email])
            ->withErrors([
                'password' => 'The provided credentials do not match our records.'
            ]);
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'birth_year' => ['required', 'integer', 'min:1900', 'max:' . date('Y')],
            'birth_month' => ['required', 'integer', 'min:1', 'max:12'],
            'birth_day' => ['required', 'integer', 'min:1', 'max:31'],
        ]);

        // Create user without personal span first
        $user = User::create([
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        // Create personal span
        $personalSpan = $user->createPersonalSpan([
            'name' => $validated['name'],
            'birth_year' => $validated['birth_year'],
            'birth_month' => $validated['birth_month'],
            'birth_day' => $validated['birth_day'],
        ]);

        event(new Registered($user));
        
        Auth::login($user);

        return redirect(RouteServiceProvider::HOME);
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
} 