<?php

declare(strict_types=1);

namespace App\Core\Middleware;

use App\Core\Http\Request;
use App\Core\Http\Response;

/**
 * Middleware-Interface für HTTP-Verarbeitung
 *
 * Implementiert das Middleware-Pattern für Request/Response-Verarbeitung
 */
interface Middleware
{
    /**
     * Verarbeitet einen Request und gibt ihn an den nächsten Handler weiter
     *
     * @param Request $request Eingehender Request
     * @param callable $next Nächster Handler in der Kette
     * @return Response Resultierende Response
     */
    public function process(Request $request, callable $next): Response;
}