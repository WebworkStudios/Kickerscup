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
    public function generate(string $name, array $parameters = [], bool $absoluteUrl = false): string
    {
        if (!isset($this->namedRoutes[$name])) {
            throw new NamedRouteNotFoundException("Route mit dem Namen '{$name}' wurde nicht gefunden.");
        }

        $routeInfo = $this->namedRoutes[$name];
        $path = $routeInfo['path'];
        $domain = $routeInfo['domain'] ?? null;

        // Ersetze alle Parameter im Pfad
        foreach ($routeInfo['parameters'] as $paramName => $paramInfo) {
            if (!isset($parameters[$paramName]) && (!isset($paramInfo['optional']) || !$paramInfo['optional'])) {
                throw new RouteCreationException("Parameter '{$paramName}' wird benötigt für Route '{$name}'.");
            }

            // Wenn Parameter vorhanden ist oder optional mit Default
            if (isset($parameters[$paramName]) || (isset($paramInfo['optional']) && isset($paramInfo['default']))) {
                $paramValue = $parameters[$paramName] ?? $paramInfo['default'];

                // Validiere den Parameter gegen den regulären Ausdruck
                if (!empty($paramInfo['regex']) && !preg_match('/^' . $paramInfo['regex'] . '$/', (string)$paramValue)) {
                    throw new RouteCreationException(
                        "Parameter '{$paramName}' mit Wert '{$paramValue}' entspricht nicht dem Muster '{$paramInfo['regex']}'."
                    );
                }

                // Ersetze den Parameter im Pfad
                $path = str_replace('{' . $paramName . '}', (string)$paramValue, $path);
                $path = str_replace('{' . $paramName . ':' . $paramInfo['regex'] . '}', (string)$paramValue, $path);

                // Entferne den Parameter aus der Parameter-Liste, damit er nicht als Query-Parameter verwendet wird
                unset($parameters[$paramName]);
            }
        }

        // Wenn eine Domain mit Parametern vorhanden ist, ersetze auch diese
        if ($domain !== null && isset($routeInfo['domainParameters']) && !empty($routeInfo['domainParameters'])) {
            foreach ($routeInfo['domainParameters'] as $paramName => $paramInfo) {
                if (!isset($parameters[$paramName]) && (!isset($paramInfo['optional']) || !$paramInfo['optional'])) {
                    throw new RouteCreationException("Domain-Parameter '{$paramName}' wird benötigt für Route '{$name}'.");
                }

                // Wenn Parameter vorhanden ist oder optional mit Default
                if (isset($parameters[$paramName]) || (isset($paramInfo['optional']) && isset($paramInfo['default']))) {
                    $paramValue = $parameters[$paramName] ?? $paramInfo['default'];

                    // Validiere den Parameter gegen den regulären Ausdruck
                    if (!empty($paramInfo['regex']) && !preg_match('/^' . $paramInfo['regex'] . '$/', (string)$paramValue)) {
                        throw new RouteCreationException(
                            "Domain-Parameter '{$paramName}' mit Wert '{$paramValue}' entspricht nicht dem Muster '{$paramInfo['regex']}'."
                        );
                    }

                    // Ersetze den Parameter in der Domain
                    $domain = str_replace('{' . $paramName . '}', (string)$paramValue, $domain);
                    $domain = str_replace('{' . $paramName . ':' . $paramInfo['regex'] . '}', (string)$paramValue, $domain);

                    // Entferne den Parameter aus der Parameter-Liste
                    unset($parameters[$paramName]);
                }
            }
        }

        // Erstelle die finale URL mit Query-Parametern
        $queryParams = $parameters; // Alle verbliebenen Parameter sind Query-Parameter
        if (!empty($queryParams)) {
            $path .= '?' . http_build_query($queryParams);
        }

        // Bei absoluter URL-Generierung die Domain berücksichtigen
        if ($absoluteUrl) {
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
            $hostDomain = $domain ?? $_SERVER['HTTP_HOST'] ?? 'localhost';

            return $protocol . $hostDomain . $path;
        }

        return $path;
    }
}