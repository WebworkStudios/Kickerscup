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

    /**
     * Fügt eine WHERE-Bedingung hinzu
     *
     * @param string $column Spalte
     * @param mixed $operator Operator oder Wert
     * @param mixed $value Wert (optional)
     * @return $this
     */
    public function where(string $column, mixed $operator, mixed $value = null): self
    {
        // Wenn nur zwei Parameter angegeben wurden, verwende = als Operator
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $paramName = $this->createParameterName('where');
        $this->parameters[$paramName] = $value;

        $this->wheres[] = "{$column} {$operator} :{$paramName}";

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
        if (empty($this->wheres)) {
            throw new QueryException('No WHERE clause specified for delete query. To delete all records, use whereTrue() explicitly.');
        }

        $sql = $this->toSql();

        return $this->getConnection()->query($sql, $this->parameters);
    }

    /**
     * Explizit alle Datensätze löschen (gefährlich!)
     *
     * @return $this
     */
    public function whereTrue(): self
    {
        $this->wheres[] = '1 = 1';
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function toSql(): string
    {
        $sql = "DELETE FROM {$this->table}";

        if (!empty($this->wheres)) {
            $sql .= ' WHERE ' . implode(' AND ', $this->wheres);
        }

        return $sql;
    }
}