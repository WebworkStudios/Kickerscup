<?php

declare(strict_types=1);

namespace App\Infrastructure\Validation\Rules;

use App\Infrastructure\Container\Attributes\Injectable;
use DateTime;

#[Injectable]
class DateRule implements ValidationRuleInterface
{
    /**
     * {@inheritdoc}
     */
    public function validate(mixed $value, array $params, string $field): bool
    {
        if (!is_string($value)) {
            return false;
        }

        $format = $params[0] ?? 'Y-m-d';

        $date = DateTime::createFromFormat($format, $value);
        return $date && $date->format($format) === $value;
    }

    /**
     * {@inheritdoc}
     */
    public function getMessage(): string
    {
        return "Das Feld :field muss ein gültiges Datum sein.";
    }
}