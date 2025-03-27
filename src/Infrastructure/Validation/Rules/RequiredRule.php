<?php


declare(strict_types=1);

namespace App\Infrastructure\Validation\Rules;

use App\Infrastructure\Container\Attributes\Injectable;

#[Injectable]
class RequiredRule extends AbstractRule
{
    /**
     * {@inheritdoc}
     */
    protected string $message = 'Das Feld :field ist erforderlich.';

    /**
     * {@inheritdoc}
     */
    // src/Infrastructure/Validation/Rules/RequiredRule.php
// Korrigieren wir die validate-Methode, um sicherzustellen, dass leere Strings erkannt werden

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

        // Prüfe auf null, leere Strings und false
        return $value !== null && $value !== false && $value !== '';
    }
}