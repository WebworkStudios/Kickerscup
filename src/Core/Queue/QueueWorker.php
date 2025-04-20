<?php


declare(strict_types=1);

namespace App\Core\Queue;

use App\Core\Container\Container;
use Exception;
use Throwable;

/**
 * Queue-Worker für die Verarbeitung von Jobs
 */
class QueueWorker
{
    private Queue $queue;
    private Container $container;
    private array $config;

    public function __construct(
        Queue     $queue,
        Container $container,
        array     $config = []
    )
    {
        $this->queue = $queue;
        $this->container = $container;
        $this->config = array_merge(
            config('queue.worker', []),
            $config
        );
    }

    /**
     * Startet den Queue-Worker
     *
     * @param string $queueName Queue-Name
     * @param bool $daemon Dauerhafter Betrieb
     */
    public function work(string $queueName = 'default', bool $daemon = false): void
    {
        do {
            try {
                $this->processNextJob($queueName);
            } catch (Throwable $e) {
                $this->handleWorkerException($e);
            }

            // Im Daemon-Modus Pause zwischen Durchläufen
            if ($daemon) {
                sleep($this->config['sleep'] ?? 3);
            }
        } while ($daemon);
    }

    /**
     * Verarbeitet den nächsten Job in der Queue
     *
     * @param string $queueName Queue-Name
     * @throws Exception
     */
    private function processNextJob(string $queueName): void
    {
        // Job aus der Queue holen
        $job = $this->queue->pop($queueName);

        if ($job === null) {
            return;
        }

        try {
            // Job-Ausführung mit Timeout
            $result = $this->executeJobWithTimeout($job);

            // Erfolgreiche Ausführung loggen
            $this->logJobSuccess($job, $result);
        } catch (Throwable $e) {
            $this->handleJobFailure($job, $e);
        }
    }

    /**
     * Führt einen Job mit Timeout aus
     *
     * @param Job $job Auszuführender Job
     * @return mixed Rückgabewert des Jobs
     * @throws Exception Bei Timeouts oder Ausführungsfehlern
     */
    private function executeJobWithTimeout(Job $job): mixed
    {
        $job->incrementAttempts();
        $timeout = $this->config['timeout'] ?? 60;

        // Setze Timeout-Mechanismus
        $start = microtime(true);
        $result = $job->handle();
        $duration = microtime(true) - $start;

        if ($duration > $timeout) {
            throw new Exception("Job-Timeout nach {$duration} Sekunden");
        }

        return $result;
    }

    /**
     * Behandelt einen erfolgreichen Job
     *
     * @param Job $job Erfolgreich ausgeführter Job
     * @param mixed $result Rückgabewert des Jobs
     */
    private function logJobSuccess(Job $job, mixed $result): void
    {
        app_log("Job erfolgreich ausgeführt", [
            'job_id' => $job->getId(),
            'queue' => $job->getQueue(),
            'attempts' => $job->getAttempts(),
            'result' => $result
        ], 'info');

        // Job löschen
        $this->queue->delete($job->getId());
    }

    /**
     * Behandelt Fehler bei der Job-Ausführung
     *
     * @param Job $job Fehlgeschlagener Job
     * @param Throwable $exception Aufgetretene Exception
     * @throws Exception Bei nicht wiederholbaren Fehlern
     */
    private function handleJobFailure(Job $job, Throwable $exception): void
    {
        // Job kann nicht mehr wiederholt werden
        if (!$job->canRetry() || !$job->shouldRetry($exception)) {
            $this->handleFatalJobFailure($job, $exception);
            return;
        }

        // Job erneut zur Queue hinzufügen
        $this->requeueFailedJob($job, $exception);
    }

    /**
     * Behandelt fatale Job-Fehler
     *
     * @param Job $job Fehlgeschlagener Job
     * @param Throwable $exception Aufgetretene Exception
     */
    private function handleFatalJobFailure(Job $job, Throwable $exception): void
    {
        app_log("Job-Fehler (Nicht wiederholbar)", [
            'job_id' => $job->getId(),
            'queue' => $job->getQueue(),
            'attempts' => $job->getAttempts(),
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ], 'error');

        // Job löschen
        $this->queue->delete($job->getId());
    }

    /**
     * Fügt einen fehlgeschlagenen Job erneut zur Queue hinzu
     *
     * @param Job $job Fehlgeschlagener Job
     * @param Throwable $exception Aufgetretene Exception
     */
    private function requeueFailedJob(Job $job, Throwable $exception): void
    {
        // Exponentielles Backoff für Wiederholungen
        $delay = $this->calculateBackoffDelay($job->getAttempts());

        app_log("Job-Wiederholung", [
            'job_id' => $job->getId(),
            'queue' => $job->getQueue(),
            'attempts' => $job->getAttempts(),
            'delay' => $delay,
            'error' => $exception->getMessage()
        ], 'warning');

        // Job mit Verzögerung erneut zur Queue hinzufügen
        $this->queue->push($job, $delay);
    }

    /**
     * Berechnet Verzögerung für Wiederholungen mit exponentiellem Backoff
     *
     * @param int $attempts Anzahl der Ausführungsversuche
     * @return int Verzögerung in Sekunden
     */
    private function calculateBackoffDelay(int $attempts): int
    {
        // Exponentielles Backoff: 1s, 4s, 16s
        return (int)pow(2, $attempts * 2);
    }
}