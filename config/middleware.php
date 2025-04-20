<?php

return [
    // Standardmäßig aktivierte Middleware (in der Reihenfolge der Ausführung)
    'global' => [
        // Immer CORS als erste Middleware, damit die Header gesetzt werden
        \App\Core\Middleware\CorsMiddleware::class,
        \App\Core\Middleware\LogMiddleware::class,
    ],

    // CORS-Konfiguration
    'cors' => [
        'allowedOrigins' => ['*'],
        'allowedMethods' => ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'],
        'allowedHeaders' => [
            'Content-Type',
            'X-Requested-With',
            'Authorization',
            'X-API-Token',
            'X-Session-ID'
        ],
        'exposedHeaders' => [],
        'maxAge' => 86400,  // 24 Stunden
        'supportsCredentials' => false,
        'allowPrivateNetwork' => false,
    ],

    // Authentifizierungs-Middleware-Konfiguration
    'auth' => [
        'ignoredPaths' => [
            '/api/auth/login',
            '/api/auth/register',
            '/api/auth/reset-password',
            '/api/docs/*', // API-Dokumentation
            '/health',     // Health-Check
        ]
    ],

    // Log-Middleware-Konfiguration
    'log' => [
        'level' => 'debug',
        'sensitiveHeaders' => [
            'Authorization',
            'Cookie',
            'X-API-Token',
            'X-Session-ID',
            'X-CSRF-Token',
        ]
    ]
];