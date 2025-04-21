<?php

declare(strict_types=1);

namespace App\Core\Security;

/**
 * Zentrale Sicherheitsklasse
 */
class Security
{
    /**
     * Hash-Funktionen
     */
    private Hash $hash;

    /**
     * Konstruktor
     *
     * @param Hash $hash Hash-Funktionen
     */
    public function __construct(Hash $hash)
    {
        $this->hash = $hash;
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
     * Verschlüsselt sensible Daten mit AES-256-GCM (authentifizierte Verschlüsselung)
     *
     * @param string $data Zu verschlüsselnde Daten
     * @param string|null $key Optionaler Schlüssel
     * @return string Verschlüsselte Daten im Format: base64(nonce|ciphertext|tag)
     * @throws \Exception Wenn die Verschlüsselung fehlschlägt
     */
    public function encrypt(string $data, ?string $key = null): string
    {
        if (empty($data)) {
            throw new \InvalidArgumentException('Zu verschlüsselnde Daten dürfen nicht leer sein');
        }

        $key = $key ?? $this->getEncryptionKey();

        // Nonce/IV generieren (12 Bytes für GCM empfohlen)
        $nonce = random_bytes(12);

        // Tag-Variable für den Auth-Tag
        $tag = '';

        // Verschlüsseln mit AES-256-GCM
        $ciphertext = openssl_encrypt(
            $data,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            '',   // AAD (zusätzliche authentifizierte Daten)
            16    // Tag-Länge: 16 Bytes (128 Bits)
        );

        if ($ciphertext === false) {
            throw new \Exception('Fehler beim Verschlüsseln der Daten: ' . openssl_error_string());
        }

        // Nonce, Ciphertext und Auth-Tag kombinieren und als base64 zurückgeben
        return base64_encode($nonce . $ciphertext . $tag);
    }

    /**
     * Gibt den Verschlüsselungsschlüssel zurück oder generiert einen neuen
     *
     * @param bool $forceRegenerate Erzwingt die Neugenerierung des Schlüssels
     * @return string Verschlüsselungsschlüssel
     * @throws \Exception Wenn kein Schlüssel gefunden wird oder generiert werden kann
     */
    private function getEncryptionKey(bool $forceRegenerate = false): string
    {
        $keyInEnv = getenv('APP_KEY');

        // In PHP 8.4 können wir den ternary-Operator mit nullsafe-Operator kombinieren
        $keyFile = getenv('APP_KEY_PATH') ?: dirname(__DIR__, 3) . '/config/encryption_key.bin';

        // Wenn Neugenerierung erzwungen wird oder kein Schlüssel in der Umgebungsvariable ist
        if ($forceRegenerate || empty($keyInEnv)) {
            // Generiere einen neuen Schlüssel (32 Bytes für AES-256)
            $newKey = random_bytes(32);

            // Wenn die APP_KEY Umgebungsvariable nicht gesetzt ist, speichere den Schlüssel in einer Datei
            if (empty($keyInEnv)) {
                $keyDir = dirname($keyFile);
                if (!is_dir($keyDir)) {
                    mkdir($keyDir, 0750, true);
                }

                // Temporäre Datei mit zufälligem Namen erstellen
                $tempFile = $keyFile . '.temp.' . bin2hex(random_bytes(8));

                if (!file_put_contents($tempFile, $newKey, LOCK_EX)) {
                    throw new \Exception('Konnte den Verschlüsselungsschlüssel nicht speichern. Bitte überprüfen Sie die Schreibrechte.');
                }

                // Die Datei sollte nur für den Webserver lesbar sein
                chmod($tempFile, 0400); // Noch restriktiver: nur lesbar, nicht ausführbar

                // Atomares Umbenennen (verhindert Race-Conditions)
                if (!rename($tempFile, $keyFile)) {
                    @unlink($tempFile); // Aufräumen im Fehlerfall
                    throw new \Exception('Konnte den Verschlüsselungsschlüssel nicht speichern (Umbenennung fehlgeschlagen).');
                }
            }

            return bin2hex($newKey); // Konvertieren von Binär zu Hex für Verwendung im Code
        }

        // Versuche den Schlüssel aus der Umgebungsvariable zu lesen
        if (!empty($keyInEnv)) {
            return $keyInEnv;
        }

        // Versuche den Schlüssel aus der binären Datei zu lesen
        if (file_exists($keyFile)) {
            $keyData = file_get_contents($keyFile);
            if (!empty($keyData)) {
                return bin2hex($keyData);
            }
        }

        throw new \Exception('Kein Verschlüsselungsschlüssel definiert. Bitte APP_KEY Umgebungsvariable setzen oder Schlüsseldatei erstellen.');
    }
    /**
     * Entschlüsselt verschlüsselte Daten mit AES-256-GCM
     *
     * @param string $data Verschlüsselte Daten im Format: base64(nonce|ciphertext|tag)
     * @param string|null $key Optionaler Schlüssel
     * @return string Entschlüsselte Daten
     * @throws \Exception Wenn die Entschlüsselung fehlschlägt oder die Daten manipuliert wurden
     */
    public function decrypt(string $data, ?string $key = null): string
    {
        $key = $key ?? $this->getEncryptionKey();

        // Base64-Decodierung
        $raw = base64_decode($data, true);
        if ($raw === false) {
            throw new \Exception('Ungültiges Format der verschlüsselten Daten');
        }

        // Nonce extrahieren (12 Bytes)
        $nonceSize = 12;
        $nonce = substr($raw, 0, $nonceSize);

        // Auth-Tag extrahieren (16 Bytes, am Ende)
        $tagSize = 16;
        $tag = substr($raw, -$tagSize);

        // Ciphertext extrahieren (zwischen Nonce und Tag)
        $ciphertext = substr($raw, $nonceSize, -$tagSize);

        // Entschlüsseln mit AES-256-GCM
        $decrypted = openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag
        );

        if ($decrypted === false) {
            throw new \Exception('Fehler beim Entschlüsseln der Daten: Die Daten wurden möglicherweise manipuliert');
        }

        return $decrypted;
    }

    /**
     * Generiert einen neuen Verschlüsselungsschlüssel und speichert ihn
     *
     * @return string Der neue Schlüssel
     */
    public function rotateEncryptionKey(): string
    {
        return $this->getEncryptionKey(true);
    }

    /**
     * Generiert einen kryptografisch sicheren Token
     *
     * @param int $length Länge des Tokens in Bytes (wird zu doppelt so vielen Hex-Zeichen)
     * @return string Token als Hex-String
     */
    public function generateToken(int $length = 32): string
    {
        return bin2hex(random_bytes($length));
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
}