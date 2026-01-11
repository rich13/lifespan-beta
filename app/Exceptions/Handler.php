<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        // Handle invalid signature exceptions (expired verification links)
        $this->renderable(function (InvalidSignatureException $e, $request) {
            if ($request->routeIs('verification.verify')) {
                return redirect()->route('login')
                    ->withErrors(['email' => 'Invalid or expired verification link. Please request a new verification email.']);
            }
        });

        $this->reportable(function (Throwable $e) {
            // Enhance logging with request details for production environment
            if (app()->environment('production')) {
                $request = request();
                
                $context = [
                    'url' => $request->fullUrl(),
                    'method' => $request->method(),
                    'ip' => $request->ip(),
                    'user_agent' => $request->header('User-Agent'),
                    'referrer' => $request->header('referer'),
                    'request_id' => $request->header('X-Request-ID'),
                    'path' => $request->path(),
                    'route' => $request->route() ? $request->route()->getName() : 'unknown',
                ];
                
                // Add authenticated user if available
                if ($request->user()) {
                    $context['user_id'] = $request->user()->id;
                    $context['user_email'] = $request->user()->email;
                }
                
                // Log the exception with extra context
                \Illuminate\Support\Facades\Log::error(
                    $e->getMessage(), 
                    array_merge($context, [
                        'exception' => get_class($e),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'trace' => $e->getTraceAsString(),
                    ])
                );
            }
        });
    }
}
