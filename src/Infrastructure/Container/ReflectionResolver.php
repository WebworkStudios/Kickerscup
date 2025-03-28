<?php


declare(strict_types=1);

namespace App\Infrastructure\Container;

use App\Infrastructure\Container\Contracts\ContainerInterface;
use App\Infrastructure\Container\Exceptions\BindingResolutionException;
use App\Infrastructure\Container\Exceptions\ContainerException;
use App\Infrastructure\Container\Exceptions\NotFoundException;
use ReflectionClass;
use ReflectionException;

/**
 * Resolver für Dependency Injection mittels Reflection
 */
class ReflectionResolver
{
    /**
     * Der Container.
     */
    protected ContainerInterface $container;

    protected array $reflectionCache = [];
    protected array $parameterCache = [];


    /**
     * Konstruktor.
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * Löst einen Typ via Reflection auf.
     *
     * @param string|object $concrete Der konkrete Typ oder eine vorhandene Instanz
     * @param array $parameters Zusätzliche Parameter
     * @return object Die aufgelöste Instanz
     * @throws BindingResolutionException
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function resolve(string|object $concrete, array $parameters = []): object
    {
        // Wenn bereits eine Instanz übergeben wurde, gib diese direkt zurück
        if (is_object($concrete)) {
            return $concrete;
        }

        try {
            // Nutze gecachte Reflection wenn möglich
            if (!isset($this->reflectionCache[$concrete])) {
                $this->reflectionCache[$concrete] = new ReflectionClass($concrete);
            }

            $reflector = $this->reflectionCache[$concrete];

            // Prüfe, ob die Klasse instantiierbar ist
            if (!$reflector->isInstantiable()) {
                throw new BindingResolutionException("Typ $concrete ist nicht instantiierbar.");
            }

            // Hole den Konstruktor
            $constructor = $reflector->getConstructor();

            // Wenn es keinen Konstruktor gibt, instanziiere direkt
            if ($constructor === null) {
                return new $concrete();
            }

            // Cache key für Parameter
            $cacheKey = $concrete . ':' . md5(serialize($parameters));

            // Nutze gecachte Parameter wenn möglich
            if (!isset($this->parameterCache[$cacheKey])) {
                $this->parameterCache[$cacheKey] = $this->resolveDependencies(
                    $constructor->getParameters(),
                    $parameters
                );

                // Begrenze Cache-Größe
                if (count($this->parameterCache) > 100) {
                    // Entferne älteste Einträge
                    array_shift($this->parameterCache);
                }
            }

            // Erstelle die Instanz mit den aufgelösten Abhängigkeiten
            return $reflector->newInstanceArgs($this->parameterCache[$cacheKey]);
        } catch (ReflectionException $e) {
            throw new BindingResolutionException("Fehler beim Auflösen des Typs $concrete: " . $e->getMessage(), 0, $e);
        }
    }


    /**
     * @param array $parameters
     * @param array $primitives
     * @return array
     * @throws BindingResolutionException
     * @throws ContainerException
     * @throws NotFoundException
     */
    protected function resolveDependencies(array $parameters, array $primitives): array
    {
        $dependencies = [];
        $maxRecursionDepth = 50;

        static $recursionDepth = 0; // Zähler für Rekursionstiefe
        $recursionDepth++;

        // Prüfe auf zu tiefe Rekursion
        if ($recursionDepth > $maxRecursionDepth) {
            $recursionDepth--; // Reduziere den Zähler vor dem Werfen der Exception
            throw new BindingResolutionException(
                "Maximale Rekursionstiefe ($maxRecursionDepth) überschritten. Mögliche zirkuläre Abhängigkeit."
            );
        }

        try {
            foreach ($parameters as $parameter) {
                $paramName = $parameter->getName();

                // Verwende direkt array_key_exists statt isset für potentielle NULL-Werte
                if (array_key_exists($paramName, $primitives)) {
                    $dependencies[] = $primitives[$paramName];
                    continue;
                }

                $paramType = $parameter->getType();

                if ($paramType !== null && !$paramType->isBuiltin()) {
                    $typeName = $paramType->getName();
                    try {
                        $dependencies[] = $this->container->get($typeName);
                        continue;
                    } catch (BindingResolutionException $e) {
                        if ($parameter->isDefaultValueAvailable()) {
                            $dependencies[] = $parameter->getDefaultValue();
                            continue;
                        }
                        throw $e;
                    }
                }

                if ($parameter->isDefaultValueAvailable()) {
                    $dependencies[] = $parameter->getDefaultValue();
                    continue;
                }

                if ($paramType !== null && $paramType->allowsNull()) {
                    $dependencies[] = null;
                    continue;
                }

                throw new BindingResolutionException(
                    "Konnte Parameter $paramName für Klasse nicht auflösen."
                );
            }
        } finally {
            $recursionDepth--;
        }

        return $dependencies;
    }
}