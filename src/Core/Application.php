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
use App\Core\Security\Auth;
use App\Core\Security\Hash;
use App\Core\Security\JWT;
use App\Core\Security\Security;
use App\Core\Security\TokenStorage;

/**
 * Hauptklasse der Framework-Anwendung
 *
 * Verwaltet Container, Router, Middleware und das Request/Response-Handling.
 * Bildet den zentralen Einstiegspunkt für die Anwendung und koordiniert
 * die Interaktion zwischen den verschiedenen Komponenten.
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
     * Middleware-Stack für die Request-Verarbeitung
     */
    private MiddlewareStack $middlewareStack;

    /**
     * Konstruktor
     *
     * Initialisiert die Kerndienste der Anwendung:
     * - Container (Dependency Injection)
     * - Router (URL-Routing)
     * - Middleware-Stack (Request-Verarbeitung)
     * - Grundlegende Services (Cache, Datenbank, etc.)
     *
     * @param string $basePath Basis-Pfad der Anwendung (für Ressourcen wie Konfiguration, Views, etc.)
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
        $this->registerBasePath();

        // Router initialisieren
        $this->router = $this->container->make(Router::class);

        // Middleware-Stack initialisieren
        $this->middlewareStack = new MiddlewareStack();

        // Core-Services registrieren
        $this->registerCoreServices();

        // Datenbank initialisieren
        $this->initializeDatabase();

        // Routen laden
        $this->loadRoutes();
    }

    /**
     * Registriert den Basis-Pfad im Container
     */
    private function registerBasePath(): void
    {
        $this->container->singleton('base_path', fn() => $this->basePath);
    }

    /**
     * Registriert die Kern-Services im Container
     *
     * Stellt sicher, dass alle grundlegenden Dienste verfügbar sind:
     * - Response/Request-Handling
     * - Router
     * - ResourceFactory
     * - Translator
     * - Authentifizierung & Sicherheit
     * - Cache
     * - Datenbank
     * - Fehlerbehandlung
     */
    private function registerCoreServices(): void
    {
        // HTTP-Services
        $this->container->singleton(ResponseFactory::class);
        $this->container->singleton(Router::class, fn() => $this->router);

        // API und Ressourcen
        $this->container->singleton(\App\Core\Api\ResourceFactory::class);
        $this->container->singleton(\App\Core\Api\ApiResource::class, function ($container) {
            return new \App\Core\Api\ApiResource(
                $container->make(\App\Core\Api\ResourceFactory::class),
                $container->make(ResponseFactory::class)
            );
        });

        // Übersetzung
        $this->registerTranslationService();

        // Authentifizierung & Sicherheit
        $this->registerSecurityServices();

        // Cache
        $this->registerCacheService();

        // Datenbank
        $this->registerDatabaseService();

        // Fehlerbehandlung
        $this->container->singleton(\App\Core\Error\ErrorHandler::class, function ($container) {
            return new \App\Core\Error\ErrorHandler(
                $container->make(ResponseFactory::class),
                config('app.debug', false)
            );
        });
    }

    /**
     * Registriert den Übersetzungsdienst
     */
    private function registerTranslationService(): void
    {
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
    }

    /**
     * Registriert die Sicherheits- und Authentifizierungsdienste
     */
    private function registerSecurityServices(): void
    {
        // JWT-Services registrieren
        $this->container->singleton(JWT::class);

        // Token-Speicher
        $this->container->singleton(TokenStorage::class, function ($container) {
            return new TokenStorage(
                $container->make('App\Core\Cache\Cache')
            );
        });

        // Auth-Service
        $this->container->singleton(Auth::class, function ($container) {
            return new Auth(
                $container->make(JWT::class),
                $container->make(TokenStorage::class),
                config('auth.jwt.secret', env('JWT_SECRET', 'your-secret-key')),
                config('auth.jwt.algorithm', JWT::ALGO_HS256),
                config('auth.jwt.lifetime', 3600)
            );
        });

        // Sicherheitsdienst
        $this->container->singleton(Security::class, function ($container) {
            return new Security(
                $container->make(Hash::class)
            );
        });
    }

    /**
     * Registriert den Cache-Dienst
     */
    private function registerCacheService(): void
    {
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
    }

    /**
     * Registriert den Datenbank-Dienst
     */
    private function registerDatabaseService(): void
    {
        $this->container->singleton(\App\Core\Database\DatabaseManager::class, function ($container) {
            $config = config('database.connections', []);
            $defaultConnection = config('database.default', 'default');

            return new \App\Core\Database\DatabaseManager($config, $defaultConnection);
        });
    }

    /**
     * Initialisiert die Datenbankverbindung
     *
     * @return bool True bei erfolgreicher Initialisierung, sonst false
     */
    private function initializeDatabase(): bool
    {
        try {
            $dbManager = $this->container->make(\App\Core\Database\DatabaseManager::class);

            // Prüfen, ob die Verbindung hergestellt werden kann
            $dbManager->connection()->getPdo();

            return true;
        } catch (\Throwable $e) {
            app_log('Datenbankverbindung konnte nicht hergestellt werden: ' . $e->getMessage(), [], 'error');
            return false;
        }
    }

    /**
     * Lädt die Routen aus der Konfigurationsdatei
     *
     * Lädt die im Routing-Verzeichnis definierten Routen und registriert
     * sie im Router der Anwendung.
     */
    private function loadRoutes(): void
    {
        $routesFile = $this->basePath . '/config/routes.php';

        if (file_exists($routesFile)) {
            // $app als Variable für das Routing-File bereitstellen
            $app = $this;
            require $routesFile;
        }
    }

    /**
     * Fügt eine Middleware zur Anwendung hinzu
     *
     * Middlewares werden in der Reihenfolge ausgeführt, in der sie hinzugefügt wurden.
     * Sie können zur Authentifizierung, Logging, CORS-Handling etc. verwendet werden.
     *
     * @param Middleware $middleware Die hinzuzufügende Middleware
     * @return self Für Method Chaining
     */
    public function addMiddleware(Middleware $middleware): self
    {
        $this->middlewareStack->add($middleware);
        return $this;
    }

    /**
     * Verarbeitet einen HTTP-Request und gibt eine Response zurück
     *
     * Dies ist der Haupteinstiegspunkt für die Request-Verarbeitung und wird
     * typischerweise vom Front-Controller aufgerufen.
     *
     * @param Request $request Der zu verarbeitende HTTP-Request
     * @return Response Die generierte HTTP-Response
     */
    public function handle(Request $request): Response
    {
        // Request im Container registrieren
        $this->container->singleton(Request::class, fn() => $request);

        try {
            // Request durch den Middleware-Stack und Core-Handler verarbeiten
            return $this->middlewareStack->process($request, function (Request $request) {
                try {
                    return $this->processRouteRequest($request);
                } catch (\Throwable $e) {
                    // Fehlerbehandler für Fehler in der Routenverarbeitung verwenden
                    return $this->container->make(\App\Core\Error\ErrorHandler::class)->handleError($e, $request);
                }
            });
        } catch (\Throwable $e) {
            // Globale Fehlerbehandlung für Fehler in der Middleware
            return $this->container->make(\App\Core\Error\ErrorHandler::class)->handleError($e, $request);
        }
    }

    /**
     * Verarbeitet einen Request gemäß der definierten Routen
     *
     * Sucht die passende Route, löst die Controller/Action auf und führt sie aus.
     *
     * @param Request $request Der zu verarbeitende Request
     * @return Response Die erzeugte Response
     * @throws \App\Core\Error\NotFoundException Wenn keine passende Route gefunden wurde
     * @throws \App\Core\Error\BadRequestException Wenn die Action nicht ausführbar ist
     */
    private function processRouteRequest(Request $request): Response
    {
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

        // Wenn Action nicht aufrufbar ist, 400 Bad Request zurückgeben
        throw new \App\Core\Error\BadRequestException(
            'Route-Action ist nicht aufrufbar',
            'ACTION_NOT_CALLABLE'
        );
    }

    /**
     * Gibt den Container zurück
     *
     * @return Container Der Dependency-Injection-Container
     */
    public function getContainer(): Container
    {
        return $this->container;
    }

    /**
     * Gibt den Router zurück
     *
     * @return Router Der Router für URL-Routing
     */
    public function getRouter(): Router
    {
        return $this->router;
    }

    /**
     * Gibt den Basis-Pfad der Anwendung zurück
     *
     * @return string Der Basispfad
     */
    public function getBasePath(): string
    {
        return $this->basePath;
    }

    /**
     * Gibt den Middleware-Stack zurück
     *
     * @return MiddlewareStack Der Middleware-Stack
     */
    public function getMiddlewareStack(): MiddlewareStack
    {
        return $this->middlewareStack;
    }

    /**
     * Gibt an, ob die Anwendung im Debug-Modus läuft
     *
     * @return bool True wenn im Debug-Modus, sonst false
     */
    public function isDebugMode(): bool
    {
        return config('app.debug', false);
    }

    /**
     * Beendet die Anwendung und gibt den Statuscode zurück
     *
     * Führt Aufräumarbeiten durch und schließt offene Ressourcen.
     *
     * @param int $status Exit-Status-Code
     * @return int Der Status-Code
     */
    public function terminate(int $status = 0): int
    {
        // Datenbankverbindungen schließen
        try {
            $this->container->make(\App\Core\Database\DatabaseManager::class)->disconnect();
        } catch (\Throwable) {
            // Fehler beim Schließen der Verbindungen ignorieren
        }

        return $status;
    }
}