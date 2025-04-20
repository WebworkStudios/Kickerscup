<?php
declare(strict_types=1);

namespace App\Core\Security;

use DateTime;

/**
 * JWT-Implementierung für das Framework
 *
 * Unterstützt die Erstellung und Validierung von JWT-Tokens mit
 * HMAC-SHA256, HMAC-SHA384 und HMAC-SHA512 Algorithmen
 */
class JWT
{
    /**
     * Unterstützte Algorithmen
     */
    public const ALGO_HS256 = 'HS256';
    public const ALGO_HS384 = 'HS384';
    public const ALGO_HS512 = 'HS512';

    /**
     * Standard-Lebensdauer in Sekunden (1 Stunde)
     */
    private const DEFAULT_LIFETIME = 3600;

    /**
     * Benutzer-ID-Schlüssel im Token
     */
    private const USER_ID_CLAIM = 'sub'; // subject

    /**
     * Konstruktor
     *
     * @param string $secretKey Geheimer Schlüssel für Token-Signatur
     * @param string $algorithm Signatur-Algorithmus
     * @param int $tokenLifetime Standard-Lebensdauer in Sekunden
     */
    public function __construct(
        private readonly string $secretKey = '',
        private readonly string $algorithm = self::ALGO_HS256,
        private readonly int $tokenLifetime = self::DEFAULT_LIFETIME
    )
    {
    }

