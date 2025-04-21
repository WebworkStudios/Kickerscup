<?php

declare(strict_types=1);

namespace App\Core\Queue;

use App\Core\Database\DatabaseManager;
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
     * Konstruktor
     *
     * @param DatabaseManager $db Datenbank-Manager
     * @param string $table Tabellenname
     * @param int $retryAfter Retry-Timeout in Sekunden
     * @throws RuntimeException Bei Migrations-Fehler
     */
    public function __construct(
        DatabaseManager $db,
        string $table = 'jobs',
        int $retryAfter = 90
    )
    {
        $this->db = $db;
        $this->table = $table;
        $this->retryAfter = $retryAfter;

        // Automatische Migration, wenn nötig
        if (config('queue.drivers.database.migration', true)) {
            $this->ensureTableExists();
        }
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

        $conn = $this->db->connection();
        $conn->beginTransaction();

        try {
            $sql = "INSERT INTO {$this->table} 
                    (id, queue, payload, attempts, available_at, created_at, reserved_at) 
                    VALUES (?, ?, ?, ?, ?, ?, NULL)";

            $stmt = $conn->prepare($sql);
            $stmt->execute([
                $jobId,
                $job->getQueue(),
                $payload,
                0, // Anfängliche Anzahl an Versuchen
                date('Y-m-d H:i:s', $availableAt),
                date('Y-m-d H:i:s')
            ]);

            $conn->commit();
            return $jobId;
        } catch (Throwable $e) {
            $conn->rollBack();
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

        $conn = $this->db->connection();
        $conn->beginTransaction();

        try {
            $sql = "INSERT INTO {$this->table} 
                    (id, queue, payload, attempts, available_at, created_at, reserved_at) 
                    VALUES (?, ?, ?, ?, ?, ?, NULL)";

            $stmt = $conn->prepare($sql);

            foreach ($jobs as $job) {
                $jobId = $job->getId() ?? bin2hex(random_bytes(16));
                $job->setId($jobId);
                $jobIds[] = $jobId;

                $payload = json_encode($job->serialize(), JSON_THROW_ON_ERROR);

                $stmt->execute([
                    $jobId,
                    $job->getQueue(),
                    $payload,
                    0, // Anfängliche Anzahl an Versuchen
                    date('Y-m-d H:i:s', $availableAt),
                    date('Y-m-d H:i:s')
                ]);
            }

            $conn->commit();
            return $jobIds;
        } catch (Throwable $e) {
            $conn->rollBack();
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
        $conn = $this->db->connection();
        $conn->beginTransaction();

        try {
            // Job finden und reservieren - Atomic Update
            $now = date('Y-m-d H:i:s');
            $retryAfterTime = date('Y-m-d H:i:s', time() - $this->retryAfter);

            $sql = "SELECT * FROM {$this->table}
                    WHERE queue = ? 
                    AND (reserved_at IS NULL OR reserved_at <= ?)
                    AND available_at <= ?
                    ORDER BY id ASC
                    LIMIT 1 FOR UPDATE";

            $stmt = $conn->prepare($sql);
            $stmt->execute([$queue, $retryAfterTime, $now]);

            $jobData = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$jobData) {
                $conn->commit();
                return null;
            }

            // Job reservieren
            $updateSql = "UPDATE {$this->table} 
                          SET reserved_at = ?, attempts = attempts + 1 
                          WHERE id = ?";

            $updateStmt = $conn->prepare($updateSql);
            $updateStmt->execute([$now, $jobData['id']]);

            $conn->commit();

            // Job deserialisieren
            try {
                $jobPayload = json_decode($jobData['payload'], true, 512, JSON_THROW_ON_ERROR);
                $job = Job::unserialize($jobPayload);
                return $job;
            } catch (Exception $e) {
                // Job-Fehler protokollieren und löschen
                app_log("Fehler beim Deserialisieren des Jobs", [
                    'job_id' => $jobData['id'],
                    'error' => $e->getMessage()
                ], 'error');

                $this->delete($jobData['id']);
                return null;
            }
        } catch (Throwable $e) {
            $conn->rollBack();
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
        $sql = "SELECT COUNT(*) FROM {$this->table} WHERE id = ?";
        $stmt = $this->db->connection()->prepare($sql);
        $stmt->execute([$jobId]);

        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Löscht einen spezifischen Job aus der Queue
     *
     * @param string $jobId Job-Identifier
     * @return bool True bei Erfolg, sonst false
     */
    public function delete(string $jobId): bool
    {
        $sql = "DELETE FROM {$this->table} WHERE id = ?";
        $stmt = $this->db->connection()->prepare($sql);
        $stmt->execute([$jobId]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Anzahl der Jobs in einer Queue
     *
     * @param string $queue Spezifische Queue-Instanz
     * @return int Anzahl der Jobs
     */
    public function count(string $queue = 'default'): int
    {
        $sql = "SELECT COUNT(*) FROM {$this->table} WHERE queue = ?";
        $stmt = $this->db->connection()->prepare($sql);
        $stmt->execute([$queue]);

        return (int)$stmt->fetchColumn();
    }

    /**
     * Leert eine komplette Queue
     *
     * @param string $queue Spezifische Queue-Instanz
     * @return bool True bei Erfolg, sonst false
     */
    public function clear(string $queue = 'default'): bool
    {
        $sql = "DELETE FROM {$this->table} WHERE queue = ?";
        $stmt = $this->db->connection()->prepare($sql);
        $stmt->execute([$queue]);

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

        $sql = "SELECT * FROM {$this->table} 
                WHERE queue = ? 
                ORDER BY available_at ASC
                LIMIT ? OFFSET ?";

        $stmt = $this->db->connection()->prepare($sql);
        $stmt->execute([$queue, $limit, $offset]);

        while ($jobData = $stmt->fetch(PDO::FETCH_ASSOC)) {
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

        $sql = "SELECT * FROM {$this->table} 
                WHERE queue = ? AND payload LIKE ?
                ORDER BY available_at ASC";

        $stmt = $this->db->connection()->prepare($sql);
        $stmt->execute([$queue, '%"tag":"' . $tag . '"%']);

        while ($jobData = $stmt->fetch(PDO::FETCH_ASSOC)) {
            try {
                $jobPayload = json_decode($jobData['payload'], true, 512, JSON_THROW_ON_ERROR);
                $job = Job::unserialize($jobPayload);

                // Zusätzliche Prüfung, da LIKE ungenau sein kann
                if ($job->getTag() === $tag) {
                    $jobs[] = $job;
                }
            } catch (Exception $e) {
                // Fehler beim Deserialisieren ignorieren
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
        $conn = $this->db->connection();

        try {
            // Prüfen, ob Tabelle existiert
            $sql = "SELECT 1 FROM {$this->table} LIMIT 1";
            $stmt = $conn->prepare($sql);
            $stmt->execute();

            // Tabelle existiert bereits
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
        $conn = $this->db->connection();

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

            $conn->exec($sql);

            app_log("Jobs-Tabelle wurde erstellt", [
                'table' => $this->table
            ], 'info');
        } catch (Exception $e) {
            throw new RuntimeException("Fehler beim Erstellen der Jobs-Tabelle: " . $e->getMessage(), 0, $e);
        }
    }
}