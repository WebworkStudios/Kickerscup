<?php

declare(strict_types=1);

namespace App\Core\Routing;

use App\Core\Http\Request;

/**
 * Router-Klasse für das Routing
 *
 * Verwaltet Routen und löst eingehende Requests auf
 */
class Router
{
    /**
     * Sammlung aller Routen
     */
    private RouteCollection $routes;

    /**
     * Aktuelle Domain für Gruppierung
     */
    private ?string $currentDomain = null;

    /**
     * Aktuelles Präfix für Gruppierung
     */
    private ?string $currentPrefix = null;

    /**
     * Konstruktor
     */
    public function __construct()
    {
        $this->routes = new RouteCollection();
    }

    /**
     * Definiert eine GET-Route
     *
     * @param string $uri URI der Route
     * @param \Closure|string|array $action Aktion, die ausgeführt werden soll
     * @return Route Die erstellte Route
     */
    public function get(string $uri, \Closure|string|array $action): Route
    {
        return $this->addRoute(['GET', 'HEAD'], $uri, $action);
    }

    /**
     * Definiert eine POST-Route
     *
     * @param string $uri URI der Route
     * @param \Closure|string|array $action Aktion, die ausgeführt werden soll
     * @return Route Die erstellte Route
     */
    public function post(string $uri, \Closure|string|array $action): Route
    {
        return $this->addRoute(['POST'], $uri, $action);
    }

    /**
     * Definiert eine PUT-Route
     *
     * @param string $uri URI der Route
     * @param \Closure|string|array $action Aktion, die ausgeführt werden soll
     * @return Route Die erstellte Route
     */
    public function put(string $uri, \Closure|string|array $action): Route
    {
        return $this->addRoute(['PUT'], $uri, $action);
    }

    /**
     * Definiert eine PATCH-Route
     *
     * @param string $uri URI der Route
     * @param \Closure|string|array $action Aktion, die ausgeführt werden soll
     * @return Route Die erstellte Route
     */
    public function patch(string $uri, \Closure|string|array $action): Route
    {
        return $this->addRoute(['PATCH'], $uri, $action);
    }

    /**
     * Definiert eine DELETE-Route
     *
     * @param string $uri URI der Route
     * @param \Closure|string|array $action Aktion, die ausgeführt werden soll
     * @return Route Die erstellte Route
     */
    public function delete(string $uri, \Closure|string|array $action): Route
    {
        return $this->addRoute(['DELETE'], $uri, $action);
    }

    /**
     * Definiert eine OPTIONS-Route
     *
     * @param string $uri URI der Route
     * @param \Closure|string|array $action Aktion, die ausgeführt werden soll
     * @return Route Die erstellte Route
     */
    public function options(string $uri, \Closure|string|array $action): Route
    {
        return $this->addRoute(['OPTIONS'], $uri, $action);
    }

    /**
     * Definiert eine Route für alle HTTP-Methoden
     *
     * @param string $uri URI der Route
     * @param \Closure|string|array $action Aktion, die ausgeführt werden soll
     * @return Route Die erstellte Route
     */
    public function any(string $uri, \Closure|string|array $action): Route
    {
        return $this->addRoute(['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'], $uri, $action);
    }

    /**
     * Definiert eine Route für bestimmte HTTP-Methoden
     *
     * @param array $methods HTTP-Methoden
     * @param string $uri URI der Route
     * @param \Closure|string|array $action Aktion, die ausgeführt werden soll
     * @return Route Die erstellte Route
     */
    public function match(array $methods, string $uri, \Closure|string|array $action): Route
    {
        return $this->addRoute($methods, $uri, $action);
    }

