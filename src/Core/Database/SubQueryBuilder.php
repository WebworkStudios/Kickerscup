<?php

declare(strict_types=1);

namespace App\Core\Database;

/**
 * Builder für Unterabfragen
 */
class SubQueryBuilder extends QueryBuilder
{
    /**
     * SQL für die Unterabfrage
     */
    private string $sql;

    /**
     * Parameter für die Unterabfrage
     */
    private array $bindings;

    /**
     * Konstruktor für eine Unterabfrage mit einem fertigen SQL-Statement
     *
     * @param Connection $connection Datenbankverbindung
     * @param string $sql SQL für die Unterabfrage
     * @param array $bindings Parameter für die Unterabfrage
     */
    public function __construct(Connection $connection, string $sql, array $bindings = [])
    {
        parent::__construct($connection, '');
        $this->sql = $sql;
        $this->bindings = $bindings;
    }

    /**
     * Gibt die SQL-Abfrage zurück
     *
     * @return string
     */
    public function toSql(): string
    {
        return $this->sql;
    }

    /**
     * Gibt die Parameter für die Abfrage zurück
     *
     * @return array
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }
}