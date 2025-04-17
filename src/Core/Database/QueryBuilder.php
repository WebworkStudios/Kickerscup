<?php
declare(strict_types=1);

namespace App\Core\Database;

/**
 * QueryBuilder für SQL-Abfragen
 */
class QueryBuilder
{
    /**
     * Komponenten der SQL-Abfrage
     */
    private array $components = [
        'select' => ['*'],
        'from' => null,
        'join' => [],
        'where' => [],
        'groupBy' => [],
        'having' => [],
        'orderBy' => [],
        'limit' => null,
        'offset' => null,
    ];

    /**
     * Parameter für die Abfrage
     */
    private array $params = [];

    /**
     * Operator für WHERE-Bedingungen
     */
    private string $whereOperator = 'AND';

    /**
     * Operator für HAVING-Bedingungen
     */
    private string $havingOperator = 'AND';

    /**
     * Konstruktor
     *
     * @param Connection $connection Datenbankverbindung
     * @param string $table Tabellenname
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly string     $table
    )
    {
        $this->components['from'] = $table;
    }

    /**
     * Fügt eine WHERE-Bedingung mit OR hinzu
     *
     * @param string $column Spalte
     * @param string $operator Operator
     * @param mixed $value Wert
     * @return self
     */
    public function orWhere(string $column, string $operator, mixed $value): self
    {
        $this->whereOperator = 'OR';

        $result = $this->where($column, $operator, $value);

        $this->whereOperator = 'AND';

        return $result;
    }

    /**
     * Fügt eine WHERE-Bedingung hinzu
     *
     * @param string $column Spalte
     * @param string $operator Operator
     * @param mixed $value Wert
     * @return self
     */
    public function where(string $column, string $operator, mixed $value): self
    {
        $this->components['where'][] = [
            'type' => 'basic',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => $this->whereOperator,
        ];

        return $this;
    }

    /**
     * Fügt eine WHERE IN-Bedingung mit OR hinzu
     *
     * @param string $column Spalte
     * @param array $values Werte
     * @return self
     */
    public function orWhereIn(string $column, array $values): self
    {
        $this->whereOperator = 'OR';

        $result = $this->whereIn($column, $values);

        $this->whereOperator = 'AND';

        return $result;
    }

    /**
     * Fügt eine WHERE IN-Bedingung hinzu
     *
     * @param string $column Spalte
     * @param array $values Werte
     * @return self
     */
    public function whereIn(string $column, array $values): self
    {
        $this->components['where'][] = [
            'type' => 'in',
            'column' => $column,
            'values' => $values,
            'boolean' => $this->whereOperator,
        ];

        return $this;
    }

    /**
     * Fügt eine WHERE NOT IN-Bedingung mit OR hinzu
     *
     * @param string $column Spalte
     * @param array $values Werte
     * @return self
     */
    public function orWhereNotIn(string $column, array $values): self
    {
        $this->whereOperator = 'OR';

        $result = $this->whereNotIn($column, $values);

        $this->whereOperator = 'AND';

        return $result;
    }

    /**
     * Fügt eine WHERE NOT IN-Bedingung hinzu
     *
     * @param string $column Spalte
     * @param array $values Werte
     * @return self
     */
    public function whereNotIn(string $column, array $values): self
    {
        $this->components['where'][] = [
            'type' => 'notIn',
            'column' => $column,
            'values' => $values,
            'boolean' => $this->whereOperator,
        ];

        return $this;
    }

    /**
     * Fügt eine WHERE NULL-Bedingung mit OR hinzu
     *
     * @param string $column Spalte
     * @return self
     */
    public function orWhereNull(string $column): self
    {
        $this->whereOperator = 'OR';

        $result = $this->whereNull($column);

        $this->whereOperator = 'AND';

        return $result;
    }

    /**
     * Fügt eine WHERE NULL-Bedingung hinzu
     *
     * @param string $column Spalte
     * @return self
     */
    public function whereNull(string $column): self
    {
        $this->components['where'][] = [
            'type' => 'null',
            'column' => $column,
            'boolean' => $this->whereOperator,
        ];

        return $this;
    }

    /**
     * Fügt eine WHERE NOT NULL-Bedingung mit OR hinzu
     *
     * @param string $column Spalte
     * @return self
     */
    public function orWhereNotNull(string $column): self
    {
        $this->whereOperator = 'OR';

        $result = $this->whereNotNull($column);

        $this->whereOperator = 'AND';

        return $result;
    }

