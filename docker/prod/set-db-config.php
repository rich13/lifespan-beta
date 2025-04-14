<?php

// This script sets the database configuration directly in Laravel's configuration cache
// It bypasses the .env file and sets the configuration directly

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log the current directory and paths for debugging
echo "Current directory: " . __DIR__ . "\n";
echo "Looking for autoload.php at: " . __DIR__ . '/../../vendor/autoload.php' . "\n";
echo "Looking for app.php at: " . __DIR__ . '/../../bootstrap/app.php' . "\n";

// Check if the files exist
if (!file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    echo "ERROR: autoload.php not found at " . __DIR__ . '/../../vendor/autoload.php' . "\n";
    echo "Trying alternative path...\n";
    
    // Try alternative paths
    $possiblePaths = [
        __DIR__ . '/../vendor/autoload.php',
        __DIR__ . '/../../../vendor/autoload.php',
        '/var/www/vendor/autoload.php'
    ];
    
    $autoloadFound = false;
    foreach ($possiblePaths as $path) {
        echo "Checking: $path\n";
        if (file_exists($path)) {
            echo "Found autoload.php at: $path\n";
            require $path;
            $autoloadFound = true;
            break;
        }
    }
    
    if (!$autoloadFound) {
        echo "ERROR: Could not find autoload.php. Using direct configuration.\n";
        // Continue without Laravel
    }
} else {
    // Load the Laravel application
    require __DIR__ . '/../../vendor/autoload.php';
}

// Try to load the Laravel app if possible
$app = null;
if (file_exists(__DIR__ . '/../../bootstrap/app.php')) {
    $app = require_once __DIR__ . '/../../bootstrap/app.php';
    echo "Laravel app loaded successfully.\n";
} else {
    echo "WARNING: bootstrap/app.php not found. Continuing without Laravel app.\n";
}

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

// Try to write the configuration to a file
$configPaths = [
    __DIR__ . '/../../bootstrap/cache/config.php',
    __DIR__ . '/../bootstrap/cache/config.php',
    __DIR__ . '/../../../bootstrap/cache/config.php',
    '/var/www/bootstrap/cache/config.php'
];

$configWritten = false;
foreach ($configPaths as $configPath) {
    echo "Trying to write config to: $configPath\n";
    
    // Make sure the directory exists
    $configDir = dirname($configPath);
    if (!file_exists($configDir)) {
        echo "Creating directory: $configDir\n";
        mkdir($configDir, 0755, true);
    }
    
    if (file_put_contents($configPath, '<?php return ' . var_export($config, true) . ';')) {
        echo "Database configuration written successfully to: $configPath\n";
        $configWritten = true;
        break;
    } else {
        echo "Failed to write to: $configPath\n";
    }
}

if (!$configWritten) {
    echo "ERROR: Could not write configuration to any location.\n";
    exit(1);
}

// Also update the .env file as a fallback
$envFile = '/var/www/.env';
if (file_exists($envFile)) {
    echo "Updating .env file as fallback...\n";
    
    $envContent = file_get_contents($envFile);
    
    // Replace database configuration in .env
    $envContent = preg_replace('/DB_CONNECTION=.*/', 'DB_CONNECTION=pgsql', $envContent);
    $envContent = preg_replace('/DB_HOST=.*/', 'DB_HOST=' . $dbHost, $envContent);
    $envContent = preg_replace('/DB_PORT=.*/', 'DB_PORT=' . $dbPort, $envContent);
    $envContent = preg_replace('/DB_DATABASE=.*/', 'DB_DATABASE=' . $dbDatabase, $envContent);
    $envContent = preg_replace('/DB_USERNAME=.*/', 'DB_USERNAME=' . $dbUsername, $envContent);
    $envContent = preg_replace('/DB_PASSWORD=.*/', 'DB_PASSWORD=' . $dbPassword, $envContent);
    
    file_put_contents($envFile, $envContent);
    echo "Updated .env file with database configuration.\n";
}

echo "Database configuration set successfully.\n"; 