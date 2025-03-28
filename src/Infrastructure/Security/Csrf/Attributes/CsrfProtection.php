<?php

declare(strict_types=1);

namespace App\Infrastructure\Security\Csrf\Attributes;

use Attribute;

/**
 * CSRF-Schutz Attribut
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
readonly class CsrfProtection
{
    /**
     * Konstruktor
     *
     * @param bool $enabled Ob der CSRF-Schutz aktiviert ist
     * @param string $tokenKey Schlüssel zur Identifikation des Tokens
     * @param bool $validateOrigin Ob der Origin-Header validiert werden soll
     * @param array $allowedOrigins Erlaubte Origins (leer = nur die aktuelle Domain)
     */
    public function __construct(
        public bool   $enabled = true,
        public string $tokenKey = 'default',
        public bool   $validateOrigin = true,
        public array  $allowedOrigins = [],
    )
    {
    }
}