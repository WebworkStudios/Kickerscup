<?php


declare(strict_types=1);

namespace App\Infrastructure\Container;

use App\Infrastructure\Container\Contracts\ContainerInterface;
use App\Infrastructure\Container\Exceptions\BindingResolutionException;
use App\Infrastructure\Container\Exceptions\ContainerException;
use App\Infrastructure\Container\Exceptions\NotFoundException;
use ReflectionClass;
use ReflectionException;
use ReflectionParameter;

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

    protected function resolveDependencies(array $parameters, array $primitives): array
    {
        $dependencies = [];
        $maxRecursionDepth = 50; // Füge eine maximale Rekursionstiefe hinzu

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
                // Wenn der Parameter in den übergebenen Parametern enthalten ist, verwende diesen
                $paramName = $parameter->getName();
                if (array_key_exists($paramName, $primitives)) {
                    $dependencies[] = $primitives[$paramName];
                    continue;
                }

                // Wenn der Parameter einen Typ hat und dieser ein Klassenname ist
                $paramType = $parameter->getType();

                if ($paramType !== null && !$paramType->isBuiltin()) {
                    $typeName = $paramType->getName();
                    // Löse die Abhängigkeit über den Container auf
                    try {
                        $dependencies[] = $this->container->get($typeName);
                        continue;
                    } catch (BindingResolutionException $e) {
                        // Wenn die Abhängigkeit nicht aufgelöst werden kann und der Parameter optional ist
                        if ($parameter->isDefaultValueAvailable()) {
                            $dependencies[] = $parameter->getDefaultValue();
                            continue;
                        }

                        throw $e;
                    }
                }

                // Wenn der Parameter einen Standardwert hat
                if ($parameter->isDefaultValueAvailable()) {
                    $dependencies[] = $parameter->getDefaultValue();
                    continue;
                }

                // Wenn nichts davon zutrifft und der Parameter erlaubt null
                if ($paramType !== null && $paramType->allowsNull()) {
                    $dependencies[] = null;
                    continue;
                }

                // Sonst können wir den Parameter nicht auflösen
                throw new BindingResolutionException(
                    "Konnte Parameter $paramName für Klasse nicht auflösen."
                );
            }
        } finally {
            // Stelle sicher, dass die Rekursionstiefe in jedem Fall reduziert wird
            $recursionDepth--;
        }

        return $dependencies;
    }
}