<?php


declare(strict_types=1);

namespace App\Infrastructure\Container\LazyLoading;

use App\Infrastructure\Container\Contracts\ContainerInterface;

/**
 * Basis-Klasse für alle Lazy-Proxies
 * Verwendet magic methods für dynamisches Verhalten
 */
class LazyProxyBase
{
    private ?object $realInstance = null;

    public function __construct(
        private readonly ContainerInterface $container,
        private readonly string             $targetClass
    )
    {
    }

    /**
     * Leitet alle Methodenaufrufe an die echte Instanz weiter
     */
    public function __call(string $name, array $arguments): mixed
    {
        return $this->loadRealInstance()->$name(...$arguments);
    }

    /**
     * Lädt die echte Instanz, falls nötig
     */
    private function loadRealInstance(): object
    {
        if ($this->realInstance === null) {
            $this->realInstance = $this->container->get($this->targetClass);
        }

        return $this->realInstance;
    }

    /**
     * Leitet alle Property-Zugriffe an die echte Instanz weiter
     */
    public function __get(string $name): mixed
    {
        return $this->loadRealInstance()->$name;
    }

    /**
     * Leitet alle Property-Zuweisungen an die echte Instanz weiter
     */
    public function __set(string $name, mixed $value): void
    {
        $this->loadRealInstance()->$name = $value;
    }

    /**
     * Prüft, ob eine Property in der echten Instanz existiert
     */
    public function __isset(string $name): bool
    {
        return isset($this->loadRealInstance()->$name);
    }

    /**
     * Löscht eine Property in der echten Instanz
     */
    public function __unset(string $name): void
    {
        unset($this->loadRealInstance()->$name);
    }
}