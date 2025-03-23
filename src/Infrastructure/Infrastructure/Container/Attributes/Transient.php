<?php
declare(strict_types=1);

namespace App\Infrastructure\Container\Attributes;

use Attribute;

/**
 * Markiert eine Klasse als Transient (neue Instanz bei jeder Anforderung)
 */
#[Attribute(Attribute::TARGET_CLASS)]
class Transient
{
}