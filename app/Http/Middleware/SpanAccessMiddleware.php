<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use App\Models\Span;
use Illuminate\Support\Str;

class SpanAccessMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $routeName = $request->route()->getName();
            
            // For index route
            if ($routeName === 'spans.index') {
                // For unauthenticated users, only show public spans
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
            if ($routeName === 'spans.show') {
                $span = $request->route('span');
                
                // Handle case when span doesn't exist
                if (!$span) {
                    return abort(404, 'Span not found');
                }
                
                // For unauthenticated users, redirect to login for non-public spans
                if (!Auth::check()) {
                    if ($span->access_level !== 'public') {
                        return redirect()->route('login');
                    }
                    return $next($request);
                }

                // For authenticated users
                $user = Auth::user();
                
                // Admin can access all spans
                if ($user->is_admin) {
                    return $next($request);
                }

                // Owner can access their spans
                if ($span->owner_id === $user->id) {
                    return $next($request);
                }

                // Public spans are visible to everyone
                if ($span->access_level === 'public') {
                    return $next($request);
                }

                // For shared spans, check permissions
                if ($span->access_level === 'shared') {
                    if ($span->permissions()->where('user_id', $user->id)->exists()) {
                        return $next($request);
                    }
                    return abort(403, 'You do not have permission to view this span.');
                }

                // Private spans are only accessible to owner and admin
                return abort(403, 'This span is private.');
            }

            // For other routes that require authentication
            if (in_array($routeName, ['spans.edit', 'spans.update', 'spans.destroy', 'spans.create', 'spans.store'])) {
                if (!Auth::check()) {
                    return redirect()->route('login');
                }
                return $next($request);
            }

            return $next($request);
        } catch (\Exception $e) {
            // Log the error
            \Illuminate\Support\Facades\Log::error('SpanAccessMiddleware error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'route' => $request->path()
            ]);
            
            // Return a generic error for production or detailed error for development
            if (app()->environment('production')) {
                return abort(500, 'An error occurred while processing your request.');
            } else {
                return abort(500, 'SpanAccessMiddleware error: ' . $e->getMessage());
            }
        }
    }
} 