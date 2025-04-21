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
    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->redis->get($this->prefix . $key);

        if ($value === null) {
            return $default;
        }

        return $this->unserialize($value);
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

    public function deletePattern(string $pattern): bool
    {
        $keys = $this->redis->keys($this->prefix . $pattern);

        // Bei großen Datensätzen in Blöcken löschen
        if (!empty($keys)) {
            // In Blöcken von 1000 Schlüsseln löschen
            foreach (array_chunk($keys, 1000) as $chunk) {
                $this->redis->del($chunk);
            }
        }

        return true;
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

    /**
     * {@inheritdoc}
     */
    public function setMultiple(array $values, ?int $ttl = null): bool
    {
        // Optimierte Implementierung für Redis
        $pipeline = $this->redis->pipeline();

        foreach ($values as $key => $value) {
            $prefixedKey = $this->prefix . $key;
            $serialized = $this->serialize($value);

            if ($ttl === null) {
                $pipeline->set($prefixedKey, $serialized);
            } else {
                $pipeline->setex($prefixedKey, $ttl, $serialized);
            }
        }

        $results = $pipeline->execute();

        // Wenn alle Befehle erfolgreich waren, ist jeder Wert 'OK'
        return !array_any($results, fn($result) => $result !== 'OK');
    }

    /**
     * {@inheritdoc}
     */
    public function getMultiple(array $keys, mixed $default = null): array
    {
        $prefixedKeys = array_map(fn($key) => $this->prefix . $key, $keys);
        $values = $this->redis->mget($prefixedKeys);

        $result = [];
        foreach ($keys as $i => $key) {
            $value = $values[$i] ?? null;
            $result[$key] = $value !== null ? $this->unserialize($value) : $default;
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteMultiple(array $keys): bool
    {
        if (empty($keys)) {
            return true;
        }

        $prefixedKeys = array_map(fn($key) => $this->prefix . $key, $keys);
        return (bool)$this->redis->del($prefixedKeys);
    }

    /**
     * {@inheritdoc}
     */
    public function increment(string $key, int $amount = 1): int|false
    {
        return $this->redis->incrby($this->prefix . $key, $amount);
    }

    /**
     * {@inheritdoc}
     */
    public function decrement(string $key, int $amount = 1): int|false
    {
        return $this->redis->decrby($this->prefix . $key, $amount);
    }

    /**
     * {@inheritdoc}
     */
    public function add(string $key, mixed $value, ?int $ttl = null): bool
    {
        $prefixedKey = $this->prefix . $key;
        $serialized = $this->serialize($value);

        // Redis NX-Option für "nur setzen wenn nicht existiert"
        if ($ttl === null) {
            return $this->redis->set($prefixedKey, $serialized, ['nx']) === 'OK';
        } else {
            return $this->redis->set($prefixedKey, $serialized, ['ex' => $ttl, 'nx']) === 'OK';
        }
    }
}