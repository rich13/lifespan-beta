<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;

class RailwayUploadLimits
{
    /**
     * Handle Railway-specific upload size limits and provide better error handling.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only apply in production Railway environment
        if (env('APP_ENV') === 'production' && env('DOCKER_CONTAINER') === 'true') {
            
            // Check if this is a file upload request
            if ($request->hasFile('photos') || $request->hasFile('files')) {
                
                // Log upload attempt for debugging
                Log::info('RailwayUploadLimits: File upload detected', [
                    'content_length' => $request->header('Content-Length'),
                    'content_type' => $request->header('Content-Type'),
                    'files_count' => $request->hasFile('photos') ? count($request->file('photos')) : 0,
                    'user_agent' => $request->header('User-Agent')
                ]);
                
                // Check content length header
                $contentLength = $request->header('Content-Length');
                if ($contentLength) {
                    $sizeInMB = round($contentLength / 1024 / 1024, 2);
                    Log::info("RailwayUploadLimits: Request size is {$sizeInMB}MB");
                    
                    // If request is larger than 50MB, log a warning
                    if ($contentLength > 50 * 1024 * 1024) {
                        Log::warning("RailwayUploadLimits: Large upload detected", [
                            'size_mb' => $sizeInMB,
                            'content_length' => $contentLength
                        ]);
                    }
                }
            }
        }
        
        return $next($request);
    }
}
