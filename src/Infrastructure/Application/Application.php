<?php

declare(strict_types=1);

namespace App\Infrastructure\Application;

use App\Infrastructure\Container\Attributes\Injectable;
use App\Infrastructure\Container\Attributes\Singleton;
use App\Infrastructure\Container\Contracts\ContainerInterface;
use App\Infrastructure\Container\Exceptions\BindingResolutionException;
use App\Infrastructure\Container\Exceptions\ContainerException;
use App\Infrastructure\Container\Exceptions\NotFoundException;
use App\Infrastructure\ErrorHandling\Contracts\ExceptionHandlerInterface;
use App\Infrastructure\Http\Contracts\RequestFactoryInterface;
use App\Infrastructure\Http\Contracts\RequestInterface;
use App\Infrastructure\Http\Contracts\ResponseFactoryInterface;
use App\Infrastructure\Http\Contracts\ResponseInterface;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Logging\Contracts\LoggerInterface;
use App\Infrastructure\Routing\Contracts\RouterInterface;
use App\Infrastructure\Routing\Exceptions\MethodNotAllowedException;
use App\Infrastructure\Routing\Exceptions\RouteNotFoundException;
use App\Infrastructure\Session\Contracts\SessionInterface;
use App\Infrastructure\Validation\Contracts\ValidatorInterface;
use App\Infrastructure\Validation\ValidationException;
use Throwable;

#[Injectable]
#[Singleton]
class Application
{
    /**
     * Konstruktor
     */
    public function __construct(
        protected ContainerInterface       $container,
        protected RequestFactoryInterface  $requestFactory,
        protected ResponseFactoryInterface $responseFactory,
        protected RouterInterface          $router,
        protected ?SessionInterface        $session = null,
    )
    {
        // Entferne die direkte Auflösung im Konstruktor, um zirkuläre Abhängigkeiten zu vermeiden
    }

    /**
     * Führt die Anwendung aus
     *
     * @return ResponseInterface
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function run(): ResponseInterface
    {
        $this->getLoggerIfNeeded()?->info('Application starting');

        // Erstelle Request nur, wenn keiner im Container gebunden ist
        if (!$this->container->has(RequestInterface::class)) {
            $request = $this->requestFactory->createFromGlobals();
            $this->container->bind(RequestInterface::class, $request);
            $this->container->bind(Request::class, $request);
        } else {
            $request = $this->container->get(RequestInterface::class);
        }

        $this->getLoggerIfNeeded()?->debug('Request initialized', [
            'method' => $request->getMethod(),
            'path' => $request->getPath(),
            'ip' => $request->getClientIp()
        ]);

        // Session nur bei Bedarf starten (z.B. nicht für API-Anfragen)
        if ($this->shouldStartSession($request)) {
            $this->getSessionIfNeeded()?->start();
        }

        try {
            // Validiere den Request, falls notwendig
            if ($this->shouldValidateRequest($request)) {
                $this->validateRequest($request);
            }

            // Router ausführen
            $response = $this->router->dispatch($request);
            $this->getLoggerIfNeeded()?->info('Request processed successfully', [
                'status' => $response->getStatusCode()
            ]);
        } catch (Throwable $e) {
            $this->getLoggerIfNeeded()?->error('Exception during request processing', [
                'exception' => get_class($e),
                'message' => $e->getMessage()
            ]);
            // Fehlerbehandlung - hier könnten Sie einen Error-Handler aufrufen
            $response = $this->handleException($e, $request);
        } finally {
            // Session nur speichern, wenn sie gestartet wurde
            $session = $this->getSessionIfNeeded();
            if ($session && $session->isStarted()) {
                $session->flush();
            }
            $this->getLoggerIfNeeded()?->debug('Request finished');
        }

        return $response;
    }

    /**
     * Lädt den Logger bei Bedarf
     *
     * @return LoggerInterface|null
     */
    private function getLoggerIfNeeded(): ?LoggerInterface
    {
        static $logger = null;

        if ($logger === null && $this->container->has(LoggerInterface::class)) {
            try {
                $logger = $this->container->get(LoggerInterface::class);
            } catch (Throwable) {
                // Fallback auf null
            }
        }

        return $logger;
    }

    /**
     * Bestimmt, ob die Session für diesen Request gestartet werden soll
     *
     * @param RequestInterface $request
     * @return bool
     */
    protected function shouldStartSession(RequestInterface $request): bool
    {
        // Prüfe Pfad schneller mit statischem Regex-Muster
        static $staticAssetsPattern = '~^/(assets|images|css|js|favicon\.ico|robots\.txt)~';

        $path = $request->getPath();
        if (preg_match($staticAssetsPattern, $path)) {
            return false;
        }

        // API-Erkennung effizienter gestalten
        if ($request->getHeader('Accept') === 'application/json' ||
            $request->getHeader('Content-Type') === 'application/json' ||
            $request->hasHeader('X-API-Key')) {
            return false;
        }

        // Options-Anfragen (CORS preflight) benötigen keine Session
        if ($request->getMethod() === 'OPTIONS') {
            return false;
        }

        return true;
    }

