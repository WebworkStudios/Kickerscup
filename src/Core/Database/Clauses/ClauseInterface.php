<?php

declare(strict_types=1);

namespace App\Core\Database\Clauses;

/**
 * Schnittstelle für SQL-Klauseln
 */
interface ClauseInterface
{
    /**
     * Generiert die SQL für diese Klausel
     *
     * @return string Die generierte SQL oder leer, wenn keine SQL generiert werden kann
     */
    public function toSql(): string;

    /**
     * Gibt alle Parameter für die Klausel zurück
     *
     * @return array
     */
    public function getBindings(): array;
}