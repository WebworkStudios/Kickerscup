<?php

declare(strict_types=1);

/**
 * Einstiegspunkt der Anwendung
 *
 * Bootet das Framework und verarbeitet die Anfrage
 */

// Autoloader laden
require __DIR__ . '/../vendor/autoload.php';

// Fehlerbehandlung für die Entwicklung einschalten
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Anwendung erstellen
$app = new \App\Core\Application(dirname(__DIR__));

// Container-Zugriff global verfügbar machen
setContainer($app->getContainer());

// Anwendung starten und Response zurückgeben
$response = $app->handle(
    \App\Core\Http\Request::fromGlobals()
);

// Response senden
$response->send();