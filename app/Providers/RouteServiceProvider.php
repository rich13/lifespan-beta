<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\Models\Span;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();

        Route::bind('span', function ($value) {
            // First try to find by slug since that's what we use in URLs
            $span = Span::where('slug', $value)->first();
            
            // If not found by slug and the value is a valid UUID, try finding by ID
            if (!$span && Str::isUuid($value)) {
                $span = Span::where('id', $value)->first();
            }

            // If no span found, abort with 404
            if (!$span) {
                abort(404);
            }

            return $span;
        });

        Route::bind('subject', function ($value) {
            // First try to find by slug since that's what we use in URLs
            $span = Span::where('slug', $value)->first();
            
            // If not found by slug and the value is a valid UUID, try finding by ID
            if (!$span && Str::isUuid($value)) {
                $span = Span::where('id', $value)->first();
            }

            // If no span found, abort with 404
            if (!$span) {
                abort(404);
            }

            return $span;
        });

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }

    /**
     * Configure the rate limiters for the application.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });
    }
}
