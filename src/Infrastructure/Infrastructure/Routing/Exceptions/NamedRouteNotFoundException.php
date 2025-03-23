<?php
declare(strict_types=1);

namespace App\Infrastructure\Routing\Exceptions;

/**
 * Wird geworfen, wenn ein benannter Route nicht gefunden wurde
 */
class NamedRouteNotFoundException extends RoutingException
{
}