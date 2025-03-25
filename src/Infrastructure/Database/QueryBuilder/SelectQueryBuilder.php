<?php


declare(strict_types=1);

namespace App\Infrastructure\Database\QueryBuilder;

use App\Infrastructure\Database\Exceptions\QueryException;

class SelectQueryBuilder extends QueryBuilder
{
    /**
     * Zu selektierende Spalten
     *
     * @var array<string>
     */
    protected array $columns = ['*'];

    /**
     * JOIN-Klauseln
     *
     * @var array<array{type: string, table: string, condition: string}>
     */
    protected array $joins = [];

    /**
     * WHERE-Bedingungen
     *
     * @var array<string>
     */
    protected array $wheres = [];

    /**
     * GROUP BY-Klauseln
     *
     * @var array<string>
     */
    protected array $groups = [];

    /**
     * HAVING-Bedingungen
     *
     * @var array<string>
     */
    protected array $havings = [];

    /**
     * ORDER BY-Klauseln
     *
     * @var array<string>
     */
    protected array $orders = [];

    /**
     * LIMIT-Klausel
     */
    protected ?int $limit = null;

    /**
     * OFFSET-Klausel
     */
    protected ?int $offset = null;

    /**
     * CTEs (Common Table Expressions) für WITH-Klauseln
     *
     * @var array<string, Subquery|SelectQueryBuilder>
     */
    protected array $ctes = [];


    protected function createParameterName(string $prefix = 'param'): string
    {
        static $counters = [];
        if (!isset($counters[$prefix])) {
            $counters[$prefix] = 0;
        }
        return $prefix . '_' . ($counters[$prefix]++);
    }

    /**
     * Setzt die zu selektierenden Spalten
     *
     * @param string|array $columns Spalten für die Abfrage
     * @return $this
     */
    public function select(string|array $columns = ['*']): self
    {
        $this->columns = is_array($columns) ? $columns : func_get_args();
        return $this;
    }

    /**
     * Fügt einen INNER JOIN hinzu
     *
     * @param string $table Tabelle für den Join
     * @param string $condition Join-Bedingung
     * @return $this
     */
    public function join(string $table, string $condition): self
    {
        return $this->addJoin('INNER', $table, $condition);
    }

    /**
     * Fügt einen LEFT JOIN hinzu
     *
     * @param string $table Tabelle für den Join
     * @param string $condition Join-Bedingung
     * @return $this
     */
    public function leftJoin(string $table, string $condition): self
    {
        return $this->addJoin('LEFT', $table, $condition);
    }

    /**
     * Fügt einen RIGHT JOIN hinzu
     *
     * @param string $table Tabelle für den Join
     * @param string $condition Join-Bedingung
     * @return $this
     */
    public function rightJoin(string $table, string $condition): self
    {
        return $this->addJoin('RIGHT', $table, $condition);
    }

    /**
     * Fügt einen Join hinzu
     *
     * @param string $type Join-Typ (INNER, LEFT, RIGHT)
     * @param string $table Tabelle für den Join
     * @param string $condition Join-Bedingung
     * @return $this
     */
    protected function addJoin(string $type, string $table, string $condition): self
    {
        $this->joins[] = [
            'type' => $type,
            'table' => $table,
            'condition' => $condition
        ];

        return $this;
    }

    /**
     * Fügt eine CTE (Common Table Expression) für WITH-Klausel hinzu
     *
     * @param string $name Name der CTE
     * @param SelectQueryBuilder|Closure $query Die Subquery oder eine Funktion, die einen SelectQueryBuilder erhält
     * @param array<string>|null $columns Optional columns for the CTE
     * @return $this
     */
    public function with(string $name, SelectQueryBuilder|\Closure $query, ?array $columns = null): self
    {
        if ($query instanceof \Closure) {
            $builder = new SelectQueryBuilder($this->connectionManager);
            $query($builder);
            $query = $builder;
        }

        // Create a Subquery instance
        $subquery = new Subquery($query, $name);

        // If columns are provided, store them with the CTE
        if ($columns !== null) {
            $subquery->withColumns($columns);
        }

        $this->ctes[$name] = $subquery;

        // Merge parameters from CTE to parent query
        $this->parameters = array_merge($this->parameters, $query->getParameters());

        return $this;
    }

