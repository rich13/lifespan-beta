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
                $dbConfig = Config::get('database.connections.pgsql');
                
                // Log database configuration (read-only, no reconfiguration)
                Log::info('Database connection configuration', [
                    'host' => $dbConfig['host'] ?? 'not set',
                    'port' => $dbConfig['port'] ?? 'not set',
                    'database' => $dbConfig['database'] ?? 'not set',
                    'username' => $dbConfig['username'] ?? 'not set',
                    'has_password' => !empty($dbConfig['password']),
                    'using_url' => !empty($dbConfig['url']),
                    'database_url' => !empty(env('DATABASE_URL')) ? 'is set' : 'not set',
                ]);
                
                // Test database connection (read-only test)
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