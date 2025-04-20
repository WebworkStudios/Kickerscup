<?php

declare(strict_types=1);

namespace App\Core\Security;

/**
 * API-Token-Sicherheitsklasse für REST APIs
 *
 * Diese Klasse ersetzt den klassischen CSRF-Schutz durch einen
 * für APIs optimierten Token-basierten Sicherheitsmechanismus.
 */
class Csrf
{
    /**
     * Session-Keys für API-Tokens
     */
    private const SESSION_KEY_API_TOKENS = 'api_tokens';

    /**
     * Standard Token-Lebensdauer in Sekunden
     */
    private const DEFAULT_TOKEN_LIFETIME = 7200; // 2 Stunden

    /**
     * Session-Management
     */
    private Session $session;

    /**
     * Token-Lebensdauer
     */
    private int $tokenLifetime;

    /**
     * Konstruktor
     *
     * @param Session $session Session-Management
     * @param int|null $tokenLifetime Optionale benutzerdefinierte Token-Lebensdauer
     */
    public function __construct(
        Session $session,
        ?int $tokenLifetime = null
    ) {
        $this->session = $session;
        $this->tokenLifetime = $tokenLifetime ?? self::DEFAULT_TOKEN_LIFETIME;
    }

    /**
     * Generiert ein neues API-Token für Client-Anfragen
     *
     * @param string|null $scope Optionaler Bereich für das Token (z.B. 'admin', 'user')
     * @param int|null $customLifetime Optionale benutzerdefinierte Lebensdauer
     * @return array{token: string, expires: int, scope: string} Token-Daten
     */
    public function generateApiToken(?string $scope = null, ?int $customLifetime = null): array
    {
        $tokenValue = $this->createSecureToken();
        $expiryTime = time() + ($customLifetime ?? $this->tokenLifetime);
        $scope = $scope ?? 'default';

        // Token und Metadaten speichern
        $apiTokens = $this->session->get(self::SESSION_KEY_API_TOKENS, []);

        $apiTokens[$tokenValue] = [
            'expires' => $expiryTime,
            'scope' => $scope,
            'created' => time(),
            'last_used' => null
        ];

        // Alte Tokens aufräumen
        $apiTokens = $this->cleanExpiredTokens($apiTokens);

        $this->session->set(self::SESSION_KEY_API_TOKENS, $apiTokens);

        return [
            'token' => $tokenValue,
            'expires' => $expiryTime,
            'scope' => $scope
        ];
    }

    /**
     * Validiert ein API-Token
     *
     * @param string $token Das zu validierende Token
     * @param string|null $requiredScope Optionaler erforderlicher Bereich
     * @return bool True, wenn das Token gültig ist
     */
    public function validateApiToken(string $token, ?string $requiredScope = null): bool
    {
        $apiTokens = $this->session->get(self::SESSION_KEY_API_TOKENS, []);

        if (!isset($apiTokens[$token])) {
            return false;
        }

        $tokenData = $apiTokens[$token];
        $now = time();

        // Prüfen, ob Token abgelaufen ist
        if ($tokenData['expires'] < $now) {
            unset($apiTokens[$token]);
            $this->session->set(self::SESSION_KEY_API_TOKENS, $apiTokens);
            return false;
        }

        // Scope prüfen, falls erforderlich
        if ($requiredScope !== null && $tokenData['scope'] !== $requiredScope) {
            return false;
        }

        // Token-Verwendung aktualisieren
        $apiTokens[$token]['last_used'] = $now;
        $this->session->set(self::SESSION_KEY_API_TOKENS, $apiTokens);

        return true;
    }

    /**
     * Validiert ein API-Token aus dem Authorization-Header
     *
     * @param array $headers Request-Headers
     * @param string|null $requiredScope Optionaler erforderlicher Bereich
     * @return bool True, wenn das Token gültig ist
     */
    public function validateTokenFromHeaders(array $headers, ?string $requiredScope = null): bool
    {
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';

        if (empty($authHeader)) {
            return false;
        }

        // Bearer-Token extrahieren
        if (preg_match('/Bearer\s+(.+)$/i', $authHeader, $matches)) {
            return $this->validateApiToken($matches[1], $requiredScope);
        }

        // X-API-Token-Header prüfen als Fallback
        $tokenHeader = $headers['X-API-Token'] ?? $headers['x-api-token'] ?? null;

        if ($tokenHeader) {
            return $this->validateApiToken($tokenHeader, $requiredScope);
        }

        return false;
    }

