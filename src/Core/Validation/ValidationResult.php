<?php

declare(strict_types=1);

namespace App\Core\Validation;

/**
 * Ergebnis einer Validierung
 */
class ValidationResult
{
    /**
     * Konstruktor
     *
     * @param array $validated Validierte Daten
     * @param array $errors Fehlermeldungen
     */
    public function __construct(
        private readonly array $validated = [],
        private readonly array $errors = []
    )
    {
    }

    /**
     * Prüft, ob die Validierung fehlgeschlagen ist
     *
     * @return bool True, wenn fehlgeschlagen, sonst false
     */
    public function fails(): bool
    {
        return !$this->isValid();
    }

    /**
     * Prüft, ob die Validierung erfolgreich war
     *
     * @return bool True, wenn erfolgreich, sonst false
     */
    public function isValid(): bool
    {
        return empty($this->errors);
    }

    /**
     * Gibt die Fehlermeldungen zurück
     *
     * @return array Fehlermeldungen
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Gibt die Fehlermeldungen für ein bestimmtes Feld zurück
     *
     * @param string $field Feldname
     * @return array Fehlermeldungen
     */
    public function getError(string $field): array
    {
        return $this->errors[$field] ?? [];
    }

    /**
     * Gibt die erste Fehlermeldung für ein bestimmtes Feld zurück
     *
     * @param string $field Feldname
     * @return string|null Fehlermeldung oder null, wenn keine vorhanden ist
     */
    public function getFirstError(string $field): ?string
    {
        return $this->errors[$field][0] ?? null;
    }

    /**
     * Gibt die validierten Daten zurück
     *
     * @return array Validierte Daten
     */
    public function validated(): array
    {
        return $this->validated;
    }

    /**
     * Gibt einen validierten Wert zurück
     *
     * @param string $key Schlüssel
     * @param mixed $default Standardwert, wenn der Schlüssel nicht existiert
     * @return mixed Validierter Wert oder Standardwert
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->validated[$key] ?? $default;
    }

    /**
     * Prüft, ob ein Schlüssel in den validierten Daten existiert
     *
     * @param string $key Schlüssel
     * @return bool True, wenn der Schlüssel existiert, sonst false
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->validated);
    }

    /**
     * Gibt die validierten Daten mit bestimmten Schlüsseln zurück
     *
     * @param array $keys Schlüssel
     * @return array Validierte Daten
     */
    public function only(array $keys): array
    {
        return array_intersect_key($this->validated, array_flip($keys));
    }

    /**
     * Gibt die validierten Daten ohne bestimmte Schlüssel zurück
     *
     * @param array $keys Schlüssel
     * @return array Validierte Daten
     */
    public function except(array $keys): array
    {
        return array_diff_key($this->validated, array_flip($keys));
    }

    /**
     * Gibt die validierten Daten und Fehler als assoziatives Array zurück
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'valid' => $this->isValid(),
            'validated' => $this->validated,
            'errors' => $this->errors
        ];
    }
}