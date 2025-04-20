<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Container\Container;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Http\ResponseFactory;
use App\Core\Middleware\Middleware;
use App\Core\Middleware\MiddlewareStack;
use App\Core\Routing\Router;
use App\Core\Security\Csrf;
use App\Core\Security\Hash;
use App\Core\Security\JWT;
use App\Core\Security\JWTAuth;
use App\Core\Security\Security;
use App\Core\Security\Session;

/**
 * Hauptklasse der API-Anwendung
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
     * @var MiddlewareStack
     */
    private MiddlewareStack $middlewareStack;

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

        $this->middlewareStack = new MiddlewareStack();

        // Kern-Services registrieren
        $this->registerCoreServices();

        // Datenbank initialisieren
        $this->initializeDatabase();

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

        // ResourceFactory registrieren
        $this->container->singleton(\App\Core\Api\ResourceFactory::class);

        // Translator als Singleton registrieren
        $this->container->singleton(\App\Core\Translation\Translator::class, function ($container) {
            $cache = null;
            if ($container->has('App\Core\Cache\Cache')) {
                $cache = $container->make('App\Core\Cache\Cache');
            }

            return new \App\Core\Translation\Translator(
                config('app.locale', 'de'),
                config('app.fallback_locale', 'en'),
                $cache
            );
        });

        $this->container->singleton(Security::class, function($container) {
            return new Security(
                $container->make(Session::class),
                $container->make(Csrf::class),
                $container->make(Hash::class)
            );
        });

        $this->container->singleton(Csrf::class, function($container) {
            return new Csrf(
                $container->make(Session::class)
            );
        });

        // JWT-Services registrieren
        $this->container->singleton(JWT::class);

        $this->container->singleton(JWTAuth::class, function ($container) {
            return new JWTAuth(
                $container->make(JWT::class),
                config('auth.jwt.secret', env('JWT_SECRET', 'your-secret-key')),
                config('auth.jwt.algorithm', JWT::ALGO_HS256),
                config('auth.jwt.lifetime', 3600)
            );
        });

        // Cache-Service registrieren
        $this->container->bind(\App\Core\Cache\Cache::class, function ($container) {
            $cacheConfig = config('cache.default', 'file');

            if ($cacheConfig === 'redis') {
                $redisClient = new \Predis\Client(config('database.redis.cache'));
                return new \App\Core\Cache\RedisCache($redisClient);
            }

            // Fallback auf File-Cache
            $cachePath = storage_path('cache');
            if (!is_dir($cachePath)) {
                mkdir($cachePath, 0755, true);
            }
            return new \App\Core\Cache\FileCache($cachePath);
        });

        // Datenbank-Manager registrieren
        $this->container->singleton(\App\Core\Database\DatabaseManager::class, function ($container) {
            $config = config('database.connections', []);
            $defaultConnection = config('database.default', 'default');

            $dbManager = new \App\Core\Database\DatabaseManager($config, $defaultConnection);
            return $dbManager;
        });

        // ErrorHandler registrieren
        $this->container->singleton(\App\Core\Error\ErrorHandler::class, function ($container) {
            return new \App\Core\Error\ErrorHandler(
                $container->make(ResponseFactory::class),
                config('app.debug', false)
            );
        });

        // ApiResource registrieren
        $this->container->singleton(\App\Core\Api\ApiResource::class, function ($container) {
            return new \App\Core\Api\ApiResource(
                $container->make(\App\Core\Api\ResourceFactory::class),
                $container->make(ResponseFactory::class)
            );
        });
    }

    /**
     * Initialisiert die Datenbankverbindung
     *
     * @return bool
     */
    private function initializeDatabase(): bool
    {
        try {
            $dbManager = $this->container->make(\App\Core\Database\DatabaseManager::class);

            // Prüfen, ob die Verbindung hergestellt werden kann
            $dbManager->connection()->getPdo();

            return true;
        } catch (\Exception $e) {
            app_log('Datenbankverbindung konnte nicht hergestellt werden: ' . $e->getMessage(), [], 'error');
            return false;
        }
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
     * Fügt eine Middleware zur Anwendung hinzu
     *
     * @param Middleware $middleware Die hinzuzufügende Middleware
     * @return self
     */
    public function addMiddleware(Middleware $middleware): self
    {
        $this->middlewareStack->add($middleware);
        return $this;
    }

    /**
     * Verarbeitet den Request und gibt eine Response zurück
     */
    public function handle(Request $request): Response
    {
        // Request im Container registrieren
        $this->container->singleton(Request::class, fn() => $request);

        // Middleware-Handler mit der Core-Anwendungslogik als innersten Handler
        try {
            return $this->middlewareStack->process($request, function (Request $request) {
                try {
                    // Route finden
                    $route = $this->router->resolve($request);

                    if ($route === null) {
                        throw new \App\Core\Error\NotFoundException(
                            "Die Route für {$request->getMethod()} {$request->getUri()} wurde nicht gefunden."
                        );
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
                            throw new \App\Core\Error\BadRequestException(
                                'Controller hat keine __invoke-Methode',
                                'CONTROLLER_INVALID'
                            );
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
                    throw new \App\Core\Error\BadRequestException(
                        'Route-Action ist nicht aufrufbar',
                        'ACTION_NOT_CALLABLE'
                    );
                } catch (\Throwable $e) {
                    // Fehlerbehandler verwenden
                    return $this->container->make(\App\Core\Error\ErrorHandler::class)->handleError($e, $request);
                }
            });
        } catch (\Throwable $e) {
            // Globale Fehlerbehandlung für Fehler in der Middleware
            return $this->container->make(\App\Core\Error\ErrorHandler::class)->handleError($e, $request);
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

    /**
     * Gibt den Basis-Pfad der Anwendung zurück
     */
    public function getBasePath(): string
    {
        return $this->basePath;
    }

    /**
     * Gibt die MiddlewareStack zurück
     */
    public function getMiddlewareStack(): MiddlewareStack
    {
        return $this->middlewareStack;
    }

    /**
     * Gibt an, ob die Anwendung im Debug-Modus läuft
     */
    public function isDebugMode(): bool
    {
        return config('app.debug', false);
    }

    /**
     * Beendet die Anwendung und gibt den Statuscode zurück
     */
    public function terminate(int $status = 0): int
    {
        // Datenbankverbindungen schließen
        try {
            $this->container->make(\App\Core\Database\DatabaseManager::class)->disconnect();
        } catch (\Throwable $e) {
        }

        return $status;
    }
}