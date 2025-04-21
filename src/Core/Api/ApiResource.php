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

    // src/Core/Api/ApiResource.php
// Neue Methode hinzufügen:

    /**
     * Verarbeitet Batch-Operationen mit mehreren Ressourcen gleichzeitig
     *
     * @param array $operations Liste der Operationen
     * @param array $options Optionen für die Batch-Verarbeitung
     * @return Response
     */
    public function batch(array $operations, array $options = []): Response
    {
        $results = [];
        $hasErrors = false;
        $status = 200;

        // Transaktionsunterstützung, wenn angefordert und DatabaseManager vorhanden
        $useTransaction = ($options['useTransaction'] ?? false) && app()->has('App\Core\Database\DatabaseManager');
        $dbManager = $useTransaction ? app('App\Core\Database\DatabaseManager') : null;

        try {
            // Starte Transaktion, wenn angefordert
            if ($useTransaction) {
                $dbManager->beginTransaction();
            }

            foreach ($operations as $index => $operation) {
                $type = $operation['type'] ?? 'unknown';
                $resourceClass = $operation['resource'] ?? null;
                $data = $operation['data'] ?? [];
                $id = $operation['id'] ?? null;
                $params = $operation['params'] ?? [];

                // Individuelle Operation ausführen
                try {
                    $result = match ($type) {
                        'create' => $this->processBatchCreate($resourceClass, $data),
                        'update' => $this->processBatchUpdate($resourceClass, $id, $data),
                        'delete' => $this->processBatchDelete($resourceClass, $id),
                        'custom' => $this->processBatchCustom($resourceClass, $data, $params),
                        default => ['success' => false, 'error' => 'Ungültiger Operationstyp: ' . $type]
                    };

                    $results[$index] = $result;

                    if (isset($result['error'])) {
                        $hasErrors = true;
                    }
                } catch (\Throwable $e) {
                    $results[$index] = [
                        'success' => false,
                        'error' => $e->getMessage(),
                        'code' => $e instanceof \App\Core\Error\ApiException ? $e->getErrorCode() : 'INTERNAL_ERROR'
                    ];
                    $hasErrors = true;

                    // Bei kritischen Fehlern sofort abbrechen, wenn konfiguriert
                    if ($options['failFast'] ?? false) {
                        throw $e;
                    }
                }
            }

            // Transaktion abschließen, wenn verwendet
            if ($useTransaction) {
                if ($hasErrors && ($options['rollbackOnError'] ?? true)) {
                    $dbManager->rollBack();
                    // Status auf 422 setzen, wenn Fehler aufgetreten sind
                    $status = 422;
                } else {
                    $dbManager->commit();
                }
            } else if ($hasErrors) {
                // Auch ohne Transaktion Status setzen
                $status = 422;
            }

            return $this->responseFactory->json([
                'success' => !$hasErrors,
                'results' => $results
            ], $status);

        } catch (\Throwable $e) {
            // Bei Ausnahmen Transaktion zurückrollen
            if ($useTransaction && $dbManager->inTransaction()) {
                $dbManager->rollBack();
            }

            // Fehlermeldung zurückgeben
            return $this->responseFactory->error(
                'Batch-Operation fehlgeschlagen: ' . $e->getMessage(),
                $e instanceof \App\Core\Error\ApiException ? $e->getErrorCode() : 'BATCH_ERROR',
                ['operations_completed' => count($results)],
                500
            );
        }
    }

    /**
     * Verarbeitet eine einzelne Create-Operation im Batch
     *
     * @param string|null $resourceClass Ressourcenklasse
     * @param array $data Zu erstellende Daten
     * @return array Ergebnis der Operation
     */
    private function processBatchCreate(?string $resourceClass, array $data): array
    {
        if (!$resourceClass) {
            return ['success' => false, 'error' => 'Keine Ressourcenklasse angegeben'];
        }

        // Hier würde die eigentliche Erstellung stattfinden
        // Typischerweise Aufruf eines Services oder Repositories
        try {
            // Beispiel: $model = app()->make('App\Services\EntityService')->create($data);
            $model = $this->createEntity($resourceClass, $data);

            return [
                'success' => true,
                'data' => $this->factory->make($model, $resourceClass)
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'code' => $e instanceof \App\Core\Error\ApiException ? $e->getErrorCode() : 'CREATE_ERROR'
            ];
        }
    }

    /**
     * Verarbeitet eine einzelne Update-Operation im Batch
     *
     * @param string|null $resourceClass Ressourcenklasse
     * @param mixed $id ID des zu aktualisierenden Objekts
     * @param array $data Zu aktualisierende Daten
     * @return array Ergebnis der Operation
     */
    private function processBatchUpdate(?string $resourceClass, $id, array $data): array
    {
        if (!$resourceClass || $id === null) {
            return ['success' => false, 'error' => 'Ressourcenklasse oder ID fehlt'];
        }

        try {
            // Beispiel: $model = app()->make('App\Services\EntityService')->update($id, $data);
            $model = $this->updateEntity($resourceClass, $id, $data);

            return [
                'success' => true,
                'data' => $this->factory->make($model, $resourceClass)
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'code' => $e instanceof \App\Core\Error\ApiException ? $e->getErrorCode() : 'UPDATE_ERROR'
            ];
        }
    }

    /**
     * Verarbeitet eine einzelne Delete-Operation im Batch
     *
     * @param string|null $resourceClass Ressourcenklasse
     * @param mixed $id ID des zu löschenden Objekts
     * @return array Ergebnis der Operation
     */
    private function processBatchDelete(?string $resourceClass, $id): array
    {
        if (!$resourceClass || $id === null) {
            return ['success' => false, 'error' => 'Ressourcenklasse oder ID fehlt'];
        }

        try {
            // Beispiel: $result = app()->make('App\Services\EntityService')->delete($id);
            $result = $this->deleteEntity($resourceClass, $id);

            return [
                'success' => $result,
                'message' => $result ? 'Ressource erfolgreich gelöscht' : 'Ressource konnte nicht gelöscht werden'
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'code' => $e instanceof \App\Core\Error\ApiException ? $e->getErrorCode() : 'DELETE_ERROR'
            ];
        }
    }

    /**
     * Verarbeitet eine benutzerdefinierte Operation im Batch
     *
     * @param string|null $resourceClass Ressourcenklasse
     * @param array $data Operationsdaten
     * @param array $params Zusätzliche Parameter
     * @return array Ergebnis der Operation
     */
    private function processBatchCustom(?string $resourceClass, array $data, array $params): array
    {
        if (!$resourceClass) {
            return ['success' => false, 'error' => 'Keine Ressourcenklasse angegeben'];
        }

        $action = $params['action'] ?? null;

        if (!$action) {
            return ['success' => false, 'error' => 'Keine Aktion angegeben'];
        }

        try {
            // Beispiel: $result = app()->make('App\Services\EntityService')->$action($data, $params);
            $result = $this->executeCustomAction($resourceClass, $action, $data, $params);

            return [
                'success' => true,
                'data' => $result
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'code' => $e instanceof \App\Core\Error\ApiException ? $e->getErrorCode() : 'CUSTOM_ACTION_ERROR'
            ];
        }
    }

    /**
     * Enitätenerstellung (Template-Methode, sollte in spezialisierter Klasse überschrieben werden)
     */
    protected function createEntity(string $resourceClass, array $data)
    {
        throw new \App\Core\Error\BadRequestException(
            'Methode createEntity() muss in abgeleiteter Klasse implementiert werden'
        );
    }

    /**
     * Enitätenaktualisierung (Template-Methode, sollte in spezialisierter Klasse überschrieben werden)
     */
    protected function updateEntity(string $resourceClass, $id, array $data)
    {
        throw new \App\Core\Error\BadRequestException(
            'Methode updateEntity() muss in abgeleiteter Klasse implementiert werden'
        );
    }

    /**
     * Enitätenlöschung (Template-Methode, sollte in spezialisierter Klasse überschrieben werden)
     */
    protected function deleteEntity(string $resourceClass, $id): bool
    {
        throw new \App\Core\Error\BadRequestException(
            'Methode deleteEntity() muss in abgeleiteter Klasse implementiert werden'
        );
    }

    /**
     * Benutzerdefinierte Aktion (Template-Methode, sollte in spezialisierter Klasse überschrieben werden)
     */
    protected function executeCustomAction(string $resourceClass, string $action, array $data, array $params)
    {
        throw new \App\Core\Error\BadRequestException(
            'Methode executeCustomAction() muss in abgeleiteter Klasse implementiert werden'
        );
    }

    // src/Core/Api/ApiResource.php
