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
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Log;

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
        // Load testing-specific logging configuration in testing environment
        if ($this->app->environment('testing')) {
            // Override logging configuration for testing
            if (file_exists(config_path('logging.testing.php'))) {
                $testConfig = require config_path('logging.testing.php');
                Config::set('logging', $testConfig);
            }
        }
        
        // Special configuration for Railway production environment
        if ($this->app->environment('production') && env('DOCKER_CONTAINER') === 'true') {
            // Force HTTPS in production
            URL::forceScheme('https');
            
            // Configure session for Railway
            $this->configureRailwaySession();
        }
    }
    
    /**
     * Set up proper session configuration for Railway environment
     */
    protected function configureRailwaySession(): void
    {
        try {
            $appUrl = env('APP_URL');
            
            if (!empty($appUrl)) {
                $parsedUrl = parse_url($appUrl);
                $domain = $parsedUrl['host'] ?? null;
                
                if ($domain) {
                    // Set session domain for cookies
                    Config::set('session.domain', $domain);
                    
                    // Ensure secure cookies for HTTPS
                    Config::set('session.secure', true);
                    
                    // Set cookie SameSite attribute
                    Config::set('session.same_site', 'lax');
                    
                    // Log the configuration
                    Log::info("Railway session configuration set domain: {$domain}");
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to configure Railway session: ' . $e->getMessage());
        }
    }
}
