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
            'meta' => $this->extractPaginatorMeta($paginator)
        ];
    }

    /**
     * Extrahiert Meta-Informationen aus einem Paginator
     *
     * @param Paginator $paginator Der Paginator
     * @return array Die Meta-Informationen
     */
    private function extractPaginatorMeta(Paginator $paginator): array
    {
        return [
            'total' => $paginator->getTotal(),
            'per_page' => $paginator->getPerPage(),
            'current_page' => $paginator->getCurrentPage(),
            'last_page' => $paginator->getLastPage(),
            'next_page_url' => $paginator->getNextPageUrl(),
            'previous_page_url' => $paginator->getPreviousPageUrl()
        ];
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
     * Transformiert ein Modell mit einer verschachtelten Beziehung
     *
     * @param mixed $model Zu transformierendes Modell
     * @param string $resourceClass Ressourcenklasse
     * @param array $includes Zu inkludierende Beziehungen mit ihren Ressourcenklassen
     * @param array $conditions Bedingungen für bedingte Includes
     * @return array Transformierte Ressource mit Beziehungen
     */
    public function makeWithIncludes(
        mixed $model,
        string $resourceClass,
        array $includes = [],
        array $conditions = []
    ): array {
        $result = $this->make($model, $resourceClass);

        foreach ($includes as $relation => $relationResourceClass) {
            if (is_numeric($relation) && is_string($relationResourceClass)) {
                // Wenn nur der Ressourcenname angegeben ist, verwenden wir den gleichen Namen für die Relation
                $relation = $relationResourceClass;
            }

            // Prüfen, ob die Bedingung für diesen Include erfüllt ist
            if (!$this->shouldIncludeRelation($relation, $model, $conditions)) {
                continue;
            }

            // Extrahiere relationData in eine separate Methode
            $relationData = $this->extractRelationData($model, $relation);

            // Transformieren, wenn Daten vorhanden sind
            $result[$relation] = match (true) {
                $relationData === null => [],
                is_array($relationData) && !array_is_list($relationData) => $this->make($relationData, $relationResourceClass),
                is_array($relationData) => $this->collection($relationData, $relationResourceClass),
                default => $this->make($relationData, $relationResourceClass)
            };
        }

        return $result;
    }

    /**
     * Prüft, ob eine Relation basierend auf Bedingungen eingeschlossen werden soll
     *
     * @param string $relation Name der Relation
     * @param mixed $model Das Model
     * @param array $conditions Bedingungen für Includes
     * @return bool True, wenn die Relation eingeschlossen werden soll
     */
    private function shouldIncludeRelation(string $relation, mixed $model, array $conditions): bool
    {
        // Wenn keine Bedingungen definiert sind oder keine für diese Relation, immer einschließen
        if (empty($conditions) || !isset($conditions[$relation])) {
            return true;
        }

        $condition = $conditions[$relation];

        // Wenn die Bedingung ein Callable ist, diesen mit dem Modell aufrufen
        if (is_callable($condition)) {
            return $condition($model);
        }

        // Wenn die Bedingung ein Attribut ist, dessen Wert überprüft werden soll
        if (is_array($condition) && count($condition) >= 2) {
            $attribute = $condition[0];
            $operator = $condition[1];
            $value = $condition[2] ?? null;

            $modelValue = $this->extractAttributeValue($model, $attribute);

            return match($operator) {
                '=' => $modelValue == $value,
                '==' => $modelValue == $value,
                '===' => $modelValue === $value,
                '!=' => $modelValue != $value,
                '!==' => $modelValue !== $value,
                '>' => $modelValue > $value,
                '>=' => $modelValue >= $value,
                '<' => $modelValue < $value,
                '<=' => $modelValue <= $value,
                'in' => is_array($value) && in_array($modelValue, $value),
                'not_in' => is_array($value) && !in_array($modelValue, $value),
                'null' => $modelValue === null,
                'not_null' => $modelValue !== null,
                'empty' => empty($modelValue),
                'not_empty' => !empty($modelValue),
                default => false
            };
        }

        // Fallback: Wenn die Bedingung ein Boolean ist, diesen direkt zurückgeben
        return (bool)$condition;
    }

    /**
     * Extrahiert einen Attributwert aus einem Modell
     *
     * @param mixed $model Das Modell
     * @param string $attribute Der Attributname
     * @return mixed Der Attributwert
     */
    private function extractAttributeValue(mixed $model, string $attribute): mixed
    {
        if (is_object($model)) {
            return match (true) {
                method_exists($model, $attribute) => $model->$attribute(),
                property_exists($model, $attribute) || isset($model->$attribute) => $model->$attribute,
                method_exists($model, 'get' . ucfirst($attribute)) => $model->{'get' . ucfirst($attribute)}(),
                default => null
            };
        }

        return is_array($model) && array_key_exists($attribute, $model) ? $model[$attribute] : null;
    }

    /**
     * Transformiert einen Paginator mit verschachtelten Beziehungen und bedingten Includes
     *
     * @param Paginator $paginator Paginator
     * @param string $resourceClass Ressourcenklasse
     * @param array $includes Zu inkludierende Beziehungen mit ihren Ressourcenklassen
     * @param array $conditions Bedingungen für bedingte Includes
     * @return array Paginierte Ressource mit Beziehungen
     */
    public function paginateWithIncludes(
        Paginator $paginator,
        string $resourceClass,
        array $includes = [],
        array $conditions = []
    ): array {
        $items = $paginator->getItems();

        $data = array_map(
            fn($item) => $this->makeWithIncludes($item, $resourceClass, $includes, $conditions),
            $items
        );

        return [
            'data' => $data,
            'meta' => $this->extractPaginatorMeta($paginator)
        ];
    }

    /**
     * Extrahiert Beziehungsdaten aus einem Modell
     *
     * @param mixed $model Das Modell
     * @param string $relation Die Beziehung
     * @return mixed Die extrahierten Daten oder null
     */
    private function extractRelationData(mixed $model, string $relation): mixed
    {
        if (is_object($model)) {
            return match (true) {
                method_exists($model, $relation) => $model->$relation(),
                property_exists($model, $relation) || isset($model->$relation) => $model->$relation,
                method_exists($model, 'get' . ucfirst($relation)) => $model->{'get' . ucfirst($relation)}(),
                default => null
            };
        }

        return is_array($model) && array_key_exists($relation, $model) ? $model[$relation] : null;
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

    /**
     * Erstellt eine Ressourceninstanz über den Container, falls vorhanden
     *
     * @param string $resourceClass Ressourcenklasse
     * @return Resource Ressourceninstanz
     */
    private function resolveResource(string $resourceClass): Resource
    {
        try {
            $container = app();
            return $container->has($resourceClass) && $container->make($resourceClass) instanceof Resource
                ? $container->make($resourceClass)
                : $this->createResource($resourceClass);
        } catch (\Throwable) {
            // Fallback bei Container-Problemen
            return $this->createResource($resourceClass);
        }
    }
}