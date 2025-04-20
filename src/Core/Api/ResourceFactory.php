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
}