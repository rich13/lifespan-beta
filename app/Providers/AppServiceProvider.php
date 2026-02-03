<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\Temporal\TemporalService;
use App\Services\Temporal\PrecisionValidator;
use App\Services\Connection\ConnectionConstraintService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Database\Eloquent\Casts\Json;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Configure database as early as possible, but only in production
        if ($this->app->environment('production')) {
            $this->configureDatabase();
        }
        
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
        // Configure database again to ensure it takes priority, but only in production
        if ($this->app->environment('production')) {
            $this->configureDatabase();
        }

        $this->registerSafeJsonDecoder();

        // Register observers
        $this->registerObservers();
        
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
        
        // Make span types available globally for the new span modal.
        // Only select type_id and name to avoid loading/decoding metadata on every request
        // (metadata can be large and was causing 60s timeouts when decoding all types).
        // Subtype options are loaded via AJAX when the user selects a type (spans.types.subtype-options).
        // Run the query once per request and share; composer runs for every view ('*') so without this we'd run it 600+ times.
        View::composer('*', function ($view) {
            if (!$view->offsetExists('spanTypes')) {
                $spanTypes = View::shared('spanTypes');
                if ($spanTypes === null) {
                    $spanTypes = \App\Models\SpanType::whereNotIn('type_id', ['connection', 'note', 'set'])
                        ->orderBy('name')
                        ->select('type_id', 'name')
                        ->get();
                    View::share('spanTypes', $spanTypes);
                }
                $view->with('spanTypes', $spanTypes);
            }
        });
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
    
    /**
     * Register a safe JSON decoder to prevent 60s timeouts from oversized or
     * pathological JSON in DB columns (e.g. Span metadata/sources).
     */
    protected function registerSafeJsonDecoder(): void
    {
        $maxBytes = (int) config('app.json_decode_max_bytes');

        Json::decodeUsing(function (mixed $value, ?bool $associative = true) use ($maxBytes): mixed {
            if ($value === null || $value === '') {
                return null;
            }
            if (! is_string($value)) {
                return $value;
            }
            if (strlen($value) > $maxBytes) {
                Log::warning('JSON column exceeded max size, returning empty structure to prevent timeout', [
                    'size_bytes' => strlen($value),
                    'max_bytes' => $maxBytes,
                ]);

                return $associative ? [] : new \stdClass;
            }
            $decoded = json_decode($value, $associative, 512);
            if (json_last_error() !== \JSON_ERROR_NONE) {
                Log::warning('JSON decode failed for column', [
                    'error' => json_last_error_msg(),
                    'length' => strlen($value),
                ]);

                return $associative ? [] : new \stdClass;
            }

            return $decoded;
        });
    }

    /**
     * Register model observers
     */
    protected function registerObservers(): void
    {
        // Register Span observer to enforce personal span constraints
        \App\Models\Span::observe(\App\Observers\SpanObserver::class);
        
        // Register Connection observer to capture deletion snapshots
        \App\Models\Connection::observe(\App\Observers\ConnectionObserver::class);
    }
}
