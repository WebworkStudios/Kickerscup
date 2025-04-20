<?php

declare(strict_types=1);

namespace App\Core\Api;

use App\Core\Database\Paginator;

/**
 * Factory für API-Ressourcen
 */
class ResourceFactory
{
    /**
     * Transformiert ein Modell in eine Ressource
     *
     * @param mixed $model Zu transformierendes Modell
     * @param string $resourceClass Ressourcenklasse
     * @return array Transformierte Ressource
     */
    public function make(mixed $model, string $resourceClass): array
    {
        $resource = $this->createResource($resourceClass);
        return $resource->toArray($model);
    }

    /**
     * Transformiert eine Sammlung von Modellen in eine Sammlung von Ressourcen
     *
     * @param array $models Zu transformierende Modelle
     * @param string $resourceClass Ressourcenklasse
     * @return array Transformierte Ressourcen
     */
    public function collection(array $models, string $resourceClass): array
    {
        $resource = $this->createResource($resourceClass);
        return array_map(fn($model) => $resource->toArray($model), $models);
    }

    /**
     * Transformiert einen Paginator in eine paginierte Ressource
     *
     * @param Paginator $paginator Paginator
     * @param string $resourceClass Ressourcenklasse
     * @return array Paginierte Ressource
     */
    public function paginate(Paginator $paginator, string $resourceClass): array
    {
        $resource = $this->createResource($resourceClass);
        $items = $paginator->getItems();

        $data = array_map(fn($item) => $resource->toArray($item), $items);

        return [
            'data' => $data,
            'meta' => [
                'total' => $paginator->getTotal(),
                'per_page' => $paginator->getPerPage(),
                'current_page' => $paginator->getCurrentPage(),
                'last_page' => $paginator->getLastPage(),
                'next_page_url' => $paginator->getNextPageUrl(),
                'previous_page_url' => $paginator->getPreviousPageUrl()
            ]
        ];
    }

    /**
     * Erstellt eine Ressourceninstanz
     *
     * @param string $resourceClass Ressourcenklasse
     * @return Resource Ressourceninstanz
     * @throws \InvalidArgumentException Wenn die Ressourcenklasse nicht existiert
     */
    private function createResource(string $resourceClass): Resource
    {
        if (!class_exists($resourceClass)) {
            throw new \InvalidArgumentException("Ressourcenklasse $resourceClass existiert nicht");
        }

        $resource = new $resourceClass();

        if (!$resource instanceof Resource) {
            throw new \InvalidArgumentException("$resourceClass implementiert nicht das Resource-Interface");
        }

        return $resource;
    }

    /**
     * Transformiert ein Modell in eine Ressource mit HTTP-Status
     *
     * @param mixed $model Zu transformierendes Modell
     * @param string $resourceClass Ressourcenklasse
     * @param int $status HTTP-Status
     * @return array Ressource mit HTTP-Status
     */
    public function response(mixed $model, string $resourceClass, int $status = 200): array
    {
        $data = $this->make($model, $resourceClass);

        return [
            'data' => $data,
            'status' => $status
        ];
    }

    /**
     * Transformiert ein Modell in eine standardisierte API-Antwort
     *
     * @param mixed $model Zu transformierendes Modell
     * @param string $resourceClass Ressourcenklasse
     * @param int $status HTTP-Status
     * @return array API-Antwort
     */
    public function successResponse(mixed $model, string $resourceClass, int $status = 200): array
    {
        $data = $this->make($model, $resourceClass);

        return [
            'success' => true,
            'data' => $data,
            'status' => $status
        ];
    }

    /**
     * Transformiert eine Sammlung in eine standardisierte API-Antwort
     *
     * @param array $models Zu transformierende Modelle
     * @param string $resourceClass Ressourcenklasse
     * @param int $status HTTP-Status
     * @return array API-Antwort
     */
    public function collectionResponse(array $models, string $resourceClass, int $status = 200): array
    {
        $data = $this->collection($models, $resourceClass);

        return [
            'success' => true,
            'data' => $data,
            'status' => $status
        ];
    }

    /**
     * Transformiert einen Paginator in eine standardisierte API-Antwort
     *
     * @param Paginator $paginator Paginator
     * @param string $resourceClass Ressourcenklasse
     * @param int $status HTTP-Status
     * @return array API-Antwort
     */
    public function paginateResponse(Paginator $paginator, string $resourceClass, int $status = 200): array
    {
        $result = $this->paginate($paginator, $resourceClass);

        return [
            'success' => true,
            'data' => $result['data'],
            'meta' => $result['meta'],
            'status' => $status
        ];
    }

