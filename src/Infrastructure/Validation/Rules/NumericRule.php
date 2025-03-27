<?php


declare(strict_types=1);

namespace App\Infrastructure\Validation\Rules;

use App\Infrastructure\Container\Attributes\Injectable;

#[Injectable]
class NumericRule extends AbstractRule
{
    /**
     * {@inheritdoc}
     */
    protected string $message = 'Das Feld :field muss numerisch sein.';

    /**
     * {@inheritdoc}
     */
    public function validate(mixed $value, array $params, string $field): bool
    {
        return is_numeric($value);
    }
}