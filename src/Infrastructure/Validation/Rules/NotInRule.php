<?php


declare(strict_types=1);

namespace App\Infrastructure\Validation\Rules;

use App\Infrastructure\Container\Attributes\Injectable;

#[Injectable]
class NotInRule implements ValidationRuleInterface
{
    /**
     * {@inheritdoc}
     */
    public function validate(mixed $value, array $params, string $field): bool
    {
        if (empty($params)) {
            return true;
        }

        // Prüfe, ob der Wert nicht in der Liste der verbotenen Werte enthalten ist
        $strictMode = $params[count($params) - 1] === 'strict';
        $forbiddenValues = $strictMode ? array_slice($params, 0, -1) : $params;

        if ($strictMode) {
            return !in_array($value, $forbiddenValues, true);
        }

        return !in_array($value, $forbiddenValues);
    }

    /**
     * {@inheritdoc}
     */
    public function getMessage(): string
    {
        return "Das Feld :field darf keinen der verbotenen Werte enthalten.";
    }
}