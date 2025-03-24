<?php


declare(strict_types=1);

namespace App\Infrastructure\Database\Contracts;

use PDOStatement;

interface QueryBuilderInterface
{
    /**
     * Fügt Parameter für die Abfrage hinzu
     *
     * @param array $params Die hinzuzufügenden Parameter
     * @return static
     */
    public function addParameters(array $params): static;

    /**
     * Gibt die aktuellen Parameter zurück
     *
     * @return array
     */
    public function getParameters(): array;

    /**
     * Erstellt die SQL-Abfrage
     *
     * @return string Die generierte SQL-Abfrage
     */
    public function toSql(): string;

    /**
     * Führt die Abfrage aus
     *
     * @return PDOStatement
     */
    public function execute(): PDOStatement;
}