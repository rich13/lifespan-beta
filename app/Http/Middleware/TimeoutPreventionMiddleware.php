<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class TimeoutPreventionMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip timeout prevention during testing
        if (app()->environment('testing')) {
            return $next($request);
        }
        
        // Set execution time limit for long-running operations
        $maxExecutionTime = 60; // 60 seconds
        set_time_limit($maxExecutionTime);
        
        // Set memory limit for large operations
        $memoryLimit = '256M';
        ini_set('memory_limit', $memoryLimit);
        
        // Log the start of potentially long-running operations
        if ($this->isPotentiallyLongRunning($request)) {
            Log::info('Starting potentially long-running operation', [
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'user_id' => $request->user()?->id,
                'max_execution_time' => $maxExecutionTime,
                'memory_limit' => $memoryLimit
            ]);
        }
        
        try {
            $response = $next($request);
            
            // Log successful completion
            if ($this->isPotentiallyLongRunning($request)) {
                Log::info('Completed potentially long-running operation', [
                    'url' => $request->fullUrl(),
                    'method' => $request->method(),
                    'user_id' => $request->user()?->id,
                    'response_time' => microtime(true) - LARAVEL_START
                ]);
            }
            
            return $response;
            
        } catch (\ErrorException $e) {
            // Only catch timeout-related errors
            if (strpos($e->getMessage(), 'Maximum execution time') !== false) {
                Log::error('Operation timed out', [
                    'url' => $request->fullUrl(),
                    'method' => $request->method(),
                    'user_id' => $request->user()?->id,
                    'error' => $e->getMessage(),
                    'execution_time' => microtime(true) - LARAVEL_START
                ]);
                
                return response()->json([
                    'error' => 'Operation timed out. Please try again with a smaller dataset.',
                    'message' => 'The request took too long to process. This usually happens with complex data structures.'
                ], 408); // Request Timeout
            }
            
            // Re-throw non-timeout exceptions
            throw $e;
        } catch (\Exception $e) {
            // Only catch timeout-related exceptions
            if (strpos($e->getMessage(), 'Maximum execution time') !== false || 
                strpos($e->getMessage(), 'timeout') !== false) {
                Log::error('Operation timed out', [
                    'url' => $request->fullUrl(),
                    'method' => $request->method(),
                    'user_id' => $request->user()?->id,
                    'error' => $e->getMessage(),
                    'execution_time' => microtime(true) - LARAVEL_START
                ]);
                
                return response()->json([
                    'error' => 'Operation timed out. Please try again with a smaller dataset.',
                    'message' => 'The request took too long to process. This usually happens with complex data structures.'
                ], 408); // Request Timeout
            }
            
            // Re-throw non-timeout exceptions
            throw $e;
        }
    }
    
    /**
     * Check if the request is potentially long-running
     */
    private function isPotentiallyLongRunning(Request $request): bool
    {
        $longRunningPatterns = [
            '#^spans/.*/editor$#',
            '#^spans/.*/yaml$#',
            '#^api/spans/.*/timeline$#',
            '#^spans/.*/show$#',
            '#^admin/.*/export$#',
            '#^admin/.*/import$#'
        ];
        
        foreach ($longRunningPatterns as $pattern) {
            if (preg_match($pattern, $request->path())) {
                return true;
            }
        }
        
        return false;
    }
}
