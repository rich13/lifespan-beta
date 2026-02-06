<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Cache Store
    |--------------------------------------------------------------------------
    |
    | This option controls the default cache connection that gets used while
    | using this caching library. This connection is used when another is
    | not explicitly specified when executing a given caching function.
    |
    | For production with many public spans (~30k+), use Redis (CACHE_DRIVER=redis).
    | File cache does not scale well at that volume (disk size, inodes, no
    | eviction). Redis is shared across instances and supports memory limits.
    |
    */

    'default' => env('CACHE_DRIVER', 'file'),

    /*
    |--------------------------------------------------------------------------
    | Cache Stores
    |--------------------------------------------------------------------------
    |
    | Here you may define all of the cache "stores" for your application as
    | well as their drivers. You may even define multiple stores for the
    | same cache driver to group types of items stored in your caches.
    |
    | Supported drivers: "apc", "array", "database", "file",
    |         "memcached", "redis", "dynamodb", "octane", "null"
    |
    */

    'stores' => [

        'apc' => [
            'driver' => 'apc',
        ],

        'array' => [
            'driver' => 'array',
            'serialize' => false,
        ],

        'database' => [
            'driver' => 'database',
            'table' => 'cache',
            'connection' => null,
            'lock_connection' => null,
        ],

        'file' => [
            'driver' => 'file',
            'path' => storage_path('framework/cache/data'),
            'lock_path' => storage_path('framework/cache/data'),
        ],

        'memcached' => [
            'driver' => 'memcached',
            'persistent_id' => env('MEMCACHED_PERSISTENT_ID'),
            'sasl' => [
                env('MEMCACHED_USERNAME'),
                env('MEMCACHED_PASSWORD'),
            ],
            'options' => [
                // Memcached::OPT_CONNECT_TIMEOUT => 2000,
            ],
            'servers' => [
                [
                    'host' => env('MEMCACHED_HOST', '127.0.0.1'),
                    'port' => env('MEMCACHED_PORT', 11211),
                    'weight' => 100,
                ],
            ],
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => 'cache',
            'lock_connection' => 'default',
        ],

        'dynamodb' => [
            'driver' => 'dynamodb',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'table' => env('DYNAMODB_CACHE_TABLE', 'cache'),
            'endpoint' => env('DYNAMODB_ENDPOINT'),
        ],

        'octane' => [
            'driver' => 'octane',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Key Prefix
    |--------------------------------------------------------------------------
    |
    | When utilizing the APC, database, memcached, Redis, or DynamoDB cache
    | stores there might be other applications using the same cache. For
    | that reason, you may prefix every cache key to avoid collisions.
    |
    */

    'prefix' => env('CACHE_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_').'_cache_'),

    /*
    |--------------------------------------------------------------------------
    | Public span page cache TTL
    |--------------------------------------------------------------------------
    |
    | Time-to-live in seconds for cached public span show pages (guest view).
    | Per-span invalidation on update means a long TTL is safe; this is how
    | long the HTML is reused before expiring. Default: 1 year (closed beta).
    |
    */

    'public_span_ttl' => (int) env('PUBLIC_SPAN_CACHE_TTL', 31536000), // 1 year (server-side Redis)

    /*
    |--------------------------------------------------------------------------
    | Public span page cache: browser Cache-Control max-age
    |--------------------------------------------------------------------------
    |
    | Shorter than server TTL so browsers revalidate sooner; the next request
    | hits the server and gets the (possibly updated) server-cached response.
    | Default: 5 minutes.
    |
    */
    'public_span_browser_max_age' => (int) env('PUBLIC_SPAN_BROWSER_MAX_AGE', 300), // 5 minutes

    /*
    |--------------------------------------------------------------------------
    | Public span page cache: rewarm after invalidation
    |--------------------------------------------------------------------------
    |
    | When a span or connection is updated, we invalidate affected pages. If
    | this is true, we also dispatch a job to rewarm those pages. Set to false
    | to avoid triggering expensive full page renders on every update.
    |
    */
    'warm_public_span_pages_on_invalidation' => (bool) env('WARM_PUBLIC_SPAN_PAGES_ON_INVALIDATION', false),

];
