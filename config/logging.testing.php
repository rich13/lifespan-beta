<?php

use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogUdpHandler;
use Monolog\Processor\PsrLogMessageProcessor;

return [
    /*
    |--------------------------------------------------------------------------
    | Default Log Channel
    |--------------------------------------------------------------------------
    */

    'default' => env('LOG_CHANNEL', 'testing'),

    /*
    |--------------------------------------------------------------------------
    | Deprecations Log Channel
    |--------------------------------------------------------------------------
    */

    'deprecations' => [
        'channel' => env('LOG_DEPRECATIONS_CHANNEL', 'null'),
        'trace' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Log Channels
    |--------------------------------------------------------------------------
    */

    'channels' => [
        'stack' => [
            'driver' => 'stack',
            'channels' => ['stderr', 'errorlog'],
            'ignore_exceptions' => false,
        ],

        'testing' => [
            'driver' => 'stack',
            'channels' => ['stderr', 'errorlog'],
            'ignore_exceptions' => false,
        ],

        'single' => [
            'driver' => 'errorlog',
            'level' => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
        ],

        'daily' => [
            'driver' => 'errorlog',
            'level' => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
        ],

        'stderr' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => StreamHandler::class,
            'formatter' => env('LOG_STDERR_FORMATTER', Monolog\Formatter\LineFormatter::class),
            'formatter_with' => [
                'format' => "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n",
            ],
            'with' => [
                'stream' => 'php://stderr',
            ],
        ],

        'syslog' => [
            'driver' => 'syslog',
            'level' => env('LOG_LEVEL', 'debug'),
            'facility' => LOG_USER,
            'replace_placeholders' => true,
        ],

        'errorlog' => [
            'driver' => 'errorlog',
            'level' => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
        ],

        'null' => [
            'driver' => 'monolog',
            'handler' => NullHandler::class,
        ],

        'emergency' => [
            'driver' => 'errorlog',
        ],

        // Custom channels for Lifespan 
        'spans' => [
            'driver' => 'stack',
            'channels' => ['stderr', 'errorlog'],
            'ignore_exceptions' => false,
        ],

        'relationships' => [
            'driver' => 'stack',
            'channels' => ['stderr', 'errorlog'],
            'ignore_exceptions' => false,
        ],

        'security' => [
            'driver' => 'stack',
            'channels' => ['stderr', 'errorlog'],
            'ignore_exceptions' => false,
        ],

        'performance' => [
            'driver' => 'stack',
            'channels' => ['stderr', 'errorlog'],
            'ignore_exceptions' => false,
        ],

        'connections' => [
            'driver' => 'stack',
            'channels' => ['stderr', 'errorlog'],
            'ignore_exceptions' => false,
        ],
    ],
]; 