<?php
declare(strict_types=1);

namespace App\Core\Security;

/**
 * JWT-basierte Authentifizierung
 */
class JWTAuth
{
    /**
     * Benutzer-ID-Schlüssel im Token
     */
    private const USER_ID_CLAIM = 'sub'; // subject

    /**
     * Konstruktor
     *
     * @param JWT $jwt JWT-Service
     * @param string $secretKey Geheimer Schlüssel für Token-Signatur
     * @param string $algorithm Signatur-Algorithmus
     * @param int $tokenLifetime Standard-Lebensdauer in Sekunden
     */
    public function __construct(
        private readonly JWT    $jwt,
        private readonly string $secretKey,
        private readonly string $algorithm = JWT::ALGO_HS256,
        private readonly int    $tokenLifetime = 3600
    )
    {
    }

    /**
     * Extrahiert die Benutzer-ID aus einem Token
     *
     * @param string $token JWT-Token
     * @return int|null Benutzer-ID oder null bei ungültigem Token
     */
    public function getUserIdFromToken(string $token): ?int
    {
        $claims = $this->validateToken($token);

        if (!$claims) {
            return null;
        }

        return $claims[self::USER_ID_CLAIM] ?? null;
    }

    /**
     * Validiert ein Token und gibt die darin enthaltenen Claims zurück
     *
     * @param string $token JWT-Token
     * @return array|null Claims oder null bei ungültigem Token
     */
    public function validateToken(string $token): ?array
    {
        try {
            return $this->jwt->decode($token, $this->secretKey, $this->algorithm);
        } catch (\Exception $e) {
            app_log('JWT-Validierungsfehler: ' . $e->getMessage(), [], 'warning');
            return null;
        }
    }

    /**
     * Validiert ein Token aus dem Authorization-Header
     *
     * @param array|string $headers Request-Headers als Array oder Authorization-Header-String
     * @return array|null JWT-Claims oder null bei ungültigem Token
     */
    public function validateTokenFromHeaders(array|string $headers): ?array
    {
        $token = $this->jwt->extractTokenFromHeader($headers);

        if (!$token) {
            return null;
        }

        return $this->validateToken($token);
    }

    /**
     * Erneuert ein Token mit derselben Benutzer-ID und aktualisierten Claims
     *
     * @param string $token Altes Token
     * @param array $additionalClaims Zusätzliche Claims für das neue Token
     * @return string|null Neues Token oder null bei Fehler
     */
    public function refreshToken(string $token, array $additionalClaims = []): ?string
    {
        $claims = $this->validateToken($token);

        if (!$claims || !isset($claims[self::USER_ID_CLAIM])) {
            return null;
        }

        $userId = $claims[self::USER_ID_CLAIM];

        // Bestehende benutzerdefinierten Claims übernehmen, aber iat/exp/nbf entfernen
        $customClaims = array_diff_key($claims, array_flip([self::USER_ID_CLAIM, 'iat', 'exp', 'nbf']));

        // Neue Claims hinzufügen
        $mergedClaims = array_merge($customClaims, $additionalClaims);

        return $this->createToken($userId, $mergedClaims);
    }

    /**
     * Erstellt ein Token für einen Benutzer
     *
     * @param int $userId Benutzer-ID
     * @param array $customClaims Zusätzliche Claims
     * @param int|null $lifetime Benutzerdefinierte Lebensdauer in Sekunden
     * @return string JWT-Token
     */
    public function createToken(int $userId, array $customClaims = [], ?int $lifetime = null): string
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
}