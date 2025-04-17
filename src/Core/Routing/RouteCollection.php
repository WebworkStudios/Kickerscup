<?php

declare(strict_types=1);

namespace App\Core\Routing;

/**
 * Sammlung von Routen
 *
 * Implementiert Iterator, um über Routen iterieren zu können
 */
class RouteCollection implements \Iterator, \Countable
{
    /**
     * Gespeicherte Routen
     */
    private array $routes = [];

    /**
     * Position des Iterators
     */
    private int $position = 0;

    public function add(Route $route): self
    {
        $this->routes[] = $route;
        error_log("Route added: " . $route->getUri() . " with methods: " . implode(', ', $route->getMethods()));
        return $this;
    }

    /**
     * Gibt alle Routen zurück
     *
     * @return array Routen
     */
    public function all(): array
    {
        return $this->routes;
    }

    /**
     * Findet eine Route anhand ihres Namens
     *
     * @param string $name Name der Route
     * @return Route|null Route oder null, wenn keine gefunden wurde
     */
    public function findByName(string $name): ?Route
    {
        foreach ($this->routes as $route) {
            if ($route->getName() === $name) {
                return $route;
            }
        }

        return null;
    }

    /**
     * Gibt die aktuelle Route zurück (für Iterator)
     *
     * @return Route Aktuelle Route
     */
    public function current(): Route
    {
        return $this->routes[$this->position];
    }

    /**
     * Gibt den aktuellen Schlüssel zurück (für Iterator)
     *
     * @return int Aktueller Schlüssel
     */
    public function key(): int
    {
        return $this->position;
    }

    /**
     * Bewegt den Iterator zum nächsten Element (für Iterator)
     *
     * @return void
     */
    public function next(): void
    {
        ++$this->position;
    }

    /**
     * Setzt den Iterator zurück (für Iterator)
     *
     * @return void
     */
    public function rewind(): void
    {
        $this->position = 0;
    }

    /**
     * Prüft, ob der Iterator gültig ist (für Iterator)
     *
     * @return bool True, wenn gültig, sonst false
     */
    public function valid(): bool
    {
        return isset($this->routes[$this->position]);
    }

    /**
     * Gibt die Anzahl der Routen zurück (für Countable)
     *
     * @return int Anzahl der Routen
     */
    public function count(): int
    {
        return count($this->routes);
    }
}