<?php
declare(strict_types=1);

namespace App\Core\Queue;

use Predis\Client;
use Exception;
use JsonException;

/**
 * Redis-basierte Queue-Implementierung
 */
class RedisQueue implements Queue
{
    private const QUEUE_PREFIX = 'queue:';
    private const DELAYED_PREFIX = 'delayed:';
    private const JOB_PREFIX = 'job:';

    private Client $redis;
    private string $prefix;

    public function __construct(
        Client $redis,
        string $prefix = 'football_manager:queue:'
    )
    {
        $this->redis = $redis;
        $this->prefix = $prefix;
    }

    /**
     * Fügt einen Job zur Queue hinzu
     *
     * @param Job $job Job, der zur Queue hinzugefügt wird
     * @param int|null $delay Verzögerung in Sekunden
     * @return string Job-Identifier
     * @throws JsonException
     */
    public function push(Job $job, ?int $delay = null): string
    {
        // Eindeutige Job-ID generieren
        $jobId = bin2hex(random_bytes(16));
        $job->setId($jobId);

        // Job serialisieren
        $serializedJob = json_encode($job->serialize(), JSON_THROW_ON_ERROR);

        // Mit Verzögerung
        if ($delay !== null && $delay > 0) {
            $this->redis->zadd(
                $this->prefix . self::DELAYED_PREFIX . $job->getQueue(),
                [
                    $serializedJob => time() + $delay
                ]
            );
        } // Direkt in Queue
        else {
            $this->redis->rpush(
                $this->prefix . self::QUEUE_PREFIX . $job->getQueue(),
                $serializedJob
            );
        }

        // Job separat speichern für Tracking und Retry-Mechanismus
        $this->redis->set(
            $this->prefix . self::JOB_PREFIX . $jobId,
            $serializedJob
        );

        return $jobId;
    }

    /**
     * Holt und entfernt den nächsten Job aus der Queue
     *
     * @param string $queue Spezifische Queue-Instanz
     * @return Job|null Der nächste Job oder null
     * @throws JsonException
     */
    public function pop(string $queue = 'default'): ?Job
    {
        // Verzögerte Jobs zuerst überprüfen
        $this->processDelayedJobs($queue);

        // Job aus der Queue holen
        $serializedJob = $this->redis->lpop($this->prefix . self::QUEUE_PREFIX . $queue);

        if ($serializedJob === null) {
            return null;
        }

        try {
            $jobData = json_decode($serializedJob, true, 512, JSON_THROW_ON_ERROR);
            $job = Job::unserialize($jobData);

            return $job;
        } catch (Exception $e) {
            // Job-Verarbeitung fehlgeschlagen
            app_log("Queue-Job Deserialisierungsfehler: " . $e->getMessage(), [
                'job' => $serializedJob,
                'queue' => $queue
            ], 'error');

            return null;
        }
    }

    /**
     * Verarbeitet verzögerte Jobs
     *
     * @param string $queue Queue-Name
     * @throws JsonException
     */
    private function processDelayedJobs(string $queue): void
    {
        $now = time();
        $delayedKey = $this->prefix . self::DELAYED_PREFIX . $queue;
        $queueKey = $this->prefix . self::QUEUE_PREFIX . $queue;

        // Jobs finden, die bereit zur Verarbeitung sind
        $readyJobs = $this->redis->zrangebyscore($delayedKey, 0, $now);

        foreach ($readyJobs as $jobData) {
            // Job aus verzögerter Queue entfernen
            $this->redis->zrem($delayedKey, $jobData);

            // Job zur aktiven Queue hinzufügen
            $this->redis->rpush($queueKey, $jobData);
        }
    }

    /**
     * Überprüft, ob ein Job bereits in der Queue existiert
     *
     * @param string $jobId Job-Identifier
     * @return bool True, wenn Job gefunden, sonst false
     */
    public function exists(string $jobId): bool
    {
        return $this->redis->exists($this->prefix . self::JOB_PREFIX . $jobId) === 1;
    }

    /**
     * Löscht einen spezifischen Job aus der Queue
     *
     * @param string $jobId Job-Identifier
     * @return bool True bei Erfolg, sonst false
     */
    public function delete(string $jobId): bool
    {
        return $this->redis->del($this->prefix . self::JOB_PREFIX . $jobId) > 0;
    }

    /**
     * Anzahl der Jobs in einer Queue
     *
     * @param string $queue Spezifische Queue-Instanz
     * @return int Anzahl der Jobs
     */
    public function count(string $queue = 'default'): int
    {
        return $this->redis->llen($this->prefix . self::QUEUE_PREFIX . $queue);
    }

    /**
     * Leert eine komplette Queue
     *
     * @param string $queue Spezifische Queue-Instanz
     * @return bool True bei Erfolg, sonst false
     */
    public function clear(string $queue = 'default'): bool
    {
        // Aktive Queue löschen
        $this->redis->del($this->prefix . self::QUEUE_PREFIX . $queue);

        // Verzögerte Jobs löschen
        $this->redis->del($this->prefix . self::DELAYED_PREFIX . $queue);

        return true;
    }
}