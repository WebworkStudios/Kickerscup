<?php


declare(strict_types=1);

namespace App\Core\Database\Clauses;

/**
 * ORDER BY-Klausel für SQL-Abfragen
 */
class OrderByClause implements ClauseInterface
{
    /**
     * ORDER BY-Anweisungen
     */
    private array $orders = [];

    /**
     * Fügt eine ORDER BY-Klausel hinzu
     *
     * @param string $column Spalte
     * @param string $direction Richtung (ASC oder DESC)
     * @return self
     */
    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $direction = strtoupper($direction);

        if (!in_array($direction, ['ASC', 'DESC'])) {
            $direction = 'ASC';
        }

        $this->orders[] = [
            'column' => $column,
            'direction' => $direction,
        ];

        return $this;
    }

    /**
     * Fügt eine ORDER BY DESC-Klausel hinzu
     *
     * @param string $column Spalte
     * @return self
     */
    public function orderByDesc(string $column): self
    {
        return $this->orderBy($column, 'DESC');
    }

    /**
     * Prüft, ob ORDER BY-Anweisungen vorhanden sind
     *
     * @return bool
     */
    public function hasOrders(): bool
    {
        return !empty($this->orders);
    }

    /**
     * Entfernt alle ORDER BY-Anweisungen
     *
     * @return self
     */
    public function clearOrders(): self
    {
        $this->orders = [];
        return $this;
    }

    /**
     * Generiert die SQL für die ORDER BY-Klausel
     *
     * @return string
     */
    public function toSql(): string
    {
        if (empty($this->orders)) {
            return '';
        }

        $clauses = [];

        foreach ($this->orders as $order) {
            $clauses[] = "{$order['column']} {$order['direction']}";
        }

        return 'ORDER BY ' . implode(', ', $clauses);
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