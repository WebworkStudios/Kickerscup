<?php

declare(strict_types=1);

namespace App\Core\Security;

/**
 * CSRF-Schutz
 */
class Csrf
{
    /**
     * Session-Key für CSRF-Token
     */
    private const SESSION_KEY = 'csrf_token';

    /**
     * Generiert ein CSRF-Token und speichert es in der Session
     *
     * @return string
     */
    public function generateToken(): string
    {
        $token = bin2hex(random_bytes(32));

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $_SESSION[self::SESSION_KEY] = $token;

        return $token;
    }

    /**
     * Gibt das aktuelle CSRF-Token zurück
     *
     * @return string|null
     */
    public function getToken(): ?string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        return $_SESSION[self::SESSION_KEY] ?? null;
    }

    /**
     * Validiert ein CSRF-Token
     *
     * @param string $token CSRF-Token
     * @return bool True, wenn gültig, sonst false
     */
    public function validateToken(string $token): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $storedToken = $_SESSION[self::SESSION_KEY] ?? null;

        if ($storedToken === null) {
            return false;
        }

        return hash_equals($storedToken, $token);
    }

    /**
     * Generiert ein HTML-Formularfeld für das CSRF-Token
     *
     * @return string HTML
     */
    public function formField(): string
    {
        $token = $this->getToken() ?? $this->generateToken();

        return sprintf(
            '<input type="hidden" name="csrf_token" value="%s">',
            htmlspecialchars($token, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        );
    }

    /**
     * Generiert ein Meta-Tag für das CSRF-Token (für JS-Anfragen)
     *
     * @return string HTML
     */
    public function metaTag(): string
    {
        $token = $this->getToken() ?? $this->generateToken();

        return sprintf(
            '<meta name="csrf-token" content="%s">',
            htmlspecialchars($token, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        );
    }
}