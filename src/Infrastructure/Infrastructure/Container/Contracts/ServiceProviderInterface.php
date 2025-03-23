<?php
declare(strict_types=1);

namespace App\Infrastructure\Container\Contracts;

/**
 * Service Provider Interface
 */
interface ServiceProviderInterface
{
    /**
     * Registriert Services im Container.
     *
     * @param ContainerInterface $container
     * @return void
     */
    public function register(ContainerInterface $container): void;
}