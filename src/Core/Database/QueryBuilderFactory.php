<?php


declare(strict_types=1);

namespace App\Core\Database;

/**
 * Factory für den QueryBuilder
 *
 * Diese Klasse erleichtert die Erstellung von QueryBuilder-Instanzen
 * und bietet praktische Methoden für häufige Datenbankoperationen.
 */
class QueryBuilderFactory
{
    /**
     * Der DatabaseManager
     */
    private DatabaseManager $dbManager;

    /**
     * Konstruktor
     *
     * @param DatabaseManager $dbManager Der DatabaseManager
     */
    public function __construct(DatabaseManager $dbManager)
    {
        $this->dbManager = $dbManager;
    }

    /**
     * Erstellt einen QueryBuilder für eine Tabelle
     *
     * @param string $table Die Tabelle
     * @param string|null $connection Die Verbindung oder null für die Standardverbindung
     * @return QueryBuilder
     */
    public function table(string $table, ?string $connection = null): QueryBuilder
    {
        return $this->dbManager->table($table, $connection);
    }

    /**
     * Erstellt eine Unterabfrage
     *
     * @param callable(QueryBuilder): void $callback Die Callback-Funktion, die einen QueryBuilder erhält
     * @param string|null $alias Der Alias für die Unterabfrage
     * @param string|null $connection Die Verbindung oder null für die Standardverbindung
     * @return SubQueryBuilder
     */
    public function subQuery(callable $callback, ?string $alias = null, ?string $connection = null): SubQueryBuilder
    {
        // Erstellt einen temporären QueryBuilder
        $query = $this->dbManager->connection($connection)->table('__temp__');

        // Ruft den Callback mit dem QueryBuilder auf
        $callback($query);

        // Erstellt die Unterabfrage
        return new SubQueryBuilder($query, $alias);
    }

    /**
     * Führt eine Funktion in einer Transaktion aus
     *
     * @param callable(Connection): mixed $callback Die Callback-Funktion
     * @param string|null $connection Die Verbindung oder null für die Standardverbindung
     * @return mixed Das Ergebnis des Callbacks
     * @throws \Throwable wenn ein Fehler auftritt
     */
    public function transaction(callable $callback, ?string $connection = null): mixed
    {
        return $this->dbManager->connection($connection)->transaction($callback);
    }

    /**
     * Führt eine rohe SQL-Abfrage aus
     *
     * @param string $query Die SQL-Abfrage
     * @param array $bindings Die Parameter
     * @param string|null $connection Die Verbindung oder null für die Standardverbindung
     * @return array Die Ergebnisse
     */
    public function query(string $query, array $bindings = [], ?string $connection = null): array
    {
        return $this->dbManager->connection($connection)->select($query, $bindings);
    }

    /**
     * Führt eine rohe SQL-Abfrage aus und gibt das erste Ergebnis zurück
     *
     * @param string $query Die SQL-Abfrage
     * @param array $bindings Die Parameter
     * @param string|null $connection Die Verbindung oder null für die Standardverbindung
     * @return array|false Das erste Ergebnis oder false
     */
    public function queryFirst(string $query, array $bindings = [], ?string $connection = null): array|false
    {
        $results = $this->query($query, $bindings, $connection);
        return $results ? $results[0] : false;
    }

    /**
     * Führt eine SQL-Abfrage aus, die einen einzelnen Wert zurückgibt
     *
     * @param string $query Die SQL-Abfrage
     * @param array $bindings Die Parameter
     * @param string|null $connection Die Verbindung oder null für die Standardverbindung
     * @return mixed Der Wert
     */
    public function queryValue(string $query, array $bindings = [], ?string $connection = null): mixed
    {
        return $this->dbManager->connection($connection)->selectValue($query, $bindings);
    }

    /**
     * Führt eine Einfügeoperation mit mehreren Datensätzen aus
     *
     * @param string $table Die Tabelle
     * @param array $data Die Daten
     * @param string|null $connection Die Verbindung oder null für die Standardverbindung
     * @return bool true bei Erfolg
     */
    public function insertBatch(string $table, array $data, ?string $connection = null): bool
    {
        return $this->table($table, $connection)->insertMany($data);
    }

    /**
     * Führt einen Massenupdate aus
     *
     * @param string $table Die Tabelle
     * @param array $values Die zu aktualisierenden Werte
     * @param string $whereColumn Die WHERE-Spalte
     * @param string|null $connection Die Verbindung oder null für die Standardverbindung
     * @return int Die Anzahl der aktualisierten Zeilen
     */
    public function updateBatch(string $table, array $values, string $whereColumn, ?string $connection = null): int
    {
        if (empty($values)) {
            return 0;
        }

        $affected = 0;

        // Da wir keine direkten CASE-Statements verwenden, führen wir separate UPDATEs aus
        $this->transaction(function () use ($table, $values, $whereColumn, $connection, &$affected) {
            $query = $this->table($table, $connection);

            foreach ($values as $key => $value) {
                $whereValue = $value[$whereColumn] ?? null;

                if ($whereValue === null) {
                    continue;
                }

                // Entferne die WHERE-Spalte aus den zu aktualisierenden Werten
                $updateValues = array_diff_key($value, [$whereColumn => true]);

                // Aktualisiere nur, wenn es etwas zu aktualisieren gibt
                if (!empty($updateValues)) {
                    $affected += $query->where($whereColumn, '=', $whereValue)->update($updateValues);
                }
            }
        }, $connection);

        return $affected;
    }

    /**
     * Gibt den DatabaseManager zurück
     *
     * @return DatabaseManager
     */
    public function getDatabaseManager(): DatabaseManager
    {
        return $this->dbManager;
    }
}