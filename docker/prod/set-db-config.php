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

// Check if the necessary variables are set
if (empty($host) || empty($database) || empty($username)) {
    echo "ERROR: Missing required database configuration variables.\n";
    echo "PGHOST: " . (empty($host) ? "NOT SET" : $host) . "\n";
    echo "PGPORT: " . (empty($port) ? "NOT SET (will use default 5432)" : $port) . "\n";
    echo "PGDATABASE: " . (empty($database) ? "NOT SET" : $database) . "\n";
    echo "PGUSER: " . (empty($username) ? "NOT SET" : $username) . "\n";
    echo "PGPASSWORD: " . (empty($password) ? "NOT SET" : "[SET]") . "\n";
    exit(1);
}

// Use default port if not set
if (empty($port)) {
    $port = '5432';
}

echo "Database configuration to apply:\n";
echo "- DB_CONNECTION: pgsql\n";
echo "- DB_HOST: $host\n";
echo "- DB_PORT: $port\n";
echo "- DB_DATABASE: $database\n";
echo "- DB_USERNAME: $username\n";
echo "- DB_PASSWORD: " . (empty($password) ? "[EMPTY]" : "[SET]") . "\n";

// Create a database.php file in bootstrap/cache that will override the Laravel config
$configDir = __DIR__ . '/../../bootstrap/cache';
$configFile = $configDir . '/database.php';

// Ensure the directory exists
if (!is_dir($configDir)) {
    echo "Creating bootstrap/cache directory\n";
    if (!mkdir($configDir, 0755, true)) {
        echo "ERROR: Failed to create bootstrap/cache directory\n";
        exit(1);
    }
}

// Format the config PHP code
$configContent = "<?php
// Auto-generated database configuration by set-db-config.php script
// Generated at: " . date('Y-m-d H:i:s') . "

return [
    'default' => 'pgsql',
    'connections' => [
        'pgsql' => [
            'driver' => 'pgsql',
            'url' => env('DATABASE_URL'),
            'host' => '$host',
            'port' => '$port',
            'database' => '$database',
            'username' => '$username',
            'password' => '" . addslashes($password) . "',
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => 'prefer',
        ],
    ],
];
";

// Write the config file
if (file_put_contents($configFile, $configContent)) {
    echo "Successfully wrote database configuration to $configFile\n";
    chmod($configFile, 0644); // Make sure it's readable
} else {
    echo "ERROR: Failed to write database configuration file\n";
    exit(1);
}

// Test database connection with configured values
echo "Testing database connection with configured values...\n";

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 5
    ];
    
    $pdo = new PDO($dsn, $username, $password, $options);
    
    // Execute a simple query to verify connection
    $stmt = $pdo->query('SELECT 1 AS connection_test');
    $result = $stmt->fetch();
    
    if (isset($result['connection_test']) && $result['connection_test'] === 1) {
        echo "Database connection test successful!\n";
    } else {
        echo "WARNING: Database connection established but test query returned unexpected result.\n";
    }
} catch (PDOException $e) {
    echo "ERROR: Database connection failed: " . $e->getMessage() . "\n";
    // We'll continue anyway - the script has set the config file
}

echo "Database configuration completed.\n";
exit(0); 