<?php

declare(strict_types=1);

/**
 * Routen-Konfiguration
 *
 * Hier können alle Routen für die Anwendung definiert werden.
 */

// Lokale Variable für Router verwenden
$router = $app->getRouter();

// Beispielrouten
$router->get('/', function ($request) {
    return response()->html('<h1>Willkommen zum Football Manager Framework</h1>');
});

$router->get('/api/status', function ($request) {
    return response()->json([
        'status' => 'online',
        'version' => '1.0.0',
        'timestamp' => time()
    ]);
});

// In config/routes.php
$router->get('/debug', function ($request) {
    return response()->json([
        'message' => 'Debug route works!'
    ]);
});

// API-Routen
$router->prefix('/api')->group(function ($router) {
    // Hier können weitere API-Routen definiert werden
});

// Admin-Bereich
$router->domain('admin.example.com')->group(function ($router) {
    $router->get('/', function ($request) {
        return response()->html('<h1>Admin-Bereich</h1>');
    });
});