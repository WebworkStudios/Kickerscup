<?php

declare(strict_types=1);

namespace App\Core\Routing;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Http\ResponseFactory;

/**
 * Router-Klasse für API-Routing mit Subdomain-Unterstützung
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
     * Definiert eine Route für alle HTTP-Methoden
     *
     * @param string $uri URI der Route
     * @param \Closure|string|array $action Aktion, die ausgeführt werden soll
     * @return Route Die erstellte Route
     */
    public function any(string $uri, \Closure|string|array $action): Route
    {
        return $this->addRoute([
            'GET', 'HEAD', 'POST', 'PUT',
            'PATCH', 'DELETE', 'OPTIONS'
        ], $uri, $action);
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
     * @param string $domain Domain (z.B. 'api.example.com', 'v1.service.com')
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
     * @param string $prefix Präfix (z.B. '/api', '/v1')
     * @return self
     */
    public function prefix(string $prefix): self
    {
        $this->currentPrefix = $prefix;
        return $this;
    }

    /**
     * Gruppiert Routen mit optionaler Domain und Prefix
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
     * Löst eine Route für einen Request auf
     *
     * @param Request $request Eingehender Request
     * @return Route|null Gefundene Route oder null
     */
    public function resolve(Request $request): ?Route
    {
        $method = $request->getMethod();
        $uri = $this->normalizeUri($request->getUri());
        $host = $request->getHost();

        // Debug-Logging kann entfernt oder angepasst werden
        error_log("Resolving route: Method=$method, URI=$uri, Host=$host");

        foreach ($this->routes as $route) {
            // Methoden prüfen
            if (!in_array($method, $route->getMethods())) {
                continue;
            }

            // Domain prüfen (wenn definiert)
            $routeDomain = $route->getDomain();
            if ($routeDomain !== null && $routeDomain !== $host) {
                continue;
            }

            // Route-Pattern generieren und prüfen
            $pattern = $this->createRoutePattern($route->getUri());

            if (preg_match($pattern, $uri, $matches)) {
                // Parameter extrahieren
                $parameters = $this->extractParameters($route->getUri(), $matches);
                $route->setParameters($parameters);

                return $route;
            }
        }

        return null;
    }

    /**
     * Normalisiert den URI
     *
     * @param string $uri Ursprünglicher URI
     * @return string Normalisierter URI
     */
    private function normalizeUri(string $uri): string
    {
        // Query-String entfernen und führenden/abschließenden Slash normalisieren
        $uri = parse_url($uri, PHP_URL_PATH) ?: '/';
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
        // Dynamische Platzhalter in Regex-Gruppen umwandeln
        $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $uri);
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
        preg_match_all('/\{([a-zA-Z0-9_]+)\}/', $routeUri, $parameterNames);

        $parameters = [];
        foreach ($parameterNames[1] as $name) {
            if (isset($matches[$name])) {
                $parameters[$name] = $matches[$name];
            }
        }

        return $parameters;
    }

    /**
     * Gibt die RouteCollection zurück
     *
     * @return RouteCollection
     */
    public function getRoutes(): RouteCollection
    {
        return $this->routes;
    }
}