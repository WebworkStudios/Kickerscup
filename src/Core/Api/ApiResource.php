<?php

declare(strict_types=1);

namespace App\Core\Api;

use App\Core\Database\Paginator;
use App\Core\Http\Response;
use App\Core\Http\ResponseFactory;

/**
 * API-Ressource für standardisierte API-Antworten
 */
class ApiResource
{
    /**
     * ResourceFactory-Instanz
     */
    private ResourceFactory $factory;

    /**
     * ResponseFactory-Instanz
     */
    private ResponseFactory $responseFactory;

    /**
     * Konstruktor
     *
     * @param ResourceFactory $factory
     * @param ResponseFactory $responseFactory
     */
    public function __construct(ResourceFactory $factory, ResponseFactory $responseFactory)
    {
        $this->factory = $factory;
        $this->responseFactory = $responseFactory;
    }

    /**
     * Erstellt eine erfolgreiche API-Antwort mit einer einzelnen Ressource
     *
     * @param mixed $model Das Modell
     * @param string $resourceClass Die Ressourcenklasse
     * @param int $status HTTP-Statuscode
     * @return Response
     */
    public function item(mixed $model, string $resourceClass, int $status = 200): Response
    {
        $data = $this->factory->make($model, $resourceClass);
        return $this->responseFactory->success($data, $status);
    }

    /**
     * Erstellt eine erfolgreiche API-Antwort mit einer Ressourcensammlung
     *
     * @param array $models Die Modelle
     * @param string $resourceClass Die Ressourcenklasse
     * @param int $status HTTP-Statuscode
     * @return Response
     */
    public function collection(array $models, string $resourceClass, int $status = 200): Response
    {
        $data = $this->factory->collection($models, $resourceClass);
        return $this->responseFactory->success($data, $status);
    }

    /**
     * Erstellt eine erfolgreiche API-Antwort mit einer paginierten Ressourcensammlung
     *
     * @param Paginator $paginator Der Paginator
     * @param string $resourceClass Die Ressourcenklasse
     * @param int $status HTTP-Statuscode
     * @return Response
     */
    public function paginate(Paginator $paginator, string $resourceClass, int $status = 200): Response
    {
        $result = $this->factory->paginate($paginator, $resourceClass);

        return $this->responseFactory->json([
            'success' => true,
            'data' => $result['data'],
            'meta' => $result['meta']
        ], $status);
    }

    /**
     * Erstellt eine erfolgreiche API-Antwort ohne Daten
     *
     * @param string $message Erfolgs-Nachricht
     * @param int $status HTTP-Statuscode
     * @return Response
     */
    public function message(string $message, int $status = 200): Response
    {
        return $this->responseFactory->json([
            'success' => true,
            'message' => $message
        ], $status);
    }

    /**
     * Erstellt eine Fehlerantwort
     *
     * @param string $message Fehlermeldung
     * @param string $errorCode Fehlercode
     * @param array $details Fehlerdetails
     * @param int $status HTTP-Statuscode
     * @return Response
     */
    public function error(
        string $message,
        string $errorCode = 'ERROR',
        array  $details = [],
        int    $status = 400
    ): Response
    {
        return $this->responseFactory->error($message, $errorCode, $details, $status);
    }

    /**
     * Erstellt eine erfolgreiche API-Antwort für eine Erstellungsoperation
     *
     * @param mixed $model Das erstellte Modell
     * @param string $resourceClass Die Ressourcenklasse
     * @return Response
     */
    public function created(mixed $model, string $resourceClass): Response
    {
        $data = $this->factory->make($model, $resourceClass);
        return $this->responseFactory->success($data, 201);
    }

    /**
     * Erstellt eine erfolgreiche API-Antwort für eine Löschoperation
     *
     * @param string|null $message Optionale Erfolgsmeldung
     * @return Response
     */
    public function deleted(?string $message = null): Response
    {
        return $this->responseFactory->json([
            'success' => true,
            'message' => $message ?? 'Die Ressource wurde erfolgreich gelöscht.'
        ], 200);
    }

    /**
     * Erstellt eine "No Content"-Antwort (204)
     *
     * @return Response
     */
    public function noContent(): Response
    {
        return $this->responseFactory->noContent();
    }
}