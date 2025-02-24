<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use App\Models\Span;

class SpanAccessMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $span = $request->route('span');
        
        // For show route
        if ($span) {
            // Non-public spans require authentication
            if ($span->access_level !== 'public' && !Auth::check()) {
                return redirect()->route('login');
            }

            // Public spans are accessible to everyone
            if ($span->access_level === 'public') {
                return $next($request);
            }

            $user = Auth::user();
            
            // Admin can access all spans
            if ($user->is_admin) {
                return $next($request);
            }

            // Owner can access their spans
            if ($span->owner_id === $user->id) {
                return $next($request);
            }

            // Private spans are only accessible to owner and admin
            if ($span->access_level === 'private') {
                abort(403, 'This span is private.');
            }

            // For shared spans, check permissions
            if ($span->access_level === 'shared' && !$span->permissions()->where('user_id', $user->id)->exists()) {
                abort(403, 'You do not have permission to view this span.');
            }

            // Check view permission using policy
            if (!Gate::allows('view', $span)) {
                abort(403, 'You do not have permission to view this span.');
            }

            // For write operations, check edit permission
            if (in_array($request->method(), ['PUT', 'PATCH', 'DELETE'])) {
                if (!Gate::allows($request->method() === 'DELETE' ? 'delete' : 'update', $span)) {
                    abort(403, 'You do not have permission to modify this span.');
                }
            }
        }

        return $next($request);
    }
} 