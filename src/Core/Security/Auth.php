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
     * Extrahiert Token aus Authorization-Header
     */
    public function extractTokenFromHeader(array|string $headers): ?array
    {
        if (is_string($headers)) {
            $authHeader = $headers;
        } else {
            $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
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

        // X-API-Token prüfen
        if (is_array($headers) && isset($headers['X-API-Token'])) {
            return [
                'type' => self::TYPE_API,
                'token' => $headers['X-API-Token']
            ];
        }

        return null;
    }

    /**
     * Validiert ein Token aus dem Authorization-Header
     */
    public function validateTokenFromHeaders(array|string $headers): ?array
    {
        $extractedToken = $this->extractTokenFromHeader($headers);

        if (!$extractedToken) {
            return null;
        }

        // Je nach Token-Typ validieren
        if ($extractedToken['type'] === self::TYPE_JWT) {
            return $this->validateJwtToken($extractedToken['token']);
        } else {
            return $this->validateApiToken($extractedToken['token']);
        }
    }

    /**
     * Validiert ein JWT
     */
    public function validateJwtToken(string $token): ?array
    {
        return $this->jwt->validateToken($token);
    }

    /**
     * Validiert ein API-Token
     */
    public function validateApiToken(string $token): ?array
    {
        $tokenData = $this->tokenStorage->get($token);

        if (!$tokenData) {
            return null;
        }

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
     */
    public function createJwtToken(int $userId, array $customClaims = [], ?int $lifetime = null): string
    {
        return $this->jwt->createUserToken($userId, $customClaims, $lifetime);
    }

    /**
     * Erstellt ein API-Token
     */
    public function createApiToken(int $userId, ?string $scope = null, ?int $lifetime = null): array
    {
        $token = bin2hex(random_bytes(32));
        $expiryTime = time() + ($lifetime ?? 3600);

        $tokenData = [
            'user_id' => $userId,
            'expires' => $expiryTime,
            'scope' => $scope ?? 'default',
            'created' => time(),
            'last_used' => time()
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
     */
    public function revokeApiToken(string $token): bool
    {
        return $this->tokenStorage->remove($token);
    }

    /**
     * Holt die Benutzer-ID aus einem Token
     */
    public function getUserId(string $token, string $type = self::TYPE_JWT): ?int
    {
        if ($type === self::TYPE_JWT) {
            return $this->jwt->getUserIdFromToken($token);
        } else {
            $tokenData = $this->validateApiToken($token);
            return $tokenData['user_id'] ?? null;
        }
    }
}