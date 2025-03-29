<?php


declare(strict_types=1);

namespace App\Infrastructure\Validation\Rules;

use App\Infrastructure\Container\Attributes\Injectable;

#[Injectable]
class ConfirmationRule implements ValidationRuleInterface
{
    /**
     * {@inheritdoc}
     */
    public function validate(mixed $value, array $params, string $field): bool
    {
        // Der erste Parameter muss der zu vergleichende Wert sein
        if (count($params) < 1) {
            return false;
        }

        $confirmationValue = $params[0];

        // Bei null oder nicht-skalaren Werten strenge Gleichheit prüfen
        if ($value === null || !is_scalar($value)) {
            return $value === $confirmationValue;
        }

        // Bei Strings Groß-/Kleinschreibung ignorieren, wenn gewünscht
        $ignoreCase = $params[1] ?? false;

        if (is_string($value) && is_string($confirmationValue) && $ignoreCase) {
            return strtolower($value) === strtolower($confirmationValue);
        }

        // Ansonsten normale Gleichheit prüfen
        return $value == $confirmationValue;
    }

    /**
     * {@inheritdoc}
     */
    public function getMessage(): string
    {
        return "Das Feld :field stimmt nicht mit dem Bestätigungsfeld überein.";
    }
}