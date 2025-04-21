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

    public function shouldCompress(Request $request, Response $response): bool
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
        $isCompressibleType = array_any($this->compressibleTypes, fn($type) => str_contains(strtolower($contentType), $type));

        if (!$isCompressibleType) {
            return false;
        }

        // Ist der Inhalt groß genug für eine sinnvolle Kompression?
        $content = $response->getContent();
        if (!is_string($content) || strlen($content) < $this->minSize) {
            return false;
        }

        // NEU: Geschätzte Kompressionsrate basierend auf Content-Type
        $compressionRatio = $this->estimateCompressionRatio($contentType, $content);

        // Keine Kompression wenn der erwartete Gewinn unter 10% liegt
        if ($compressionRatio < 0.1) {
            return false;
        }

        return true;
    }

    /**
     * Schätzt die Kompressionsrate basierend auf Content-Type und Stichprobe
     *
     * @param string $contentType Der Content-Type
     * @param string $content Der zu komprimierende Inhalt
     * @return float Geschätzte Kompressionsrate (0-1)
     */
    private function estimateCompressionRatio(string $contentType, string $content): float
    {
        // Für sehr kurze Inhalte lohnt sich die Kompression nie
        if (strlen($content) < $this->minSize * 2) {
            return 0;
        }

        // Schnelle Schätzung für verschiedene Content-Types
        if (str_contains($contentType, 'application/json')) {
            // JSON hat typischerweise eine gute Kompressionsrate
            return 0.6;
        }

        if (str_contains($contentType, 'text/html') || str_contains($contentType, 'text/plain')) {
            // Text komprimiert in der Regel gut
            return 0.7;
        }

        if (str_contains($contentType, 'application/javascript') || str_contains($contentType, 'text/css')) {
            // Code komprimiert auch gut
            return 0.65;
        }

        // Für große Inhalte (>50KB) eine Stichprobe komprimieren
        if (strlen($content) > 50000) {
            // Nimm eine 5KB Stichprobe aus der Mitte
            $sample = substr($content, strlen($content) / 2 - 2500, 5000);
            $compressedSample = gzencode($sample, 1); // Schnellste Kompression
            return 1 - (strlen($compressedSample) / strlen($sample));
        }

        // Standard-Fallback: 30% Kompressionsrate annehmen
        return 0.3;
    }
}