<?php


declare(strict_types=1);

namespace App\Infrastructure\Session\Contracts;

interface SessionInterface
{
    /**
     * Startet eine neue Session oder verwendet eine bestehende
     *
     * @return bool True, wenn die Session erfolgreich gestartet wurde
     */
    public function start(): bool;

    /**
     * Beendet die aktuelle Session
     *
     * @return bool True, wenn die Session erfolgreich beendet wurde
     */
    public function destroy(): bool;

    /**
     * Regeneriert die Session-ID
     *
     * @param bool $deleteOldSession Ob die alte Session gelöscht werden soll
     * @return bool True, wenn die Session-ID erfolgreich regeneriert wurde
     */
    public function regenerate(bool $deleteOldSession = true): bool;

    /**
     * Speichert einen Wert in der Session
     *
     * @param string $key Der Schlüssel
     * @param mixed $value Der zu speichernde Wert
     * @return static
     */
    public function set(string $key, mixed $value): static;

    /**
     * Holt einen Wert aus der Session
     *
     * @param string $key Der Schlüssel
     * @param mixed $default Der Standardwert, falls der Schlüssel nicht existiert
     * @return mixed Der gespeicherte Wert oder der Standardwert
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Prüft, ob ein Schlüssel in der Session existiert
     *
     * @param string $key Der zu prüfende Schlüssel
     * @return bool True, wenn der Schlüssel existiert
     */
    public function has(string $key): bool;

    /**
     * Entfernt einen Schlüssel aus der Session
     *
     * @param string $key Der zu entfernende Schlüssel
     * @return static
     */
    public function remove(string $key): static;

    /**
     * Speichert eine Flash-Message in der Session
     *
     * @param string $key Der Schlüssel
     * @param mixed $value Der Wert
     * @return static
     */
    public function flash(string $key, mixed $value): static;

    /**
     * Holt eine Flash-Message aus der Session und entfernt sie
     *
     * @param string $key Der Schlüssel
     * @param mixed $default Der Standardwert
     * @return mixed Der Wert oder der Standardwert
     */
    public function getFlash(string $key, mixed $default = null): mixed;

    /**
     * Gibt die aktuelle Session-ID zurück
     *
     * @return string|null Die Session-ID oder null, wenn keine Session aktiv ist
     */
    public function getId(): ?string;

    /**
     * Setzt die Session-ID
     *
     * @param string $id Die neue Session-ID
     * @return static
     */
    public function setId(string $id): static;

    /**
     * Gibt den Session-Namen zurück
     *
     * @return string Der Session-Name
     */
    public function getName(): string;

    /**
     * Setzt den Session-Namen
     *
     * @param string $name Der neue Session-Name
     * @return static
     */
    public function setName(string $name): static;

    /**
     * Löscht alle Daten aus der Session
     *
     * @return static
     */
    public function clear(): static;

    /**
     * Gibt alle Session-Daten zurück
     *
     * @return array Alle Session-Daten
     */
    public function getAll(): array;

    /**
     * Gibt den User-Agent des aktuellen Benutzers zurück
     *
     * @return string|null Der User-Agent oder null, wenn nicht vorhanden
     */
    public function getUserAgent(): ?string;

    /**
     * Gibt den Zeitpunkt der letzten Aktivität zurück
     *
     * @return int|null Der Zeitpunkt der letzten Aktivität oder null, wenn nicht gesetzt
     */
    public function getLastActivity(): ?int;

    /**
     * Schreibt alle Daten in die Session und beendet sie.
     *
     * @return bool True, wenn die Session erfolgreich gespeichert wurde
     */
    public function flush(): bool;

    /**
     * Überprüft, ob die Session gültig ist (nicht abgelaufen, Fingerprint stimmt, etc.)
     *
     * @return bool True, wenn die Session gültig ist
     */
    public function isValid(): bool;

    /**
     * Speichert einen Fingerprint in der Session
     *
     * @return void
     */
    public function saveFingerprint(): void;

    /**
     * Überprüft, ob der aktuelle Fingerprint mit dem gespeicherten übereinstimmt
     *
     * @return bool True, wenn der Fingerprint übereinstimmt
     */
    public function validateFingerprint(): bool;

    /**
     * Generiert ein CSRF-Token und speichert es in der Session
     *
     * @param string $key Der Schlüssel für das Token
     * @return string Das generierte Token
     */
    public function generateCsrfToken(string $key = 'csrf'): string;

    /**
     * Validiert ein CSRF-Token
     *
     * @param string $token Das zu validierende Token
     * @param string $key Der Schlüssel für das Token
     * @return bool True, wenn das Token gültig ist
     */
    public function validateCsrfToken(string $token, string $key = 'csrf'): bool;

    /**
     * Prüft, ob die Session inaktiv ist und aktualisiert den Timestamp
     *
     * @return bool True, wenn die Session aktiv ist (nicht abgelaufen)
     */
    public function checkActivity(): bool;

    /**
     * Rotiert die Session-ID basierend auf Zeit oder Events
     *
     * @param bool $force Erzwingt die Rotation, unabhängig vom Intervall
     * @return bool True, wenn die Session-ID erfolgreich rotiert wurde
     */
    public function rotateId(bool $force = false): bool;
}