<?php

declare(strict_types=1);

namespace App\Core\Queue;

use App\Core\Container\Container;
use Exception;
use Throwable;
use RuntimeException;

/**
 * Queue-Worker für die Verarbeitung von Jobs
 *
 * Verarbeitet Jobs aus der Queue in einem Loop, mit Unterstützung für
 * Daemon-Modus, automatischen Retries und Fehlerbehandlung.
 */
class QueueWorker
{
    /**
     * Queue-Instanz
     */
    private readonly Queue $queue;

    /**
     * Container für Dependency Injection
     */
    private readonly Container $container;

    /**
     * Worker-Konfiguration
     *
     * @var array<string, mixed>
     */
    private readonly array $config;

    /**
     * Flag für Worker-Beendigung
     */
    private bool $shouldStop = false;

    /**
     * Konstruktor
     *
     * @param Queue $queue Queue-Instanz
     * @param Container $container Container für Dependency Injection
     * @param array<string, mixed> $config Worker-Konfiguration
     */
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

        // Signal-Handler für graceful shutdown im Daemon-Modus
        if (extension_loaded('pcntl')) {
            pcntl_signal(SIGTERM, [$this, 'shutdown']);
            pcntl_signal(SIGINT, [$this, 'shutdown']);
        }
    }

    /**
     * Startet den Queue-Worker
     *
     * @param string $queueName Queue-Name
     * @param bool $daemon Dauerhafter Betrieb
     * @param int $maxJobs Maximale Anzahl zu verarbeitender Jobs (0 = unbegrenzt)
     * @return void
     */
    public function work(string $queueName = 'default', bool $daemon = false, int $maxJobs = 0): void
    {
        $processedJobs = 0;

        do {
            // Signal-Verarbeitung, wenn pcntl verfügbar
            if (extension_loaded('pcntl')) {
                pcntl_signal_dispatch();
            }

            // Worker-Beendigung überprüfen
            if ($this->shouldStop) {
                break;
            }

            try {
                $processed = $this->processNextJob($queueName);

                if ($processed) {
                    $processedJobs++;

                    // Job-Begrenzung
                    if ($maxJobs > 0 && $processedJobs >= $maxJobs) {
                        break;
                    }
                } else {
                    // Kein Job vorhanden, Pause für CPU-Entlastung
                    usleep(($this->config['sleep_ms'] ?? 500) * 1000);
                }
            } catch (Throwable $e) {
                $this->handleWorkerException($e);

                // Pause nach Fehler
                sleep(1);
            }
        } while ($daemon && !$this->shouldStop);

        // Worker beenden
        app_log('Queue-Worker beendet', [
            'processed_jobs' => $processedJobs,
            'queue' => $queueName,
            'daemon' => $daemon,
            'stopped' => $this
                ->shouldStop
        ], 'info');
    }

    /**
     * Verarbeitet den nächsten Job in der Queue
     *
     * @param string $queueName Queue-Name
     * @return bool True, wenn ein Job verarbeitet wurde, false sonst
     * @throws Exception Bei schwerwiegenden Fehlern
     */
    private function processNextJob(string $queueName): bool
    {
        // Job aus der Queue holen
        $job = $this->queue->pop($queueName);

        if ($job === null) {
            return false;
        }

        try {
            // Lebenszyklus-Hooks ausführen
            $job->beforeHandle();

            // Job-Ausführung mit Timeout
            $result = $this->executeJobWithTimeout($job);

            // After-Hook ausführen
            $job->afterHandle($result);

            // Erfolgreiche Ausführung loggen
            $this->logJobSuccess($job, $result);

            return true;
        } catch (Throwable $e) {
            $this->handleJobFailure($job, $e);
            return true;
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
            throw new RuntimeException("Job-Timeout nach {$duration} Sekunden");
        }

        return $result;
    }

    /**
     * Behandelt einen erfolgreichen Job
     *
     * @param Job $job Erfolgreich ausgeführter Job
     * @param mixed $result Rückgabewert des Jobs
     * @return void
     */
    private function logJobSuccess(Job $job, mixed $result): void
    {
        app_log("Job erfolgreich ausgeführt", [
            'job_id' => $job->getId(),
            'job_class' => get_class($job),
            'queue' => $job->getQueue(),
            'attempts' => $job->getAttempts(),
            'tag' => $job->getTag(),
            'result' => is_scalar($result) ? $result : gettype($result)
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
     * @return void
     */
    private function handleJobFailure(Job $job, Throwable $exception): void
    {
        // Failure-Hook ausführen
        try {
            $job->onFailure($exception);
        } catch (Throwable $hookException) {
            // Fehler im Failure-Hook ignorieren
            app_log("Fehler im onFailure-Hook", [
                'job_id' => $job->getId(),
                'error' => $hookException->getMessage()
            ], 'error');
        }

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
     * @return void
     */
    private function handleFatalJobFailure(Job $job, Throwable $exception): void
    {
        app_log("Job-Fehler (Nicht wiederholbar)", [
            'job_id' => $job->getId(),
            'job_class' => get_class($job),
            'queue' => $job->getQueue(),
            'attempts' => $job->getAttempts(),
            'error' => $exception->getMessage(),
            'exception_class' => get_class($exception),
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
     * @return void
     */
    private function requeueFailedJob(Job $job, Throwable $exception): void
    {
        // Exponentielles Backoff für Wiederholungen
        $delay = $this->calculateBackoffDelay($job->getAttempts());

        app_log("Job-Wiederholung", [
            'job_id' => $job->getId(),
            'job_class' => get_class($job),
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
        // Exponentielles Backoff mit Jitter für bessere Verteilung
        $base = 2 ** min($attempts, 6); // Max 64s Basis-Delay
        $max = min($base * 10, 600);    // Max 10 Minuten Delay

        // Zufällige Komponente für bessere Verteilung (Jitter)
        return (int)(($base + mt_rand(0, $base)) / 2);
    }

    /**
     * Behandelt Worker-Exceptions
     *
     * @param Throwable $exception Aufgetretene Exception
     * @return void
     */
    private function handleWorkerException(Throwable $exception): void
    {
        app_log("Queue-Worker-Fehler", [
            'error' => $exception->getMessage(),
            'exception_class' => get_class($exception),
            'trace' => $exception->getTraceAsString()
        ], 'error');
    }

    /**
     * Stoppt den Worker gracefully
     *
     * @param int $signal Signal (SIGTERM/SIGINT)
     * @return void
     */
    public function shutdown(int $signal = 0): void
    {
        $this->shouldStop = true;
        app_log("Queue-Worker wird beendet", [
            'signal' => $signal
        ], 'info');
    }
}