    /**
     * Transformiert ein Modell mit einer verschachtelten Beziehung
     *
     * @param mixed $model Zu transformierendes Modell
     * @param string $resourceClass Ressourcenklasse
     * @param array $includes Zu inkludierende Beziehungen mit ihren Ressourcenklassen
     * @return array Transformierte Ressource mit Beziehungen
     */
    public function makeWithIncludes(mixed $model, string $resourceClass, array $includes = []): array
    {
        $result = $this->make($model, $resourceClass);

        foreach ($includes as $relation => $relationResourceClass) {
            if (is_numeric($relation) && is_string($relationResourceClass)) {
                // Wenn nur der Ressourcenname angegeben ist, verwenden wir den gleichen Namen für die Relation
                $relation = $relationResourceClass;
            }

            // Prüfen, ob die Relation als Methode oder Eigenschaft existiert
            $relationData = null;
            if (is_object($model)) {
                if (method_exists($model, $relation)) {
                    $relationData = $model->$relation();
                } elseif (property_exists($model, $relation) || isset($model->$relation)) {
                    $relationData = $model->$relation;
                } elseif (method_exists($model, 'get' . ucfirst($relation))) {
                    $method = 'get' . ucfirst($relation);
                    $relationData = $model->$method();
                }
            } elseif (is_array($model) && array_key_exists($relation, $model)) {
                $relationData = $model[$relation];
            }

            // Transformieren, wenn Daten vorhanden sind
            if ($relationData !== null) {
                if (is_array($relationData) && !array_is_list($relationData)) {
                    // Einzelne Beziehung
                    $result[$relation] = $this->make($relationData, $relationResourceClass);
                } elseif (is_array($relationData)) {
                    // Collection von Beziehungen
                    $result[$relation] = $this->collection($relationData, $relationResourceClass);
                } else {
                    // Einzelne Beziehung (Objekt)
                    $result[$relation] = $this->make($relationData, $relationResourceClass);
                }
            } else {
                // Leeres Array setzen, wenn keine Daten vorhanden sind
                $result[$relation] = [];
            }
        }

        return $result;
    }

    /**
     * Transformiert eine Sammlung mit verschachtelten Beziehungen
     *
     * @param array $models Zu transformierende Modelle
     * @param string $resourceClass Ressourcenklasse
     * @param array $includes Zu inkludierende Beziehungen mit ihren Ressourcenklassen
     * @return array Transformierte Ressourcen mit Beziehungen
     */
    public function collectionWithIncludes(array $models, string $resourceClass, array $includes = []): array
    {
        return array_map(
            fn($model) => $this->makeWithIncludes($model, $resourceClass, $includes),
            $models
        );
    }

    /**
     * Transformiert einen Paginator mit verschachtelten Beziehungen
     *
     * @param Paginator $paginator Paginator
     * @param string $resourceClass Ressourcenklasse
     * @param array $includes Zu inkludierende Beziehungen mit ihren Ressourcenklassen
     * @return array Paginierte Ressource mit Beziehungen
     */
    public function paginateWithIncludes(Paginator $paginator, string $resourceClass, array $includes = []): array
    {
        $items = $paginator->getItems();

        $data = array_map(
            fn($item) => $this->makeWithIncludes($item, $resourceClass, $includes),
            $items
        );

        return [
            'data' => $data,
            'meta' => [
                'total' => $paginator->getTotal(),
                'per_page' => $paginator->getPerPage(),
                'current_page' => $paginator->getCurrentPage(),
                'last_page' => $paginator->getLastPage(),
                'next_page_url' => $paginator->getNextPageUrl(),
                'previous_page_url' => $paginator->getPreviousPageUrl()
            ]
        ];
    }

    /**
     * Erstellt eine Ressourceninstanz über den Container, falls vorhanden
     *
     * @param string $resourceClass Ressourcenklasse
     * @return Resource Ressourceninstanz
     */
    private function resolveResource(string $resourceClass): Resource
    {
        // Versuchen, die Ressource über den Container zu erstellen, falls vorhanden
        try {
            $container = app();
            if ($container->has($resourceClass)) {
                $resource = $container->make($resourceClass);
                if ($resource instanceof Resource) {
                    return $resource;
                }
            }
        } catch (\Throwable $e) {
            // Fallback zur direkten Instanziierung, wenn Container nicht verfügbar ist
        }

        return $this->createResource($resourceClass);
    }

    /**
     * Macht die API-Ausgabe kompakt, entfernt leere Arrays und null-Werte
     *
     * @param array $data Zu bereinigende Daten
     * @return array Bereinigte Daten
     */
    public function compact(array $data): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            // Rekursiv für Arrays
            if (is_array($value)) {
                $value = $this->compact($value);
            }

            // Wert nur hinzufügen, wenn nicht null und nicht leeres Array
            if ($value !== null && (!is_array($value) || !empty($value))) {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}