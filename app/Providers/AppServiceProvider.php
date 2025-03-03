<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\Temporal\TemporalService;
use App\Services\Temporal\PrecisionValidator;
use App\Services\Connection\ConnectionConstraintService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register PrecisionValidator as a singleton
        $this->app->singleton(PrecisionValidator::class);

        // Register TemporalService as a singleton with PrecisionValidator dependency
        $this->app->singleton(TemporalService::class, function ($app) {
            return new TemporalService($app->make(PrecisionValidator::class));
        });

        // Register ConnectionConstraintService with dependencies
        $this->app->singleton(ConnectionConstraintService::class, function ($app) {
            return new ConnectionConstraintService(
                $app->make(TemporalService::class),
                $app->make(PrecisionValidator::class)
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
