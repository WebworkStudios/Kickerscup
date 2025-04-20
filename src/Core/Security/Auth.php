<?php


declare(strict_types=1);

namespace App\Core\Security;

/**
 * Vereinheitlichte Authentifizierungsklasse für JWT und API-Token
 */
class Auth
{
    /**
     * Benutzer-ID-Schlüssel im Token
     */
    private const USER_ID_CLAIM = 'sub'; // subject

    /**
     * Token-Typen
     */
    public const TYPE_JWT = 'jwt';
    public const TYPE_API = 'api';

    public function __construct(
        private readonly JWT          $jwt,
        private readonly TokenStorage $tokenStorage,
        private readonly string       $secretKey,
        private readonly string       $algorithm = JWT::ALGO_HS256,
        private readonly int          $tokenLifetime = 3600
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
        try {
            return $this->jwt->decode($token, $this->secretKey, $this->algorithm);
        } catch (\Exception $e) {
            app_log('JWT-Validierungsfehler: ' . $e->getMessage(), [], 'warning');
            return null;
        }
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
        $claims = array_merge([
            self::USER_ID_CLAIM => $userId
        ], $customClaims);

        return $this->jwt->encode(
            $claims,
            $this->secretKey,
            $this->algorithm,
            $lifetime ?? $this->tokenLifetime
        );
    }

    /**
     * Erstellt ein API-Token
     */
    public function createApiToken(int $userId, ?string $scope = null, ?int $lifetime = null): array
    {
        $token = bin2hex(random_bytes(32));
        $expiryTime = time() + ($lifetime ?? $this->tokenLifetime);

        $tokenData = [
            'user_id' => $userId,
            'expires' => $expiryTime,
            'scope' => $scope ?? 'default',
            'created' => time(),
            'last_used' => time()
        ];

        $this->tokenStorage->store($token, $tokenData, $lifetime ?? $this->tokenLifetime);

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
            $claims = $this->validateJwtToken($token);
            return $claims[self::USER_ID_CLAIM] ?? null;
        } else {
            $tokenData = $this->validateApiToken($token);
            return $tokenData['user_id'] ?? null;
        }
    }
}