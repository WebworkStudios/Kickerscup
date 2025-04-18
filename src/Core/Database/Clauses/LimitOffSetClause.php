<?php

declare(strict_types=1);

namespace App\Core\Database\Clauses;

/**
 * LIMIT/OFFSET-Klausel für SQL-Abfragen
 */
class LimitOffsetClause
{
    /**
     * Limit für die Abfrage
     */
    private ?int $limit = null;

    /**
     * Offset für die Abfrage
     */
    private ?int $offset = null;

    /**
     * Setzt das Limit für die Abfrage
     *
     * @param int|null $limit Limit
     * @return self
     */
    public function limit(?int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    /**
     * Setzt den Offset für die Abfrage
     *
     * @param int|null $offset Offset
     * @return self
     */
    public function offset(?int $offset): self
    {
        $this->offset = $offset;
        return $this;
    }

    /**
     * Kombiniert Limit und Offset für Paginierung
     *
     * @param int $page Seitennummer (beginnend bei 1)
     * @param int $perPage Einträge pro Seite
     * @return self
     */
    public function forPage(int $page, int $perPage): self
    {
        $this->limit = $perPage;
        $this->offset = ($page - 1) * $perPage;
        return $this;
    }

    /**
     * Generiert die SQL-Abfrage für die LIMIT/OFFSET-Klausel
     *
     * @return string
     */
    public function toSql(): string
    {
        $sql = '';

        if ($this->limit !== null) {
            $sql .= "LIMIT $this->limit";
        }

        if ($this->offset !== null) {
            $sql .= $this->limit !== null ? " OFFSET $this->offset" : "OFFSET $this->offset";
        }

        return $sql;
    }
}