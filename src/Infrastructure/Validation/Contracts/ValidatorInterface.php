<?php


declare(strict_types=1);

namespace App\Infrastructure\Validation\Contracts;

interface ValidatorInterface
{
    /**
     * Validiert Daten gegen definierte Regeln
     *
     * @param array<string, mixed> $data Die zu validierenden Daten
     * @param array<string, mixed> $rules Die Validierungsregeln
     * @return bool True, wenn alle Validierungen erfolgreich sind
     */
    public function validate(array $data, array $rules): bool;

    /**
     * Gibt alle nach der Validierung gefundenen Fehler zurück
     *
     * @return array<string, array<string>> Fehler nach Feld gruppiert
     */
    public function getErrors(): array;

    /**
     * Fügt eine benutzerdefinierte Validierungsregel hinzu
     *
     * @param string $name Name der Regel
     * @param callable $callback Callback-Funktion für die Validierung
     * @param string|null $errorMessage Optionale Fehlermeldung
     * @return static
     */
    public function addRule(string $name, callable $callback, ?string $errorMessage = null): static;

    /**
     * Validiert einen einzelnen Wert gegen eine bestimmte Regel
     *
     * @param mixed $value Der zu validierende Wert
     * @param string $rule Die anzuwendende Regel
     * @param array<string, mixed> $params Parameter für die Regel
     * @param string $field Name des Feldes (für Fehlermeldungen)
     * @return bool True, wenn die Validierung erfolgreich ist
     */
    public function validateSingle(mixed $value, string $rule, array $params = [], string $field = ''): bool;
}