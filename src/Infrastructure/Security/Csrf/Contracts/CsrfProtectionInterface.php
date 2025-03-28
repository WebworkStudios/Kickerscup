<?php

declare(strict_types=1);

namespace App\Infrastructure\Security\Csrf\Contracts;

use App\Infrastructure\Http\Contracts\RequestInterface;

/**
 * Interface für den CSRF-Schutz
 */
interface CsrfProtectionInterface
{
    /**
     * Generiert ein neues CSRF-Token
     *
     * @param string $key Optionaler Schlüssel zur Identifikation des Tokens
     * @return string Das generierte Token
     */
    public function generateToken(string $key = 'default'): string;

    /**
     * Validiert ein CSRF-Token
     *
     * @param string $token Das zu validierende Token
     * @param string $key Der Schlüssel des Tokens
     * @return bool True, wenn das Token gültig ist
     */
    public function validateToken(string $token, string $key = 'default'): bool;

    /**
     * Löscht ein CSRF-Token
     *
     * @param string $key Der Schlüssel des zu löschenden Tokens
     * @return bool True, wenn das Token gelöscht wurde
     */
    public function removeToken(string $key = 'default'): bool;

    /**
     * Prüft, ob ein Request durch CSRF geschützt werden sollte
     *
     * @param RequestInterface $request Der zu prüfende Request
     * @return bool True, wenn der Request geschützt werden sollte
     */
    public function shouldProtectRequest(RequestInterface $request): bool;

    /**
     * Validiert den Origin-Header des Requests
     *
     * @param array $allowedOrigins Erlaubte Origins (leer = nur die aktuelle Domain)
     * @return bool True, wenn der Origin gültig ist
     */
    public function validateOrigin(array $allowedOrigins = []): bool;

    /**
     * Gibt ein Input-Feld für das CSRF-Token zurück
     *
     * @param string $key Optionaler Schlüssel zur Identifikation des Tokens
     * @return string HTML-Input-Feld
     */
    public function getTokenField(string $key = 'default'): string;
}