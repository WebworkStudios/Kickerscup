<?php


declare(strict_types=1);

namespace App\Infrastructure\Container;

use App\Infrastructure\Container\Contracts\ContainerInterface;
use App\Infrastructure\Container\Contracts\ServiceProviderInterface;

/**
 * Basisklasse für Service Provider
 */
abstract class ServiceProvider implements ServiceProviderInterface
{
    /**
     * Registriert Services im Container.
     *
     * @param ContainerInterface $container
     * @return void
     */
    abstract public function register(ContainerInterface $container): void;
}