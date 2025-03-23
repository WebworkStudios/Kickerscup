<?php


declare(strict_types=1);

namespace App\Infrastructure\Routing;

use App\Infrastructure\Container\Attributes\Injectable;
use App\Infrastructure\Container\Attributes\Singleton;
use App\Infrastructure\Routing\Contracts\UrlGeneratorInterface;
use App\Infrastructure\Routing\Exceptions\NamedRouteNotFoundException;
use App\Infrastructure\Routing\Exceptions\RouteCreationException;

/**
 * URL-Generator für benannte Routen
 */
#[Injectable]
#[Singleton]
class UrlGenerator implements UrlGeneratorInterface
{
    /**
     * Benannte Routen
     *
     * @var array<string, array>
     */
    protected array $namedRoutes = [];

    /**
     * Setzt die benannten Routen
     *
     * @param array<string, array> $namedRoutes Benannte Routen
     * @return void
     */
    public function setNamedRoutes(array $namedRoutes): void
    {
        $this->namedRoutes = $namedRoutes;
    }

    /**
     * Fügt eine benannte Route hinzu
     *
     * @param string $name Routenname
     * @param array $routeInfo Routen-Informationen
     * @return void
     */
    public function addNamedRoute(string $name, array $routeInfo): void
    {
        $this->namedRoutes[$name] = $routeInfo;
    }

    /**
     * {@inheritdoc}
     */
    /**
     * {@inheritdoc}
     */
    public function generate(string $name, array $parameters = [], bool $absoluteUrl = false): string
    {
        if (!isset($this->namedRoutes[$name])) {
            throw new NamedRouteNotFoundException("Route mit dem Namen '{$name}' wurde nicht gefunden.");
        }

        $routeInfo = $this->namedRoutes[$name];
        $path = $routeInfo['path'];

        // Ersetze alle Parameter im Pfad
        foreach ($routeInfo['parameters'] as $paramName => $paramInfo) {
            if (!isset($parameters[$paramName])) {
                throw new RouteCreationException("Parameter '{$paramName}' wird benötigt für Route '{$name}'.");
            }

            // Validiere den Parameter gegen den regulären Ausdruck
            $paramValue = $parameters[$paramName];
            if (!empty($paramInfo['regex']) && !preg_match('/^' . $paramInfo['regex'] . '$/', (string)$paramValue)) {
                throw new RouteCreationException(
                    "Parameter '{$paramName}' mit Wert '{$paramValue}' entspricht nicht dem Muster '{$paramInfo['regex']}'."
                );
            }

            // Ersetze den Parameter im Pfad
            $path = str_replace('{' . $paramName . '}', (string)$paramValue, $path);
            $path = str_replace('{' . $paramName . ':' . $paramInfo['regex'] . '}', (string)$paramValue, $path);
        }

        // Erstelle die finale URL mit Query-Parametern
        $queryParams = array_diff_key($parameters, $routeInfo['parameters']);
        if (!empty($queryParams)) {
            $path .= '?' . http_build_query($queryParams);
        }

        // Bei absoluter URL-Generierung die Domain berücksichtigen
        if ($absoluteUrl) {
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
            $domain = $routeInfo['domain'] ?? $_SERVER['HTTP_HOST'] ?? 'localhost';

            return $protocol . $domain . $path;
        }

        return $path;
    }
}