<?php

declare(strict_types=1);

namespace App\Infrastructure\Validation\Rules;

use App\Infrastructure\Container\Attributes\Injectable;

#[Injectable]
class RequiredRule implements ValidationRuleInterface
{
    /**
     * {@inheritdoc}
     */
    public function validate(mixed $value, array $params, string $field): bool
    {
        // Wenn es ein String ist, prüfen wir, ob er nicht leer ist (nach dem Trimmen)
        if (is_string($value)) {
            return trim($value) !== '';
        }

        // Wenn es ein Array ist, prüfen wir, ob es nicht leer ist
        if (is_array($value)) {
            return !empty($value);
        }

        // Spezialfall für numerische Werte (0 ist gültig)
        if (is_numeric($value)) {
            return true;
        }

        // Prüfe auf null, leere Strings und false
        return $value !== null && $value !== false && $value !== '';
    }

    /**
     * {@inheritdoc}
     */
    public function getMessage(): string
    {
        return "Das Feld :field ist erforderlich.";
    }
}