<?php

declare(strict_types=1);

namespace App\Infrastructure\Security\Csrf;

use App\Infrastructure\Container\Attributes\Injectable;
use App\Infrastructure\Container\Attributes\Singleton;
use App\Infrastructure\Http\Contracts\RequestInterface;
use App\Infrastructure\Logging\Contracts\LoggerInterface;
use App\Infrastructure\Security\Csrf\Contracts\CsrfProtectionInterface;
use App\Infrastructure\Session\Contracts\SessionInterface;
use Random\RandomException;

/**
 * CSRF-Schutz Implementierung
 */
#[Injectable]
#[Singleton]
class CsrfProtection implements CsrfProtectionInterface
{
    /**
     * Der Prefix für CSRF-Token in der Session
     */
    protected const string TOKEN_PREFIX = 'csrf_token_';

    /**
     * Konstruktor
     */
    public function __construct(
        protected SessionInterface $session,
        protected ?LoggerInterface $logger = null
    )
    {
    }

    /**
     * {@inheritdoc}
     */
    public function validateToken(string $token, string $key = 'default'): bool
    {
        // Hole das Token aus der Session
        $storedToken = $this->session->get(self::TOKEN_PREFIX . $key);

        // Wenn kein Token in der Session ist, schlägt die Validierung fehl
        if ($storedToken === null) {
            $this->logger?->warning('CSRF-Token Validierung fehlgeschlagen: Kein Token in der Session', [
                'key' => $key
            ]);
            return false;
        }

        // Vergleiche die Tokens mit Timing-Attack-Schutz
        $valid = hash_equals($storedToken, $token);

        if (!$valid) {
            $this->logger?->warning('CSRF-Token Validierung fehlgeschlagen: Token stimmen nicht überein', [
                'key' => $key,
                'stored_token_hash' => hash('sha256', $storedToken),
                'provided_token_hash' => hash('sha256', $token)
            ]);
        }

        return $valid;
    }

    /**
     * {@inheritdoc}
     */
    public function removeToken(string $key = 'default'): bool
    {
        $this->session->remove(self::TOKEN_PREFIX . $key);
        $this->logger?->debug('CSRF-Token entfernt', ['key' => $key]);
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function shouldProtectRequest(RequestInterface $request): bool
    {
        // CSRF-Schutz nur für state-verändernde Methoden
        $stateChangingMethods = ['POST', 'PUT', 'PATCH', 'DELETE'];

        if (!in_array($request->getMethod(), $stateChangingMethods)) {
            return false;
        }

        // Keine CSRF-Validierung für AJAX-Requests mit X-Requested-With
        if ($request->getHeader('x-requested-with') === 'XMLHttpRequest'
            && $request->hasHeader('X-CSRF-TOKEN')) {
            return true;
        }

        // Keine CSRF-Validierung für API-Requests mit Authorization Header
        if ($request->hasHeader('authorization')) {
            return false;
        }

        // Check für spezielle Content-Types
        $contentType = $request->getContentType();
        if ($contentType && str_contains(strtolower($contentType), 'application/json')) {
            // Bei JSON-Requests prüfen, ob ein CSRF-Token im Header vorhanden ist
            return $request->hasHeader('X-CSRF-TOKEN');
        }

        // Standard: Schütze alle state-verändernden Requests
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function validateOrigin(array $allowedOrigins = []): bool
    {
        // Wir benötigen die $_SERVER globale Variable, da die Session nicht direkt
        // Zugriff auf die Request-Header gibt
        $origin = $_SERVER['HTTP_ORIGIN'] ?? null;
        $referer = $_SERVER['HTTP_REFERER'] ?? null;
        $host = $_SERVER['HTTP_HOST'] ?? null;

        // Wenn kein Origin oder Referer gesetzt ist, ist die Validierung abhängig vom Kontext
        if (!$origin && !$referer) {
            // In Produktionsumgebungen sollten wir hier strenger sein
            return true;
        }

        // Wenn Origin gesetzt ist, muss dieser zur erlaubten Liste gehören
        if ($origin) {
            // Wenn erlaubte Origins leer ist, nur die eigene Domain erlauben
            if (empty($allowedOrigins)) {
                $parsedOrigin = parse_url($origin, PHP_URL_HOST);
                return $parsedOrigin === $host;
            }

            // Andernfalls gegen die erlaubte Liste prüfen
            return in_array($origin, $allowedOrigins);
        }

        // Wenn Referer gesetzt ist, muss dieser von der gleichen Domain sein
        if ($referer) {
            $parsedReferer = parse_url($referer, PHP_URL_HOST);
            return $parsedReferer === $host;
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function getTokenField(string $key = 'default'): string
    {
        $token = $this->session->get(self::TOKEN_PREFIX . $key);

        // Wenn kein Token existiert, generiere eines
        if ($token === null) {
            $token = $this->generateToken($key);
        }

        return sprintf(
            '<input type="hidden" name="_csrf_token" value="%s">',
            htmlspecialchars($token, ENT_QUOTES, 'UTF-8')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function generateToken(string $key = 'default'): string
    {
        $token = $this->generateRandomToken();


        // Speichere das Token in der Session
        $this->session->set(self::TOKEN_PREFIX . $key, $token);

        $this->logger?->debug('CSRF-Token generiert', [
            'key' => $key,
            'token_hash' => hash('sha256', $token)
        ]);

        return $token;
    }

    /**
     * Generiert ein zufälliges Token
     * @throws RandomException
     */
    protected function generateRandomToken(): string
    {
        if (function_exists('random_bytes')) {
            return bin2hex(random_bytes(32));
        }

        // Fallback, weniger sicher
        return bin2hex(openssl_random_pseudo_bytes(32));
    }
}