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

echo "Setting session and CSRF configuration for Railway environment\n";

// Get the app URL from environment
$appUrl = getenv('APP_URL');
$appEnv = getenv('APP_ENV') ?: 'production';

if (!$appUrl) {
    echo "WARNING: APP_URL environment variable is not set\n";
    echo "Using default hostname from HTTP_HOST\n";
    $appUrl = 'https://lifespan-beta-production.up.railway.app';
}

// Parse the domain from the URL
$parsedUrl = parse_url($appUrl);
$domain = $parsedUrl['host'] ?? null;

if (!$domain) {
    echo "WARNING: Could not parse domain from APP_URL\n";
    echo "Using default domain\n";
    $domain = 'lifespan-beta-production.up.railway.app';
}

echo "Using domain: $domain for session cookies\n";

// Update environment variables
putenv("SESSION_DOMAIN=$domain");
putenv("SESSION_SECURE_COOKIE=true");
putenv("SESSION_DRIVER=file");
putenv("SESSION_LIFETIME=525600"); // 1 year

// Update the .env file if it exists
$envFile = '/var/www/.env';
if (file_exists($envFile) && is_writable($envFile)) {
    echo "Updating .env file with session configuration\n";
    
    $envContent = file_get_contents($envFile);
    
    // Update session settings
    $envContent = preg_replace('/SESSION_DOMAIN=.*/', "SESSION_DOMAIN=$domain", $envContent);
    
    // If the SESSION_DOMAIN line doesn't exist, add it
    if (!preg_match('/SESSION_DOMAIN=/', $envContent)) {
        $envContent .= "\nSESSION_DOMAIN=$domain";
    }
    
    // Set secure cookies in production
    $envContent = preg_replace('/SESSION_SECURE_COOKIE=.*/', "SESSION_SECURE_COOKIE=true", $envContent);
    
    // If the SESSION_SECURE_COOKIE line doesn't exist, add it
    if (!preg_match('/SESSION_SECURE_COOKIE=/', $envContent)) {
        $envContent .= "\nSESSION_SECURE_COOKIE=true";
    }
    
    file_put_contents($envFile, $envContent);
    echo "Updated .env file with session configuration\n";
} else {
    echo "WARNING: Could not update .env file (not found or not writable)\n";
}

// Create custom config files to override Laravel defaults
$sessionConfigDir = '/var/www/config';

if (is_writable($sessionConfigDir)) {
    // Create a custom session configuration
    $sessionConfig = <<<EOT
<?php

// Railway/Docker specific session configuration

return [
    'driver' => env('SESSION_DRIVER', 'file'),
    'lifetime' => env('SESSION_LIFETIME', 525600),
    'expire_on_close' => false,
    'encrypt' => false,
    'files' => storage_path('framework/sessions'),
    'connection' => env('SESSION_CONNECTION'),
    'table' => 'sessions',
    'store' => env('SESSION_STORE'),
    'lottery' => [2, 100],
    'cookie' => env('SESSION_COOKIE', 'lifespan_session'),
    'path' => '/',
    'domain' => env('SESSION_DOMAIN', '$domain'),
    'secure' => env('SESSION_SECURE_COOKIE', true),
    'http_only' => true,
    'same_site' => 'lax',
    'partitioned' => false,
];
EOT;
    
    file_put_contents("$sessionConfigDir/session.railway.php", $sessionConfig);
    echo "Created custom session configuration\n";
    
    // Create a bootstrap file to load our custom config
    $bootstrapDir = '/var/www/bootstrap';
    if (is_dir($bootstrapDir) && is_writable($bootstrapDir)) {
        $bootstrapCode = <<<EOT
<?php

// Railway environment configuration loader
if (getenv('APP_ENV') === 'production' && getenv('DOCKER_CONTAINER') === 'true') {
    // Load custom Railway configurations
    if (file_exists(__DIR__.'/../config/session.railway.php')) {
        config(['session' => require __DIR__.'/../config/session.railway.php']);
    }
}
EOT;
        
        file_put_contents("$bootstrapDir/railway.php", $bootstrapCode);
        echo "Created bootstrap file for Railway environment\n";
        
        // Add this file to the autoload in composer.json if needed
    }
} else {
    echo "WARNING: Could not create custom config files (directory not writable)\n";
}

// Create a command to clear and rebuild the cache
echo "Clearing configuration cache\n";
$command = "cd /var/www && php artisan config:clear";
passthru($command, $exitCode);

if ($exitCode !== 0) {
    echo "WARNING: Failed to clear configuration cache\n";
}

echo "Session and CSRF configuration completed\n";
exit(0); 