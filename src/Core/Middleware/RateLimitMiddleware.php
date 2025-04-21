<?php

declare(strict_types=1);

namespace App\Core\Middleware;

use App\Core\Cache\Cache;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Http\ResponseFactory;

/**
 * Rate-Limiting Middleware
 *
 * Implementiert Rate-Limiting für API-Endpunkte, um Missbrauch zu verhindern
 */
class RateLimitMiddleware implements Middleware
{
    /**
     * Cache-Instanz zum Speichern der Rate-Limit-Daten
     */
    private Cache $cache;

    /**
     * Response-Factory für die Erzeugung von Fehlermeldungen
     */
    private ResponseFactory $responseFactory;

    /**
     * Konfiguration für verschiedene Rate-Limit-Regeln
     *
     * @var array<string, array>
     */
    private array $limiters;

    /**
     * Pfade, die vom Rate-Limiting ausgenommen sind
     *
     * @var array<string>
     */
    private array $ignoredPaths;

    /**
     * Konstruktor
     *
     * @param Cache $cache Cache-Instanz
     * @param ResponseFactory $responseFactory Factory für Responses
     * @param array $limiters Konfiguration für verschiedene Rate-Limit-Regeln
     * @param array $ignoredPaths Pfade, die vom Rate-Limiting ausgenommen sind
     */
    public function __construct(
        Cache $cache,
        ResponseFactory $responseFactory,
        array $limiters = [],
        array $ignoredPaths = []
    ) {
        $this->cache = $cache;
        $this->responseFactory = $responseFactory;
        $this->limiters = $limiters;
        $this->ignoredPaths = $ignoredPaths;
    }

    /**
     * Verarbeitet einen Request und prüft Rate-Limits
     *
     * @param Request $request Eingehender Request
     * @param callable $next Nächster Handler in der Kette
     * @return Response Resultierende Response
     */
    public function process(Request $request, callable $next): Response
    {
        // Vorbereitungen für Rate-Limiting
        $path = $this->normalizePath($request->getUri());

        // Prüfen, ob der Pfad ignoriert werden soll
        if ($this->shouldIgnorePath($path)) {
            return $next($request);
        }

        // Identifier für den Client ermitteln (IP + optional ApiKey/UserId)
        $identifier = $this->getClientIdentifier($request);

        // Passende Limiter für den Pfad finden
        $limits = $this->getLimitsForPath($path);

        // Wenn keine Limits definiert sind, direkt weitergeben
        if (empty($limits)) {
            return $next($request);
        }

        // Alle anwendbaren Limits prüfen
        foreach ($limits as $name => $config) {
            $key = "ratelimit:{$identifier}:{$name}";
            $limit = $config['limit'] ?? 60;
            $window = $config['window'] ?? 60;

            $exceeded = $this->checkRateLimit($key, $limit, $window);

            if ($exceeded) {
                // Bestimmen, wann der nächste Request möglich ist
                $retryAfter = $this->getRetryAfter($key, $window);

                return $this->responseFactory->tooManyRequests(
                    $retryAfter,
                    'Rate limit exceeded. Please try again later.',
                    [
                        'X-RateLimit-Limit' => (string)$limit,
                        'X-RateLimit-Reset' => (string)time() + $retryAfter,
                        'Retry-After' => (string)$retryAfter
                    ]
                );
            }
        }

        // Rate-Limit nicht überschritten, Request weitergeben
        $response = $next($request);

        // Headers für Rate-Limit-Informationen hinzufügen
        if (!empty($limits)) {
            $mainLimit = reset($limits);
            $mainLimitName = key($limits);
            $key = "ratelimit:{$identifier}:{$mainLimitName}";

            $remaining = $this->getRemainingAttempts($key, $mainLimit['limit'] ?? 60);
            $resetTime = $this->getResetTime($key, $mainLimit['window'] ?? 60);

            $response->setHeader('X-RateLimit-Limit', (string)($mainLimit['limit'] ?? 60));
            $response->setHeader('X-RateLimit-Remaining', (string)max(0, $remaining));
            $response->setHeader('X-RateLimit-Reset', (string)$resetTime);
        }

        return $response;
    }

