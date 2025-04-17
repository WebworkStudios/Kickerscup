<?php

declare(strict_types=1);

namespace App\Core\Cache;

use Predis\Client;

/**
 * Redis-basierter Cache
 */
class RedisCache implements Cache
{
    /**
     * Redis-Client
     */
    private Client $redis;

    /**
     * Prefix für Schlüssel
     */
    private string $prefix;

    /**
     * Konstruktor
     *
     * @param Client $redis Redis-Client
     * @param string $prefix Prefix für Schlüssel
     */
    public function __construct(Client $redis, string $prefix = 'cache:')
    {
        $this->redis = $redis;
        $this->prefix = $prefix;
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $key): bool
    {
        return (bool)$this->redis->exists($this->prefix . $key);
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->redis->get($this->prefix . $key);

        if ($value === null) {
            return $default;
        }

        return $this->unserialize($value);
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        $key = $this->prefix . $key;
        $value = $this->serialize($value);

        if ($ttl === null) {
            return $this->redis->set($key, $value) === 'OK';
        }

        return $this->redis->setex($key, $ttl, $value) === 'OK';
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $key): bool
    {
        return (bool)$this->redis->del($this->prefix . $key);
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): bool
    {
        $keys = $this->redis->keys($this->prefix . '*');

        if (!empty($keys)) {
            return (bool)$this->redis->del($keys);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function remember(string $key, ?int $ttl, callable $callback): mixed
    {
        $prefixedKey = $this->prefix . $key;

        $value = $this->redis->get($prefixedKey);

        if ($value !== null) {
            return $this->unserialize($value);
        }

        $value = $callback();

        $serialized = $this->serialize($value);

        if ($ttl === null) {
            $this->redis->set($prefixedKey, $serialized);
        } else {
            $this->redis->setex($prefixedKey, $ttl, $serialized);
        }

        return $value;
    }

    /**
     * {@inheritdoc}
     */
    public function deletePattern(string $pattern): bool
    {
        $keys = $this->redis->keys($this->prefix . $pattern);

        if (!empty($keys)) {
            return (bool)$this->redis->del($keys);
        }

        return true;
    }

    /**
     * Serialisiert einen Wert
     *
     * @param mixed $value Wert
     * @return string Serialisierter Wert
     */
    private function serialize(mixed $value): string
    {
        return serialize($value);
    }

    /**
     * Deserialisiert einen Wert
     *
     * @param string $value Serialisierter Wert
     * @return mixed Deserialisierter Wert
     */
    private function unserialize(string $value): mixed
    {
        return unserialize($value);
    }

    /**
     * Gibt den Redis-Client zurück
     *
     * @return Client
     */
    public function getRedis(): Client
    {
        return $this->redis;
    }
}