<?php

declare(strict_types=1);

namespace App\Infrastructure\Database\QueryBuilder;

use App\Infrastructure\Container\Contracts\ContainerInterface;
use App\Infrastructure\Container\Exceptions\ContainerException;
use App\Infrastructure\Container\Exceptions\NotFoundException;
use App\Infrastructure\Database\Cache\StatementCache;
use App\Infrastructure\Database\Connection\ConnectionManager;
use App\Infrastructure\Database\Contracts\ConnectionInterface;
use App\Infrastructure\Database\Contracts\QueryBuilderInterface;
use App\Infrastructure\Database\Debug\QueryDebugger;
use App\Infrastructure\Database\Exceptions\QueryException;
use Closure;
use PDOStatement;

abstract class QueryBuilder implements QueryBuilderInterface
{
    /**
     * Tabelle für die Abfrage
     */
    protected string $table = '';

    /**
     * Parameter für die Abfrage
     *
     * @var array<string, mixed>
     */
    protected array $parameters = [];

    /**
     * Die zu verwendende Datenbankverbindung
     */
    protected ?ConnectionInterface $connection = null;

    /**
     * Container für Dependency Injection
     */
    protected ?ContainerInterface $container = null;

    /**
     * WHERE-Klauselgruppe für die Abfrage
     */
    protected ?WhereClauseGroup $whereGroup = null;

    public function __construct(
        protected readonly ConnectionManager $connectionManager,
        ?ContainerInterface                  $container = null
    )
    {
        $this->container = $container;
    }

    /**
     * Setzt die Tabelle für die Abfrage
     *
     * @param string $table Name der Tabelle
     * @return static
     */
    public function table(string $table): static
    {
        $this->table = $table;
        return $this;
    }

    /**
     * Setzt die zu verwendende Datenbankverbindung
     *
     * @param string|ConnectionInterface $connection Name der Verbindung oder Verbindungsobjekt
     * @return static
     */
    public function connection(string|ConnectionInterface $connection): static
    {
        if (is_string($connection)) {
            $this->connection = $this->connectionManager->getConnection($connection);
        } else {
            $this->connection = $connection;
        }

        return $this;
    }

    /**
     * Gibt die aktuelle Datenbankverbindung zurück
     *
     * @return ConnectionInterface
     */
    protected function getConnection(): ConnectionInterface
    {
        if ($this->connection === null) {
            $this->connection = $this->connectionManager->getDefaultConnection();
        }

        return $this->connection;
    }

