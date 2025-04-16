<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;

class DatabaseConnectionLogger
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only log in Railway production environment
        if (env('APP_ENV') === 'production' && env('DOCKER_CONTAINER') === 'true') {
            try {
                // Ensure database configuration is set correctly before testing
                $this->ensureDatabaseConfigIsCorrect();
                
                $dbConfig = Config::get('database.connections.pgsql');
                
                // Log database configuration
                Log::info('Database connection configuration', [
                    'host' => $dbConfig['host'] ?? 'not set',
                    'port' => $dbConfig['port'] ?? 'not set',
                    'database' => $dbConfig['database'] ?? 'not set',
                    'username' => $dbConfig['username'] ?? 'not set',
                    'has_password' => !empty($dbConfig['password']),
                    'using_url' => !empty($dbConfig['url']),
                    'database_url' => !empty(env('DATABASE_URL')) ? 'is set' : 'not set',
                ]);
                
                // Test database connection
                DB::connection('pgsql')->getPdo();
                Log::info('Database connection successful');
            } catch (\Exception $e) {
                Log::error('Database connection failed: ' . $e->getMessage(), [
                    'trace' => $e->getTraceAsString(),
                    'database_url' => $this->maskSensitiveInfo(env('DATABASE_URL')),
                ]);
            }
        }
        
        return $next($request);
    }
    
    /**
     * Ensure database configuration is correct
     */
    private function ensureDatabaseConfigIsCorrect(): void
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
                    Log::info("Using DATABASE_URL directly in middleware");
                    
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

            // Only proceed if we have all required values
            if (!empty($host) && !empty($database) && !empty($username)) {
                Log::info("Setting database configuration directly from middleware", [
                    'host' => $host,
                    'port' => $port,
                    'database' => $database,
                    'username' => $username
                ]);
                
                // IMPORTANT: Hard-code values directly in the configuration
                Config::set('database.connections.pgsql', [
                    'driver' => 'pgsql',
                    'host' => $host,
                    'port' => $port,
                    'database' => $database,
                    'username' => $username,
                    'password' => $password ?? '',
                    'charset' => 'utf8',
                    'prefix' => '',
                    'prefix_indexes' => true,
                    'search_path' => 'public',
                    'sslmode' => 'prefer',
                ]);
                
                // Reconnect to apply changes
                DB::purge('pgsql');
                DB::reconnect('pgsql');
            }
        } catch (\Exception $e) {
            Log::error('Failed to configure database in middleware: ' . $e->getMessage());
        }
    }
    
    /**
     * Mask sensitive information in the DATABASE_URL
     */
    private function maskSensitiveInfo(?string $url): ?string
    {
        if (empty($url)) {
            return null;
        }
        
        // Replace password with asterisks
        return preg_replace('/(postgres:\/\/[^:]+:)([^@]+)(@.*)/', '$1*****$3', $url);
    }
} 