    /**
     * Prüft, ob ein Rate-Limit überschritten wurde
     *
     * @param string $key Cache-Schlüssel
     * @param int $limit Maximale Anzahl von Requests
     * @param int $window Zeitfenster in Sekunden
     * @return bool True, wenn Rate-Limit überschritten wurde
     */
    private function checkRateLimit(string $key, int $limit, int $window): bool
    {
        $timestamps = $this->cache->get($key, []);
        $now = time();

        // Alte Timestamps entfernen
        $timestamps = array_filter($timestamps, fn($timestamp) => $timestamp >= $now - $window);

        // Prüfen, ob das Limit überschritten wurde
        if (count($timestamps) >= $limit) {
            return true;
        }

        // Aktuellen Timestamp hinzufügen
        $timestamps[] = $now;

        // Im Cache speichern
        $this->cache->set($key, $timestamps, $window);

        return false;
    }

    /**
     * Ermittelt, wie viele Versuche noch übrig sind
     *
     * @param string $key Cache-Schlüssel
     * @param int $limit Maximale Anzahl von Requests
     * @return int Anzahl der verbleibenden Versuche
     */
    private function getRemainingAttempts(string $key, int $limit): int
    {
        $timestamps = $this->cache->get($key, []);
        return $limit - count($timestamps);
    }

    /**
     * Ermittelt den Zeitpunkt, an dem das Rate-Limit zurückgesetzt wird
     *
     * @param string $key Cache-Schlüssel
     * @param int $window Zeitfenster in Sekunden
     * @return int Timestamp für das Zurücksetzen
     */
    private function getResetTime(string $key, int $window): int
    {
        $timestamps = $this->cache->get($key, []);

        if (empty($timestamps)) {
            return time() + $window;
        }

        // Ältester Timestamp + Zeitfenster
        return min($timestamps) + $window;
    }

    /**
     * Ermittelt, wie lange gewartet werden muss, bis der nächste Request möglich ist
     *
     * @param string $key Cache-Schlüssel
     * @param int $window Zeitfenster in Sekunden
     * @return int Wartezeit in Sekunden
     */
    private function getRetryAfter(string $key, int $window): int
    {
        $resetTime = $this->getResetTime($key, $window);
        return max(1, $resetTime - time());
    }

    /**
     * Normalisiert einen Pfad
     *
     * @param string $path Pfad
     * @return string Normalisierter Pfad
     */
    private function normalizePath(string $path): string
    {
        $path = parse_url($path, PHP_URL_PATH) ?: '/';
        return '/' . trim($path, '/');
    }

    /**
     * Prüft, ob ein Pfad ignoriert werden soll
     *
     * @param string $path Zu prüfender Pfad
     * @return bool True, wenn der Pfad ignoriert werden soll
     */
    private function shouldIgnorePath(string $path): bool
    {
        return array_any($this->ignoredPaths, fn($pattern) => $this->pathMatchesPattern($path, $pattern));
    }

    /**
     * Prüft, ob ein Pfad einem Muster entspricht
     *
     * @param string $path Zu prüfender Pfad
     * @param string $pattern Muster mit Wildcards (*)
     * @return bool True, wenn der Pfad dem Muster entspricht
     */
    private function pathMatchesPattern(string $path, string $pattern): bool
    {
        $pattern = str_replace('\*', '.*', preg_quote($pattern, '/'));
        return (bool)preg_match('/^' . $pattern . '$/', $path);
    }

    /**
     * Ermittelt einen eindeutigen Identifier für den Client
     *
     * @param Request $request Request
     * @return string Client-Identifier
     */
    private function getClientIdentifier(Request $request): string
    {
        // IP-Adresse als Basis
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        // Wenn API-Token oder Authorization-Header vorhanden, diesen mit einbeziehen
        $authToken = $request->getHeader('Authorization');
        $apiToken = $request->getHeader('X-API-Token');

        if ($apiToken) {
            return md5($ip . ':' . $apiToken);
        }

        if ($authToken) {
            return md5($ip . ':' . $authToken);
        }

        // Ansonsten nur IP verwenden
        return md5($ip);
    }

    /**
     * Ermittelt die passenden Rate-Limits für einen Pfad
     *
     * @param string $path Pfad
     * @return array<string, array> Anwendbare Rate-Limits
     */
    private function getLimitsForPath(string $path): array
    {
        $applicableLimits = [];

        foreach ($this->limiters as $name => $config) {
            $paths = $config['paths'] ?? [];

            foreach ($paths as $pattern) {
                if ($this->pathMatchesPattern($path, $pattern)) {
                    $applicableLimits[$name] = [
                        'limit' => $config['limit'] ?? 60,
                        'window' => $config['window'] ?? 60
                    ];
                    break;
                }
            }
        }

        return $applicableLimits;
    }
}