<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RequireProfileCompletion
{
    /**
     * Handle an incoming request.
     *
     * Redirect authenticated users without personal spans to profile completion.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $user = Auth::user();
            
            // Only require profile completion for verified users
            // Unverified users should verify their email first
            if (!$user->hasVerifiedEmail()) {
                return $next($request);
            }
            
            // Skip if user already has a personal span
            if ($user->personal_span_id) {
                return $next($request);
            }
            
            // Skip if already on profile completion page
            if ($request->routeIs('profile.complete') || $request->routeIs('profile.complete.store')) {
                return $next($request);
            }
            
            // Skip for logout route
            if ($request->routeIs('logout')) {
                return $next($request);
            }
            
            // Skip for email verification routes
            if ($request->routeIs('verification.notice') || $request->routeIs('verification.send') || $request->routeIs('verification.verify')) {
                return $next($request);
            }
            
            // Redirect to profile completion
            return redirect()->route('profile.complete');
        }

        return $next($request);
    }
}
