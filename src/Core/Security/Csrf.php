<?php

declare(strict_types=1);

namespace App\Core\Security;

/**
 * Verbesserte CSRF-Schutzklasse
 *
 * Bietet erweiterten Schutz vor Cross-Site Request Forgery mit
 * unterstützung für formularbasierte und API-basierte Anwendungen
 */
class Csrf
{
    /**
     * Session-Keys für CSRF-Token
     */
    private const SESSION_KEY = 'csrf_token';
    private const SESSION_KEY_EXPIRY = 'csrf_token_expiry';

    /**
     * Default Token-Lebensdauer in Sekunden
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
        ?int    $tokenLifetime = null
    )
    {
        $this->session = $session;
        $this->tokenLifetime = $tokenLifetime ?? self::DEFAULT_TOKEN_LIFETIME;
    }

    /**
     * Erstellt einen kryptographisch sicheren Token-String
     *
     * @return string
     */
    private function createTokenString(): string
    {
        // 32 Bytes erzeugen 64 Hex-Zeichen, die sicherer sind als base64
        return bin2hex(random_bytes(32));
    }

    /**
     * Gibt das aktuelle CSRF-Token zurück
     *
     * @return string|null
     */
    public function getToken(): ?string
    {
        $token = $this->session->get(self::SESSION_KEY);
        $expiryTime = $this->session->get(self::SESSION_KEY_EXPIRY);

        // Überprüfe, ob Token abgelaufen ist
        if ($token !== null && $expiryTime !== null && time() > $expiryTime) {
            // Token ist abgelaufen, generiere ein neues
            return $this->generateToken();
        }

        return $token;
    }

    /**
     * Generiert ein CSRF-Token und speichert es in der Session
     *
     * @return string
     */
    public function generateToken(): string
    {
        $token = $this->createTokenString();
        $expiryTime = time() + $this->tokenLifetime;

        $this->session->set(self::SESSION_KEY, $token);
        $this->session->set(self::SESSION_KEY_EXPIRY, $expiryTime);

        return $token;
    }

    /**
     * Erzeugt ein JSON-Web-Token für API-Anfragen
     *
     * @param int $validity Gültigkeitsdauer in Sekunden
     * @return array{token: string, expires: int} Token und Ablaufzeit
     */
    public function generateApiToken(int $validity = 3600): array
    {
        $token = $this->createTokenString();
        $expiryTime = time() + $validity;

        // Für API-Anfragen speichern wir ein separates Token
        $apiTokens = $this->session->get('api_csrf_tokens', []);
        $apiTokens[$token] = $expiryTime;

        // Bereinige ältere API-Tokens
        $now = time();
        $apiTokens = array_filter($apiTokens, fn($expiry) => $expiry > $now);

        $this->session->set('api_csrf_tokens', $apiTokens);

        return [
            'token' => $token,
            'expires' => $expiryTime
        ];
    }

    /**
     * Validiert ein API-CSRF-Token
     *
     * @param string $token API-CSRF-Token
     * @return bool True, wenn gültig, sonst false
     */
    public function validateApiToken(string $token): bool
    {
        $apiTokens = $this->session->get('api_csrf_tokens', []);

        if (!isset($apiTokens[$token])) {
            return false;
        }

        $expiryTime = $apiTokens[$token];

        // Prüfe, ob Token abgelaufen ist
        if (time() > $expiryTime) {
            // Entferne abgelaufenes Token
            unset($apiTokens[$token]);
            $this->session->set('api_csrf_tokens', $apiTokens);
            return false;
        }

        return true;
    }

    /**
     * Prüft ein CSRF-Token aus einem HTTP-Request-Header
     *
     * @param array<string, string> $headers HTTP-Header
     * @return bool True, wenn gültig, sonst false
     */
    public function validateHeaderToken(array $headers): bool
    {
        $token = $headers['X-CSRF-TOKEN'] ?? $headers['x-csrf-token'] ?? null;

        if ($token === null) {
            return false;
        }

        return $this->validateToken($token);
    }

    /**
     * Validiert ein CSRF-Token
     *
     * @param string $token CSRF-Token
     * @param string|null $formId Optional: Formular-ID für spezifische Token-Validierung
     * @return bool True, wenn gültig, sonst false
     */
    public function validateToken(string $token, ?string $formId = null): bool
    {
        // Überprüfung des benutzerspezifischen Tokens, wenn formId angegeben wurde
        if ($formId !== null) {
            return $this->validateFormToken($token, $formId);
        }

        // Prüfe, ob Token in der Session existiert
        $storedToken = $this->session->get(self::SESSION_KEY);
        if ($storedToken === null) {
            return false;
        }

        // Prüfe, ob Token abgelaufen ist
        $expiryTime = $this->session->get(self::SESSION_KEY_EXPIRY);
        if ($expiryTime !== null && time() > $expiryTime) {
            // Token abgelaufen, generiere ein neues
            $this->generateToken();
            return false;
        }

        // Vergleiche Token mit constant-time Vergleich gegen Timing-Attacken
        return hash_equals($storedToken, $token);
    }

    /**
     * Entfernt alle CSRF-Token aus der Session
     *
     * @return void
     */
    public function clearAllTokens(): void
    {
        $this->session->remove(self::SESSION_KEY);
        $this->session->remove(self::SESSION_KEY_EXPIRY);
        $this->session->remove(self::SESSION_KEY_PER_FORM);
        $this->session->remove('api_csrf_tokens');
    }
}