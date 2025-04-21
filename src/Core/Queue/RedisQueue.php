<?php

declare(strict_types=1);

namespace App\Core\Queue;

use Predis\Client;
use Exception;
use JsonException;
use RuntimeException;
use ReflectionException;

/**
 * Redis-basierte Queue-Implementierung
 *
 * Verwendet Redis als Backend für die Job-Queue mit Unterstützung für
 * verzögerte Jobs, Job-Wiederholungen und persistente Job-Daten.
 */
class RedisQueue implements Queue
{
    /**
     * Redis-Schlüssel-Präfixe
     */
    private const QUEUE_PREFIX = 'queue:';
    private const DELAYED_PREFIX = 'delayed:';
    private const JOB_PREFIX = 'job:';
    private const TAGS_PREFIX = 'tags:';

    /**
     * Redis-Client
     */
    private readonly Client $redis;

    /**
     * Präfix für alle Redis-Schlüssel
     */
    private readonly string $prefix;

    /**
     * Konstruktor
     *
     * @param Client $redis Redis-Client
     * @param string $prefix Präfix für Redis-Schlüssel
     */
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
     * @throws JsonException|ReflectionException Wenn Serialisierung fehlschlägt
     */
    public function push(Job $job, ?int $delay = null): string
    {
        // Eindeutige Job-ID generieren falls noch nicht vorhanden
        $jobId = $job->getId() ?? bin2hex(random_bytes(16));
        $job->setId($jobId);

        // Job serialisieren
        $serializedJob = $this->serializeJob($job);

        // Mit Verzögerung
        if ($delay !== null && $delay > 0) {
            $this->pushDelayed($job, $serializedJob, $delay);
        } else {
            // Direkt in Queue
            $this->pushImmediate($job, $serializedJob);
        }

        // Tag speichern, falls vorhanden
        $this->saveTag($job);

        return $jobId;
    }

    /**
     * Fügt mehrere Jobs gleichzeitig zur Queue hinzu
     *
     * @param array<Job> $jobs Liste von Jobs
     * @param int|null $delay Verzögerung in Sekunden
     * @return array<string> Liste der Job-IDs
     * @throws JsonException|ReflectionException Wenn Serialisierung fehlschlägt
     */
    public function pushBatch(array $jobs, ?int $delay = null): array
    {
        $jobIds = [];

        // Verwendung eines Multi/Exec-Blocks für atomare Operation
        $this->redis->multi();

        try {
            foreach ($jobs as $job) {
                $jobIds[] = $this->push($job, $delay);
            }

            $this->redis->exec();
        } catch (Exception $e) {
            $this->redis->discard();
            throw $e;
        }

        return $jobIds;
    }

