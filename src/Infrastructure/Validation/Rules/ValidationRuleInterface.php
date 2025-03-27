<?php


declare(strict_types=1);

namespace App\Infrastructure\Validation\Rules;

interface ValidationRuleInterface
{
    /**
     * Validiert einen Wert gegen die Regel
     *
     * @param mixed $value Der zu validierende Wert
     * @param array<string, mixed> $params Parameter für die Regel
     * @param string $field Name des Feldes (für Fehlermeldungen)
     * @return bool True, wenn die Validierung erfolgreich ist
     */
    public function validate(mixed $value, array $params, string $field): bool;

    /**
     * Gibt die Standardfehlermeldung für die Regel zurück
     *
     * @return string Die Standardfehlermeldung
     */
    public function getMessage(): string;
}