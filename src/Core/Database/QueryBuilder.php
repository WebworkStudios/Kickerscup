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
            $query .= ' HAVING ';

            $havingClauses = [];
            $havingParams = [];

            foreach ($this->components['having'] as $i => $having) {
                $key = "having_{$i}";

                if ($i > 0) {
                    $query .= " {$having['boolean']} ";
                }

                if ($having['type'] === 'basic') {
                    $havingClauses[] = "{$having['column']} {$having['operator']} :$key";
                    $havingParams[$key] = $having['value'];
                }
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