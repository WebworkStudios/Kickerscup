<?php

declare(strict_types=1);

namespace App\Core\Database;

use App\Core\Database\Clauses\WhereClause;
use App\Core\Database\Clauses\JoinClause;
use App\Core\Database\Clauses\GroupByClause;
use App\Core\Database\Clauses\HavingClause;
use App\Core\Database\Clauses\OrderByClause;
use App\Core\Database\Clauses\LimitOffsetClause;
use PDO;

/**
 * QueryBuilder für SQL-Abfragen
 *
 * Diese Klasse wurde neu strukturiert, um eine bessere Trennung der Verantwortlichkeiten zu erreichen.
 * Jeder Teil der SQL-Abfrage wird von einer spezialisierten Klasse verwaltet.
 */
class QueryBuilder
{
    /**
     * Zu selektierende Spalten
     */
    private array $select = ['*'];

    /**
     * Zu verwendende Tabelle
     */
    private string $from;

    /**
     * Verbindung zur Datenbank
     */
    private readonly Connection $connection;

    /**
     * WHERE-Klausel
     */
    private WhereClause $whereClause;

    /**
     * JOIN-Klausel
     */
    private JoinClause $joinClause;

    /**
     * GROUP BY-Klausel
     */
    private GroupByClause $groupByClause;

    /**
     * HAVING-Klausel
     */
    private HavingClause $havingClause;

    /**
     * ORDER BY-Klausel
     */
    private OrderByClause $orderByClause;

    /**
     * LIMIT/OFFSET-Klausel
     */
    private LimitOffsetClause $limitOffsetClause;

    /**
     * Konstruktor
     *
     * @param Connection $connection Datenbankverbindung
     * @param string $table Tabellenname
     */
    public function __construct(Connection $connection, string $table)
    {
        $this->connection = $connection;
        $this->from = $table;

        // Initialisieren der Klauseln
        $this->whereClause = new WhereClause();
        $this->joinClause = new JoinClause();
        $this->groupByClause = new GroupByClause();
        $this->havingClause = new HavingClause();
        $this->orderByClause = new OrderByClause();
        $this->limitOffsetClause = new LimitOffsetClause();
    }

    /**
     * Setzt die SELECT-Klausel
     *
     * @param string ...$columns Spalten
     * @return self
     */
    public function select(string ...$columns): self
    {
        $this->select = $columns;
        return $this;
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
        $this->whereClause->where($column, $operator, $value);
        return $this;
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
        $this->whereClause->orWhere($column, $operator, $value);
        return $this;
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
        $this->whereClause->whereIn($column, $values);
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
        $this->whereClause->orWhereIn($column, $values);
        return $this;
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
        $this->whereClause->whereNotIn($column, $values);
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
        $this->whereClause->orWhereNotIn($column, $values);
        return $this;
    }

    /**
     * Fügt eine WHERE NULL-Bedingung hinzu
     *
     * @param string $column Spalte
     * @return self
     */
    public function whereNull(string $column): self
    {
        $this->whereClause->whereNull($column);
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
        $this->whereClause->orWhereNull($column);
        return $this;
    }

    /**
     * Fügt eine WHERE NOT NULL-Bedingung hinzu
     *
     * @param string $column Spalte
     * @return self
     */
    public function whereNotNull(string $column): self
    {
        $this->whereClause->whereNotNull($column);
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
        $this->whereClause->orWhereNotNull($column);
        return $this;
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
        $this->whereClause->whereBetween($column, $min, $max);
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
        $this->whereClause->orWhereBetween($column, $min, $max);
        return $this;
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
        $this->joinClause->join($table, $first, $operator, $second, $type);
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
        $this->joinClause->leftJoin($table, $first, $operator, $second);
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
        $this->joinClause->rightJoin($table, $first, $operator, $second);
        return $this;
    }

