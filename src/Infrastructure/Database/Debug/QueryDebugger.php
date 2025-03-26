<?php


declare(strict_types=1);

namespace App\Infrastructure\Database\Debug;

use App\Infrastructure\Container\Attributes\Injectable;
use App\Infrastructure\Container\Attributes\Singleton;
use App\Infrastructure\Logging\Contracts\LoggerInterface;

#[Injectable]
#[Singleton]
class QueryDebugger
{
    /** @var array<array{query: string, params: array, time: float, backtrace: ?array}> */
    private array $queries = [];
    private bool $enabled = false;
    private bool $logBacktrace = false;

    public function __construct(
        private readonly LoggerInterface $logger
    )
    {
    }

    public function enable(bool $withBacktrace = false): void
    {
        $this->enabled = true;
        $this->logBacktrace = $withBacktrace;
    }

    public function disable(): void
    {
        $this->enabled = false;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function logQuery(string $query, array $params, float $time): void
    {
        if (!$this->enabled) {
            return;
        }

        $backtrace = null;
        if ($this->logBacktrace) {
            $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5);
        }

        $this->queries[] = [
            'query' => $query,
            'params' => $params,
            'time' => $time,
            'backtrace' => $backtrace,
        ];

        $this->logger->debug('SQL Query', [
            'query' => $query,
            'params' => $params,
            'time_ms' => round($time * 1000, 2),
        ]);
    }

    /**
     * @return array<array{query: string, params: array, time: float, backtrace: ?array}>
     */
    public function getQueries(): array
    {
        return $this->queries;
    }

    public function getLastQuery(): ?array
    {
        if (empty($this->queries)) {
            return null;
        }

        return $this->queries[array_key_last($this->queries)];
    }

    public function formatQuery(string $query, array $params): string
    {
        // Replace named parameters with their values for debugging
        $formattedQuery = $query;

        foreach ($params as $name => $value) {
            $placeholder = ":{$name}";
            $formattedValue = $this->formatValue($value);
            $formattedQuery = str_replace($placeholder, $formattedValue, $formattedQuery);
        }

        // Beautify the SQL
        $formattedQuery = $this->beautifySql($formattedQuery);

        return $formattedQuery;
    }

    private function formatValue(mixed $value): string
    {
        return match (true) {
            is_null($value) => 'NULL',
            is_bool($value) => $value ? 'TRUE' : 'FALSE',
            is_int($value) || is_float($value) => (string)$value,
            is_string($value) => "'" . addslashes($value) . "'",
            is_array($value) => json_encode($value, JSON_UNESCAPED_UNICODE),
            is_object($value) && method_exists($value, '__toString') => "'" . addslashes((string)$value) . "'",
            default => "'[COMPLEX VALUE]'"
        };
    }

    private function beautifySql(string $sql): string
    {
        // Add line breaks for readability
        $keywords = ['SELECT', 'FROM', 'JOIN', 'LEFT JOIN', 'RIGHT JOIN', 'WHERE', 'GROUP BY', 'HAVING', 'ORDER BY', 'LIMIT', 'OFFSET'];

        foreach ($keywords as $keyword) {
            $sql = str_replace(" $keyword ", "\n$keyword ", $sql);
        }

        return $sql;
    }

    public function getTotalQueryTime(): float
    {
        return array_sum(array_column($this->queries, 'time'));
    }

    public function reset(): void
    {
        $this->queries = [];
    }
}