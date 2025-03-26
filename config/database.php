<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default Database Connection
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the database connections below you wish
    | to use as your default connection for all database work.
    |
    */
    'default' => 'mysql',

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    |
    | Here are each of the database connections setup for your application.
    |
    */
    'connections' => [
        'mysql' => [
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'port' => 3306,
            'database' => 'testdb',
            'username' => 'root',
            'password' => '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Query Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the statement cache size and other cache-related settings.
    |
    */
    'statement_cache' => [
        'max_size' => 100
    ],

    /*
    |--------------------------------------------------------------------------
    | Query Debugging
    |--------------------------------------------------------------------------
    |
    | Configure query logging and debugging settings.
    |
    */
    'debug' => [
        'enabled' => true,
        'with_backtrace' => true
    ]
];