    /**
     * Lädt die Session bei Bedarf
     *
     * @return SessionInterface|null
     */
    private function getSessionIfNeeded(): ?SessionInterface
    {
        if ($this->session === null && $this->container->has(SessionInterface::class)) {
            try {
                $this->session = $this->container->get(SessionInterface::class);
            } catch (Throwable) {
                // Ignoriere Fehler, um zirkuläre Abhängigkeiten zu vermeiden
            }
        }
        return $this->session;
    }

    /**
     * Prüft, ob der Request validiert werden sollte
     *
     * @param RequestInterface $request
     * @return bool
     */
    protected function shouldValidateRequest(RequestInterface $request): bool
    {
        // Nur POST, PUT, PATCH Requests validieren, aber nur wenn es
        // einen entsprechenden Route-Handler gibt
        if (!in_array($request->getMethod(), ['POST', 'PUT', 'PATCH'])) {
            return false;
        }

        // Match-Informationen über die Route holen
        $match = $this->router->match($request);
        if (!$match || !isset($match['route']['handler'])) {
            return false;
        }

        // Validation nur durchführen, wenn Regeln vorhanden
        $rules = $this->getValidationRules($request);
        return !empty($rules);
    }



    protected function getValidationRules(RequestInterface $request): array
    {
        $route = $this->router->match($request);
        if ($route && isset($route['route']['handler'])) {
            $handler = $route['route']['handler'];

            // Prüfe, ob der Handler ein Objekt oder eine Klasse ist
            if (is_object($handler) && method_exists($handler, 'getValidationRules')) {
                return $handler->getValidationRules();
            }

            // Prüfe, ob der Handler ein Array [Controller, Methode] ist
            if (is_array($handler) && count($handler) === 2) {
                if (is_object($handler[0]) && method_exists($handler[0], 'getValidationRules')) {
                    return $handler[0]->getValidationRules();
                } else if (is_string($handler[0]) && method_exists($handler[0], 'getValidationRules')) {
                    try {
                        $controller = $this->container->get($handler[0]);
                        if (method_exists($controller, 'getValidationRules')) {
                            return $controller->getValidationRules();
                        }
                    } catch (Throwable) {
                        // Ignore resolution errors
                    }
                }
            }

            // Prüfe, ob der Handler ein String (Klassenname) ist
            if (is_string($handler) && class_exists($handler) && method_exists($handler, 'getValidationRules')) {
                try {
                    $instance = $this->container->get($handler);
                    return $instance->getValidationRules();
                } catch (Throwable) {
                    // Ignore resolution errors
                }
            }
        }

        return [];
    }

    /**
     * Prüft, ob der Request validiert werden sollte
     *
     * @param RequestInterface $request
     * @return bool
     */
// src/Infrastructure/Application/Application.php

    /**
     * Validiert den Request
     *
     * @param RequestInterface $request
     * @throws ContainerException
     * @throws NotFoundException
     */

    protected function validateRequest(RequestInterface $request): void
    {
        // Validator aus dem Container holen
        $validator = $this->container->get(ValidatorInterface::class);

        // Validierungsregeln basierend auf Route oder Controller ermitteln
        $rules = $this->getValidationRules($request);

        if (empty($rules)) {
            return; // Keine Regeln, keine Validierung notwendig
        }

        // Daten für die Validierung sammeln
        $data = [];

        // JSON-Body-Daten zuerst prüfen und mit höchster Priorität einfügen
        if ($request->isJson()) {
            $jsonData = $request->getJsonBody();
            if (!empty($jsonData) && is_array($jsonData)) {
                $data = $jsonData; // Ersetze $data komplett durch $jsonData
            }
        } else {
            // POST-Daten hinzufügen, wenn keine JSON-Anfrage
            $postData = $request->getPostData();
            if (!empty($postData)) {
                $data = $postData;
            }

            // Query-Parameter als Ergänzung für POST-Anfragen
            $queryParams = $request->getQueryParams();
            if (!empty($queryParams)) {
                // Nur Felder hinzufügen, die noch nicht gesetzt sind
                foreach ($queryParams as $key => $value) {
                    if (!isset($data[$key])) {
                        $data[$key] = $value;
                    }
                }
            }
        }

        // Daten validieren
        $isValid = $validator->validate($data, $rules);

        if (!$isValid) {
            throw ValidationException::withErrors(
                'Validation failed',
                $validator->getErrors()
            );
        }
    }

