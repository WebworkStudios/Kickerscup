<?php

declare(strict_types=1);

namespace App\Core\Queue;

use App\Core\Database\DatabaseManager;
use App\Core\Database\QueryBuilder;
use Exception;
use JsonException;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Datenbank-basierte Queue-Implementierung
 *
 * Verwendet eine Datenbanktabelle zur Speicherung von Jobs mit Unterstützung
 * für verzögerte Jobs, Job-Wiederholungen und persistente Job-Daten.
 * Nutzt den Framework-QueryBuilder für konsistente Datenbankinteraktionen.
 */
class DatabaseQueue implements Queue
{
    /**
     * Datenbank-Manager
     */
    private readonly DatabaseManager $db;

    /**
     * Tabellennamen
     */
    private readonly string $table;

    /**
     * Retry-Timeout
     */
    private readonly int $retryAfter;

    /**
     * Datenbankverbindung für diese Queue
     */
    private ?string $connection = null;

    /**
     * Konstruktor
     *
     * @param DatabaseManager $db Datenbank-Manager
     * @param string $table Tabellenname
     * @param int $retryAfter Retry-Timeout in Sekunden
     * @param string|null $connection Spezifische Datenbankverbindung oder null für Standard
     * @throws RuntimeException Bei Migrations-Fehler
     */
    public function __construct(
        DatabaseManager $db,
        string $table = 'jobs',
        int $retryAfter = 90,
        ?string $connection = null
    )
    {
        $this->db = $db;
        $this->table = $table;
        $this->retryAfter = $retryAfter;
        $this->connection = $connection;

        // Automatische Migration, wenn nötig
        if (config('queue.drivers.database.migration', true)) {
            $this->ensureTableExists();
        }
    }

    /**
     * Gibt den QueryBuilder für die Jobs-Tabelle zurück
     *
     * @return QueryBuilder
     */
    private function table(): QueryBuilder
    {
        return $this->db->table($this->table, $this->connection);
    }

