# Slack Integration for Lifespan

This document describes the Slack integration feature for the Lifespan application, which provides real-time notifications for various system events.

## Overview

The Slack integration allows Lifespan to send notifications to a Slack channel for important events such as:

- Span creation and updates
- User registrations
- AI YAML generation
- Import operations
- System events
- Database backups

## Setup Options

### Option 1: Incoming Webhooks (Current Setup - Recommended)

This is the simplest approach and what's currently implemented. It only requires a webhook URL.

#### Setup Steps:
1. Go to your Slack workspace
2. Navigate to **Apps** → **Custom Integrations** → **Incoming Webhooks**
3. Click **Add Configuration**
4. Choose the channel where notifications should appear
5. Copy the webhook URL

#### Environment Variables:
```env
# Required
SLACK_WEBHOOK_URL=https://hooks.slack.com/services/YOUR/WEBHOOK/URL

# Optional - customize appearance
SLACK_CHANNEL=#lifespan-notifications
SLACK_USERNAME=Lifespan Bot
SLACK_ICON=:calendar:
```

### Option 2: Full Slack App with OAuth

This approach provides more features like interactive messages, but requires more setup.

#### Setup Steps:
1. Go to https://api.slack.com/apps
2. Click **Create New App** → **From scratch**
3. Give your app a name (e.g., "Lifespan Notifications")
4. Select your workspace
5. In the **OAuth & Permissions** section:
   - Add the following scopes:
     - `chat:write` (to send messages)
     - `chat:write.public` (to send to public channels)
     - `channels:read` (to read channel info)
   - Install the app to your workspace
   - Copy the **Bot User OAuth Token** (starts with `xoxb-`)
6. In the **Basic Information** section:
   - Copy the **Client ID**
   - Copy the **Client Secret**
   - Copy the **Signing Secret**

#### Environment Variables:
```env
# OAuth App credentials
SLACK_CLIENT_ID=your_client_id
SLACK_CLIENT_SECRET=your_client_secret
SLACK_SIGNING_SECRET=your_signing_secret
SLACK_BOT_TOKEN=xoxb-your_bot_token
SLACK_USER_TOKEN=xoxp-your_user_token

# Optional - customize appearance
SLACK_CHANNEL=#lifespan-notifications
SLACK_USERNAME=Lifespan Bot
SLACK_ICON=:calendar:
```

## Features

### 1. Event Notifications

#### Span Events
- **Span Created**: Notifies when new spans are created
- **Span Updated**: Notifies when existing spans are modified

#### User Events
- **User Registration**: Notifies when new users register

#### AI Events
- **AI YAML Generation**: Notifies when AI generates biographical YAML data
- **AI Generation Failures**: Alerts when AI operations fail

#### System Events
- **Import Operations**: Reports on data import success/failure rates
- **Backup Operations**: Notifies about database backup status
- **Custom System Events**: General system event notifications

### 2. Configuration Options

The integration is highly configurable through environment variables and configuration files:

#### Environment Variables
```env
# Slack Webhook Configuration (Option 1)
SLACK_WEBHOOK_URL=https://hooks.slack.com/services/YOUR/WEBHOOK/URL
SLACK_CHANNEL=#general
SLACK_USERNAME=Lifespan Bot
SLACK_ICON=:calendar:

# Slack OAuth Configuration (Option 2)
SLACK_CLIENT_ID=your_client_id
SLACK_CLIENT_SECRET=your_client_secret
SLACK_SIGNING_SECRET=your_signing_secret
SLACK_BOT_TOKEN=xoxb-your_bot_token
SLACK_USER_TOKEN=xoxp-your_user_token

# Notification Control
SLACK_NOTIFICATIONS_ENABLED=true
SLACK_NOTIFY_SPAN_CREATED=true
SLACK_NOTIFY_SPAN_UPDATED=true
SLACK_NOTIFY_USER_REGISTERED=true
SLACK_NOTIFY_AI_YAML=true
SLACK_NOTIFY_IMPORT=true
SLACK_NOTIFY_BACKUP=true
SLACK_NOTIFY_SYSTEM_EVENTS=true

# Notification Levels
SLACK_NOTIFICATION_LEVEL=info

# Rate Limiting
SLACK_RATE_LIMITING_ENABLED=true
SLACK_MAX_NOTIFICATIONS_PER_HOUR=100
SLACK_MAX_NOTIFICATIONS_PER_MINUTE=10

# Filtering
SLACK_SPAN_TYPES_EXCLUDE=connection
SLACK_USERS_EXCLUDE=
```

### 3. Notification Levels

Notifications support different levels with appropriate Slack formatting:

- **info**: Blue color, general information
- **warning**: Yellow color, cautionary information
- **error**: Red color, error conditions
- **success**: Green color, successful operations

### 4. Filtering Options

#### Span Type Filtering
- Include/exclude specific span types
- Default: Excludes 'connection' spans to reduce noise

#### User Filtering
- Include/exclude specific users by email
- Useful for testing or limiting notifications

