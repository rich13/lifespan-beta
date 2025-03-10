<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UserSwitcherMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Allow access if the user is an admin OR if there's an admin_user_id in the session
        if ($request->user() && ($request->user()->is_admin || session()->has('admin_user_id'))) {
            return $next($request);
        }
        
        // Otherwise, deny access
        abort(403, 'Access denied. Admin privileges or admin session required.');
    }
}
