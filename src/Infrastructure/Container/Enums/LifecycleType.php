<?php
declare(strict_types=1);

namespace App\Infrastructure\Container\Enums;

enum LifecycleType
{
    case Singleton;
    case Scoped;
    case Transient;
}