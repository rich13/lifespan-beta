<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use App\Models\Span;

class SetsAccessMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $routeName = $request->route()->getName();
        
        // For index route
        if ($routeName === 'sets.index') {
            // For unauthenticated users, only show public sets
            if (!Auth::check()) {
                $request->merge(['access_filter' => 'public_only']);
            } else {
                $user = Auth::user();
                if (!$user->is_admin) {
                    $request->merge(['access_filter' => 'user_accessible']);
                }
            }
            return $next($request);
        }

        // For show route
        if ($routeName === 'sets.show') {
            $set = $request->route('set');
            
            // If set is not a model, let the controller handle the 404
            if (!$set || !($set instanceof \App\Models\Span)) {
                return $next($request);
            }

            // Check if the set is public
            if ($set->access_level === 'public') {
                return $next($request);
            }

            // For non-public sets, require authentication
            if (!Auth::check()) {
                return redirect()->route('login');
            }

            $user = Auth::user();
            
            // Admin can access all sets
            if ($user->is_admin) {
                return $next($request);
            }

            // Check if user has permission to view this set
            if (!$set->hasPermission($user, 'view')) {
                abort(403, 'You do not have permission to view this set.');
            }
        }

        return $next($request);
    }
} 