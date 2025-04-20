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
     * Registrierte Middleware mit Prioritäten
     *
     * Niedrigere Zahlen werden zuerst ausgeführt
     *
     * @var array<int, array{middleware: Middleware, priority: int}>
     */
    private array $middleware = [];

    /**
     * Registriert eine Middleware mit einer bestimmten Priorität
     *
     * @param Middleware $middleware Die zu registrierende Middleware
     * @param int $priority Priorität (kleinere Zahlen = frühere Ausführung)
     * @return self
     */
    public function add(Middleware $middleware, int $priority = 100): self
    {
        $this->middleware[] = [
            'middleware' => $middleware,
            'priority' => $priority
        ];
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
        // Middleware nach Priorität sortieren
        usort($this->middleware, fn($a, $b) => $a['priority'] <=> $b['priority']);

        // Nur die Middleware-Objekte extrahieren
        $middlewareInstances = array_map(fn($item) => $item['middleware'], $this->middleware);

        // Erstellen eines verschachtelten Handlers, der die Middleware-Kette durchläuft
        $handler = array_reduce(
            array_reverse($middlewareInstances),
            function (callable $next, Middleware $middleware) {
                return function (Request $request) use ($middleware, $next): Response {
                    try {
                        return $middleware->process($request, $next);
                    } catch (\App\Core\Error\HttpException $e) {
                        // Bekannte HTTP-Ausnahmen durchreichen
                        throw $e;
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
     * @return array<Middleware> Registrierte Middleware-Instanzen
     */
    public function getAll(): array
    {
        return array_map(fn($item) => $item['middleware'], $this->middleware);
    }
}