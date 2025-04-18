<?php

declare(strict_types=1);

namespace App\Core\Database\Exceptions;

use PDOException;

/**
 * Exception, die geworfen wird, wenn eine SQL-Query fehlschlägt.
 */
class QueryException extends \Exception
{
    /**
     * Die PDOException, die die ursprüngliche Fehlermeldung enthält.
     */
    private PDOException $pdoException;

    /**
     * Die SQL-Query, die den Fehler verursacht hat.
     */
    private string $query;

    /**
     * Die Parameter, die für die Query verwendet wurden.
     */
    private array $params;

    /**
     * Konstruktor
     *
     * @param string $message Fehlermeldung
     * @param int $code Fehlercode
     * @param PDOException $pdoException Die ursprüngliche PDOException
     * @param string $query Die SQL-Query, die den Fehler verursacht hat
     * @param array $params Die Parameter, die für die Query verwendet wurden
     */
    public function __construct(
        string $message,
        int $code,
        PDOException $pdoException,
        string $query,
        array $params = []
    ) {
        parent::__construct($message, $code, $pdoException);
        $this->pdoException = $pdoException;
        $this->query = $query;
        $this->params = $params;
    }

    /**
     * Gibt die ursprüngliche PDOException zurück
     *
     * @return PDOException
     */
    public function getPdoException(): PDOException
    {
        return $this->pdoException;
    }

    /**
     * Gibt die SQL-Query zurück, die den Fehler verursacht hat
     *
     * @return string
     */
    public function getQuery(): string
    {
        return $this->query;
    }

    /**
     * Gibt die Parameter zurück, die für die Query verwendet wurden
     *
     * @return array
     */
    public function getParams(): array
    {
        return $this->params;
    }
}