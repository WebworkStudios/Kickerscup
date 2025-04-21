<?php

declare(strict_types=1);

/**
 * Konfiguration für Rate-Limiting
 */
return [
    /*
    |--------------------------------------------------------------------------
    | Pfade, die vom Rate-Limiting ausgenommen sind
    |--------------------------------------------------------------------------
    */
    'ignored_paths' => [
        '/health',      // Health-Check
        '/metrics',     // Monitoring
        '/docs/*',      // API-Dokumentation
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate-Limit-Konfigurationen
    |--------------------------------------------------------------------------
    |
    | Jede Konfiguration definiert ein separates Rate-Limit mit eigenen Regeln
    |
    */
    'limiters' => [
        'api' => [
            'limit' => 60,     // 60 Requests
            'window' => 60,     // pro Minute
            'paths' => ['/api/*'], // Gültig für alle API-Pfade
        ],

        'auth' => [
            'limit' => 5,      // 5 Requests
            'window' => 60,     // pro Minute
            'paths' => [
                '/api/auth/login',
                '/api/auth/register',
                '/api/auth/reset-password',
            ],
        ],

        'critical' => [
            'limit' => 3,      // 3 Requests
            'window' => 300,    // pro 5 Minuten
            'paths' => [
                '/api/user/delete',
                '/api/admin/*',
            ],
        ],
    ],
];