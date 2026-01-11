<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View|RedirectResponse
    {
        $email = session('email') ?? old('email');
        
        // If no email in session, redirect back to login
        if (!$email) {
            return redirect()->route('login')
                ->withErrors(['email' => 'Please enter your email address to begin registration.']);
        }
        
        return view('auth.register', [
            'email' => $email
        ]);
    }

    /**
     * Display the pending approval view.
     */
    public function pending(): View
    {
        return view('auth.pending-approval');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(RegisterRequest $request): RedirectResponse
    {
        $user = $request->register();

        // Always redirect to pending page - user needs to verify email and get approved
        // Even if auto-approved (via invite code), they still need to verify email
        return redirect()->route('register.pending')
            ->with('status', 'Registration successful! Please check your email to verify your address.');
    }
}
