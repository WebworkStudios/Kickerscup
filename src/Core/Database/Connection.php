<?php

declare(strict_types=1);

namespace App\Core\Database;

use PDO;
use PDOException;
use PDOStatement;

/**
 * Datenbankverbindung
 *
 * Stellt eine Verbindung zu einer Datenbank her und verwaltet diese
 */
class Connection
{
    /**
     * PDO-Instanz
     */
    private ?PDO $pdo = null;

    /**
     * Konfiguration für die Verbindung
     */
    private array $config;

    /**
     * Standardkonfiguration
     */
    private array $defaultConfig = [
        'driver' => 'mysql',
        'host' => 'localhost',
        'port' => 3306,
        'database' => '',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'options' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_PERSISTENT => true
        ]
    ];

    /**
     * Konstruktor
     *
     * @param array $config Konfiguration für die Verbindung
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge($this->defaultConfig, $config);
    }

    /**
     * Gibt die PDO-Instanz zurück oder erstellt eine neue, wenn noch keine existiert
     *
     * @return PDO
     */
    public function getPdo(): PDO
    {
        if ($this->pdo === null) {
            $this->connect();
        }

        return $this->pdo;
    }

    /**
     * Stellt eine Verbindung zur Datenbank her
     *
     * @return void
     * @throws PDOException wenn die Verbindung nicht hergestellt werden kann
     */
    private function connect(): void
    {
        $dsn = $this->createDsn();

        try {
            $this->pdo = new PDO(
                $dsn,
                $this->config['username'],
                $this->config['password'],
                $this->config['options']
            );
        } catch (PDOException $e) {
            throw new PDOException(
                "Fehler beim Verbinden zur Datenbank: {$e->getMessage()}",
                (int)$e->getCode(),
                $e
            );
        }
    }

    /**
     * Erstellt den DSN für die Verbindung
     *
     * @return string
     */
    private function createDsn(): string
    {
        $driver = $this->config['driver'];

        switch ($driver) {
            case 'mysql':
                return sprintf(
                    'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                    $this->config['host'],
                    $this->config['port'],
                    $this->config['database'],
                    $this->config['charset']
                );

            case 'pgsql':
                return sprintf(
                    'pgsql:host=%s;port=%d;dbname=%s',
                    $this->config['host'],
                    $this->config['port'],
                    $this->config['database']
                );

            case 'sqlite':
                return sprintf('sqlite:%s', $this->config['database']);

            default:
                throw new \InvalidArgumentException("Der Datenbanktreiber '{$driver}' wird nicht unterstützt.");
        }
    }

    /**
     * Führt eine SQL-Query aus
     *
     * @param string $query SQL-Query
     * @param array $params Parameter für die Query
     * @return PDOStatement
     */
    public function query(string $query, array $params = []): PDOStatement
    {
        $statement = $this->getPdo()->prepare($query);
        $statement->execute($params);

        return $statement;
    }

    /**
     * Führt eine SQL-Query aus und gibt alle Ergebnisse zurück
     *
     * @param string $query SQL-Query
     * @param array $params Parameter für die Query
     * @param int $fetchMode Fetch-Modus für PDO
     * @return array
     */
    public function select(string $query, array $params = [], int $fetchMode = PDO::FETCH_ASSOC): array
    {
        return $this->query($query, $params)->fetchAll($fetchMode);
    }

    /**
     * Führt eine SQL-Query aus und gibt das erste Ergebnis zurück
     *
     * @param string $query SQL-Query
     * @param array $params Parameter für die Query
     * @param int $fetchMode Fetch-Modus für PDO
     * @return array|false
     */
    public function selectOne(string $query, array $params = [], int $fetchMode = PDO::FETCH_ASSOC): array|false
    {
        return $this->query($query, $params)->fetch($fetchMode);
    }

    /**
     * Führt eine SQL-Query aus und gibt die erste Spalte des ersten Ergebnisses zurück
     *
     * @param string $query SQL-Query
     * @param array $params Parameter für die Query
     * @return mixed
     */
    public function selectValue(string $query, array $params = []): mixed
    {
        return $this->query($query, $params)->fetchColumn();
    }

    /**
     * Führt ein INSERT aus
     *
     * @param string $table Tabellenname
     * @param array $data Daten
     * @return int Letzte eingefügte ID
     */
    public function insert(string $table, array $data): int
    {
        $columns = array_keys($data);
        $placeholders = array_map(fn($column) => ":$column", $columns);

        $query = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $this->query($query, $data);

        return (int)$this->getPdo()->lastInsertId();
    }

    /**
     * Führt ein UPDATE aus
     *
     * @param string $table Tabellenname
     * @param array $data Daten
     * @param string $where WHERE-Klausel
     * @param array $params Parameter für die WHERE-Klausel
     * @return int Anzahl der geänderten Zeilen
     */
    public function update(string $table, array $data, string $where, array $params = []): int
    {
        $set = array_map(fn($column) => "$column = :$column", array_keys($data));

        $query = sprintf(
            'UPDATE %s SET %s WHERE %s',
            $table,
            implode(', ', $set),
            $where
        );

        $statement = $this->query($query, array_merge($data, $params));

        return $statement->rowCount();
    }

    /**
     * Führt ein DELETE aus
     *
     * @param string $table Tabellenname
     * @param string $where WHERE-Klausel
     * @param array $params Parameter für die WHERE-Klausel
     * @return int Anzahl der gelöschten Zeilen
     */
    public function delete(string $table, string $where, array $params = []): int
    {
        $query = sprintf('DELETE FROM %s WHERE %s', $table, $where);

        $statement = $this->query($query, $params);

        return $statement->rowCount();
    }

    /**
     * Startet eine Transaktion
     *
     * @return bool
     */
    public function beginTransaction(): bool
    {
        return $this->getPdo()->beginTransaction();
    }

    /**
     * Führt ein Commit aus
     *
     * @return bool
     */
    public function commit(): bool
    {
        return $this->getPdo()->commit();
    }

    /**
     * Führt ein Rollback aus
     *
     * @return bool
     */
    public function rollBack(): bool
    {
        return $this->getPdo()->rollBack();
    }

    /**
     * Führt eine Funktion in einer Transaktion aus
     *
     * @param \Closure $callback Funktion, die in der Transaktion ausgeführt werden soll
     * @return mixed
     * @throws \Throwable wenn ein Fehler auftritt
     */
    public function transaction(\Closure $callback): mixed
    {
        $this->beginTransaction();

        try {
            $result = $callback($this);

            $this->commit();

            return $result;
        } catch (\Throwable $e) {
            $this->rollBack();

            throw $e;
        }
    }

    /**
     * Erstellt einen QueryBuilder für eine Tabelle
     *
     * @param string $table Tabellenname
     * @return QueryBuilder
     */
    public function table(string $table): QueryBuilder
    {
        return new QueryBuilder($this, $table);
    }

    /**
     * Schließt die Verbindung zur Datenbank
     *
     * @return void
     */
    public function disconnect(): void
    {
        $this->pdo = null;
    }
}