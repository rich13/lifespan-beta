<?php

// This script sets the database configuration directly in Laravel's configuration cache
// It bypasses the .env file and sets the configuration directly

// Load the Laravel application
require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';

// Get the environment variables
$dbHost = getenv('PGHOST');
$dbPort = getenv('PGPORT');
$dbDatabase = getenv('PGDATABASE');
$dbUsername = getenv('PGUSER');
$dbPassword = getenv('PGPASSWORD');

// Log the database configuration
echo "Setting database configuration:\n";
echo "DB_HOST: $dbHost\n";
echo "DB_PORT: $dbPort\n";
echo "DB_DATABASE: $dbDatabase\n";
echo "DB_USERNAME: $dbUsername\n";

// Set the database configuration directly
$config = [
    'default' => 'pgsql',
    'connections' => [
        'pgsql' => [
            'driver' => 'pgsql',
            'host' => $dbHost,
            'port' => $dbPort,
            'database' => $dbDatabase,
            'username' => $dbUsername,
            'password' => $dbPassword,
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => 'prefer',
        ],
    ],
];

// Write the configuration to a file
$configFile = __DIR__ . '/../bootstrap/cache/config.php';
file_put_contents($configFile, '<?php return ' . var_export($config, true) . ';');

echo "Database configuration set successfully.\n"; 