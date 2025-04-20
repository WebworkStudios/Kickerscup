<?php
declare(strict_types=1);

namespace App\Core\Middleware;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Http\ResponseFactory;

/**
 * JSON-Validierungs-Middleware
 *
 * Validiert eingehende JSON-Requests und stellt sicher, dass diese korrekt formatiert sind
 */
class JsonValidationMiddleware implements Middleware
{
    /**
     * ResponseFactory für Fehlerantworten
     */
    private readonly ResponseFactory $responseFactory;

    /**
     * Pfade, die ignoriert werden sollen
     *
     * @var array<string>
     */
    private array $ignoredPaths = [];

    /**
     * Konstruktor
     *
     * @param ResponseFactory $responseFactory Factory für Antworten
     * @param array $ignoredPaths Pfade, die nicht validiert werden sollen
     */
    public function __construct(
        ResponseFactory $responseFactory,
        array           $ignoredPaths = []
    )
    {
        $this->responseFactory = $responseFactory;
        $this->ignoredPaths = $ignoredPaths;
    }

    /**
     * Verarbeitet einen Request und validiert JSON
     *
     * @param Request $request Eingehender Request
     * @param callable $next Nächster Handler in der Kette
     * @return Response Resultierende Response
     */
    public function process(Request $request, callable $next): Response
    {
        // Nur POST, PUT, PATCH und DELETE mit Content-Type application/json validieren
        if (!$this->shouldValidateRequest($request)) {
            return $next($request);
        }

        // JSON-Körper validieren
        $contentType = $request->getHeader('Content-Type') ?? '';

        if (str_contains(strtolower($contentType), 'application/json')) {
            $body = $request->getBody();

            if (!empty($body)) {
                if (!$this->isValidJson($body)) {
                    return $this->responseFactory->error(
                        'Ungültiges JSON-Format',
                        'INVALID_JSON',
                        [],
                        400
                    );
                }
            }
        }

        return $next($request);
    }

    /**
     * Prüft, ob ein Request validiert werden soll
     *
     * @param Request $request Zu prüfender Request
     * @return bool True, wenn Request validiert werden soll
     */
    private function shouldValidateRequest(Request $request): bool
    {
        // OPTIONS-Requests immer ignorieren
        if ($request->getMethod() === 'OPTIONS') {
            return false;
        }

        // Nur bestimmte Methoden validieren
        $methods = ['POST', 'PUT', 'PATCH', 'DELETE'];
        if (!in_array($request->getMethod(), $methods)) {
            return false;
        }

        // Prüfen, ob der Pfad ignoriert werden soll
        $path = parse_url($request->getUri(), PHP_URL_PATH) ?: '/';

        return !array_any($this->ignoredPaths, fn($ignored) => $this->pathMatches($path, $ignored));
    }

    /**
     * Prüft, ob ein Pfad einem Muster entspricht
     *
     * @param string $path Zu prüfender Pfad
     * @param string $pattern Muster
     * @return bool True, wenn der Pfad dem Muster entspricht
     */
    private function pathMatches(string $path, string $pattern): bool
    {
        $regex = str_replace('\*', '.*', preg_quote($pattern, '/'));
        return (bool)preg_match('/^' . $regex . '$/', $path);
    }

    /**
     * Prüft, ob ein String gültiges JSON ist
     *
     * @param string $json Zu prüfender JSON-String
     * @return bool True, wenn der String gültiges JSON ist
     */
    private function isValidJson(string $json): bool
    {
        if (empty($json)) {
            return false;
        }

        try {
            json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            return true;
        } catch (\JsonException) {
            return false;
        }
    }
}