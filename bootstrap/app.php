<?php

declare(strict_types=1);

use App\Infrastructure\Application\Application;
use App\Infrastructure\Container\Container;
use App\Infrastructure\Container\ServiceScanner;
use App\Infrastructure\Database\DatabaseServiceProvider;
use App\Infrastructure\ErrorHandling\ErrorHandlingServiceProvider;
use App\Infrastructure\Http\Factory\RequestFactory;
use App\Infrastructure\Http\Factory\ResponseFactory;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Logging\LoggerServiceProvider;
use App\Infrastructure\Routing\RoutingServiceProvider;
use App\Infrastructure\Security\Csrf\CsrfServiceProvider;
use App\Infrastructure\Session\SessionServiceProvider;
use App\Infrastructure\Validation\ValidationServiceProvider;


$container = new Container;

$container->singleton('config', function () {
    return new App\Infrastructure\Config\Config;
});
// Register core services
$routingProvider = new RoutingServiceProvider;
$routingProvider->register($container);

$sessionProvider = new SessionServiceProvider;
$sessionProvider->register($container);

$csrfProvider = new CsrfServiceProvider;
$csrfProvider->register($container);

$loggerProvider = new LoggerServiceProvider;
$loggerProvider->register($container);

$errorHandlingProvider = new ErrorHandlingServiceProvider;
$errorHandlingProvider->register($container);

$databaseProvider = new DatabaseServiceProvider;
$databaseProvider->register($container);

$provider = new ValidationServiceProvider;
$provider->register($container);

$container->singleton(App\Infrastructure\Http\Contracts\RequestFactoryInterface::class, RequestFactory::class);
$container->singleton(App\Infrastructure\Http\Contracts\ResponseFactoryInterface::class, ResponseFactory::class);

$container->bind(App\Infrastructure\Http\Contracts\RequestInterface::class, Request::class);

$config = require APP_ROOT . '/config/app.php';

$serviceScanner = new ServiceScanner($container);
$serviceScanner->scan([
    APP_ROOT . '/src/Application',
    APP_ROOT . '/src/Domain',
    APP_ROOT . '/src/Infrastructure',
]);

$routeScanner = $container->get(App\Infrastructure\Routing\Contracts\RouteScannerInterface::class);

$routeScanner->scan([
    APP_ROOT . '/src/Presentation/Controllers',
    APP_ROOT . '/src/Presentation/Actions',
], 'App\\Presentation');

$routesCallback = require APP_ROOT . '/config/routes.php';
$routesCallback($container);

return $container->get(Application::class);
