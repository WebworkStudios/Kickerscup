<?php


declare(strict_types=1);

namespace App\Infrastructure\Application;

use App\Infrastructure\Container\Attributes\Injectable;
use App\Infrastructure\Container\Attributes\Singleton;
use App\Infrastructure\Container\Contracts\ContainerInterface;
use App\Infrastructure\Container\Exceptions\ContainerException;
use App\Infrastructure\Container\Exceptions\NotFoundException;
use App\Infrastructure\Http\Contracts\RequestFactoryInterface;
use App\Infrastructure\Http\Contracts\ResponseInterface;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Logging\Contracts\LoggerInterface;
use App\Infrastructure\Routing\Contracts\RouterInterface;
use App\Infrastructure\Session\Contracts\SessionInterface;
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
        $this->logger->debug('Request created', [
            'method' => $request->getMethod(),
            'path' => $request->getPath(),
            'ip' => $request->getClientIp()
        ]);

        try {
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

        try {
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
     * Behandelt Ausnahmen, die während der Verarbeitung auftreten
     *
     * @param Throwable $e Die aufgetretene Ausnahme
     * @param Request $request Der aktuelle Request
     * @return ResponseInterface
     * @throws ContainerException
     * @throws NotFoundException
     */
    protected function handleException(Throwable $e, Request $request): ResponseInterface
    {
        // Rufe den zentralen Exception-Handler auf
        try {
            $exceptionHandler = $this->container->get(ExceptionHandlerInterface::class);
            $exceptionHandler->handle($e, ['request' => $request]);
        } catch (Throwable $handlerException) {
            // Fallback wenn Exception-Handler selbst eine Exception wirft
            $this->logger->critical('Error in exception handler', [
                'original_exception' => get_class($e) . ': ' . $e->getMessage(),
                'handler_exception' => $handlerException->getMessage()
            ]);
        }

        // Erstelle eine Response basierend auf der Exception
        return $this->createExceptionResponse($e, $request);
    }

    /**
     * Erstellt eine passende Response für eine Exception
     *
     * @param Throwable $e Die aufgetretene Ausnahme
     * @param Request $request Der aktuelle Request
     * @return ResponseInterface
     * @throws ContainerException
     * @throws NotFoundException
     */
    protected function createExceptionResponse(Throwable $e, Request $request): ResponseInterface
    {
        $factory = $this->container->get('App\Infrastructure\Http\Contracts\ResponseFactoryInterface');

        // Je nach Exception-Typ eine passende Response erstellen
        return match (true) {
            $e instanceof \App\Infrastructure\Routing\Exceptions\RouteNotFoundException =>
            $factory->createNotFound('Die angeforderte Seite wurde nicht gefunden.'),

            $e instanceof \App\Infrastructure\Routing\Exceptions\MethodNotAllowedException =>
            $factory->createMethodNotAllowed('Die HTTP-Methode ist für diese Route nicht erlaubt.'),

            $e instanceof \App\Infrastructure\Container\Exceptions\NotFoundException,
                $e instanceof \App\Infrastructure\Container\Exceptions\BindingResolutionException =>
            $factory->createServerError('Ein interner Serverfehler ist aufgetreten.'),

            default => $factory->createServerError('Ein Fehler ist aufgetreten: ' . $this->getSafeExceptionMessage($e))
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
        $isProduction = ($_ENV['APP_ENV'] ?? 'production') === 'production';

        if ($isProduction) {
            return 'Ein unerwarteter Fehler ist aufgetreten.';
        }

        // In anderen Umgebungen die tatsächliche Fehlermeldung zurückgeben
        return $e->getMessage();
    }
}