// Neue Methoden für bedingte Includes:

    /**
     * Erstellt eine erfolgreiche API-Antwort mit einer Ressource und bedingten Relations
     *
     * @param mixed $model Das Modell
     * @param string $resourceClass Die Ressourcenklasse
     * @param array $includes Zu inkludierende Beziehungen
     * @param array $conditions Bedingungen für Includes
     * @param int $status HTTP-Statuscode
     * @return Response
     */
    public function itemWithIncludes(
        mixed $model,
        string $resourceClass,
        array $includes = [],
        array $conditions = [],
        int $status = 200
    ): Response {
        $data = $this->factory->makeWithIncludes($model, $resourceClass, $includes, $conditions);
        return $this->responseFactory->success($data, $status);
    }

    /**
     * Erstellt eine erfolgreiche API-Antwort mit einer Ressourcensammlung und bedingten Relations
     *
     * @param array $models Die Modelle
     * @param string $resourceClass Die Ressourcenklasse
     * @param array $includes Zu inkludierende Beziehungen
     * @param array $conditions Bedingungen für Includes
     * @param int $status HTTP-Statuscode
     * @return Response
     */
    public function collectionWithIncludes(
        array $models,
        string $resourceClass,
        array $includes = [],
        array $conditions = [],
        int $status = 200
    ): Response {
        $data = array_map(
            fn($model) => $this->factory->makeWithIncludes($model, $resourceClass, $includes, $conditions),
            $models
        );

        return $this->responseFactory->success($data, $status);
    }

    /**
     * Erstellt eine erfolgreiche API-Antwort mit einem paginierten Ergebnis und bedingten Relations
     *
     * @param Paginator $paginator Der Paginator
     * @param string $resourceClass Die Ressourcenklasse
     * @param array $includes Zu inkludierende Beziehungen
     * @param array $conditions Bedingungen für Includes
     * @param int $status HTTP-Statuscode
     * @param string $format Format der Paginierung (standard, json_api)
     * @return Response
     */
    public function paginateWithIncludes(
        Paginator $paginator,
        string $resourceClass,
        array $includes = [],
        array $conditions = [],
        int $status = 200,
        string $format = 'standard'
    ): Response {
        $result = $this->factory->paginateWithIncludes($paginator, $resourceClass, $includes, $conditions);

        if ($format === 'json_api') {
            return $this->responseFactory->json([
                'data' => $result['data'],
                'meta' => $paginator->toJsonApi()['meta'],
                'links' => $paginator->toJsonApi()['links']
            ], $status);
        }

        return $this->responseFactory->json([
            'success' => true,
            'data' => $result['data'],
            'meta' => $result['meta']
        ], $status);
    }
}