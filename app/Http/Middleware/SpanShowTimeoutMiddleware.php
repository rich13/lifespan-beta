<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SpanShowTimeoutMiddleware
{
    /**
     * Raise PHP max_execution_time and memory_limit for the span show route only.
     * This is an app-level setting; no need to pay for a bigger server just for this.
     * Nginx/proxy (e.g. Railway) must also allow long requests (see docker/prod/nginx.conf).
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment('testing')) {
            return $next($request);
        }

        $maxExecutionTime = config('app.span_show_max_execution_time', 120);
        set_time_limit($maxExecutionTime);

        $memoryLimit = config('app.span_show_memory_limit');
        if ($memoryLimit) {
            @ini_set('memory_limit', $memoryLimit);
        }

        return $next($request);
    }
}
