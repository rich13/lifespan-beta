<?php

/**
 * Initialize the testing database with predictable state
 * Run this before ALL tests to ensure consistent environment
 */

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;

// Force PostgreSQL to drop all connections to allow clean DB reset
DB::statement('SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = ? AND pid <> pg_backend_pid()', [
    config('database.connections.testing.database')
]);

// Run migrations from scratch
Artisan::call('migrate:fresh', [
    '--database' => 'testing',
    '--env' => 'testing',
    '--force' => true,
]);

// Run base seeders
Artisan::call('db:seed', [
    '--class' => 'Database\\Seeders\\TestDatabaseSeeder', 
    '--database' => 'testing',
    '--force' => true
]);

// Verify database setup
$result = DB::connection('testing')->select('SELECT COUNT(*) as count FROM span_types');
if (empty($result) || $result[0]->count < 1) {
    throw new Exception('Database initialization failed - missing expected span types');
}

// Set database testing flag to confirm proper initialization
DB::connection('testing')->table('migrations')
    ->where('migration', 'like', '%prepare_testing_environment%')
    ->update(['batch' => 999]);

echo "✓ Test database initialized successfully\n"; 