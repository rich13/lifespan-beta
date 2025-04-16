#!/usr/bin/env php
<?php

/**
 * Set proper session and CSRF configuration for Railway environment
 * 
 * This script updates session and cookie settings in a Docker/Railway environment
 * to ensure CSRF tokens and authentication work correctly.
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Configuring session handling for Railway environment\n";

// Get the APP_URL to extract domain information
$appUrl = getenv('APP_URL');

if (empty($appUrl)) {
    echo "WARNING: APP_URL environment variable is not set\n";
    echo "Using default session configuration\n";
    exit(0);
}

echo "Using APP_URL: $appUrl\n";

// Parse the URL to extract domain
$parsedUrl = parse_url($appUrl);
$domain = $parsedUrl['host'] ?? null;

if (!$domain) {
    echo "WARNING: Could not extract domain from APP_URL\n";
    echo "Using default session configuration\n";
    exit(0);
}

echo "Extracted domain: $domain\n";

// Create session configuration file
$configDir = __DIR__ . '/../../bootstrap/cache';
$configFile = $configDir . '/session.php';

// Ensure the directory exists
if (!is_dir($configDir)) {
    echo "Creating bootstrap/cache directory\n";
    if (!mkdir($configDir, 0755, true)) {
        echo "ERROR: Failed to create bootstrap/cache directory\n";
        exit(1);
    }
}

// Check if host is an IP address
$isIpAddress = filter_var($domain, FILTER_VALIDATE_IP) !== false;

// If it's an IP, don't set domain (as this causes issues)
$domainConfig = $isIpAddress ? 'null' : "'$domain'";

// Create session configuration
$configContent = "<?php
// Auto-generated session configuration for Railway environment
// Generated at: " . date('Y-m-d H:i:s') . "

return [
    'driver' => 'file',
    'lifetime' => 120,
    'expire_on_close' => false,
    'encrypt' => false,
    'files' => storage_path('framework/sessions'),
    'connection' => null,
    'table' => 'sessions',
    'store' => null,
    'lottery' => [2, 100],
    'cookie' => env(
        'SESSION_COOKIE',
        str_slug(env('APP_NAME', 'laravel'), '_').'_session'
    ),
    'path' => '/',
    'domain' => $domainConfig,
    'secure' => true,
    'http_only' => true,
    'same_site' => 'lax',
];
";

// Write the config file
if (file_put_contents($configFile, $configContent)) {
    echo "Successfully wrote session configuration to $configFile\n";
    chmod($configFile, 0644); // Make sure it's readable
} else {
    echo "ERROR: Failed to write session configuration file\n";
    exit(1);
}

// Also update environment variables
putenv("SESSION_DOMAIN=$domain");
putenv("SESSION_SECURE_COOKIE=true");
putenv("SESSION_SAME_SITE=lax");

// Update the .env file
$envFile = '/var/www/.env';
if (file_exists($envFile) && is_writable($envFile)) {
    echo "Updating session configuration in .env file\n";
    
    $content = file_get_contents($envFile);
    
    // Update or add session variables
    $vars = [
        'SESSION_DRIVER' => 'file',
        'SESSION_DOMAIN' => $domain,
        'SESSION_SECURE_COOKIE' => 'true',
        'SESSION_SAME_SITE' => 'lax',
    ];
    
    foreach ($vars as $key => $value) {
        if (preg_match("/^{$key}=/m", $content)) {
            // Replace existing variable
            $content = preg_replace("/^{$key}=.*/m", "{$key}={$value}", $content);
        } else {
            // Add new variable
            $content .= PHP_EOL . "{$key}={$value}";
        }
    }
    
    // Write back to .env file
    if (file_put_contents($envFile, $content)) {
        echo "Successfully updated .env file with session configuration\n";
    } else {
        echo "WARNING: Failed to update .env file\n";
    }
} else {
    echo "WARNING: .env file not found or not writable at $envFile\n";
}

echo "Session configuration completed\n";
exit(0); 