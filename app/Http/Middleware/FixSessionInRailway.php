<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class FixSessionInRailway
{
    /**
     * Handle an incoming request and fix session configuration for Railway.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only apply fixes in production Docker environment (Railway)
        if (env('APP_ENV') === 'production' && env('DOCKER_CONTAINER') === 'true') {
            $domain = env('SESSION_DOMAIN');
            
            if (!$domain && $request->getHost()) {
                $domain = $request->getHost();
                // Update config dynamically
                Config::set('session.domain', $domain);
                
                // Log that we're setting the domain for debugging
                Log::debug("FixSessionInRailway: Setting session domain to {$domain}");
            }
            
            // Force secure cookies in production
            Config::set('session.secure', true);
            
            // Force SameSite attribute to be lax
            Config::set('session.same_site', 'lax');
            
            // Regenerate session ID if needed
            if (!$request->session()->has('_token')) {
                $request->session()->regenerate();
                Log::debug("FixSessionInRailway: Regenerated session due to missing CSRF token");
            }
        }
        
        return $next($request);
    }
} 