    /**
     * Fügt einen Job zur Queue hinzu
     *
     * @param Job $job Job, der zur Queue hinzugefügt wird
     * @param int|null $delay Verzögerung in Sekunden
     * @return string Job-Identifier
     * @throws JsonException|Exception Wenn Serialisierung fehlschlägt
     */
    public function push(Job $job, ?int $delay = null): string
    {
        // Eindeutige Job-ID generieren falls noch nicht vorhanden
        $jobId = $job->getId() ?? bin2hex(random_bytes(16));
        $job->setId($jobId);

        // Berechnete Werte
        $availableAt = $delay ? time() + $delay : time();
        $payload = json_encode($job->serialize(), JSON_THROW_ON_ERROR);

        try {
            return $this->db->transaction(function () use ($jobId, $job, $payload, $availableAt) {
                // Job einfügen mit QueryBuilder
                $this->table()->insert([
                    'id' => $jobId,
                    'queue' => $job->getQueue(),
                    'payload' => $payload,
                    'attempts' => 0,
                    'available_at' => date('Y-m-d H:i:s', $availableAt),
                    'created_at' => date('Y-m-d H:i:s'),
                    'reserved_at' => null
                ]);

                return $jobId;
            }, $this->connection);
        } catch (Throwable $e) {
            throw new RuntimeException("Fehler beim Speichern des Jobs: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Fügt mehrere Jobs gleichzeitig zur Queue hinzu
     *
     * @param array<Job> $jobs Liste von Jobs
     * @param int|null $delay Verzögerung in Sekunden
     * @return array<string> Liste der Job-IDs
     * @throws JsonException|Exception Wenn Serialisierung fehlschlägt
     */
    public function pushBatch(array $jobs, ?int $delay = null): array
    {
        $jobIds = [];
        $availableAt = $delay ? time() + $delay : time();
        $jobsData = [];

        try {
            return $this->db->transaction(function () use ($jobs, $availableAt, &$jobIds) {
                $batchData = [];

                foreach ($jobs as $job) {
                    $jobId = $job->getId() ?? bin2hex(random_bytes(16));
                    $job->setId($jobId);
                    $jobIds[] = $jobId;

                    $batchData[] = [
                        'id' => $jobId,
                        'queue' => $job->getQueue(),
                        'payload' => json_encode($job->serialize(), JSON_THROW_ON_ERROR),
                        'attempts' => 0,
                        'available_at' => date('Y-m-d H:i:s', $availableAt),
                        'created_at' => date('Y-m-d H:i:s'),
                        'reserved_at' => null
                    ];
                }

                // Batch-Insert mit QueryBuilder
                $this->table()->insertMany($batchData);

                return $jobIds;
            }, $this->connection);
        } catch (Throwable $e) {
            throw new RuntimeException("Fehler beim Batch-Speichern der Jobs: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Holt und reserviert den nächsten Job aus der Queue
     *
     * @param string $queue Spezifische Queue-Instanz
     * @return Job|null Der nächste Job oder null
     * @throws JsonException|Exception Wenn Deserialisierung fehlschlägt
     */
    public function pop(string $queue = 'default'): ?Job
    {
        try {
            return $this->db->transaction(function () use ($queue) {
                $now = date('Y-m-d H:i:s');
                $retryAfterTime = date('Y-m-d H:i:s', time() - $this->retryAfter);

                // Job finden mit QueryBuilder und FOR UPDATE
                $jobData = $this->table()
                    ->where('queue', '=', $queue)
                    ->where(function ($query) use ($retryAfterTime) {
                        $query->whereNull('reserved_at')
                            ->orWhere('reserved_at', '<=', $retryAfterTime);
                    })
                    ->where('available_at', '<=', $now)
                    ->orderBy('id')
                    ->limit(1)
                    ->forUpdate()
                    ->first();

                if ($jobData === false) {
                    return null;
                }

                // Job reservieren mit QueryBuilder
                $this->table()
                    ->where('id', '=', $jobData['id'])
                    ->update([
                        'reserved_at' => $now,
                        'attempts' => $jobData['attempts'] + 1
                    ]);

                // Job deserialisieren
                try {
                    $jobPayload = json_decode($jobData['payload'], true, 512, JSON_THROW_ON_ERROR);
                    $job = Job::unserialize($jobPayload);

                    // Sicherstellen, dass die Job-Versuche korrekt gesetzt sind
                    if ($job->getAttempts() !== $jobData['attempts']) {
                        // Setzen der Versuche auf die in der Datenbank gespeicherte Anzahl
                        for ($i = 0; $i < $jobData['attempts']; $i++) {
                            $job->incrementAttempts();
                        }
                    }

                    return $job;
                } catch (Exception $e) {
                    // Job-Fehler protokollieren und löschen
                    app_log("Fehler beim Deserialisieren des Jobs", [
                        'job_id' => $jobData['id'],
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                        'payload' => $jobData['payload'] ?? 'nicht verfügbar'
                    ], 'error');

                    $this->delete($jobData['id']);
                    return null;
                }
            }, $this->connection);
        } catch (Throwable $e) {
            throw new RuntimeException("Fehler beim Abrufen des Jobs: " . $e->getMessage(), 0, $e);
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
        return $this->table()
                ->where('id', '=', $jobId)
                ->count() > 0;
    }

    /**
     * Löscht einen spezifischen Job aus der Queue
     *
     * @param string $jobId Job-Identifier
     * @return bool True bei Erfolg, sonst false
     */
    public function delete(string $jobId): bool
    {
        return $this->table()
                ->where('id', '=', $jobId)
                ->delete() > 0;
    }

    /**
     * Anzahl der Jobs in einer Queue
     *
     * @param string $queue Spezifische Queue-Instanz
     * @return int Anzahl der Jobs
     */
    public function count(string $queue = 'default'): int
    {
        return $this->table()
            ->where('queue', '=', $queue)
            ->count();
    }

    /**
     * Leert eine komplette Queue
     *
     * @param string $queue Spezifische Queue-Instanz
     * @return bool True bei Erfolg, sonst false
     */
    public function clear(string $queue = 'default'): bool
    {
        $this->table()
            ->where('queue', '=', $queue)
            ->delete();

        return true;
    }

    /**
     * Gibt alle Jobs einer Queue zurück, ohne sie zu entfernen
     *
     * @param string $queue Spezifische Queue-Instanz
     * @param int $limit Maximale Anzahl zurückzugebender Jobs
     * @param int $offset Startposition in der Queue
     * @return array<Job> Liste der Jobs
     * @throws JsonException|Exception Wenn Deserialisierung fehlschlägt
     */
    public function list(string $queue = 'default', int $limit = 100, int $offset = 0): array
    {
        $jobs = [];

        $jobsData = $this->table()
            ->where('queue', '=', $queue)
            ->orderBy('available_at')
            ->limit($limit)
            ->offset($offset)
            ->get();

        foreach ($jobsData as $jobData) {
            try {
                $jobPayload = json_decode($jobData['payload'], true, 512, JSON_THROW_ON_ERROR);
                $job = Job::unserialize($jobPayload);
                $jobs[] = $job;
            } catch (Exception $e) {
                // Fehler beim Deserialisieren ignorieren
                app_log("Fehler beim Deserialisieren eines Jobs in list()", [
                    'job_id' => $jobData['id'],
                    'error' => $e->getMessage()
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
     * @throws JsonException|Exception Wenn Deserialisierung fehlschlägt
     */
    public function findByTag(string $tag, string $queue = 'default'): array
    {
        $jobs = [];

        // Verwendung des QueryBuilders mit LIKE-Bedingung
        $jobsData = $this->table()
            ->where('queue', '=', $queue)
            ->whereLike('payload', '%"tag":"' . $tag . '"%')
            ->orderBy('available_at')
            ->get();

        foreach ($jobsData as $jobData) {
            try {
                $jobPayload = json_decode($jobData['payload'], true, 512, JSON_THROW_ON_ERROR);
                $job = Job::unserialize($jobPayload);

                if ($job->getTag() === $tag) {
                    $jobs[] = $job;
                }
            } catch (Exception $e) {
            }
        }

        return $jobs;
    }
    /**
     * Stellt sicher, dass die Jobs-Tabelle existiert
     *
     * @return void
     * @throws RuntimeException Bei Migrations-Fehler
     */
    private function ensureTableExists(): void
    {
        try {
            // Prüfen, ob Tabelle existiert mit QueryBuilder
            $this->table()->first();
            return;
        } catch (Exception $e) {
            // Tabelle existiert nicht, erstellen
            $this->createJobsTable();
        }
    }

    /**
     * Erstellt die Jobs-Tabelle
     *
     * @return void
     * @throws RuntimeException Bei Migrations-Fehler
     */
    private function createJobsTable(): void
    {
        try {
            $sql = "CREATE TABLE IF NOT EXISTS {$this->table} (
                id VARCHAR(50) NOT NULL PRIMARY KEY,
                queue VARCHAR(50) NOT NULL,
                payload LONGTEXT NOT NULL,
                attempts INTEGER UNSIGNED NOT NULL,
                available_at DATETIME NOT NULL,
                created_at DATETIME NOT NULL,
                reserved_at DATETIME NULL,
                INDEX queue_index (queue),
                INDEX available_at_index (available_at),
                INDEX reserved_at_index (reserved_at)
            )";

            $this->db->connection($this->connection)->query($sql);

            app_log("Jobs-Tabelle wurde erstellt", [
                'table' => $this->table
            ], 'info');
        } catch (Exception $e) {
            throw new RuntimeException("Fehler beim Erstellen der Jobs-Tabelle: " . $e->getMessage(), 0, $e);
        }
    }
}