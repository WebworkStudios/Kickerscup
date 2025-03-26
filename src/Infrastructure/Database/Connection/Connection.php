<?php


declare(strict_types=1);

namespace App\Infrastructure\Database\Connection;

use App\Infrastructure\Database\Contracts\ConnectionInterface;
use App\Infrastructure\Database\Exceptions\ConnectionException;
use App\Infrastructure\Logging\Contracts\LoggerInterface;
use PDO;
use PDOException;
use PDOStatement;

class Connection implements ConnectionInterface
{
    private ?PDO $pdo = null;
    private bool $connected = false;
    private int $reconnectAttempts = 0;
    private const MAX_RECONNECT_ATTEMPTS = 3;

    public function __construct(
        private readonly ConnectionConfiguration $config,
        private readonly LoggerInterface         $logger
    )
    {
    }

    public function getPdo(): PDO
    {
        if (!$this->isConnected()) {
            $this->connect();
        }

        return $this->pdo;
    }

    public function query(string $query, array $params = []): PDOStatement
    {
        try {
            $startTime = microtime(true);
            $statement = $this->prepareAndExecute($query, $params);
            $endTime = microtime(true);

            // Log query if debugging is enabled
            if ($this->container->has(QueryDebugger::class)) {
                $debugger = $this->container->get(QueryDebugger::class);
                if ($debugger->isEnabled()) {
                    $debugger->logQuery($query, $params, $endTime - $startTime);
                }
            }

            $this->reconnectAttempts = 0; // Reset reconnect counter on success
            return $statement;
        } catch (PDOException $e) {
            // Verbindungsverlust erkennen (MySQL-spezifische Fehlercodes)
            if ($this->isConnectionLossError($e) && $this->reconnectAttempts < self::MAX_RECONNECT_ATTEMPTS) {
                $this->reconnectAttempts++;
                $this->logger->warning('Database connection lost. Attempting to reconnect.', [
                    'attempt' => $this->reconnectAttempts,
                    'max_attempts' => self::MAX_RECONNECT_ATTEMPTS
                ]);

                $this->connected = false;
                $this->connect();

                // Erneuter Versuch nach Wiederverbindung
                return $this->query($query, $params);
            }

            $this->logger->error('Database query error', [
                'query' => $query,
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);

            throw new ConnectionException(
                'Error executing query: ' . $e->getMessage(),
                (int)$e->getCode(),
                $e
            );
        }
    }

    public function queryFirst(string $query, array $params = []): ?array
    {
        $statement = $this->query($query, $params);
        $result = $statement->fetch(PDO::FETCH_ASSOC);

        return $result !== false ? $result : null;
    }

    public function queryAll(string $query, array $params = []): array
    {
        $statement = $this->query($query, $params);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function beginTransaction(): bool
    {
        return $this->getPdo()->beginTransaction();
    }

    public function commit(): bool
    {
        return $this->getPdo()->commit();
    }

    public function rollback(): bool
    {
        return $this->getPdo()->rollBack();
    }

    public function inTransaction(): bool
    {
        return $this->isConnected() && $this->pdo->inTransaction();
    }

    public function isConnected(): bool
    {
        return $this->connected && $this->pdo !== null;
    }

    public function connect(): void
    {
        if ($this->isConnected()) {
            return;
        }

        try {
            $dsn = $this->buildDsn();

            $this->pdo = new PDO(
                $dsn,
                $this->config->username,
                $this->config->password,
                $this->config->options
            );

            // Konfiguriere PDO
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

            // Setze Zeichensatz, wenn konfiguriert
            if ($this->config->charset) {
                $this->pdo->exec("SET NAMES '{$this->config->charset}'");
            }

            $this->connected = true;
            $this->logger->info('Database connection established', [
                'database' => $this->config->database,
                'host' => $this->config->host
            ]);
        } catch (PDOException $e) {
            $this->logger->error('Database connection failed', [
                'error' => $e->getMessage(),
                'database' => $this->config->database,
                'host' => $this->config->host
            ]);

            throw new ConnectionException(
                'Failed to connect to database: ' . $e->getMessage(),
                (int)$e->getCode(),
                $e
            );
        }
    }

    public function disconnect(): void
    {
        $this->pdo = null;
        $this->connected = false;
        $this->logger->debug('Database connection closed');
    }

    private function buildDsn(): string
    {
        $driver = $this->config->driver ?? 'mysql';

        return match ($driver) {
            'mysql' => $this->buildMysqlDsn(),
            'pgsql' => $this->buildPgsqlDsn(),
            'sqlite' => $this->buildSqliteDsn(),
            default => throw new ConnectionException("Unsupported database driver: {$driver}")
        };
    }

    private function buildMysqlDsn(): string
    {
        $dsn = "mysql:host={$this->config->host};dbname={$this->config->database}";

        if ($this->config->port) {
            $dsn .= ";port={$this->config->port}";
        }

        if ($this->config->charset) {
            $dsn .= ";charset={$this->config->charset}";
        }

        return $dsn;
    }

    private function buildPgsqlDsn(): string
    {
        $dsn = "pgsql:host={$this->config->host};dbname={$this->config->database}";

        if ($this->config->port) {
            $dsn .= ";port={$this->config->port}";
        }

        return $dsn;
    }

    private function buildSqliteDsn(): string
    {
        return "sqlite:{$this->config->database}";
    }

    private function prepareAndExecute(string $query, array $params): PDOStatement
    {
        $cacheKey = null;
        $statement = null;

        // Try to get from cache if available
        if ($this->container->has(StatementCache::class)) {
            $cache = $this->container->get(StatementCache::class);
            $cacheKey = $this->generateStatementCacheKey($query, $params);
            $statement = $cache->get($cacheKey);
        }

        // If not in cache or cache not available, prepare statement
        if ($statement === null) {
            $statement = $this->getPdo()->prepare($query);

            // Store in cache if available
            if ($cacheKey !== null && isset($cache)) {
                $cache->put($cacheKey, $statement);
            }
        }

        // Execute with parameters
        $statement->execute($params);

        return $statement;
    }

    /**
     * @param string $query
     * @param array $params
     * @return string
     */
    private function generateStatementCacheKey(string $query, array $params = []): string
    {
        $paramString = '';
        if (!empty($params)) {
            ksort($params);
            $paramString = json_encode($params);
        }

        return md5($this->config->driver . $this->config->host . $this->config->database . $query . $paramString);
    }

    /**
     * @param PDOException $e
     * @return bool
     */
    private function isConnectionLossError(PDOException $e): bool
    {
        // MySQL-spezifische Fehlercodes für Verbindungsverlust
        $connectionErrorCodes = [
            2006, // MySQL server has gone away
            2013, // Lost connection to MySQL server during query
            2003, // Can't connect to MySQL server
        ];

        return in_array((int)$e->getCode(), $connectionErrorCodes);
    }
}