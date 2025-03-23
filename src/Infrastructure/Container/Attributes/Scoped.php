<?php
declare(strict_types=1);

namespace App\Infrastructure\Container\Attributes;

use Attribute;

/**
 * Markiert eine Klasse als Scoped (eine Instanz pro Scope, z.B. Request)
 */
#[Attribute(Attribute::TARGET_CLASS)]
class Scoped
{
}