    /**
     * Fügt eine WHERE NOT NULL-Bedingung hinzu
     *
     * @param string $column Spalte
     * @return self
     */
    public function whereNotNull(string $column): self
    {
        $this->components['where'][] = [
            'type' => 'notNull',
            'column' => $column,
            'boolean' => $this->whereOperator,
        ];

        return $this;
    }

    /**
     * Fügt eine WHERE BETWEEN-Bedingung mit OR hinzu
     *
     * @param string $column Spalte
     * @param mixed $min Minimalwert
     * @param mixed $max Maximalwert
     * @return self
     */
    public function orWhereBetween(string $column, mixed $min, mixed $max): self
    {
        $this->whereOperator = 'OR';

        $result = $this->whereBetween($column, $min, $max);

        $this->whereOperator = 'AND';

        return $result;
    }

    /**
     * Fügt eine WHERE BETWEEN-Bedingung hinzu
     *
     * @param string $column Spalte
     * @param mixed $min Minimalwert
     * @param mixed $max Maximalwert
     * @return self
     */
    public function whereBetween(string $column, mixed $min, mixed $max): self
    {
        $this->components['where'][] = [
            'type' => 'between',
            'column' => $column,
            'min' => $min,
            'max' => $max,
            'boolean' => $this->whereOperator,
        ];

        return $this;
    }

