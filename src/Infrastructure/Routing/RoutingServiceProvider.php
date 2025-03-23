<?php

declare(strict_types=1);

namespace App\Infrastructure\Routing;

use App\Infrastructure\Container\Contracts\ContainerInterface;
use App\Infrastructure\Container\ServiceProvider;
use App\Infrastructure\Http\Contracts\ResponseFactoryInterface;
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
     */
    public function register(ContainerInterface $container): void
    {
        // Registriere Interfaces auf konkrete Implementierungen
        $container->bind(RouterInterface::class, Router::class);
        $container->bind(UrlGeneratorInterface::class, UrlGenerator::class);
        $container->bind(RouteScannerInterface::class, RouteScanner::class);

        // Registriere Singleton-Instanzen
        $container->singleton(Router::class);
        $container->singleton(UrlGenerator::class);

        // Standardfehlerbehandlung konfigurieren, falls gewünscht
        // Dies ist optional und kann auch nach der Registrierung direkt im Router erfolgen
        $router = $container->get(RouterInterface::class);

        // Standardmäßig den Router mit Fehlerseiten-Handlern konfigurieren
        $this->setupErrorHandlers($router, $container);
    }

    /**
     * Richtet die Standard-Fehlerbehandlung ein
     *
     * @param RouterInterface $router
     * @param ContainerInterface $container
     * @return void
     */
    protected function setupErrorHandlers(RouterInterface $router, ContainerInterface $container): void
    {
        // Beispiel für die Registrierung von Standardfehlern
        // In einer vollständigen Implementation würde hier auf Konfigurationen zugegriffen
        // oder Controller registriert

        // 404 Not Found Handler
        $router->registerErrorHandler(404, function ($request, $exception) {
            // Einfacher Standardhandler für 404-Fehler
            // In der Praxis würde hier ein vollständiger Controller verwendet
            $content = '<!DOCTYPE html>
                <html>
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

            return $container->get(ResponseFactoryInterface::class)->createHtml($content, 404);
        });

        // 405 Method Not Allowed Handler
        $router->registerErrorHandler(405, function ($request, $exception) use ($container) {
            // In der Praxis würde hier ein Controller verwendet
            $allowedMethods = $exception->getAllowedMethods();

            $content = '<!DOCTYPE html>
                <html>
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

            $response = $container->get(ResponseFactoryInterface::class)->createHtml($content, 405);
            $response->setHeader('Allow', implode(', ', $allowedMethods));

            return $response;
        });
    }
}