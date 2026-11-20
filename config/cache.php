<?php

/**
 * Cache configuration.
 */
return [
    'default' => env('CACHE_DRIVER', 'file'),

    // Global key prefix.
    'prefix' => env('CACHE_PREFIX', 'vayu_'),

    // Default TTL in seconds for put()/remember() when none is given
    // (null = store forever).
    'ttl' => env('CACHE_TTL', 3600),

    'stores' => [
        'file' => [
            'driver' => 'file',
            'path' => 'cache/data', // relative to storage/
        ],

        'array' => [
            'driver' => 'array',
        ],

        'redis' => [
            'driver' => 'redis',
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'port' => env('REDIS_PORT', 6379),
            'password' => env('REDIS_PASSWORD', null),
            'database' => env('REDIS_CACHE_DB', 1),
        ],
    ],
];
