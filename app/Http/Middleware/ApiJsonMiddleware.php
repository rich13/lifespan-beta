<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiJsonMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Ensure the request expects JSON
        $request->headers->set('Accept', 'application/json');
        
        $response = $next($request);
        
        // If the response is a redirect (like to login), convert it to a JSON error
        if ($response->getStatusCode() === 302) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Authentication required'
            ], 401);
        }
        
        return $response;
    }
} 