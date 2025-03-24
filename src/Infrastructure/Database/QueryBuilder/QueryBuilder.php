<?php


declare(strict_types=1);

namespace App\Infrastructure\Database\QueryBuilder;

use App\Infrastructure\Database\Connection\ConnectionManager;
use App\Infrastructure\Database\Contracts\ConnectionInterface;
use App\Infrastructure\Database\Contracts\QueryBuilderInterface;
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
        protected readonly ConnectionManager $connectionManager
    )
    {
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
        // Hier könnte ein eigenes Expression-Objekt implementiert werden
        return is_object($value) && method_exists($value, 'toSql');
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
}