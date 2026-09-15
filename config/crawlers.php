<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Blocked crawler user-agent fragments
    |--------------------------------------------------------------------------
    |
    | Requests whose User-Agent contains any of these fragments (case
    | insensitive) are refused before the app does expensive work. Keep this
    | list in sync with the map in docker/prod/nginx.conf.
    |
    */

    'user_agent_patterns' => [
        'GPTBot',
        'ChatGPT-User',
        'OAI-SearchBot',
        'ClaudeBot',
        'anthropic-ai',
        'Google-Extended',
        'CCBot',
        'Bytespider',
        'Amazonbot',
        'Applebot-Extended',
        'meta-externalagent',
        'PetalBot',
        'AhrefsBot',
        'SemrushBot',
        'DotBot',
        'MJ12bot',
        'Scrapy',
    ],

];