    /**
     * Fügt eine LEFT JOIN-Klausel hinzu
     *
     * @param string $table Tabellenname
     * @param string $first Erste Spalte
     * @param string $operator Operator
     * @param string $second Zweite Spalte
     * @return self
     */
    public function leftJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->join($table, $first, $operator, $second, 'LEFT');
    }

    /**
     * Fügt eine JOIN-Klausel hinzu
     *
     * @param string $table Tabellenname
     * @param string $first Erste Spalte
     * @param string $operator Operator
     * @param string $second Zweite Spalte
     * @param string $type Typ des Joins (INNER, LEFT, RIGHT, etc.)
     * @return self
     */
    public function join(
        string $table,
        string $first,
        string $operator,
        string $second,
        string $type = 'INNER'
    ): self
    {
        $this->components['join'][] = [
            'table' => $table,
            'first' => $first,
            'operator' => $operator,
            'second' => $second,
            'type' => $type,
        ];

        return $this;
    }

    /**
     * Fügt eine RIGHT JOIN-Klausel hinzu
     *
     * @param string $table Tabellenname
     * @param string $first Erste Spalte
     * @param string $operator Operator
     * @param string $second Zweite Spalte
     * @return self
     */
    public function rightJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->join($table, $first, $operator, $second, 'RIGHT');
    }

    /**
     * Fügt eine GROUP BY-Klausel hinzu
     *
     * @param string ...$columns Spalten
     * @return self
     */
    public function groupBy(string ...$columns): self
    {
        $this->components['groupBy'] = array_merge($this->components['groupBy'], $columns);

        return $this;
    }

    /**
     * Fügt eine HAVING-Bedingung mit OR hinzu
     *
     * @param string $column Spalte
     * @param string $operator Operator
     * @param mixed $value Wert
     * @return self
     */
    public function orHaving(string $column, string $operator, mixed $value): self
    {
        $this->havingOperator = 'OR';

        $result = $this->having($column, $operator, $value);

        $this->havingOperator = 'AND';

        return $result;
    }

    /**
     * Fügt eine HAVING-Bedingung hinzu
     *
     * @param string $column Spalte
     * @param string $operator Operator
     * @param mixed $value Wert
     * @return self
     */
    public function having(string $column, string $operator, mixed $value): self
    {
        $this->components['having'][] = [
            'type' => 'basic',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => $this->havingOperator,
        ];

        return $this;
    }

    /**
     * Fügt eine ORDER BY DESC-Klausel hinzu
     *
     * @param string $column Spalte
     * @return self
     */
    public function orderByDesc(string $column): self
    {
        return $this->orderBy($column, 'DESC');
    }

    /**
     * Fügt eine ORDER BY-Klausel hinzu
     *
     * @param string $column Spalte
     * @param string $direction Richtung (ASC oder DESC)
     * @return self
     */
    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $direction = strtoupper($direction);

        if (!in_array($direction, ['ASC', 'DESC'])) {
            $direction = 'ASC';
        }

        $this->components['orderBy'][] = [
            'column' => $column,
            'direction' => $direction,
        ];

        return $this;
    }

    /**
     * Kombiniert LIMIT und OFFSET
     *
     * @param int $page Seite
     * @param int $perPage Einträge pro Seite
     * @return self
     */
    public function forPage(int $page, int $perPage): self
    {
        return $this->offset(($page - 1) * $perPage)->limit($perPage);
    }

    /**
     * Setzt eine LIMIT-Klausel
     *
     * @param int $limit Limit
     * @return self
     */
    public function limit(int $limit): self
    {
        $this->components['limit'] = $limit;

        return $this;
    }

    /**
     * Setzt eine OFFSET-Klausel
     *
     * @param int $offset Offset
     * @return self
     */
    public function offset(int $offset): self
    {
        $this->components['offset'] = $offset;

        return $this;
    }

    /**
     * Baut die WHERE-Klausel für die Abfrage
     *
     * @return array [string, array] Die WHERE-Klausel und die Parameter
     */
    private function buildWhereClause(): array
    {
        if (empty($this->components['where'])) {
            return ['', []];
        }

        $whereClauses = [];
        $params = [];

        foreach ($this->components['where'] as $i => $where) {
            $prefix = $i === 0 ? '' : " {$where['boolean']} ";

            match ($where['type']) {
                'basic' => function() use (&$whereClauses, &$params, $prefix, $where, $i) {
                    $key = "where_{$i}";
                    $whereClauses[] = "{$prefix}{$where['column']} {$where['operator']} :{$key}";
                    $params[$key] = $where['value'];
                },
                'in' => function() use (&$whereClauses, &$params, $prefix, $where, $i) {
                    if (empty($where['values'])) {
                        $whereClauses[] = "{$prefix}1 = 0"; // Immer falsch, wenn leeres IN
                        return;
                    }

                    $placeholders = [];
                    foreach ($where['values'] as $j => $value) {
                        $key = "where_{$i}_{$j}";
                        $placeholders[] = ":{$key}";
                        $params[$key] = $value;
                    }

                    $whereClauses[] = "{$prefix}{$where['column']} IN (" . implode(', ', $placeholders) . ")";
                },
                'notIn' => function() use (&$whereClauses, &$params, $prefix, $where, $i) {
                    if (empty($where['values'])) {
                        $whereClauses[] = "{$prefix}1 = 1"; // Immer wahr, wenn leeres NOT IN
                        return;
                    }

                    $placeholders = [];
                    foreach ($where['values'] as $j => $value) {
                        $key = "where_{$i}_{$j}";
                        $placeholders[] = ":{$key}";
                        $params[$key] = $value;
                    }

                    $whereClauses[] = "{$prefix}{$where['column']} NOT IN (" . implode(', ', $placeholders) . ")";
                },
                'null' => function() use (&$whereClauses, $prefix, $where) {
                    $whereClauses[] = "{$prefix}{$where['column']} IS NULL";
                },
                'notNull' => function() use (&$whereClauses, $prefix, $where) {
                    $whereClauses[] = "{$prefix}{$where['column']} IS NOT NULL";
                },
                'between' => function() use (&$whereClauses, &$params, $prefix, $where, $i) {
                    $minKey = "where_{$i}_min";
                    $maxKey = "where_{$i}_max";
                    $whereClauses[] = "{$prefix}{$where['column']} BETWEEN :{$minKey} AND :{$maxKey}";
                    $params[$minKey] = $where['min'];
                    $params[$maxKey] = $where['max'];
                },
                default => null
            }();
        }

        return [implode(' ', $whereClauses), $params];
    }

    /**
     * Generiert die SQL-Abfrage
     *
     * @return string
     */
    public function toSql(): string
    {
        // SELECT
        $query = 'SELECT ' . implode(', ', $this->components['select']);

        // FROM
        $query .= ' FROM ' . $this->components['from'];

        // JOINs
        if (!empty($this->components['join'])) {
            foreach ($this->components['join'] as $join) {
                $query .= sprintf(
                    ' %s JOIN %s ON %s %s %s',
                    $join['type'],
                    $join['table'],
                    $join['first'],
                    $join['operator'],
                    $join['second']
                );
            }
        }

        // WHERE
        [$where, $whereParams] = $this->buildWhereClause();

        if ($where) {
            $query .= " WHERE $where";
            $this->params = array_merge($this->params, $whereParams);
        }

        // GROUP BY
        if (!empty($this->components['groupBy'])) {
            $query .= ' GROUP BY ' . implode(', ', $this->components['groupBy']);
        }

        // HAVING
        if (!empty($this->components['having'])) {
            $havingClauses = [];
            $havingParams = [];

            foreach ($this->components['having'] as $i => $having) {
                $key = "having_{$i}";
                $prefix = $i === 0 ? '' : " {$having['boolean']} ";

                if ($having['type'] === 'basic') {
                    $havingClauses[] = "{$prefix}{$having['column']} {$having['operator']} :{$key}";
                    $havingParams[$key] = $having['value'];
                }
            }

            if (!empty($havingClauses)) {
                $query .= ' HAVING ' . implode('', $havingClauses);
                $this->params = array_merge($this->params, $havingParams);
            }
        }

        // ORDER BY
        if (!empty($this->components['orderBy'])) {
            $orderClauses = [];

            foreach ($this->components['orderBy'] as $order) {
                $orderClauses[] = "{$order['column']} {$order['direction']}";
            }

            $query .= ' ORDER BY ' . implode(', ', $orderClauses);
        }

        // LIMIT & OFFSET
        if ($this->components['limit'] !== null) {
            $query .= " LIMIT {$this->components['limit']}";
        }

        if ($this->components['offset'] !== null) {
            $query .= " OFFSET {$this->components['offset']}";
        }

        return $query;
    }

    /**
     * Gibt alle Parameter für die Abfrage zurück
     *
     * @return array
     */
    public function getBindings(): array
    {
        return $this->params;
    }

    /**
     * Führt die Abfrage aus und gibt die erste Spalte des ersten Ergebnisses zurück
     *
     * @param string $column Spalte
     * @return mixed
     */
    public function value(string $column): mixed
    {
        $result = $this->first([$column]);

        return $result ? $result[$column] : null;
    }

    /**
     * Führt die Abfrage aus und gibt das erste Ergebnis zurück
     *
     * @param string[]|null $columns Spalten
     * @return array|false
     */
    public function first(?array $columns = null): array|false
    {
        if ($columns !== null) {
            $this->select(...$columns);
        }

        $result = $this->limit(1)->get();

        return $result ? $result[0] : false;
    }

    /**
     * Setzt die SELECT-Klausel
     *
     * @param string ...$columns Spalten
     * @return self
     */
    public function select(string ...$columns): self
    {
        $this->components['select'] = $columns;

        return $this;
    }

    /**
     * Führt die Abfrage aus und gibt alle Ergebnisse zurück
     *
     * @param string[]|null $columns Spalten
     * @return array
     */
    public function get(?array $columns = null): array
    {
        if ($columns !== null) {
            $this->select(...$columns);
        }

        return $this->connection->select(
            $this->toSql(),
            $this->getBindings()
        );
    }

    /**
     * Führt die Abfrage aus und gibt die Anzahl der Ergebnisse zurück
     *
     * @param string $column Spalte
     * @return int
     */
    public function count(string $column = '*'): int
    {
        $this->components['select'] = ["COUNT($column) as count"];

        $result = $this->first();

        return (int)($result['count'] ?? 0);
    }

    /**
     * Führt ein INSERT aus
     *
     * @param array $data Daten
     * @return int Letzte eingefügte ID
     */
    public function insert(array $data): int
    {
        return $this->connection->insert($this->table, $data);
    }

    /**
     * Führt mehrere INSERTs aus
     *
     * @param array $data Daten
     * @return bool
     */
    public function insertMany(array $data): bool
    {
        if (empty($data)) {
            return true;
        }

        $first = reset($data);
        $columns = array_keys($first);

        $query = sprintf(
            'INSERT INTO %s (%s) VALUES ',
            $this->table,
            implode(', ', $columns)
        );

        $valuePlaceholders = [];
        $values = [];

        foreach ($data as $i => $row) {
            $rowPlaceholders = [];

            foreach ($columns as $column) {
                $key = ":{$column}_{$i}";
                $rowPlaceholders[] = $key;
                $values[$key] = $row[$column] ?? null;
            }

            $valuePlaceholders[] = '(' . implode(', ', $rowPlaceholders) . ')';
        }

        $query .= implode(', ', $valuePlaceholders);

        $this->connection->query($query, $values);

        return true;
    }

    /**
     * Führt ein UPDATE aus
     *
     * @param array $data Daten
     * @return int Anzahl der geänderten Zeilen
     */
    public function update(array $data): int
    {
        $query = sprintf('UPDATE %s SET ', $this->table);

        $set = [];
        $params = [];

        foreach ($data as $column => $value) {
            $set[] = "$column = :update_$column";
            $params["update_$column"] = $value;
        }

        $query .= implode(', ', $set);

        // WHERE-Klausel hinzufügen
        [$where, $whereParams] = $this->buildWhereClause();

        if ($where) {
            $query .= " WHERE $where";
            $params = array_merge($params, $whereParams);
        }

        $statement = $this->connection->query($query, $params);

        return $statement->rowCount();
    }

    /**
     * Führt ein DELETE aus
     *
     * @return int Anzahl der gelöschten Zeilen
     */
    public function delete(): int
    {
        $query = sprintf('DELETE FROM %s', $this->table);

        // WHERE-Klausel hinzufügen
        [$where, $params] = $this->buildWhereClause();

        if ($where) {
            $query .= " WHERE $where";
        }

        $statement = $this->connection->query($query, $params);

        return $statement->rowCount();
    }


    /**
     * Berechnet die Summe einer Spalte
     *
     * @param string $column Spalte
     * @return int|float|null
     */
    public function sum(string $column): int|float|null
    {
        return $this->aggregate('SUM', $column);
    }

    /**
     * Berechnet den Durchschnitt einer Spalte
     *
     * @param string $column Spalte
     * @return int|float|null
     */
    public function avg(string $column): int|float|null
    {
        return $this->aggregate('AVG', $column);
    }

    /**
     * Ermittelt den Minimalwert einer Spalte
     *
     * @param string $column Spalte
     * @return mixed
     */
    public function min(string $column): mixed
    {
        return $this->aggregate('MIN', $column);
    }

    /**
     * Ermittelt den Maximalwert einer Spalte
     *
     * @param string $column Spalte
     * @return mixed
     */
    public function max(string $column): mixed
    {
        return $this->aggregate('MAX', $column);
    }

    /**
     * Führt eine Aggregatfunktion auf einer Spalte aus
     *
     * @param string $function SQL-Funktion (SUM, AVG, MIN, MAX, etc.)
     * @param string $column Spalte
     * @return mixed
     */
    protected function aggregate(string $function, string $column): mixed
    {
        $this->components['select'] = ["$function($column) as aggregate"];

        $result = $this->first();

        return $result ? $result['aggregate'] : null;
    }

    /**
     * Selektiert eine Spalte mit einer SQL-Funktion
     *
     * @param string $function SQL-Funktion
     * @param string $column Spalte
     * @param string|null $alias Alias für das Ergebnis
     * @return self
     */
    public function selectRaw(string $function, string $column, ?string $alias = null): self
    {
        $expression = "$function($column)";

        if ($alias !== null) {
            $expression .= " as $alias";
        }

        if (in_array('*', $this->components['select'])) {
            $this->components['select'] = [$expression];
        } else {
            $this->components['select'][] = $expression;
        }

        return $this;
    }

    /**
     * Selektiert mehrere SQL-Funktionen
     *
     * @param array $functions Array mit SQL-Funktionen und Spalten
     *                         Format: [['function' => 'SUM', 'column' => 'price', 'alias' => 'total'], ...]
     * @return self
     */
    public function selectFunctions(array $functions): self
    {
        $selections = [];

        foreach ($functions as $function) {
            $expression = "{$function['function']}({$function['column']})";

            if (isset($function['alias'])) {
                $expression .= " as {$function['alias']}";
            }

            $selections[] = $expression;
        }

        $this->components['select'] = $selections;

        return $this;
    }

    /**
     * Ermittelt die Anzahl eindeutiger Werte in einer Spalte
     *
     * @param string $column Spalte
     * @return int
     */
    public function countDistinct(string $column): int
    {
        $this->components['select'] = ["COUNT(DISTINCT $column) as count"];

        $result = $this->first();

        return (int)($result['count'] ?? 0);
    }

    /**
     * Führt die Abfrage aus und gibt die Anzahl der Ergebnisse zurück
     *
     * Überschriebene Methode, jetzt mit Unterstützung für DISTINCT
     *
     * @param string $column Spalte
     * @param bool $distinct Nur eindeutige Werte zählen
     * @return int
     */
    public function count(string $column = '*', bool $distinct = false): int
    {
        $columnExpression = $distinct ? "DISTINCT $column" : $column;
        $this->components['select'] = ["COUNT($columnExpression) as count"];

        $result = $this->first();

        return (int)($result['count'] ?? 0);
    }

    /**
     * Fügt ein CONCAT für mehrere Spalten hinzu
     *
     * @param array $columns Zu verkettende Spalten
     * @param string $separator Trennzeichen zwischen den Spalten
     * @param string|null $alias Alias für das Ergebnis
     * @return self
     */
    public function selectConcat(array $columns, string $separator = ' ', ?string $alias = null): self
    {
        $quotedSeparator = "'" . addslashes($separator) . "'";
        $expression = "CONCAT_WS($quotedSeparator, " . implode(', ', $columns) . ")";

        if ($alias !== null) {
            $expression .= " as $alias";
        }

        if (in_array('*', $this->components['select'])) {
            $this->components['select'] = [$expression];
        } else {
            $this->components['select'][] = $expression;
        }

        return $this;
    }

    /**
     * Fügt eine CASE WHEN-Abfrage hinzu
     *
     * @param string $column Zu prüfende Spalte
     * @param array $cases Array mit Bedingungen und Ergebnissen
     *                     Format: [['when' => 'value1', 'then' => 'result1'], ...]
     * @param mixed $else Ergebnis, wenn keine Bedingung zutrifft
     * @param string|null $alias Alias für das Ergebnis
     * @return self
     */
    public function selectCase(string $column, array $cases, mixed $else = null, ?string $alias = null): self
    {
        $expression = "CASE $column";

        foreach ($cases as $case) {
            $whenValue = is_string($case['when']) ? "'" . addslashes($case['when']) . "'" : $case['when'];
            $thenValue = is_string($case['then']) ? "'" . addslashes($case['then']) . "'" : $case['then'];
            $expression .= " WHEN $whenValue THEN $thenValue";
        }

        if ($else !== null) {
            $elseValue = is_string($else) ? "'" . addslashes($else) . "'" : $else;
            $expression .= " ELSE $elseValue";
        }

        $expression .= " END";

        if ($alias !== null) {
            $expression .= " as $alias";
        }

        if (in_array('*', $this->components['select'])) {
            $this->components['select'] = [$expression];
        } else {
            $this->components['select'][] = $expression;
        }

        return $this;
    }

    /**
     * Selektiert mit COALESCE, um NULL-Werte zu ersetzen
     *
     * @param string $column Hauptspalte
     * @param mixed $default Ersatzwert, wenn die Spalte NULL ist
     * @param string|null $alias Alias für das Ergebnis
     * @return self
     */
    public function selectCoalesce(string $column, mixed $default, ?string $alias = null): self
    {
        $defaultValue = is_string($default) ? "'" . addslashes($default) . "'" : $default;
        $expression = "COALESCE($column, $defaultValue)";

        if ($alias !== null) {
            $expression .= " as $alias";
        }

        if (in_array('*', $this->components['select'])) {
            $this->components['select'] = [$expression];
        } else {
            $this->components['select'][] = $expression;
        }

        return $this;
    }

    /**
     * Selektiert ein JSON-Feld (MySQL JSON Extension)
     *
     * @param string $column JSON-Spalte
     * @param string $path JSON-Pfad (z.B. '$.name')
     * @param string|null $alias Alias für das Ergebnis
     * @return self
     */
    public function selectJson(string $column, string $path, ?string $alias = null): self
    {
        $expression = "JSON_EXTRACT($column, '$path')";

        if ($alias !== null) {
            $expression .= " as $alias";
        }

        if (in_array('*', $this->components['select'])) {
            $this->components['select'] = [$expression];
        } else {
            $this->components['select'][] = $expression;
        }

        return $this;
    }

    /**
     * Selektiert mit DATE_FORMAT für Datum-Formatierung
     *
     * @param string $column Datum-Spalte
     * @param string $format MySQL DATE_FORMAT Format
     * @param string|null $alias Alias für das Ergebnis
     * @return self
     */
    public function selectDateFormat(string $column, string $format, ?string $alias = null): self
    {
        $expression = "DATE_FORMAT($column, '$format')";

        if ($alias !== null) {
            $expression .= " as $alias";
        }

        if (in_array('*', $this->components['select'])) {
            $this->components['select'] = [$expression];
        } else {
            $this->components['select'][] = $expression;
        }

        return $this;
    }
}