    /**
     * Widerruft ein bestimmtes API-Token
     *
     * @param string $token Das zu widerrufende Token
     * @return bool True, wenn erfolgreich widerrufen
     */
    public function revokeApiToken(string $token): bool
    {
        $apiTokens = $this->session->get(self::SESSION_KEY_API_TOKENS, []);

        if (!isset($apiTokens[$token])) {
            return false;
        }

        unset($apiTokens[$token]);
        $this->session->set(self::SESSION_KEY_API_TOKENS, $apiTokens);

        return true;
    }

    /**
     * Widerruft alle API-Tokens eines bestimmten Bereichs
     *
     * @param string|null $scope Bereich oder null für alle Tokens
     * @return int Anzahl der widerrufenen Tokens
     */
    public function revokeAllTokens(?string $scope = null): int
    {
        $apiTokens = $this->session->get(self::SESSION_KEY_API_TOKENS, []);
        $count = 0;

        if ($scope === null) {
            $count = count($apiTokens);
            $apiTokens = [];
        } else {
            $originalCount = count($apiTokens);
            $apiTokens = array_filter(
                $apiTokens,
                fn($data) => $data['scope'] !== $scope
            );
            $count = $originalCount - count($apiTokens);
        }

        $this->session->set(self::SESSION_KEY_API_TOKENS, $apiTokens);

        return $count;
    }

    /**
     * Erneuert ein API-Token und gibt ein neues mit aktualisierter Ablaufzeit zurück
     *
     * @param string $oldToken Das zu erneuernde Token
     * @return array|null Neue Token-Daten oder null bei Fehler
     */
    public function refreshApiToken(string $oldToken): ?array
    {
        $apiTokens = $this->session->get(self::SESSION_KEY_API_TOKENS, []);

        if (!isset($apiTokens[$oldToken])) {
            return null;
        }

        $oldData = $apiTokens[$oldToken];

        // Altes Token entfernen
        unset($apiTokens[$oldToken]);
        $this->session->set(self::SESSION_KEY_API_TOKENS, $apiTokens);

        // Neues Token mit gleichen Berechtigungen erstellen
        return $this->generateApiToken(
            $oldData['scope'],
            $this->tokenLifetime
        );
    }

    /**
     * Erstellt einen kryptographisch sicheren Token-String
     *
     * @return string
     */
    private function createSecureToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Entfernt abgelaufene Tokens aus dem Token-Store
     *
     * @param array $tokens Alle Tokens
     * @return array Bereinigte Tokens
     */
    private function cleanExpiredTokens(array $tokens): array
    {
        $now = time();

        return array_filter(
            $tokens,
            fn($data) => $data['expires'] > $now
        );
    }

    /**
     * Gibt Informationen zu einem Token zurück
     *
     * @param string $token Das Token
     * @return array|null Token-Informationen oder null, wenn nicht gefunden
     */
    public function getTokenInfo(string $token): ?array
    {
        $apiTokens = $this->session->get(self::SESSION_KEY_API_TOKENS, []);

        if (!isset($apiTokens[$token])) {
            return null;
        }

        $data = $apiTokens[$token];

        return [
            'token' => $token,
            'expires' => $data['expires'],
            'expires_in' => max(0, $data['expires'] - time()),
            'scope' => $data['scope'],
            'created' => $data['created'],
            'last_used' => $data['last_used']
        ];
    }

    /**
     * Verlängert die Lebensdauer eines Tokens
     *
     * @param string $token Das Token
     * @param int $additionalTime Zusätzliche Zeit in Sekunden
     * @return bool True bei Erfolg, sonst false
     */
    public function extendTokenLifetime(string $token, int $additionalTime): bool
    {
        $apiTokens = $this->session->get(self::SESSION_KEY_API_TOKENS, []);

        if (!isset($apiTokens[$token])) {
            return false;
        }

        $apiTokens[$token]['expires'] += $additionalTime;
        $this->session->set(self::SESSION_KEY_API_TOKENS, $apiTokens);

        return true;
    }
}