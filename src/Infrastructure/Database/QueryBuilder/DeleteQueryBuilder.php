<?php


declare(strict_types=1);

namespace App\Infrastructure\Database\QueryBuilder;

use App\Infrastructure\Database\Exceptions\QueryException;
use Closure;
use PDOStatement;

class DeleteQueryBuilder extends QueryBuilder
{
    use JsonQueryTrait;

    protected ?WhereClauseGroup $whereGroup = null;

    /**
     * Flag für das Löschen aller Datensätze
     *
     * @var bool
     */
    protected bool $whereTrue = false;

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
        if ($this->whereGroup === null) {
            $this->whereGroup = new WhereClauseGroup();
        }

        $this->whereGroup->where(new RawExpression("{$column} IS NULL"));
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
        if ($this->whereGroup === null || empty($this->whereGroup->toSql())) {
            if (!$this->whereTrue) {
                throw new QueryException('No WHERE clause specified for delete query. To delete all records, use whereTrue() explicitly.');
            }
        }

        $sql = $this->toSql();
        $statement = $this->getConnection()->query($sql, $this->parameters);

        $this->invalidateStatementCache();

        return $statement;
    }

    /**
     * {@inheritdoc}
     */
    public function toSql(): string
    {
        $sql = "DELETE FROM {$this->table}";

        // Verwende die WhereClauseGroup für konsistente Bedingungsverarbeitung
        if ($this->whereGroup !== null && !empty($this->whereGroup->toSql())) {
            // Parameter aus der WhereClauseGroup übernehmen
            $this->parameters = array_merge($this->parameters, $this->whereGroup->getParameters());
            $sql .= ' WHERE ' . $this->whereGroup->toSql();
        } else if ($this->whereTrue) {
            $sql .= ' WHERE 1 = 1';
        } else {
            throw new QueryException('No WHERE clause specified for delete query. To delete all records, use whereTrue() explicitly.');
        }

        return $sql;
    }
    /**
     * Explizit alle Datensätze löschen (gefährlich!)
     *
     * @return $this
     */
    public function whereTrue(): self
    {
        $this->whereTrue = true;

        // Auch in der WhereClauseGroup setzen für Konsistenz
        if ($this->whereGroup === null) {
            $this->whereGroup = new WhereClauseGroup();
        }

        $this->whereGroup->where(new RawExpression('1 = 1'));
        return $this;
    }

}