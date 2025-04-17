<?php

declare(strict_types=1);

/**
 * Datenbank-Konfiguration
 *
 * Hier können alle Datenbank-Verbindungen konfiguriert werden.
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Standardverbindung
    |--------------------------------------------------------------------------
    |
    | Die Standardverbindung, die verwendet werden soll.
    |
    */
    'default' => env('DB_CONNECTION', 'game'),

    /*
    |--------------------------------------------------------------------------
    | Datenbankverbindungen
    |--------------------------------------------------------------------------
    |
    | Hier können alle Datenbankverbindungen konfiguriert werden.
    |
    */
    'connections' => [
        'game' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST', 'localhost'),
            'port' => env('DB_PORT', 3306),
            'database' => env('DB_DATABASE', 'football_manager'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'options' => [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => true
            ]
        ],

        'forum' => [
            'driver' => 'mysql',
            'host' => env('FORUM_DB_HOST', 'localhost'),
            'port' => env('FORUM_DB_PORT', 3306),
            'database' => env('FORUM_DB_DATABASE', 'football_manager_forum'),
            'username' => env('FORUM_DB_USERNAME', 'root'),
            'password' => env('FORUM_DB_PASSWORD', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'options' => [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => true
            ]
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Redis-Konfiguration
    |--------------------------------------------------------------------------
    |
    | Hier können alle Redis-Verbindungen konfiguriert werden.
    |
    */
    'redis' => [
        'default' => [
            'host' => env('REDIS_HOST', 'localhost'),
            'port' => env('REDIS_PORT', 6379),
            'database' => env('REDIS_DB', 0),
            'password' => env('REDIS_PASSWORD', null),
            'prefix' => env('REDIS_PREFIX', 'football_manager:')
        ],

        'cache' => [
            'host' => env('REDIS_HOST', 'localhost'),
            'port' => env('REDIS_PORT', 6379),
            'database' => env('REDIS_CACHE_DB', 1),
            'password' => env('REDIS_PASSWORD', null),
            'prefix' => env('REDIS_PREFIX', 'football_manager:cache:')
        ],

        'session' => [
            'host' => env('REDIS_HOST', 'localhost'),
            'port' => env('REDIS_PORT', 6379),
            'database' => env('REDIS_SESSION_DB', 2),
            'password' => env('REDIS_PASSWORD', null),
            'prefix' => env('REDIS_PREFIX', 'football_manager:session:')
        ]
    ]
];