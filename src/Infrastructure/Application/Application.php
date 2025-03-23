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
        protected SessionInterface        $session
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
        // Session am Anfang des Requests starten und validieren
        $this->session->start();

        // Request erstellen
        $request = $this->requestFactory->createFromGlobals();

        try {
            // Router ausführen
            $response = $this->router->dispatch($request);
        } catch (Throwable $e) {
            // Fehlerbehandlung - hier könnten Sie einen Error-Handler aufrufen
            $response = $this->handleException($e, $request);
        } finally {
            // Session am Ende des Requests speichern
            $this->session->flush();
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
        // Log error details including request information
        error_log("Error handling request to {$request->getPath()}: {$e->getMessage()}");

        // Oder den Request an einen spezifischeren Error-Handler übergeben
        return $this->container->get('App\Infrastructure\Http\Contracts\ResponseFactoryInterface')
            ->createServerError('Ein Fehler ist aufgetreten: ' . $e->getMessage());
    }
}