    /**
     * Kompiliert den WITH-Teil der Abfrage für CTEs
     *
     * @return string
     */
    protected function compileWith(): string
    {
        if (empty($this->ctes)) {
            return '';
        }

        $expressions = [];

        foreach ($this->ctes as $name => $cte) {
            $columns = '';
            if (method_exists($cte, 'getColumns') && !empty($cte->getColumns())) {
                $columns = '(' . implode(', ', $cte->getColumns()) . ')';
            }

            if ($cte instanceof Subquery) {
                $expressions[] = $name . $columns . ' AS (' . $cte->getBuilder()->toSql() . ')';
            } else {
                $expressions[] = $name . $columns . ' AS (' . $cte->toSql() . ')';
            }
        }

        return 'WITH ' . implode(', ', $expressions);
    }

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

        // Für kleinere Arrays können OR-Bedingungen performanter sein als IN-Klauseln
        if (count($values) <= 3) {
            $conditions = [];
            foreach ($values as $value) {
                $paramName = $this->createParameterName('wherein');
                $this->parameters[$paramName] = $value;
                $conditions[] = "{$column} = :{$paramName}";
            }
            $this->wheres[] = "(" . implode(' OR ', $conditions) . ")";
            return $this;
        }

        // Standard IN-Klausel für größere Arrays
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
     * Fügt eine WHERE NOT IN-Bedingung hinzu
     *
     * @param string $column Spalte
     * @param array $values Werte
     * @return $this
     */
    public function whereNotIn(string $column, array $values): self
    {
        if (empty($values)) {
            return $this; // Keine Einschränkung, wenn keine Werte angegeben
        }

        // Für kleinere Arrays können AND-Bedingungen performanter sein als NOT IN-Klauseln
        if (count($values) <= 3) {
            $conditions = [];
            foreach ($values as $value) {
                $paramName = $this->createParameterName('wherenotin');
                $this->parameters[$paramName] = $value;
                $conditions[] = "{$column} != :{$paramName}";
            }
            $this->wheres[] = "(" . implode(' AND ', $conditions) . ")";
            return $this;
        }

        // Standard NOT IN-Klausel für größere Arrays
        $placeholders = [];
        foreach ($values as $value) {
            $paramName = $this->createParameterName('wherenotin');
            $this->parameters[$paramName] = $value;
            $placeholders[] = ":{$paramName}";
        }

        $this->wheres[] = "{$column} NOT IN (" . implode(', ', $placeholders) . ")";
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
     * Fügt eine WHERE BETWEEN-Bedingung hinzu
     *
     * @param string $column Spalte
     * @param mixed $min Minimalwert
     * @param mixed $max Maximalwert
     * @return $this
     */
    public function whereBetween(string $column, mixed $min, mixed $max): self
    {
        $minParam = $this->createParameterName('min');
        $maxParam = $this->createParameterName('max');

        $this->parameters[$minParam] = $min;
        $this->parameters[$maxParam] = $max;

        $this->wheres[] = "{$column} BETWEEN :{$minParam} AND :{$maxParam}";

        return $this;
    }

    /**
     * Fügt eine GROUP BY-Klausel hinzu
     *
     * @param string|array $columns Spalten für GROUP BY
     * @return $this
     */
    public function groupBy(string|array $columns): self
    {
        $this->groups = array_merge(
            $this->groups,
            is_array($columns) ? $columns : [$columns]
        );

        return $this;
    }

    /**
     * Fügt mehrere Spalten zur GROUP BY-Klausel hinzu und optimiert die Verarbeitung
     * für Anwendungsfälle mit mehreren Gruppierungskriterien.
     *
     * Diese Methode ist speziell für den Fall konzipiert, dass mehrere Spalten auf einmal
     * gruppiert werden sollen. Sie validiert die Eingabe und stellt sicher, dass keine
     * leeren Spaltennamen verwendet werden.
     *
     * @param array<string> $columns Ein Array von Spaltennamen für die Gruppierung
     * @return $this Für Method-Chaining
     * @throws QueryException Wenn leere Spaltennamen im Array enthalten sind
     *
     * @example
     * // Gruppiere nach mehreren Spalten
     * $query->groupByMultiple(['department', 'position', 'hire_year']);
     *
     * // Entspricht, ist aber lesbarer als:
     * $query->groupBy('department')->groupBy('position')->groupBy('hire_year');
     * // oder
     * $query->groupBy(['department', 'position', 'hire_year']);
     */
    public function groupByMultiple(array $columns): self
    {
        if (array_any($columns, fn($col) => empty($col))) {
            throw new QueryException("Empty column name in groupBy");
        }

        $this->groups = array_merge($this->groups, $columns);
        return $this;
    }

    /**
     * Fügt eine HAVING-Bedingung hinzu
     *
     * @param string $column Spalte
     * @param mixed $operator Operator oder Wert
     * @param mixed $value Wert (optional)
     * @return $this
     */
    public function having(string $column, mixed $operator, mixed $value = null): self
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $paramName = $this->createParameterName('having');
        $this->parameters[$paramName] = $value;

        $this->havings[] = "{$column} {$operator} :{$paramName}";

        return $this;
    }