    /**
     * Signiert und erstellt ein JWT-Token
     *
     * @param array $payload Nutzdaten des Tokens
     * @param string|null $key Optionaler Signaturschlüssel (überschreibt Konstruktor-Key)
     * @param string|null $algorithm Optionaler Algorithmus (überschreibt Konstruktor-Algo)
     * @param int|null $lifetime Lebensdauer in Sekunden (null für unbegrenzt)
     * @return string Signiertes JWT
     */
    public function encode(
        array $payload,
        ?string $key = null,
        ?string $algorithm = null,
        ?int $lifetime = null
    ): string
    {
        // Header erstellen
        $header = [
            'typ' => 'JWT',
            'alg' => $algorithm ?? $this->algorithm
        ];

        // Standard-Claims hinzufügen
        $now = new DateTime();
        $payload['iat'] = $now->getTimestamp(); // Issued At

        // Wenn eine Lebensdauer angegeben wurde, Ablaufzeit hinzufügen
        if ($lifetime !== null) {
            $expires = (clone $now)->modify("+{$lifetime} seconds");
            $payload['exp'] = $expires->getTimestamp(); // Expiration Time
        }

        // Base64Url-kodierte Teile erstellen
        $base64UrlHeader = $this->base64UrlEncode(json_encode($header, JSON_THROW_ON_ERROR));
        $base64UrlPayload = $this->base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR));

        // Signatur erstellen
        $signature = $this->createSignature(
            $base64UrlHeader,
            $base64UrlPayload,
            $key ?? $this->secretKey,
            $algorithm ?? $this->algorithm
        );
        $base64UrlSignature = $this->base64UrlEncode($signature);

        // Token zusammensetzen
        return $base64UrlHeader . '.' . $base64UrlPayload . '.' . $base64UrlSignature;
    }

    /**
     * Erstellt ein Token für einen Benutzer
     *
     * @param int $userId Benutzer-ID
     * @param array $customClaims Zusätzliche Claims
     * @param int|null $lifetime Benutzerdefinierte Lebensdauer in Sekunden
     * @return string JWT-Token
     */
    public function createUserToken(int $userId, array $customClaims = [], ?int $lifetime = null): string
    {
        $claims = array_merge([
            self::USER_ID_CLAIM => $userId
        ], $customClaims);

        return $this->encode(
            $claims,
            null,
            null,
            $lifetime ?? $this->tokenLifetime
        );
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
     * @param string|null $key Optionaler Schlüssel (überschreibt Konstruktor-Key)
     * @param string|null $algorithm Optionaler Algorithmus (überschreibt Konstruktor-Algo)
     * @return array|null Claims oder null bei ungültigem Token
     */
    public function validateToken(string $token, ?string $key = null, ?string $algorithm = null): ?array
    {
        try {
            return $this->decode($token, $key ?? $this->secretKey, $algorithm ?? $this->algorithm);
        } catch (\Exception $e) {
            app_log('JWT-Validierungsfehler: ' . $e->getMessage(), [], 'warning');
            return null;
        }
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

        return $this->createUserToken($userId, $mergedClaims);
    }

    /**
     * Erstellt einen Base64Url-kodierten String
     *
     * @param string $data Zu kodierende Daten
     * @return string Base64Url-kodierter String
     */
    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Erstellt die Signatur für ein JWT
     *
     * @param string $base64UrlHeader Base64Url-kodierter Header
     * @param string $base64UrlPayload Base64Url-kodierte Nutzdaten
     * @param string $key Signaturschlüssel
     * @param string $algorithm Algorithmus für die Signatur
     * @return string Signatur
     * @throws \Exception Bei nicht unterstütztem Algorithmus
     */
    private function createSignature(
        string $base64UrlHeader,
        string $base64UrlPayload,
        string $key,
        string $algorithm
    ): string
    {
        $data = $base64UrlHeader . '.' . $base64UrlPayload;

        return match ($algorithm) {
            self::ALGO_HS256 => hash_hmac('sha256', $data, $key, true),
            self::ALGO_HS384 => hash_hmac('sha384', $data, $key, true),
            self::ALGO_HS512 => hash_hmac('sha512', $data, $key, true),
            default => throw new \Exception("Nicht unterstützter Algorithmus: $algorithm"),
        };
    }

    /**
     * Dekodiert und validiert ein JWT-Token
     *
     * @param string $jwt Token
     * @param string $key Signaturschlüssel
     * @param string|null $algorithm Erwarteter Algorithmus (oder null für automatische Erkennung)
     * @return array Dekodierte Nutzdaten
     * @throws \Exception Bei ungültigem oder abgelaufenem Token
     */
    public function decode(string $jwt, string $key, ?string $algorithm = null): array
    {
        // Token in Teile zerlegen
        $tokenParts = explode('.', $jwt);
        if (count($tokenParts) !== 3) {
            throw new \Exception('Ungültiges JWT-Format');
        }

        [$base64UrlHeader, $base64UrlPayload, $base64UrlSignature] = $tokenParts;

        // Header und Payload dekodieren
        $header = json_decode($this->base64UrlDecode($base64UrlHeader), true, 512, JSON_THROW_ON_ERROR);
        $payload = json_decode($this->base64UrlDecode($base64UrlPayload), true, 512, JSON_THROW_ON_ERROR);

        // Algorithmus überprüfen, wenn einer angegeben wurde
        if ($algorithm !== null && ($header['alg'] ?? null) !== $algorithm) {
            throw new \Exception('Algorithmus stimmt nicht überein');
        }

        // Signatur validieren
        $actualSignature = $this->base64UrlDecode($base64UrlSignature);
        $expectedSignature = $this->createSignature(
            $base64UrlHeader,
            $base64UrlPayload,
            $key,
            $header['alg'] ?? self::ALGO_HS256
        );

        if (!hash_equals($actualSignature, $expectedSignature)) {
            throw new \Exception('Ungültige Signatur');
        }

        // Ablaufzeit prüfen
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            throw new \Exception('Token ist abgelaufen');
        }

        return $payload;
    }

    /**
     * Dekodiert einen Base64Url-kodierten String
     *
     * @param string $data Zu dekodierende Daten
     * @return string Dekodierte Daten
     */
    private function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }

    /**
     * Extrahiert ein JWT-Token aus dem Authorization-Header
     *
     * @param array|string $headers Request-Headers oder Authorization-Header
     * @return string|null Extrahiertes Token oder null
     */
    public function extractTokenFromHeader(array|string $headers): ?string
    {
        if (is_string($headers)) {
            $authHeader = $headers;
        } else {
            $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        }

        if (empty($authHeader)) {
            return null;
        }

        // Bearer-Token extrahieren
        if (preg_match('/Bearer\s+(.+)$/i', $authHeader, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Validiert ein Token aus dem Authorization-Header
     *
     * @param array|string $headers Request-Headers als Array oder Authorization-Header-String
     * @return array|null JWT-Claims oder null bei ungültigem Token
     */
    public function validateTokenFromHeaders(array|string $headers): ?array
    {
        $token = $this->extractTokenFromHeader($headers);

        if (!$token) {
            return null;
        }

        return $this->validateToken($token);
    }
}