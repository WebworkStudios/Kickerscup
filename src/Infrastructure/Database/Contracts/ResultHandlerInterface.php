<?php

declare(strict_types=1);

namespace App\Infrastructure\Database\Contracts;

use PDOStatement;

interface ResultHandlerInterface
{
    /**
     * Wandelt eine Abfrageergebnis-Zeile in ein Objekt um
     *
     * @template T
     * @param array $row Die Zeile aus dem Abfrageergebnis
     * @param class-string<T> $className Die Zielklasse
     * @return T Das erstellte Objekt
     */
    public function hydrateObject(array $row, string $className): object;

    /**
     * Wandelt ein PDOStatement in eine Liste von Objekten um
     *
     * @template T
     * @param PDOStatement $statement Das PDOStatement
     * @param class-string<T> $className Die Zielklasse
     * @return array<T> Die Liste der erstellten Objekte
     */
    public function hydrateObjects(PDOStatement $statement, string $className): array;
}