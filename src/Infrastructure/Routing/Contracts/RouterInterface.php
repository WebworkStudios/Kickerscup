<?php


declare(strict_types=1);

namespace App\Infrastructure\Routing\Contracts;

use App\Infrastructure\Http\Contracts\RequestInterface;
use App\Infrastructure\Http\Contracts\ResponseInterface;
use App\Infrastructure\Routing\Attributes\Cors;

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

    /**
     * Fügt eine CORS-Konfiguration für eine Route hinzu
     *
     * @param string $path Der Pfad der Route
     * @param Cors $corsConfig Die CORS-Konfiguration
     * @return void
     */
    public function addCorsConfiguration(string $path, Cors $corsConfig): void;

    /**
     * Findet die CORS-Konfiguration für einen Pfad
     *
     * @param string $path Der Pfad
     * @return array|null Die CORS-Konfiguration oder null
     */
    public function findCorsConfigurationForPath(string $path): ?array;

    /**
     * Fügt eine Umleitung hinzu
     *
     * @param string $fromPath Quellpfad
     * @param string $toPath Zielpfad (kann auch eine benannte Route sein mit 'name:routeName')
     * @param int $statusCode HTTP-Statuscode (301 = permanent, 302 = temporär)
     * @param bool $preserveQueryString Ob der Query-String übernommen werden soll
     * @return static
     */
    public function addRedirect(
        string $fromPath,
        string $toPath,
        int    $statusCode = 302,
        bool   $preserveQueryString = true
    ): static;
}