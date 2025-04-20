<?php
declare(strict_types=1);

namespace App\Core\Error;

/**
 * Exception für Nicht-gefunden-Fehler
 */
class NotFoundException extends ApiException
{
    /**
     * HTTP-Statuscode für Nicht-gefunden-Fehler
     */
    protected int $statusCode = 404;

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
        string      $message = 'Die angeforderte Ressource wurde nicht gefunden.',
        ?string     $errorCode = null,
        array       $details = [],
        ?int        $code = 0,
        ?\Throwable $previous = null
    )
    {
        parent::__construct($message, $errorCode ?? 'NOT_FOUND', $details, $code, $previous);
    }
}