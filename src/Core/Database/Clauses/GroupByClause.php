<?php


declare(strict_types=1);

namespace App\Core\Database\Clauses;

/**
 * GROUP BY-Klausel für SQL-Abfragen
 */
class GroupByClause implements ClauseInterface
{
    /**
     * GROUP BY-Spalten
     */
    private array $columns = [];

    /**
     * Fügt eine GROUP BY-Klausel hinzu
     *
     * @param string ...$columns Spalten
     * @return self
     */
    public function groupBy(string ...$columns): self
    {
        $this->columns = array_merge($this->columns, $columns);
        return $this;
    }

    /**
     * Prüft, ob GROUP BY-Spalten vorhanden sind
     *
     * @return bool
     */
    public function hasColumns(): bool
    {
        return !empty($this->columns);
    }

    /**
     * Generiert die SQL für die GROUP BY-Klausel
     *
     * @return string
     */
    public function toSql(): string
    {
        if (empty($this->columns)) {
            return '';
        }

        return 'GROUP BY ' . implode(', ', $this->columns);
    }

    /**
     * Gibt alle Parameter für die Klausel zurück
     *
     * @return array
     */
    public function getBindings(): array
    {
        return [];
    }
}