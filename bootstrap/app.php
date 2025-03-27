<?php

declare(strict_types=1);

use App\Infrastructure\Application\Application;
use App\Infrastructure\Container\Container;
use App\Infrastructure\Container\ServiceScanner;
use App\Infrastructure\ErrorHandling\ErrorHandlingServiceProvider;
use App\Infrastructure\Http\Factory\RequestFactory;
use App\Infrastructure\Http\Factory\ResponseFactory;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Logging\LoggerServiceProvider;
use App\Infrastructure\Routing\RoutingServiceProvider;

// Erstelle den Container
$container = new Container;

// Konfiguration laden
$container->singleton('config', function () {
    return new App\Infrastructure\Config\Config;
});

// Logger-Provider registrieren (wird für den ServiceScanner benötigt)
$loggerProvider = new LoggerServiceProvider;
$loggerProvider->register($container);

// Error-Handling-Provider registrieren (wichtig für frühe Fehlerbehandlung)
$errorHandlingProvider = new ErrorHandlingServiceProvider;
$errorHandlingProvider->register($container);

// Routing-Provider registrieren (wichtig für grundlegende Anwendungsfunktionalität)
$routingProvider = new RoutingServiceProvider;
$routingProvider->register($container);

// Kern-Factories registrieren
$container->singleton(App\Infrastructure\Http\Contracts\RequestFactoryInterface::class, RequestFactory::class);
$container->singleton(App\Infrastructure\Http\Contracts\ResponseFactoryInterface::class, ResponseFactory::class);

// Automatische Service-Erkennung
$serviceScanner = new ServiceScanner($container);
$serviceScanner->scan([
    APP_ROOT . '/src/Application',
    APP_ROOT . '/src/Domain',
    APP_ROOT . '/src/Infrastructure',
]);

// Konfiguration laden
$config = require APP_ROOT . '/config/app.php';

// Routen-Scanner ausführen
$routeScanner = $container->get(App\Infrastructure\Routing\Contracts\RouteScannerInterface::class);
$routeScanner->scan([
    APP_ROOT . '/src/Presentation/Controllers',
    APP_ROOT . '/src/Presentation/Actions',
], 'App\\Presentation');

// Manuelle Routen registrieren (falls vorhanden)
$routesCallback = require APP_ROOT . '/config/routes.php';
$routesCallback($container);

// Application zurückgeben
return $container->get(Application::class);