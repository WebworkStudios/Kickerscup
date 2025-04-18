<?php

declare(strict_types=1);

namespace App\Core\Security;

/**
 * Zentrale Sicherheitsklasse
 */
class Security
{
    /**
     * Session-Management
     */
    private Session $session;

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
     * @param Session $session Session-Management
     * @param Csrf $csrf CSRF-Schutz
     * @param Hash $hash Hash-Funktionen
     */
    public function __construct(Session $session, Csrf $csrf, Hash $hash)
    {
        $this->session = $session;
        $this->csrf = $csrf;
        $this->hash = $hash;
    }

    /**
     * Gibt die Session-Instanz zurück
     *
     * @return Session
     */
    public function getSession(): Session
    {
        return $this->session;
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
     * Verschlüsselt sensible Daten mit AES-256-GCM (authentifizierte Verschlüsselung)
     *
     * @param string $data Zu verschlüsselnde Daten
     * @param string|null $key Optionaler Schlüssel
     * @return string Verschlüsselte Daten im Format: base64(nonce|ciphertext|tag)
     * @throws \Exception Wenn die Verschlüsselung fehlschlägt
     */
    public function encrypt(string $data, ?string $key = null): string
    {
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
     * Gibt den Verschlüsselungsschlüssel zurück oder generiert einen neuen
     * 
     * @param bool $forceRegenerate Erzwingt die Neugenerierung des Schlüssels
     * @return string Verschlüsselungsschlüssel
     * @throws \Exception Wenn kein Schlüssel gefunden wird oder generiert werden kann
     */
    private function getEncryptionKey(bool $forceRegenerate = false): string
    {
        $keyInEnv = getenv('APP_KEY');
        $keyFile = getenv('APP_KEY_PATH') ?: __DIR__ . '/../../../config/encryption_key.php';
        
        // Wenn Neugenerierung erzwungen wird oder kein Schlüssel in der Umgebungsvariable ist
        if ($forceRegenerate || empty($keyInEnv)) {
            // Generiere einen neuen Schlüssel (32 Bytes für AES-256)
            $newKey = bin2hex(random_bytes(32));
            
            // Wenn die APP_KEY Umgebungsvariable nicht gesetzt ist, speichere den Schlüssel in einer Datei
            if (empty($keyInEnv)) {
            $keyContent = "<?php\n// Automatisch generierter Verschlüsselungsschlüssel\nreturn '" . $newKey . "';\n";
            
            // Speichern des Schlüssels in der Datei
            if (!file_put_contents($keyFile, $keyContent)) {
                throw new \Exception('Konnte den Verschlüsselungsschlüssel nicht speichern. Bitte überprüfen Sie die Schreibrechte.');
            }
            
            // Die Datei sollte nur für den Webserver lesbar sein
            chmod($keyFile, 0600);
        }
        
        return $newKey;
    }
    
    // Versuche den Schlüssel aus der Umgebungsvariable zu lesen
    if (!empty($keyInEnv)) {
        return $keyInEnv;
    }
    
    // Versuche den Schlüssel aus der Datei zu lesen
    if (file_exists($keyFile)) {
        $keyFromFile = include $keyFile;
        if (!empty($keyFromFile)) {
            return $keyFromFile;
        }
    }
    
    throw new \Exception('Kein Verschlüsselungsschlüssel definiert. Bitte APP_KEY Umgebungsvariable setzen oder Schlüsseldatei erstellen.');
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