<?php

declare(strict_types=1);

namespace App\Core\Middleware;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Http\ResponseFactory;
use App\Core\Security\Csrf;

/**
 * Authentifizierungs-Middleware
 *
 * Prüft API-Tokens für geschützte Routen
 */
class AuthMiddleware implements Middleware
{
    /**
     * CSRF-Schutz (enthält Token-Validierung)
     */
    private readonly Csrf $csrf;

    /**
     * Response-Factory
     */
    private readonly ResponseFactory $responseFactory;

    /**
     * Zu ignorierende Pfade
     *
     * @var array<string>
     */
    private array $ignoredPaths = [];

    /**
     * Konstruktor
     *
     * @param Csrf $csrf CSRF-Schutz
     * @param ResponseFactory $responseFactory Response-Factory
     * @param array $ignoredPaths Zu ignorierende Pfade (kein Auth-Check)
     */
    public function __construct(
        Csrf $csrf,
        ResponseFactory $responseFactory,
        array $ignoredPaths = []
    ) {
        $this->csrf = $csrf;
        $this->responseFactory = $responseFactory;
        $this->ignoredPaths = $ignoredPaths;
    }

    /**
     * Verarbeitet den Request und prüft Authentifizierung
     *
     * @param Request $request Der Request
     * @param callable $next Nächster Handler
     * @return Response Die Response
     */
    public function process(Request $request, callable $next): Response
    {
        // Prüfen, ob der Pfad ignoriert werden soll
        $path = parse_url($request->getUri(), PHP_URL_PATH) ?: '/';

        foreach ($this->ignoredPaths as $ignoredPath) {
            if ($this->pathMatches($path, $ignoredPath)) {
                return $next($request);
            }
        }

        // OPTIONS-Requests immer zulassen (für CORS)
        if ($request->getMethod() === 'OPTIONS') {
            return $next($request);
        }

        // API-Token aus Headers prüfen
        if (!$this->csrf->validateTokenFromHeaders($request->getHeaders())) {
            return $this->responseFactory->unauthorized([
                'error' => 'Nicht autorisiert',
                'code' => 'UNAUTHORIZED',
                'message' => 'Gültiges API-Token erforderlich'
            ]);
        }

        return $next($request);
    }

    /**
     * Prüft, ob ein Pfad einem Muster entspricht (mit Wildcards)
     *
     * @param string $path Der zu prüfende Pfad
     * @param string $pattern Das Muster (kann * enthalten)
     * @return bool True, wenn der Pfad dem Muster entspricht
     */
    private function pathMatches(string $path, string $pattern): bool
    {
        // Muster in regulären Ausdruck umwandeln (einfache * Wildcards unterstützen)
        $regex = str_replace('\\*', '.*', preg_quote($pattern, '/'));
        return (bool) preg_match('/^' . $regex . '$/', $path);
    }
}