<?php


declare(strict_types=1);

namespace App\Infrastructure\Database\QueryBuilder;

use App\Infrastructure\Database\Exceptions\QueryException;
use PDO;

class InsertQueryBuilder extends QueryBuilder
{
    /**
     * Zu inserierende Daten
     *
     * @var array<array>
     */
    protected array $values = [];

    /**
     * Fügt Daten für den Insert hinzu
     *
     * @param array $values Zu inserierende Daten
     * @return $this
     */
    public function values(array $values): self
    {
        // Prüft, ob es sich um ein assoziatives Array oder eine Liste von Arrays handelt
        if (isset($values[0]) && is_array($values[0])) {
            // Liste von Datensätzen
            $this->values = array_merge($this->values, $values);
        } else {
            // Einzelner Datensatz
            $this->values[] = $values;
        }

        return $this;
    }

    /**
     * Führt den Insert aus und gibt die ID des neuen Datensatzes zurück
     *
     * @return int|string|null Die ID des eingefügten Datensatzes oder null
     */
    public function execute(): int|string|null
    {
        if (empty($this->values)) {
            throw new QueryException('No values specified for insert query');
        }

        $sql = $this->toSql();
        $connection = $this->getConnection();

        $connection->query($sql, $this->parameters);

        $pdoInstance = $connection->getPdo();

        // Zurückgeben der ID für das zuletzt eingefügte Element
        $lastId = $pdoInstance->lastInsertId();

        // Konvertiere in Integer, wenn möglich
        return is_numeric($lastId) ? (int)$lastId : $lastId;
    }

    /**
     * Führt einen Batch-Insert aus und gibt die Anzahl eingefügter Zeilen zurück
     *
     * @param int $batchSize Anzahl der Datensätze pro Batch
     * @return int Anzahl eingefügter Zeilen
     */
    public function executeBatch(int $batchSize = 100): int
    {
        if (empty($this->values)) {
            throw new QueryException('No values specified for batch insert query');
        }

        // Speichere die ursprünglichen Werte
        $allValues = $this->values;
        $totalInserted = 0;

        // Verarbeite die Werte in Batches
        foreach (array_chunk($allValues, $batchSize) as $batchValues) {
            $this->values = $batchValues;
            $this->parameters = []; // Parameter zurücksetzen

            $sql = $this->toSql();
            $statement = $this->getConnection()->query($sql, $this->parameters);

            $totalInserted += $statement->rowCount();
        }

        // Setze die Werte zurück
        $this->values = $allValues;

        return $totalInserted;
    }

    /**
     * {@inheritdoc}
     */
    public function toSql(): string
    {
        if (empty($this->values)) {
            throw new QueryException('No values specified for insert query');
        }

        $sql = "INSERT INTO {$this->table}";

        // Extrahiere die Spalten aus dem ersten Datensatz
        $columns = array_keys($this->values[0]);
        $sql .= ' (' . implode(', ', $columns) . ')';

        $sql .= ' VALUES ';

        $valueSets = [];

        foreach ($this->values as $index => $record) {
            $placeholders = [];

            foreach ($record as $column => $value) {
                $paramName = "value_{$index}_{$column}";
                $this->parameters[$paramName] = $value;
                $placeholders[] = ":{$paramName}";
            }

            $valueSets[] = '(' . implode(', ', $placeholders) . ')';
        }

        $sql .= implode(', ', $valueSets);

        return $sql;
    }
}