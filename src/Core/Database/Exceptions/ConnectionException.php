<?php

declare(strict_types=1);

namespace App\Core\Database\Exceptions;

use Exception;
use PDOException;

/**
 * Exception, die geworfen wird, wenn eine Verbindung zur Datenbank nicht hergestellt werden kann.
 */
class ConnectionException extends Exception
{
    /**
     * Die PDOException, die die ursprüngliche Fehlermeldung enthält.
     */
    private PDOException $pdoException;

    /**
     * Konstruktor
     *
     * @param string $message Fehlermeldung
     * @param int $code Fehlercode
     * @param PDOException $pdoException Die ursprüngliche PDOException
     */
    public function __construct(string $message, int $code, PDOException $pdoException)
    {
        parent::__construct($message, $code, $pdoException);
        $this->pdoException = $pdoException;
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
}