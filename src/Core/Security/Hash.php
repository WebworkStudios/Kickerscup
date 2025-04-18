<?php

declare(strict_types=1);

namespace App\Core\Security;

/**
 * Hash-Funktionen
 */
class Hash
{
    /**
     * Standardeinstellungen für Argon2id
     */
    private const ARGON_OPTIONS = [
        'memory_cost' => 65536, // 64 MB
        'time_cost' => 4,       // 4 Iterationen
        'threads' => 1          // 1 Thread (für Parallelisierungs-Resistenz)
    ];

    /**
     * Hasht ein Passwort mit Argon2id
     *
     * @param string $password Passwort
     * @param array|null $options Optionale Argon2id-Parameter
     * @return string Gehashtes Passwort
     */
    public function password(string $password, ?array $options = null): string
    {
        $options ??= self::ARGON_OPTIONS;

        return password_hash($password, PASSWORD_ARGON2ID, $options);
    }

    /**
     * Überprüft ein Passwort
     *
     * @param string $password Passwort
     * @param string $hash Hash
     * @return bool True, wenn das Passwort korrekt ist, sonst false
     */
    public function verify(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * Prüft, ob ein Passwort-Hash neu gehasht werden sollte
     *
     * @param string $hash Hash
     * @param array|null $options Optionale Argon2id-Parameter
     * @return bool True, wenn neu gehasht werden sollte, sonst false
     */
    public function needsRehash(string $hash, ?array $options = null): bool
    {
        $options ??= self::ARGON_OPTIONS;

        return password_needs_rehash($hash, PASSWORD_ARGON2ID, $options);
    }

    /**
     * Erstellt einen HMAC
     *
     * @param string $data Daten
     * @param string $key Schlüssel
     * @param string $algo Algorithmus
     * @return string HMAC
     */
    public function hmac(string $data, string $key, string $algo = 'sha256'): string
    {
        return hash_hmac($algo, $data, $key);
    }

    /**
     * Berechnet einen Hash
     *
     * @param string $data Daten
     * @param string $algo Algorithmus
     * @return string Hash
     */
    public function make(string $data, string $algo = 'sha256'): string
    {
        return hash($algo, $data);
    }

    /**
     * Generiert einen zufälligen String
     *
     * @param int $length Länge des Strings
     * @param bool $rawOutput Raw-Bytes zurückgeben
     * @return string Zufälliger String
     */
    public function random(int $length = 32, bool $rawOutput = false): string
    {
        $randomBytes = random_bytes($length);

        return $rawOutput ? $randomBytes : bin2hex($randomBytes);
    }
}