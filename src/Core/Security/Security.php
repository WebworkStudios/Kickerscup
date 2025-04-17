<?php

declare(strict_types=1);

namespace App\Core\Security;

/**
 * Zentrale Sicherheitsklasse
 */
class Security
{
    /**
     * CSRF-Schutz
     */
    private Csrf $csrf;

    /**
     * Hash-Funktionen
     */
    private Hash $hash;

    /**
     * Konstruktor
     *
     * @param Csrf $csrf CSRF-Schutz
     * @param Hash $hash Hash-Funktionen
     */
    public function __construct(Csrf $csrf, Hash $hash)
    {
        $this->csrf = $csrf;
        $this->hash = $hash;
    }

    /**
     * Gibt die CSRF-Instanz zurück
     *
     * @return Csrf
     */
    public function getCsrf(): Csrf
    {
        return $this->csrf;
    }

    /**
     * Gibt die Hash-Instanz zurück
     *
     * @return Hash
     */
    public function getHash(): Hash
    {
        return $this->hash;
    }

    /**
     * Escaped einen String für die Ausgabe
     *
     * @param string $value Zu escapender String
     * @return string Escapeter String
     */
    public function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Verschlüsselt sensible Daten
     *
     * @param string $data Zu verschlüsselnde Daten
     * @param string|null $key Optionaler Schlüssel
     * @return string Verschlüsselte Daten
     */
    public function encrypt(string $data, ?string $key = null): string
    {
        $key = $key ?? $this->getEncryptionKey();

        $ivLength = openssl_cipher_iv_length('aes-256-cbc');
        $iv = openssl_random_pseudo_bytes($ivLength);

        $encrypted = openssl_encrypt(
            $data,
            'aes-256-cbc',
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($encrypted === false) {
            throw new \Exception('Fehler beim Verschlüsseln der Daten: ' . openssl_error_string());
        }

        // IV und verschlüsselte Daten kombinieren und als base64 zurückgeben
        return base64_encode($iv . $encrypted);
    }

    /**
     * Entschlüsselt verschlüsselte Daten
     *
     * @param string $data Verschlüsselte Daten
     * @param string|null $key Optionaler Schlüssel
     * @return string Entschlüsselte Daten
     */
    public function decrypt(string $data, ?string $key = null): string
    {
        $key = $key ?? $this->getEncryptionKey();

        $data = base64_decode($data);

        $ivLength = openssl_cipher_iv_length('aes-256-cbc');
        $iv = substr($data, 0, $ivLength);
        $encrypted = substr($data, $ivLength);

        $decrypted = openssl_decrypt(
            $encrypted,
            'aes-256-cbc',
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($decrypted === false) {
            throw new \Exception('Fehler beim Entschlüsseln der Daten: ' . openssl_error_string());
        }

        return $decrypted;
    }

    /**
     * Generiert einen kryptografisch sicheren Token
     *
     * @param int $length Länge des Tokens
     * @return string Token
     */
    public function generateToken(int $length = 32): string
    {
        return bin2hex(random_bytes($length / 2));
    }

    /**
     * Überprüft, ob eine E-Mail-Adresse gültig ist
     *
     * @param string $email E-Mail-Adresse
     * @return bool True, wenn gültig, sonst false
     */
    public function isValidEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Überprüft, ob eine URL gültig ist
     *
     * @param string $url URL
     * @return bool True, wenn gültig, sonst false
     */
    public function isValidUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Gibt den Verschlüsselungsschlüssel zurück
     *
     * @return string
     */
    private function getEncryptionKey(): string
    {
        // In einer echten Anwendung sollte der Schlüssel aus einer sicheren Quelle stammen
        $key = getenv('APP_KEY');

        if (empty($key)) {
            throw new \Exception('Kein Verschlüsselungsschlüssel definiert. Bitte APP_KEY Umgebungsvariable setzen.');
        }

        return $key;
    }
}