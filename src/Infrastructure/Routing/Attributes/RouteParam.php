<?php


declare(strict_types=1);

namespace App\Infrastructure\Routing\Attributes;

use Attribute;


/**
 * Parameter Attribut für Route-Parameter
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
class RouteParam
{
    /**
     * Konstruktor
     *
     * @param string|null $regex Optionaler regulärer Ausdruck für Validierung
     * @param bool $optional Gibt an, ob der Parameter optional ist
     * @param mixed $default Standardwert für optionale Parameter
     */
    public function __construct(
        public readonly ?string $regex = null,
        public readonly bool    $optional = false,
        public readonly mixed   $default = null
    )
    {
    }
}