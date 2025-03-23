<?php


declare(strict_types=1);

namespace App\Infrastructure\Container;

use App\Infrastructure\Container\Contracts\ContainerInterface;
use App\Infrastructure\Container\Contracts\FactoryInterface;
use App\Infrastructure\Container\Exceptions\ContainerException;
use App\Infrastructure\Container\Exceptions\NotFoundException;
use Closure;

/**
 * Container-Implementierung
 */
class Container implements ContainerInterface
{
    /**
     * Die registrierten Typ-Bindungen.
     *
     * @var array<string, array{concrete: mixed, shared: bool, scoped: bool}>
     */
    protected array $bindings = [];

    /**
     * Die registrierten Factories.
     *
     * @var array<string, FactoryInterface>
     */
    protected array $factories = [];

    /**
     * Die gemerkten Singleton-Instanzen.
     *
     * @var array<string, mixed>
     */
    protected array $instances = [];

    /**
     * Die gemerkten Scoped-Instanzen.
     *
     * @var array<string, array<string, mixed>>
     */
    protected array $scopedInstances = [];

    /**
     * Die aktuelle Scope-ID.
     */
    protected string $currentScopeId = 'default';

    /**
     * Der Reflection-Resolver.
     */
    protected ReflectionResolver $reflectionResolver;

    /**
     * Container-Konstruktor.
     */
    public function __construct()
    {
        $this->reflectionResolver = new ReflectionResolver($this);

        // Registriere den Container selbst als Singleton
        $this->instances[ContainerInterface::class] = $this;
        $this->instances[self::class] = $this;
    }

    /**
     * {@inheritdoc}
     */
    public function bind(string $abstract, mixed $concrete = null): static
    {
        // Wenn keine konkrete Implementierung angegeben ist, verwende den abstrakten Typ
        $concrete = $concrete ?? $abstract;

        // Speichere die Bindung
        $this->bindings[$abstract] = [
            'concrete' => $concrete,
            'shared' => false,
            'scoped' => false,
        ];

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function singleton(string $abstract, mixed $concrete = null): static
    {
        $concrete = $concrete ?? $abstract;

        $this->bindings[$abstract] = [
            'concrete' => $concrete,
            'shared' => true,
            'scoped' => false,
        ];

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function scoped(string $abstract, mixed $concrete = null): static
    {
        $concrete = $concrete ?? $abstract;

        $this->bindings[$abstract] = [
            'concrete' => $concrete,
            'shared' => false,
            'scoped' => true,
        ];

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function factory(string $abstract, FactoryInterface $factory): static
    {
        $this->factories[$abstract] = $factory;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $id): mixed
    {
        return $this->resolve($id);
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $id): bool
    {
        return isset($this->bindings[$id]) || isset($this->instances[$id]) || isset($this->factories[$id]);
    }

    /**
     * {@inheritdoc}
     * @throws ContainerException
     */
    public function makeWith(string $abstract, array $parameters = []): mixed
    {
        return $this->resolve($abstract, $parameters);
    }

    /**
     * Setze die aktuelle Scope-ID.
     */
    public function setScopeId(string $scopeId): void
    {
        $this->currentScopeId = $scopeId;
    }

    /**
     * Löst einen Typ auf und gibt eine Instanz zurück.
     *
     * @param string $abstract
     * @param array $parameters
     * @return mixed
     * @throws ContainerException
     */
    protected function resolve(string $abstract, array $parameters = []): mixed
    {
        // 1. Prüfe auf vorhandene Singleton-Instanz
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        // 2. Prüfe auf Scoped-Instanz
        if (
            isset($this->bindings[$abstract]['scoped']) &&
            $this->bindings[$abstract]['scoped'] &&
            isset($this->scopedInstances[$this->currentScopeId][$abstract])
        ) {
            return $this->scopedInstances[$this->currentScopeId][$abstract];
        }

        // 3. Prüfe auf registrierte Factory
        if (isset($this->factories[$abstract])) {
            return $this->factories[$abstract]->make($this, $parameters);
        }

        // 4. Prüfe auf Bindung
        if (!isset($this->bindings[$abstract])) {
            // Wenn keine Bindung existiert, versuche, den Typ direkt aufzulösen
            if (class_exists($abstract)) {
                return $this->reflectionResolver->resolve($abstract, $parameters);
            }

            throw new NotFoundException("Typ $abstract konnte nicht gefunden werden.");
        }

        $concrete = $this->bindings[$abstract]['concrete'];

        // Wenn die konkrete Implementierung ein Closure ist, führe es aus
        if ($concrete instanceof Closure) {
            $instance = $concrete($this, $parameters);
        } elseif (is_string($concrete) && $concrete !== $abstract) {
            // Wenn die konkrete Implementierung ein anderer Typ ist, löse diesen rekursiv auf
            $instance = $this->resolve($concrete, $parameters);
        } else {
            // Sonst versuche, den Typ via Reflection aufzulösen
            $instance = $this->reflectionResolver->resolve($concrete, $parameters);
        }

        // Speichere Singleton-Instanzen
        if (isset($this->bindings[$abstract]['shared']) && $this->bindings[$abstract]['shared']) {
            $this->instances[$abstract] = $instance;
        }

        // Speichere Scoped-Instanzen
        if (isset($this->bindings[$abstract]['scoped']) && $this->bindings[$abstract]['scoped']) {
            if (!isset($this->scopedInstances[$this->currentScopeId])) {
                $this->scopedInstances[$this->currentScopeId] = [];
            }
            $this->scopedInstances[$this->currentScopeId][$abstract] = $instance;
        }

        return $instance;
    }
}