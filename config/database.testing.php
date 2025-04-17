<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Test Database Connection
    |--------------------------------------------------------------------------
    |
    | This configuration file provides a specific database setup for tests.
    | It defines the 'testing' connection that overrides the default DB
    | configuration when running tests.
    |
    */

    // Default database connection for tests
    'default' => env('DB_CONNECTION', 'pgsql'),

    // Database connections
    'connections' => [
        // Testing connection
        'testing' => [
            'driver' => 'pgsql',
            'url' => env('DATABASE_URL'),
            'host' => env('DB_HOST', 'postgres'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'lifespan_beta_testing'),
            'username' => env('DB_USERNAME', 'lifespan_user'),
            'password' => env('DB_PASSWORD', 'lifespan_password'),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => 'prefer',
        ],
    ],

    // Enforce foreign key constraints in tests
    'foreign_keys' => env('DB_FOREIGN_KEYS', true),

    // Use fast migrations to speed up tests
    'migrations' => [
        'table' => 'migrations',
        'path' => database_path('migrations'),
    ],
]; 