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
     * Speichert Token-Daten
     */
    public function store(string $token, array $data, int $lifetime): bool
    {
        return $this->cache->set(self::PREFIX . $token, $data, $lifetime);
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