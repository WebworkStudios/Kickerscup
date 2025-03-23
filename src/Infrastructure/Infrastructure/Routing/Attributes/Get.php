<?php

declare(strict_types=1);

namespace App\Infrastructure\Routing\Attributes;

use Attribute;

/**
 * GET Route Attribut
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
class Get extends Route
{
    /**
     * Konstruktor
     *
     * @param string $path Der Pfad der Route
     * @param string|null $name Optionaler Name für die Route
     */
    public function __construct(
        string  $path,
        ?string $name = null
    )
    {
        parent::__construct($path, 'GET', $name);
    }
}
