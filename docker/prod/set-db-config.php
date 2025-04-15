<?php

// This script sets the database configuration directly in the database config file
// It bypasses the .env file and config cache

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log current directory and function
echo "Setting database configuration from PHP script\n";
echo "Current directory: " . getcwd() . "\n";

// Get environment variables
$host = getenv('PGHOST');
$port = getenv('PGPORT');
$database = getenv('PGDATABASE');
$username = getenv('PGUSER');
$password = getenv('PGPASSWORD');

// Verify all variables are set
if (!$host || !$port || !$database || !$username || !$password) {
    echo "ERROR: Missing required database environment variables\n";
    exit(1);
}

// Log database connection information (without password)
echo "Database configuration: host=$host, port=$port, database=$database, username=$username\n";

// Create database configuration
$config = <<<EOT
<?php

return [
    'default' => env('DB_CONNECTION', 'pgsql'),
    'connections' => [
        'pgsql' => [
            'driver' => 'pgsql',
            'url' => env('DATABASE_URL'),
            'host' => '$host',
            'port' => '$port',
            'database' => '$database',
            'username' => '$username', 
            'password' => '$password',
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => 'prefer',
        ],
    ],
    'migrations' => 'migrations',
];
EOT;

// Possible config file paths
$paths = [
    '/var/www/config/database.php',
    './config/database.php',
    __DIR__ . '/../../config/database.php'
];

$success = false;

// Try to write to each path
foreach ($paths as $path) {
    if (is_writable(dirname($path)) || is_writable($path)) {
        if (file_put_contents($path, $config)) {
            echo "Successfully wrote database configuration to $path\n";
            $success = true;
            break;
        } else {
            echo "Failed to write to $path\n";
        }
    } else {
        echo "Path is not writable: $path\n";
    }
}

// Also update the .env file as a fallback
if ($success) {
    echo "Updating .env file as a fallback\n";
    
    $envPaths = [
        '/var/www/.env',
        './.env',
        __DIR__ . '/../../.env'
    ];
    
    foreach ($envPaths as $envPath) {
        if (file_exists($envPath) && is_writable($envPath)) {
            $envContent = file_get_contents($envPath);
            
            $envContent = preg_replace('/DB_CONNECTION=.*/', 'DB_CONNECTION=pgsql', $envContent);
            $envContent = preg_replace('/DB_HOST=.*/', "DB_HOST=$host", $envContent);
            $envContent = preg_replace('/DB_PORT=.*/', "DB_PORT=$port", $envContent);
            $envContent = preg_replace('/DB_DATABASE=.*/', "DB_DATABASE=$database", $envContent);
            $envContent = preg_replace('/DB_USERNAME=.*/', "DB_USERNAME=$username", $envContent);
            $envContent = preg_replace('/DB_PASSWORD=.*/', "DB_PASSWORD=$password", $envContent);
            
            file_put_contents($envPath, $envContent);
            echo "Updated $envPath\n";
            break;
        }
    }
} else {
    echo "ERROR: Could not write database configuration to any path\n";
    exit(1);
}

echo "Database configuration complete\n"; 