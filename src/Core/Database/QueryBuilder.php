<?php

declare(strict_types=1);

namespace App\Core\Database;

use AllowDynamicProperties;
use App\Core\Database\Clauses\GroupByClause;
use App\Core\Database\Clauses\HavingClause;
use App\Core\Database\Clauses\JoinClause;
use App\Core\Database\Clauses\LimitOffsetClause;
use App\Core\Database\Clauses\OrderByClause;
use App\Core\Database\Clauses\WhereClause;
use App\Core\Database\Exceptions\QueryException;

/**
 * QueryBuilder für SQL-Abfragen
 *
 * Diese Klasse wurde neu strukturiert, um eine bessere Trennung der Verantwortlichkeiten zu erreichen.
 * Jeder Teil der SQL-Abfrage wird von einer spezialisierten Klasse verwaltet.
 */
#[AllowDynamicProperties] class QueryBuilder
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
    private ?WhereClause $whereClause = null;

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
     * @var array<QueryBuilder> $unions
     */
    private array $unions = [];

    /**
     * @var array<bool> $unionAll
     */
    private array $unionAll = [];

    private array $connectionConfig;

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

        // Hole die Verbindungskonfiguration von der Connection
        $this->connectionConfig = $connection->getConnectionConfig();

        // Restlicher Konstruktor bleibt unverändert
        $this->joinClause = new JoinClause();
        $this->groupByClause = new GroupByClause();
        $this->havingClause = new HavingClause();
        $this->orderByClause = new OrderByClause();
        $this->limitOffsetClause = new LimitOffsetClause();
    }

    /**
     * Fügt eine WHERE-Bedingung hinzu
     *
     * @param string|callable $column Spalte oder Callback für gruppierte Bedingungen
     * @param string|null $operator Operator (nur bei string für $column)
     * @param mixed|null $value Wert (nur bei string für $column)
     * @return self
     */
    public function where(string|callable $column, ?string $operator = null, mixed $value = null): self
    {
        // Wenn ein Callback übergeben wurde, Gruppierung verwenden
        if (is_callable($column)) {
            return $this->whereGroup($column);
        }

        // Standard-Verhalten beibehalten
        $this->getWhereClause()->where($column, $operator, $value);
        return $this;
    }

    /**
     * Gibt die WHERE-Klausel zurück oder erstellt eine neue
     *
     * @return WhereClause
     */
    private function getWhereClause(): WhereClause
    {
        if ($this->whereClause === null) {
            $this->whereClause = new WhereClause();
        }
        return $this->whereClause;
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
     * Führt die Abfrage aus und gibt eine Paginator-Instanz zurück
     *
     * @param int $page Seite
     * @param int $perPage Einträge pro Seite
     * @param string[]|null $columns Spalten
     * @param string|null $baseUrl Basis-URL für Links
     * @return Paginator
     */
    public function paginate(int $page, int $perPage, ?array $columns = null, ?string $baseUrl = null): Paginator
    {
        if ($columns !== null) {
            $this->select(...$columns);
        }

        // Gesamtanzahl der Einträge ermitteln
        $countBuilder = clone $this;
        $countBuilder->select = ['COUNT(*) as count'];
        $countBuilder->orderByClause->clearOrders();
        $countBuilder->limitOffsetClause->limit(null)->offset(null);
        $total = (int)$countBuilder->first()['count'];

        // Ergebnisse für die aktuelle Seite abrufen
        $this->forPage($page, $perPage);
        $items = $this->get();

        return new Paginator($items, $total, $perPage, $page, $baseUrl);
    }

    /**
     * Fügt eine SELECT-Klausel hinzu
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
     * Fügt eine WHERE LIKE-Bedingung hinzu
     *
     * @param string $column Spalte
     * @param string $value Wert (kann Wildcards % oder _ enthalten)
     * @return self
     */
    public function whereLike(string $column, string $value): self
    {
        $this->getWhereClause()->whereLike($column, $value);
        return $this;
    }

    /**
     * Fügt eine WHERE LIKE-Bedingung mit OR hinzu
     *
     * @param string $column Spalte
     * @param string $value Wert (kann Wildcards % oder _ enthalten)
     * @return self
     */
    public function orWhereLike(string $column, string $value): self
    {
        $this->getWhereClause()->orWhereLike($column, $value);
        return $this;
    }

    /**
     * Fügt eine WHERE NOT LIKE-Bedingung hinzu
     *
     * @param string $column Spalte
     * @param string $value Wert (kann Wildcards % oder _ enthalten)
     * @return self
     */
    public function whereNotLike(string $column, string $value): self
    {
        $this->getWhereClause()->whereNotLike($column, $value);
        return $this;
    }

    /**
     * Fügt eine WHERE NOT LIKE-Bedingung mit OR hinzu
     *
     * @param string $column Spalte
     * @param string $value Wert (kann Wildcards % oder _ enthalten)
     * @return self
     */
    public function orWhereNotLike(string $column, string $value): self
    {
        $this->getWhereClause()->orWhereNotLike($column, $value);
        return $this;
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
        $query = 'SELECT ' . implode(', ', $this->select);

        // FROM
        $query .= ' FROM ' . $this->from;

        // JOIN
        $joinSql = $this->joinClause->toSql();
        if ($joinSql) {
            $query .= ' ' . $joinSql;
        }

        // WHERE
        if ($this->whereClause !== null) {
            $whereSql = $this->whereClause->toSql();
            if ($whereSql) {
                $query .= ' ' . $whereSql;
            }
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

        // UNION
        foreach ($this->unions as $index => $unionQuery) {
            $unionType = $this->unionAll[$index] ? 'UNION ALL' : 'UNION';
            $query .= " $unionType " . $unionQuery->toSql();
        }

        // FOR UPDATE (am Ende der Abfrage hinzufügen)
        if (isset($this->forUpdate) && $this->forUpdate === true) {
            $query .= ' FOR UPDATE';
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
        $bindings = [];

        if ($this->whereClause !== null) {
            $bindings = array_merge($bindings, $this->whereClause->getBindings());
        }

        $bindings = array_merge(
            $bindings,
            $this->joinClause->getBindings(),
            $this->havingClause->getBindings()
        );

        // Bindings aus UNION-Abfragen hinzufügen
        foreach ($this->unions as $unionQuery) {
            $bindings = array_merge($bindings, $unionQuery->getBindings());
        }

        return $bindings;
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
     * @template T
     * @param class-string<T> $class
     * @return T|null
     */
    public function firstAs(string $class, ?array $columns = null): ?object
    {
        $data = $this->first($columns);
        if ($data === false) {
            return null;
        }

        // Neue PHP 8.4 Funktion für saubere Objekterstellung
        return new $class(...$data);
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
     * @throws QueryException
     */
    public function insert(array $data): int
    {
        $columns = array_keys($data);
        $placeholders = array_map(fn($column) => ":$column", $columns);

        $query = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->from,
            implode(', ', array_map([$this, 'sanitizeColumnName'], $columns)),
            implode(', ', $placeholders)
        );

        $statement = $this->connection->query($query, $data);

        return (int)$this->connection->getPdo()->lastInsertId();
    }

    /**
     * Überprüft und maskiert einen Tabellennamen
     *
     * @param string $table Tabellenname
     * @return string Maskierter Tabellenname
     * @throws \InvalidArgumentException wenn der Tabellenname ungültig ist
     */
    private function sanitizeTableName(string $table): string
    {
        // Validieren des Tabellennamens mit einem strengen Pattern
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            throw new \InvalidArgumentException("Ungültiger Tabellenname: '{$table}'");
        }

        // Mit Backticks umgeben (MySQL-spezifisch)
        $driver = $this->connectionConfig['driver'] ?? 'mysql';

        if ($driver === 'mysql') {
            return "`{$table}`";
        }

        // Für PostgreSQL mit Anführungszeichen umgeben
        if ($driver === 'pgsql') {
            return "\"{$table}\"";
        }

        return $table;
    }

    public function query(string $query, array $params = []): \PDOStatement
    {
        return $this->connection->query($query, $params);
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
        $params = [];

        if ($this->whereClause !== null) {
            $whereSql = $this->whereClause->toSql();
            if ($whereSql) {
                $query .= ' ' . $whereSql;
                $params = $this->whereClause->getBindings();
            }
        }

        $statement = $this->connection->query($query, $params);

        return $statement->rowCount();
    }

    /**
     * Führt die Abfrage aus und gibt die Summe einer Spalte zurück
     *
     * @param string $column Spalte
     * @return float|int
     */
    public function sum(string $column): float|int
    {
        $this->select = ["SUM($column) as aggregate"];

        $result = $this->first();

        return $result ? (float)($result['aggregate'] ?? 0) : 0;
    }

    /**
     * Führt die Abfrage aus und gibt den Durchschnitt einer Spalte zurück
     *
     * @param string $column Spalte
     * @return float|int
     */
    public function avg(string $column): float|int
    {
        $this->select = ["AVG($column) as aggregate"];

        $result = $this->first();

        return $result ? (float)($result['aggregate'] ?? 0) : 0;
    }

    /**
     * Führt die Abfrage aus und gibt den Minimalwert einer Spalte zurück
     *
     * @param string $column Spalte
     * @return float|int|string|null
     */
    public function min(string $column): float|int|string|null
    {
        $this->select = ["MIN($column) as aggregate"];

        $result = $this->first();

        return $result ? $result['aggregate'] : null;
    }

    /**
     * Führt die Abfrage aus und gibt den Maximalwert einer Spalte zurück
     *
     * @param string $column Spalte
     * @return float|int|string|null
     */
    public function max(string $column): float|int|string|null
    {
        $this->select = ["MAX($column) as aggregate"];

        $result = $this->first();

        return $result ? $result['aggregate'] : null;
    }

    /**
     * Führt die Abfrage aus und gibt den Modalwert (häufigster Wert) einer Spalte zurück
     *
     * @param string $column Spalte
     * @return mixed
     */
    public function mode(string $column): mixed
    {
        $subQuery = new QueryBuilder($this->connection, $this->from);
        $subQuery->select($column, "COUNT(*) as count")
            ->groupBy($column)
            ->orderByDesc("count")
            ->limit(1);

        // Kopieren aller relevanten Klauseln
        if ($this->whereClause->hasConditions()) {
            $whereSql = $this->whereClause->toSql();
            $bindings = $this->whereClause->getBindings();

            // Parameter-Bindung statt direktes Einsetzen des Spaltennamens
            $result = $this->connection->selectOne(
                "SELECT t1.{$column} FROM ({$subQuery->toSql()}) as t1 LIMIT 1",
                array_merge($bindings, $subQuery->getBindings())
            );
        } else {
            $result = $subQuery->first();
        }

        return $result ? $result[$column] : null;
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
     * Fügt eine WHERE-Bedingung mit einer Subquery hinzu
     *
     * @param string $column Spalte
     * @param string $operator Operator
     * @param SubQueryBuilder $subQuery Unterabfrage
     * @return self
     */
    public function whereSubQuery(string $column, string $operator, SubQueryBuilder $subQuery): self
    {
        $this->whereClause->whereSubQuery($column, $operator, $subQuery);
        return $this;
    }

    /**
     * Fügt eine WHERE-Bedingung mit einer Subquery und OR hinzu
     *
     * @param string $column Spalte
     * @param string $operator Operator
     * @param SubQueryBuilder $subQuery Unterabfrage
     * @return self
     */
    public function orWhereSubQuery(string $column, string $operator, SubQueryBuilder $subQuery): self
    {
        $this->whereClause->orWhereSubQuery($column, $operator, $subQuery);
        return $this;
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
     * Fügt FOR UPDATE zur Abfrage hinzu
     *
     * @return self
     */
    public function forUpdate(): self
    {
        $this->forUpdate = true;
        return $this;
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

    /**
     * Startet eine gruppierte Bedingung mit WHERE
     *
     * @param callable $callback Callback-Funktion
     * @return self
     */
    public function whereGroup(callable $callback): self
    {
        $this->whereClause->beginGroup();
        $callback($this);
        $this->whereClause->endGroup();
        return $this;
    }

    /**
     * Startet eine gruppierte Bedingung mit OR WHERE
     *
     * @param callable $callback Callback-Funktion
     * @return self
     */
    public function orWhereGroup(callable $callback): self
    {
        $this->whereClause->beginOrGroup();
        $callback($this);
        $this->whereClause->endGroup();
        return $this;
    }

    /**
     * Führt eine UNION durch
     *
     * @param QueryBuilder $query
     * @return self
     */
    public function union(QueryBuilder $query): self
    {
        $this->unions[] = $query;
        $this->unionAll[] = false;
        return $this;
    }

    /**
     * Führt eine UNION ALL durch
     *
     * @param QueryBuilder $query
     * @return self
     */
    public function unionAll(QueryBuilder $query): self
    {
        $this->unions[] = $query;
        $this->unionAll[] = true;
        return $this;
    }

    /**
     * Führt ein UPDATE aus
     *
     * @param string $table Tabellenname
     * @param array $data Daten
     * @param array $conditions Bedingungen für WHERE-Klausel als Schlüssel-Wert-Paare
     * @return int Anzahl der geänderten Zeilen
     */
    public function updateWhere(string $table, array $data, array $conditions): int
    {
        $set = array_map(fn($column) => $this->sanitizeColumnName($column) . " = :set_$column", array_keys($data));

        // Prepared-Statement-Parameter für SET-Klausel vorbereiten (mit Präfix)
        $setParams = [];
        foreach ($data as $key => $value) {
            $setParams["set_$key"] = $value;
        }

        // WHERE-Bedingungen als sichere Prepared-Statement-Parameter
        $whereClause = [];
        $whereParams = [];

        foreach ($conditions as $key => $value) {
            $whereClause[] = $this->sanitizeColumnName($key) . " = :where_$key";
            $whereParams["where_$key"] = $value;
        }

        $query = sprintf(
            'UPDATE %s SET %s WHERE %s',
            $this->sanitizeTableName($table),
            implode(', ', $set),
            implode(' AND ', $whereClause)
        );

        $statement = $this->query($query, array_merge($setParams, $whereParams));

        return $statement->rowCount();
    }

    /**
     * Überprüft und maskiert einen Spaltennamen
     *
     * @param string $column Spaltenname
     * @return string Maskierter Spaltenname
     */
    private function sanitizeColumnName(string $column): string
    {
        // Validieren des Spaltennamens mit einem strengen Pattern
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
            throw new \InvalidArgumentException("Ungültiger Spaltenname: '{$column}'");
        }

        // Mit Backticks umgeben (MySQL-spezifisch)
        $driver = $this->connectionConfig['driver'] ?? 'mysql';

        if ($driver === 'mysql') {
            return "`{$column}`";
        }

        // Für PostgreSQL mit Anführungszeichen umgeben
        if ($driver === 'pgsql') {
            return "\"{$column}\"";
        }

        return $column;
    }

    /**
     * Führt ein DELETE aus
     *
     * @param string $table Tabellenname
     * @param array $conditions Bedingungen für WHERE-Klausel als Schlüssel-Wert-Paare
     * @return int Anzahl der gelöschten Zeilen
     */
    public function deleteWhere(string $table, array $conditions): int
    {
        // WHERE-Bedingungen als sichere Prepared-Statement-Parameter
        $whereClause = [];
        $whereParams = [];

        foreach ($conditions as $key => $value) {
            $whereClause[] = $this->sanitizeColumnName($key) . " = :where_$key";
            $whereParams["where_$key"] = $value;
        }

        $query = sprintf(
            'DELETE FROM %s WHERE %s',
            $this->sanitizeTableName($table),
            implode(' AND ', $whereClause)
        );

        $statement = $this->query($query, $whereParams);

        return $statement->rowCount();
    }
}