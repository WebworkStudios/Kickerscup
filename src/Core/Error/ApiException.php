<?php


declare(strict_types=1);

namespace App\Core\Error;

use Exception;

/**
 * Basis-Exception für alle API-Fehler
 */
abstract class ApiException extends Exception
{
    /**
     * Fehlercode für API-Antworten
     */
    protected string $errorCode;

    /**
     * HTTP-Statuscode
     */
    protected int $statusCode;

    /**
     * Zusätzliche Fehlerdetails
     */
    protected array $details = [];

    /**
     * Konstruktor
     *
     * @param string $message Fehlermeldung
     * @param string|null $errorCode Fehlercode (optional)
     * @param array $details Fehlerdetails (optional)
     * @param int|null $code PHP-interner Fehlercode (optional)
     * @param \Throwable|null $previous Vorangegangene Exception (optional)
     */
    public function __construct(
        string      $message,
        ?string     $errorCode = null,
        array       $details = [],
        ?int        $code = 0,
        ?\Throwable $previous = null
    )
    {
        parent::__construct($message, $code ?? 0, $previous);

        $this->errorCode = $errorCode ?? $this->getDefaultErrorCode();
        $this->details = $details;
    }

    /**
     * Gibt den Fehlercode zurück
     *
     * @return string
     */
    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * Gibt den HTTP-Statuscode zurück
     *
     * @return int
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Gibt zusätzliche Fehlerdetails zurück
     *
     * @return array
     */
    public function getDetails(): array
    {
        return $this->details;
    }

    /**
     * Erstellt einen Standardfehlercode aus dem Klassennamen
     *
     * @return string
     */
    private function getDefaultErrorCode(): string
    {
        $className = basename(str_replace('\\', '/', get_class($this)));
        $className = str_replace('Exception', '', $className);

        // Camel-Case zu Underscore-Case umwandeln
        return strtoupper(preg_replace('/(?<!^)[A-Z]/', '_$0', $className));
    }
}