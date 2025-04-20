<?php

declare(strict_types=1);

namespace App\Core\Middleware;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Http\ResponseFactory;
use App\Core\Security\Auth;

/**
 * Einheitliche Authentifizierungs-Middleware
 */
class AuthMiddleware implements Middleware
{
    private readonly Auth $auth;
    private readonly ResponseFactory $responseFactory;
    private array $ignoredPaths = [];

    public function __construct(
        Auth $auth,
        ResponseFactory $responseFactory,
        array $ignoredPaths = []
    ) {
        $this->auth = $auth;
        $this->responseFactory = $responseFactory;
        $this->ignoredPaths = $ignoredPaths;
    }

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

        // Token aus Headers prüfen
        $claims = $this->auth->validateTokenFromHeaders($request->getHeaders());

        if ($claims === null) {
            return $this->responseFactory->unauthorized([
                'error' => 'Nicht autorisiert',
                'code' => 'UNAUTHORIZED',
                'message' => 'Gültiges Token erforderlich'
            ]);
        }

        // Benutzer-ID im Request speichern
        $userId = $claims['sub'] ?? $claims['user_id'] ?? null;
        if ($userId) {
            app('auth_user_id', $userId);
        }

        return $next($request);
    }

    private function pathMatches(string $path, string $pattern): bool
    {
        $regex = str_replace('\\*', '.*', preg_quote($pattern, '/'));
        return (bool)preg_match('/^' . $regex . '$/', $path);
    }
}