<?php
declare(strict_types=1);

namespace App\Infrastructure\Container\Attributes;

use Attribute;

/**
 * Markiert eine Klasse als automatisch injizierbar
 */
#[Attribute(Attribute::TARGET_CLASS)]
class Injectable
{
    /**
     * @param string|null $alias Optional: Interface oder Name, unter dem die Klasse registriert werden soll
     */
    public function __construct(
        public readonly ?string $alias = null
    )
    {
    }
}