<?php
declare(strict_types=1);

namespace App\Infrastructure\Container\Contracts;

/**
 * Factory Interface
 */
interface FactoryInterface
{
    /**
     * Erstellt eine neue Instanz des Services.
     *
     * @param ContainerInterface $container Der Container für Dependency-Auflösung
     * @param array $parameters Zusätzliche Parameter für die Erstellung
     * @return mixed Die erstellte Instanz
     */
    public function make(ContainerInterface $container, array $parameters = []): mixed;
}