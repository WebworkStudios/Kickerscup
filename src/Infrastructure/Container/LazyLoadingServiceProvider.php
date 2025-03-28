<?php

declare(strict_types=1);

namespace App\Infrastructure\Container;

use App\Infrastructure\Container\Config\LazyLoadingConfig;
use App\Infrastructure\Container\Contracts\ContainerInterface;
use App\Infrastructure\Container\LazyLoading\LazyProxyGenerator;

class LazyLoadingServiceProvider extends ServiceProvider
{
    /**
     * Registriert Konfigurationen und Services für Lazy Loading
     *
     * @param ContainerInterface $container Der Dependency Injection Container
     */
    public function register(ContainerInterface $container): void
    {
        // Registriere die Standard-Lazy-Loading-Konfiguration
        $container->singleton(LazyLoadingConfig::class, function () {
            $config = new LazyLoadingConfig();

            // Statische Konfiguration ohne Umgebungsvariablen
            $config->autoDetectHeavyServices = true;
            $config->memoryThreshold = 1024 * 1024; // 1 MB
            $config->constructorParameterThreshold = 3;
            $config->executionTimeThreshold = 0.1; // 100 Millisekunden

            return $config;
        });

        // Registriere den Proxy-Generator als Singleton
        $container->singleton(LazyProxyGenerator::class, function (ContainerInterface $c) {
            return new LazyProxyGenerator($c);
        });
    }
}