#### Environment Filtering
- Control which environments send notifications
- Default: Production and staging only

## Setup Instructions

### 1. Create Slack Webhook

1. Go to your Slack workspace
2. Navigate to **Apps** → **Custom Integrations** → **Incoming Webhooks**
3. Click **Add Configuration**
4. Choose the channel where notifications should appear
5. Copy the webhook URL

### 2. Configure Environment Variables

Add the following to your `.env` file:

```env
# Required
SLACK_WEBHOOK_URL=https://hooks.slack.com/services/YOUR/WEBHOOK/URL

# Optional - customize appearance
SLACK_CHANNEL=#lifespan-notifications
SLACK_USERNAME=Lifespan Bot
SLACK_ICON=:calendar:

# Optional - control notifications
SLACK_NOTIFICATIONS_ENABLED=true
SLACK_NOTIFY_SPAN_CREATED=true
SLACK_NOTIFY_USER_REGISTERED=true
```

### 3. Test the Integration

Use the provided Artisan command to test the integration:

```bash
# Test system event notification
php artisan slack:test system

# Test AI notification
php artisan slack:test ai

# Test with custom message
php artisan slack:test system --message="Custom test message"
```

## Configuration Files

### `config/slack-notifications.php`

This file contains detailed configuration options for the Slack integration:

```php
return [
    'enabled' => env('SLACK_NOTIFICATIONS_ENABLED', true),
    
    'events' => [
        'span_created' => env('SLACK_NOTIFY_SPAN_CREATED', true),
        'span_updated' => env('SLACK_NOTIFY_SPAN_UPDATED', true),
        'user_registered' => env('SLACK_NOTIFY_USER_REGISTERED', true),
        'ai_yaml_generated' => env('SLACK_NOTIFY_AI_YAML', true),
        'import_completed' => env('SLACK_NOTIFY_IMPORT', true),
        'backup_completed' => env('SLACK_NOTIFY_BACKUP', true),
        'system_events' => env('SLACK_NOTIFY_SYSTEM_EVENTS', true),
    ],
    
    'environments' => [
        'production' => true,
        'staging' => true,
        'local' => false,
        'testing' => false,
    ],
    
    // ... more configuration options
];
```

### `config/services.php`

Slack webhook configuration:

```php
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
```

## Usage Examples

### Sending Custom Notifications

```php
use App\Services\SlackNotificationService;

$slackService = app(SlackNotificationService::class);

// Send a system event notification
$slackService->notifySystemEvent('Custom Event', [
    'user_id' => $user->id,
    'action' => 'performed some action',
    'timestamp' => now()->toISOString(),
], 'info');

// Send AI generation notification
$slackService->notifyAiYamlGenerated('John Doe', true);

// Send import completion notification
$slackService->notifyImportCompleted('CSV Import', 100, 95, 5);
```

### Notification Classes

The integration includes several notification classes:

- `SpanCreatedNotification`: For span creation events
- `SpanUpdatedNotification`: For span update events
- `SystemEventNotification`: For general system events

## Troubleshooting

### Common Issues

1. **No notifications received**
   - Check `SLACK_WEBHOOK_URL` is correct
   - Verify `SLACK_NOTIFICATIONS_ENABLED=true`
   - Check environment filtering settings

2. **Too many notifications**
   - Adjust rate limiting settings
   - Use span type filtering to exclude certain types
   - Increase minimum notification level

3. **Notifications in wrong channel**
   - Check `SLACK_CHANNEL` setting
   - Verify webhook is configured for correct channel

### Testing

Use the test command to verify configuration:

```bash
php artisan slack:test system --message="Test notification"
```

### Logging

Slack notification attempts are logged to Laravel's log system:

- Success: `info` level
- Failures: `error` level with details

## Security Considerations

1. **Webhook URL Security**
   - Keep webhook URLs private
   - Rotate webhook URLs periodically
   - Use environment variables for configuration

2. **OAuth Token Security**
   - Keep OAuth tokens secure
   - Rotate tokens periodically
   - Use environment variables for all credentials

3. **Data Privacy**
   - Notifications include minimal necessary data
   - User emails are included but can be filtered
   - Span names and types are included for context

4. **Rate Limiting**
   - Built-in rate limiting prevents spam
   - Configurable limits per hour/minute
   - Automatic throttling of notifications

## Future Enhancements

Potential improvements for the Slack integration:

1. **Interactive Notifications**
   - Add buttons for quick actions
   - Include links to relevant pages
   - Support for threaded conversations

2. **Advanced Filtering**
   - Time-based filtering
   - Custom notification rules
   - User preference settings

3. **Analytics**
   - Notification delivery tracking
   - Usage statistics
   - Performance monitoring

4. **Integration with Other Services**
   - Discord webhooks
   - Microsoft Teams
   - Email notifications

## Support

For issues with the Slack integration:

1. Check the Laravel logs for error messages
2. Verify webhook configuration
3. Test with the provided Artisan command
4. Review environment variable settings 