<?php
declare(strict_types=1);

namespace App\Core\Api;

abstract class AbstractResource implements Resource
{
    /**
     * Erzeugt eine Ressource aus einem Modell
     *
     * @param mixed $model Zu transformierendes Modell
     * @return array Transformierte Ressource
     */
    public static function make(mixed $model): array
    {
        return (new static())->toArray($model);
    }

    /**
     * Transformiert ein Modell in eine Ressource
     *
     * @param mixed $model Zu transformierendes Modell
     * @return array Transformierte Ressource
     */
    abstract public function toArray(mixed $model): array;

    /**
     * Transformiert eine Sammlung von Modellen in eine Sammlung von Ressourcen
     *
     * @param array $models Zu transformierende Modelle
     * @return array Transformierte Ressourcen
     */
    public static function collection(array $models): array
    {
        $resource = new static();

        // Korrekte Reihenfolge: zuerst die Callback-Funktion, dann das Array
        return array_map(fn($model) => $resource->toArray($model), $models);
    }

    /**
     * Transformiert eine Sammlung von Modellen in eine paginierte Ressource
     *
     * @param \App\Core\Database\Paginator $paginator Paginator-Instanz
     * @return array Paginierte Ressource
     */
    public static function paginate(\App\Core\Database\Paginator $paginator): array
    {
        $resource = new static();
        $items = $paginator->getItems();

        $resourceItems = array_map(
            fn($item) => $resource->toArray($item),
            $items
        );

        return $resource->withMeta($resourceItems, [
            'total' => $paginator->getTotal(),
            'per_page' => $paginator->getPerPage(),
            'current_page' => $paginator->getCurrentPage(),
            'last_page' => $paginator->getLastPage(),
            'has_more_pages' => $paginator->hasMorePages(),
            'next_page_url' => $paginator->getNextPageUrl(),
            'previous_page_url' => $paginator->getPreviousPageUrl()
        ]);
    }

    /**
     * Fügt Metadaten zu einer Ressourcensammlung hinzu
     *
     * @param array $resources Ressourcen
     * @param array $meta Metadaten
     * @return array Ressourcen mit Metadaten
     */
    protected function withMeta(array $resources, array $meta): array
    {
        return [
            'data' => $resources,
            'meta' => $meta
        ];
    }

    /**
     * Konvertiert einen Wert mit einer anderen Ressource
     *
     * @param mixed $model Zu transformierendes Modell
     * @param Resource|string $resource Ressource oder Ressourcenname
     * @return array|null Transformierte Ressource oder null
     */
    protected function when(mixed $model, Resource|string $resource): ?array
    {
        if ($model === null) {
            return null;
        }

        // PHP 8.4: Wir könnten `match` für bessere Lesbarkeit verwenden
        return match (true) {
            is_string($resource) => (new $resource())->toArray($model),
            default => $resource->toArray($model)
        };
    }

    /**
     * Konvertiert eine Sammlung von Modellen mit einer anderen Ressource
     *
     * @param array|null $models Zu transformierende Modelle
     * @param Resource|string $resource Ressource oder Ressourcenname
     * @return array|null Transformierte Ressourcen oder null
     */
    protected function whenCollection(?array $models, Resource|string $resource): ?array
    {
        if ($models === null || empty($models)) {
            return [];
        }

        if (is_string($resource)) {
            $resource = new $resource();
        }

        return array_map(
            fn($model) => $resource->toArray($model),
            $models
        );
    }

    /**
     * Fügt Felder bedingt zu einer Ressource hinzu
     *
     * @param bool $condition Bedingung
     * @param callable $callback Callback, der die Felder erzeugt
     * @return array Bedingte Felder oder leeres Array
     */
    protected function whenCondition(bool $condition, callable $callback): array
    {
        return $condition ? $callback() : [];
    }

    /**
     * Fügt ein Feld bedingt zu einer Ressource hinzu
     *
     * @param bool $condition Bedingung
     * @param mixed $value Feldwert
     * @param mixed $default Standardwert
     * @return mixed Feldwert oder Standardwert
     */
    protected function whenField(bool $condition, mixed $value, mixed $default = null): mixed
    {
        return $condition ? $value : $default;
    }
}