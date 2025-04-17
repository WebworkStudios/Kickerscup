<?php


declare(strict_types=1);

namespace App\Core\Database\Clauses;

/**
 * LIMIT und OFFSET-Klausel für SQL-Abfragen
 */
class LimitOffsetClause implements ClauseInterface
{
    /**
     * LIMIT-Wert
     */
    private ?int $limit = null;

    /**
     * OFFSET-Wert
     */
    private ?int $offset = null;

    /**
     * Setzt den LIMIT-Wert
     *
     * @param int|null $limit Limit oder null für kein Limit
     * @return self
     */
    public function limit(?int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    /**
     * Setzt den OFFSET-Wert
     *
     * @param int|null $offset Offset oder null für kein Offset
     * @return self
     */
    public function offset(?int $offset): self
    {
        $this->offset = $offset;
        return $this;
    }

    /**
     * Kombiniert LIMIT und OFFSET für Paginierung
     *
     * @param int $page Seite (ab 1)
     * @param int $perPage Einträge pro Seite
     * @return self
     */
    public function forPage(int $page, int $perPage): self
    {
        return $this->offset(($page - 1) * $perPage)->limit($perPage);
    }

    /**
     * Prüft, ob LIMIT oder OFFSET gesetzt ist
     *
     * @return bool
     */
    public function hasLimitOrOffset(): bool
    {
        return $this->limit !== null || $this->offset !== null;
    }

    /**
     * Generiert die SQL für die LIMIT/OFFSET-Klausel
     *
     * @return string
     */
    public function toSql(): string
    {
        $sql = '';

        if ($this->limit !== null) {
            $sql .= "LIMIT {$this->limit}";
        }

        if ($this->offset !== null) {
            $sql .= $this->limit !== null ? " OFFSET {$this->offset}" : "OFFSET {$this->offset}";
        }

        return $sql;
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