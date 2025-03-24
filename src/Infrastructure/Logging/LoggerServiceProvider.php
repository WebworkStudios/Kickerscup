<?php


declare(strict_types=1);

namespace App\Infrastructure\Logging;

use App\Infrastructure\Container\Contracts\ContainerInterface;
use App\Infrastructure\Container\ServiceProvider;
use App\Infrastructure\Logging\Contracts\LoggerInterface;

/**
 * Service Provider für Logger
 */
class LoggerServiceProvider extends ServiceProvider
{
    /**
     * {@inheritdoc}
     */
    public function register(ContainerInterface $container): void
    {
        // Registriere die Konfiguration
        $container->singleton(LoggerConfiguration::class, function () {
            // Hier können wir die Konfiguration aus einer Konfigurations-Datei laden
            // Für jetzt verwenden wir die Standardkonfiguration
            return new LoggerConfiguration();
        });

        // Registriere das Interface auf die Implementierung
        $container->bind(LoggerInterface::class, FileLogger::class);

        // Registriere den Logger als Singleton
        $container->singleton(FileLogger::class);
    }
}