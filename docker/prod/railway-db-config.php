#!/usr/bin/env php
<?php

/**
 * Railway-specific database configuration
 * 
 * This script directly creates database configuration for Railway environment
 * overriding Laravel's default database connection settings.
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Setting up Railway-specific database configuration\n";

// Get DATABASE_URL from environment
$databaseUrl = getenv('DATABASE_URL');

if (!$databaseUrl) {
    echo "ERROR: DATABASE_URL environment variable is not set\n";
    exit(1);
}

// Special handling for Railway's DATABASE_URL format which might have connection params
$rawUrl = $databaseUrl;

// Create output file path
$outputFile = '/var/www/bootstrap/cache/railway_db.php';
$dbConfigDir = dirname($outputFile);

// We'll print the database URL with the password masked for debugging
$maskedUrl = preg_replace('/(postgres:\/\/[^:]+:)([^@]+)(@.*)/', '$1*****$3', $databaseUrl);
echo "Database URL: $maskedUrl\n";

// Special handling for Railway format - they might include extra parameters
$params = [];
if (preg_match('/\?(.+)$/', $databaseUrl, $matches)) {
    parse_str($matches[1], $params);
    // Remove the query string part for standard parsing
    $databaseUrl = preg_replace('/\?.+$/', '', $databaseUrl);
}

// Parse the main URL components
$parsedUrl = parse_url($databaseUrl);

if (!$parsedUrl) {
    echo "ERROR: Failed to parse DATABASE_URL\n";
    exit(1);
}

// Extract components
$host = $parsedUrl['host'] ?? null;
$port = $parsedUrl['port'] ?? '5432';
$username = $parsedUrl['user'] ?? null;
$password = $parsedUrl['pass'] ?? null;
$path = $parsedUrl['path'] ?? null;
$database = ltrim($path ?? '', '/');

// Validate required components
if (!$host || !$database) {
    echo "ERROR: DATABASE_URL is missing required components\n";
    echo "Parsed URL: " . print_r($parsedUrl, true) . "\n";
    exit(1);
}

// Create config array
$config = [
    'driver' => 'pgsql',
    'host' => $host,
    'port' => $port,
    'database' => $database,
    'username' => $username,
    'password' => $password,
    'charset' => 'utf8',
    'prefix' => '',
    'prefix_indexes' => true,
    'schema' => 'public',
    'sslmode' => 'prefer',
];

// Add any parameters from the query string
if (!empty($params)) {
    foreach ($params as $key => $value) {
        $config[$key] = $value;
    }
}

// Create directory if it doesn't exist
if (!is_dir($dbConfigDir)) {
    mkdir($dbConfigDir, 0755, true);
}

// Generate PHP file content
$fileContent = "<?php\n\n";
$fileContent .= "// Generated by railway-db-config.php on " . date('Y-m-d H:i:s') . "\n\n";
$fileContent .= "return " . var_export([
    'connections' => [
        'pgsql' => $config
    ]
], true) . ";\n";

// Write the file
if (file_put_contents($outputFile, $fileContent)) {
    echo "Successfully wrote Railway database configuration to $outputFile\n";
    
    // Test the database connection with these credentials
    try {
        echo "Testing database connection...\n";
        $dsn = "pgsql:host={$host};port={$port};dbname={$database}";
        $pdo = new PDO($dsn, $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        echo "Database connection successful!\n";
    } catch (PDOException $e) {
        echo "ERROR: Database connection test failed: " . $e->getMessage() . "\n";
        // We'll continue anyway since the container might need to start for other operations
    }
    
    // Success!
    exit(0);
} else {
    echo "ERROR: Failed to write configuration to $outputFile\n";
    exit(1);
} 