<?php

/**
 * Core application configuration.
 */
return [
    'name' => env('APP_NAME', 'Vayu'),

    'env' => env('APP_ENV', 'production'),

    'debug' => filter_var(env('APP_DEBUG', false), FILTER_VALIDATE_BOOLEAN),

    'url' => env('APP_URL', null),

    'key' => env('APP_KEY', null),

    'timezone' => env('APP_TIMEZONE', 'UTC'),

    // Logging channel: 'daily' or 'single'
    'log' => env('LOG_CHANNEL', 'daily'),
];
