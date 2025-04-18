<?php

declare(strict_types=1);

namespace App\Core\Database\Clauses;

/**
 * JOIN-Klausel für SQL-Abfragen
 */
class JoinClause
{
    /**
     * Joins
     */
    private array $joins = [];

    /**
     * Parameter für die Joins
     */
    private array $bindings = [];

    /**
     * Aktuelle Bindungs-ID
     */
    private int $bindingId = 0;

    /**
     * Fügt einen JOIN hinzu
     *
     * @param string $table Tabellenname
     * @param string $first Erste Spalte
     * @param string $operator Operator
     * @param string $second Zweite Spalte
     * @param string $type Typ des Joins (INNER, LEFT, RIGHT, etc.)
     * @return void
     */
    public function join(
        string $table,
        string $first,
        string $operator,
        string $second,
        string $type = 'INNER'
    ): void {
        $this->joins[] = "$type JOIN $table ON $first $operator $second";
    }

    /**
     * Fügt einen LEFT JOIN hinzu
     *
     * @param string $table Tabellenname
     * @param string $first Erste Spalte
     * @param string $operator Operator
     * @param string $second Zweite Spalte
     * @return void
     */
    public function leftJoin(string $table, string $first, string $operator, string $second): void
    {
        $this->join($table, $first, $operator, $second, 'LEFT');
    }

    /**
     * Fügt einen RIGHT JOIN hinzu
     *
     * @param string $table Tabellenname
     * @param string $first Erste Spalte
     * @param string $operator Operator
     * @param string $second Zweite Spalte
     * @return void
     */
    public function rightJoin(string $table, string $first, string $operator, string $second): void
    {
        $this->join($table, $first, $operator, $second, 'RIGHT');
    }

    /**
     * Generiert die SQL-Abfrage für die JOIN-Klausel
     *
     * @return string
     */
    public function toSql(): string
    {
        if (empty($this->joins)) {
            return '';
        }

        return implode(' ', $this->joins);
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