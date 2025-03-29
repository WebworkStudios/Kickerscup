<?php


declare(strict_types=1);

namespace App\Infrastructure\Validation\Rules;

use App\Infrastructure\Container\Attributes\Injectable;

#[Injectable]
class ComparisonRule implements ValidationRuleInterface
{
    /**
     * {@inheritdoc}
     */
    public function validate(mixed $value, array $params, string $field): bool
    {
        if (count($params) < 2) {
            return false;
        }

        $operator = $params[0];
        $compareValue = $params[1];

        // Stelle sicher, dass die Werte vergleichbar sind
        if (!$this->canCompare($value, $compareValue)) {
            return false;
        }

        return match ($operator) {
            '=', '==' => $value == $compareValue,
            '!=', '<>' => $value != $compareValue,
            '>' => $value > $compareValue,
            '>=' => $value >= $compareValue,
            '<' => $value < $compareValue,
            '<=' => $value <= $compareValue,
            default => false,
        };
    }

    /**
     * Prüft, ob zwei Werte verglichen werden können
     */
    private function canCompare(mixed $a, mixed $b): bool
    {
        // Erlaubt Vergleiche für skalare Werte: string, int, float, bool
        return (is_scalar($a) || $a === null) && (is_scalar($b) || $b === null);
    }

    /**
     * {@inheritdoc}
     */
    public function getMessage(): string
    {
        return "Das Feld :field erfüllt nicht die Vergleichsbedingung.";
    }
}