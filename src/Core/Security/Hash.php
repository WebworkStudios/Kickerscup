<?php

declare(strict_types=1);

namespace App\Core\Security;

/**
 * Hash-Funktionen mit PHP 8.4 Features
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
     * Unterstützte Hash-Algorithmen
     */
    public const SUPPORTED_ALGOS = ['sha256', 'sha384', 'sha512', 'xxh128', 'xxh3', 'xxh64'];

    /**
     * Hasht ein Passwort mit Argon2id oder dem angegebenen Algorithmus
     *
     * @param string $password Passwort
     * @param string|null $algo Optional. Algorithmus (PASSWORD_ARGON2ID, PASSWORD_BCRYPT)
     * @param array|null $options Optional. Algorithmus-Parameter
     * @return string Gehashtes Passwort
     */
    public function password(string $password, ?string $algo = null, ?array $options = null): string
    {
        $algo ??= PASSWORD_ARGON2ID;
        $options ??= self::ARGON_OPTIONS;

        // Wenn Argon2id nicht verfügbar ist, auf bcrypt zurückfallen
        if ($algo === PASSWORD_ARGON2ID && !$this->isAlgoAvailable(PASSWORD_ARGON2ID)) {
            $algo = PASSWORD_BCRYPT;
            $options = ['cost' => 12]; // Äquivalente Sicherheit
            app_log('Argon2id nicht verfügbar, Fallback auf bcrypt', [], 'warning');
        }

        return password_hash($password, $algo, $options);
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
     * @param string|null $algo Optional. Algorithmus
     * @param array|null $options Optional. Algorithmus-Parameter
     * @return bool True, wenn neu gehasht werden sollte, sonst false
     */
    public function needsRehash(string $hash, ?string $algo = null, ?array $options = null): bool
    {
        $algo ??= PASSWORD_ARGON2ID;
        $options ??= self::ARGON_OPTIONS;

        return password_needs_rehash($hash, $algo, $options);
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
        // Überprüfen, ob der Algorithmus von hash_hmac unterstützt wird
        if (!in_array($algo, hash_hmac_algos(), true)) {
            throw new \InvalidArgumentException("Algorithmus $algo wird nicht unterstützt");
        }

        return hash_hmac($algo, $data, $key);
    }

    /**
     * Berechnet einen Hash mit Unterstützung für die neuen xxHash-Algorithmen in PHP 8.4
     *
     * @param string $data Daten
     * @param string $algo Algorithmus
     * @return string Hash
     */
    public function make(string $data, string $algo = 'sha256'): string
    {
        // Überprüfen, ob der Algorithmus unterstützt wird
        if (!in_array($algo, hash_algos(), true)) {
            throw new \InvalidArgumentException("Algorithmus $algo wird nicht unterstützt");
        }

        return hash($algo, $data);
    }

    /**
     * Generiert einen zufälligen String mit verbesserter Typisierung
     *
     * @param int $length Länge der zufälligen Bytes
     * @param bool $rawOutput Raw-Bytes zurückgeben
     * @return string Zufälliger String
     */
    public function random(int $length = 32, bool $rawOutput = false): string
    {
        $randomBytes = random_bytes($length);

        return $rawOutput ? $randomBytes : bin2hex($randomBytes);
    }

    /**
     * Generiert einen Base64-codierten zufälligen String (URL-sicher)
     *
     * @param int $length Länge der zufälligen Bytes
     * @return string Base64-codierter String
     */
    public function randomBase64(int $length = 32): string
    {
        $randomBytes = random_bytes($length);
        return rtrim(strtr(base64_encode($randomBytes), '+/', '-_'), '=');
    }

    /**
     * Überprüft, ob ein Passwort-Algorithmus verfügbar ist
     *
     * @param string|int $algo Der Algorithmus (PASSWORD_ARGON2ID, etc.)
     * @return bool True wenn verfügbar, sonst false
     */
    public function isAlgoAvailable(string|int $algo): bool
    {
        return in_array($algo, password_algos(), true);
    }

    /**
     * Analysiert, ob die aktuellen Argon2id-Kosten für die Hardware geeignet sind
     * Gibt eine Empfehlung zurück, wenn die Kosten angepasst werden sollten
     *
     * @param array|null $currentOptions Aktuelle Optionen oder null für Standardwerte
     * @param float $targetTime Zielzeit in Sekunden (0.05 = 50ms)
     * @return array Empfohlene Optionen und Informationen
     */
    public function analyzeArgonCosts(?array $currentOptions = null, float $targetTime = 0.05): array
    {
        $currentOptions ??= self::ARGON_OPTIONS;

        // Ist Argon2id verfügbar?
        if (!$this->isAlgoAvailable(PASSWORD_ARGON2ID)) {
            return [
                'available' => false,
                'message' => 'Argon2id ist nicht verfügbar',
                'recommendation' => 'Verwenden Sie bcrypt mit cost=12'
            ];
        }

        // Zeit messen für aktuelles Setup
        $startTime = microtime(true);
        password_hash('benchmark_password', PASSWORD_ARGON2ID, $currentOptions);
        $duration = microtime(true) - $startTime;

        $recommendedOptions = $currentOptions;
        $needsAdjustment = false;

        // Wenn die Hash-Dauer zu kurz ist (< 80% des Ziels), Kosten erhöhen
        if ($duration < $targetTime * 0.8) {
            $recommendedOptions['time_cost'] = min($currentOptions['time_cost'] + 1, 10);
            $needsAdjustment = true;
        }
        // Wenn die Hash-Dauer zu lang ist (> 120% des Ziels), Kosten reduzieren
        elseif ($duration > $targetTime * 1.2) {
            $recommendedOptions['time_cost'] = max($currentOptions['time_cost'] - 1, 3);
            $needsAdjustment = true;
        }

        return [
            'available' => true,
            'current_options' => $currentOptions,
            'current_duration' => $duration,
            'target_time' => $targetTime,
            'needs_adjustment' => $needsAdjustment,
            'recommended_options' => $recommendedOptions
        ];
    }

    /**
     * Erstellt einen schnellen (nicht-kryptographischen) Hash für Caching oder ähnliches
     * mit xxHash Algorithmen in PHP 8.4
     *
     * @param string $data Die zu hashenden Daten
     * @param string $algo Algorithmus (xxh3, xxh64, xxh128)
     * @return string Hash-Wert
     */
    public function fastHash(string $data, string $algo = 'xxh3'): string
    {
        $validAlgos = ['xxh3', 'xxh64', 'xxh128'];

        if (!in_array($algo, $validAlgos, true)) {
            throw new \InvalidArgumentException(
                "Ungültiger xxHash-Algorithmus: $algo. Erlaubt sind: " . implode(', ', $validAlgos)
            );
        }

        if (!in_array($algo, hash_algos(), true)) {
            throw new \RuntimeException("xxHash-Algorithmus $algo ist nicht verfügbar");
        }

        return hash($algo, $data);
    }
}