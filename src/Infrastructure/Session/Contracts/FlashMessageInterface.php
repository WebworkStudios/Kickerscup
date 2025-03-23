<?php


declare(strict_types=1);

namespace App\Infrastructure\Session\Contracts;

interface FlashMessageInterface
{
    /**
     * Speichert eine Flash-Message
     *
     * @param string $key Der Schlüssel
     * @param mixed $value Der Wert
     * @return static
     */
    public function add(string $key, mixed $value): static;

    /**
     * Holt eine Flash-Message und entfernt sie
     *
     * @param string $key Der Schlüssel
     * @param mixed $default Der Standardwert
     * @return mixed Der Wert oder der Standardwert
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Prüft, ob eine Flash-Message existiert
     *
     * @param string $key Der Schlüssel
     * @return bool True, wenn die Flash-Message existiert
     */
    public function has(string $key): bool;

    /**
     * Holt alle Flash-Messages
     *
     * @return array Alle Flash-Messages
     */
    public function all(): array;

    /**
     * Entfernt alle Flash-Messages
     *
     * @return static
     */
    public function clear(): static;

    /**
     * Lädt alte Flash-Messages für den nächsten Request
     *
     * @return void
     */
    public function load(): void;

    /**
     * Bewahrt alle aktuellen Flash-Messages für den nächsten Request auf
     *
     * @return void
     */
    public function keep(): void;
}