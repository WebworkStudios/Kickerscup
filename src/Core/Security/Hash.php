<?php

declare(strict_types=1);

namespace App\Core\Security;

/**
 * Hash-Funktionen
 */
class Hash
{
    /**
     * Hasht ein Passwort mit Argon2id
     *
     * @param string $password Passwort
     * @return string Gehashtes Passwort
     */
    public function password(string $password): string
    {
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536, // 64 MB
            'time_cost' => 4,       // 4 Iterationen
            'threads' => 1          // 1 Thread (für Parallelisierungs-Resistenz)
        ]);
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
     * @return bool True, wenn neu gehasht werden sollte, sonst false
     */
    public function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,
            'time_cost' => 4,
            'threads' => 1
        ]);
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
}