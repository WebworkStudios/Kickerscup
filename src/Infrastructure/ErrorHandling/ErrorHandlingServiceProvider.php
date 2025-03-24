<?php

declare(strict_types=1);

namespace App\Infrastructure\ErrorHandling;

use App\Infrastructure\Container\Contracts\ContainerInterface;
use App\Infrastructure\Container\ServiceProvider;
use App\Infrastructure\ErrorHandling\Contracts\ExceptionHandlerInterface;

/**
 * Service Provider für Error-Handling-Komponenten
 */
class ErrorHandlingServiceProvider extends ServiceProvider
{
    /**
     * Registriert Error-Handling-Services im Container
     *
     * @param ContainerInterface $container
     * @return void
     */
    public function register(ContainerInterface $container): void
    {
        // Registriere das Interface auf die Implementierung
        $container->bind(ExceptionHandlerInterface::class, ExceptionHandler::class);

        // Registriere den Exception-Handler als Singleton
        $container->singleton(ExceptionHandler::class);
    }
}