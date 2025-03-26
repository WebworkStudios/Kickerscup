<?php


declare(strict_types=1);

namespace App\Infrastructure\Database\QueryBuilder;

use App\Infrastructure\Database\Exceptions\QueryException;
use PDOStatement;

class UpdateQueryBuilder extends QueryBuilder
{
    /**
     * Zu aktualisierende Daten
     *
     * @var array<string, mixed>
     */
    protected array $values = [];

    /**
     * WHERE-Bedingungen
     *
     * @var array<string>
     */
    protected array $wheres = [];

    protected ?WhereClauseGroup $whereGroup = null;

    /**
     * Fügt zu aktualisierende Daten hinzu
     *
     * @param array $values Zu aktualisierende Daten
     * @return $this
     */
    public function values(array $values): self
    {
        $this->values = array_merge($this->values, $values);
        return $this;
    }

    /**
     * Setzt einen einzelnen Wert, der auch eine Raw-Expression sein kann
     *
     * @param string $column Spalte
     * @param mixed $value Wert oder Raw-Expression
     * @return $this
     */
    public function set(string $column, mixed $value): self
    {
        $this->values[$column] = $value;
        return $this;
    }

    /**
     * Fügt eine WHERE-Bedingung hinzu
     *
     * @param string|RawExpression $column Spalte oder Raw-Expression
     * @param mixed $operator Operator oder Wert
     * @param mixed $value Wert (optional)
     * @return $this
     */
    public function where(string|RawExpression $column, mixed $operator = null, mixed $value = null): self
    {
        // Initialisiere die WhereClauseGroup falls noch nicht vorhanden
        if ($this->whereGroup === null) {
            $this->whereGroup = new WhereClauseGroup;
        }

        // Wenn column eine RawExpression ist, verwende sie direkt
        if ($column instanceof RawExpression) {
            // Füge alle Bindungen der Raw-Expression hinzu
            $this->parameters = array_merge($this->parameters, $column->getParameters());
            $this->whereGroup->where($column);
            return $this;
        }

        // Wenn nur zwei Parameter angegeben wurden, verwende = als Operator
        if ($value === null && $operator !== null) {
            $value = $operator;
            $operator = '=';
        }

        // Delegiere an die WhereClauseGroup
        $this->whereGroup->where($column, $operator, $value);

        return $this;
    }

    /**
     * Fügt eine WHERE OR-Bedingung hinzu
     *
     * @param string|RawExpression $column Spalte oder Raw-Expression
     * @param mixed $operator Operator oder Wert
     * @param mixed $value Wert (optional)
     * @return $this
     */
    public function orWhere(string|RawExpression $column, mixed $operator = null, mixed $value = null): self
    {
        if ($this->whereGroup === null) {
            $this->whereGroup = new WhereClauseGroup();
        }

        $this->whereGroup->orWhere($column, $operator, $value);

        return $this;
    }

    /**
     * Erstellt eine neue verschachtelte Bedingungsgruppe mit AND-Verknüpfung
     *
     * @param \Closure $callback Callback-Funktion, die die neue Gruppe konfiguriert
     * @return $this
     */
    public function whereGroup(\Closure $callback): self
    {
        if ($this->whereGroup === null) {
            $this->whereGroup = new WhereClauseGroup();
        }

        $this->whereGroup->whereGroup($callback);

        return $this;
    }

    /**
     * Erstellt eine neue verschachtelte Bedingungsgruppe mit OR-Verknüpfung
     *
     * @param \Closure $callback Callback-Funktion, die die neue Gruppe konfiguriert
     * @return $this
     */
    public function orWhereGroup(\Closure $callback): self
    {
        if ($this->whereGroup === null) {
            $this->whereGroup = new WhereClauseGroup();
        }

        $this->whereGroup->orWhereGroup($callback);

        return $this;
    }


