<?php


declare(strict_types=1);

namespace App\Infrastructure\Database\QueryBuilder;

use App\Infrastructure\Database\Exceptions\QueryException;
use Closure;
use PDOStatement;

class UpdateQueryBuilder extends QueryBuilder
{
    use JsonQueryTrait;

    /**
     * Zu aktualisierende Daten
     *
     * @var array<string, mixed>
     */
    protected array $values = [];


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
     * {@inheritdoc}
     */
    public function where(string|RawExpression $column, mixed $operator = null, mixed $value = null): static
    {
        return parent::where($column, $operator, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function orWhere(string|RawExpression $column, mixed $operator = null, mixed $value = null): static
    {
        return parent::orWhere($column, $operator, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function whereGroup(Closure $callback): static
    {
        return parent::whereGroup($callback);
    }


    /**
     * {@inheritdoc}
     */
    public function orWhereGroup(Closure $callback): static
    {
        return parent::orWhereGroup($callback);
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
            if ($this->whereGroup === null) {
                $this->whereGroup = new WhereClauseGroup();
            }
            $this->whereGroup->where(new RawExpression('0 = 1'));
            return $this;
        }

        $placeholders = [];
        $params = [];

        foreach ($values as $value) {
            $paramName = $this->createParameterName('wherein');
            $params[$paramName] = $value;
            $placeholders[] = ":{$paramName}";
        }

        if ($this->whereGroup === null) {
            $this->whereGroup = new WhereClauseGroup();
        }

        $expr = new RawExpression("{$column} IN (" . implode(', ', $placeholders) . ")", $params);
        $this->whereGroup->where($expr);

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
        if ($this->whereGroup === null) {
            $this->whereGroup = new WhereClauseGroup();
        }

        $this->whereGroup->where(new RawExpression("{$column} IS NULL"));
        return $this;
    }

    /**
     * Die whereNotNull-Methode muss auf WhereClauseGroup umgestellt werden
     *
     * @param string $column Spalte
     * @return $this
     */
    public function whereNotNull(string $column): self
    {
        if ($this->whereGroup === null) {
            $this->whereGroup = new WhereClauseGroup();
        }

        $this->whereGroup->where(new RawExpression("{$column} IS NOT NULL"));
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

        if ($this->whereGroup === null || empty($this->whereGroup->toSql())) {
            throw new QueryException('No WHERE clause specified for update query. To update all records, use whereTrue() explicitly.');
        }

        $sql = $this->toSql();
        $statement = $this->getConnection()->query($sql, $this->parameters);

        return $statement;
    }

    /**
     * @param array $records
     * @param string $keyColumn
     * @return int
     * @throws QueryException
     */
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
                $this->values($updateData);

                // Statt direkt $this->wheres zu manipulieren, nutzen wir die where-Methode
                // mit WhereClauseGroup
                if ($this->whereGroup === null) {
                    $this->whereGroup = new WhereClauseGroup();
                } else {
                    // Zurücksetzen der WhereClauseGroup für jede neue Update-Operation
                    $this->whereGroup = new WhereClauseGroup();
                }

                // Bedingung für diesen spezifischen Update hinzufügen
                $this->where($keyColumn, $keyValue);

                // Führe das Update aus
                $result = parent::execute();
                $updated += $result->rowCount();

                // Setze für das nächste Update zurück
                $this->values = [];
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

        // Verwende die WhereClauseGroup für konsistente Bedingungsverarbeitung
        if ($this->whereGroup !== null && !empty($this->whereGroup->toSql())) {
            // Parameter aus der WhereClauseGroup übernehmen
            $this->parameters = array_merge($this->parameters, $this->whereGroup->getParameters());
            $sql .= ' WHERE ' . $this->whereGroup->toSql();
        } else {
            // Wenn keine WHERE-Bedingung angegeben wurde, wirf eine Exception
            throw new QueryException('No WHERE clause specified for update query. To update all records, use whereTrue() explicitly.');
        }

        return $sql;
    }

    /**
     * Explizit alle Datensätze aktualisieren (gefährlich!)
     *
     * @return $this
     */
    public function whereTrue(): self
    {
        if ($this->whereGroup === null) {
            $this->whereGroup = new WhereClauseGroup();
        }

        $this->whereGroup->where(new RawExpression('1 = 1'));
        return $this;
    }

}