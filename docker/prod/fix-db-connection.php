#!/usr/bin/env php
<?php

/**
 * Fix database connection issues with Railway's DATABASE_URL
 * 
 * This script properly parses DATABASE_URL in Railway's environment
 * and creates the necessary configuration for Laravel to use
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Fixing database connection in Railway environment\n";

// Get DATABASE_URL from environment
$databaseUrl = getenv('DATABASE_URL');

if (!$databaseUrl) {
    echo "ERROR: DATABASE_URL environment variable is not set\n";
    exit(1);
}

echo "Parsing DATABASE_URL: " . preg_replace('/:[^:@]+@/', ':***@', $databaseUrl) . "\n";

// Parse the DATABASE_URL
$parsedUrl = parse_url($databaseUrl);

if ($parsedUrl === false) {
    echo "ERROR: Failed to parse DATABASE_URL\n";
    exit(1);
}

// Extract components
$host = $parsedUrl['host'] ?? null;
$port = $parsedUrl['port'] ?? '5432';
$user = $parsedUrl['user'] ?? null;
$pass = $parsedUrl['pass'] ?? null;
$path = $parsedUrl['path'] ?? null;

// Remove leading slash from path to get database name
$database = $path ? ltrim($path, '/') : null;

if (!$host || !$user || !$database) {
    echo "ERROR: DATABASE_URL is missing required components\n";
    echo "Parsed components: host=$host, port=$port, user=$user, database=$database\n";
    exit(1);
}

echo "Parsed database components: host=$host, port=$port, user=$user, database=$database\n";

// Set environment variables for the database connection
putenv("DB_CONNECTION=pgsql");
putenv("DB_HOST=$host");
putenv("DB_PORT=$port");
putenv("DB_DATABASE=$database");
putenv("DB_USERNAME=$user");
putenv("DB_PASSWORD=$pass");

// Also set PGHOST and other Postgres-specific variables
putenv("PGHOST=$host");
putenv("PGPORT=$port");
putenv("PGDATABASE=$database");
putenv("PGUSER=$user");
putenv("PGPASSWORD=$pass");

// Update the .env file if it exists
$envFile = '/var/www/.env';
if (file_exists($envFile) && is_writable($envFile)) {
    echo "Updating .env file with database configuration\n";
    
    $envContent = file_get_contents($envFile);
    
    // Update database connection settings
    $envContent = preg_replace('/DB_CONNECTION=.*/', "DB_CONNECTION=pgsql", $envContent);
    $envContent = preg_replace('/DB_HOST=.*/', "DB_HOST=$host", $envContent);
    $envContent = preg_replace('/DB_PORT=.*/', "DB_PORT=$port", $envContent);
    $envContent = preg_replace('/DB_DATABASE=.*/', "DB_DATABASE=$database", $envContent);
    $envContent = preg_replace('/DB_USERNAME=.*/', "DB_USERNAME=$user", $envContent);
    $envContent = preg_replace('/DB_PASSWORD=.*/', "DB_PASSWORD=$pass", $envContent);
    
    file_put_contents($envFile, $envContent);
    echo "Updated .env file\n";
} else {
    echo "WARNING: Could not update .env file (not found or not writable)\n";
}

// Test the database connection to verify settings
echo "Testing database connection with new settings...\n";

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database";
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Simple query to test connection
    $stmt = $pdo->query("SELECT 1");
    $result = $stmt->fetchColumn();
    
    if ($result == 1) {
        echo "SUCCESS: Database connection established successfully\n";
    } else {
        echo "WARNING: Connected to database but test query returned unexpected result\n";
    }
} catch (PDOException $e) {
    echo "ERROR: Database connection test failed: " . $e->getMessage() . "\n";
    // Continue execution - we've at least set the environment variables
}

echo "Database configuration completed\n";
exit(0); 