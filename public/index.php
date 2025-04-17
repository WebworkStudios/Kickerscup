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

// Anwendung starten und Response zurückgeben
$response = $app->handle(
    \App\Core\Http\Request::fromGlobals()
);

// Response senden
$response->send();