    /**
     * {@inheritdoc}
     */
    public function addParameters(array $params): static
    {
        $this->parameters = array_merge($this->parameters, $params);
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function execute(): PDOStatement
    {
        $sql = $this->toSql();

        if (empty($this->table)) {
            throw new QueryException('No table specified for query');
        }

        return $this->getConnection()->query($sql, $this->parameters);
    }

    /**
     * Erstellt eine neue verschachtelte Bedingungsgruppe mit AND-Verknüpfung
     *
     * @param Closure $callback Callback-Funktion, die die neue Gruppe konfiguriert
     * @return static
     */
    public function whereGroup(Closure $callback): static
    {
        if ($this->whereGroup === null) {
            $this->whereGroup = new WhereClauseGroup();
        }

        $this->whereGroup->whereGroup($callback);

        return $this;
    }

    /**
     * Erstellt eine neue verschachtelte Bedingungsgruppe mit OR-Verknüpfung
     *
     * @param Closure $callback Callback-Funktion, die die neue Gruppe konfiguriert
     * @return static
     */
    public function orWhereGroup(Closure $callback): static
    {
        if ($this->whereGroup === null) {
            $this->whereGroup = new WhereClauseGroup();
        }

        $this->whereGroup->orWhereGroup($callback);

        return $this;
    }

    /**
     * Aktiviert Debugging für diese Abfrage
     *
     * @param bool $withBacktrace Ob ein Backtrace für die Abfrage erstellt werden soll
     * @return static
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function debug(bool $withBacktrace = false): static
    {
        if ($this->container && $this->container->has(QueryDebugger::class)) {
            $debugger = $this->container->get(QueryDebugger::class);
            $debugger->enable($withBacktrace);
        }

        return $this;
    }

    /**
     * Gibt die ausformatierte SQL-Abfrage zurück (mit eingefügten Parameterwerten)
     *
     * @return string
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function toFormattedSql(): string
    {
        if ($this->container && $this->container->has(QueryDebugger::class)) {
            $debugger = $this->container->get(QueryDebugger::class);
            return $debugger->formatQuery($this->toSql(), $this->parameters);
        }

        return $this->toSql();
    }

    /**
     * Parst einen Wert für die SQL-Abfrage
     *
     * @param mixed $value Der zu parsende Wert
     * @return string
     */
    protected function parseValue(mixed $value): string
    {
        if ($this->isRawExpression($value)) {
            // Füge die Parameter aus der RawExpression hinzu
            $this->parameters = array_merge($this->parameters, $value->getParameters());
            return $value->toSql();
        }

        return '?';
    }

    /**
     * Prüft, ob ein Wert eine rohe SQL-Expression ist
     *
     * @param mixed $value Der zu prüfende Wert
     * @return bool
     */
    protected function isRawExpression(mixed $value): bool
    {
        return $value instanceof RawExpression;
    }

    /**
     * {@inheritdoc}
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    /**
     * Fügt eine WHERE-Bedingung hinzu
     *
     * @param string|RawExpression $column Spalte oder Raw-Expression
     * @param mixed $operator Operator oder Wert
     * @param mixed $value Wert (optional)
     * @param string $boolean AND oder OR
     * @return static
     */
    protected function addWhereCondition(string|RawExpression $column, mixed $operator = null, mixed $value = null, string $boolean = 'AND'): static
    {
        // Initialisiere die WhereClauseGroup falls noch nicht vorhanden
        if ($this->whereGroup === null) {
            $this->whereGroup = new WhereClauseGroup();
        }

        // Generiere einem eindeutigen Präfix für diese Bedingung, um Parameter-Kollisionen zu vermeiden
        $paramPrefix = 'where_' . uniqid();

        // Bei RawExpressions: Sicherstellen, dass die Parameter-Namen eindeutig sind
        if ($column instanceof RawExpression) {
            $originalParams = $column->getParameters();
            $newParams = [];

            // Parameter umschreiben mit eindeutigen Namen
            foreach ($originalParams as $key => $val) {
                $newKey = "{$paramPrefix}_{$key}";
                $newParams[$newKey] = $val;
            }

            // Neue RawExpression mit umbenannten Parametern erstellen
            if (!empty($originalParams)) {
                $expression = $column->getExpression();

                // Ersetze Parameter-Platzhalter in der Expression
                foreach ($originalParams as $key => $val) {
                    $expression = str_replace(":{$key}", ":{$paramPrefix}_{$key}", $expression);
                }

                $column = new RawExpression($expression, $newParams);
            }
        }

        // Delegiere direkt an die WhereClauseGroup für konsistente Behandlung
        if ($boolean === 'AND') {
            $this->whereGroup->where($column, $operator, $value);
        } else {
            $this->whereGroup->orWhere($column, $operator, $value);
        }

        // Aktualisiere die Hauptparameter mit den Parametern aus der WhereClauseGroup
        $this->parameters = array_merge($this->parameters, $this->whereGroup->getParameters());

        return $this;
    }

    /**
     * Fügt eine WHERE-Bedingung hinzu
     *
     * @param string|RawExpression $column Spalte oder Raw-Expression
     * @param mixed $operator Operator oder Wert
     * @param mixed $value Wert (optional)
     * @return static
     */
    public function where(string|RawExpression $column, mixed $operator = null, mixed $value = null): static
    {
        return $this->addWhereCondition($column, $operator, $value, 'AND');
    }

    /**
     * Fügt eine WHERE OR-Bedingung hinzu
     *
     * @param string|RawExpression $column Spalte oder Raw-Expression
     * @param mixed $operator Operator oder Wert
     * @param mixed $value Wert (optional)
     * @return static
     */
    public function orWhere(string|RawExpression $column, mixed $operator = null, mixed $value = null): static
    {
        return $this->addWhereCondition($column, $operator, $value, 'OR');
    }

    /**
     * Erstellt eine neue Raw-SQL-Expression
     *
     * @param string $expression Die rohe SQL-Expression
     * @param array $bindings Parameter-Bindungen für die Expression
     * @return RawExpression
     */
    protected function raw(string $expression, array $bindings = []): RawExpression
    {
        return new RawExpression($expression, $bindings);
    }

    /**
     * Invalidiert den Statement-Cache für Abfragen dieser Tabelle
     *
     * @return void
     * @throws ContainerException
     * @throws NotFoundException
     */
    protected function invalidateStatementCache(): void
    {
        if ($this->container && $this->container->has(StatementCache::class)) {
            $cache = $this->container->get(StatementCache::class);
            $cache->invalidateByPrefix($this->table);
        }
    }

    /**
     * Bereitet eine WHERE IN oder WHERE NOT IN Bedingung vor
     *
     * @param string $column Spalte
     * @param array $values Werte
     * @param bool $not Ob es sich um NOT IN handelt
     * @return string Die generierte SQL-Bedingung oder eine leere Zeichenkette
     */
    protected function prepareWhereInCondition(string $column, array $values, bool $not = false): string
    {
        if (empty($values)) {
            return $not ? '' : '0 = 1'; // Immer false bei leeren Werten für IN, keine Einschränkung für NOT IN
        }

        // Für kleinere Arrays können Gleichheitsbedingungen performanter sein als IN-Klauseln
        if (count($values) <= 3) {
            $operator = $not ? '!=' : '=';
            $combiner = $not ? ' AND ' : ' OR ';

            $conditions = array_map(function ($value) use ($column, $operator, $not) {
                $paramName = $this->createParameterName($not ? 'wherenotin' : 'wherein');
                $this->parameters[$paramName] = $value;
                return "{$column} {$operator} :{$paramName}";
            }, $values);

            return "(" . implode($combiner, $conditions) . ")";
        }

        // Standard IN/NOT IN-Klausel für größere Arrays
        $placeholders = array_map(function ($value) use ($not) {
            $paramName = $this->createParameterName($not ? 'wherenotin' : 'wherein');
            $this->parameters[$paramName] = $value;
            return ":{$paramName}";
        }, $values);

        $operator = $not ? 'NOT IN' : 'IN';
        return "{$column} {$operator} (" . implode(', ', $placeholders) . ")";
    }

    /**
     * Erstellt einen eindeutigen Parameternamen
     *
     * @param string $prefix Präfix für den Parameternamen
     * @return string Der generierte Parametername
     */
    protected function createParameterName(string $prefix = 'param'): string
    {
        static $counters = [];
        if (!isset($counters[$prefix])) {
            $counters[$prefix] = 0;
        }
        return $prefix . '_' . ($counters[$prefix]++);
    }
}