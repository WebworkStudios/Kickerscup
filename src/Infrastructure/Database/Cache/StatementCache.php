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

    /**
     * @param string $prefix
     * @return void
     */
    public function invalidateByPrefix(string $prefix): void
    {
        if (empty($prefix)) {
            return;
        }

        // Sammle alle Schlüssel, die mit dem Präfix beginnen
        $keysToRemove = [];
        foreach (array_keys($this->statements) as $key) {
            if (str_starts_with($key, $prefix)) {
                $keysToRemove[] = $key;
            }
        }

        // Entferne alle gesammelten Schlüssel
        foreach ($keysToRemove as $key) {
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
        $oldestTimestamp = PHP_INT_MAX;
        $oldestKey = null;

        foreach ($this->lastUsed as $key => $timestamp) {
            if ($timestamp < $oldestTimestamp) {
                $oldestTimestamp = $timestamp;
                $oldestKey = $key;
            }
        }

        if ($oldestKey !== null) {
            $this->remove($oldestKey);
        }
    }
}