    /**
     * Holt und entfernt den nächsten Job aus der Queue
     *
     * @param string $queue Spezifische Queue-Instanz
     * @return Job|null Der nächste Job oder null
     * @throws JsonException Wenn Deserialisierung fehlschlägt
     */
    public function pop(string $queue = 'default'): ?Job
    {
        // Verzögerte Jobs zuerst überprüfen
        $this->processDelayedJobs($queue);

        // Job aus der Queue holen mit Atomic LPOP
        $serializedJob = $this->redis->lpop($this->prefix . self::QUEUE_PREFIX . $queue);

        if ($serializedJob === null) {
            return null;
        }

        try {
            return $this->deserializeJob($serializedJob);
        } catch (Exception $e) {
            // Job-Verarbeitung fehlgeschlagen
            app_log("Queue-Job Deserialisierungsfehler: " . $e->getMessage(), [
                'job' => $serializedJob,
                'queue' => $queue,
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString()
            ], 'error');

            return null;
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
        return (bool)$this->redis->exists($this->prefix . self::JOB_PREFIX . $jobId);
    }

    /**
     * Löscht einen spezifischen Job aus der Queue
     *
     * @param string $jobId Job-Identifier
     * @return bool True bei Erfolg, sonst false
     * @throws JsonException Wenn Deserialisierung fehlschlägt
     */
    public function delete(string $jobId): bool
    {
        $jobKey = $this->prefix . self::JOB_PREFIX . $jobId;

        // Job-Daten abrufen, um Tag zu entfernen, falls vorhanden
        $serializedJob = $this->redis->get($jobKey);
        if ($serializedJob !== null) {
            try {
                $job = $this->deserializeJob($serializedJob);
                if ($job->getTag() !== null) {
                    $this->redis->srem(
                        $this->prefix . self::TAGS_PREFIX . $job->getTag(),
                        $jobId
                    );
                }
            } catch (Exception $e) {
                // Fehler beim Deserialisieren ignorieren
            }
        }

        return (bool)$this->redis->del($jobKey);
    }

    /**
     * Anzahl der Jobs in einer Queue
     *
     * @param string $queue Spezifische Queue-Instanz
     * @return int Anzahl der Jobs
     */
    public function count(string $queue = 'default'): int
    {
        return (int)$this->redis->llen($this->prefix . self::QUEUE_PREFIX . $queue);
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

    /**
     * Gibt alle Jobs einer Queue zurück, ohne sie zu entfernen
     *
     * @param string $queue Spezifische Queue-Instanz
     * @param int $limit Maximale Anzahl zurückzugebender Jobs
     * @param int $offset Startposition in der Queue
     * @return array<Job> Liste der Jobs
     * @throws JsonException Wenn Deserialisierung fehlschlägt
     */
    public function list(string $queue = 'default', int $limit = 100, int $offset = 0): array
    {
        $jobs = [];
        $queueKey = $this->prefix . self::QUEUE_PREFIX . $queue;

        // Jobs aus der Queue abrufen ohne sie zu entfernen (LRANGE)
        $serializedJobs = $this->redis->lrange($queueKey, $offset, $offset + $limit - 1);

        foreach ($serializedJobs as $serializedJob) {
            try {
                $jobs[] = $this->deserializeJob($serializedJob);
            } catch (Exception $e) {
                // Fehler bei der Deserialisierung eines einzelnen Jobs ignorieren
                app_log("Fehler beim Deserialisieren eines Jobs in list(): " . $e->getMessage(), [
                    'queue' => $queue
                ], 'warning');
            }
        }

        return $jobs;
    }

    /**
     * Findet Jobs anhand eines Tags
     *
     * @param string $tag Der zu suchende Tag
     * @param string $queue Spezifische Queue-Instanz
     * @return array<Job> Liste der gefundenen Jobs
     * @throws JsonException Wenn Deserialisierung fehlschlägt
     */
    public function findByTag(string $tag, string $queue = 'default'): array
    {
        $jobs = [];
        $tagKey = $this->prefix . self::TAGS_PREFIX . $tag;

        // Job-IDs für den Tag abrufen
        $jobIds = $this->redis->smembers($tagKey);

        foreach ($jobIds as $jobId) {
            $jobKey = $this->prefix . self::JOB_PREFIX . $jobId;
            $serializedJob = $this->redis->get($jobKey);

            if ($serializedJob !== null) {
                try {
                    $job = $this->deserializeJob($serializedJob);
                    // Nur Jobs aus der angegebenen Queue zurückgeben
                    if ($job->getQueue() === $queue) {
                        $jobs[] = $job;
                    }
                } catch (Exception $e) {
                    // Fehler beim Deserialisieren ignorieren
                }
            }
        }

        return $jobs;
    }

    /**
     * Verarbeitet verzögerte Jobs
     *
     * @param string $queue Queue-Name
     * @throws JsonException Wenn Deserialisierung fehlschlägt
     */
    private function processDelayedJobs(string $queue): void
    {
        $now = time();
        $delayedKey = $this->prefix . self::DELAYED_PREFIX . $queue;
        $queueKey = $this->prefix . self::QUEUE_PREFIX . $queue;

        // Jobs finden, die bereit zur Verarbeitung sind
        $readyJobs = $this->redis->zrangebyscore($delayedKey, 0, $now);

        if (empty($readyJobs)) {
            return;
        }

        // Multi-Exec-Block für atomare Operation
        $this->redis->multi();

        try {
            foreach ($readyJobs as $jobData) {
                // Job aus verzögerter Queue entfernen
                $this->redis->zrem($delayedKey, $jobData);

                // Job zur aktiven Queue hinzufügen
                $this->redis->rpush($queueKey, $jobData);
            }

            $this->redis->exec();
        } catch (Exception $e) {
            $this->redis->discard();
            throw new RuntimeException(
                "Fehler beim Verarbeiten verzögerter Jobs: " . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Serialisiert einen Job für die Speicherung in Redis
     *
     * @param Job $job Zu serialisierender Job
     * @return string Serialisierter Job
     * @throws JsonException|ReflectionException Wenn Serialisierung fehlschlägt
     */
    private function serializeJob(Job $job): string
    {
        return json_encode($job->serialize(), JSON_THROW_ON_ERROR);
    }

    /**
     * Deserialisiert einen Job aus einem JSON-String
     *
     * @param string $serializedJob Serialisierter Job
     * @return Job Deserialisierter Job
     * @throws JsonException|ReflectionException Wenn Deserialisierung fehlschlägt
     */
    private function deserializeJob(string $serializedJob): Job
    {
        $jobData = json_decode($serializedJob, true, 512, JSON_THROW_ON_ERROR);
        return Job::unserialize($jobData);
    }

    /**
     * Fügt einen verzögerten Job zur Queue hinzu
     *
     * @param Job $job Job
     * @param string $serializedJob Serialisierter Job
     * @param int $delay Verzögerung in Sekunden
     * @return void
     */
    private function pushDelayed(Job $job, string $serializedJob, int $delay): void
    {
        $this->redis->zadd(
            $this->prefix . self::DELAYED_PREFIX . $job->getQueue(),
            [
                $serializedJob => time() + $delay
            ]
        );

        // Job separat speichern für Tracking und Retry-Mechanismus
        $this->redis->set(
            $this->prefix . self::JOB_PREFIX . $job->getId(),
            $serializedJob
        );
    }

    /**
     * Fügt einen Job sofort zur Queue hinzu
     *
     * @param Job $job Job
     * @param string $serializedJob Serialisierter Job
     * @return void
     */
    private function pushImmediate(Job $job, string $serializedJob): void
    {
        $this->redis->rpush(
            $this->prefix . self::QUEUE_PREFIX . $job->getQueue(),
            $serializedJob
        );

        // Job separat speichern für Tracking und Retry-Mechanismus
        $this->redis->set(
            $this->prefix . self::JOB_PREFIX . $job->getId(),
            $serializedJob
        );
    }

    /**
     * Speichert den Tag eines Jobs, falls vorhanden
     *
     * @param Job $job Job
     * @return void
     */
    private function saveTag(Job $job): void
    {
        if ($job->getTag() !== null) {
            $this->redis->sadd(
                $this->prefix . self::TAGS_PREFIX . $job->getTag(),
                $job->getId()
            );
        }
    }
}