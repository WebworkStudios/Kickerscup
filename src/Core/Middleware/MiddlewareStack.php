<?php

declare(strict_types=1);

namespace App\Core\Middleware;

use App\Core\Http\Request;
use App\Core\Http\Response;

/**
 * Middleware-Stack für die Anwendung
 *
 * Verwaltet und führt Middleware-Ketten aus
 */
class MiddlewareStack
{
    /**
     * Registrierte Middleware
     *
     * @var array<Middleware>
     */
    private array $middleware = [];

    /**
     * Registriert eine Middleware
     *
     * @param Middleware $middleware Die zu registrierende Middleware
     * @return self
     */
    public function add(Middleware $middleware): self
    {
        $this->middleware[] = $middleware;
        return $this;
    }

    /**
     * Führt die Middleware-Kette aus
     *
     * @param Request $request Der zu verarbeitende Request
     * @param callable $coreHandler Die Core-Anwendungs-Handler-Funktion
     * @return Response Die erzeugte Response
     */
    public function process(Request $request, callable $coreHandler): Response
    {
        // Erstellen eines verschachtelten Handlers, der die Middleware-Kette durchläuft
        $handler = array_reduce(
            array_reverse($this->middleware),
            function (callable $next, Middleware $middleware) {
                return function (Request $request) use ($middleware, $next): Response {
                    try {
                        return $middleware->process($request, $next);
                    } catch (\Throwable $e) {
                        // Log exceptions and continue to avoid middleware bugs breaking the application
                        app_log(
                            'Fehler in Middleware: ' . $e->getMessage(),
                            ['middleware' => get_class($middleware), 'exception' => $e],
                            'error'
                        );
                        return $next($request);
                    }
                };
            },
            $coreHandler
        );

        return $handler($request);
    }

    /**
     * Gibt alle registrierten Middleware zurück
     *
     * @return array<Middleware> Registrierte Middleware
     */
    public function getAll(): array
    {
        return $this->middleware;
    }
}