<?php
declare(strict_types=1);

namespace App\Core\Error;

/**
 * Exception für Validierungsfehler
 */
class ValidationException extends ApiException
{
    /**
     * HTTP-Statuscode für Validierungsfehler
     */
    protected int $statusCode = 422;

    /**
     * Validierungsfehler
     */
    private array $errors;

    /**
     * Konstruktor
     *
     * @param string $message Fehlermeldung
     * @param array $errors Validierungsfehler
     * @param string|null $errorCode Fehlercode (optional)
     * @param int|null $code PHP-interner Fehlercode (optional)
     * @param \Throwable|null $previous Vorangegangene Exception (optional)
     */
    public function __construct(
        string      $message,
        array       $errors = [],
        ?string     $errorCode = null,
        ?int        $code = 0,
        ?\Throwable $previous = null
    )
    {
        parent::__construct($message, $errorCode ?? 'VALIDATION_ERROR', [], $code, $previous);

        $this->errors = $errors;
    }

    /**
     * Gibt die Validierungsfehler zurück
     *
     * @return array
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}