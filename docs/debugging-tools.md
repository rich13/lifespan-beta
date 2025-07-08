# Debugging Tools for Local Development

This document explains how to use Laravel Telescope and Laravel Debugbar for debugging and monitoring your application during local development.

## Laravel Telescope

Laravel Telescope is an elegant debug assistant for the Laravel framework that provides insight into the requests coming into your application, exceptions, log entries, database queries, queued jobs, mail, notifications, scheduled tasks, variable dumps, and more.

### Accessing Telescope

- **URL**: `http://localhost/telescope`
- **Access**: Available to all users in local environment
- **Authentication**: Uses the `viewTelescope` gate (automatically allows local environment)

### What Telescope Monitors

- **Requests**: All HTTP requests and responses
- **Database Queries**: SQL queries with bindings and execution time
- **Cache Operations**: Cache hits, misses, and operations
- **Log Entries**: Application log messages
- **Exceptions**: Error details and stack traces
- **Mail**: Outgoing email messages
- **Notifications**: Application notifications
- **Jobs**: Queue job execution
- **Events**: Application events
- **Model Operations**: Eloquent model queries and operations
- **Redis Operations**: Redis commands and responses

### Configuration

Telescope is configured in `config/telescope.php` and controlled via environment variables:

```env
TELESCOPE_ENABLED=true
TELESCOPE_PATH=telescope
```

### Filtering Data

Telescope automatically filters sensitive data in non-local environments. In local development, all data is captured for debugging purposes.

## Laravel Debugbar

Laravel Debugbar is a package to integrate PHP Debug Bar with Laravel. It includes a web profiler toolbar that can be injected into your application.

### Features

- **Database Queries**: Shows all executed queries with timing
- **Memory Usage**: Displays memory consumption
- **Request Information**: Shows request details and response
- **Views**: Lists rendered views with data
- **Session Data**: Displays session information
- **Cache Operations**: Shows cache hits and misses
- **Mail**: Displays sent emails
- **Models**: Shows Eloquent model operations

### Configuration

Debugbar is configured in `config/debugbar.php` and controlled via environment variables:

```env
DEBUGBAR_ENABLED=true
DEBUGBAR_EDITOR=phpstorm
```

### Accessing Debugbar

The debugbar appears as a toolbar at the bottom of your web pages when:
- `APP_DEBUG=true` (which it is in local environment)
- `DEBUGBAR_ENABLED=true`
- You're not on excluded paths (like `/telescope/*`)

### Editor Integration

Clicking on file names in the debugbar will open them in your configured editor (PhpStorm by default).

## Usage Tips

### For Performance Debugging

1. **Use Telescope** to monitor:
   - Slow database queries
   - N+1 query problems
   - Cache performance
   - Job execution times

2. **Use Debugbar** to monitor:
   - Real-time query execution
   - Memory usage per request
   - View rendering times
   - Session data

### For Error Debugging

1. **Telescope** provides detailed exception information with stack traces
2. **Debugbar** shows the current request context and variables

### For Development Workflow

1. Keep Telescope open in a separate tab to monitor all requests
2. Use Debugbar to inspect specific requests and their data
3. Both tools automatically exclude each other to prevent conflicts

## Security Notes

- Both tools are only enabled in local development
- Telescope automatically hides sensitive data in production
- Debugbar is completely disabled in production environments
- Access to Telescope in production requires admin privileges

## Troubleshooting

### Debugbar Not Showing

1. Check that `APP_DEBUG=true` in your `.env`
2. Verify `DEBUGBAR_ENABLED=true`
3. Clear configuration cache: `php artisan config:clear`
4. Check that you're not on an excluded path

### Telescope Not Accessible

1. Ensure `TELESCOPE_ENABLED=true`
2. Run migrations: `php artisan migrate`
3. Clear configuration cache: `php artisan config:clear`
4. Check the `viewTelescope` gate in `TelescopeServiceProvider`

### Performance Impact

- Both tools have minimal performance impact in development
- Telescope stores data in the database (can be cleared with `php artisan telescope:clear`)
- Debugbar stores data in files (automatically cleaned up)

## Commands

### Telescope Commands

```bash
# Clear all Telescope data
php artisan telescope:clear

# Install Telescope assets
php artisan telescope:install

# Publish Telescope configuration
php artisan vendor:publish --tag=telescope-config
```

### Debugbar Commands

```bash
# Publish Debugbar configuration
php artisan vendor:publish --provider="Barryvdh\Debugbar\ServiceProvider"

# Clear Debugbar cache
php artisan debugbar:clear
``` 