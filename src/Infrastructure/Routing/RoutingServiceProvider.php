<?php


declare(strict_types=1);

namespace App\Infrastructure\Routing;

use App\Infrastructure\Container\Contracts\ContainerInterface;
use App\Infrastructure\Container\ServiceProvider;
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
    }
}