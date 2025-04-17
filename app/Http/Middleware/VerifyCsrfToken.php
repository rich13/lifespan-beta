<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Encryption\Encrypter;
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
     * Create a new middleware instance.
     *
     * @param \Illuminate\Contracts\Foundation\Application $app
     * @param \Illuminate\Contracts\Encryption\Encrypter $encrypter
     * @return void
     */
    public function __construct(Application $app, Encrypter $encrypter)
    {
        parent::__construct($app, $encrypter);
        
        // In Railway environment, exclude auth routes from CSRF verification
        if (env('APP_ENV') === 'production' && env('DOCKER_CONTAINER') === 'true') {
            $this->except = array_merge($this->except, [
                'auth/*',
                'login',
                'logout',
                'register',
                'password/*'
            ]);
            
            // Log that we're excluding these routes
            Log::info('Railway environment detected: Auth routes excluded from CSRF verification', [
                'excluded_routes' => $this->except
            ]);
        }
    }
    
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
            
            // Check if the current URL is one of the excluded paths
            foreach ($this->except as $except) {
                if ($request->is($except)) {
                    Log::info('CSRF verification skipped for route: ' . $request->path());
                    return $next($request);
                }
            }
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
        try {
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
                'is_excluded' => $this->isExcluded($request),
            ]);
        } catch (\Exception $e) {
            Log::error('Error logging CSRF details', [
                'error' => $e->getMessage(),
            ]);
        }
    }
    
    /**
     * Determine if the request has a URI that should be CSRF verified.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    protected function isExcluded($request)
    {
        foreach ($this->except as $except) {
            if ($request->is($except)) {
                return true;
            }
        }

        return false;
    }
}
