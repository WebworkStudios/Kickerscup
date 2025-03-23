<?php

declare(strict_types=1);

namespace App\Infrastructure\Security\Csrf\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
readonly class CsrfProtection
{
    /**
     * Konstruktor
     *
     * @param bool $enabled Ob der CSRF-Schutz aktiviert ist
     * @param string $tokenKey Schlüssel für das zu verwendende Token
     * @param int|null $tokenLifetime Lebensdauer des Tokens in Sekunden
     * @param bool $validateOrigin Ob zusätzlich der Origin validiert werden soll
     */
    public function __construct(
        public bool   $enabled = true,
        public string $tokenKey = 'default',
        public ?int   $tokenLifetime = null,
        public bool   $validateOrigin = false
    )
    {
    }
}