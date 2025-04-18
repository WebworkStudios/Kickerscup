<?php

declare(strict_types=1);

namespace App\Core\Database\Exceptions;

/**
 * Basisklasse für alle Datenbankausnahmen
 */
class DatabaseException extends \Exception
{
    /**
     * SQL-Abfrage, die den Fehler verursacht hat
     */
    protected ?string $query = null;

    /**
     * Parameter für die SQL-Abfrage
     */
    protected array $params = [];

    /**
     * Konstruktor
     *
     * @param string $message Fehlermeldung
     * @param int $code Fehlercode
     * @param \Throwable|null $previous Vorherige Ausnahme
     * @param string|null $query SQL-Abfrage
     * @param array $params Parameter für die SQL-Abfrage
     */
    public function __construct(
        string      $message,
        int         $code = 0,
        ?\Throwable $previous = null,
        ?string     $query = null,
        array       $params = []
    )
    {
        parent::__construct($message, $code, $previous);

        $this->query = $query;
        $this->params = $params;
    }

    /**
     * Gibt die SQL-Abfrage zurück
     */
    public function getQuery(): ?string
    {
        return $this->query;
    }

    /**
     * Gibt die Parameter für die SQL-Abfrage zurück
     */
    public function getParams(): array
    {
        return $this->params;
    }
}