    /**
     * Fügt eine ORDER BY-Klausel hinzu
     *
     * @param string $column Spalte
     * @param string $direction Sortierrichtung (ASC, DESC)
     * @return $this
     */
    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $direction = strtoupper($direction);

        if (!in_array($direction, ['ASC', 'DESC'])) {
            $direction = 'ASC';
        }

        $this->orders[] = "{$column} {$direction}";

        return $this;
    }

    /**
     * Fügt eine LIMIT-Klausel hinzu
     *
     * @param int $limit Limit
     * @return $this
     */
    public function limit(int $limit): self
    {
        $this->limit = max(0, $limit);
        return $this;
    }

    /**
     * Fügt eine OFFSET-Klausel hinzu
     *
     * @param int $offset Offset
     * @return $this
     */
    public function offset(int $offset): self
    {
        $this->offset = max(0, $offset);
        return $this;
    }

    /**
     * Setzt LIMIT und OFFSET für Paginierung
     *
     * @param int $page Seitennummer (beginnt bei 1)
     * @param int $perPage Einträge pro Seite
     * @return $this
     */
    public function paginate(int $page, int $perPage): self
    {
        $page = max(1, $page);
        $perPage = max(1, $perPage);

        $this->limit($perPage);
        $this->offset(($page - 1) * $perPage);

        return $this;
    }

    /**
     * Führt die Abfrage aus und gibt das erste Ergebnis zurück
     *
     * @return array|null Die erste Zeile oder null
     */
    public function first(): ?array
    {
        $this->limit(1);
        return $this->getConnection()->queryFirst($this->toSql(), $this->parameters);
    }

    /**
     * Führt die Abfrage aus und gibt alle Ergebnisse zurück
     *
     * @return array Die Ergebniszeilen
     */
    public function get(): array
    {
        return $this->getConnection()->queryAll($this->toSql(), $this->parameters);
    }

    /**
     * Zählt die Anzahl der Ergebnisse
     *
     * @param string $column Spalte für die Zählung (Standard: *)
     * @return int Die Anzahl der Ergebnisse
     */
    public function count(string $column = '*'): int
    {
        $original = $this->columns;

        $this->columns = ["COUNT({$column}) as aggregate"];

        $result = $this->first();

        $this->columns = $original;

        return (int)($result['aggregate'] ?? 0);
    }

    /**
     * Berechnet die Summe einer Spalte
     *
     * @param string $column Spalte für die Summe
     * @return float|int Die Summe der Spalte
     */
    public function sum(string $column): float|int
    {
        $original = $this->columns;

        $this->columns = ["SUM({$column}) as aggregate"];

        $result = $this->first();

        $this->columns = $original;

        return $result['aggregate'] ?? 0;
    }

    /**
     * Berechnet den Durchschnitt einer Spalte
     *
     * @param string $column Spalte für den Durchschnitt
     * @return float|int Der Durchschnitt der Spalte
     */
    public function avg(string $column): float|int
    {
        $original = $this->columns;

        $this->columns = ["AVG({$column}) as aggregate"];

        $result = $this->first();

        $this->columns = $original;

        return $result['aggregate'] ?? 0;
    }

    /**
     * Ermittelt den Minimalwert einer Spalte
     *
     * @param string $column Spalte für den Minimalwert
     * @return mixed Der Minimalwert der Spalte
     */
    public function min(string $column): mixed
    {
        $original = $this->columns;

        $this->columns = ["MIN({$column}) as aggregate"];

        $result = $this->first();

        $this->columns = $original;

        return $result['aggregate'] ?? null;
    }

    /**
     * Ermittelt den Maximalwert einer Spalte
     *
     * @param string $column Spalte für den Maximalwert
     * @return mixed Der Maximalwert der Spalte
     */
    public function max(string $column): mixed
    {
        $original = $this->columns;

        $this->columns = ["MAX({$column}) as aggregate"];

        $result = $this->first();

        $this->columns = $original;

        return $result['aggregate'] ?? null;
    }

    /**
     * {@inheritdoc}
     */
    public function toSql(): string
    {
        $withClause = $this->compileWith();

        $mainQuery = $this->compileSelect()
            . $this->compileFrom()
            . $this->compileJoins()
            . $this->compileWheres()
            . $this->compileGroups()
            . $this->compileHavings()
            . $this->compileOrders()
            . $this->compileLimit()
            . $this->compileOffset();

        return $withClause ? $withClause . ' ' . $mainQuery : $mainQuery;
    }

    /**
     * Erstellt eine Subquery
     *
     * @param \Closure $callback Eine Funktion, die einen SelectQueryBuilder erhält
     * @param string $alias Alias für die Subquery
     * @return Subquery Die erstellte Subquery
     */
    public function subquery(\Closure $callback, string $alias): Subquery
    {
        $builder = new SelectQueryBuilder($this->connectionManager);
        $callback($builder);

        // Merge parameters from subquery to parent query
        $this->parameters = array_merge($this->parameters, $builder->getParameters());

        return new Subquery($builder, $alias);
    }

    /**
     * Fügt eine EXISTS-Bedingung hinzu
     *
     * @param SelectQueryBuilder|Closure $query Die Subquery oder eine Funktion, die einen SelectQueryBuilder erhält
     * @return $this
     */
    public function whereExists(SelectQueryBuilder|\Closure $query): self
    {
        if ($query instanceof \Closure) {
            $builder = new SelectQueryBuilder($this->connectionManager);
            $query($builder);
            $query = $builder;
        }

        // Merge parameters from subquery to parent query
        $this->parameters = array_merge($this->parameters, $query->getParameters());

        $this->wheres[] = "EXISTS (" . $query->toSql() . ")";

        return $this;
    }

    /**
     * Fügt eine NOT EXISTS-Bedingung hinzu
     *
     * @param SelectQueryBuilder|Closure $query Die Subquery oder eine Funktion, die einen SelectQueryBuilder erhält
     * @return $this
     */
    public function whereNotExists(SelectQueryBuilder|\Closure $query): self
    {
        if ($query instanceof \Closure) {
            $builder = new SelectQueryBuilder($this->connectionManager);
            $query($builder);
            $query = $builder;
        }

        // Merge parameters from subquery to parent query
        $this->parameters = array_merge($this->parameters, $query->getParameters());

        $this->wheres[] = "NOT EXISTS (" . $query->toSql() . ")";

        return $this;
    }

    /**
     * Kompiliert den SELECT-Teil der Abfrage
     *
     * @return string
     */
    protected function compileSelect(): string
    {
        return 'SELECT ' . implode(', ', $this->columns);
    }

    /**
     * Kompiliert den FROM-Teil der Abfrage
     *
     * @return string
     */
    protected function compileFrom(): string
    {
        return ' FROM ' . $this->table;
    }

    /**
     * Kompiliert die JOIN-Teile der Abfrage
     *
     * @return string
     */
    protected function compileJoins(): string
    {
        if (empty($this->joins)) {
            return '';
        }

        $sql = '';

        foreach ($this->joins as $join) {
            $sql .= ' ' . $join['type'] . ' JOIN ' . $join['table'] . ' ON ' . $join['condition'];
        }

        return $sql;
    }

    /**
     * Kompiliert den WHERE-Teil der Abfrage
     *
     * @return string
     */
    protected function compileWheres(): string
    {
        if (empty($this->wheres)) {
            return '';
        }

        return ' WHERE ' . implode(' AND ', $this->wheres);
    }

    /**
     * Kompiliert den GROUP BY-Teil der Abfrage
     *
     * @return string
     */
    protected function compileGroups(): string
    {
        if (empty($this->groups)) {
            return '';
        }

        return ' GROUP BY ' . implode(', ', $this->groups);
    }

    /**
     * Kompiliert den HAVING-Teil der Abfrage
     *
     * @return string
     */
    protected function compileHavings(): string
    {
        if (empty($this->havings)) {
            return '';
        }

        return ' HAVING ' . implode(' AND ', $this->havings);
    }

    /**
     * Kompiliert den ORDER BY-Teil der Abfrage
     *
     * @return string
     */
    protected function compileOrders(): string
    {
        if (empty($this->orders)) {
            return '';
        }

        return ' ORDER BY ' . implode(', ', $this->orders);
    }

    /**
     * Kompiliert den LIMIT-Teil der Abfrage
     *
     * @return string
     */
    protected function compileLimit(): string
    {
        if ($this->limit === null) {
            return '';
        }

        return ' LIMIT ' . $this->limit;
    }

    /**
     * Kompiliert den OFFSET-Teil der Abfrage
     *
     * @return string
     */
    protected function compileOffset(): string
    {
        if ($this->offset === null) {
            return '';
        }

        return ' OFFSET ' . $this->offset;
    }
}