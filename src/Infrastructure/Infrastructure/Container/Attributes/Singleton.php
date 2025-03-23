<?php
declare(strict_types=1);

namespace App\Infrastructure\Container\Attributes;

use Attribute;

/**
 * Markiert eine Klasse als Singleton
 */
#[Attribute(Attribute::TARGET_CLASS)]
class Singleton
{
}