    /**
     * Fügt eine WHERE IN-Bedingung hinzu
     *
     * @param string $column Spalte
     * @param array $values Werte
     * @return $this
     */
    public function whereIn(string $column, array $values): self
    {
        if (empty($values)) {
            $this->wheres[] = '0 = 1'; // Immer false, wenn keine Werte angegeben
            return $this;
        }

        $placeholders = [];

        foreach ($values as $value) {
            $paramName = $this->createParameterName('wherein');
            $this->parameters[$paramName] = $value;
            $placeholders[] = ":{$paramName}";
        }

        $this->wheres[] = "{$column} IN (" . implode(', ', $placeholders) . ")";

        return $this;
    }

    /**
     * Fügt eine WHERE NULL-Bedingung hinzu
     *
     * @param string $column Spalte
     * @return $this
     */
    public function whereNull(string $column): self
    {
        $this->wheres[] = "{$column} IS NULL";
        return $this;
    }

    /**
     * Fügt eine WHERE NOT NULL-Bedingung hinzu
     *
     * @param string $column Spalte
     * @return $this
     */
    public function whereNotNull(string $column): self
    {
        $this->wheres[] = "{$column} IS NOT NULL";
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function execute(): PDOStatement
    {
        if (empty($this->values)) {
            throw new QueryException('No values specified for update query');
        }

        if (empty($this->wheres)) {
            throw new QueryException('No WHERE clause specified for update query. To update all records, use whereTrue() explicitly.');
        }

        $sql = $this->toSql();
        $statement = $this->getConnection()->query($sql, $this->parameters);

        return $statement;
    }

    /**
     * Explizit alle Datensätze aktualisieren (gefährlich!)
     *
     * @return $this
     */
    public function whereTrue(): self
    {
        $this->wheres[] = '1 = 1';
        return $this;
    }

    public function bulkUpdate(array $records, string $keyColumn): int
    {
        if (empty($records)) {
            return 0;
        }

        // Extrahiere alle eindeutigen Schlüsselwerte
        $keyValues = array_map(fn($record) => $record[$keyColumn] ?? null, $records);

        // Gruppiere Records nach Schlüssel für effizientere Updates
        $recordsByKey = [];
        foreach ($records as $record) {
            if (!isset($record[$keyColumn])) continue;
            $recordsByKey[$record[$keyColumn]] = $record;
        }

        // Führe eine Transaktion für alle Updates durch
        $connection = $this->getConnection();
        $connection->beginTransaction();

        $updated = 0;
        try {
            foreach ($recordsByKey as $keyValue => $record) {
                // Entferne den Schlüssel aus den zu aktualisierenden Daten
                $updateData = array_filter($record, fn($k) => $k !== $keyColumn, ARRAY_FILTER_USE_KEY);

                // Setze die Werte und die WHERE-Bedingung
                $this->values($updateData)
                    ->where($keyColumn, $keyValue);

                // Führe das Update aus
                $result = parent::execute();
                $updated += $result->rowCount();

                // Setze für das nächste Update zurück
                $this->values = [];
                $this->wheres = [];
                $this->parameters = [];
            }

            $connection->commit();
            return $updated;
        } catch (\Exception $e) {
            $connection->rollback();
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function toSql(): string
    {
        if (empty($this->values)) {
            throw new QueryException('No values specified for update query');
        }

        $sql = "UPDATE {$this->table} SET ";

        $sets = [];

        foreach ($this->values as $column => $value) {
            if ($value instanceof RawExpression) {
                // Füge die Parameter der Raw-Expression hinzu
                $this->parameters = array_merge($this->parameters, $value->getParameters());
                $sets[] = "{$column} = {$value->toSql()}";
            } else {
                $paramName = "update_{$column}";
                $this->parameters[$paramName] = $value;
                $sets[] = "{$column} = :{$paramName}";
            }
        }

        $sql .= implode(', ', $sets);

        // Verwende die WhereClauseGroup statt der alten wheres-Array
        if ($this->whereGroup !== null && !empty($this->whereGroup->toSql())) {
            // Parameter aus der WhereClauseGroup übernehmen
            $this->parameters = array_merge($this->parameters, $this->whereGroup->getParameters());
            $sql .= ' WHERE ' . $this->whereGroup->toSql();
        }

        return $sql;
    }

}