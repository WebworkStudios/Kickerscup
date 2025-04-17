<?php


declare(strict_types=1);

namespace App\Core\Database;

/**
 * SubQueryBuilder für SQL-Unterabfragen
 *
 * Diese Klasse ermöglicht die Erstellung von Unterabfragen, die in den
 * Hauptabfragen verwendet werden können.
 */
class SubQueryBuilder
{
    /**
     * Die QueryBuilder-Instanz
     */
    private QueryBuilder $query;

    /**
     * Der Alias für die Unterabfrage
     */
    private ?string $alias = null;

    /**
     * Konstruktor
     *
     * @param QueryBuilder $query Die QueryBuilder-Instanz
     * @param string|null $alias Der Alias für die Unterabfrage
     */
    public function __construct(QueryBuilder $query, ?string $alias = null)
    {
        $this->query = $query;
        $this->alias = $alias;
    }

    /**
     * Setzt den Alias für die Unterabfrage
     *
     * @param string $alias Der Alias
     * @return self
     */
    public function as(string $alias): self
    {
        $this->alias = $alias;
        return $this;
    }

    /**
     * Gibt die SQL-Abfrage zurück
     *
     * @return string
     */
    public function toSql(): string
    {
        $sql = '(' . $this->query->toSql() . ')';

        if ($this->alias !== null) {
            $sql .= ' AS ' . $this->alias;
        }

        return $sql;
    }

    /**
     * Gibt die Parameter für die Abfrage zurück
     *
     * @return array
     */
    public function getBindings(): array
    {
        return $this->query->getBindings();
    }

    /**
     * Gibt den QueryBuilder zurück
     *
     * @return QueryBuilder
     */
    public function getQuery(): QueryBuilder
    {
        return $this->query;
    }

    /**
     * Gibt den Alias zurück
     *
     * @return string|null
     */
    public function getAlias(): ?string
    {
        return $this->alias;
    }

    /**
     * String-Repräsentation der Unterabfrage
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->toSql();
    }
}