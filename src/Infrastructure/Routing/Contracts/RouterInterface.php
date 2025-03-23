<?php


declare(strict_types=1);

namespace App\Infrastructure\Routing\Contracts;

use App\Infrastructure\Http\Contracts\RequestInterface;
use App\Infrastructure\Http\Contracts\ResponseInterface;

/**
 * Interface für Router
 */
interface RouterInterface
{
    /**
     * Fügt eine neue Route hinzu
     *
     * @param string|array $methods HTTP-Methode(n)
     * @param string $path URL-Pfad mit optionalen Parametern
     * @param callable|array $handler Zu rufende Funktion oder [Controller, Methode]
     * @param string|null $name Optionaler Name für die Route
     * @param string|null $domain Optionale Domain/Subdomain für die Route
     * @return static
     */
    public function addRoute(string|array $methods, string $path, callable|array|string $handler, ?string $name = null, ?string $domain = null): static;

    /**
     * Findet eine Route für den gegebenen Request
     *
     * @param RequestInterface $request Der HTTP-Request
     * @return mixed Information über die passende Route oder false
     */
    public function match(RequestInterface $request): mixed;

    /**
     * Führt den Request mit der passenden Route aus
     *
     * @param RequestInterface $request Der HTTP-Request
     * @return ResponseInterface Die Response
     */
    public function dispatch(RequestInterface $request): ResponseInterface;

    /**
     * Generiert eine URL für einen Routennamen
     *
     * @param string $name Name der Route
     * @param array $parameters Parameter für die URL
     * @return string Die generierte URL
     */
    public function generateUrl(string $name, array $parameters = []): string;

}
