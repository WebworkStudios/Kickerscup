<?php
declare(strict_types=1);

namespace App\Infrastructure\Security\Csrf;

use App\Infrastructure\Container\Contracts\ContainerInterface;
use App\Infrastructure\Container\ServiceProvider;
use App\Infrastructure\Security\Csrf\Contracts\CsrfProtectionInterface;

class CsrfServiceProvider extends ServiceProvider
{
    /**
     * {@inheritdoc}
     */
    public function register(ContainerInterface $container): void
    {
        // Registriere die CSRF-Konfiguration
        $container->singleton(CsrfConfiguration::class, function () {
            // Hier können wir die Konfiguration aus einer Konfigurations-Datei laden
            return new CsrfConfiguration();
        });

        // Registriere das Interface auf die Implementierung
        $container->bind(CsrfProtectionInterface::class, CsrfProtection::class);

        // Registriere den Service als Singleton
        $container->singleton(CsrfProtection::class);
    }
}