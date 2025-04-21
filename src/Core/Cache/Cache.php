<?php

declare(strict_types=1);

namespace App\Core\Cache;

/**
 * Erweitertes Cache-Interface mit besserer Granularität
 */
interface Cache
{
    /**
     * Prüft, ob ein Schlüssel im Cache existiert
     *
     * @param string $key Schlüssel
     * @return bool True, wenn der Schlüssel existiert, sonst false
     */
    public function has(string $key): bool;

    /**
     * Gibt einen Wert aus dem Cache zurück
     *
     * @param string $key Schlüssel
     * @param mixed $default Standardwert, wenn der Schlüssel nicht existiert
     * @return mixed Wert oder Standardwert
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Speichert einen Wert im Cache
     *
     * @param string $key Schlüssel
     * @param mixed $value Wert
     * @param int|null $ttl Gültigkeitsdauer in Sekunden (null für unbegrenzt)
     * @return bool True bei Erfolg, sonst false
     */
    public function set(string $key, mixed $value, ?int $ttl = null): bool;

    /**
     * Löscht einen Wert aus dem Cache
     *
     * @param string $key Schlüssel
     * @return bool True bei Erfolg, sonst false
     */
    public function delete(string $key): bool;

    /**
     * Leert den gesamten Cache
     *
     * @return bool True bei Erfolg, sonst false
     */
    public function clear(): bool;

    /**
     * Gibt einen Wert aus dem Cache zurück oder speichert das Ergebnis einer Callback-Funktion
     *
     * @param string $key Schlüssel
     * @param int|null $ttl Gültigkeitsdauer in Sekunden (null für unbegrenzt)
     * @param callable $callback Callback-Funktion, die den Wert erzeugt
     * @return mixed Wert aus dem Cache oder Ergebnis der Callback-Funktion
     */
    public function remember(string $key, ?int $ttl, callable $callback): mixed;

    /**
     * Löscht mehrere Werte aus dem Cache anhand eines Musters
     *
     * @param string $pattern Muster (z.B. "user_*")
     * @return bool True bei Erfolg, sonst false
     */
    public function deletePattern(string $pattern): bool;

    /**
     * Speichert mehrere Werte gleichzeitig im Cache
     *
     * @param array<string, mixed> $values Schlüssel-Wert-Paare
     * @param int|null $ttl Gültigkeitsdauer in Sekunden (null für unbegrenzt)
     * @return bool True bei Erfolg, sonst false
     */
    public function setMultiple(array $values, ?int $ttl = null): bool;

    /**
     * Holt mehrere Werte gleichzeitig aus dem Cache
     *
     * @param array<string> $keys Liste von Schlüsseln
     * @param mixed $default Standardwert für nicht gefundene Schlüssel
     * @return array<string, mixed> Schlüssel-Wert-Paare
     */
    public function getMultiple(array $keys, mixed $default = null): array;

    /**
     * Löscht mehrere Schlüssel gleichzeitig
     *
     * @param array<string> $keys Liste von Schlüsseln
     * @return bool True bei Erfolg, sonst false
     */
    public function deleteMultiple(array $keys): bool;

    /**
     * Inkrementiert einen numerischen Wert im Cache
     *
     * @param string $key Schlüssel
     * @param int $amount Inkrementierungswert (default: 1)
     * @return int|false Neuer Wert oder false bei Fehler
     */
    public function increment(string $key, int $amount = 1): int|false;

    /**
     * Dekrementiert einen numerischen Wert im Cache
     *
     * @param string $key Schlüssel
     * @param int $amount Dekrementierungswert (default: 1)
     * @return int|false Neuer Wert oder false bei Fehler
     */
    public function decrement(string $key, int $amount = 1): int|false;

    /**
     * Speichert einen Wert im Cache, nur wenn der Schlüssel noch nicht existiert
     *
     * @param string $key Schlüssel
     * @param mixed $value Wert
     * @param int|null $ttl Gültigkeitsdauer in Sekunden (null für unbegrenzt)
     * @return bool True bei Erfolg, sonst false
     */
    public function add(string $key, mixed $value, ?int $ttl = null): bool;
}