<?php
declare(strict_types=1);

namespace App\Infrastructure\Validation\Rules;

use App\Infrastructure\Container\Attributes\Injectable;

#[Injectable]
class StringLengthRule implements ValidationRuleInterface
{
    /**
     * {@inheritdoc}
     */
    public function validate(mixed $value, array $params, string $field): bool
    {
        if (!is_string($value)) {
            return false;
        }

        $length = mb_strlen($value);

        // Exakte Länge prüfen
        if (count($params) === 1) {
            return $length === (int)$params[0];
        }

        // Min und Max prüfen
        if (count($params) === 2) {
            $min = (int)$params[0];
            $max = (int)$params[1];
            return $length >= $min && $length <= $max;
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function getMessage(): string
    {
        return "Das Feld :field hat nicht die richtige Länge.";
    }
}