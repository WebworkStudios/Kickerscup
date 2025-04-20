<?php


declare(strict_types=1);

namespace App\Core\Queue;

/**
 * Queue-Interface für verschiedene Queue-Implementierungen
 */
interface Queue
{
    /**
     * Fügt einen Job zur Queue hinzu
     *
     * @param Job $job Job, der zur Queue hinzugefügt wird
     * @param int|null $delay Verzögerung in Sekunden
     * @return string Job-Identifier
     */
    public function push(Job $job, ?int $delay = null): string;

    /**
     * Holt und entfernt den nächsten Job aus der Queue
     *
     * @param string $queue Spezifische Queue-Instanz
     * @return Job|null Der nächste Job oder null
     */
    public function pop(string $queue = 'default'): ?Job;

    /**
     * Überprüft, ob ein Job bereits in der Queue existiert
     *
     * @param string $jobId Job-Identifier
     * @return bool True, wenn Job gefunden, sonst false
     */
    public function exists(string $jobId): bool;

    /**
     * Löscht einen spezifischen Job aus der Queue
     *
     * @param string $jobId Job-Identifier
     * @return bool True bei Erfolg, sonst false
     */
    public function delete(string $jobId): bool;

    /**
     * Anzahl der Jobs in einer Queue
     *
     * @param string $queue Spezifische Queue-Instanz
     * @return int Anzahl der Jobs
     */
    public function count(string $queue = 'default'): int;

    /**
     * Leert eine komplette Queue
     *
     * @param string $queue Spezifische Queue-Instanz
     * @return bool True bei Erfolg, sonst false
     */
    public function clear(string $queue = 'default'): bool;
}