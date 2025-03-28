<?php


declare(strict_types=1);

namespace App\Infrastructure\Security\Csrf\Middleware;

use App\Infrastructure\Container\Attributes\Injectable;
use App\Infrastructure\Http\Contracts\RequestInterface;
use App\Infrastructure\Http\Contracts\ResponseFactoryInterface;
use App\Infrastructure\Http\Contracts\ResponseInterface;
use App\Infrastructure\Security\Csrf\Contracts\CsrfProtectionInterface;
use Closure;

/**
 * Middleware für CSRF-Schutz
 */
#[Injectable]
class CsrfMiddleware
{
    /**
     * Konstruktor
     */
    public function __construct(
        protected CsrfProtectionInterface  $csrfProtection,
        protected ResponseFactoryInterface $responseFactory
    )
    {
    }

    /**
     * Führt die Middleware aus
     *
     * @param RequestInterface $request Der HTTP-Request
     * @param Closure $next Die nächste Middleware oder Handler
     * @param array $options Optionen für die Middleware
     * @return ResponseInterface Die Response
     */
    public function handle(RequestInterface $request, Closure $next, array $options = []): ResponseInterface
    {
        // Prüfen, ob CSRF-Schutz aktiviert ist
        $enabled = $options['enabled'] ?? true;
        if (!$enabled) {
            return $next($request);
        }

        // Prüfen, ob der Request geschützt werden sollte
        if (!$this->csrfProtection->shouldProtectRequest($request)) {
            return $next($request);
        }

        // Token-Schlüssel aus den Optionen holen
        $tokenKey = $options['tokenKey'] ?? 'default';

        // Token aus dem Request extrahieren
        $token = $this->getTokenFromRequest($request);

        if (!$token || !$this->csrfProtection->validateToken($token, $tokenKey)) {
            return $this->handleCsrfError("CSRF-Token ungültig oder nicht vorhanden.");
        }

        // Origin-Validierung
        $validateOrigin = $options['validateOrigin'] ?? true;
        if ($validateOrigin) {
            $allowedOrigins = $options['allowedOrigins'] ?? [];
            if (!$this->csrfProtection->validateOrigin($allowedOrigins)) {
                return $this->handleCsrfError("Ungültiger Request-Origin");
            }
        }

        return $next($request);
    }

    /**
     * Extrahiert das CSRF-Token aus dem Request
     *
     * @param RequestInterface $request Der HTTP-Request
     * @return string|null Das CSRF-Token oder null
     */
    protected function getTokenFromRequest(RequestInterface $request): ?string
    {
        // Token aus POST/PUT-Parameter
        $token = $request->getInput('_csrf_token');
        if ($token) {
            return $token;
        }

        // Token aus Header (für AJAX-Requests)
        $token = $request->getHeader('X-CSRF-TOKEN');
        if ($token) {
            return $token;
        }

        // Token aus X-XSRF-TOKEN Header (für AJAX mit Cookies)
        $token = $request->getHeader('X-XSRF-TOKEN');
        if ($token) {
            return $token;
        }

        return null;
    }

    /**
     * Behandelt CSRF-Fehler
     *
     * @param string $message Die Fehlermeldung
     * @return ResponseInterface Die Fehler-Response
     */
    protected function handleCsrfError(string $message = "CSRF-Fehler"): ResponseInterface
    {
        // Bei AJAX-Requests eine JSON-Antwort senden
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            return $this->responseFactory->createJson([
                'error' => true,
                'message' => $message,
                'code' => 'csrf_error'
            ], 403);
        }

        // Andernfalls eine HTML-Antwort
        $html = '<!DOCTYPE html><html lang=""><head><title>CSRF-Fehler</title>';
        $html .= '<style>body{font-family:sans-serif;margin:40px;line-height:1.6;}';
        $html .= '.error{background:#f8d7da;color:#721c24;padding:20px;border-radius:4px;}</style></head>';
        $html .= '<body><div class="error"><h1>CSRF-Fehler</h1>';
        $html .= '<p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>';
        $html .= '<p>Bitte laden Sie die Seite neu und versuchen Sie es erneut.</p>';
        $html .= '</div></body></html>';

        return $this->responseFactory->createHtml($html, 403);
    }
}