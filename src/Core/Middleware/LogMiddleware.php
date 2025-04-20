<?php

declare(strict_types=1);

namespace App\Core\Middleware;

use App\Core\Http\Request;
use App\Core\Http\Response;

/**
 * Log-Middleware
 *
 * Protokolliert eingehende Requests und ausgehende Responses
 */
class LogMiddleware implements Middleware
{
    /**
     * Log-Level
     */
    private string $level;

    /**
     * Sensible Header, die nicht protokolliert werden sollen
     *
     * @var array<string>
     */
    private array $sensitiveHeaders = [
        'Authorization',
        'Cookie',
        'X-API-Token',
        'X-Session-ID'
    ];

    /**
     * Konstruktor
     *
     * @param string $level Log-Level (info, debug, etc.)
     * @param array $sensitiveHeaders Zusätzliche sensible Header
     */
    public function __construct(
        string $level = 'debug',
        array  $sensitiveHeaders = []
    )
    {
        $this->level = $level;
        $this->sensitiveHeaders = array_merge(
            $this->sensitiveHeaders,
            $sensitiveHeaders
        );
    }

    /**
     * Verarbeitet den Request und protokolliert ihn
     *
     * @param Request $request Der Request
     * @param callable $next Nächster Handler
     * @return Response Die Response
     */
    public function process(Request $request, callable $next): Response
    {
        $startTime = microtime(true);

        // Eingehenden Request protokollieren
        $this->logRequest($request);

        // Request weiterleiten und Response erhalten
        $response = $next($request);

        // Ausgehende Response protokollieren
        $duration = microtime(true) - $startTime;
        $this->logResponse($response, $duration);

        return $response;
    }

    /**
     * Protokolliert einen eingehenden Request
     *
     * @param Request $request Der zu protokollierende Request
     */
    private function logRequest(Request $request): void
    {
        $method = $request->getMethod();
        $uri = $request->getUri();
        $headers = $this->sanitizeHeaders($request->getHeaders());

        app_log("Eingehender Request: $method $uri", [
            'method' => $method,
            'uri' => $uri,
            'headers' => $headers
        ], $this->level);
    }

    /**
     * Entfernt sensible Daten aus Headern
     *
     * @param array $headers Die zu bereinigenden Header
     * @return array Die bereinigten Header
     */
    private function sanitizeHeaders(array $headers): array
    {
        foreach ($this->sensitiveHeaders as $sensitiveHeader) {
            if (isset($headers[$sensitiveHeader])) {
                $headers[$sensitiveHeader] = '[REDACTED]';
            }

            // Auch für Lowercase-Varianten prüfen
            $lowercaseHeader = strtolower($sensitiveHeader);
            if (isset($headers[$lowercaseHeader])) {
                $headers[$lowercaseHeader] = '[REDACTED]';
            }
        }

        return $headers;
    }

    /**
     * Protokolliert eine ausgehende Response
     *
     * @param Response $response Die zu protokollierende Response
     * @param float $duration Die Verarbeitungsdauer in Sekunden
     */
    private function logResponse(Response $response, float $duration): void
    {
        $statusCode = $response->getStatusCode();
        $headers = $this->sanitizeHeaders($response->getHeaders());

        app_log("Ausgehende Response: $statusCode in " . round($duration * 1000) . "ms", [
            'statusCode' => $statusCode,
            'headers' => $headers,
            'duration' => $duration
        ], $this->level);
    }
}