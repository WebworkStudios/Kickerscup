<?php


declare(strict_types=1);

namespace App\Infrastructure\Application;

use App\Infrastructure\Container\Attributes\Injectable;
use App\Infrastructure\Container\Attributes\Singleton;
use App\Infrastructure\Container\Contracts\ContainerInterface;
use App\Infrastructure\Http\Contracts\RequestFactoryInterface;
use App\Infrastructure\Http\Contracts\ResponseInterface;
use App\Infrastructure\Routing\Contracts\RouterInterface;
use App\Infrastructure\Session\Contracts\SessionInterface;
use App\Infrastructure\Http\Request;

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
        } catch (\Throwable $e) {
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
     */
    public function handle(Request $request): ResponseInterface
    {
        // Session am Anfang des Requests starten und validieren
        $this->session->start();

        try {
            // Router ausführen
            $response = $this->router->dispatch($request);
        } catch (\Throwable $e) {
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
     * @param \Throwable $e Die aufgetretene Ausnahme
     * @param Request $request Der aktuelle Request
     * @return ResponseInterface
     */
    protected function handleException(\Throwable $e, Request $request): ResponseInterface
    {
        // Hier würde eine spezifische Fehlerbehandlung stattfinden
        // Im einfachsten Fall könnten Sie den Exception-Handler des Routers verwenden

        // Senden Sie einen 500 Internal Server Error zurück, wenn kein spezifischer Handler existiert
        return $this->container->get('App\Infrastructure\Http\Contracts\ResponseFactoryInterface')
            ->createServerError('Ein Fehler ist aufgetreten: ' . $e->getMessage());
    }
}