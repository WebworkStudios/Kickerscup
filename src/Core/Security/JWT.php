<?php
declare(strict_types=1);

namespace App\Core\Security;

use DateTime;
use DateTimeInterface;
use stdClass;

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
     * Signiert und erstellt ein JWT-Token
     *
     * @param array $payload Nutzdaten des Tokens
     * @param string $key Signaturschlüssel
     * @param string $algorithm Algorithmus für die Signatur
     * @param int|null $lifetime Lebensdauer in Sekunden (null für unbegrenzt)
     * @return string Signiertes JWT
     */
    public function encode(
        array  $payload,
        string $key,
        string $algorithm = self::ALGO_HS256,
        ?int   $lifetime = null
    ): string
    {
        // Header erstellen
        $header = [
            'typ' => 'JWT',
            'alg' => $algorithm
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
        $signature = $this->createSignature($base64UrlHeader, $base64UrlPayload, $key, $algorithm);
        $base64UrlSignature = $this->base64UrlEncode($signature);

        // Token zusammensetzen
        return $base64UrlHeader . '.' . $base64UrlPayload . '.' . $base64UrlSignature;
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
}