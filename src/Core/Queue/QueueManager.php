<?php
declare(strict_types=1);

namespace App\Core\Queue;

use App\Core\Container\Container;
use App\Core\Database\DatabaseManager;
use InvalidArgumentException;
use Predis\Client;

/**
 * Manager für verschiedene Queue-Treiber
 *
 * Factory-Klasse, die verschiedene Queue-Implementierungen erstellt und verwaltet.
 */
class QueueManager
{
    /**
     * Container für Dependency Injection
     */
    private readonly Container $container;

    /**
     * Queue-Konfiguration
     */
    private readonly array $config;

    /**
     * Cache für Queue-Instanzen
     *
     * @var array<string, Queue>
     */
    private array $queues = [];

    /**
     * Konstruktor
     *
     * @param Container $container Container für Dependency Injection
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->config = config('queue', []);
    }

    /**
     * Gibt eine Queue-Instanz zurück
     *
     * @param string|null $driver Treiber-Name, null für Standard-Treiber
     * @return Queue Queue-Instanz
     * @throws InvalidArgumentException Wenn der Treiber nicht unterstützt wird
     */
    public function connection(?string $driver = null): Queue
    {
        $driver = $driver ?? $this->config['default'] ?? 'redis';

        // Cache prüfen
        if (isset($this->queues[$driver])) {
            return $this->queues[$driver];
        }

        // Neue Instanz erstellen
        $this->queues[$driver] = match ($driver) {
            'redis' => $this->createRedisQueue(),
            'database' => $this->createDatabaseQueue(),
            'sync' => $this->createSyncQueue(),
            default => throw new InvalidArgumentException("Nicht unterstützter Queue-Treiber: {$driver}")
        };

        return $this->queues[$driver];
    }

    /**
     * Erstellt eine RedisQueue-Instanz
     *
     * @return RedisQueue
     */
    private function createRedisQueue(): RedisQueue
    {
        $config = $this->config['drivers']['redis'] ?? [];
        $connectionName = $config['connection'] ?? 'queue';
        $prefix = $config['prefix'] ?? 'football_manager:queue:';

        // Redis-Client aus der Konfiguration holen
        $redis = $this->container->make(Client::class, [
            'parameters' => config("database.redis.{$connectionName}")
        ]);

        return new RedisQueue($redis, $prefix);
    }

    /**
     * Erstellt eine DatabaseQueue-Instanz
     *
     * @return DatabaseQueue
     */
    private function createDatabaseQueue(): DatabaseQueue
    {
        $config = $this->config['drivers']['database'] ?? [];
        $table = $config['table'] ?? 'jobs';
        $retryAfter = $config['retry_after'] ?? 90;

        // Datenbank-Manager holen
        $db = $this->container->make(DatabaseManager::class);

        return new DatabaseQueue($db, $table, $retryAfter);
    }

    /**
     * Erstellt eine SyncQueue-Instanz
     *
     * @return SyncQueue
     */
    private function createSyncQueue(): SyncQueue
    {
        $config = $this->config['drivers']['sync'] ?? [];
        $failOnError = $config['fail_on_error'] ?? true;

        return new SyncQueue($this->container, $failOnError);
    }

    /**
     * Erstellt einen QueueWorker
     *
     * @param string|null $driver Treiber-Name, null für Standard-Treiber
     * @param array $config Worker-Konfiguration
     * @return QueueWorker
     */
    public function worker(?string $driver = null, array $config = []): QueueWorker
    {
        $queue = $this->connection($driver);

        return new QueueWorker(
            $queue,
            $this->container,
            array_merge($this->config['worker'] ?? [], $config)
        );
    }
}