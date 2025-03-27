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
        protected ContainerInterface      $container,
        protected RequestFactoryInterface $requestFactory,
        protected ResponseFactoryInterface $responseFactory,
        protected RouterInterface         $router,
        protected SessionInterface        $session,
        protected LoggerInterface         $logger
    )
    {
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
        $this->logger->info('Application starting');
        // Session am Anfang des Requests starten und validieren
        $this->session->start();

        // Request erstellen
        $request = $this->requestFactory->createFromGlobals();

        // Wichtig: Registriere den aktuellen Request im Container
        $this->container->bind(RequestInterface::class, $request);
        // Binde auch die konkrete Implementierung
        $this->container->bind(Request::class, $request);

        $this->logger->debug('Request created', [
            'method' => $request->getMethod(),
            'path' => $request->getPath(),
            'ip' => $request->getClientIp()
        ]);

        try {
            // Validiere den Request, falls notwendig
            if ($this->shouldValidateRequest($request)) {
                $this->validateRequest($request);
            }
            
            // Router ausführen
            $response = $this->router->dispatch($request);
            $this->logger->info('Request processed successfully', [
                'status' => $response->getStatusCode()
            ]);
        } catch (Throwable $e) {
            $this->logger->error('Exception during request processing', [
                'exception' => get_class($e),
                'message' => $e->getMessage()
            ]);
            // Fehlerbehandlung - hier könnten Sie einen Error-Handler aufrufen
            $response = $this->handleException($e, $request);
        } finally {
            // Session am Ende des Requests speichern
            $this->session->flush();
            $this->logger->debug('Request finished');
        }

        return $response;
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
            $this->logger->critical('Error in exception handler', [
                'original_exception' => get_class($e) . ': ' . $e->getMessage(),
                'handler_exception' => $handlerException->getMessage()
            ]);
        }

        // Erstelle eine Response basierend auf der Exception
        return $this->createExceptionResponse($e, $request);
    }

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
        $this->session->start();

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
            $this->session->flush();
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
     * Liefert eine sichere Fehlermeldung basierend auf der Umgebung
     *
     * @param Throwable $e Die Exception
     * @return string Die sichere Fehlermeldung
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

    /**
     * Prüft, ob der Request validiert werden sollte
     * 
     * @param RequestInterface $request
     * @return bool
     */
    protected function shouldValidateRequest(RequestInterface $request): bool
    {
        // Implementiere Logik, um zu entscheiden, ob der Request validiert werden soll
        // Beispiel: Nur POST, PUT, PATCH Requests validieren
        return in_array($request->getMethod(), ['POST', 'PUT', 'PATCH']);
    }

    /**
     * Validiert den Request
     * 
     * @param RequestInterface $request
     * @throws ValidationException
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
        $data = array_merge(
            $request->getQueryParams(),
            $request->getPostData()
        );
        
        // Daten validieren
        $isValid = $validator->validate($data, $rules);
        
        if (!$isValid) {
            throw new ValidationException('Validation failed', $validator->getErrors());
        }
    }

    /**
     * Ermittelt die Validierungsregeln für den Request
     * 
     * @param RequestInterface $request
     * @return array<string, string|array<string>>
     */
    protected function getValidationRules(RequestInterface $request): array
    {
        // Hier könnten Regeln aus verschiedenen Quellen kommen:
        // - Route-Attribute
        // - Controller-Methoden
        // - Konfigurationsdateien
        
        // Beispiel-Implementierung:
        $route = $this->router->match($request);
        if ($route && method_exists($route->getHandler(), 'getValidationRules')) {
            return $route->getHandler()->getValidationRules();
        }
        
        return [];
    }

    /**
     * Behandelt Validierungsfehler
     * 
     * @param ValidationException $exception
     * @param RequestInterface $request
     * @return ResponseInterface
     * @throws ContainerException
     * @throws NotFoundException
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
        $this->session->flash('errors', $exception->getErrors());
        
        // Alte Eingabedaten in die Session schreiben
        $inputData = array_merge(
            $request->getQueryParams(),
            $request->getPostData()
        );
        $this->session->flash('old', $inputData);
        
        // Zurück zur vorherigen Seite
        return $this->responseFactory->createRedirect($request->getHeader('Referer') ?? '/');
    }
}
