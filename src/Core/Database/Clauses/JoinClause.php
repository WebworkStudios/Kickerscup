<?php


declare(strict_types=1);

namespace App\Core\Database\Clauses;

/**
 * JOIN-Klausel für SQL-Abfragen
 */
class JoinClause implements ClauseInterface
{
    /**
     * JOIN-Bedingungen
     */
    private array $joins = [];

    /**
     * Parameter für die Abfrage
     */
    private array $params = [];

    /**
     * Fügt eine JOIN-Klausel hinzu
     *
     * @param string $table Tabellenname
     * @param string $first Erste Spalte
     * @param string $operator Operator
     * @param string $second Zweite Spalte
     * @param string $type Typ des Joins (INNER, LEFT, RIGHT, etc.)
     * @return self
     */
    public function join(
        string $table,
        string $first,
        string $operator,
        string $second,
        string $type = 'INNER'
    ): self
    {
        $this->joins[] = [
            'table' => $table,
            'first' => $first,
            'operator' => $operator,
            'second' => $second,
            'type' => $type,
        ];

        return $this;
    }

    /**
     * Fügt eine INNER JOIN-Klausel hinzu
     *
     * @param string $table Tabellenname
     * @param string $first Erste Spalte
     * @param string $operator Operator
     * @param string $second Zweite Spalte
     * @return self
     */
    public function innerJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->join($table, $first, $operator, $second, 'INNER');
    }

    /**
     * Fügt eine LEFT JOIN-Klausel hinzu
     *
     * @param string $table Tabellenname
     * @param string $first Erste Spalte
     * @param string $operator Operator
     * @param string $second Zweite Spalte
     * @return self
     */
    public function leftJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->join($table, $first, $operator, $second, 'LEFT');
    }

    /**
     * Fügt eine RIGHT JOIN-Klausel hinzu
     *
     * @param string $table Tabellenname
     * @param string $first Erste Spalte
     * @param string $operator Operator
     * @param string $second Zweite Spalte
     * @return self
     */
    public function rightJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->join($table, $first, $operator, $second, 'RIGHT');
    }

    /**
     * Fügt eine FULL JOIN-Klausel hinzu
     *
     * @param string $table Tabellenname
     * @param string $first Erste Spalte
     * @param string $operator Operator
     * @param string $second Zweite Spalte
     * @return self
     */
    public function fullJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->join($table, $first, $operator, $second, 'FULL');
    }

    /**
     * Prüft, ob JOIN-Bedingungen vorhanden sind
     *
     * @return bool
     */
    public function hasJoins(): bool
    {
        return !empty($this->joins);
    }

    /**
     * Generiert die SQL für die JOIN-Klausel
     *
     * @return string
     */
    public function toSql(): string
    {
        if (empty($this->joins)) {
            return '';
        }

        $sql = [];

        foreach ($this->joins as $join) {
            $sql[] = sprintf(
                '%s JOIN %s ON %s %s %s',
                $join['type'],
                $join['table'],
                $join['first'],
                $join['operator'],
                $join['second']
            );
        }

        return implode(' ', $sql);
    }

    /**
     * Gibt alle Parameter für die Klausel zurück
     *
     * @return array
     */
    public function getBindings(): array
    {
        return $this->params;
    }
}