<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\Temporal\TemporalService;
use App\Services\Temporal\PrecisionValidator;
use App\Services\Connection\ConnectionConstraintService;
use App\Services\Comparison\Comparers\ConnectionComparer;
use App\Services\Comparison\Comparers\HistoricalContextComparer;
use App\Services\Comparison\Comparers\SignificantEventComparer;
use App\Services\Comparison\Contracts\SpanComparerInterface;
use App\Services\Comparison\SpanComparisonService;

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

        // Register comparison services
        $this->app->singleton(SignificantEventComparer::class);
        $this->app->singleton(HistoricalContextComparer::class);
        $this->app->singleton(ConnectionComparer::class);
        
        $this->app->singleton(SpanComparerInterface::class, SpanComparisonService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
