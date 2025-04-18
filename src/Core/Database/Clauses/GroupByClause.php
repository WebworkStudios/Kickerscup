<?php

declare(strict_types=1);

namespace App\Core\Database\Clauses;

/**
 * GROUP BY-Klausel für SQL-Abfragen
 */
class GroupByClause
{
    /**
     * Zu gruppierende Spalten
     */
    private array $columns = [];

    /**
     * Fügt eine GROUP BY-Klausel hinzu
     *
     * @param string ...$columns Spalten
     * @return void
     */
    public function groupBy(string ...$columns): void
    {
        $this->columns = array_merge($this->columns, $columns);
    }

    /**
     * Generiert die SQL-Abfrage für die GROUP BY-Klausel
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
}