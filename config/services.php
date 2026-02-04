<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
    ],

    'slack' => [
        // Incoming Webhook (current setup)
        'webhook_url' => env('SLACK_WEBHOOK_URL'),
        'channel' => env('SLACK_CHANNEL', '#general'),
        'username' => env('SLACK_USERNAME', 'Lifespan Bot'),
        'icon' => env('SLACK_ICON', ':calendar:'),
        
        // OAuth App credentials (for full Slack app integration)
        'client_id' => env('SLACK_CLIENT_ID'),
        'client_secret' => env('SLACK_CLIENT_SECRET'),
        'signing_secret' => env('SLACK_SIGNING_SECRET'),
        'bot_token' => env('SLACK_BOT_TOKEN'),
        'user_token' => env('SLACK_USER_TOKEN'),
    ],

    'flickr' => [
        'api_key' => env('FLICKR_API_KEY'),
        'api_secret' => env('FLICKR_API_SECRET'),
        'client_id' => env('FLICKR_CLIENT_ID', '3c41cc8de5c3d33ea2433a17ed61bebf'),
        'client_secret' => env('FLICKR_CLIENT_SECRET', 'a9773d44336e6279'),
        'callback_url' => env('FLICKR_CALLBACK_URL', 'http://localhost:8000/settings/import/flickr/callback'),
    ],

    'guardian' => [
        'api_key' => env('GUARDIAN_API_KEY', '09621a6e-033b-43be-87b2-4b5f0b27055e'),
    ],

    'osm_import_data_path' => env('OSM_IMPORT_DATA_PATH', 'osm/london-major-locations.json'),

    // Use local Nominatim when running in Docker (docker-compose nominatim service).
    // Set NOMINATIM_BASE_URL in .env to override (e.g. from host use http://localhost:7001).
    'nominatim_base_url' => env('NOMINATIM_BASE_URL')
        ?: (env('DOCKER_CONTAINER') ? 'http://nominatim:8080' : 'https://nominatim.openstreetmap.org'),

    // Simplify polygon output so large boundaries (e.g. Greater London) fit within MAX_BOUNDARY_POINTS.
    // Tolerance in degrees; 0.0005 yields ~683 points for London (storable in both local and prod).
    'nominatim_polygon_threshold' => (float) env('NOMINATIM_POLYGON_THRESHOLD', 0.0005),

    'mailersend' => [
        'api_key' => env('MAILERSEND_API_KEY'),
    ],

];
