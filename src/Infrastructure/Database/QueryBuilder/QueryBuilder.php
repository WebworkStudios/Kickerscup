<?php


declare(strict_types=1);

namespace App\Infrastructure\Database\QueryBuilder;

use App\Infrastructure\Container\Contracts\ContainerInterface;
use App\Infrastructure\Database\Cache\StatementCache;
use App\Infrastructure\Database\Connection\ConnectionManager;
use App\Infrastructure\Database\Contracts\ConnectionInterface;
use App\Infrastructure\Database\Contracts\QueryBuilderInterface;
use App\Infrastructure\Database\Debug\QueryDebugger;
use App\Infrastructure\Database\Exceptions\QueryException;
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
    public function getParameters(): array
    {
        return $this->parameters;
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
     * Parst einen Wert für die SQL-Abfrage
     *
     * @param mixed $value Der zu parsende Wert
     * @return string
     */
    protected function parseValue(mixed $value): string
    {
        if ($this->isRawExpression($value)) {
            return $value->toSql();
        }

        return '?';
    }

    /**
     * Erstellt einen eindeutigen Parameternamen
     *
     * @param string $prefix Präfix für den Parameternamen
     * @return string Der generierte Parametername
     */
    protected function createParameterName(string $prefix = 'param'): string
    {
        return $prefix . '_' . count($this->parameters);
    }

    /**
     * Aktiviert Debugging für diese Abfrage
     *
     * @param bool $withBacktrace Ob ein Backtrace für die Abfrage erstellt werden soll
     * @return static
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
     * Invalidiert den Statement-Cache für Abfragen dieser Tabelle
     *
     * @return void
     */
    protected function invalidateStatementCache(): void
    {
        if ($this->container && $this->container->has(StatementCache::class)) {
            $cache = $this->container->get(StatementCache::class);
            $cache->invalidateByPrefix($this->table);
        }
    }
}