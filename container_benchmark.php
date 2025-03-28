<?php
// Speichern Sie diese Datei als container_benchmark_simple.php

// Pfad zu Ihrer autoload.php anpassen
require_once __DIR__ . '/vendor/autoload.php';

use App\Infrastructure\Container\Container;

// Einfache Testfunktion
function testContainer()
{
    $container = new Container();

    echo "Container erstellt." . PHP_EOL;

    // Einfachen Service registrieren
    $container->bind('test.service', function () {
        return new stdClass();
    });

    // Timing für Service-Auflösung
    $start = microtime(true);
    for ($i = 0; $i < 10000; $i++) {
        $container->get('test.service');
    }
    $end = microtime(true);

    echo "1000 Service-Auflösungen: " . number_format(($end - $start) * 1000, 2) . " ms" . PHP_EOL;
    echo "Durchschnitt pro Auflösung: " . number_format(($end - $start) * 1000 / 1000, 3) . " ms" . PHP_EOL;

    echo "Speicherverbrauch: " . number_format(memory_get_usage() / 1024 / 1024, 2) . " MB" . PHP_EOL;
}

// Führe den Test aus
echo "Starte einfachen Container-Test..." . PHP_EOL;
testContainer();
echo "Test abgeschlossen." . PHP_EOL;