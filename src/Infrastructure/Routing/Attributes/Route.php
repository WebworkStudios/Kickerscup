<?php
declare(strict_types=1);

namespace App\Infrastructure\Routing\Attributes;

use Attribute;

/**
 * Route Basisattribut
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
class Route
{
    /**
     * Konstruktor
     *
     * @param string $path Der Pfad der Route (z.B. '/users/{id}')
     * @param array|string $methods Erlaubte HTTP-Methoden für diese Route
     * @param string|null $name Optionaler Name für die Route (für URL-Generierung)
     */
    public function __construct(
        public readonly string       $path,
        public readonly array|string $methods = ['GET'],
        public readonly ?string      $name = null
    )
    {
    }
}
