<?php

declare(strict_types=1);

namespace App\Infrastructure\Security\Csrf;

use App\Infrastructure\Container\Contracts\ContainerInterface;
use App\Infrastructure\Container\ServiceProvider;
use App\Infrastructure\Security\Csrf\Contracts\CsrfProtectionInterface;

/**
 * Service Provider für CSRF-Schutz
 */
class CsrfServiceProvider extends ServiceProvider
{
    /**
     * {@inheritdoc}
     */
    public function register(ContainerInterface $container): void
    {
        // Registriere das Interface auf die Implementierung
        $container->bind(CsrfProtectionInterface::class, CsrfProtection::class);

        // Registriere die Implementierung als Singleton
        $container->singleton(CsrfProtection::class);
    }
}