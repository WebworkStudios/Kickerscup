<?php

declare(strict_types=1);

namespace App\Core\Middleware;

use App\Core\Http\Request;
use App\Core\Http\Response;

/**
 * Middleware zur automatischen Response-Kompression
 */
class CompressionMiddleware implements Middleware
{
    /**
     * Minimale Größe für Kompression in Bytes
     */
    private int $minSize;

    /**
     * Zu komprimierende Content-Types
     */
    private array $compressibleTypes;

    /**
     * Konstruktor
     *
     * @param int $minSize Minimale Größe für Kompression in Bytes
     * @param array $compressibleTypes Zu komprimierende Content-Types
     */
    public function __construct(
        int   $minSize = 1024,  // Standardmäßig ab 1KB komprimieren
        array $compressibleTypes = [
            'application/json',
            'text/html',
            'text/plain',
            'text/css',
            'text/javascript',
            'application/javascript',
            'application/xml',
            'text/xml'
        ]
    )
    {
        $this->minSize = $minSize;
        $this->compressibleTypes = $compressibleTypes;
    }

    /**
     * Verarbeitet den Request und komprimiert ggf. die Response
     *
     * @param Request $request Der Request
     * @param callable $next Der nächste Handler
     * @return Response Die Response
     */
    public function process(Request $request, callable $next): Response
    {
        // Request an nächsten Handler weiterleiten
        $response = $next($request);

        // Prüfen, ob Kompression angewendet werden sollte
        if ($this->shouldCompress($request, $response)) {
            $response->compress();
        }

        return $response;
    }

    /**
     * Prüft, ob die Response komprimiert werden sollte
     *
     * @param Request $request Der Request
     * @param Response $response Die Response
     * @return bool True, wenn komprimiert werden sollte, sonst false
     */
    private function shouldCompress(Request $request, Response $response): bool
    {
        // Akzeptiert der Client Kompression?
        $acceptEncoding = $request->getHeader('Accept-Encoding', '');
        if (!str_contains($acceptEncoding, 'gzip') && !str_contains($acceptEncoding, 'br')) {
            return false;
        }

        // Hat die Response bereits einen Content-Encoding Header?
        if ($response->getHeader('Content-Encoding') !== null) {
            return false;
        }

        // Ist der Content-Type komprimierbar?
        $contentType = $response->getHeader('Content-Type', '');
        $isCompressibleType = array_any($this->compressibleTypes, fn($type) => str_contains(strtolower($contentType), $type)
        );

        if (!$isCompressibleType) {
            return false;
        }

        // Ist der Inhalt groß genug für eine sinnvolle Kompression?
        $content = $response->getContent();
        if (!is_string($content) || strlen($content) < $this->minSize) {
            return false;
        }

        return true;
    }
}