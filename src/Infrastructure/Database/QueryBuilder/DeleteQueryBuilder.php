<?php


declare(strict_types=1);

namespace App\Infrastructure\Database\QueryBuilder;

use App\Infrastructure\Database\Exceptions\QueryException;
use PDOStatement;

class DeleteQueryBuilder extends QueryBuilder
{
    /**
     * WHERE-Bedingungen
     *
     * @var array<string>
     */
    protected array $wheres = [];

    protected ?WhereClauseGroup $whereGroup = null;

    /**
     * {@inheritdoc}
     */
    public function where(string|RawExpression $column, mixed $operator = null, mixed $value = null): static
    {
        return parent::where($column, $operator, $value);
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
        if (empty($this->wheres)) {
            throw new QueryException('No WHERE clause specified for delete query. To delete all records, use whereTrue() explicitly.');
        }

        $sql = $this->toSql();
        $statement = $this->getConnection()->query($sql, $this->parameters);

        $this->invalidateStatementCache();

        return $statement;
    }

    /**
     * Explizit alle Datensätze löschen (gefährlich!)
     *
     * @return $this
     */
    public function whereTrue(): self
    {
        $this->whereTrue = true;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function toSql(): string
    {
        $sql = "DELETE FROM {$this->table}";

        // Verwende die WhereClauseGroup statt der alten wheres-Array
        if ($this->whereGroup !== null && !empty($this->whereGroup->toSql())) {
            // Parameter aus der WhereClauseGroup übernehmen
            $this->parameters = array_merge($this->parameters, $this->whereGroup->getParameters());
            $sql .= ' WHERE ' . $this->whereGroup->toSql();
        } else if (!empty($this->whereTrue)) {
            $sql .= ' WHERE 1 = 1';
        } else {
            throw new QueryException('No WHERE clause specified for delete query. To delete all records, use whereTrue() explicitly.');
        }

        return $sql;
    }

}