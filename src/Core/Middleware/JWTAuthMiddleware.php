<?php

declare(strict_types=1);

namespace App\Core\Middleware;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Http\ResponseFactory;
use App\Core\Security\JWTAuth;

/**
 * JWT-Authentifizierungs-Middleware
 *
 * Prüft JWT-Tokens für geschützte Routen
 */
class JWTAuthMiddleware implements Middleware
{
    /**
     * JWT-Authentifizierung
     */
    private readonly JWTAuth $jwtAuth;

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
     * @param JWTAuth $jwtAuth JWT-Authentifizierung
     * @param ResponseFactory $responseFactory Response-Factory
     * @param array $ignoredPaths Zu ignorierende Pfade (kein Auth-Check)
     */
    public function __construct(
        JWTAuth $jwtAuth,
        ResponseFactory $responseFactory,
        array $ignoredPaths = []
    ) {
        $this->jwtAuth = $jwtAuth;
        $this->responseFactory = $responseFactory;
        $this->ignoredPaths = $ignoredPaths;
    }

    /**
     * Verarbeitet den Request und prüft JWT-Authentifizierung
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

        // JWT-Token aus Headers prüfen
        $claims = $this->jwtAuth->validateTokenFromHeaders($request->getHeaders());

        if ($claims === null) {
            return $this->responseFactory->unauthorized([
                'error' => 'Nicht autorisiert',
                'code' => 'UNAUTHORIZED',
                'message' => 'Gültiges JWT-Token erforderlich'
            ]);
        }

        // Benutzer-ID im Request speichern, damit Handler darauf zugreifen können
        $userId = $claims['sub'] ?? null;
        if ($userId) {
            // In einem echten Szenario würden wir hier den Request erweitern
            // Da die Request-Klasse keine setAttribute-Methode hat, könnten wir
            // dies über einen benutzerdefinierten Request-Container im globalen Container tun
            app('auth_user_id', $userId);
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