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
use Illuminate\Support\Facades\DB;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Configure database as early as possible
        $this->configureDatabase();
        
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
        // Configure database again to ensure it takes priority
        $this->configureDatabase();
        
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
     * Set database configuration directly with highest priority
     */
    protected function configureDatabase(): void
    {
        try {
            // Get database credentials from environment variables
            // Try PG* variables first (set by our scripts)
            $host = env('PGHOST');
            $port = env('PGPORT', '5432');
            $database = env('PGDATABASE');
            $username = env('PGUSER');
            $password = env('PGPASSWORD');

            // If PG* variables aren't available, try DATABASE_URL
            if (empty($host) || empty($database) || empty($username)) {
                $databaseUrl = env('DATABASE_URL');
                
                if (!empty($databaseUrl)) {
                    Log::info("Using DATABASE_URL instead of PG* variables");
                    
                    $parsedUrl = parse_url($databaseUrl);
                    
                    if ($parsedUrl !== false) {
                        $host = $parsedUrl['host'] ?? null;
                        $port = $parsedUrl['port'] ?? '5432';
                        $username = $parsedUrl['user'] ?? null;
                        $password = $parsedUrl['pass'] ?? null;
                        $path = $parsedUrl['path'] ?? null;
                        $database = $path ? ltrim($path, '/') : null;
                    }
                }
            }

            // Log what we're using for debugging
            if (!empty($host) && !empty($database) && !empty($username)) {
                Log::info("Setting database configuration directly", [
                    'host' => $host,
                    'port' => $port,
                    'database' => $database,
                    'username' => $username,
                    'has_password' => !empty($password)
                ]);
                
                // Set the configuration directly - this overrides anything previously set
                Config::set('database.default', 'pgsql');
                Config::set('database.connections.pgsql.driver', 'pgsql');
                Config::set('database.connections.pgsql.host', $host);
                Config::set('database.connections.pgsql.port', $port);
                Config::set('database.connections.pgsql.database', $database);
                Config::set('database.connections.pgsql.username', $username);
                
                if (!empty($password)) {
                    Config::set('database.connections.pgsql.password', $password);
                }
                
                // Force database to reconnect with new config
                DB::purge('pgsql');
                DB::reconnect('pgsql');
                
                Log::info("Database configuration set and connection purged/reconnected");
            } else {
                Log::error("Failed to set database configuration - missing required values", [
                    'has_host' => !empty($host),
                    'has_database' => !empty($database),
                    'has_username' => !empty($username)
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to configure database: ' . $e->getMessage());
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
