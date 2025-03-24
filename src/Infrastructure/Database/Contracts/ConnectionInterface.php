<?php


declare(strict_types=1);

namespace App\Infrastructure\Database\Contracts;

use PDO;
use PDOStatement;

interface ConnectionInterface
{
    /**
     * Gibt die PDO-Instanz zurück
     */
    public function getPdo(): PDO;

    /**
     * Führt eine SQL-Abfrage aus
     *
     * @param string $query Die SQL-Abfrage
     * @param array $params Parameter für die Abfrage
     * @return PDOStatement Das Ergebnis der Abfrage
     */
    public function query(string $query, array $params = []): PDOStatement;

    /**
     * Führt eine SQL-Abfrage aus und gibt die erste Zeile zurück
     *
     * @param string $query Die SQL-Abfrage
     * @param array $params Parameter für die Abfrage
     * @return array|null Die erste Zeile oder null
     */
    public function queryFirst(string $query, array $params = []): ?array;

    /**
     * Führt eine SQL-Abfrage aus und gibt alle Zeilen zurück
     *
     * @param string $query Die SQL-Abfrage
     * @param array $params Parameter für die Abfrage
     * @return array Die Ergebniszeilen
     */
    public function queryAll(string $query, array $params = []): array;

    /**
     * Startet eine Transaktion
     */
    public function beginTransaction(): bool;

    /**
     * Bestätigt eine Transaktion
     */
    public function commit(): bool;

    /**
     * Macht eine Transaktion rückgängig
     */
    public function rollback(): bool;

    /**
     * Prüft, ob sich die Verbindung innerhalb einer Transaktion befindet
     */
    public function inTransaction(): bool;

    /**
     * Prüft, ob die Verbindung aktiv ist
     */
    public function isConnected(): bool;

    /**
     * Verbindet mit der Datenbank, wenn keine Verbindung besteht
     */
    public function connect(): void;

    /**
     * Schließt die Verbindung zur Datenbank
     */
    public function disconnect(): void;
}