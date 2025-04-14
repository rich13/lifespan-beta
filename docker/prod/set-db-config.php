<?php

// This script sets the database configuration directly in the database config file
// It bypasses the .env file and config cache

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log the current directory and paths for debugging
echo "Current directory: " . __DIR__ . "\n";

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

// Create the database configuration array
$config = <<<PHP
<?php

return [
    'default' => 'pgsql',
    'connections' => [
        'pgsql' => [
            'driver' => 'pgsql',
            'url' => null,
            'host' => '$dbHost',
            'port' => $dbPort,
            'database' => '$dbDatabase',
            'username' => '$dbUsername',
            'password' => '$dbPassword',
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => 'prefer',
        ],
    ],
];
PHP;

// Try to write the configuration to the database config file
$configPaths = [
    '/var/www/config/database.php',
    __DIR__ . '/../../config/database.php',
    __DIR__ . '/../config/database.php'
];

$configWritten = false;
foreach ($configPaths as $path) {
    echo "Trying to write config to: $path\n";
    
    // Make sure the directory exists
    $configDir = dirname($path);
    if (!file_exists($configDir)) {
        echo "Creating directory: $configDir\n";
        mkdir($configDir, 0755, true);
    }
    
    if (file_put_contents($path, $config)) {
        echo "Database configuration written successfully to: $path\n";
        $configWritten = true;
        break;
    } else {
        echo "Failed to write to: $path\n";
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