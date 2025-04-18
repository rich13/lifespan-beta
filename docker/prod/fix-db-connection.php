#!/usr/bin/env php
<?php

/**
 * Fix database connection issues with Railway's DATABASE_URL
 * 
 * This script properly parses DATABASE_URL in Railway's environment
 * and creates the necessary configuration for Laravel to use
 */

// This script provides improved DATABASE_URL parsing for the Laravel environment
// It parses the DATABASE_URL value directly, handling complex formats and query parameters

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Running improved DATABASE_URL parsing script\n";

// Get the DATABASE_URL environment variable
$databaseUrl = getenv('DATABASE_URL');

if (empty($databaseUrl)) {
    echo "DATABASE_URL is not set or empty. Checking for direct PostgreSQL variables.\n";
    
    // Check if individual PostgreSQL variables are set and use them
    $pgHost = getenv('PGHOST');
    $pgPort = getenv('PGPORT') ?: '5432';
    $pgUser = getenv('PGUSER');
    $pgPassword = getenv('PGPASSWORD');
    $pgDatabase = getenv('PGDATABASE');
    
    if (!empty($pgHost) && !empty($pgUser) && !empty($pgDatabase)) {
        echo "Using direct PostgreSQL variables:\n";
        echo "- Host: $pgHost\n";
        echo "- Port: $pgPort\n";
        echo "- Database: $pgDatabase\n";
        echo "- Username: $pgUser\n";
        echo "- Password: " . (!empty($pgPassword) ? "[SET]" : "[EMPTY]") . "\n";
        
        // Set DB_ variables directly for Laravel
        putenv("DB_CONNECTION=pgsql");
        putenv("DB_HOST=$pgHost");
        putenv("DB_PORT=$pgPort");
        putenv("DB_DATABASE=$pgDatabase");
        putenv("DB_USERNAME=$pgUser");
        if (!empty($pgPassword)) {
            putenv("DB_PASSWORD=$pgPassword");
        }
        
        // Update the .env file
        updateEnvFile([
            'DB_CONNECTION' => 'pgsql',
            'DB_HOST' => $pgHost,
            'DB_PORT' => $pgPort,
            'DB_DATABASE' => $pgDatabase,
            'DB_USERNAME' => $pgUser,
            'DB_PASSWORD' => $pgPassword,
        ]);
        
        echo "Configuration completed using direct PostgreSQL variables.\n";
        exit(0);
    } else {
        echo "Required PostgreSQL variables not set. Skipping parsing.\n";
        exit(0);
    }
}

echo "Parsing DATABASE_URL: " . preg_replace('/:[^:@]+@/', ':***@', $databaseUrl) . "\n";

// Fix malformed URLs like "dbname='forge'" that might be causing issues
if (strpos($databaseUrl, "dbname=") !== false) {
    echo "Found potentially malformed URL with dbname= format. Attempting to fix...\n";
    
    // Extract parts of the malformed connection string
    if (preg_match('/host=([^;]+)/', $databaseUrl, $hostMatches)) {
        $host = trim($hostMatches[1], "'\"");
    }
    
    if (preg_match('/port=([^;]+)/', $databaseUrl, $portMatches)) {
        $port = trim($portMatches[1], "'\"");
    } else {
        $port = '5432';
    }
    
    if (preg_match('/dbname=([^;]+)/', $databaseUrl, $dbMatches)) {
        $database = trim($dbMatches[1], "'\"");
    }
    
    if (preg_match('/user=([^;]+)/', $databaseUrl, $userMatches)) {
        $user = trim($userMatches[1], "'\"");
    }
    
    if (preg_match('/password=([^;]+)/', $databaseUrl, $passMatches)) {
        $pass = trim($passMatches[1], "'\"");
    } else {
        $pass = null;
    }
    
    // Check if we have the necessary components
    if (!empty($host) && !empty($user) && !empty($database)) {
        echo "Successfully parsed malformed URL to:\n";
        echo "- Host: $host\n";
        echo "- Port: $port\n";
        echo "- Database: $database\n";
        echo "- Username: $user\n";
        echo "- Password: " . (!empty($pass) ? "[SET]" : "[EMPTY]") . "\n";
        
        // Export to environment variables
        putenv("PGHOST=$host");
        putenv("PGPORT=$port");
        putenv("PGDATABASE=$database");
        putenv("PGUSER=$user");
        if (!empty($pass)) {
            putenv("PGPASSWORD=$pass");
        }
        
        // Set DB_ variables directly for Laravel
        putenv("DB_CONNECTION=pgsql");
        putenv("DB_HOST=$host");
        putenv("DB_PORT=$port");
        putenv("DB_DATABASE=$database");
        putenv("DB_USERNAME=$user");
        if (!empty($pass)) {
            putenv("DB_PASSWORD=$pass");
        }
        
        // Update the .env file
        updateEnvFile([
            'DB_CONNECTION' => 'pgsql',
            'DB_HOST' => $host,
            'DB_PORT' => $port,
            'DB_DATABASE' => $database,
            'DB_USERNAME' => $user,
            'DB_PASSWORD' => $pass,
        ]);
        
        echo "Configuration completed for malformed URL.\n";
        exit(0);
    } else {
        echo "Could not extract all necessary components from malformed URL. Continuing to standard parsing...\n";
    }
}

