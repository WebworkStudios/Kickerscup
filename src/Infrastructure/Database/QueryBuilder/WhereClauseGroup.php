<?php


declare(strict_types=1);

namespace App\Infrastructure\Database\QueryBuilder;

use Closure;

class WhereClauseGroup
{
    /**
     * @var array<string|WhereClauseGroup>
     */
    protected array $conditions = [];

    /**
     * @var array<string, mixed>
     */
    protected array $parameters = [];

    /**
     * Boolean Operator für die Verknüpfung der Bedingungen (AND oder OR)
     */
    protected string $boolean;

    /**
     * @param string $boolean Boolean Operator für die Verknüpfung der Bedingungen (AND oder OR)
     */
    public function __construct(string $boolean = 'AND')
    {
        $this->boolean = strtoupper($boolean);
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
        return $this->where($column, $operator, $value, 'OR');
    }

    /**
     * Fügt eine einfache Bedingung zu dieser Gruppe hinzu
     *
     * @param string|RawExpression $column Spalte oder Raw-Expression
     * @param mixed $operator Operator oder Wert
     * @param mixed $value Wert (optional)
     * @param string $boolean Boolean Operator für die Verknüpfung (AND oder OR)
     * @return $this
     */
    public function where(string|RawExpression $column, mixed $operator = null, mixed $value = null, string $boolean = 'AND'): self
    {
        // Wenn column eine RawExpression ist, verwende sie direkt
        if ($column instanceof RawExpression) {
            $this->parameters = array_merge($this->parameters, $column->getParameters());
            $this->conditions[] = [$column->toSql(), $boolean];
            return $this;
        }

        // Wenn nur zwei Parameter angegeben wurden, verwende = als Operator
        if ($value === null && $operator !== null) {
            $value = $operator;
            $operator = '=';
        }

        $paramName = $this->createParameterName('where');
        $this->parameters[$paramName] = $value;

        $this->conditions[] = ["{$column} {$operator} :{$paramName}", $boolean];

        return $this;
    }

    /**
     * Gibt die Parameter dieser Gruppe zurück
     *
     * @return array<string, mixed>
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    /**
     * Kompiliert die Bedingungen zu einem SQL-String
     *
     * @return string
     */
    public function toSql(): string
    {
        if (empty($this->conditions)) {
            return '';
        }

        $sql = [];

        foreach ($this->conditions as $index => [$condition, $boolean]) {
            if ($condition instanceof WhereClauseGroup) {
                $groupSql = $condition->toSql();

                if (!empty($groupSql)) {
                    $sql[] = ($index > 0 ? $boolean . ' ' : '') . '(' . $groupSql . ')';
                }
            } else {
                $sql[] = ($index > 0 ? $boolean . ' ' : '') . $condition;
            }
        }

        return implode(' ', $sql);
    }

    /**
     * Erstellt einen eindeutigen Parameternamen
     *
     * @param string $prefix Präfix für den Parameternamen
     * @return string Der generierte Parametername
     */
    protected function createParameterName(string $prefix = 'param'): string
    {
        static $counter = 0;
        return $prefix . '_' . (++$counter);
    }

    /**
     * Erstellt eine neue verschachtelte Bedingungsgruppe mit OR-Verknüpfung
     *
     * @param Closure $callback Callback-Funktion, die die neue Gruppe konfiguriert
     * @return $this
     */
    public function orWhereGroup(Closure $callback): self
    {
        return $this->whereGroup($callback, 'OR');
    }

    /**
     * Erstellt eine neue verschachtelte Bedingungsgruppe mit AND-Verknüpfung
     *
     * @param Closure $callback Callback-Funktion, die die neue Gruppe konfiguriert
     * @param string $boolean Boolean Operator für die Verknüpfung (AND oder OR)
     * @return $this
     */
    public function whereGroup(Closure $callback, string $boolean = 'AND'): self
    {
        $group = new WhereClauseGroup();

        $callback($group);

        $this->parameters = array_merge($this->parameters, $group->getParameters());
        $this->conditions[] = [$group, $boolean];

        return $this;
    }
}