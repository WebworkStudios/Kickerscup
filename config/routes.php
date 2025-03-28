<?php

declare(strict_types=1);

/**
 * Application Routes
 *
 * This file contains manually registered routes.
 * For attribute-based routing, use the appropriate attributes in your
 * controller classes.
 */

use App\Infrastructure\Routing\Contracts\RouterInterface;

// Diese Datei gibt jetzt eine Funktion zurück, die den Container als Parameter nimmt
return function ($container) {
    /** @var RouterInterface $router */
    $router = $container->get(RouterInterface::class);

    // Define routes
    $router->addRoute('GET', '/', function () use ($container) {
        $responseFactory = $container->get(App\Infrastructure\Http\Contracts\ResponseFactoryInterface::class);
        return $responseFactory->createHtml('<h1>Welcome to your PHP Framework</h1>');
    }, 'home');
};
