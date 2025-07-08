<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Slack Notifications Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration for Slack notifications in the Lifespan
    | application. You can control which events trigger notifications and
    | customize the notification behavior.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Enable/Disable Notifications
    |--------------------------------------------------------------------------
    |
    | Set to false to disable all Slack notifications globally.
    |
    */
    'enabled' => env('SLACK_NOTIFICATIONS_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Event Notifications
    |--------------------------------------------------------------------------
    |
    | Configure which events should trigger Slack notifications.
    |
    */
    'events' => [
        'span_created' => env('SLACK_NOTIFY_SPAN_CREATED', true),
        'span_updated' => env('SLACK_NOTIFY_SPAN_UPDATED', true),
        'user_registered' => env('SLACK_NOTIFY_USER_REGISTERED', true),
        'ai_yaml_generated' => env('SLACK_NOTIFY_AI_YAML', true),
        'import_completed' => env('SLACK_NOTIFY_IMPORT', true),
        'backup_completed' => env('SLACK_NOTIFY_BACKUP', true),
        'system_events' => env('SLACK_NOTIFY_SYSTEM_EVENTS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification Levels
    |--------------------------------------------------------------------------
    |
    | Configure the minimum level for notifications to be sent.
    | Available levels: info, warning, error, success
    |
    */
    'minimum_level' => env('SLACK_NOTIFICATION_LEVEL', 'info'),

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Configure rate limiting for notifications to prevent spam.
    |
    */
    'rate_limiting' => [
        'enabled' => env('SLACK_RATE_LIMITING_ENABLED', true),
        'max_per_hour' => env('SLACK_MAX_NOTIFICATIONS_PER_HOUR', 100),
        'max_per_minute' => env('SLACK_MAX_NOTIFICATIONS_PER_MINUTE', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Span Type Filtering
    |--------------------------------------------------------------------------
    |
    | Configure which span types should trigger notifications.
    | Set to empty array to notify for all types.
    |
    */
    'span_types' => [
        'include' => env('SLACK_SPAN_TYPES_INCLUDE', []), // Empty = all types
        'exclude' => env('SLACK_SPAN_TYPES_EXCLUDE', ['connection']), // Exclude connection spans
    ],

    /*
    |--------------------------------------------------------------------------
    | User Filtering
    |--------------------------------------------------------------------------
    |
    | Configure which users should trigger notifications.
    | Set to empty array to notify for all users.
    |
    */
    'users' => [
        'include' => env('SLACK_USERS_INCLUDE', []), // Empty = all users
        'exclude' => env('SLACK_USERS_EXCLUDE', []), // Exclude specific users
    ],

    /*
    |--------------------------------------------------------------------------
    | Environment Filtering
    |--------------------------------------------------------------------------
    |
    | Configure which environments should send notifications.
    |
    */
    'environments' => [
        'production' => true,
        'staging' => false,
        'local' => false,
        'testing' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification Templates
    |--------------------------------------------------------------------------
    |
    | Customize the appearance of notifications in Slack.
    |
    */
    'templates' => [
        'username' => env('SLACK_USERNAME', 'Lifespan Bot'),
        'icon' => env('SLACK_ICON', ':calendar:'),
        'channel' => env('SLACK_CHANNEL', '#general'),
        'footer_text' => 'Lifespan Beta',
        'footer_icon' => 'https://beta.lifespan.dev/favicon.ico',
    ],

]; 