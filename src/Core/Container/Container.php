<?php

declare(strict_types=1);

namespace App\Core\Container;

use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;

/**
 * Container für Dependency Injection
 *
 * Ein einfacher und performanter DI-Container
 */
class Container
{
    /**
     * Gespeicherte Instanzen (Singletons)
     */
    private array $instances = [];

    /**
     * Gespeicherte Bindungen
     */
    private array $bindings = [];

    /**
     * Bindet eine Implementierung an eine Schnittstelle
     *
     * @param string $abstract Abstrakte Klasse oder Interface
     * @param \Closure|string|null $concrete Konkrete Implementierung
     */
    public function bind(string $abstract, \Closure|string|null $concrete = null): void
    {
        // Wenn keine konkrete Implementierung angegeben wurde, die abstrakte Klasse selbst verwenden
        if ($concrete === null) {
            $concrete = $abstract;
        }

        $this->bindings[$abstract] = $concrete;
    }

    /**
     * Registriert eine Klasse als Singleton
     *
     * @param string $abstract Abstrakte Klasse oder Interface
     * @param \Closure|string|null $concrete Konkrete Implementierung
     */
    public function singleton(string $abstract, \Closure|string|mixed $concrete = null): void
    {
        // Wenn bereits ein Wert übergeben wurde, diesen direkt als Instanz registrieren
        if (!is_string($concrete) && !($concrete instanceof \Closure)) {
            $this->instances[$abstract] = $concrete;
            return;
        }

        // Wenn keine konkrete Implementierung angegeben wurde, die abstrakte Klasse selbst verwenden
        if ($concrete === null) {
            $concrete = $abstract;
        }

        // Als Singleton markieren, indem wir eine Closure mit der konkreten Implementierung speichern
        $this->bindings[$abstract] = function () use ($concrete) {
            if ($concrete instanceof \Closure) {
                return $concrete($this);
            }

            return $this->build($concrete);
        };

        // Als Singleton markieren
        $this->instances[$abstract] = null;
    }

    /**
     * Erstellt oder gibt eine Instanz einer Klasse zurück
     *
     * @param string $abstract Klasse, die erstellt werden soll
     * @param array $parameters Zusätzliche Parameter für den Konstruktor
     * @return mixed Instanz der Klasse
     */
    public function make(string $abstract, array $parameters = []): mixed
    {
        // Wenn es bereits eine Instanz gibt, diese zurückgeben
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        // Wenn es eine Bindung gibt, diese verwenden
        if (isset($this->bindings[$abstract])) {
            $concrete = $this->bindings[$abstract];

            // Wenn die Bindung eine Closure ist, diese ausführen
            if ($concrete instanceof \Closure) {
                $instance = $concrete($this, $parameters);

                // Wenn es sich um einen Singleton handelt, die Instanz speichern
                if (array_key_exists($abstract, $this->instances)) {
                    $this->instances[$abstract] = $instance;
                }

                return $instance;
            }

            // Sonst die konkrete Klasse verwenden
            $abstract = $concrete;
        }

        // Klasse erstellen
        $instance = $this->build($abstract, $parameters);

        // Wenn es sich um einen Singleton handelt, die Instanz speichern
        if (array_key_exists($abstract, $this->instances)) {
            $this->instances[$abstract] = $instance;
        }

        return $instance;
    }

    /**
     * Prüft, ob eine Bindung existiert
     */
    public function has(string $abstract): bool
    {
        return isset($this->bindings[$abstract]) || isset($this->instances[$abstract]);
    }

    /**
     * Baut eine Klasse mit ihren Abhängigkeiten auf
     */
    private function build(string $concrete, array $parameters = []): object
    {
        // Reflection-Informationen abrufen
        $reflector = new ReflectionClass($concrete);

        // Prüfen, ob die Klasse instanziierbar ist
        if (!$reflector->isInstantiable()) {
            throw new \Exception("Klasse {$concrete} ist nicht instanziierbar.");
        }

        // Konstruktor abrufen
        $constructor = $reflector->getConstructor();

        // Wenn es keinen Konstruktor gibt oder keine Parameter benötigt werden, einfach instanziieren
        if ($constructor === null || empty($constructor->getParameters())) {
            return new $concrete;
        }

        // Parameter für den Konstruktor sammeln
        $dependencies = [];

        foreach ($constructor->getParameters() as $parameter) {
            // Wenn der Parameter in den übergebenen Parametern enthalten ist, diesen verwenden
            $paramName = $parameter->getName();
            if (array_key_exists($paramName, $parameters)) {
                $dependencies[] = $parameters[$paramName];
                continue;
            }

            // Wenn der Parameter einen Typ hat, diesen auflösen
            $type = $parameter->getType();

            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $dependencies[] = $this->make($type->getName());
                continue;
            }

            // Wenn der Parameter optional ist, den Standardwert verwenden
            if ($parameter->isDefaultValueAvailable()) {
                $dependencies[] = $parameter->getDefaultValue();
                continue;
            }

            // Wenn wir hier ankommen, kann der Parameter nicht aufgelöst werden
            throw new \Exception("Parameter {$paramName} kann nicht aufgelöst werden.");
        }

        // Klasse mit den aufgelösten Abhängigkeiten instanziieren
        return $reflector->newInstanceArgs($dependencies);
    }
}