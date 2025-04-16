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
        $this->loadRailwayDatabaseConfig();
        
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
        // Force HTTPS in production
        if (config('app.env') === 'production') {
            URL::forceScheme('https');
        }
        
        // Special configuration for Railway production environment
        if ($this->app->environment('production') && env('DOCKER_CONTAINER') === 'true') {
            // Configure session for Railway
            $this->configureRailwaySession();
            
            // Configure database for Railway
            $this->configureRailwayDatabase();
        }
    }
    
    /**
     * Configure database connection for Railway environment
     */
    protected function configureRailwayDatabase(): void
    {
        try {
            $databaseUrl = env('DATABASE_URL');
            
            if (!empty($databaseUrl)) {
                Log::info("Parsing DATABASE_URL for Railway environment");
                
                $parsedUrl = parse_url($databaseUrl);
                
                if ($parsedUrl !== false) {
                    $host = $parsedUrl['host'] ?? null;
                    $port = $parsedUrl['port'] ?? '5432';
                    $username = $parsedUrl['user'] ?? null;
                    $password = $parsedUrl['pass'] ?? null;
                    $path = $parsedUrl['path'] ?? null;
                    $database = $path ? ltrim($path, '/') : null;
                    
                    if ($host && $username && $database) {
                        // Reconfigure the database on-the-fly
                        Config::set('database.connections.pgsql.host', $host);
                        Config::set('database.connections.pgsql.port', $port);
                        Config::set('database.connections.pgsql.database', $database);
                        Config::set('database.connections.pgsql.username', $username);
                        Config::set('database.connections.pgsql.password', $password);
                        
                        // Reconnect to apply changes
                        DB::purge('pgsql');
                        DB::reconnect('pgsql');
                        
                        Log::info("Railway database configuration applied", [
                            'host' => $host,
                            'port' => $port,
                            'database' => $database,
                            'username' => $username
                        ]);
                    } else {
                        Log::error("Failed to parse DATABASE_URL: missing components", [
                            'has_host' => !empty($host),
                            'has_username' => !empty($username),
                            'has_database' => !empty($database)
                        ]);
                    }
                } else {
                    Log::error("Failed to parse DATABASE_URL: invalid URL format");
                }
            } else {
                Log::error("DATABASE_URL environment variable is not set");
            }
        } catch (\Exception $e) {
            Log::error('Failed to configure Railway database: ' . $e->getMessage());
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

    /**
     * Load the custom database configuration for Railway if available
     */
    private function loadRailwayDatabaseConfig(): void
    {
        $configPath = base_path('bootstrap/cache/railway_database.php');
        
        if (file_exists($configPath)) {
            try {
                $railwayConfig = require $configPath;
                
                if (isset($railwayConfig['connections']['pgsql'])) {
                    // Merge the Railway database configuration with the existing configuration
                    Config::set('database.connections.pgsql', $railwayConfig['connections']['pgsql']);
                    Log::info('Loaded Railway database configuration');
                }
            } catch (\Throwable $e) {
                Log::error('Failed to load Railway database configuration: ' . $e->getMessage());
            }
        }
    }
}
