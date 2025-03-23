<?php


declare(strict_types=1);

namespace App\Infrastructure\Session\Store;

use App\Infrastructure\Container\Attributes\Injectable;
use App\Infrastructure\Session\Contracts\SessionStoreInterface;
use Redis;

#[Injectable]
class RedisSessionStore implements SessionStoreInterface
{
    private Redis $redis;
    private string $prefix;
    private int $ttl;

    public function __construct(
        Redis  $redis,
        string $prefix = 'sess:',
        int    $ttl = 86400
    )
    {
        $this->redis = $redis;
        $this->prefix = $prefix;
        $this->ttl = $ttl;
    }

    public function open(string $path, string $name): bool
    {
        return $this->redis->isConnected();
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string
    {
        $data = $this->redis->get($this->prefix . $id);
        return $data !== false ? (string)$data : '';
    }

    public function write(string $id, string $data): bool
    {
        return $this->redis->setex($this->prefix . $id, $this->ttl, $data);
    }

    public function destroy(string $id): bool
    {
        return $this->redis->del($this->prefix . $id) > 0;
    }

    public function gc(int $maxlifetime): bool
    {
        // Redis übernimmt das Ablaufen automatisch
        return true;
    }
}