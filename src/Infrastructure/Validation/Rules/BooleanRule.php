<?php


declare(strict_types=1);

namespace App\Infrastructure\Validation\Rules;

use App\Infrastructure\Container\Attributes\Injectable;

#[Injectable]
class BooleanRule implements ValidationRuleInterface
{
    /**
     * {@inheritdoc}
     */
    public function validate(mixed $value, array $params, string $field): bool
    {
        // Akzeptiere echte boolesche Werte
        if (is_bool($value)) {
            return true;
        }

        // Akzeptiere 0, 1, "0", "1", "true", "false" als boolesche Werte
        if (is_numeric($value)) {
            return $value === 0 || $value === 1 || $value === '0' || $value === '1';
        }

        if (is_string($value)) {
            $lowerValue = strtolower($value);
            return in_array($lowerValue, ['true', 'false', 'yes', 'no'], true);
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function getMessage(): string
    {
        return "Das Feld :field muss ein boolescher Wert sein.";
    }
}