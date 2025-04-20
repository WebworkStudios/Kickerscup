<?php
declare(strict_types=1);

namespace App\Core\Security;

/**
 * Vereinheitlichte Authentifizierungsklasse für JWT und API-Token
 */
class Auth
{
    /**
     * Token-Typen
     */
    public const TYPE_JWT = 'jwt';
    public const TYPE_API = 'api';

    public function __construct(
        private readonly JWT          $jwt,
        private readonly TokenStorage $tokenStorage
    )
    {
    }

    /**
     * Validiert ein Token aus dem Authorization-Header
     *
     * @param array|string $headers Headers als Array oder Authorization-Header-String
     * @return array|null Token-Claims oder null bei ungültigem Token
     */
    public function validateTokenFromHeaders(array|string $headers): ?array
    {
        $extractedToken = $this->extractTokenFromHeader($headers);

        if (!$extractedToken) {
            return null;
        }

        // Je nach Token-Typ validieren
        return match ($extractedToken['type']) {
            self::TYPE_JWT => $this->validateJwtToken($extractedToken['token']),
            self::TYPE_API => $this->validateApiToken($extractedToken['token']),
            default => null
        };
    }

    /**
     * Extrahiert Token aus Authorization-Header mit PHP 8.4 Features
     *
     * @param array|string $headers Headers als Array oder Authorization-Header-String
     * @return array|null Token-Informationen oder null
     */
    public function extractTokenFromHeader(array|string $headers): ?array
    {
        if (is_string($headers)) {
            $authHeader = $headers;
            $apiTokenHeader = null;
        } else {
            // Case-insensitive Header-Suche mit array_find_key in PHP 8.4
            $authKey = array_find_key($headers, fn($v, $k) => strtolower($k) === 'authorization');
            $apiTokenKey = array_find_key($headers, fn($v, $k) => strtolower($k) === 'x-api-token');

            $authHeader = $authKey ? $headers[$authKey] : '';
            $apiTokenHeader = $apiTokenKey ? $headers[$apiTokenKey] : null;
        }

        // Zuerst direkte API-Token prüfen
        if (!empty($apiTokenHeader)) {
            return [
                'type' => self::TYPE_API,
                'token' => $apiTokenHeader
            ];
        }

        if (empty($authHeader)) {
            return null;
        }

        // Bearer-Token extrahieren (JWT)
        if (preg_match('/Bearer\s+(.+)$/i', $authHeader, $matches)) {
            return [
                'type' => self::TYPE_JWT,
                'token' => $matches[1]
            ];
        }

        // API-Token extrahieren
        if (preg_match('/ApiKey\s+(.+)$/i', $authHeader, $matches)) {
            return [
                'type' => self::TYPE_API,
                'token' => $matches[1]
            ];
        }

        return null;
    }

    /**
     * Validiert ein JWT
     *
     * @param string $token JWT-Token
     * @return array|null Token-Claims oder null bei ungültigem Token
     */
    public function validateJwtToken(string $token): ?array
    {
        return $this->jwt->validateToken($token);
    }

    /**
     * Validiert ein API-Token
     *
     * @param string $token API-Token
     * @return array|null Token-Daten oder null bei ungültigem Token
     */
    public function validateApiToken(string $token): ?array
    {
        $tokenData = $this->tokenStorage->get($token);

        // Prüfen ob Token existiert und gültig ist mit PHP 8.4 array_all
        if (!$tokenData || !array_all(['user_id', 'expires', 'scope'], fn($key) => isset($tokenData[$key]))) {
            return null;
        }

        // Prüfen ob Token abgelaufen ist
        if ($tokenData['expires'] < time()) {
            $this->tokenStorage->remove($token);
            return null;
        }

        // Token-Verwendung aktualisieren
        $tokenData['last_used'] = time();
        $this->tokenStorage->store($token, $tokenData, $tokenData['expires'] - time());

        return $tokenData;
    }

    /**
     * Erstellt ein JWT
     *
     * @param int $userId Benutzer-ID
     * @param array $customClaims Benutzerdefinierte Claims
     * @param int|null $lifetime Lebensdauer in Sekunden
     * @return string JWT-Token
     */
    public function createJwtToken(int $userId, array $customClaims = [], ?int $lifetime = null): string
    {
        return $this->jwt->createUserToken($userId, $customClaims, $lifetime);
    }

    /**
     * Erstellt ein API-Token
     *
     * @param int $userId Benutzer-ID
     * @param string|null $scope Berechtigungen
     * @param int|null $lifetime Lebensdauer in Sekunden
     * @return array Token-Informationen (token, expires, scope)
     */
    public function createApiToken(int $userId, ?string $scope = null, ?int $lifetime = null): array
    {
        // Stärkere Zufallsquelle mit ausreichender Entropie
        $token = bin2hex(random_bytes(32));
        $expiryTime = time() + ($lifetime ?? 3600);

        $tokenData = [
            'user_id' => $userId,
            'expires' => $expiryTime,
            'scope' => $scope ?? 'default',
            'created' => time(),
            'last_used' => time(),
            // Speichern der IP-Adresse und User-Agent für Sicherheitslogs
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
        ];

        $this->tokenStorage->store($token, $tokenData, $lifetime ?? 3600);

        return [
            'token' => $token,
            'expires' => $expiryTime,
            'scope' => $scope ?? 'default'
        ];
    }

    /**
     * Widerruft ein API-Token
     *
     * @param string $token API-Token
     * @return bool Erfolg
     */
    public function revokeApiToken(string $token): bool
    {
        return $this->tokenStorage->remove($token);
    }

    /**
     * Widerruft alle API-Tokens eines Benutzers
     *
     * @param int $userId Benutzer-ID
     * @return bool Erfolg
     */
    public function revokeAllUserTokens(int $userId): bool
    {
        // Implementierung hängt von der TokenStorage-Fähigkeit ab,
        // Tokens nach Benutzer-ID zu finden und zu entfernen
        // Das ist eine Erweiterung, die noch implementiert werden muss

        // Grundidee:
        // $userTokens = $this->tokenStorage->findByUserId($userId);
        // return $this->tokenStorage->removeMultiple($userTokens);

        // Da diese Funktionalität noch nicht implementiert ist,
        // geben wir false zurück
        app_log('Method revokeAllUserTokens is not fully implemented yet', ['userId' => $userId], 'warning');
        return false;
    }

    /**
     * Verlängert ein vorhandenes API-Token
     *
     * @param string $token API-Token
     * @param int|null $lifetime Neue Lebensdauer in Sekunden
     * @return bool Erfolg
     */
    public function extendApiToken(string $token, ?int $lifetime = null): bool
    {
        $tokenData = $this->tokenStorage->get($token);

        if (!$tokenData) {
            return false;
        }

        $newExpiry = time() + ($lifetime ?? 3600);
        $tokenData['expires'] = $newExpiry;
        $tokenData['last_used'] = time();

        return $this->tokenStorage->store($token, $tokenData, $lifetime ?? 3600);
    }

    /**
     * Holt die Benutzer-ID aus einem Token
     *
     * @param string $token Token
     * @param string $type Token-Typ (Auth::TYPE_JWT oder Auth::TYPE_API)
     * @return int|null Benutzer-ID oder null bei ungültigem Token
     */
    public function getUserId(string $token, string $type = self::TYPE_JWT): ?int
    {
        return match ($type) {
            self::TYPE_JWT => $this->jwt->getUserIdFromToken($token),
            self::TYPE_API => ($this->validateApiToken($token)['user_id'] ?? null),
            default => null
        };
    }
}