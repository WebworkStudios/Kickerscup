<?php

declare(strict_types=1);

namespace App\Core\Error;

/**
 * Exception für fehlerhafte Anfragen
 */
class BadRequestException extends ApiException
{
    /**
     * HTTP-Statuscode für fehlerhafte Anfragen
     */
    protected int $statusCode = 400;

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
        string      $message = 'Ungültige Anfrage.',
        ?string     $errorCode = null,
        array       $details = [],
        ?int        $code = 0,
        ?\Throwable $previous = null
    )
    {
        parent::__construct($message, $errorCode ?? 'BAD_REQUEST', $details, $code, $previous);
    }
}