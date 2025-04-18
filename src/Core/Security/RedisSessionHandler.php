<?php

declare(strict_types=1);

namespace App\Core\Security;

use Redis;

/**
 * Redis-basierter Session-Handler für verbesserte Skalierbarkeit
 */
class RedisSessionHandler implements \SessionHandlerInterface
{
    private Redis $redis;
    private int $ttl;
    private string $prefix;

    /**
     * Konstruktor
     *
     * @param Redis $redis Redis-Instanz
     * @param int $ttl Lebensdauer der Session in Sekunden
     * @param string $prefix Präfix für Session-Keys
     */
    public function __construct(Redis $redis, int $ttl = 7200, string $prefix = 'session:')
    {
        $this->redis = $redis;
        $this->ttl = $ttl;
        $this->prefix = $prefix;
    }

    /**
     * {@inheritdoc}
     */
    public function open(string $path, string $name): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function close(): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function read(string $id): string|false
    {
        $data = $this->redis->get($this->prefix . $id);
        
        return $data !== false ? $data : '';
    }

    /**
     * {@inheritdoc}
     */
    public function write(string $id, string $data): bool
    {
        return $this->redis->setex($this->prefix . $id, $this->ttl, $data);
    }

    /**
     * {@inheritdoc}
     */
    public function destroy(string $id): bool
    {
        $this->redis->del($this->prefix . $id);
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function gc(int $max_lifetime): int|false
    {
        // Redis kümmert sich automatisch um das Löschen abgelaufener Einträge
        return 0;
    }
}