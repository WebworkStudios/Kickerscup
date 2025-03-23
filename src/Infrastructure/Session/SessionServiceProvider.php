<?php

declare(strict_types=1);

namespace App\Infrastructure\Session;

use App\Infrastructure\Container\Contracts\ContainerInterface;
use App\Infrastructure\Container\ServiceProvider;
use App\Infrastructure\Session\Contracts\FlashMessageInterface;
use App\Infrastructure\Session\Contracts\SessionInterface;

class SessionServiceProvider extends ServiceProvider
{
    /**
     * {@inheritdoc}
     */
    public function register(ContainerInterface $container): void
    {
        // Registriere die Session-Konfiguration
        $container->singleton(SessionConfiguration::class, function () {
            // Hier können wir die Konfiguration aus einer Konfigurations-Datei laden
            // Für jetzt verwenden wir die Standard-Konfiguration
            return new SessionConfiguration();
        });

        // Registriere die Interfaces
        $container->bind(SessionInterface::class, Session::class);
        $container->bind(FlashMessageInterface::class, FlashMessage::class);

        // Registriere die Klassen als Singletons
        $container->singleton(Session::class);
        $container->singleton(FlashMessage::class);
    }
}