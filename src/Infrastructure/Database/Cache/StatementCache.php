<?php


declare(strict_types=1);

namespace App\Infrastructure\Database\Cache;

use App\Infrastructure\Container\Attributes\Injectable;
use App\Infrastructure\Container\Attributes\Singleton;
use PDOStatement;

#[Injectable]
#[Singleton]
class StatementCache
{
    /**
     * @var array<string, PDOStatement>
     */
    private array $statements = [];

    /**
     * @var array<string, int>
     */
    private array $hits = [];

    /**
     * @var int
     */
    private int $maxSize;

    /**
     * @var array<string, int>
     */
    private array $lastUsed = [];

    public function __construct(int $maxSize = 100)
    {
        $this->maxSize = $maxSize;
    }

    public function get(string $key): ?PDOStatement
    {
        if (!isset($this->statements[$key])) {
            return null;
        }

        // Update usage statistics
        $this->hits[$key] = ($this->hits[$key] ?? 0) + 1;
        $this->lastUsed[$key] = time();

        return $this->statements[$key];
    }

    public function put(string $key, PDOStatement $statement): void
    {
        // If cache is full, evict least recently used statement
        if (count($this->statements) >= $this->maxSize) {
            $this->evictLeastRecentlyUsed();
        }

        $this->statements[$key] = $statement;
        $this->hits[$key] = 0;
        $this->lastUsed[$key] = time();
    }

    public function has(string $key): bool
    {
        return isset($this->statements[$key]);
    }

    public function remove(string $key): void
    {
        unset($this->statements[$key], $this->hits[$key], $this->lastUsed[$key]);
    }

    public function clear(): void
    {
        $this->statements = [];
        $this->hits = [];
        $this->lastUsed = [];
    }

    public function invalidateByPrefix(string $prefix): void
    {
        $keys = array_find_key($this->statements, fn($_, $key) => str_starts_with($key, $prefix));

        foreach ($keys as $key) {
            $this->remove($key);
        }
    }

    public function getStatistics(): array
    {
        return [
            'size' => count($this->statements),
            'max_size' => $this->maxSize,
            'hits' => $this->hits,
            'total_hits' => array_sum($this->hits),
        ];
    }

    private function evictLeastRecentlyUsed(): void
    {
        if (empty($this->lastUsed)) {
            return;
        }

        // Find key with oldest last used timestamp
        $oldestKey = array_find_key(
            $this->lastUsed,
            fn($timestamp, $_, $min) => $timestamp < $min,
            PHP_INT_MAX
        );

        if ($oldestKey !== null) {
            $this->remove($oldestKey);
        }
    }
}