    /**
     * Fügt eine Route hinzu
     *
     * @param array $methods HTTP-Methoden
     * @param string $uri URI der Route
     * @param \Closure|string|array $action Aktion, die ausgeführt werden soll
     * @return Route Die erstellte Route
     */
    private function addRoute(array $methods, string $uri, \Closure|string|array $action): Route
    {
        // Präfix hinzufügen, falls vorhanden
        if ($this->currentPrefix !== null) {
            $uri = rtrim($this->currentPrefix, '/') . '/' . ltrim($uri, '/');
        }

        // Route erstellen
        $route = new Route($methods, $uri, $action);

        // Domain setzen, falls vorhanden
        if ($this->currentDomain !== null) {
            $route->setDomain($this->currentDomain);
        }

        // Route zur Sammlung hinzufügen
        $this->routes->add($route);

        return $route;
    }

    /**
     * Setzt die Domain für die nächsten Routen
     *
     * @param string $domain Domain (z.B. 'api.example.com')
     * @return self
     */
    public function domain(string $domain): self
    {
        $this->currentDomain = $domain;

        return $this;
    }

    /**
     * Setzt den Präfix für die nächsten Routen
     *
     * @param string $prefix Präfix (z.B. '/admin')
     * @return self
     */
    public function prefix(string $prefix): self
    {
        $this->currentPrefix = $prefix;

        return $this;
    }

    /**
     * Gruppiert Routen
     *
     * @param \Closure $callback Callback, der weitere Routen definiert
     * @return void
     */
    public function group(\Closure $callback): void
    {
        $previousDomain = $this->currentDomain;
        $previousPrefix = $this->currentPrefix;

        $callback($this);

        $this->currentDomain = $previousDomain;
        $this->currentPrefix = $previousPrefix;
    }

    /**
     * Löst einen Request auf und gibt die passende Route zurück
     *
     * @param Request $request Request
     * @return Route|null Die passende Route oder null, wenn keine gefunden wurde
     */
    public function resolve(Request $request): ?Route
    {
        // Request-Methode und -URI abrufen
        $method = $request->getMethod();
        $uri = $request->getUri();
        $host = $request->getHost();

        // URI normalisieren
        $uri = $this->normalizeUri($uri);

        // Passende Route suchen
        foreach ($this->routes as $route) {
            // Prüfen, ob die Methode passt
            if (!in_array($method, $route->getMethods())) {
                continue;
            }

            // Prüfen, ob die Domain passt (falls vorhanden)
            $routeDomain = $route->getDomain();
            if ($routeDomain !== null && $routeDomain !== $host) {
                continue;
            }

            // Pattern erstellen und prüfen, ob der URI passt
            $pattern = $this->createRoutePattern($route->getUri());
            if (preg_match($pattern, $uri, $matches)) {
                // Benannte Parameter extrahieren
                $parameters = $this->extractParameters($route->getUri(), $matches);

                // Parameter zur Route hinzufügen
                $route->setParameters($parameters);

                return $route;
            }
        }

        return null;
    }

    /**
     * Normalisiert einen URI
     *
     * @param string $uri URI
     * @return string Normalisierter URI
     */
    private function normalizeUri(string $uri): string
    {
        // Query-String entfernen
        $uri = parse_url($uri, PHP_URL_PATH) ?: '/';

        // Führenden und abschließenden Slash hinzufügen
        return '/' . trim($uri, '/');
    }

    /**
     * Erstellt ein Regex-Pattern für eine Route
     *
     * @param string $uri URI der Route
     * @return string Regex-Pattern
     */
    private function createRoutePattern(string $uri): string
    {
        // Dynamische Platzhalter ersetzen ({id} -> ([^/]+))
        $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $uri);

        // Pattern zu einer vollständigen Regex machen
        return '#^' . $pattern . '$#';
    }

    /**
     * Extrahiert Parameter aus einem URI
     *
     * @param string $routeUri URI der Route
     * @param array $matches Regex-Matches
     * @return array Parameter
     */
    private function extractParameters(string $routeUri, array $matches): array
    {
        // Parameter aus der Route extrahieren
        preg_match_all('/\{([a-zA-Z0-9_]+)\}/', $routeUri, $parameterNames);

        $parameters = [];

        foreach ($parameterNames[1] as $name) {
            if (isset($matches[$name])) {
                $parameters[$name] = $matches[$name];
            }
        }

        return $parameters;
    }
}