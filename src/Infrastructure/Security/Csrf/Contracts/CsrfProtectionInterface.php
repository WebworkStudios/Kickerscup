<?php


declare(strict_types=1);

namespace App\Infrastructure\Security\Csrf\Contracts;

interface CsrfProtectionInterface
{
    /**
     * Generiert ein CSRF-Token für den angegebenen Verwendungszweck
     *
     * @param string $key Schlüssel/Identifikator für das Token (z.B. Formular-ID oder Aktionsname)
     * @param int|null $lifetime Gültigkeitsdauer in Sekunden (null = kein Timeout)
     * @return string Das generierte Token
     */
    public function generateToken(string $key = 'default', ?int $lifetime = null): string;

    /**
     * Validiert ein CSRF-Token
     *
     * @param string $token Das zu validierende Token
     * @param string $key Schlüssel/Identifikator für das Token
     * @param bool $removeAfterValidation Ob das Token nach der Validierung entfernt werden soll
     * @return bool True wenn das Token gültig ist
     */
    public function validateToken(string $token, string $key = 'default', bool $removeAfterValidation = true): bool;

    /**
     * Generiert ein Token und HTML-Formularfeld
     *
     * @param string $key Schlüssel/Identifikator für das Token
     * @param int|null $lifetime Gültigkeitsdauer in Sekunden
     * @return string HTML-Input-Element mit dem Token
     */
    public function tokenField(string $key = 'default', ?int $lifetime = null): string;

    /**
     * Validiert den Ursprung einer Anfrage (Origin/Referer)
     *
     * @param string|array $allowedOrigins Erlaubte Ursprünge oder Muster
     * @return bool True wenn der Ursprung gültig ist
     */
    public function validateOrigin(string|array $allowedOrigins): bool;

    /**
     * Prüft, ob der aktuelle Request CSRF-geschützt werden sollte
     *
     * @return bool
     */
    public function shouldProtectRequest(): bool;
}