<?php

use App\Core\Security\JWT;

return [
    'jwt' => [
        'secret' => env('JWT_SECRET', 'change-this-secret-key-in-production'),
        'algorithm' => JWT::ALGO_HS256,
        'lifetime' => 3600, // 1 Stunde
        'refresh_lifetime' => 604800, // 7 Tage
        'issuer' => env('APP_URL', 'https://yourapp.com'),
    ],

    'ignored_paths' => [
        '/api/auth/login',
        '/api/auth/register',
        '/api/auth/refresh',
        '/api/health',
        '/api/docs/*',
    ]
];