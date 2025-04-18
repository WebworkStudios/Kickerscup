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
    private const SESSION_KEY_PER_FORM = 'csrf_tokens_per_form';

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
        ?int $tokenLifetime = null
    ) {
        $this->session = $session;
        $this->tokenLifetime = $tokenLifetime ?? self::DEFAULT_TOKEN_LIFETIME;
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
     * Validiert ein formularspezifisches CSRF-Token
     *
     * @param string $token CSRF-Token
     * @param string $formId Formular-ID
     * @return bool True, wenn gültig, sonst false
     */
    private function validateFormToken(string $token, string $formId): bool
    {
        $tokens = $this->session->get(self::SESSION_KEY_PER_FORM, []);

        if (!isset($tokens[$formId])) {
            return false;
        }

        [$storedToken, $expiryTime] = $tokens[$formId];

        // Prüfe, ob Token abgelaufen ist
        if (time() > $expiryTime) {
            // Entferne abgelaufenes Token
            unset($tokens[$formId]);
            $this->session->set(self::SESSION_KEY_PER_FORM, $tokens);
            return false;
        }

        return hash_equals($storedToken, $token);
    }

    /**
     * Generiert ein HTML-Formularfeld für das CSRF-Token
     *
     * @param string|null $formId Optional: Formular-ID für spezifisches Token
     * @return string HTML
     */
    public function formField(?string $formId = null): string
    {
        $token = $formId !== null
            ? $this->getFormToken($formId)
            : ($this->getToken() ?? $this->generateToken());

        return sprintf(
            '<input type="hidden" name="csrf_token" value="%s">',
            htmlspecialchars($token, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        );
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
     * Gibt ein formularspezifisches CSRF-Token zurück oder generiert ein neues
     *
     * @param string $formId Formular-ID
     * @return string Token
     */
    public function getFormToken(string $formId): string
    {
        $tokens = $this->session->get(self::SESSION_KEY_PER_FORM, []);

        // Prüfe, ob ein Token für dieses Formular existiert und nicht abgelaufen ist
        if (isset($tokens[$formId])) {
            [$token, $expiryTime] = $tokens[$formId];

            if (time() <= $expiryTime) {
                return $token;
            }
        }

        // Generiere ein neues Token für dieses Formular
        return $this->generateFormToken($formId);
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
     * Generiert ein formularspezifisches CSRF-Token
     *
     * @param string $formId Formular-ID
     * @return string
     */
    public function generateFormToken(string $formId): string
    {
        $tokens = $this->session->get(self::SESSION_KEY_PER_FORM, []);

        // Bereinige abgelaufene Tokens, wenn die Liste zu groß wird
        if (count($tokens) > 20) {
            $this->cleanupExpiredFormTokens();
        }

        $token = $this->createTokenString();
        $expiryTime = time() + $this->tokenLifetime;

        $tokens[$formId] = [$token, $expiryTime];
        $this->session->set(self::SESSION_KEY_PER_FORM, $tokens);

        return $token;
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
     * Entfernt abgelaufene formularspezifische Tokens
     *
     * @return void
     */
    private function cleanupExpiredFormTokens(): void
    {
        $tokens = $this->session->get(self::SESSION_KEY_PER_FORM, []);
        $now = time();

        // Array-Funktionen von PHP 8.4 benutzen
        $tokens = array_filter(
            $tokens,
            fn(array $tokenData): bool => $tokenData[1] > $now
        );

        $this->session->set(self::SESSION_KEY_PER_FORM, $tokens);
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