    /**
     * Behandelt Ausnahmen, die während der Verarbeitung auftreten
     *
     * @param Throwable $e Die aufgetretene Ausnahme
     * @param RequestInterface $request Der aktuelle Request
     * @return ResponseInterface
     * @throws ContainerException
     * @throws NotFoundException
     */
    protected function handleException(Throwable $e, RequestInterface $request): ResponseInterface
    {
        // Rufe den zentralen Exception-Handler auf
        try {
            $exceptionHandler = $this->container->get(ExceptionHandlerInterface::class);
            $exceptionHandler->handle($e, ['request' => $request]);
        } catch (Throwable $handlerException) {
            // Fallback, wenn Exception-Handler selbst eine Exception wirft
            $this->getLoggerIfNeeded()?->critical('Error in exception handler', [
                'original_exception' => get_class($e) . ': ' . $e->getMessage(),
                'handler_exception' => $handlerException->getMessage()
            ]);
        }

        // Erstelle eine Response basierend auf der Exception
        return $this->createExceptionResponse($e, $request);
    }

    /**
     * Ermittelt die Validierungsregeln für den Request
     *
     * @param RequestInterface $request
     * @return array<string, string|array<string>>
     */
    // src/Infrastructure/Application/Application.php
// Korrigieren wir die getValidationRules-Methode, Zeile ~306
    /**
     * Verarbeitet eine eingehende Anfrage mit einem benutzerdefinierten Request-Objekt
     *
     * @param Request $request Das zu verarbeitende Request-Objekt
     * @return ResponseInterface
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function handle(Request $request): ResponseInterface
    {
        // Session am Anfang des Requests starten und validieren
        $this->getSessionIfNeeded()?->start();

        // Binde den aktuellen Request im Container
        $this->container->bind(RequestInterface::class, $request);
        $this->container->bind(Request::class, $request);

        try {
            // Validiere den Request, falls notwendig
            if ($this->shouldValidateRequest($request)) {
                $this->validateRequest($request);
            }

            // Router ausführen
            $response = $this->router->dispatch($request);
        } catch (Throwable $e) {
            // Fehlerbehandlung
            $response = $this->handleException($e, $request);
        } finally {
            // Session am Ende des Requests speichern
            $this->getSessionIfNeeded()?->flush();
        }

        return $response;
    }

    /**
     * Erstellt eine passende Response für eine Exception
     *
     * @param Throwable $e Die aufgetretene Ausnahme
     * @param RequestInterface $request Der aktuelle Request
     * @return ResponseInterface
     * @throws ContainerException
     * @throws NotFoundException
     */
    protected function createExceptionResponse(Throwable $e, RequestInterface $request): ResponseInterface
    {
        // Je nach Exception-Typ eine passende Response erstellen
        return match (true) {
            $e instanceof ValidationException =>
            $this->handleValidationException($e, $request),

            $e instanceof RouteNotFoundException =>
            $this->responseFactory->createNotFound('Die angeforderte Seite wurde nicht gefunden.'),

            $e instanceof MethodNotAllowedException =>
            $this->responseFactory->createMethodNotAllowed('Die HTTP-Methode ist für diese Route nicht erlaubt.'),

            $e instanceof NotFoundException,
                $e instanceof BindingResolutionException =>
            $this->responseFactory->createServerError('Ein interner Serverfehler ist aufgetreten.'),

            default => $this->responseFactory->createServerError('Ein Fehler ist aufgetreten: ' . $this->getSafeExceptionMessage($e))
        };
    }

    /**
     * Behandelt Validierungsfehler
     *
     * @param ValidationException $exception
     * @param RequestInterface $request
     * @return ResponseInterface
     */
    protected function handleValidationException(ValidationException $exception, RequestInterface $request): ResponseInterface
    {
        // Erstelle eine Response für Validierungsfehler
        // bei API-Anfragen: JSON-Response mit Fehlern
        // bei Web-Anfragen: Redirect zurück mit Fehlern in der Session

        if ($request->isJson()) {
            return $this->responseFactory->createJson(['errors' => $exception->getErrors()], 422);
        }

        // Fehler in die Session schreiben
        $session = $this->getSessionIfNeeded();
        if ($session) {
            $session->flash('errors', $exception->getErrors());

            // Alte Eingabedaten in die Session schreiben
            $inputData = array_merge(
                $request->getQueryParams(),
                $request->getPostData()
            );
            $session->flash('old', $inputData);
        }

        // Zurück zur vorherigen Seite
        return $this->responseFactory->createRedirect($request->getHeader('Referer') ?? '/');
    }

    /**
     * Liefert eine sichere Fehlermeldung basierend auf der Umgebung
     *
     * @param Throwable $e Die Exception
     * @return string Die sichere Fehlermeldung
     * @throws ContainerException
     * @throws NotFoundException
     */
    protected function getSafeExceptionMessage(Throwable $e): string
    {
        // In der Produktionsumgebung nur generische Fehlermeldungen anzeigen
        $isProduction = $this->container->get('config')->get('app.env') === 'production';

        if ($isProduction) {
            return 'Ein unerwarteter Fehler ist aufgetreten.';
        }

        // In anderen Umgebungen die tatsächliche Fehlermeldung zurückgeben
        return $e->getMessage();
    }
}