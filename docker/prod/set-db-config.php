<?php

// This script sets the database configuration directly in the database config file
// It bypasses the .env file and config cache

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log the current directory and paths for debugging
echo "Current directory: " . __DIR__ . "\n";

// Get and validate the environment variables
$dbHost = getenv('PGHOST');
$dbPort = getenv('PGPORT');
$dbDatabase = getenv('PGDATABASE');
$dbUsername = getenv('PGUSER');
$dbPassword = getenv('PGPASSWORD');

// Validate required variables
if (!$dbHost || !$dbPort || !$dbDatabase || !$dbUsername || !$dbPassword) {
    echo "ERROR: Missing required database environment variables\n";
    exit(1);
}

// Clean and escape the values
$dbHost = addslashes($dbHost);
$dbPort = (int)$dbPort ?: 5432;
$dbDatabase = addslashes($dbDatabase);
$dbUsername = addslashes($dbUsername);
$dbPassword = addslashes($dbPassword);

// Set Laravel's DB_ environment variables
putenv("DB_CONNECTION=pgsql");
putenv("DB_HOST=$dbHost");
putenv("DB_PORT=$dbPort");
putenv("DB_DATABASE=$dbDatabase");
putenv("DB_USERNAME=$dbUsername");
putenv("DB_PASSWORD=$dbPassword");

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
            'options' => [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ],
        ],
    ],
    'migrations' => 'migrations',
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
    $replacements = [
        'DB_CONNECTION' => 'pgsql',
        'DB_HOST' => $dbHost,
        'DB_PORT' => $dbPort,
        'DB_DATABASE' => $dbDatabase,
        'DB_USERNAME' => $dbUsername,
        'DB_PASSWORD' => $dbPassword
    ];
    
    foreach ($replacements as $key => $value) {
        $pattern = "/$key=.*/";
        $replacement = "$key=$value";
        $envContent = preg_replace($pattern, $replacement, $envContent);
        
        // If the variable doesn't exist, add it
        if (strpos($envContent, "$key=") === false) {
            $envContent .= "\n$key=$value";
        }
    }
    
    if (file_put_contents($envFile, $envContent)) {
        echo "Updated .env file with database configuration.\n";
    } else {
        echo "WARNING: Failed to update .env file.\n";
    }
}

// Test the database connection
try {
    $dsn = "pgsql:host=$dbHost;port=$dbPort;dbname=$dbDatabase;user=$dbUsername;password=$dbPassword";
    $pdo = new PDO($dsn);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Successfully connected to the database.\n";
} catch (PDOException $e) {
    echo "ERROR: Failed to connect to the database: " . $e->getMessage() . "\n";
    exit(1);
}

echo "Database configuration completed successfully.\n"; 