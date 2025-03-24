<?php

declare(strict_types=1);

namespace App\Infrastructure\Routing;

use App\Infrastructure\Container\Contracts\ContainerInterface;
use App\Infrastructure\Container\Exceptions\ContainerException;
use App\Infrastructure\Container\Exceptions\NotFoundException;
use App\Infrastructure\Container\ServiceProvider;
use App\Infrastructure\Http\Contracts\ResponseFactoryInterface;
use App\Infrastructure\Http\Factory\ResponseFactory;
use App\Infrastructure\Routing\Contracts\RouterInterface;
use App\Infrastructure\Routing\Contracts\RouteScannerInterface;
use App\Infrastructure\Routing\Contracts\UrlGeneratorInterface;

/**
 * Service Provider für Routing-Komponenten
 */
class RoutingServiceProvider extends ServiceProvider
{
    /**
     * Registriert Routing-Services im Container
     *
     * @param ContainerInterface $container
     * @return void
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function register(ContainerInterface $container): void
    {
        // Registriere Interfaces auf konkrete Implementierungen
        $container->bind(RouterInterface::class, Router::class);
        $container->bind(UrlGeneratorInterface::class, UrlGenerator::class);
        $container->bind(RouteScannerInterface::class, RouteScanner::class);

        // Bind ResponseFactoryInterface to ResponseFactory
        $container->bind(ResponseFactoryInterface::class, ResponseFactory::class);

        // Registriere Singleton-Instanzen
        $container->singleton(Router::class);
        $container->singleton(UrlGenerator::class);
        $container->singleton(ResponseFactory::class);

        // Router abrufen und optional Konfigurationen vornehmen
        $router = $container->get(RouterInterface::class);

        // Beispielhafte Konfiguration von Standard-Fehler-Handlern
        $this->configureDefaultErrorHandlers($router, $container);
    }

    /**
     * Konfiguriert Standard-Fehler-Handler für den Router
     *
     * @param RouterInterface $router
     * @param ContainerInterface $container
     */
    protected function configureDefaultErrorHandlers(RouterInterface $router, ContainerInterface $container): void
    {
        // 404 Not Found Handler
        $router->registerErrorHandler(404, function ($request) use ($container) {
            $responseFactory = $container->get(ResponseFactoryInterface::class);
            $content = '<!DOCTYPE html>
                <html lang="de">
                <head>
                    <title>404 Nicht gefunden</title>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; padding: 20px; }
                        .error-container { max-width: 800px; margin: 0 auto; }
                        h1 { color: #e74c3c; }
                    </style>
                </head>
                <body>
                    <div class="error-container">
                        <h1>404 - Seite nicht gefunden</h1>
                        <p>Die angeforderte Seite konnte nicht gefunden werden.</p>
                        <p>Pfad: ' . htmlspecialchars($request->getPath()) . '</p>
                    </div>
                </body>
                </html>';

            return $responseFactory->createHtml($content, 404);
        });

        // 405 Method Not Allowed Handler
        $router->registerErrorHandler(405, function ($request, $exception) use ($container) {
            $responseFactory = $container->get(ResponseFactoryInterface::class);
            $allowedMethods = $exception->getAllowedMethods();

            $content = '<!DOCTYPE html>
                <html lang="de">
                <head>
                    <title>405 Methode nicht erlaubt</title>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; padding: 20px; }
                        .error-container { max-width: 800px; margin: 0 auto; }
                        h1 { color: #e74c3c; }
                    </style>
                </head>
                <body>
                    <div class="error-container">
                        <h1>405 - Methode nicht erlaubt</h1>
                        <p>Die HTTP-Methode "' . htmlspecialchars($request->getMethod()) . '" ist für diese Ressource nicht erlaubt.</p>
                        <p>Erlaubte Methoden: ' . htmlspecialchars(implode(', ', $allowedMethods)) . '</p>
                    </div>
                </body>
                </html>';

            $response = $responseFactory->createHtml($content, 405);
            $response->setHeader('Allow', implode(', ', $allowedMethods));

            return $response;
        });
    }
}