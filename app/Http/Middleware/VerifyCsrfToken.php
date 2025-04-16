<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;
use Illuminate\Support\Facades\Log;
use Closure;
use Illuminate\Http\Request;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        //
    ];
    
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        // In Railway production, log CSRF details to help with debugging
        if (env('APP_ENV') === 'production' && env('DOCKER_CONTAINER') === 'true') {
            $this->logCsrfDetails($request);
        }
        
        // If it's a login page/route and there's an issue with the token, regenerate it
        if ($this->isRelevantAuthRoute($request) && !$request->session()->has('_token')) {
            $request->session()->regenerate();
            Log::debug('CSRF: Regenerated token on auth route');
        }
        
        return parent::handle($request, $next);
    }
    
    /**
     * Check if the current route is an authentication route 
     */
    protected function isRelevantAuthRoute(Request $request): bool
    {
        $authRoutes = [
            'login', 'register', 'password/reset', 'password/email',
            'auth/email', 'auth/magic-link'
        ];
        
        foreach ($authRoutes as $route) {
            if ($request->is($route) || $request->is("*/$route")) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Log CSRF debugging details 
     */
    protected function logCsrfDetails(Request $request): void
    {
        $hasToken = $request->session()->has('_token');
        $tokenInSession = $request->session()->get('_token');
        $tokenInHeader = $request->header('X-CSRF-TOKEN');
        $tokenInInput = $request->input('_token');
        
        Log::debug('CSRF Debug Info', [
            'uri' => $request->getRequestUri(),
            'has_token' => $hasToken,
            'token_length' => $tokenInSession ? strlen($tokenInSession) : 0,
            'header_token_exists' => !empty($tokenInHeader),
            'input_token_exists' => !empty($tokenInInput),
            'session_id' => $request->session()->getId(),
            'cookies' => $request->cookies->all(),
        ]);
    }
}
