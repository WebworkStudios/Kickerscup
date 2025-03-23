<?php
declare(strict_types=1);

namespace App\Infrastructure\Container\Contracts;

use App\Infrastructure\Container\Exceptions\ContainerException;
use App\Infrastructure\Container\Exceptions\NotFoundException;

/**
 * Container Interface
 */
interface ContainerInterface
{
    /**
     * Findet einen Eintrag des gegebenen Identifiers im Container.
     *
     * @param string $id Identifier des Eintrags zum Suchen.
     * @return mixed Eintrag.
     * @throws NotFoundException Kein Eintrag wurde für den gegebenen Identifier gefunden.
     * @throws ContainerException Fehler beim Auflösen des Eintrags.
     */
    public function get(string $id): mixed;

    /**
     * Überprüft, ob der Container einen Eintrag für den gegebenen Identifier hat.
     *
     * @param string $id Identifier des Eintrags zum Suchen.
     * @return bool
     */
    public function has(string $id): bool;

    /**
     * Registriert einen neuen Eintrag im Container.
     *
     * @param string $abstract Typ oder Identifier
     * @param mixed $concrete Konkrete Implementierung oder Factory
     * @return static
     */
    public function bind(string $abstract, mixed $concrete = null): static;

    /**
     * Registriert einen Singleton im Container.
     *
     * @param string $abstract Typ oder Identifier
     * @param mixed $concrete Konkrete Implementierung oder Factory
     * @return static
     */
    public function singleton(string $abstract, mixed $concrete = null): static;

    /**
     * Registriert einen Scoped Service im Container.
     *
     * @param string $abstract Typ oder Identifier
     * @param mixed $concrete Konkrete Implementierung oder Factory
     * @return static
     */
    public function scoped(string $abstract, mixed $concrete = null): static;

    /**
     * Registriert eine Factory im Container.
     *
     * @param string $abstract Typ oder Identifier
     * @param FactoryInterface $factory Factory zur Erstellung der Instanz
     * @return static
     */
    public function factory(string $abstract, FactoryInterface $factory): static;

    /**
     * Erstellt eine Instanz mit expliziten Parametern.
     *
     * @param string $abstract Typ oder Identifier
     * @param array $parameters Parameter für die Konstruktion
     * @return mixed
     */
    public function makeWith(string $abstract, array $parameters = []): mixed;
}