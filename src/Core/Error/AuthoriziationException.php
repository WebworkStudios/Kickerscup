<?php
declare(strict_types=1);

namespace App\Core\Error;

/**
 * Exception für Autorisierungsfehler
 */
class AuthoriziationException extends ApiException
{
    /**
     * HTTP-Statuscode für Autorisierungsfehler
     */
    protected int $statusCode = 403;

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
        string      $message = 'Zugriff verweigert. Sie haben nicht die erforderlichen Berechtigungen.',
        ?string     $errorCode = null,
        array       $details = [],
        ?int        $code = 0,
        ?\Throwable $previous = null
    )
    {
        parent::__construct($message, $errorCode ?? 'FORBIDDEN', $details, $code, $previous);
    }
}