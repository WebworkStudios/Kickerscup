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
     * Alias für die Unterabfrage
     */
    private ?string $alias = null;

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
     * Alternative Konstruktormethode für die Erstellung aus QueryBuilder
     *
     * @param QueryBuilder $query Der QueryBuilder, der als Unterabfrage verwendet wird
     * @param string|null $alias Optionaler Alias für die Unterabfrage
     * @return self
     */
    public static function fromQueryBuilder(QueryBuilder $query, ?string $alias = null): self
    {
        $instance = new self($query->getConnection(), $query->toSql(), $query->getBindings());
        $instance->alias = $alias;
        return $instance;
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

    /**
     * Gibt den Alias der Unterabfrage zurück
     *
     * @return string|null
     */
    public function getAlias(): ?string
    {
        return $this->alias;
    }

    /**
     * Setzt den Alias der Unterabfrage
     *
     * @param string|null $alias Der Alias
     * @return self
     */
    public function as(?string $alias): self
    {
        $this->alias = $alias;
        return $this;
    }
}