    /**
     * Fügt eine GROUP BY-Klausel hinzu
     *
     * @param string ...$columns Spalten
     * @return self
     */
    public function groupBy(string ...$columns): self
    {
        $this->groupByClause->groupBy(...$columns);
        return $this;
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
        $this->havingClause->having($column, $operator, $value);
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
        $this->havingClause->orHaving($column, $operator, $value);
        return $this;
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
        $this->orderByClause->orderBy($column, $direction);
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
        $this->orderByClause->orderByDesc($column);
        return $this;
    }

    /**
     * Setzt eine LIMIT-Klausel
     *
     * @param int $limit Limit
     * @return self
     */
    public function limit(int $limit): self
    {
        $this->limitOffsetClause->limit($limit);
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
        $this->limitOffsetClause->offset($offset);
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
        $this->limitOffsetClause->forPage($page, $perPage);
        return $this;
    }

    /**
     * Generiert die SQL-Abfrage
     *
     * @return string
     */
    public function toSql(): string
    {
        // SELECT
        $query = 'SELECT ' . implode(', ', $this->select);

        // FROM
        $query .= ' FROM ' . $this->from;

        // JOIN
        $joinSql = $this->joinClause->toSql();
        if ($joinSql) {
            $query .= ' ' . $joinSql;
        }

        // WHERE
        $whereSql = $this->whereClause->toSql();
        if ($whereSql) {
            $query .= ' ' . $whereSql;
        }

        // GROUP BY
        $groupBySql = $this->groupByClause->toSql();
        if ($groupBySql) {
            $query .= ' ' . $groupBySql;
        }

        // HAVING
        $havingSql = $this->havingClause->toSql();
        if ($havingSql) {
            $query .= ' ' . $havingSql;
        }

        // ORDER BY
        $orderBySql = $this->orderByClause->toSql();
        if ($orderBySql) {
            $query .= ' ' . $orderBySql;
        }

        // LIMIT/OFFSET
        $limitOffsetSql = $this->limitOffsetClause->toSql();
        if ($limitOffsetSql) {
            $query .= ' ' . $limitOffsetSql;
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
        return array_merge(
            $this->whereClause->getBindings(),
            $this->havingClause->getBindings()
        );
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

        $this->limitOffsetClause->limit(1);

        $result = $this->get();

        return $result ? $result[0] : false;
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
     * Führt die Abfrage aus und gibt die Anzahl der Ergebnisse zurück
     *
     * @param string $column Spalte
     * @return int
     */
    public function count(string $column = '*'): int
    {
        $this->select = ["COUNT($column) as count"];

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
        return $this->connection->insert($this->from, $data);
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
            $this->from,
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
        $query = sprintf('UPDATE %s SET ', $this->from);

        $set = [];
        $params = [];

        foreach ($data as $column => $value) {
            $set[] = "$column = :update_$column";
            $params["update_$column"] = $value;
        }

        $query .= implode(', ', $set);

        // WHERE-Klausel hinzufügen
        $whereSql = $this->whereClause->toSql();
        if ($whereSql) {
            $query .= ' ' . $whereSql;
            $params = array_merge($params, $this->whereClause->getBindings());
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
        $query = sprintf('DELETE FROM %s', $this->from);

        // WHERE-Klausel hinzufügen
        $whereSql = $this->whereClause->toSql();
        $params = [];

        if ($whereSql) {
            $query .= ' ' . $whereSql;
            $params = $this->whereClause->getBindings();
        }

        $statement = $this->connection->query($query, $params);

        return $statement->rowCount();
    }

    /**
     * Führt ein TRUNCATE aus
     *
     * @return bool
     */
    public function truncate(): bool
    {
        $this->connection->query("TRUNCATE TABLE {$this->from}");
        return true;
    }

    /**
     * Gibt die Tabellenname zurück
     *
     * @return string
     */
    public function getTable(): string
    {
        return $this->from;
    }

    /**
     * Gibt die Connection zurück
     *
     * @return Connection
     */
    public function getConnection(): Connection
    {
        return $this->connection;
    }
}