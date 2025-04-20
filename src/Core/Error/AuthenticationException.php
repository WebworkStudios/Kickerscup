<?php
declare(strict_types=1);

namespace App\Core\Error;

/**
 * Exception für Authentifizierungsfehler
 */
class AuthenticationException extends ApiException
{
    /**
     * HTTP-Statuscode für Authentifizierungsfehler
     */
    protected int $statusCode = 401;

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
        string      $message = 'Nicht authentifiziert. Bitte melden Sie sich an.',
        ?string     $errorCode = null,
        array       $details = [],
        ?int        $code = 0,
        ?\Throwable $previous = null
    )
    {
        parent::__construct($message, $errorCode ?? 'UNAUTHENTICATED', $details, $code, $previous);
    }
}