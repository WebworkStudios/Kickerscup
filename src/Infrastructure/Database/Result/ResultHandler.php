<?php


declare(strict_types=1);

namespace App\Infrastructure\Database\Result;

use App\Infrastructure\Container\Contracts\ContainerInterface;
use App\Infrastructure\Database\Contracts\ResultHandlerInterface;
use PDOStatement;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;
use Throwable;

class ResultHandler implements ResultHandlerInterface
{
    public function __construct(
        private readonly ContainerInterface $container
    )
    {
    }

    /**
     * Wandelt eine Abfrageergebnis-Zeile in ein Objekt um
     *
     * @template T
     * @param array $row Die Zeile aus dem Abfrageergebnis
     * @param class-string<T> $className Die Zielklasse
     * @return T Das erstellte Objekt
     * @throws ReflectionException
     */
    public function hydrateObject(array $row, string $className): object
    {
        try {
            // Prüfen, ob die Klasse existiert
            if (!class_exists($className)) {
                throw new ReflectionException("Class {$className} does not exist");
            }

            // Reflection-Objekt für die Klasse erstellen
            $reflector = new ReflectionClass($className);

            // Neues Objekt erstellen
            $instance = $reflector->newInstanceWithoutConstructor();

            // Properties iterieren und Werte setzen
            foreach ($row as $column => $value) {
                // Spalte in CamelCase konvertieren (z.B. user_id -> userId)
                $property = $this->snakeCaseToCamelCase($column);

                // Prüfen, ob die Property existiert
                if ($reflector->hasProperty($property)) {
                    $prop = $reflector->getProperty($property);

                    // Nur setzen, wenn die Property zugänglich ist
                    if ($prop->isPublic()) {
                        $instance->$property = $this->castValue($value, $prop);
                    } else {
                        // Private/Protected Property über Reflection setzen
                        $prop->setAccessible(true);
                        $prop->setValue($instance, $this->castValue($value, $prop));
                    }
                }
            }

            return $instance;
        } catch (Throwable $e) {
            throw new ReflectionException(
                "Error hydrating object of class {$className}: " . $e->getMessage(),
                previous: $e
            );
        }
    }

    /**
     * Wandelt ein PDOStatement in eine Liste von Objekten um
     *
     * @template T
     * @param PDOStatement $statement Das PDOStatement
     * @param class-string<T> $className Die Zielklasse
     * @return array<T> Die Liste der erstellten Objekte
     */
    public function hydrateObjects(PDOStatement $statement, string $className): array
    {
        $results = [];

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $results[] = $this->hydrateObject($row, $className);
        }

        return $results;
    }

    /**
     * Wandelt einen Wert in den Typ der Property um
     *
     * @param mixed $value Der umzuwandelnde Wert
     * @param ReflectionProperty $property Die Property
     * @return mixed Der umgewandelte Wert
     */
    protected function castValue(mixed $value, ReflectionProperty $property): mixed
    {
        if ($value === null) {
            return null;
        }

        // Ab PHP 8.0 können wir den Typ der Property abrufen
        $type = $property->getType();

        if ($type === null) {
            return $value;
        }

        // Typname abrufen
        $typeName = $type->getName();

        return match ($typeName) {
            'int', 'integer' => (int)$value,
            'float', 'double' => (float)$value,
            'bool', 'boolean' => (bool)$value,
            'string' => (string)$value,
            'array' => is_string($value) ? json_decode($value, true) : (array)$value,
            'DateTime', '\DateTime' => is_string($value) ? new \DateTime($value) : $value,
            'DateTimeImmutable', '\DateTimeImmutable' => is_string($value) ? new \DateTimeImmutable($value) : $value,
            default => $value
        };
    }

    /**
     * Wandelt einen Snake Case String in CamelCase um
     *
     * @param string $input Der umzuwandelnde String
     * @return string Der umgewandelte String
     */
    protected function snakeCaseToCamelCase(string $input): string
    {
        return lcfirst(str_replace('_', '', ucwords($input, '_')));
    }
}