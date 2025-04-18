<?php

declare(strict_types=1);

namespace App\Core\Database\Clauses;

/**
 * ORDER BY-Klausel für SQL-Abfragen
 */
class OrderByClause
{
    /**
     * Sortierungen
     */
    private array $orders = [];

    /**
     * Fügt eine ORDER BY DESC-Klausel hinzu
     *
     * @param string $column Spalte
     * @return void
     */
    public function orderByDesc(string $column): void
    {
        $this->orderBy($column, 'DESC');
    }

    /**
     * Fügt eine ORDER BY-Klausel hinzu
     *
     * @param string $column Spalte
     * @param string $direction Richtung (ASC oder DESC)
     * @return void
     */
    public function orderBy(string $column, string $direction = 'ASC'): void
    {
        $direction = strtoupper($direction);
        if (!in_array($direction, ['ASC', 'DESC'])) {
            $direction = 'ASC';
        }

        $this->orders[] = "$column $direction";
    }

    /**
     * Löscht alle Sortierungen
     *
     * @return void
     */
    public function clearOrders(): void
    {
        $this->orders = [];
    }

    /**
     * Generiert die SQL-Abfrage für die ORDER BY-Klausel
     *
     * @return string
     */
    public function toSql(): string
    {
        if (empty($this->orders)) {
            return '';
        }

        return 'ORDER BY ' . implode(', ', $this->orders);
    }
}