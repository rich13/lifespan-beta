#!/usr/bin/env php
<?php

/**
 * Unified Database Configuration Script for Laravel on Railway
 *
 * This script provides a robust solution for configuring database connections
 * in a Railway environment by handling various configuration sources with a clear
 * priority order. It directly creates a custom database configuration file that
 * Laravel will load.
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Function to log messages with timestamps
function log_message($message, $level = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    echo "[$timestamp] $level: $message" . PHP_EOL;
}

// Function to log errors
function log_error($message) {
    log_message($message, 'ERROR');
}

// Function to parse query parameters from DATABASE_URL
function parse_query_params($url) {
    $query = parse_url($url, PHP_URL_QUERY);
    $params = [];
    
    if ($query) {
        parse_str($query, $params);
    }
    
    return $params;
}

log_message("Starting unified database configuration...");

// Define the Laravel root path
$laravelRoot = '/var/www';
$bootstrapPath = "$laravelRoot/bootstrap/cache";
$configPath = "$bootstrapPath/railway_database.php";

// Ensure bootstrap/cache directory exists and is writable
if (!is_dir($bootstrapPath)) {
    log_message("Creating bootstrap/cache directory...");
    if (!mkdir($bootstrapPath, 0755, true)) {
        log_error("Failed to create bootstrap/cache directory!");
        exit(1);
    }
}

if (!is_writable($bootstrapPath)) {
    log_error("bootstrap/cache directory is not writable!");
    exit(1);
}

// Detect available configuration sources
$sources = [
    'pg_vars' => !empty(getenv('PGHOST')) && !empty(getenv('PGUSER')) && !empty(getenv('PGDATABASE')),
    'database_url' => !empty(getenv('DATABASE_URL')),
];

log_message("Configuration sources detected:");
log_message("- PG* environment variables: " . ($sources['pg_vars'] ? "YES" : "NO"));
log_message("- DATABASE_URL: " . ($sources['database_url'] ? "YES" : "NO"));

// If no configuration sources are available, exit with error
if (!$sources['pg_vars'] && !$sources['database_url']) {
    log_error("No database configuration sources detected!");
    log_error("Please provide either DATABASE_URL or PGHOST, PGPORT, PGDATABASE, PGUSER, and PGPASSWORD");
    exit(1);
}

// Priority 1: Use direct PostgreSQL environment variables if available
if ($sources['pg_vars']) {
    log_message("Using direct PostgreSQL environment variables (highest priority)");
    
    $config = [
        'driver' => 'pgsql',
        'host' => getenv('PGHOST'),
        'port' => getenv('PGPORT') ?: '5432',
        'database' => getenv('PGDATABASE'),
        'username' => getenv('PGUSER'),
        'password' => getenv('PGPASSWORD') ?: '',
        'charset' => 'utf8',
        'prefix' => '',
        'prefix_indexes' => true,
        'search_path' => 'public',
        'sslmode' => 'prefer',
    ];
}
// Priority 2: Parse DATABASE_URL if direct variables are not available
else if ($sources['database_url']) {
    log_message("Parsing DATABASE_URL (second priority)");
    
    $dbUrl = getenv('DATABASE_URL');
    log_message("DATABASE_URL format: " . preg_replace('/:[^:@]+@/', ':***@', $dbUrl)); // Hide password in logs
    
    $parsed = parse_url($dbUrl);
    if (!$parsed) {
        log_error("Failed to parse DATABASE_URL!");
        exit(1);
    }
    
    // Get additional parameters from query string
    $queryParams = parse_query_params($dbUrl);
    $sslmode = $queryParams['sslmode'] ?? 'prefer';
    
    // Extract database name correctly even with query parameters
    $dbName = ltrim($parsed['path'] ?? '', '/');
    if (strpos($dbName, '?') !== false) {
        $dbName = substr($dbName, 0, strpos($dbName, '?'));
    }
    
    $config = [
        'driver' => 'pgsql',
        'host' => $parsed['host'] ?? 'localhost',
        'port' => $parsed['port'] ?? '5432',
        'database' => $dbName ?: 'forge',
        'username' => $parsed['user'] ?? 'forge',
        'password' => $parsed['pass'] ?? '',
        'charset' => 'utf8',
        'prefix' => '',
        'prefix_indexes' => true,
        'search_path' => 'public',
        'sslmode' => $sslmode,
    ];
}

// Log the configuration (without password)
log_message("Database configuration:");
foreach ($config as $key => $value) {
    if ($key === 'password') {
        log_message("- $key: " . (!empty($value) ? "[SET]" : "[EMPTY]"));
    } else {
        log_message("- $key: $value");
    }
}

// Test database connection before applying configuration
log_message("Testing database connection...");
try {
    $dsn = "pgsql:host={$config['host']};port={$config['port']};dbname={$config['database']}";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 5, // 5 seconds timeout
    ];
    
    $pdo = new PDO($dsn, $config['username'], $config['password'], $options);
    
    // Execute a simple query to verify connection
    $stmt = $pdo->query('SELECT 1 AS connection_test');
    $result = $stmt->fetch();
    
    if (isset($result['connection_test']) && $result['connection_test'] === 1) {
        log_message("Database connection test successful!");
    } else {
        log_error("Database connection test returned unexpected result.");
        // Continue anyway, since connection was established
    }
} catch (PDOException $e) {
    log_error("Database connection test failed: " . $e->getMessage());
    
    // Alternative error message for common connection issues
    if (strpos($e->getMessage(), "could not translate host") !== false) {
        log_error("Host resolution failed. Please check the host name is correct.");
    }
    else if (strpos($e->getMessage(), "Connection refused") !== false) {
        log_error("Connection refused. Please check if the database server is running and accessible.");
    }
    else if (strpos($e->getMessage(), "password authentication failed") !== false) {
        log_error("Authentication failed. Please check your username and password.");
    }
    else if (strpos($e->getMessage(), "database") !== false && strpos($e->getMessage(), "does not exist") !== false) {
        log_error("Database does not exist. Please check the database name.");
    }
    
    // Don't exit, we'll still create the config file for Laravel to use
    log_message("Will proceed with configuration file creation despite connection failure.");
}

// Create Laravel database configuration file
log_message("Creating Laravel database configuration file at $configPath");

// Format the configuration as PHP code
$configContent = "<?php

// Generated by configure-database.php script
// Date: " . date('Y-m-d H:i:s') . "

return [
    'connections' => [
        'pgsql' => [
            'driver' => '{$config['driver']}',
            'url' => env('DATABASE_URL'),
            'host' => '{$config['host']}',
            'port' => '{$config['port']}',
            'database' => '{$config['database']}',
            'username' => '{$config['username']}',
            'password' => '" . addslashes($config['password']) . "',
            'charset' => '{$config['charset']}',
            'prefix' => '{$config['prefix']}',
            'prefix_indexes' => " . ($config['prefix_indexes'] ? 'true' : 'false') . ",
            'search_path' => '{$config['search_path']}',
            'sslmode' => '{$config['sslmode']}',
        ],
    ],
];";

// Write the configuration file
if (file_put_contents($configPath, $configContent)) {
    chmod($configPath, 0644); // Make readable by the web server
    log_message("Configuration file created successfully.");
} else {
    log_error("Failed to write configuration file!");
    exit(1);
}

// Update environment variables for Laravel to use
if (file_exists("$laravelRoot/.env")) {
    log_message("Updating .env file with database configuration references");
    
    $envContent = file_get_contents("$laravelRoot/.env");
    $updated = false;
    
    // Update DB connection variables to point to our config
    $envVars = [
        'DB_CONNECTION' => 'pgsql',
        'DB_HOST' => $config['host'],
        'DB_PORT' => $config['port'],
        'DB_DATABASE' => $config['database'],
        'DB_USERNAME' => $config['username'],
        'DB_PASSWORD' => $config['password'],
    ];
    
    foreach ($envVars as $key => $value) {
        $escapedValue = str_replace('"', '\"', $value);
        if (preg_match("/^{$key}=/m", $envContent)) {
            $envContent = preg_replace("/^{$key}=.*/m", "{$key}=\"{$escapedValue}\"", $envContent);
            $updated = true;
        } else {
            $envContent .= "\n{$key}=\"{$escapedValue}\"";
            $updated = true;
        }
    }
    
    if ($updated) {
        file_put_contents("$laravelRoot/.env", $envContent);
        log_message(".env file updated successfully.");
    }
}

// Clear Laravel configuration cache
log_message("Clearing Laravel configuration cache...");
if (file_exists("$laravelRoot/artisan")) {
    $output = [];
    $result = 0;
    exec("cd $laravelRoot && php artisan config:clear 2>&1", $output, $result);
    
    if ($result === 0) {
        log_message("Laravel configuration cache cleared successfully.");
    } else {
        log_error("Failed to clear Laravel configuration cache: " . implode("\n", $output));
        // Continue anyway
    }
}

log_message("Database configuration completed successfully.");
exit(0); 