// Parse the DATABASE_URL
$parsed = parse_url($databaseUrl);

if ($parsed === false) {
    echo "Failed to parse DATABASE_URL: Invalid URL format\n";
    exit(1);
}

// Extract components with error checking
$host = $parsed['host'] ?? null;
$port = $parsed['port'] ?? '5432';
$user = $parsed['user'] ?? null;
$pass = $parsed['pass'] ?? null;
$path = $parsed['path'] ?? null;
$database = ltrim($path, '/');

// Check for missing required components
if (empty($host)) {
    echo "ERROR: Host is missing in DATABASE_URL\n";
    exit(1);
}

if (empty($user)) {
    echo "ERROR: Username is missing in DATABASE_URL\n";
    exit(1);
}

if (empty($database)) {
    echo "ERROR: Database name is missing in DATABASE_URL\n";
    exit(1);
}

// If there are query parameters, extract and process them
if (isset($parsed['query'])) {
    parse_str($parsed['query'], $query);
    
    // Handle sslmode
    if (isset($query['sslmode'])) {
        echo "SSLMode specified: " . $query['sslmode'] . "\n";
        // You could set this in your database configuration if needed
    }
    
    // Remove query from database name if it got included
    if (strpos($database, '?') !== false) {
        $database = substr($database, 0, strpos($database, '?'));
    }
}

echo "Extracted database connection parameters:\n";
echo "- Host: $host\n";
echo "- Port: $port\n";
echo "- Database: $database\n";
echo "- Username: $user\n";
echo "- Password: " . (!empty($pass) ? "[SET]" : "[EMPTY]") . "\n";

// Export to environment variables for the shell script to use
putenv("PGHOST=$host");
putenv("PGPORT=$port");
putenv("PGDATABASE=$database");
putenv("PGUSER=$user");
if (!empty($pass)) {
    putenv("PGPASSWORD=$pass");
}

// Also set DB_ variables directly for Laravel
putenv("DB_CONNECTION=pgsql");
putenv("DB_HOST=$host");
putenv("DB_PORT=$port");
putenv("DB_DATABASE=$database");
putenv("DB_USERNAME=$user");
if (!empty($pass)) {
    putenv("DB_PASSWORD=$pass");
}

// Update the .env file
updateEnvFile([
    'DB_CONNECTION' => 'pgsql',
    'DB_HOST' => $host,
    'DB_PORT' => $port,
    'DB_DATABASE' => $database,
    'DB_USERNAME' => $user,
    'DB_PASSWORD' => $pass,
]);

echo "Improved DATABASE_URL parsing completed successfully\n";
exit(0);

// Helper function to update the .env file
function updateEnvFile($vars) {
    $envFile = '/var/www/.env';
    
    if (file_exists($envFile) && is_writable($envFile)) {
        echo "Updating Laravel .env file with database configuration\n";
        
        $content = file_get_contents($envFile);
        
        foreach ($vars as $key => $value) {
            if ($value === null) continue;
            
            $value = str_replace('"', '\"', $value); // Escape double quotes
            
            if (preg_match("/^{$key}=/m", $content)) {
                // Replace existing variable
                $content = preg_replace("/^{$key}=.*/m", "{$key}=\"{$value}\"", $content);
            } else {
                // Add new variable
                $content .= PHP_EOL . "{$key}=\"{$value}\"";
            }
        }
        
        // Write back to .env file
        if (file_put_contents($envFile, $content)) {
            echo "Successfully updated .env file with database configuration\n";
        } else {
            echo "WARNING: Failed to update .env file\n";
        }
    } else {
        echo "WARNING: .env file not found or not writable at $envFile\n";
    }
} 