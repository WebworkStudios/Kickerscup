<?php


declare(strict_types=1);

namespace App\Infrastructure\Container;

use App\Infrastructure\Container\Contracts\ContainerInterface;
use App\Infrastructure\Container\Exceptions\BindingResolutionException;
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
     * @param string $concrete Der konkrete Typ
     * @param array $parameters Zusätzliche Parameter
     * @return object Die aufgelöste Instanz
     * @throws BindingResolutionException
     */
    public function resolve(string $concrete, array $parameters = []): object
    {
        try {
            $reflector = new ReflectionClass($concrete);

            // Prüfe, ob die Klasse instantiierbar ist
            if (!$reflector->isInstantiable()) {
                throw new BindingResolutionException("Typ {$concrete} ist nicht instantiierbar.");
            }

            // Hole den Konstruktor
            $constructor = $reflector->getConstructor();

            // Wenn es keinen Konstruktor gibt, instanziiere direkt
            if ($constructor === null) {
                return new $concrete();
            }

            // Löse die Konstruktor-Parameter auf
            $dependencies = $this->resolveDependencies($constructor->getParameters(), $parameters);

            // Erstelle die Instanz mit den aufgelösten Abhängigkeiten
            return $reflector->newInstanceArgs($dependencies);
        } catch (ReflectionException $e) {
            throw new BindingResolutionException("Fehler beim Auflösen des Typs {$concrete}: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Löst die Konstruktor-Parameter auf.
     *
     * @param ReflectionParameter[] $parameters Die Reflection-Parameter
     * @param array $primitives Zusätzliche primitive Parameter
     * @return array Die aufgelösten Parameter
     * @throws BindingResolutionException
     */
    protected function resolveDependencies(array $parameters, array $primitives): array
    {
        $dependencies = [];

        foreach ($parameters as $parameter) {
            // Wenn der Parameter in den übergebenen Parametern enthalten ist, verwende diesen
            $paramName = $parameter->getName();
            if (array_key_exists($paramName, $primitives)) {
                $dependencies[] = $primitives[$paramName];
                continue;
            }

            // Wenn der Parameter einen Typ hat und dieser ein Klassennname ist
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
                "Konnte Parameter {$paramName} für Klasse nicht auflösen."
            );
        }

        return $dependencies;
    }
}