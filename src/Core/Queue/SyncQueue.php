<?php

declare(strict_types=1);

namespace App\Core\Queue;

use App\Core\Container\Container;
use Random\RandomException;

/**
 * Synchrone Queue-Implementierung für Tests und Entwicklung
 *
 * Führt Jobs sofort statt asynchron aus, nützlich für Tests und
 * Entwicklungsumgebungen.
 */
class SyncQueue implements Queue
{
    /**
     * Container für Dependency Injection
     */
    private readonly Container $container;

    /**
     * Flag für Fehlerbehandlung
     */
    private readonly bool $failOnError;

    /**
     * Konstruktor
     *
     * @param Container $container Container für Dependency Injection
     * @param bool $failOnError Exceptions weiterleiten bei Fehlern
     */
    public function __construct(
        Container $container,
        bool      $failOnError = true
    )
    {
        $this->container = $container;
        $this->failOnError = $failOnError;
    }

    /**
     * Fügt mehrere Jobs gleichzeitig zur Queue hinzu und führt sie sofort aus
     *
     * @param array<Job> $jobs Liste von Jobs
     * @param int|null $delay Verzögerung wird ignoriert
     * @return array<string> Liste der Job-IDs
     */
    public function pushBatch(array $jobs, ?int $delay = null): array
    {
        $jobIds = [];

        foreach ($jobs as $job) {
            $jobIds[] = $this->push($job);
        }

        return $jobIds;
    }

    /**
     * Fügt einen Job zur Queue hinzu und führt ihn sofort aus
     *
     * @param Job $job Job, der zur Queue hinzugefügt wird
     * @param int|null $delay Verzögerung wird bei synchroner Ausführung simuliert
     * @return string Job-Identifier
     * @throws RandomException
     * @throws \Throwable
     */
    public function push(Job $job, ?int $delay = null): string
    {
        // Eindeutige Job-ID generieren
        $jobId = $job->getId() ?? bin2hex(random_bytes(16));
        $job->setId($jobId);

        // Verzögerung simulieren, wenn im Entwicklungsmodus
        if ($delay !== null && $delay > 0 && config('app.debug', false)) {
            app_log("SyncQueue: Verzögerung von {$delay}s für Job simuliert", [
                'job_id' => $jobId,
                'job_class' => get_class($job),
                'queue' => $job->getQueue(),
                'delay' => $delay
            ], 'debug');

            // Im Debug-Modus können wir einen kleinen Delay simulieren (max 3 Sekunden)
            if (config('queue.drivers.sync.simulate_delay', false)) {
                sleep(min($delay, 3));
            }
        }

        // Job direkt ausführen
        try {
            // Lebenszyklus-Hooks ausführen
            $job->beforeHandle();
            $job->incrementAttempts(); // Inkrementieren der Versuche wie bei echten Queues

            // Startzeit für Performance-Messung
            $startTime = microtime(true);

            // Job ausführen
            $result = $job->handle();

            // Ausführungszeit berechnen
            $executionTime = microtime(true) - $startTime;

            // After-Hook ausführen
            $job->afterHandle($result);

            app_log("Sync-Job erfolgreich ausgeführt", [
                'job_id' => $jobId,
                'job_class' => get_class($job),
                'queue' => $job->getQueue(),
                'execution_time' => round($executionTime * 1000, 2) . 'ms', // in Millisekunden
                'result_type' => gettype($result)
            ], 'info');

            // Erfolgreiche Ausführung kann durch ein Event signalisiert werden
            if (method_exists($this->container, 'dispatch') && class_exists('\App\Core\Events\JobProcessed')) {
                $this->container->dispatch(new \App\Core\Events\JobProcessed($job, $result));
            }
        } catch (\Throwable $e) {
            app_log("Sync-Job fehlgeschlagen", [
                'job_id' => $jobId,
                'job_class' => get_class($job),
                'queue' => $job->getQueue(),
                'error' => $e->getMessage(),
                'trace' => array_slice(explode("\n", $e->getTraceAsString()), 0, 10)
            ], 'error');

            // Failure-Hook ausführen
            $job->onFailure($e);

            // Fehler-Event auslösen
            if (method_exists($this->container, 'dispatch') && class_exists('\App\Core\Events\JobFailed')) {
                $this->container->dispatch(new \App\Core\Events\JobFailed($job, $e));
            }

            if ($this->failOnError) {
                throw $e;
            }
        }

        return $jobId;
    }

    /**
     * Holt den nächsten Job aus der Queue (immer null bei SyncQueue)
     *
     * @param string $queue Spezifische Queue-Instanz
     * @return Job|null Immer null, da Jobs direkt ausgeführt werden
     */
    public function pop(string $queue = 'default'): ?Job
    {
        return null;
    }

    /**
     * Überprüft, ob ein Job existiert (immer false bei SyncQueue)
     *
     * @param string $jobId Job-Identifier
     * @return bool Immer false
     */
    public function exists(string $jobId): bool
    {
        return false;
    }

    /**
     * Löscht einen Job (immer true bei SyncQueue)
     *
     * @param string $jobId Job-Identifier
     * @return bool Immer true
     */
    public function delete(string $jobId): bool
    {
        return true;
    }

    /**
     * Anzahl der Jobs in der Queue (immer 0 bei SyncQueue)
     *
     * @param string $queue Spezifische Queue-Instanz
     * @return int Immer 0
     */
    public function count(string $queue = 'default'): int
    {
        return 0;
    }

    /**
     * Leert die Queue (immer true bei SyncQueue)
     *
     * @param string $queue Spezifische Queue-Instanz
     * @return bool Immer true
     */
    public function clear(string $queue = 'default'): bool
    {
        return true;
    }

    /**
     * Gibt Jobs zurück (immer leere Liste bei SyncQueue)
     *
     * @param string $queue Spezifische Queue-Instanz
     * @param int $limit Maximale Anzahl zurückzugebender Jobs
     * @param int $offset Startposition in der Queue
     * @return array<Job> Immer leere Liste
     */
    public function list(string $queue = 'default', int $limit = 100, int $offset = 0): array
    {
        return [];
    }

    /**
     * Findet Jobs anhand eines Tags (immer leere Liste bei SyncQueue)
     *
     * @param string $tag Der zu suchende Tag
     * @param string $queue Spezifische Queue-Instanz
     * @return array<Job> Immer leere Liste
     */
    public function findByTag(string $tag, string $queue = 'default'): array
    {
        return [];
    }
}