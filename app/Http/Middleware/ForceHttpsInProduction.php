<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class ForceHttpsInProduction
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        if (app()->environment('production')) {
            // Force HTTPS for all URLs in production
            URL::forceScheme('https');
            
            // Update the request to reflect the forced HTTPS scheme
            if ($request->header('x-forwarded-proto') === 'https') {
                $request->server->set('HTTPS', 'on');
            }
        }

        return $next($request);
    }
} 