<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class RequestResponseLogger
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
        // Log at the very start of each request so separate page requests are easy to find in the logs
        $requestId = uniqid('req_', true);
        $request->attributes->set('request_id', $requestId);
        Log::info('Page request started', [
            'request_id' => $requestId,
            'method' => $request->method(),
            'url' => $request->fullUrl(),
        ]);

        // Process the request
        $response = $next($request);
        
        try {
            // Only log if we're in production (Railway) and response code is 4xx or 5xx
            if (app()->environment('production') && $response->getStatusCode() >= 400) {
                $this->logRequest($request, $response);
            }
        } catch (\Exception $e) {
            // Don't let logging failures break the app
            try {
                Log::error('Error in request logging: ' . $e->getMessage());
            } catch (\Exception $loggingError) {
                // If even the basic logging fails, just continue silently
            }
        }
        
        return $response;
    }
    
    /**
     * Log the request and response details
     */
    protected function logRequest(Request $request, $response)
    {
        $status = $response->getStatusCode();
        
        $data = [
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'status' => $status,
            'ip' => $request->ip(),
            'user_agent' => $request->header('User-Agent'),
            'referrer' => $request->header('referer'),
            'request_id' => $request->attributes->get('request_id') ?? $request->header('X-Request-ID'),
        ];
        
        // Add session ID if available
        if ($request->hasSession()) {
            $data['session_id'] = $request->session()->getId();
        }
        
        // Add authenticated user if available
        if ($request->user()) {
            $data['user_id'] = $request->user()->id;
            $data['user_email'] = $request->user()->email;
        }
        
        // Add request data for non-GET requests (limited for security)
        if ($request->method() !== 'GET') {
            // Only include safe fields, exclude passwords and tokens
            $safeFields = collect($request->all())->except(['password', 'password_confirmation', 'token', '_token', 'csrf_token']);
            $data['request_data'] = $safeFields->toArray();
        }
        
        // Add query parameters for all requests
        if ($request->query()) {
            $data['query_params'] = $request->query();
        }
        
        // For error responses, include more details
        if ($status >= 400) {
            // For 500 errors, include exception details if available
            if ($status >= 500 && app()->bound('sentry')) {
                $data['sentry_id'] = app('sentry')->getLastEventId();
            }
            
            // If the response is a view with errors
            if ($response instanceof \Illuminate\Http\Response && $response->original instanceof \Illuminate\View\View) {
                if (isset($response->original->errors) && $response->original->errors->any()) {
                    $data['validation_errors'] = $response->original->errors->toArray();
                }
            }
        }
        
        // Log with appropriate level based on status code
        if ($status >= 500) {
            Log::error('Server Error', $data);
        } elseif ($status >= 400) {
            Log::warning('Client Error', $data);
        } else {
            Log::info('Request', $data);
        }
    }
} 