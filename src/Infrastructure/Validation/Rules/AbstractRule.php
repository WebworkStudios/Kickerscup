<?php


declare(strict_types=1);

namespace App\Infrastructure\Validation\Rules;

abstract class AbstractRule implements ValidationRuleInterface
{
    /**
     * Standardfehlermeldung für die Regel
     */
    protected string $message = 'Die Validierung für das Feld :field ist fehlgeschlagen.';

    /**
     * {@inheritdoc}
     */
    public function getMessage(): string
    {
        return $this->message;
    }
}