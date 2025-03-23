<?php
declare(strict_types=1);

namespace App\Infrastructure\Container\Exceptions;

/**
 * Wird geworfen, wenn ein Problem bei der Auflösung einer Abhängigkeit auftritt
 */
class BindingResolutionException extends ContainerException
{
}