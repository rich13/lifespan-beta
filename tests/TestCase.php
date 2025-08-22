<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Artisan;
use Exception;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication, PostgresRefreshDatabase;

    /**
     * The name of the test database.
     */
    protected string $testDatabaseName = 'lifespan_beta_testing';

    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        // Force testing environment variables before app bootstraps
        $this->forceTestingEnvironment();
        
        // First call parent::setUp() to bootstrap the application and set up database transactions
        parent::setUp();

        // Now it's safe to use facades
        // Ensure tests only run in testing environment to prevent data corruption
        if (!App::environment('testing')) {
            $this->markTestSkipped(
                'ERROR: Tests must be run in the testing environment. ' .
                'Use --env=testing with phpunit or ensure APP_ENV=testing in your .env.testing file.'
            );
        }

        // Set database connection to testing connection explicitly
        $this->forceDatabaseConnection();
        
        // Validate database initialization
        $this->validateDatabaseInit();
        
        // Run specific migrations that add connection types
        $this->artisan('migrate', [
            '--path' => 'database/migrations/2024_03_21_000001_add_contains_connection_type.php',
            '--force' => true
        ]);
        
        // Seed common test data
        $this->seed(\Database\Seeders\TestDatabaseSeeder::class);
        
        // Disable CSRF token verification during tests
        Config::set('session.driver', 'array');
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
        
        // Mock external services to prevent real API calls during tests
        $this->mockExternalServices();
        
        // Validate test environment after everything is set up
        $this->validateTestEnvironment();
    }

    /**
     * Force testing environment variables
     */
    protected function forceTestingEnvironment(): void
    {
        // Ensure APP_ENV is set to testing
        putenv('APP_ENV=testing');
        $_ENV['APP_ENV'] = 'testing';
        $_SERVER['APP_ENV'] = 'testing';
        
        // Ensure DB_DATABASE is set to test database
        putenv("DB_DATABASE={$this->testDatabaseName}");
        $_ENV['DB_DATABASE'] = $this->testDatabaseName;
        $_SERVER['DB_DATABASE'] = $this->testDatabaseName;
    }

    /**
     * Force database connection to testing
     */
    protected function forceDatabaseConnection(): void
    {
        // Set default connection to testing
        Config::set('database.default', 'testing');
        
        // Configure testing connection explicitly
        Config::set('database.connections.testing.database', $this->testDatabaseName);
        
        // Purge existing connections to ensure we're using the testing connection
        DB::purge();
        DB::reconnect('testing');
        
        // Log the enforced connection
        Log::debug('Enforced test database connection', [
            'database' => DB::getDatabaseName(),
            'connection' => Config::get('database.default')
        ]);
    }

    /**
     * Validate the test environment to ensure proper isolation
     */
    protected function validateTestEnvironment(): void
    {
        // Check environment
        $this->assertTrue(
            app()->environment('testing'),
            'Tests must run in the testing environment'
        );

        // Check database name - allow for parallel test databases
        $this->assertStringStartsWith(
            'lifespan_beta_testing',
            DB::getDatabaseName(),
            'Tests must use a test database'
        );

        // Check database connection
        $this->assertEquals(
            'pgsql',
            DB::connection()->getDriverName(),
            'Tests must use PostgreSQL'
        );

        // Check transaction level
        if (DB::connection()->transactionLevel() <= 0) {
            Log::warning('⚠️ No transaction detected - tests should run in transactions for proper isolation!');
            Log::warning('This may occur if the database driver doesn\'t support transactions or if RefreshDatabase is not working properly.');
            
            // We won't fail the test, but log clearly that we're not in a transaction
            // This allows tests to run, but provides visibility that they may not be fully isolated
        } else {
            Log::info('✅ Transaction level: ' . DB::connection()->transactionLevel());
        }

        // Log test environment details for debugging
        Log::debug('Test environment validation', [
            'environment' => app()->environment(),
            'database' => DB::getDatabaseName(),
            'connection' => DB::connection()->getDriverName(),
            'transaction_level' => DB::connection()->transactionLevel(),
            'container' => gethostname(),
        ]);
    }

    /**
     * Ensure we're using the test database connection
     */
    protected function getDatabaseConnection(): string
    {
        return 'testing';
    }

    /**
     * Boot the testing helper traits.
     *
     * @return array
     */
    protected function setUpTraits()
    {
        $uses = parent::setUpTraits();

        if (isset($uses[PostgresRefreshDatabase::class])) {
            $this->setupDatabase();
        }

        return $uses;
    }

    /**
     * Setup the database for testing.
     *
     * @return void
     */
    protected function setupDatabase()
    {
        // Force the connection to be 'testing'
        config(['database.default' => 'testing']);
        
        // Log the current database configuration
        Log::info('Test database configuration', [
            'connection' => config('database.default'),
            'database' => config('database.connections.testing.database'),
            'host' => config('database.connections.testing.host'),
        ]);

        // Check initial transaction level
        try {
            $initialTransLevel = DB::connection('testing')->transactionLevel();
            Log::info('Initial transaction level', ['level' => $initialTransLevel]);
            
            // Register a callback to check the transaction level after the application is destroyed
            $this->beforeApplicationDestroyed(function () use ($initialTransLevel) {
                try {
                    $finalTransLevel = DB::connection('testing')->transactionLevel();
                    Log::info('Final transaction level', ['level' => $finalTransLevel]);
                    
                    if ($finalTransLevel !== $initialTransLevel) {
                        Log::warning('Transaction level mismatch', [
                            'initial' => $initialTransLevel,
                            'final' => $finalTransLevel
                        ]);
                    }
                } catch (Exception $e) {
                    Log::error('Error checking final transaction level', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            });
        } catch (Exception $e) {
            Log::error('Error checking initial transaction level', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Validate the test database was properly initialized
     */
    protected function validateDatabaseInit(): void
    {
        try {
            // Check if the test database has basic required tables
            $hasMigrationsTable = DB::connection('testing')
                ->getSchemaBuilder()
                ->hasTable('migrations');
                
            if (!$hasMigrationsTable) {
                $this->markTestSkipped(
                    "ERROR: Test database was not properly initialized. " .
                    "Please run the master test script to ensure proper test environment."
                );
            }
            
            // Check for span_types table which should be populated by TestDatabaseSeeder
            $hasSpanTypes = DB::connection('testing')
                ->getSchemaBuilder()
                ->hasTable('span_types');
                
            if (!$hasSpanTypes) {
                Log::warning('Test database is missing span_types table - migrations may not have completed properly');
            }
        } catch (Exception $e) {
            $this->markTestSkipped(
                "ERROR: Failed to validate test database: " . $e->getMessage()
            );
        }
    }

    /**
     * Mock external services to prevent real API calls during tests
     */
    protected function mockExternalServices(): void
    {
        // Mock MusicBrainzCoverArtService to prevent real API calls during tests
        $this->mock(\App\Services\MusicBrainzCoverArtService::class, function ($mock) {
            $mock->shouldReceive('getCoverArt')->andReturn(null);
            $mock->shouldReceive('getFrontCoverUrl')->andReturn(null);
            $mock->shouldReceive('getAllCoverUrls')->andReturn([]);
            $mock->shouldReceive('hasCoverArt')->andReturn(false);
            $mock->shouldReceive('getCoverArtSummary')->andReturn(null);
            $mock->shouldReceive('clearCache')->andReturn(null);
            $mock->shouldReceive('clearAllCaches')->andReturn(null);
        });
    }
}
