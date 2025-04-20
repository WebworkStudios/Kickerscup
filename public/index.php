<?php

declare(strict_types=1);

/**
 * Einstiegspunkt der Anwendung
 *
 * Bootet das Framework und verarbeitet die Anfrage
 */

// Autoloader laden
require __DIR__ . '/../vendor/autoload.php';

// Umgebungsvariablen laden (optional, falls Sie dotenv nutzen möchten)
// (new \Dotenv\Dotenv(__DIR__ . '/..'))->load();

// Anwendung erstellen
$app = new \App\Core\Application(dirname(__DIR__));

// Globalen Container setzen
setContainer($app->getContainer());

// Middleware aus der Konfiguration registrieren
$middlewareConfig = config('middleware', []);
$globalMiddleware = $middlewareConfig['global'] ?? [];

foreach ($globalMiddleware as $middlewareClass) {
    // Middleware-Instanz mit Konfiguration erstellen
    switch ($middlewareClass) {
        case \App\Core\Middleware\CorsMiddleware::class:
            $middleware = new $middlewareClass($middlewareConfig['cors'] ?? []);
            break;
        case \App\Core\Middleware\AuthMiddleware::class:
            $middleware = new $middlewareClass(
                app(\App\Core\Security\Csrf::class),
                app(\App\Core\Http\ResponseFactory::class),
                $middlewareConfig['auth']['ignoredPaths'] ?? []
            );
            break;
        case \App\Core\Middleware\LogMiddleware::class:
            $middleware = new $middlewareClass(
                $middlewareConfig['log']['level'] ?? 'debug',
                $middlewareConfig['log']['sensitiveHeaders'] ?? []
            );
            break;
        default:
            $middleware = app($middlewareClass);
    }

    $app->addMiddleware($middleware);
}

// Anwendung starten und Response zurückgeben
$response = $app->handle(
    \App\Core\Http\Request::fromGlobals()
);

// Response senden
$response->send();