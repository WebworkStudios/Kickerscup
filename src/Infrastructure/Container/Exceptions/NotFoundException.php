<?php
declare(strict_types=1);

namespace App\Infrastructure\Container\Exceptions;

/**
 * Wird geworfen, wenn ein angeforderter Service nicht gefunden wurde
 */
class NotFoundException extends ContainerException
{
}