<?php


declare(strict_types=1);

namespace App\Core\Security;

use App\Core\Cache\Cache;

/**
 * Einfacher Token-Storage für API-Token-Verwaltung
 */
class TokenStorage
{
    private const PREFIX = 'token:';

    public function __construct(
        private readonly Cache $cache
    )
    {
    }

    /**
     * Speichert Token-Daten mit automatischen Verfallszeiten
     */
    public function store(string $token, array $data, int $lifetime): bool
    {
        // In PHP 8.4 können wir array_filter mit kürzerer Syntax verwenden
        $data = array_filter($data, fn($value) => $value !== null);

        // Sicherstellen, dass Last-Used immer gesetzt ist
        $data['last_used'] ??= time();

        return $this->cache->set(self::PREFIX . $token, $data, $lifetime);
    }

    /**
     * Batch-Operation für mehrere Tokens gleichzeitig (Performance-Optimierung)
     */
    public function batchStore(array $tokens, array $data, int $lifetime): bool
    {
        if (!$tokens) {
            return true;
        }

        // Prüfen ob Cache-Implementierung Batch-Operationen unterstützt
        if (method_exists($this->cache, 'setMultiple')) {
            $batchData = [];
            foreach ($tokens as $i => $token) {
                $batchData[self::PREFIX . $token] = $data[$i] ?? $data;
            }
            return $this->cache->setMultiple($batchData, $lifetime);
        }

        // Fallback für Caches ohne Batch-Support
        $success = true;
        foreach ($tokens as $i => $token) {
            $tokenData = $data[$i] ?? $data;
            $success = $this->store($token, $tokenData, $lifetime) && $success;
        }
        return $success;
    }

    /**
     * Holt Token-Daten
     */
    public function get(string $token): ?array
    {
        return $this->cache->get(self::PREFIX . $token);
    }

    /**
     * Prüft, ob ein Token existiert
     */
    public function has(string $token): bool
    {
        return $this->cache->has(self::PREFIX . $token);
    }

    /**
     * Löscht ein Token
     */
    public function remove(string $token): bool
    {
        return $this->cache->delete(self::PREFIX . $token);
    }

    /**
     * Setzt eine Rate-Limit-Info
     */
    public function setRateLimit(string $key, array $timestamps, int $window): bool
    {
        return $this->cache->set(self::PREFIX . "rate_limit:{$key}", $timestamps, $window);
    }

    /**
     * Holt Rate-Limit-Info
     */
    public function getRateLimit(string $key): array
    {
        return $this->cache->get(self::PREFIX . "rate_limit:{$key}", []);
    }
}