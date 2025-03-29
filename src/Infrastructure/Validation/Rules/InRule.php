<?php


declare(strict_types=1);

namespace App\Infrastructure\Validation\Rules;

use App\Infrastructure\Container\Attributes\Injectable;

#[Injectable]
class InRule implements ValidationRuleInterface
{
    /**
     * {@inheritdoc}
     */
    public function validate(mixed $value, array $params, string $field): bool
    {
        if (empty($params)) {
            return false;
        }

        // Prüfe, ob der Wert in der Liste der erlaubten Werte enthalten ist
        $strictMode = $params[count($params) - 1] === 'strict';
        $allowedValues = $strictMode ? array_slice($params, 0, -1) : $params;

        if ($strictMode) {
            return in_array($value, $allowedValues, true);
        }

        return in_array($value, $allowedValues);
    }

    /**
     * {@inheritdoc}
     */
    public function getMessage(): string
    {
        return "Das Feld :field muss einen der erlaubten Werte enthalten.";
    }
}