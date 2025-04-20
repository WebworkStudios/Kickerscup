<?php

declare(strict_types=1);

namespace App\Core\Middleware;

use App\Core\Http\Request;
use App\Core\Http\Response;

/**
 * CORS-Middleware
 *
 * Implementiert Cross-Origin Resource Sharing (CORS) für API-Requests
 */
class CorsMiddleware implements Middleware
{
    /**
     * CORS-Konfiguration
     */
    private readonly array $config;

    /**
     * Konstruktor
     *
     * @param array $config CORS-Konfiguration
     */
    public function __construct(array $config = [])
    {
        // Standardkonfiguration mit benutzerdefinierten Werten überschreiben
        $this->config = array_merge([
            'allowedOrigins' => ['*'],
            'allowedMethods' => ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'],
            'allowedHeaders' => ['Content-Type', 'X-Requested-With', 'Authorization', 'X-API-Token', 'X-Session-ID'],
            'exposedHeaders' => [],
            'maxAge' => 86400,  // 24 Stunden
            'supportsCredentials' => false,
            'allowPrivateNetwork' => false,
        ], $config);
    }

    /**
     * Verarbeitet einen Request und fügt CORS-Header hinzu
     *
     * @param Request $request Eingehender Request
     * @param callable $next Nächster Handler in der Kette
     * @return Response Resultierende Response
     */
    public function process(Request $request, callable $next): Response
    {
        // Falls es ein Preflight-Request ist (OPTIONS)
        if ($request->getMethod() === 'OPTIONS') {
            return $this->handlePreflightRequest($request);
        }

        // Regulärer Request - CORS-Header zur Response hinzufügen
        $response = $next($request);
        return $this->addCorsHeaders($request, $response);
    }

    /**
     * Behandelt einen Preflight-Request
     *
     * @param Request $request Eingehender Preflight-Request
     * @return Response Response mit CORS-Headern
     */
    private function handlePreflightRequest(Request $request): Response
    {
        $response = new Response('', 204);
        return $this->addCorsHeaders($request, $response);
    }

    /**
     * Fügt CORS-Header zu einer Response hinzu
     *
     * @param Request $request Der ursprüngliche Request
     * @param Response $response Die Response, zu der Header hinzugefügt werden sollen
     * @return Response Die modifizierte Response
     */
    private function addCorsHeaders(Request $request, Response $response): Response
    {
        // Origin prüfen
        $origin = $request->getHeader('Origin');

        if ($origin) {
            // Zulässigen Origin setzen mit array_any
            if (array_any($this->config['allowedOrigins'], fn($allowed) => $allowed === '*' || $allowed === $origin)) {
                $response->setHeader('Access-Control-Allow-Origin', $origin);
            }

            // Bei Nutzung von Wildcards 'Vary: Origin' setzen
            if (array_any($this->config['allowedOrigins'], fn($allowed) => $allowed === '*')) {
                $response->setHeader('Vary', 'Origin');
            }
        }

        // Rest des Codes bleibt unverändert
        $response->setHeader('Access-Control-Allow-Methods', implode(', ', $this->config['allowedMethods']));
        $response->setHeader('Access-Control-Allow-Headers', implode(', ', $this->config['allowedHeaders']));

        if ($this->config['supportsCredentials']) {
            $response->setHeader('Access-Control-Allow-Credentials', 'true');
        }

        if ($this->config['maxAge'] > 0) {
            $response->setHeader('Access-Control-Max-Age', (string)$this->config['maxAge']);
        }

        if ($this->config['allowPrivateNetwork']) {
            $response->setHeader('Access-Control-Allow-Private-Network', 'true');
        }

        if (!empty($this->config['exposedHeaders'])) {
            $response->setHeader('Access-Control-Expose-Headers', implode(', ', $this->config['exposedHeaders']));
        }

        return $response;
    }
}