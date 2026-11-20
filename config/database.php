<?php

/**
 * Database configuration.
 *
 * The same query-builder / ORM API works across every driver below.
 */
return [
    'default' => env('DB_CONNECTION', env('DB_TYPE', 'sqlite')),

    'connections' => [
        'sqlite' => [
            'driver' => 'sqlite',
            // Relative paths resolve against the project root. Use ':memory:'
            // for an ephemeral in-memory database.
            'database' => env('DB_DATABASE', 'database/database.sqlite'),
            'foreign_keys' => true,
        ],

        'mysql' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'vayu'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
        ],
    ],

    'migrations' => 'migrations',
];
