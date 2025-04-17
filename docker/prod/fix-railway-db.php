#!/usr/bin/env php
<?php

/**
 * Fix database configuration for Railway by directly adding configuration to .env file
 */

echo "Fixing database configuration for Railway...\n";

// Railway provides database configuration through multiple environment variables
$dbUrl = getenv('DATABASE_URL');
$pgHost = getenv('PGHOST');
$pgPort = getenv('PGPORT');
$pgDatabase = getenv('PGDATABASE');
$pgUser = getenv('PGUSER');
$pgPassword = getenv('PGPASSWORD');

// Track if we're using direct PG variables or DATABASE_URL
$usingPgVars = !empty($pgHost) && !empty($pgUser) && !empty($pgDatabase);
$usingDbUrl = !empty($dbUrl);

echo "Database environment detection:\n";
echo "- Using direct PG variables: " . ($usingPgVars ? "YES" : "NO") . "\n";
echo "- Using DATABASE_URL: " . ($usingDbUrl ? "YES" : "NO") . "\n";

// If we don't have either, we can't proceed
if (!$usingPgVars && !$usingDbUrl) {
    echo "ERROR: No database configuration found in environment!\n";
    exit(1);
}

// Create a configuration array
$config = [];
if ($usingPgVars) {
    // Set values directly from PG* variables
    $config = [
        'DB_CONNECTION' => 'pgsql',
        'DB_HOST' => $pgHost,
        'DB_PORT' => $pgPort,
        'DB_DATABASE' => $pgDatabase,
        'DB_USERNAME' => $pgUser,
        'DB_PASSWORD' => $pgPassword,
    ];
    echo "Using direct Railway PostgreSQL environment variables.\n";
} elseif ($usingDbUrl) {
    // Parse DATABASE_URL
    echo "Parsing DATABASE_URL...\n";
    $parsed = parse_url($dbUrl);
    if ($parsed) {
        $config = [
            'DB_CONNECTION' => 'pgsql',
            'DB_HOST' => $parsed['host'] ?? 'localhost',
            'DB_PORT' => $parsed['port'] ?? '5432',
            'DB_DATABASE' => ltrim($parsed['path'] ?? '', '/'),
            'DB_USERNAME' => $parsed['user'] ?? 'forge',
            'DB_PASSWORD' => $parsed['pass'] ?? '',
        ];
        echo "Successfully parsed DATABASE_URL.\n";
    } else {
        echo "ERROR: Could not parse DATABASE_URL!\n";
        exit(1);
    }
}

// Print config (without password)
echo "Database configuration:\n";
echo "- DB_CONNECTION: {$config['DB_CONNECTION']}\n";
echo "- DB_HOST: {$config['DB_HOST']}\n";
echo "- DB_PORT: {$config['DB_PORT']}\n";
echo "- DB_DATABASE: {$config['DB_DATABASE']}\n";
echo "- DB_USERNAME: {$config['DB_USERNAME']}\n";
echo "- DB_PASSWORD: " . (!empty($config['DB_PASSWORD']) ? "[set]" : "[empty]") . "\n";

// Update the .env file
echo "Updating .env file with database configuration...\n";
$envPath = '/var/www/.env';

if (!file_exists($envPath)) {
    echo "ERROR: .env file not found at $envPath!\n";
    exit(1);
}

if (!is_writable($envPath)) {
    echo "ERROR: .env file is not writable!\n";
    exit(1);
}

// Read the .env file
$envContent = file_get_contents($envPath);

// Update each configuration value
foreach ($config as $key => $value) {
    $escapedValue = str_replace('"', '\"', $value);
    
    // Check if the key exists in the file
    if (preg_match("/^{$key}=/m", $envContent)) {
        // Replace existing value
        $envContent = preg_replace("/^{$key}=.*/m", "{$key}=\"{$escapedValue}\"", $envContent);
    } else {
        // Add new key=value pair
        $envContent .= "\n{$key}=\"{$escapedValue}\"";
    }
}

// Write the updated content back to the file
if (file_put_contents($envPath, $envContent)) {
    echo "Successfully updated .env file with database configuration.\n";
} else {
    echo "ERROR: Failed to write to .env file!\n";
    exit(1);
}

// Clear Laravel's configuration cache
echo "Clearing configuration cache...\n";
system('cd /var/www && php artisan config:clear');

// Test database connection
echo "Testing database connection...\n";
try {
    $dsn = "pgsql:host={$config['DB_HOST']};port={$config['DB_PORT']};dbname={$config['DB_DATABASE']}";
    $pdo = new PDO($dsn, $config['DB_USERNAME'], $config['DB_PASSWORD']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Test a simple query
    $stmt = $pdo->query('SELECT 1');
    $result = $stmt->fetchColumn();
    
    if ($result === 1) {
        echo "Database connection successful!\n";
    } else {
        echo "WARNING: Unexpected query result. Database may not be fully functional.\n";
    }
} catch (PDOException $e) {
    echo "ERROR: Database connection failed: " . $e->getMessage() . "\n";
    // Exit with an error code, but we'll continue with deployment
}

echo "Database configuration completed.\n";
exit(0); 