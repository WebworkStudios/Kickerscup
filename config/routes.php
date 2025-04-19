<?php

declare(strict_types=1);

/**
 * Routen-Konfiguration
 *
 * Hier können alle Routen für die Anwendung definiert werden.
 */

// Lokale Variable für Router verwenden
$router = $app->getRouter();

$router->prefix('/api')->group(function ($router) {
    $router->get('/status', fn($request) => response()->json([
        'status' => 'online',
        'version' => '1.0.0',
        'timestamp' => time()
    ]));

    // Nur API-Routen definieren
});