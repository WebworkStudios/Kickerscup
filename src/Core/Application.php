<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Container\Container;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Http\ResponseFactory;
use App\Core\Routing\Router;

/**
 * Hauptklasse der Anwendung
 *
 * Verwaltet Container, Router und Request/Response-Handling
 */
class Application
{
    /**
     * Container für Dependency Injection
     */
    private Container $container;

    /**
     * Router für das Routing
     */
    private Router $router;

    /**
     * Konstruktor
     *
     * @param string $basePath Basis-Pfad der Anwendung
     */
    public function __construct(
        private readonly string $basePath
    )
    {
        // Container initialisieren
        $this->container = new Container();

        // Container global verfügbar machen, BEVOR irgendwelche anderen Operationen ausgeführt werden
        global $container;
        $container = $this->container;

        // Basis-Pfad registrieren
        $this->container->singleton('base_path', fn() => $this->basePath);

        // Router initialisieren
        $this->router = $this->container->make(Router::class);

        // Kern-Services registrieren
        $this->registerCoreServices();

        // Routen laden
        $this->loadRoutes();
    }

    /**
     * Registriert die Kern-Services
     */
    private function registerCoreServices(): void
    {
        // Request/Response
        $this->container->singleton(ResponseFactory::class);

        // Router als Singleton registrieren
        $this->container->singleton(Router::class, fn() => $this->router);
    }

    /**
     * Lädt die Routen aus der Konfigurationsdatei
     */
    private function loadRoutes(): void
    {
        $routesFile = $this->basePath . '/config/routes.php';
        $app = $this;

        if (file_exists($routesFile)) {
            require $routesFile;
        }
    }

    /**
     * Verarbeitet den Request und gibt eine Response zurück
     */
    public function handle(Request $request): Response
    {
        try {
            // Request im Container registrieren
            $this->container->singleton(Request::class, fn() => $request);

            // Route finden
            $route = $this->router->resolve($request);

            if ($route === null) {
                return $this->container->make(ResponseFactory::class)->notFound();
            }

            // Action auflösen
            $action = $route->getAction();

            // Wenn Action ein Array ist [Controller::class, 'method']
            if (is_array($action) && count($action) === 2 && is_string($action[0]) && is_string($action[1])) {
                $controller = $this->container->make($action[0]);
                $action = [$controller, $action[1]];
            }

            // Wenn Action ein Classname ist, instanziieren und invoke-Methode aufrufen
            if (is_string($action) && class_exists($action)) {
                $controller = $this->container->make($action);

                if (method_exists($controller, '__invoke')) {
                    $action = [$controller, '__invoke'];
                } else {
                    return $this->container->make(ResponseFactory::class)->serverError('Controller hat keine __invoke-Methode.');
                }
            }

            // Parameter vorbereiten
            $parameters = array_merge([$request], $route->getParameters());

            // Action ausführen
            if (is_callable($action)) {
                $response = $action(...$parameters);

                // Wenn keine Response zurückgegeben wurde, 204 No Content zurückgeben
                if (!$response instanceof Response) {
                    return $this->container->make(ResponseFactory::class)->noContent();
                }

                return $response;
            }

            // Wenn Action nicht aufrufbar ist, 500 Internal Server Error zurückgeben
            return $this->container->make(ResponseFactory::class)->serverError('Route-Action ist nicht aufrufbar.');
        } catch (\Throwable $e) {
            // Fehler protokollieren und 500 Internal Server Error zurückgeben
            log('error', $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->container->make(ResponseFactory::class)->serverError();
        }
    }

    /**
     * Gibt den Container zurück
     */
    public function getContainer(): Container
    {
        return $this->container;
    }

    /**
     * Gibt den Router zurück
     */
    public function getRouter(): Router
    {
        return $this->router;
    }
}